<?php
/**
 * Artifact Compatibility Adapter - v17.1 Unified Artifact Compatibility
 * 
 * Ensures permanent alignment between pipeline outputs and viewer expectations.
 * Provides normalization, aliasing, and schema adaptation for all synthesis artifacts.
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/log_service.php');
require_once(__DIR__ . '/artifact_repository.php');

/**
 * Unified compatibility adapter for synthesis artifacts
 * 
 * Handles all mismatches between pipeline generation and viewer consumption:
 * - Artifact name aliasing (normalized_inputs_v16 → synthesis_inputs)
 * - JSON schema normalization (report.sections → sections)
 * - Cache structure completion (v15_structure field injection)
 * - Cross-component compatibility guarantees
 */
class artifact_compatibility_adapter {
    
    /** @var artifact_repository */
    private $artifact_repo;
    
    /** @var \moodle_database */
    private $db;
    
    /** @var string */
    const COMPATIBILITY_VERSION = 'v17.1';
    
    /** @var array Artifact name mappings */
    private static $artifact_aliases = [
        // Legacy pipeline names → Standardized viewer names
        'normalized_inputs_v16' => 'synthesis_inputs',
        'final_bundle' => 'synthesis_bundle',
        'assembled_sections' => 'content_sections',
        'detected_patterns' => 'analysis_patterns',
        'target_bridge' => 'bridge_analysis',
        'diversity_metrics' => 'diversity_analysis'
    ];
    
    /** @var array Schema transformation rules */
    private static $schema_transformations = [
        'synthesis_inputs' => [
            'ensure_fields' => ['normalized_citations', 'company_source', 'company_target', 'nb', 'processing_stats'],
            'normalize_citations' => true,
            'extract_domains' => true
        ],
        'synthesis_bundle' => [
            'inject_v15_structure' => true,
            'complete_cache_fields' => true,
            'normalize_qa_structure' => true
        ]
    ];
    
    public function __construct() {
        global $DB;
        $this->db = $DB;
        $this->artifact_repo = new artifact_repository();
    }
    
    /**
     * Load artifact with full compatibility adaptation
     * 
     * @param int $runid Run ID
     * @param string $logical_name Logical artifact name (e.g., 'synthesis_inputs')
     * @param string $phase Pipeline phase (optional, auto-detected)
     * @return array|null Normalized artifact data
     */
    public function load_artifact($runid, $logical_name, $phase = null) {
        try {
            // Step 1: Resolve physical artifact name
            $physical_name = $this->resolve_artifact_name($logical_name);
            $detected_phase = $phase ?? $this->detect_phase($physical_name);
            
            log_service::info($runid, 
                "[Compatibility] Loading artifact: {$logical_name} → {$physical_name} (phase: {$detected_phase})");
            
            // Step 2: Load from database
            $artifact = $this->db->get_record('local_ci_artifact', [
                'runid' => $runid,
                'phase' => $detected_phase,
                'artifacttype' => $physical_name
            ]);
            
            if (!$artifact || empty($artifact->jsondata)) {
                log_service::warning($runid, 
                    "[Compatibility] Artifact not found: {$physical_name} in phase {$detected_phase}");
                return null;
            }
            
            // Step 3: Parse JSON data
            $data = json_decode($artifact->jsondata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_service::error($runid, 
                    "[Compatibility] JSON parse error in {$physical_name}: " . json_last_error_msg());
                return null;
            }
            
            // Step 4: Apply schema transformations
            $normalized_data = $this->normalize_schema($data, $logical_name, $runid);
            
            log_service::info($runid, 
                "[Compatibility] Artifact loaded and normalized: {$logical_name} " .
                "({$this->get_data_summary($normalized_data)})");
            
            return $normalized_data;
            
        } catch (\Exception $e) {
            log_service::error($runid, 
                "[Compatibility] Failed to load artifact {$logical_name}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save artifact with compatibility aliasing
     * 
     * @param int $runid Run ID
     * @param string $phase Pipeline phase
     * @param string $logical_name Logical artifact name
     * @param array $data Artifact data
     * @return bool Success status
     */
    public function save_artifact($runid, $phase, $logical_name, $data) {
        try {
            $physical_name = $this->resolve_artifact_name($logical_name);
            
            // Apply pre-save transformations
            $prepared_data = $this->prepare_for_storage($data, $logical_name, $runid);
            
            // Save using standard artifact repository
            $this->artifact_repo->save_artifact($runid, $phase, $physical_name, $prepared_data);
            
            log_service::info($runid, 
                "[Compatibility] Artifact saved: {$logical_name} → {$physical_name} " .
                "({$this->get_data_summary($prepared_data)})");
            
            return true;
            
        } catch (\Exception $e) {
            log_service::error($runid, 
                "[Compatibility] Failed to save artifact {$logical_name}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load synthesis bundle with complete field injection
     * 
     * @param int $runid Run ID
     * @return array|null Complete synthesis bundle
     */
    public function load_synthesis_bundle($runid) {
        try {
            // Load from cache first
            $synthesis = $this->db->get_record('local_ci_synthesis', ['runid' => $runid]);
            if (!$synthesis || empty($synthesis->jsoncontent)) {
                log_service::warning($runid, "[Compatibility] No synthesis cache found");
                return null;
            }
            
            $json_data = json_decode($synthesis->jsoncontent, true);
            if (!$json_data || !isset($json_data['synthesis_cache'])) {
                log_service::warning($runid, "[Compatibility] Invalid synthesis cache structure");
                return null;
            }
            
            $cache = $json_data['synthesis_cache'];
            
            // Build complete bundle with all expected fields
            $bundle = $this->build_complete_synthesis_bundle($cache, $runid);
            
            log_service::info($runid, 
                "[Compatibility] Synthesis bundle loaded with " . count($bundle) . " fields including v15_structure");
            
            return $bundle;
            
        } catch (\Exception $e) {
            log_service::error($runid, 
                "[Compatibility] Failed to load synthesis bundle: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save synthesis bundle with complete field caching
     * 
     * @param int $runid Run ID
     * @param array $result Synthesis result
     * @return bool Success status
     */
    public function save_synthesis_bundle($runid, $result) {
        try {
            $synthesis = $this->db->get_record('local_ci_synthesis', ['runid' => $runid]);
            if (!$synthesis) {
                log_service::warning($runid, "[Compatibility] No synthesis record found for caching");
                return false;
            }
            
            $json_data = !empty($synthesis->jsoncontent) ? json_decode($synthesis->jsoncontent, true) : [];
            if (!$json_data) {
                $json_data = [];
            }
            
            // Build complete cache structure
            $json_data['synthesis_cache'] = $this->build_complete_cache_structure($result, $runid);
            
            $synthesis->jsoncontent = json_encode($json_data);
            $synthesis->updatedat = time();
            
            $this->db->update_record('local_ci_synthesis', $synthesis);
            
            log_service::info($runid, 
                "[Compatibility] Synthesis bundle cached with v17.1 compatibility structure");
            
            return true;
            
        } catch (\Exception $e) {
            log_service::error($runid, 
                "[Compatibility] Failed to save synthesis bundle: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resolve logical artifact name to physical storage name
     */
    private function resolve_artifact_name($logical_name) {
        // Check if it's already a physical name
        if (in_array($logical_name, array_keys(self::$artifact_aliases))) {
            return $logical_name; // It's a physical name
        }
        
        // Find reverse mapping (logical → physical)
        $reverse_map = array_flip(self::$artifact_aliases);
        if (isset($reverse_map[$logical_name])) {
            return $reverse_map[$logical_name];
        }
        
        // No mapping found, use as-is
        return $logical_name;
    }
    
    /**
     * Detect pipeline phase from artifact name
     */
    private function detect_phase($artifact_name) {
        $phase_map = [
            'normalized_inputs_v16' => 'citation_normalization',
            'normalized_inputs' => 'nb_orchestration',
            'rebalanced_inputs' => 'retrieval_rebalancing',
            'detected_patterns' => 'discovery',
            'target_bridge' => 'discovery',
            'assembled_sections' => 'assembler',
            'drafted_sections' => 'synthesis',
            'final_bundle' => 'synthesis',
            'qa_scores' => 'qa',
            'diversity_metrics' => 'retrieval_rebalancing'
        ];
        
        return $phase_map[$artifact_name] ?? 'synthesis';
    }
    
    /**
     * Normalize artifact schema according to transformation rules
     */
    private function normalize_schema($data, $logical_name, $runid) {
        if (!isset(self::$schema_transformations[$logical_name])) {
            return $data; // No transformations needed
        }
        
        $rules = self::$schema_transformations[$logical_name];
        $normalized = $data;
        
        // Ensure required fields exist
        if (isset($rules['ensure_fields'])) {
            foreach ($rules['ensure_fields'] as $field) {
                if (!isset($normalized[$field])) {
                    $normalized[$field] = $this->get_default_field_value($field);
                    log_service::info($runid, 
                        "[Compatibility] Added missing field: {$field} to {$logical_name}");
                }
            }
        }
        
        // Normalize citations structure
        if (!empty($rules['normalize_citations']) && isset($normalized['normalized_citations'])) {
            $normalized['normalized_citations'] = $this->normalize_citations_structure($normalized['normalized_citations'], $runid);
        }
        
        // Extract domains from citations
        if (!empty($rules['extract_domains']) && isset($normalized['normalized_citations'])) {
            $normalized['domain_analysis'] = $this->extract_domain_analysis($normalized['normalized_citations'], $runid);
        }
        
        return $normalized;
    }
    
    /**
     * Build complete synthesis bundle with all expected fields
     */
    private function build_complete_synthesis_bundle($cache, $runid) {
        $bundle = [
            // Core render fields
            'html' => $cache['render']['html'] ?? '',
            'json' => $cache['render']['json'] ?? '{}',
            'voice_report' => $cache['render']['voice_report'] ?? '{}',
            'selfcheck_report' => $cache['render']['selfcheck_report'] ?? '{}',
            'qa_report' => $cache['render']['qa_report'] ?? '{}',
            
            // Citation fields
            'citations' => $cache['citations'] ?? [],
            'sources' => $cache['sources'] ?? [],
            
            // v17.1 Complete fields
            'coherence_report' => $cache['render']['coherence_report'] ?? '{}',
            'pattern_alignment_report' => $cache['render']['pattern_alignment_report'] ?? '{}',
            'appendix_notes' => $cache['render']['appendix_notes'] ?? '',
            
            // Critical v15_structure field
            'v15_structure' => $cache['v15_structure'] ?? $this->extract_v15_structure($cache, $runid)
        ];
        
        log_service::info($runid, 
            "[Compatibility] Built complete synthesis bundle with v15_structure field");
        
        return $bundle;
    }
    
    /**
     * Build complete cache structure for v17.1 compatibility
     */
    private function build_complete_cache_structure($result, $runid) {
        $cache_structure = [
            'version' => 'v17.1-unified-compatibility',
            'built_at' => time(),
            'compatibility_adapter' => self::COMPATIBILITY_VERSION,
            'citations' => $result['citations'] ?? [],
            'sources' => $result['sources'] ?? [],
            'render' => [
                'html' => $result['html'] ?? '',
                'json' => $result['json'] ?? '{}',
                'voice_report' => $result['voice_report'] ?? '{}',
                'selfcheck_report' => $result['selfcheck_report'] ?? '{}',
                'qa_report' => $result['qa_report'] ?? '{}',
                'coherence_report' => $result['coherence_report'] ?? '{}',
                'pattern_alignment_report' => $result['pattern_alignment_report'] ?? '{}',
                'appendix_notes' => $result['appendix_notes'] ?? ''
            ],
            'v15_structure' => $this->extract_v15_structure_from_result($result, $runid)
        ];
        
        log_service::info($runid, 
            "[Compatibility] Built complete cache structure with v17.1 unified compatibility");
        
        return $cache_structure;
    }
    
    /**
     * Extract v15_structure from JSON content
     */
    private function extract_v15_structure($cache, $runid) {
        if (isset($cache['render']['json'])) {
            $json_data = json_decode($cache['render']['json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                log_service::info($runid, 
                    "[Compatibility] Extracted v15_structure from JSON render content");
                return $json_data;
            }
        }
        
        log_service::warning($runid, 
            "[Compatibility] Could not extract v15_structure, using empty structure");
        return ['qa' => ['scores' => [], 'warnings' => []]];
    }
    
    /**
     * Extract v15_structure directly from synthesis result
     */
    private function extract_v15_structure_from_result($result, $runid) {
        if (isset($result['json'])) {
            $json_data = json_decode($result['json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                return $json_data;
            }
        }
        
        return ['qa' => ['scores' => [], 'warnings' => []]];
    }
    
    /**
     * Normalize citations to ensure consistent structure
     */
    private function normalize_citations_structure($citations, $runid) {
        $normalized = [];
        $domain_count = 0;
        
        foreach ($citations as $citation) {
            if (is_string($citation)) {
                // Convert string URL to object
                $normalized[] = [
                    'url' => $citation,
                    'domain' => $this->extract_domain_from_url($citation),
                    'title' => 'External Source',
                    'type' => 'web'
                ];
                $domain_count++;
            } elseif (is_array($citation)) {
                // Ensure required fields exist
                $normalized_citation = $citation;
                if (!isset($citation['domain']) && isset($citation['url'])) {
                    $normalized_citation['domain'] = $this->extract_domain_from_url($citation['url']);
                    $domain_count++;
                }
                if (!isset($citation['type'])) {
                    $normalized_citation['type'] = 'web';
                }
                $normalized[] = $normalized_citation;
            }
        }
        
        if ($domain_count > 0) {
            log_service::info($runid, 
                "[Compatibility] Normalized {$domain_count} citation domains");
        }
        
        return $normalized;
    }
    
    /**
     * Extract domain analysis from citations
     */
    private function extract_domain_analysis($citations, $runid) {
        $domains = [];
        
        foreach ($citations as $citation) {
            $domain = null;
            if (is_array($citation) && isset($citation['domain'])) {
                $domain = $citation['domain'];
            } elseif (is_array($citation) && isset($citation['url'])) {
                $domain = $this->extract_domain_from_url($citation['url']);
            } elseif (is_string($citation)) {
                $domain = $this->extract_domain_from_url($citation);
            }
            
            if ($domain) {
                $domains[$domain] = ($domains[$domain] ?? 0) + 1;
            }
        }
        
        arsort($domains);
        
        return [
            'total_domains' => count($domains),
            'total_citations' => count($citations),
            'top_domains' => array_slice($domains, 0, 10, true),
            'diversity_ratio' => count($domains) / max(1, count($citations))
        ];
    }
    
    /**
     * Extract domain from URL
     */
    private function extract_domain_from_url($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'unknown';
    }
    
    /**
     * Get default value for missing field
     */
    private function get_default_field_value($field) {
        $defaults = [
            'normalized_citations' => [],
            'company_source' => null,
            'company_target' => null,
            'nb' => [],
            'processing_stats' => ['nb_count' => 0, 'citation_count' => 0]
        ];
        
        return $defaults[$field] ?? null;
    }
    
    /**
     * Prepare data for storage (pre-save transformations)
     */
    private function prepare_for_storage($data, $logical_name, $runid) {
        // Add compatibility metadata
        $prepared = $data;
        $prepared['_compatibility'] = [
            'version' => self::COMPATIBILITY_VERSION,
            'logical_name' => $logical_name,
            'saved_at' => time(),
            'runid' => $runid
        ];
        
        return $prepared;
    }
    
    /**
     * Get summary of data for logging
     */
    private function get_data_summary($data) {
        if (!is_array($data)) {
            return gettype($data);
        }
        
        $summary_parts = [];
        
        if (isset($data['normalized_citations'])) {
            $summary_parts[] = count($data['normalized_citations']) . ' citations';
        }
        if (isset($data['citations'])) {
            $summary_parts[] = count($data['citations']) . ' citations';
        }
        if (isset($data['sources'])) {
            $summary_parts[] = count($data['sources']) . ' sources';
        }
        if (isset($data['html'])) {
            $summary_parts[] = 'HTML render';
        }
        if (isset($data['v15_structure'])) {
            $summary_parts[] = 'v15_structure';
        }
        
        return empty($summary_parts) ? count($data) . ' fields' : implode(', ', $summary_parts);
    }
    
    /**
     * Get compatibility version info
     */
    public static function get_compatibility_info() {
        return [
            'version' => self::COMPATIBILITY_VERSION,
            'artifact_aliases' => self::$artifact_aliases,
            'schema_transformations' => array_keys(self::$schema_transformations),
            'description' => 'Unified artifact compatibility adapter ensuring permanent alignment between pipeline and viewer'
        ];
    }
}