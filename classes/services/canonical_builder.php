<?php
/**
 * Canonical Builder Service - M1T6
 *
 * Builds canonical NB dataset from normalized inputs.
 * Merges source and target company data, normalizes structure, and collects citations.
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

class canonical_builder {

    /**
     * Normalize NB code format (NB-1 → NB1)
     *
     * @param string $nbcode NB code to normalize
     * @return string Normalized NB code
     */
    private function normalize_nbcode(string $nbcode): string {
        return str_replace('-', '', strtoupper($nbcode));
    }

    /**
     * Build canonical NB dataset from normalized inputs
     *
     * @param array $inputs Normalized inputs from raw_collector
     * @param array $canonical_nbkeys List of canonical NB codes to include
     * @param int $runid Run ID for logging
     * @return array Canonical dataset structure
     */
    public function build_canonical_nb_dataset($inputs, $canonical_nbkeys, $runid) {
        global $DB;

        // Defensive input validation
        if (!is_array($inputs)) {
            throw new \Exception("Invalid inputs provided to build_canonical_nb_dataset: not an array");
        }
        if (!is_array($canonical_nbkeys)) {
            throw new \Exception("Invalid canonical_nbkeys provided to build_canonical_nb_dataset: not an array");
        }
        if (!isset($inputs['nb']) || !is_array($inputs['nb'])) {
            throw new \Exception("No NB data found in inputs array");
        }

        error_log("[TRACE] build_canonical_nb_dataset: Processing " . count($canonical_nbkeys) . " canonical NBs from " . count($inputs['nb']) . " total NBs");

        $dataset = [
            'metadata' => [
                'runid' => $runid,
                'timestamp' => time(),
                'nb_count' => count($canonical_nbkeys),
                'total_available' => count($inputs['nb'] ?? []),
                'completion_rate' => count($canonical_nbkeys) > 0 ? count($canonical_nbkeys) / 15.0 : 0.0,
                'canonical_keys' => $canonical_nbkeys
            ],
            'nb_data' => [],
            'citations' => [],
            'processing_stats' => [
                'normalization_complete' => true,
                'canonical_keys_identified' => count($canonical_nbkeys),
                'total_citations' => 0,
                'avg_tokens_per_nb' => 0
            ]
        ];

        // Load all NB results from database in one query for efficiency
        $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);

        error_log(sprintf('[TRACE] canonical: Loaded %d NB records from database for run %d', count($nb_results), $runid));

        // Extract NB data with normalized structure
        $total_citations = 0;
        $total_tokens = 0;
        $loaded_count = 0;
        $missing_count = 0;

        foreach ($canonical_nbkeys as $nbcode) {
            // Find the NB record in the loaded results
            // Normalize both codes for comparison (NB-1 vs NB1)
            $normalized_nbcode = $this->normalize_nbcode($nbcode);

            $nb_record = null;
            foreach ($nb_results as $result) {
                if ($this->normalize_nbcode($result->nbcode) === $normalized_nbcode) {
                    $nb_record = $result;
                    break;
                }
            }

            if ($nb_record && !empty($nb_record->jsonpayload)) {
                // Decode jsonpayload
                $payload_data = json_decode($nb_record->jsonpayload, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log(sprintf('[TRACE] canonical: WARNING: Failed to decode %s: %s', $nbcode, json_last_error_msg()));
                    $missing_count++;
                    continue;
                }

                // Decode citations
                $citations = [];
                if (!empty($nb_record->citations)) {
                    $citations = json_decode($nb_record->citations, true);
                    if (!is_array($citations)) {
                        $citations = [];
                    }
                }

                // Store in canonical structure
                $dataset['nb_data'][$nbcode] = [
                    'nbcode' => $nbcode,
                    'status' => $nb_record->status ?? 'completed',
                    'data' => $payload_data,
                    'citations' => $citations,
                    'raw_payload' => $nb_record->jsonpayload,
                    'duration_ms' => $nb_record->durationms ?? 0,
                    'tokens_used' => $nb_record->tokensused ?? 0
                ];

                // Accumulate stats
                $total_citations += count($citations);
                $total_tokens += ($nb_record->tokensused ?? 0);
                $loaded_count++;

                error_log(sprintf('[TRACE] canonical: Merged %s: %d citations, %d tokens, status=%s',
                    $nbcode, count($citations), $nb_record->tokensused ?? 0, $nb_record->status ?? 'completed'));
            } else {
                // NB not found or empty payload
                $dataset['nb_data'][$nbcode] = [
                    'nbcode' => $nbcode,
                    'status' => 'missing',
                    'data' => [],
                    'citations' => [],
                    'raw_payload' => null,
                    'duration_ms' => 0,
                    'tokens_used' => 0
                ];
                $missing_count++;

                error_log(sprintf('[TRACE] canonical: WARNING: %s not found or empty in database', $nbcode));
            }
        }

        // Log final summary
        error_log(sprintf('[TRACE] canonical: Canonical dataset merge complete: %d NBs loaded, %d missing, %d total citations, %d total tokens',
            $loaded_count, $missing_count, $total_citations, $total_tokens));

        // Update processing stats
        $dataset['processing_stats']['total_citations'] = $total_citations;
        $dataset['processing_stats']['avg_tokens_per_nb'] = count($canonical_nbkeys) > 0
            ? round($total_tokens / count($canonical_nbkeys))
            : 0;

        // Add company metadata if available
        if (isset($inputs['company_source'])) {
            $dataset['metadata']['source_company'] = [
                'name' => $inputs['company_source']->name ?? 'Unknown',
                'sector' => $inputs['company_source']->sector ?? null,
                'ticker' => $inputs['company_source']->ticker ?? null
            ];
        }

        if (isset($inputs['company_target'])) {
            $dataset['metadata']['target_company'] = [
                'name' => $inputs['company_target']->name ?? 'Unknown',
                'sector' => $inputs['company_target']->sector ?? null,
                'ticker' => $inputs['company_target']->ticker ?? null
            ];
        }

        return $dataset;
    }

    /**
     * Calculate citation density validation
     */
    public function validate_citation_density($canonical_dataset): array {
        if (!isset($canonical_dataset['nb_data'])) {
            return ['average' => 0, 'meets_target' => false];
        }

        $total_citations = 0;
        $nb_count = 0;

        foreach ($canonical_dataset['nb_data'] as $nb_code => $nb_content) {
            if (isset($nb_content['citations']) && is_array($nb_content['citations'])) {
                $total_citations += count($nb_content['citations']);
                $nb_count++;
            }
        }

        $avg = $nb_count > 0 ? $total_citations / $nb_count : 0;

        return [
            'total' => $total_citations,
            'average' => round($avg, 1),
            'meets_target' => $avg >= 10
        ];
    }
}
