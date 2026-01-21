<?php
/**
 * Legacy Synthesis Record Builder
 * 
 * Builds synthesis_record.json in legacy format from v17.1 artifacts
 * for backward compatibility with older viewer components.
 * 
 * Combines data from:
 * - /synthesis/final_bundle.json
 * - /retrieval_rebalancing/diversity_metrics.json  
 * - /citation_normalization/normalized_inputs_v16.json
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
 * Builds legacy synthesis_record.json from v17.1 artifacts
 */
class legacy_synthesis_record_builder {
    
    /** @var \moodle_database */
    private $db;
    
    /** @var artifact_repository */
    private $artifact_repo;
    
    public function __construct() {
        global $DB;
        $this->db = $DB;
        $this->artifact_repo = new artifact_repository();
    }
    
    /**
     * Build legacy synthesis_record.json from current run artifacts
     * 
     * @param int $runid Run ID to build legacy record for
     * @return bool Success status
     */
    public function build_legacy_synthesis_record($runid) {
        try {
            log_service::info($runid, 
                '[LegacyRecord] Starting synthesis_record.json build from v17.1 artifacts');
            
            // 1. Collect required artifacts
            $artifacts = $this->collect_artifacts($runid);
            
            // 2. Load company information
            $companies = $this->load_company_data($runid);
            
            // 3. Build legacy record structure
            $legacy_record = $this->build_legacy_structure($runid, $artifacts, $companies);
            
            // 4. Save as synthesis_record artifact
            $this->artifact_repo->save_artifact($runid, 'synthesis', 'synthesis_record', $legacy_record);
            
            // 5. Log success
            $section_count = count($legacy_record['sections'] ?? []);
            $citation_count = count($legacy_record['citations'] ?? []);
            
            log_service::info($runid, 
                "[LegacyRecord] synthesis_record.json built successfully with {$section_count} sections and {$citation_count} citations");
            
            return true;
            
        } catch (\Exception $e) {
            log_service::error($runid, 
                '[LegacyRecord] Failed to build synthesis_record.json: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Collect required artifacts for legacy record building
     * 
     * @param int $runid Run ID
     * @return array Artifact data
     */
    private function collect_artifacts($runid) {
        $artifacts = [
            'final_bundle' => null,
            'diversity_metrics' => null,
            'normalized_inputs' => null
        ];
        
        // Load final_bundle.json
        $final_bundle_artifact = $this->db->get_record('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'synthesis',
            'artifacttype' => 'final_bundle'
        ]);
        
        if ($final_bundle_artifact && !empty($final_bundle_artifact->jsondata)) {
            $artifacts['final_bundle'] = json_decode($final_bundle_artifact->jsondata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_service::warning($runid, 
                    '[LegacyRecord] final_bundle.json has invalid JSON, continuing with empty data');
                $artifacts['final_bundle'] = null;
            }
        }
        
        // Load diversity_metrics.json
        $diversity_artifact = $this->db->get_record('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'retrieval_rebalancing',
            'artifacttype' => 'diversity_metrics'
        ]);
        
        if ($diversity_artifact && !empty($diversity_artifact->jsondata)) {
            $artifacts['diversity_metrics'] = json_decode($diversity_artifact->jsondata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_service::warning($runid, 
                    '[LegacyRecord] diversity_metrics.json has invalid JSON, continuing with empty data');
                $artifacts['diversity_metrics'] = null;
            }
        }
        
        // Load normalized_inputs_v16.json
        $normalized_artifact = $this->db->get_record('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'citation_normalization',
            'artifacttype' => 'normalized_inputs_v16'
        ]);
        
        if ($normalized_artifact && !empty($normalized_artifact->jsondata)) {
            $artifacts['normalized_inputs'] = json_decode($normalized_artifact->jsondata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_service::warning($runid, 
                    '[LegacyRecord] normalized_inputs_v16.json has invalid JSON, continuing with empty data');
                $artifacts['normalized_inputs'] = null;
            }
        }
        
        log_service::info($runid, 
            '[LegacyRecord] Collected artifacts: ' . 
            'final_bundle=' . ($artifacts['final_bundle'] ? 'found' : 'missing') . ', ' .
            'diversity_metrics=' . ($artifacts['diversity_metrics'] ? 'found' : 'missing') . ', ' .
            'normalized_inputs=' . ($artifacts['normalized_inputs'] ? 'found' : 'missing'));
        
        return $artifacts;
    }
    
    /**
     * Load company data for the run
     * 
     * @param int $runid Run ID
     * @return array Company data
     */
    private function load_company_data($runid) {
        $run = $this->db->get_record('local_ci_run', ['id' => $runid]);
        if (!$run) {
            throw new \invalid_parameter_exception("Run {$runid} not found");
        }
        
        $companies = [
            'source' => null,
            'target' => null
        ];
        
        // Load source company
        if ($run->companyid) {
            $companies['source'] = $this->db->get_record('local_ci_company', ['id' => $run->companyid]);
        }
        
        // Load target company
        if ($run->targetcompanyid) {
            $companies['target'] = $this->db->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
        }
        
        return $companies;
    }
    
    /**
     * Build legacy synthesis record structure
     * 
     * @param int $runid Run ID
     * @param array $artifacts Collected artifact data
     * @param array $companies Company data
     * @return array Legacy synthesis record
     */
    private function build_legacy_structure($runid, $artifacts, $companies) {
        // Base structure
        $legacy_record = [
            'runid' => $runid,
            'company_source' => $companies['source'] ? $companies['source']->name : 'Unknown',
            'company_target' => $companies['target'] ? $companies['target']->name : null,
            'sections' => [],
            'summaries' => [],
            'citations' => [],
            'trace' => [
                'nodes' => [],
                'edges' => []
            ],
            'diversity_metrics' => [],
            'qa_metrics' => [
                'overall' => 0.0,
                'coherence' => 0.0,
                'completeness' => 0.0
            ]
        ];
        
        // 1. Extract sections and summaries from final_bundle
        if ($artifacts['final_bundle']) {
            $legacy_record['sections'] = $this->extract_sections($artifacts['final_bundle'], $runid);
            $legacy_record['summaries'] = $this->extract_summaries($artifacts['final_bundle'], $runid);
            $legacy_record['qa_metrics'] = $this->extract_qa_metrics($artifacts['final_bundle'], $runid);
        }
        
        // 2. Extract citations from normalized_inputs_v16
        if ($artifacts['normalized_inputs']) {
            $legacy_record['citations'] = $this->extract_citations($artifacts['normalized_inputs'], $runid);
        }
        
        // 3. Build trace from citations if not available
        $legacy_record['trace'] = $this->build_trace($legacy_record['citations'], $runid);
        
        // 4. Merge diversity metrics
        if ($artifacts['diversity_metrics']) {
            $legacy_record['diversity_metrics'] = $this->extract_diversity_metrics($artifacts['diversity_metrics'], $runid);
        }
        
        return $legacy_record;
    }
    
    /**
     * Extract sections from final_bundle
     * 
     * @param array $final_bundle Final bundle data
     * @param int $runid Run ID for logging
     * @return array Sections array
     */
    private function extract_sections($final_bundle, $runid) {
        $sections = [];
        
        // Try to get sections from JSON structure
        if (isset($final_bundle['json'])) {
            $json_data = json_decode($final_bundle['json'], true);
            if ($json_data && isset($json_data['sections'])) {
                $sections = $json_data['sections'];
                log_service::info($runid, 
                    '[LegacyRecord] Extracted ' . count($sections) . ' sections from final_bundle JSON');
            }
        }
        
        // Try to get sections from v15_structure
        if (empty($sections) && isset($final_bundle['v15_structure']['sections'])) {
            $sections = $final_bundle['v15_structure']['sections'];
            log_service::info($runid, 
                '[LegacyRecord] Extracted ' . count($sections) . ' sections from v15_structure');
        }
        
        // Fallback: create sections from HTML if available
        if (empty($sections) && isset($final_bundle['html'])) {
            $sections = $this->parse_sections_from_html($final_bundle['html'], $runid);
        }
        
        return $sections;
    }
    
    /**
     * Extract summaries from final_bundle
     * 
     * @param array $final_bundle Final bundle data
     * @param int $runid Run ID for logging
     * @return array Summaries array
     */
    private function extract_summaries($final_bundle, $runid) {
        $summaries = [];
        
        // Try to get summaries from JSON structure
        if (isset($final_bundle['json'])) {
            $json_data = json_decode($final_bundle['json'], true);
            if ($json_data && isset($json_data['summaries'])) {
                $summaries = $json_data['summaries'];
                log_service::info($runid, 
                    '[LegacyRecord] Extracted summaries from final_bundle JSON');
            }
        }
        
        // Try to get summaries from v15_structure
        if (empty($summaries) && isset($final_bundle['v15_structure']['summaries'])) {
            $summaries = $final_bundle['v15_structure']['summaries'];
            log_service::info($runid, 
                '[LegacyRecord] Extracted summaries from v15_structure');
        }
        
        // Fallback: create basic summary from available data
        if (empty($summaries)) {
            $summaries = $this->create_fallback_summaries($final_bundle, $runid);
        }
        
        return $summaries;
    }
    
    /**
     * Extract QA metrics from final_bundle
     * 
     * @param array $final_bundle Final bundle data
     * @param int $runid Run ID for logging
     * @return array QA metrics
     */
    private function extract_qa_metrics($final_bundle, $runid) {
        $qa_metrics = [
            'overall' => 0.0,
            'coherence' => 0.0,
            'completeness' => 0.0
        ];
        
        // Try to get QA scores from v15_structure
        if (isset($final_bundle['v15_structure']['qa']['scores'])) {
            $scores = $final_bundle['v15_structure']['qa']['scores'];
            
            // Calculate overall score as average of available scores
            $available_scores = array_filter($scores, 'is_numeric');
            if (!empty($available_scores)) {
                $qa_metrics['overall'] = array_sum($available_scores) / count($available_scores);
            }
            
            // Extract specific metrics
            $qa_metrics['coherence'] = $scores['coherence'] ?? 0.0;
            
            // Calculate completeness from evidence_health and other metrics
            $qa_metrics['completeness'] = $scores['evidence_health'] ?? $scores['precision'] ?? 0.0;
            
            log_service::info($runid, 
                '[LegacyRecord] Extracted QA metrics from v15_structure (overall: ' . 
                round($qa_metrics['overall'], 2) . ')');
        }
        
        // Try coherence_report as fallback
        if ($qa_metrics['coherence'] === 0.0 && isset($final_bundle['coherence_report'])) {
            $coherence_data = json_decode($final_bundle['coherence_report'], true);
            if ($coherence_data && isset($coherence_data['score'])) {
                $qa_metrics['coherence'] = $coherence_data['score'];
                log_service::info($runid, 
                    '[LegacyRecord] Used coherence_report for coherence metric');
            }
        }
        
        return $qa_metrics;
    }
    
    /**
     * Extract citations from normalized_inputs_v16
     * 
     * @param array $normalized_inputs Normalized inputs data
     * @param int $runid Run ID for logging
     * @return array Citations array
     */
    private function extract_citations($normalized_inputs, $runid) {
        $citations = [];
        
        // Extract from normalized_citations
        if (isset($normalized_inputs['normalized_citations'])) {
            $citations = $normalized_inputs['normalized_citations'];
            log_service::info($runid, 
                '[LegacyRecord] Extracted ' . count($citations) . ' citations from normalized_citations');
        }
        
        // Extract from citation_list if available
        if (empty($citations) && isset($normalized_inputs['citation_list'])) {
            $citations = $normalized_inputs['citation_list'];
            log_service::info($runid, 
                '[LegacyRecord] Extracted ' . count($citations) . ' citations from citation_list');
        }
        
        // Extract from citation_map if available
        if (empty($citations) && isset($normalized_inputs['citation_map'])) {
            $citation_map = $normalized_inputs['citation_map'];
            $citations = array_values($citation_map);
            log_service::info($runid, 
                '[LegacyRecord] Extracted ' . count($citations) . ' citations from citation_map');
        }
        
        // Normalize citation format for legacy compatibility
        $normalized_citations = [];
        foreach ($citations as $citation) {
            if (is_string($citation)) {
                // Convert string URL to object
                $normalized_citations[] = [
                    'url' => $citation,
                    'domain' => $this->extract_domain($citation),
                    'title' => 'External Source',
                    'type' => 'web'
                ];
            } elseif (is_array($citation)) {
                // Ensure required fields exist
                $normalized_citation = $citation;
                if (!isset($citation['domain']) && isset($citation['url'])) {
                    $normalized_citation['domain'] = $this->extract_domain($citation['url']);
                }
                if (!isset($citation['type'])) {
                    $normalized_citation['type'] = 'web';
                }
                $normalized_citations[] = $normalized_citation;
            }
        }
        
        return $normalized_citations;
    }
    
    /**
     * Build trace from citations
     * 
     * @param array $citations Citations array
     * @param int $runid Run ID for logging
     * @return array Trace structure
     */
    private function build_trace($citations, $runid) {
        $trace = [
            'nodes' => [],
            'edges' => []
        ];
        
        // Extract unique domains as nodes
        $domains = [];
        foreach ($citations as $citation) {
            if (isset($citation['domain'])) {
                $domain = $citation['domain'];
                if (!isset($domains[$domain])) {
                    $domains[$domain] = [
                        'id' => $domain,
                        'label' => $domain,
                        'type' => 'domain',
                        'citation_count' => 0
                    ];
                }
                $domains[$domain]['citation_count']++;
            }
        }
        
        $trace['nodes'] = array_values($domains);
        
        // For now, leave edges empty as minimal trace
        // Future enhancement could add relationships between domains
        $trace['edges'] = [];
        
        log_service::info($runid, 
            '[LegacyRecord] Built minimal trace with ' . count($trace['nodes']) . ' domain nodes');
        
        return $trace;
    }
    
    /**
     * Extract diversity metrics
     * 
     * @param array $diversity_data Diversity metrics data
     * @param int $runid Run ID for logging
     * @return array Diversity metrics
     */
    private function extract_diversity_metrics($diversity_data, $runid) {
        // Return the diversity data as-is, it should already be in the correct format
        log_service::info($runid, 
            '[LegacyRecord] Merged diversity metrics from retrieval_rebalancing artifact');
        
        return $diversity_data;
    }
    
    /**
     * Parse sections from HTML content
     * 
     * @param string $html HTML content
     * @param int $runid Run ID for logging
     * @return array Sections array
     */
    private function parse_sections_from_html($html, $runid) {
        $sections = [];
        
        // Simple regex to extract h2/h3 headers as section titles
        if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/i', $html, $matches)) {
            foreach ($matches[1] as $index => $title) {
                $section_key = 'section_' . ($index + 1);
                $sections[$section_key] = strip_tags($title);
            }
            
            log_service::info($runid, 
                '[LegacyRecord] Parsed ' . count($sections) . ' sections from HTML headers');
        }
        
        // Fallback: create single section
        if (empty($sections)) {
            $sections['main_content'] = 'Intelligence Report';
            log_service::info($runid, 
                '[LegacyRecord] Created fallback section from HTML content');
        }
        
        return $sections;
    }
    
    /**
     * Create fallback summaries
     * 
     * @param array $final_bundle Final bundle data
     * @param int $runid Run ID for logging
     * @return array Summaries array
     */
    private function create_fallback_summaries($final_bundle, $runid) {
        $summaries = [];
        
        // Try to extract from appendix_notes
        if (isset($final_bundle['appendix_notes'])) {
            $summaries['appendix'] = $final_bundle['appendix_notes'];
        }
        
        // Try to extract from voice_report
        if (isset($final_bundle['voice_report'])) {
            $voice_data = json_decode($final_bundle['voice_report'], true);
            if ($voice_data && isset($voice_data['tone'])) {
                $summaries['voice_tone'] = $voice_data['tone'];
            }
        }
        
        // Create basic summary
        if (empty($summaries)) {
            $summaries['generated'] = 'Legacy synthesis record generated from v17.1 artifacts';
        }
        
        log_service::info($runid, 
            '[LegacyRecord] Created fallback summaries with ' . count($summaries) . ' items');
        
        return $summaries;
    }
    
    /**
     * Extract domain from URL
     * 
     * @param string $url URL
     * @return string Domain
     */
    private function extract_domain($url) {
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'unknown';
    }
}