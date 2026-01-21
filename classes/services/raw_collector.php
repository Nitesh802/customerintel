<?php
/**
 * Raw Collector Service - M1T5
 *
 * Handles NB collection and normalization from database.
 * Extracts NB results, normalizes data structures, and processes citations.
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/artifact_compatibility_adapter.php');
require_once(__DIR__ . '/log_service.php');

class raw_collector {

    /**
     * Get normalized inputs from NB results
     *
     * Reads all NB1-NB15 results for the run and optional target company,
     * decodes JSON payloads, and converts to canonical structure based on
     * the NB → Field Normalization Map.
     *
     * @param int $runid Run ID to fetch NB results for
     * @return array Normalized data structure with source/target company data
     */
    public function get_normalized_inputs(int $runid): array {
        global $DB;

        // v17.1 Unified Compatibility: Use adapter for all artifact loading
        $adapter = new artifact_compatibility_adapter();

        // 0. Check for normalized citation artifacts first (v16 enhancement)
        $normalized_artifact = $adapter->load_artifact($runid, 'synthesis_inputs');
        if ($normalized_artifact) {
            return $this->build_inputs_from_normalized_artifact($runid, $normalized_artifact);
        }

        // 0.1. Auto-rebuild: If normalized artifact missing, attempt to reconstruct it
        \local_customerintel\services\log_service::warning($runid,
            "Synthesis input auto-rebuild triggered: normalized artifact missing for run {$runid}");

        if ($this->attempt_normalization_reconstruction($runid)) {
            // Try loading the artifact again after reconstruction
            $normalized_artifact = $this->load_normalized_citation_artifact($runid);
            if ($normalized_artifact) {
                \local_customerintel\services\log_service::info($runid,
                    "Synthesis input auto-rebuild successful: using reconstructed normalized artifact");
                return $this->build_inputs_from_normalized_artifact($runid, $normalized_artifact);
            }
        }

        \local_customerintel\services\log_service::warning($runid,
            "Synthesis input auto-rebuild failed: falling back to direct database access");

        // 1. Load and validate run record (fallback to database)
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        if (!$run) {
            throw new \invalid_parameter_exception("Run ID {$runid} not found");
        }

        if ($run->status !== 'completed') {
            throw new \invalid_parameter_exception("Run ID {$runid} status is '{$run->status}', must be 'completed'");
        }

        // 2. Load source company (required)
        $company_source = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
        if (!$company_source) {
            throw new \invalid_parameter_exception("Source company ID {$run->companyid} not found");
        }

        // 3. Load target company (optional)
        $company_target = null;
        if ($run->targetcompanyid) {
            $company_target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
            if (!$company_target) {
                debugging("Target company ID {$run->targetcompanyid} not found, proceeding without target", DEBUG_DEVELOPER);
            }
        }

        // 4. Fetch all NB results for this run
        $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');

        // 5. Process and normalize NB data
        $nb_data = [];
        $all_citations = [];
        $nb_count = 0;
        $citation_count = 0;

        foreach ($nb_results as $result) {
            $nb_count++;

            // Decode JSON payload safely
            $payload = null;
            if (!empty($result->jsonpayload)) {
                $payload = json_decode($result->jsonpayload, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    debugging("Failed to decode JSON for {$result->nbcode}: " . json_last_error_msg(), DEBUG_DEVELOPER);
                    $payload = null;
                }
            }

            // Decode citations safely
            $citations = [];
            if (!empty($result->citations)) {
                $citations = json_decode($result->citations, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    debugging("Failed to decode citations for {$result->nbcode}: " . json_last_error_msg(), DEBUG_DEVELOPER);
                    $citations = [];
                } else {
                    $citation_count += count($citations);
                    $all_citations = array_merge($all_citations, $citations);
                }
            }

            // Normalize according to NB → Field Normalization Map
            $normalized = $this->normalize_nb_data($result->nbcode, $payload);

            // Normalize the NB code key for consistent access
            $canonical_nbcode = $this->nbcode_normalize($result->nbcode);

            $nb_data[$canonical_nbcode] = [
                'status' => $result->status,
                'data' => $normalized,
                'citations' => $citations,
                'raw_payload' => $payload,
                'duration_ms' => $result->durationms,
                'tokens_used' => $result->tokensused
            ];

            // Also create alias entries for backward compatibility during transition
            $aliases = $this->nbcode_aliases($canonical_nbcode);
            foreach ($aliases as $alias) {
                if ($alias !== $canonical_nbcode && !isset($nb_data[$alias])) {
                    $nb_data[$alias] = &$nb_data[$canonical_nbcode]; // Reference to avoid duplication
                }
            }
        }

        // 6. Construct target hints for bridge building
        $target_hints = null;
        if ($company_target) {
            $target_hints = [
                'name' => $company_target->name ?? '',
                'sector' => $company_target->sector ?? '',
                'website' => $company_target->website ?? '',
                'ticker' => $company_target->ticker ?? '',
                'metadata' => !empty($company_target->metadata) ? json_decode($company_target->metadata, true) : null
            ];
        }

        // 7. Log processing results
        debugging("Synthesis input processing for run {$runid}: {$nb_count} NBs found, {$citation_count} total citations", DEBUG_DEVELOPER);

        // 8. Construct final inputs structure
        $inputs = [
            'run' => $run,
            'company_source' => $company_source,
            'company_target' => $company_target,
            'nb' => $nb_data,
            'citations' => array_unique($all_citations, SORT_REGULAR),
            'target_hints' => $target_hints,
            'processing_stats' => [
                'nb_count' => $nb_count,
                'citation_count' => $citation_count,
                'completed_nbs' => count(array_filter($nb_data, function($nb) {
                    return isset($nb['status']) && $nb['status'] === 'completed';
                })),
                'missing_nbs' => $this->get_missing_nbs(array_keys($nb_data))
            ]
        ];

        return $inputs;
    }

    /**
     * Normalize NB data according to field mapping
     */
    private function normalize_nb_data(string $nbcode, ?array $payload): array {
        if (empty($payload)) {
            return [];
        }

        // Implementation would contain the full NB normalization mapping
        // This is simplified for the defensive programming example
        return $payload;
    }

    /**
     * Normalize NB code to canonical form - handles null inputs
     *
     * Converts any of: "NB-1", "nb-1", "NB_1", "nb1", "Nb01" → "NB1"
     */
    private function nbcode_normalize(string $code): string {
        if (empty($code)) {
            return 'NB1'; // Default fallback
        }

        // Extract digits from the code
        preg_match('/\d+/', $code, $matches);
        if (empty($matches)) {
            return strtoupper($code);
        }

        $number = (int)$matches[0]; // Convert to int to remove leading zeros
        return "NB" . $number;
    }

    /**
     * Generate common aliases for an NB code
     */
    private function nbcode_aliases(string $canonical_code): array {
        if (empty($canonical_code)) {
            return ['NB1'];
        }

        // Extract number from canonical form
        preg_match('/\d+/', $canonical_code, $matches);
        if (empty($matches)) {
            return [$canonical_code];
        }

        $number = $matches[0];
        $padded_number = str_pad($number, 2, '0', STR_PAD_LEFT);

        return [
            $canonical_code,              // "NB1"
            "NB-" . $number,             // "NB-1"
            "NB_" . $number,             // "NB_1"
            "nb" . $number,              // "nb1"
            "nb-" . $number,             // "nb-1"
            "nb_" . $number,             // "nb_1"
            "Nb" . $padded_number,       // "Nb01"
            strtolower($canonical_code)   // "nb1"
        ];
    }

    /**
     * Get missing NBs from expected set
     */
    private function get_missing_nbs($found_nbs): array {
        if (!is_array($found_nbs)) {
            $found_nbs = [];
        }

        // Core NBs required for basic synthesis
        $core_nbs = ['NB1', 'NB2', 'NB3', 'NB4', 'NB7', 'NB12', 'NB14', 'NB15'];

        // Optional NBs that can be skipped without blocking synthesis
        $optional_nbs = ['NB5', 'NB6', 'NB8', 'NB9', 'NB10', 'NB11', 'NB13'];

        $all_expected_nbs = array_merge($core_nbs, $optional_nbs);
        $missing_nbs = array_diff($all_expected_nbs, $found_nbs);

        // Separate core vs optional missing NBs for better diagnostics
        $missing_core = array_intersect($missing_nbs, $core_nbs);
        $missing_optional = array_intersect($missing_nbs, $optional_nbs);

        // Log the distinction for diagnostics
        if (!empty($missing_core)) {
            debugging("Missing core NBs (may impact synthesis): " . implode(', ', $missing_core), DEBUG_DEVELOPER);
        }
        if (!empty($missing_optional)) {
            debugging("Missing optional NBs (synthesis can proceed): " . implode(', ', $missing_optional), DEBUG_DEVELOPER);
        }

        return $missing_nbs;
    }

    /**
     * Load normalized citation artifact from repository
     */
    private function load_normalized_citation_artifact(int $runid): ?array {
        global $DB;

        try {
            // Check for normalized_inputs_v16 artifact
            $artifact = $DB->get_record('local_ci_artifact', [
                'runid' => $runid,
                'phase' => 'citation_normalization',
                'artifacttype' => 'normalized_inputs_v16'
            ]);

            if ($artifact && !empty($artifact->jsondata)) {
                $data = json_decode($artifact->jsondata, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['normalized_citations'])) {
                    \local_customerintel\services\log_service::info($runid,
                        "Artifact loaded successfully: normalized_inputs_v16_{$runid}.json found with " .
                        count($data['normalized_citations']) . " citations");
                    return $data;
                }
            }

            \local_customerintel\services\log_service::warning($runid,
                "No normalized artifact found — rebuilding synthesis inputs from NB results");
            return null;

        } catch (\Exception $e) {
            debugging("Error loading normalized citation artifact for run {$runid}: " . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Attempt to reconstruct normalized citation artifact
     */
    private function attempt_normalization_reconstruction(int $runid): bool {
        global $DB;

        try {
            // Check if we have NB results that can be normalized
            $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);

            if (empty($nb_results)) {
                \local_customerintel\services\log_service::error($runid,
                    "Cannot reconstruct normalization: no NB results found for run {$runid}");
                return false;
            }

            \local_customerintel\services\log_service::info($runid,
                "Auto-rebuild: Found " . count($nb_results) . " NB results, attempting normalization reconstruction");

            // Load and execute the normalization process
            require_once(__DIR__ . '/nb_orchestrator.php');
            $orchestrator = new \local_customerintel\services\nb_orchestrator();

            // Use reflection to access the protected normalize_citation_domains method
            $reflection = new \ReflectionClass($orchestrator);
            $normalize_method = $reflection->getMethod('normalize_citation_domains');
            $normalize_method->setAccessible(true);

            // Execute normalization
            $normalize_method->invoke($orchestrator, $runid);

            \local_customerintel\services\log_service::info($runid,
                "Auto-rebuild: Citation domain normalization completed for run {$runid}");

            return true;

        } catch (\Exception $e) {
            \local_customerintel\services\log_service::error($runid,
                "Auto-rebuild failed: normalization reconstruction error - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Build inputs structure from normalized artifact
     */
    private function build_inputs_from_normalized_artifact(int $runid, array $normalized_artifact): array {
        global $DB;

        // Load company data
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $company_source = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
        $company_target = null;
        if ($run->targetcompanyid) {
            $company_target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
        }

        // Get normalized citations with domain fields
        $normalized_citations = $normalized_artifact['normalized_citations'] ?? [];
        $domain_frequency = $normalized_artifact['domain_frequency_map'] ?? [];

        // Build NB data structure from original database, but enhance citations with domains
        $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');
        $nb_data = [];
        $citation_index = 0;

        foreach ($nb_results as $result) {
            $payload = null;
            if (!empty($result->jsonpayload)) {
                $payload = json_decode($result->jsonpayload, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $payload = null;
                }
            }

            // Enhance citations in payload with domain data
            $enhanced_citations = [];
            if ($payload && isset($payload['citations'])) {
                foreach ($payload['citations'] as $citation) {
                    if ($citation_index < count($normalized_citations)) {
                        $normalized_citation = $normalized_citations[$citation_index];
                        // Merge original citation with normalized domain data
                        if (is_string($citation)) {
                            $enhanced_citations[] = $normalized_citation;
                        } else {
                            $enhanced_citations[] = array_merge((array)$citation, $normalized_citation);
                        }
                        $citation_index++;
                    } else {
                        $enhanced_citations[] = $citation;
                    }
                }
                $payload['citations'] = $enhanced_citations;
            } else if (!empty($result->citations)) {
                // Fall back to citations column if not in payload
                $enhanced_citations = json_decode($result->citations, true);
                if (!is_array($enhanced_citations)) {
                    $enhanced_citations = [];
                }
            }

            // Normalize NB code for consistent access (NB-1 → NB1)
            $canonical_nbcode = $this->nbcode_normalize($result->nbcode);

            $nb_data[$canonical_nbcode] = [
                'payload' => $payload,
                'data' => $payload,  // Map payload to data for pattern detection compatibility
                'citations' => $enhanced_citations,  // Add citations array for canonical_builder
                'status' => $result->status ?? 'completed',
                'raw_payload' => $result->jsonpayload,
                'duration_ms' => $result->durationms ?? 0,
                'tokens_used' => $result->tokensused ?? 0,
                'metadata' => [
                    'tokens_used' => $result->tokensused ?? 0,
                    'duration_ms' => $result->durationms ?? 0,
                    'attempts' => $result->attempts ?? 1,
                    'status' => $result->status ?? 'completed'
                ]
            ];
        }

        return [
            'company_source' => $company_source,
            'company_target' => $company_target,
            'nb' => $nb_data,
            'diversity_metadata' => [
                'source' => 'normalized_artifact_v16',
                'domain_frequency' => $domain_frequency,
                'total_citations' => count($normalized_citations)
            ],
            'run' => $run
        ];
    }
}
