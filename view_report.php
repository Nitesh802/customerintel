<?php
/**
 * Customer Intelligence Dashboard - View Individual Report
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();

// Wrap everything in try-catch to capture and display errors
try {

$context = context_system::instance();
require_capability('local/customerintel:view', $context);

// Required parameter
$runid = required_param('runid', PARAM_INT);
$regenerate = optional_param('regenerate', 0, PARAM_INT);

// Verify the run exists
$run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);

// M2 Task 0.2: Check if synthesis is still in progress
// Support both old 'running' and new 'processing'/'pending' statuses
$is_processing = ($run->status === 'processing' || $run->status === 'pending' || $run->status === 'running');

// Allow viewing if completed, processing, pending, OR running (backward compatibility)
if ($run->status !== 'completed' && $run->status !== 'processing' && $run->status !== 'pending' && $run->status !== 'running') {
    throw new moodle_exception('reportnotready', 'local_customerintel', '', null,
        'Run ' . $runid . ' is not ready. Status: ' . $run->status);
}

// Check user permissions
$can_manage = has_capability('local/customerintel:manage', $context);
if ($run->initiatedbyuserid != $USER->id && !$can_manage) {
    throw new moodle_exception('nopermission', 'local_customerintel');
}

// Get company details
$company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
$targetcompany = null;
if ($run->targetcompanyid) {
    $targetcompany = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
}

// Set up page
$PAGE->set_url(new moodle_url('/local/customerintel/view_report.php', ['runid' => $runid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Customer Intelligence Report');
$PAGE->set_heading('Customer Intelligence Report');
$PAGE->set_pagelayout('admin');

// Add breadcrumbs
$PAGE->navbar->add('Reports', new moodle_url('/local/customerintel/reports.php'));
$PAGE->navbar->add('View Report');

// Add CSS for report styling
$PAGE->requires->css('/local/customerintel/styles/customerintel.css'); 

// Add Chart.js for interactive charts
$PAGE->requires->js(new moodle_url('/local/customerintel/js/chart.min.js'));

// Initialize synthesis engine
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
$synthesis_engine = new \local_customerintel\services\synthesis_engine();

// v17.1 Unified Compatibility: Initialize compatibility adapter
require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_compatibility_adapter.php');
$compatibility_adapter = new \local_customerintel\services\artifact_compatibility_adapter();

/**
 * v17.1 Compatibility Mapping Function
 * Maps v17.1 bundle structure to v15 viewer schema expectations
 * 
 * @param array $bundle Synthesis bundle from adapter
 * @param int $runid Run ID for logging
 * @return array Mapped bundle with v15-compatible fields
 */
function apply_v17_1_compatibility_mapping($bundle, $runid) {
    $mapped_bundle = $bundle;
    $mapping_operations = [];
    
    // 1. Map QA scores from nested structures
    if (empty($mapped_bundle['qa_score']) && !empty($bundle['v15_structure']['qa']['scores'])) {
        $mapped_bundle['qa_score'] = $bundle['v15_structure']['qa']['scores'];
        $mapping_operations[] = 'qa_score from v15_structure.qa.scores';
    }
    
    // Alternative QA mapping from qa_metrics
    if (empty($mapped_bundle['qa_score']) && !empty($bundle['qa_metrics']['scores'])) {
        $mapped_bundle['qa_score'] = $bundle['qa_metrics']['scores'];
        $mapping_operations[] = 'qa_score from qa_metrics.scores';
    }
    
    // Alternative QA mapping from coherence_report
    if (empty($mapped_bundle['qa_score']) && !empty($bundle['coherence_report'])) {
        $coherence_data = json_decode($bundle['coherence_report'], true);
        if ($coherence_data && isset($coherence_data['score'])) {
            $mapped_bundle['qa_score'] = ['coherence' => $coherence_data['score']];
            $mapping_operations[] = 'qa_score.coherence from coherence_report';
        }
    }
    
    // 2. Map citations from nested structures
    if (empty($mapped_bundle['citations']) && !empty($bundle['v15_structure']['citations'])) {
        $mapped_bundle['citations'] = $bundle['v15_structure']['citations'];
        $mapping_operations[] = 'citations from v15_structure.citations';
    }
    
    // Alternative citations mapping from metrics
    if (empty($mapped_bundle['citations']) && !empty($bundle['metrics']['citations'])) {
        $mapped_bundle['citations'] = $bundle['metrics']['citations'];
        $mapping_operations[] = 'citations from metrics.citations';
    }
    
    // Ensure sources fallback to citations
    if (empty($mapped_bundle['sources']) && !empty($mapped_bundle['citations'])) {
        $mapped_bundle['sources'] = $mapped_bundle['citations'];
        $mapping_operations[] = 'sources fallback to citations';
    }
    
    // 3. Map domain analysis from nested structures
    if (empty($mapped_bundle['domains']) && !empty($bundle['v15_structure']['evidence_diversity_metrics'])) {
        $diversity_metrics = $bundle['v15_structure']['evidence_diversity_metrics'];
        if (isset($diversity_metrics['domain_distribution'])) {
            $mapped_bundle['domains'] = $diversity_metrics['domain_distribution'];
            $mapping_operations[] = 'domains from v15_structure.evidence_diversity_metrics.domain_distribution';
        }
    }
    
    // Alternative domain mapping from metrics
    if (empty($mapped_bundle['domains']) && !empty($bundle['metrics']['domain_analysis'])) {
        $mapped_bundle['domains'] = $bundle['metrics']['domain_analysis'];
        $mapping_operations[] = 'domains from metrics.domain_analysis';
    }
    
    // 4. Map additional v17.1 fields for completeness
    if (empty($mapped_bundle['evidence_diversity']) && !empty($bundle['v15_structure']['evidence_diversity_metrics'])) {
        $mapped_bundle['evidence_diversity'] = $bundle['v15_structure']['evidence_diversity_metrics'];
        $mapping_operations[] = 'evidence_diversity from v15_structure.evidence_diversity_metrics';
    }
    
    // 5. Ensure v15_structure is properly structured for legacy code
    if (!empty($bundle['v15_structure']) && !isset($mapped_bundle['v15_structure']['qa']['scores'])) {
        // Try to reconstruct qa.scores from JSON data
        if (!empty($bundle['json'])) {
            $json_data = json_decode($bundle['json'], true);
            if ($json_data && isset($json_data['qa']['scores'])) {
                if (!isset($mapped_bundle['v15_structure'])) {
                    $mapped_bundle['v15_structure'] = [];
                }
                if (!isset($mapped_bundle['v15_structure']['qa'])) {
                    $mapped_bundle['v15_structure']['qa'] = [];
                }
                $mapped_bundle['v15_structure']['qa']['scores'] = $json_data['qa']['scores'];
                $mapping_operations[] = 'v15_structure.qa.scores from JSON data';
            }
        }
    }
    
    // 6. Map pattern alignment scores
    if (empty($mapped_bundle['pattern_alignment']) && !empty($bundle['pattern_alignment_report'])) {
        $pattern_data = json_decode($bundle['pattern_alignment_report'], true);
        if ($pattern_data && isset($pattern_data['score'])) {
            $mapped_bundle['pattern_alignment'] = $pattern_data['score'];
            $mapping_operations[] = 'pattern_alignment from pattern_alignment_report';
        }
    }
    
    // 7. Map appendix data
    if (empty($mapped_bundle['appendix']) && !empty($bundle['appendix_notes'])) {
        $mapped_bundle['appendix'] = $bundle['appendix_notes'];
        $mapping_operations[] = 'appendix from appendix_notes';
    }
    
    // Log mapping operations for traceability
    if (!empty($mapping_operations)) {
        global $CFG;
        require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
        \local_customerintel\services\log_service::info($runid,
            '[Compatibility] Viewer auto-mapped v17.1 bundle fields to v15 viewer schema: ' .
            implode(', ', $mapping_operations));
    }
    
    return $mapped_bundle;
}

/**
 * Convert legacy synthesis_record.json to expected bundle format
 * 
 * @param array $legacy_record Legacy synthesis record data
 * @param int $runid Run ID for logging
 * @return array Bundle format compatible with viewer
 */
function convert_legacy_record_to_bundle($legacy_record, $runid) {
    $bundle = [
        'html' => '',
        'json' => '',
        'voice_report' => '{}',
        'selfcheck_report' => '{}',
        'coherence_report' => '{}',
        'pattern_alignment_report' => '{}',
        'citations' => [],
        'sources' => [],
        'qa_report' => '{}',
        'appendix_notes' => ''
    ];
    
    // Generate HTML from sections
    if (!empty($legacy_record['sections'])) {
        $html_content = '<div class="legacy-synthesis-content">';
        $html_content .= '<h1>Intelligence Report</h1>';
        
        foreach ($legacy_record['sections'] as $section_key => $section_content) {
            $section_title = ucwords(str_replace('_', ' ', $section_key));
            $html_content .= '<h2>' . htmlspecialchars($section_title) . '</h2>';
            $html_content .= '<p>' . htmlspecialchars($section_content) . '</p>';
        }
        
        $html_content .= '</div>';
        $bundle['html'] = $html_content;
    }
    
    // Build JSON structure
    $json_structure = [
        'sections' => $legacy_record['sections'] ?? [],
        'summaries' => $legacy_record['summaries'] ?? [],
        'qa' => [
            'scores' => [
                'overall' => $legacy_record['qa_metrics']['overall'] ?? 0.0,
                'coherence' => $legacy_record['qa_metrics']['coherence'] ?? 0.0,
                'evidence_health' => $legacy_record['qa_metrics']['completeness'] ?? 0.0
            ],
            'warnings' => []
        ]
    ];
    
    // Add diversity metrics if available
    if (!empty($legacy_record['diversity_metrics'])) {
        $json_structure['evidence_diversity_metrics'] = $legacy_record['diversity_metrics'];
    }
    
    $bundle['json'] = json_encode($json_structure);
    
    // Add citations
    $bundle['citations'] = $legacy_record['citations'] ?? [];
    $bundle['sources'] = $legacy_record['citations'] ?? [];
    
    // Build QA reports from legacy metrics
    if (!empty($legacy_record['qa_metrics'])) {
        $bundle['qa_report'] = json_encode($legacy_record['qa_metrics']);
        $bundle['coherence_report'] = json_encode([
            'score' => $legacy_record['qa_metrics']['coherence'] ?? 0.0,
            'details' => 'Converted from legacy synthesis_record'
        ]);
    }
    
    // Add summaries as appendix notes
    if (!empty($legacy_record['summaries'])) {
        $summary_text = '';
        foreach ($legacy_record['summaries'] as $key => $value) {
            $summary_text .= ucwords(str_replace('_', ' ', $key)) . ': ' . $value . "\n";
        }
        $bundle['appendix_notes'] = trim($summary_text);
    }
    
    // Add v15_structure for compatibility
    $bundle['v15_structure'] = $json_structure;
    
    require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
    \local_customerintel\services\log_service::info($runid, 
        '[Compatibility] Legacy synthesis_record converted to bundle format with ' . 
        count($bundle['sections'] ?? []) . ' sections and ' . 
        count($bundle['citations']) . ' citations');
    
    return $bundle;
}

// Initialize renderer for UI components
$output = $PAGE->get_renderer('local_customerintel');

// Telemetry variables
$cache_hit = false;
$build_duration_ms = 0;
$citation_count = 0;
$token_cost_est = 0;
$build_start_time = 0;

// Determine if we should force regeneration
$force_regenerate = false;
if ($regenerate === 1 && $can_manage) {
    $force_regenerate = true;
}

// Try to get cached synthesis first (unless forcing regeneration)
$synthesis_bundle = null;
$cache_timestamp = null;
$needs_rebuild = false;

if (!$force_regenerate) {
    // v17.1 Unified Compatibility: Use adapter for all synthesis bundle loading
    $synthesis_bundle = $compatibility_adapter->load_synthesis_bundle($runid);

    // PRIORITY: Check for Phase 2 synthesis_final_bundle first
    if ($synthesis_bundle === null) {
        $phase2_artifact = $DB->get_record('local_ci_artifact', [
            'runid' => $runid,
            'artifacttype' => 'synthesis_final_bundle'
        ]);

        if ($phase2_artifact && !empty($phase2_artifact->jsondata)) {
            $phase2_data = json_decode($phase2_artifact->jsondata, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($phase2_data['final_report'])) {
                require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
                \local_customerintel\services\log_service::info($runid,
                    '[Phase 2] Loading synthesis_final_bundle artifact (preferred format)');

                // Convert Phase 2 bundle to a format that signals "use Phase 2 display"
                // By NOT setting 'html' field, it will fall through to Phase 2 display code
                $synthesis_bundle = [
                    'phase2_format' => true,
                    'artifact_id' => $phase2_artifact->id,
                    'timestamp' => $phase2_artifact->timecreated
                ];

                $cache_hit = true;
                $cache_timestamp = $phase2_artifact->timecreated;
            }
        }
    }

    if ($synthesis_bundle !== null && empty($synthesis_bundle['phase2_format'])) {
        $cache_hit = true;
        $cache_timestamp = $synthesis_engine->get_cache_timestamp($runid);
    } else if ($synthesis_bundle === null) {
        // v17.1 Compatibility: Check for final_bundle.json or synthesis_record.json artifacts before rebuilding
        $final_bundle_artifact = $DB->get_record('local_ci_artifact', [
            'runid' => $runid
            // 'phase' => 'synthesis',
            // 'artifacttype' => 'final_bundle'
        ]);
        
        $synthesis_record_artifact = $DB->get_record('local_ci_artifact', [
            'runid' => $runid
            // 'phase' => 'synthesis',
            // 'artifacttype' => 'synthesis_record'
        ]);

        if ($final_bundle_artifact && !empty($final_bundle_artifact->jsondata)) {
            // Load final bundle artifact as primary fallback
            $final_bundle_data = json_decode($final_bundle_artifact->jsondata, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($final_bundle_data)) {
                require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
                \local_customerintel\services\log_service::info($runid, 
                    '[Compatibility] Fallback to final_bundle.json – synthesis cache not found but valid bundle detected');
                
                $synthesis_bundle = $final_bundle_data;
                $cache_hit = false; // It's a fallback, not a cache hit
                $cache_timestamp = $final_bundle_artifact->timecreated;
                $needs_rebuild = false; // No rebuild needed
                
                \local_customerintel\services\log_service::info($runid, 
                    '[Compatibility] Successfully loaded synthesis from final_bundle artifact (created: ' . 
                    date('Y-m-d H:i:s', $final_bundle_artifact->timecreated) . ')');
            } else {
                \local_customerintel\services\log_service::warning($runid, 
                    '[Compatibility] final_bundle artifact found but JSON data is invalid');
                $needs_rebuild = true;
            }
        } elseif ($synthesis_record_artifact && !empty($synthesis_record_artifact->jsondata)) {
            // Load legacy synthesis_record artifact as secondary fallback
            $synthesis_record_data = json_decode($synthesis_record_artifact->jsondata, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($synthesis_record_data)) {
                require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
                \local_customerintel\services\log_service::info($runid, 
                    '[Compatibility] Fallback to synthesis_record.json – legacy format detected and loaded');
                
                // Convert legacy format to expected bundle format
                $synthesis_bundle = $this->convert_legacy_record_to_bundle($synthesis_record_data, $runid);
                $cache_hit = false; // It's a fallback, not a cache hit
                $cache_timestamp = $synthesis_record_artifact->timecreated;
                $needs_rebuild = false; // No rebuild needed
                
                \local_customerintel\services\log_service::info($runid, 
                    '[Compatibility] Successfully loaded synthesis from legacy synthesis_record artifact (created: ' . 
                    date('Y-m-d H:i:s', $synthesis_record_artifact->timecreated) . ')');
            } else {
                \local_customerintel\services\log_service::warning($runid, 
                    '[Compatibility] synthesis_record artifact found but JSON data is invalid');
                $needs_rebuild = true;
            }
        } else {
            \local_customerintel\services\log_service::warning($runid, 
                '[Compatibility] No synthesis cache, final_bundle, or synthesis_record artifacts found – rebuild required');
            $needs_rebuild = true;
        }
    }
} else {
    $needs_rebuild = true;
}

// Build or rebuild synthesis if needed
if ($needs_rebuild) {
    // M2 Task 0.3: Check if run has any NB results before attempting synthesis
    $nb_count = $DB->count_records('local_ci_nb_result', ['runid' => $runid, 'status' => 'completed']);

    if ($nb_count == 0 && $is_processing) {
        // No NB results yet - show waiting message
        require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
        \local_customerintel\services\log_service::info($runid,
            '[View] Run ' . $runid . ' has no completed NBs yet - showing wait message');

        // Skip synthesis generation and jump to the output section
        // The auto-refresh UI will be shown below
        $synthesis_bundle = null;
        $cache_hit = false;
        $cache_timestamp = null;
        $needs_rebuild = false; // Skip the rebuild attempt
    }
}

// Only attempt synthesis generation if we have data
if ($needs_rebuild && !$is_processing) {
    try {
        $build_start_time = microtime(true);
        
        // v17.1 Unified Compatibility: Check for synthesis inputs via adapter
        require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
        
        // Check for normalized artifact via compatibility adapter
        $synthesis_inputs = $compatibility_adapter->load_artifact($runid, 'synthesis_inputs');
        
        if ($synthesis_inputs) {
            \local_customerintel\services\log_service::info($runid, 
                '[Compatibility] Synthesis inputs found via adapter - proceeding with synthesis');
        } else {
            \local_customerintel\services\log_service::warning($runid, 
                '[Compatibility] No synthesis inputs found via adapter — rebuilding from NB results');
        }
        
        // Build synthesis (this will also cache it internally)
        $synthesis_bundle = $synthesis_engine->build_report($runid, $force_regenerate);
        
        // Calculate build duration
        $build_duration_ms = round((microtime(true) - $build_start_time) * 1000);
        
        // Get the new cache timestamp
        $cache_timestamp = time();
        
        // Log successful synthesis generation
        require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
        \local_customerintel\services\log_service::info($runid, 
            'Synthesis ' . ($force_regenerate ? 'regenerated' : 'generated') . 
            ' on view for run ' . $runid . ' (duration: ' . $build_duration_ms . 'ms)');
        
    } catch (Exception $e) {
        // Log synthesis failure
        require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
        \local_customerintel\services\log_service::error($runid, 
            'Failed to generate synthesis on view: ' . $e->getMessage());
        
        // Enhanced error context extraction for synthesis_build_failed
        $error_details = [
            'runid' => $runid,
            'method' => 'build_report',
            'phase' => '',
            'section' => '',
            'nbkeys_seen' => [],
            'inner' => substr($e->getMessage(), 0, 240),
            'missing_nb' => []
        ];
        
        // Check if it's a synthesis_build_failed exception with enhanced context
        if ($e instanceof moodle_exception && $e->errorcode === 'synthesis_build_failed') {
            // Extract all available context from $e->a
            if (isset($e->a) && is_array($e->a)) {
                foreach (['method', 'phase', 'section', 'nbkeys_seen', 'inner'] as $field) {
                    if (isset($e->a[$field])) {
                        $error_details[$field] = $e->a[$field];
                    }
                }
                // Also preserve runid if provided
                if (isset($e->a['runid'])) {
                    $error_details['runid'] = $e->a['runid'];
                }
            }
        }
        
        // Helper function to normalize NB codes (same as in synthesis_engine)
        $normalize_nbcode = function($code) {
            preg_match('/\d+/', $code, $matches);
            if (empty($matches)) {
                return strtoupper($code);
            }
            $number = (int)$matches[0];
            return "NB" . $number;
        };
        
        // Try to get NB status for debugging
        $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC', 'nbcode, status');
        $completed_nbs = [];
        $failed_nbs = [];
        foreach ($nb_results as $nb) {
            $normalized_code = $normalize_nbcode($nb->nbcode);
            if ($nb->status === 'completed') {
                $completed_nbs[] = $normalized_code;
            } else {
                $failed_nbs[] = $normalized_code;
            }
        }
        
        $expected_nbs = ['NB1', 'NB2', 'NB3', 'NB4', 'NB5', 'NB6', 'NB7', 'NB8', 'NB9', 'NB10', 'NB11', 'NB12', 'NB13', 'NB14', 'NB15'];
        $missing_nbs = array_diff($expected_nbs, $completed_nbs);
        $error_details['missing_nb'] = $missing_nbs;
        $error_details['failed_nb'] = $failed_nbs;
        $error_details['completed_count'] = count($completed_nbs);
        
        // Construct detailed message with enhanced context  
        $detailed_message = 'Debug info: Synthesis generation failed for run ' . $runid . ': ' . ($e->errorcode ?? 'unknown_error');
        if (!empty($error_details['method'])) {
            $detailed_message .= ' | Method: ' . $error_details['method'];
        }
        if (!empty($error_details['phase'])) {
            $detailed_message .= ' | Phase: ' . $error_details['phase'];
        }
        if (!empty($error_details['section'])) {
            $detailed_message .= ' | Section: ' . $error_details['section'];
        }
        if (!empty($error_details['nbkeys_seen']) && is_array($error_details['nbkeys_seen'])) {
            $detailed_message .= ' | NBs seen: ' . implode(',', $error_details['nbkeys_seen']);
        }
        if (!empty($missing_nbs)) {
            $detailed_message .= ' | Missing NBs: ' . implode(', ', $missing_nbs);
        }
        if (!empty($failed_nbs)) {
            $detailed_message .= ' | Failed NBs: ' . implode(', ', $failed_nbs);
        }
        $detailed_message .= ' | Completed: ' . count($completed_nbs) . '/15';
        if (!empty($error_details['inner'])) {
            $detailed_message .= ' | Inner: ' . $error_details['inner'];
        }
        
        throw new moodle_exception('synthesis_required', 'local_customerintel', '', $error_details, $detailed_message);
    }
}

// Get synthesis data from DB for additional details
$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

// Citation count will be calculated after v17.1 compatibility mapping is applied

// Calculate relative time from cache timestamp
$relative_time = 'Just now';
if ($cache_timestamp) {
    $time_diff = time() - $cache_timestamp;
    if ($time_diff < 60) {
        $relative_time = 'Just now';
    } else if ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        $relative_time = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } else if ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        $relative_time = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($time_diff / 86400);
        $relative_time = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

// Log telemetry metrics
$telemetry = new stdClass();
$telemetry->runid = $runid;
$telemetry->timecreated = time();

// Log cache hit metric
$telemetry->metrickey = 'synth_cache_hit';
$telemetry->metricvaluenum = $cache_hit ? 1 : 0;
$telemetry->payload = json_encode(['regenerate' => $force_regenerate ? 1 : 0]);
$DB->insert_record('local_ci_telemetry', $telemetry);

// Log duration metric
$telemetry->metrickey = 'synth_duration_ms';
$telemetry->metricvaluenum = $cache_hit ? 0 : $build_duration_ms;
$telemetry->payload = json_encode(['cache_hit' => $cache_hit ? 1 : 0]);
$DB->insert_record('local_ci_telemetry', $telemetry);

// Log citation count metric
$telemetry->metrickey = 'synth_citation_count';
$telemetry->metricvaluenum = $citation_count;
$telemetry->payload = json_encode(['runid' => $runid]);
$DB->insert_record('local_ci_telemetry', $telemetry);

// Log token cost estimate if available (currently not tracked, so set to 0)
$telemetry->metrickey = 'synth_token_cost_est';
$telemetry->metricvaluenum = 0; // Token cost tracking not implemented yet
$telemetry->payload = json_encode(['runid' => $runid]);
$DB->insert_record('local_ci_telemetry', $telemetry);

// Output header
echo $OUTPUT->header();

// Add CSS link for compatibility
echo '<link rel="stylesheet" href="' . $CFG->wwwroot . '/local/customerintel/styles/customerintel.css">';

// M2 Phase 1: Add Energy Exemplar styled report CSS
echo '<link rel="stylesheet" href="' . $CFG->wwwroot . '/local/customerintel/styles/report.css">';

// M2 Task 0.2: Show processing UI if synthesis is still running
if ($is_processing) {
    ?>
    <!-- M2 Task 0.2: Synthesis in Progress UI -->
    <div class="alert alert-info" id="synthesis-status" style="margin: 20px auto; padding: 30px; background: #e3f2fd; border-left: 4px solid #2196f3; max-width: 800px; text-align: center; border-radius: 8px;">
        <h3 style="margin: 0 0 15px 0; color: #1976d2;">
            ⏳ Synthesis in Progress
        </h3>
        <p style="margin: 0 0 20px 0; font-size: 16px; color: #555;">
            Generating your intelligence report. This typically takes 8-10 minutes.<br>
            <strong>Please wait – the page will refresh automatically when complete.</strong>
        </p>
        <div class="progress-indicator">
            <div class="spinner"></div>
        </div>
    </div>

    <style>
    .progress-indicator {
        margin-top: 15px;
    }

    .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #7FF6D3;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>

    <script>
    function checkSynthesisStatus() {
        fetch('<?php echo $CFG->wwwroot; ?>/local/customerintel/ajax_check_status.php?runid=<?php echo $runid; ?>')
            .then(response => response.json())
            .then(data => {
                console.log('Status check:', data);
                if (data.completed) {
                    console.log('✅ Synthesis completed - reloading page');
                    location.reload();
                } else {
                    console.log('⏳ Still processing - checking again in 5 seconds');
                    setTimeout(checkSynthesisStatus, 5000);
                }
            })
            .catch(error => {
                console.error('❌ Status check failed:', error);
                // Retry after 10 seconds on error
                setTimeout(checkSynthesisStatus, 10000);
            });
    }

    // Start polling immediately
    console.log('🚀 Starting synthesis status polling for run <?php echo $runid; ?>');
    checkSynthesisStatus();
    </script>

    <?php
    // Don't show report content while processing
    echo $OUTPUT->footer();
    exit;
}

// Navigation buttons
echo '<div class="mb-3">';
echo '<a href="' . new moodle_url('/local/customerintel/reports.php') . '" class="btn btn-secondary">';
echo get_string('back') . ' to Reports</a> ';

// Export buttons
echo '<a href="' . new moodle_url('/local/customerintel/export.php', ['runid' => $runid, 'format' => 'json']) . '" class="btn btn-info">';
echo 'Export JSON</a>';

// Add synthesis JSON export if synthesis exists
if ($synthesis_bundle !== null) {
    echo ' <a href="' . new moodle_url('/local/customerintel/export.php', ['runid' => $runid, 'format' => 'synthesis_json']) . '" class="btn btn-success">';
    echo 'Export Synthesis JSON</a>';
}

// Add diagnostic download button for admins
if ($can_manage) {
    echo ' <a href="' . new moodle_url('/local/customerintel/download_diagnostics.php', ['runid' => $runid]) . '" class="btn btn-warning">';
    echo '🔍 Download Diagnostics</a>';
    
    // Add preview button for quick inspection
    echo ' <button type="button" class="btn btn-outline-warning" onclick="previewDiagnostics(' . $runid . ')">';
    echo '👁️ Preview</button>';
}

echo '</div>';

// Collapsible Canonical NB Dataset debug section
echo '<div class="card mb-3" style="border-left: 3px solid #6c757d;">';
echo '<div class="card-header bg-light">';
echo '<h4 class="mb-0">';
echo '<button class="btn btn-link collapsed text-left p-0 text-decoration-none w-100" type="button" data-toggle="collapse" data-target="#canonicalDatasetDebug" aria-expanded="false" aria-controls="canonicalDatasetDebug">';
echo '<i class="fa fa-chevron-right mr-2" id="canonicalDatasetDebugIcon"></i>';
echo '<span class="text-muted">🔧 Debug: Canonical NB Dataset</span>';
echo '<span class="badge badge-secondary ml-2">For Debugging</span>';
echo '</button>';
echo '</h4>';
echo '</div>';
echo '<div id="canonicalDatasetDebug" class="collapse">';
echo '<div class="card-body">';
echo '<p class="text-muted mb-2"><strong>Note:</strong> This is the raw canonical dataset used to generate the intelligence report. Useful for debugging section generation issues.</p>';
echo '<pre style="max-height: 500px; overflow-y: auto; background-color: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 12px;">';

// Display the canonical dataset JSON
$canonical_dataset = $DB->get_record('local_ci_artifact', [
    'runid' => $runid,
    'artifacttype' => 'canonical_nb_dataset'
]);

if ($canonical_dataset && !empty($canonical_dataset->jsondata)) {
    $decoded = json_decode($canonical_dataset->jsondata, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    } else {
        echo 'Error decoding canonical dataset JSON';
    }
} else {
    echo 'Canonical dataset not found for this run';
}

echo '</pre>';
echo '</div>';
echo '</div>';
echo '</div>';

// Add JavaScript to toggle chevron icon
echo '<script>
$("#canonicalDatasetDebug").on("show.bs.collapse", function() {
    $("#canonicalDatasetDebugIcon").removeClass("fa-chevron-right").addClass("fa-chevron-down");
});
$("#canonicalDatasetDebug").on("hide.bs.collapse", function() {
    $("#canonicalDatasetDebugIcon").removeClass("fa-chevron-down").addClass("fa-chevron-right");
});
</script>';
// Show cache status line
if ($synthesis_bundle !== null) {
    echo '<div class="alert alert-light border" role="alert">';
    echo '<small class="text-muted">';
    echo 'This report was generated ' . htmlspecialchars($relative_time);
    if ($can_manage) {
        echo ' · <a href="' . new moodle_url('/local/customerintel/view_report.php', ['runid' => $runid, 'regenerate' => 1]) . '">Regenerate</a>';
    }
    echo '</small>';
    echo '</div>';
}

// Check if Data Trace tab should be shown
$show_trace_tab = false;
$trace_mode_enabled = get_config('local_customerintel', 'enable_trace_mode');
if ($trace_mode_enabled === '1' && has_capability('local/customerintel:view', $context)) {
    $show_trace_tab = true;
}

// Add tab navigation if trace mode is enabled
if ($show_trace_tab) {
    echo '<div class="card mb-4">';
    echo '<div class="card-header p-0">';
    echo '<ul class="nav nav-tabs card-header-tabs" id="reportTabs" role="tablist">';
    echo '<li class="nav-item">';
    echo '<a class="nav-link active" id="report-tab" data-toggle="tab" href="#report-content" role="tab" aria-controls="report-content" aria-selected="true">';
    echo '📊 Intelligence Report</a>';
    echo '</li>';
    echo '<li class="nav-item">';
    echo '<a class="nav-link" id="trace-tab" data-toggle="tab" href="#trace-content" role="tab" aria-controls="trace-content" aria-selected="false">';
    echo '🔍 Data Trace</a>';
    echo '</li>';
    echo '</ul>';
    echo '</div>';
    echo '<div class="tab-content" id="reportTabContent">';
    
    // Start Report tab content
    echo '<div class="tab-pane fade show active" id="report-content" role="tabpanel" aria-labelledby="report-tab">';
}

// Report header
echo '<div class="card mb-4">';
echo '<div class="card-header">';
echo '<h2 class="mb-0">Intelligence Report</h2>';
echo '</div>';
echo '<div class="card-body">';

echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<h4>Company: ' . htmlspecialchars($company->name) . '</h4>';
if ($company->ticker) {
    echo '<p><strong>Ticker:</strong> ' . htmlspecialchars($company->ticker) . '</p>';
}
if ($company->website) {
    echo '<p><strong>Website:</strong> <a href="' . htmlspecialchars($company->website) . '" target="_blank">' . 
         htmlspecialchars($company->website) . '</a></p>';
}
if ($company->sector) {
    echo '<p><strong>Sector:</strong> ' . htmlspecialchars($company->sector) . '</p>';
}
echo '</div>';

echo '<div class="col-md-6">';
if ($targetcompany) {
    echo '<h4>Target: ' . htmlspecialchars($targetcompany->name) . '</h4>';
    if ($targetcompany->ticker) {
        echo '<p><strong>Target Ticker:</strong> ' . htmlspecialchars($targetcompany->ticker) . '</p>';
    }
}
echo '<p><strong>Run ID:</strong> ' . $run->id . '</p>';
echo '<p><strong>Status:</strong> <span class="badge badge-success">' . ucfirst($run->status) . '</span></p>';

// Show completed date
$generated_date = userdate($run->timecompleted ?: time());
echo '<p><strong>Completed:</strong> ' . $generated_date . '</p>';

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

// Render synthesis playbook
// PRIORITY: Always check for Phase 2 synthesis_final_bundle first
$phase2_artifact = $DB->get_record('local_ci_artifact', [
    'runid' => $runid,
    'artifacttype' => 'synthesis_final_bundle'
]);

if ($phase2_artifact && !empty($phase2_artifact->jsondata)) {
    // Phase 2 format exists - display it
    $bundle_data = json_decode($phase2_artifact->jsondata, true);

    if (json_last_error() === JSON_ERROR_NONE && !empty($bundle_data['final_report'])) {
        // M2 Phase 1: Removed Moodle card wrapper to display Energy Exemplar styled report standalone

        // Get the markdown report
        $markdown_report = $bundle_data['final_report'];

        // M2 Phase 1: Use Moodle's markdown converter
        // M3 Fix: Disable media filters to prevent YouTube video embeds in citations
        $html_report = format_text($markdown_report, FORMAT_MARKDOWN, [
            'noclean' => true,
            'filter' => false  // Disable media filters to show URLs as plain text links
        ]);

        // M3 Task 3.4: Replace sections with formatted_html when available
        if (!empty($bundle_data['sections'])) {
            require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
            \local_customerintel\services\log_service::info($runid, '[M3] Processing ' . count($bundle_data['sections']) . ' sections for formatted HTML replacement');

            foreach ($bundle_data['sections'] as $nb_code => $section_data) {
                if (!empty($section_data['formatted_html']) && !empty($section_data['title'])) {
                    $section_title = $section_data['title'];
                    $formatted_html = $section_data['formatted_html'];

                    \local_customerintel\services\log_service::info($runid, "[M3] Attempting to replace section {$nb_code}: '{$section_title}' (formatted_html: " . strlen($formatted_html) . " chars)");

                    // Find the section in HTML by title (h2 tag)
                    // Pattern: <h2>Title</h2> or <h2>1. Title</h2> followed by content until next <h2> or end
                    // Handle both numbered (1. Company Overview) and plain (Company Overview) formats
                    // IMPORTANT: Handle HTML entity encoding (&amp; vs &, etc.)
                    $search_title = htmlspecialchars($section_title, ENT_QUOTES, 'UTF-8');
                    $escaped_title = preg_quote($search_title, '/');
                    $pattern = '/(<h2[^>]*>(?:\d+\.\s*)?' . $escaped_title . '<\/h2>)(.*?)(?=<h2|$)/s';

                    $replacement_count = 0;
                    $html_report = preg_replace_callback($pattern, function($matches) use ($formatted_html, $nb_code, $runid, &$replacement_count) {
                        $replacement_count++;
                        \local_customerintel\services\log_service::info($runid, "[M3] ✓ Replaced {$nb_code} with formatted HTML (" . strlen($formatted_html) . " chars)");
                        return $matches[1] . "\n" . $formatted_html . "\n";
                    }, $html_report, 1); // Replace only first match

                    if ($replacement_count === 0) {
                        \local_customerintel\services\log_service::info($runid, "[M3] ✗ Could not find section '{$section_title}' in HTML for {$nb_code}");
                    }
                } else {
                    if (empty($section_data['formatted_html'])) {
                        \local_customerintel\services\log_service::info($runid, "[M3] Section {$nb_code} has no formatted_html, skipping");
                    }
                }
            }
        }

        // M2 Phase 1: Add section div wrappers around each h2 and its content
        $parts = preg_split('/(<h2[^>]*>.*?<\/h2>)/s', $html_report, -1, PREG_SPLIT_DELIM_CAPTURE);
        $wrapped_html = '';
        $in_section = false;

        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];

            if (preg_match('/<h2[^>]*>.*?<\/h2>/s', $part)) {
                // This is an h2 header
                if ($in_section) {
                    $wrapped_html .= '</div>' . "\n"; // Close previous section
                }
                $wrapped_html .= '<div class="section">' . "\n";
                $wrapped_html .= $part . "\n";
                $in_section = true;
            } else if (!empty(trim($part))) {
                $wrapped_html .= $part;
            }
        }

        if ($in_section) {
            $wrapped_html .= '</div>' . "\n"; // Close final section
        }

        $html_report = $wrapped_html;

        // M2.1: Clean stray periods from cached executive summaries
        $html_report = str_replace('<p>.</p>', '', $html_report);
        $html_report = preg_replace('/<p[^>]*>\s*\.\s*<\/p>/i', '', $html_report);
        $html_report = preg_replace('/>\s*\.\s*<hr/i', '><hr', $html_report);  // Remove period before <hr>
        $html_report = trim($html_report);

        // Extract company names for header
        $metadata = $bundle_data['metadata'] ?? [];
        $source_company_name = is_array($metadata['source_company'])
            ? $metadata['source_company']['name']
            : ($metadata['source_company'] ?? 'Unknown');
        $target_company_name = is_array($metadata['target_company'])
            ? $metadata['target_company']['name']
            : ($metadata['target_company'] ?? '');

        // M2 Phase 1: Wrap in Energy Exemplar styled structure
        echo '<div class="report-container">';
        echo '<div class="report-header">';
        echo '<h1>' . htmlspecialchars($source_company_name);
        if ($target_company_name) {
            echo ' → ' . htmlspecialchars($target_company_name);
        }
        echo '</h1>';
        echo '<div class="report-subtitle">';
        echo 'Intelligence Report | Run ' . $runid . ' | ' . userdate($run->timecompleted ?? time());
        echo '</div>';
        echo '</div>';

        echo '<div class="report-content">';
        echo $html_report;
        echo '</div>';

        // DIAGNOSTIC: Check what data we're reading for metadata display
        // Check synthesis_final_bundle artifact
        $citations_in_bundle = isset($bundle_data['citations']) ? count($bundle_data['citations']) : 0;
        error_log("[DIAGNOSTIC-META-77] synthesis_final_bundle citations array: {$citations_in_bundle}");
        error_log("[DIAGNOSTIC-META-77] bundle_data['citation_count']: " . ($bundle_data['citation_count'] ?? 'NOT SET'));
        error_log("[DIAGNOSTIC-META-77] Bundle keys: " . implode(', ', array_keys($bundle_data)));

        // Check canonical dataset
        $canonical = $DB->get_record('local_ci_artifact', [
            'runid' => $runid,
            'artifacttype' => 'canonical_nb_dataset'
        ]);

        if ($canonical) {
            $canonical_data = json_decode($canonical->jsondata, true);
            $metadata = $canonical_data['metadata'] ?? [];
            error_log("[DIAGNOSTIC-META-77] Canonical metadata source_company: " . ($metadata['source_company']['name'] ?? 'NOT SET'));
            error_log("[DIAGNOSTIC-META-77] Canonical metadata target_company: " . ($metadata['target_company']['name'] ?? 'NOT SET'));
            error_log("[DIAGNOSTIC-META-77] Canonical metadata nb_count: " . ($metadata['nb_count'] ?? 0));

            // Count actual citations in canonical (using correct key: nb_data)
            $canonical_citations = 0;
            if (isset($canonical_data['nb_data']) && is_array($canonical_data['nb_data'])) {
                foreach ($canonical_data['nb_data'] as $nb_code => $nb_record) {
                    $nb_citations = $nb_record['citations'] ?? [];
                    $canonical_citations += count($nb_citations);
                }
                error_log("[DIAGNOSTIC-META-77] Total citations in canonical NBs (nb_data): {$canonical_citations}");
            } else {
                error_log("[DIAGNOSTIC-META-77] nb_data key not found in canonical");
            }
        } else {
            error_log("[DIAGNOSTIC-META-77] canonical_nb_dataset NOT FOUND for run {$runid}");
        }

        // Count citations in markdown report
        $citation_refs_in_markdown = preg_match_all('/\[(\d+)\]/', $markdown_report, $matches);
        error_log("[DIAGNOSTIC-META-77] Citation references found in markdown: {$citation_refs_in_markdown}");

        // M2 Phase 1: Display metadata footer inside report-container
        echo '<div class="report-footer" style="padding: 20px 40px; border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.9em;">';
        echo '<p style="margin: 0;">';
        echo '<strong>Sections:</strong> ' . ($bundle_data['section_count'] ?? 0) . ' | ';
        echo '<strong>Citations:</strong> ' . ($bundle_data['citation_count'] ?? 0) . ' | ';
        $gen_time = $bundle_data['generation_timestamp'] ?? $phase2_artifact->timecreated;
        echo '<strong>Generated:</strong> ' . date('F j, Y \a\t g:i A', $gen_time);
        $qa_overall = 0;
        if (!empty($bundle_data['qa_scores']) && is_array($bundle_data['qa_scores'])) {
            $qa_overall = $bundle_data['qa_scores']['overall'] ?? 0;
        }
        echo ' | <strong>QA Score:</strong> ' . number_format($qa_overall, 2);
        echo '</p>';
        echo '</div>';
        echo '</div>'; // Close report-container

        // Display QA scores if available
        if (!empty($bundle_data['qa_scores']) && is_array($bundle_data['qa_scores'])) {
            echo '<div class="card mt-3">';
            echo '<div class="card-header">';
            echo '<h4 class="mb-0">';
            echo '<button class="btn btn-link text-left p-0 text-decoration-none" type="button" data-toggle="collapse" data-target="#qaScoresPhase2" aria-expanded="false" aria-controls="qaScoresPhase2">';
            echo '<i class="fa fa-chevron-right" id="qaScoresPhase2Icon"></i> View Quality Scores';
            echo '</button>';
            echo '</h4>';
            echo '</div>';
            echo '<div id="qaScoresPhase2" class="collapse">';
            echo '<div class="card-body">';

            $qa_scores = $bundle_data['qa_scores'];
            echo '<div class="row">';
            foreach ($qa_scores as $metric => $score) {
                if (is_numeric($score)) {
                    $badge_color = $score >= 0.7 ? 'success' : ($score >= 0.6 ? 'warning' : 'danger');
                    echo '<div class="col-md-3 mb-2">';
                    echo '<span class="badge badge-' . $badge_color . ' mr-2">' . number_format($score, 2) . '</span>';
                    echo '<span class="small">' . htmlspecialchars(ucwords(str_replace('_', ' ', $metric))) . '</span>';
                    echo '</div>';
                }
            }
            echo '</div>';

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

    } else {
        echo '<div class="alert alert-warning">';
        echo '<strong>Synthesis Data Error:</strong> synthesis_final_bundle artifact found but contains invalid data.';
        echo '</div>';
    }
} else if ($synthesis_bundle !== null && !empty($synthesis_bundle['html'])) {
    // Fallback: Display old V15 format for runs that don't have Phase 2
    $synthesis_bundle = apply_v17_1_compatibility_mapping($synthesis_bundle, $runid);

    // Count citations from the mapped synthesis bundle
    if (!empty($synthesis_bundle['citations']) && is_array($synthesis_bundle['citations'])) {
        $citation_count = count($synthesis_bundle['citations']);
    } else if (!empty($synthesis_bundle['sources']) && is_array($synthesis_bundle['sources'])) {
        $citation_count = count($synthesis_bundle['sources']);
    }

    echo '<div id="synthesis-content">';

    // M2 Phase 1: Wrap report in Energy Exemplar styled container
    echo '<div class="report-container">';

    // Report Header
    echo '<div class="report-header">';
    echo '<h1>' . htmlspecialchars($company->name);
    if ($targetcompany) {
        echo ' → ' . htmlspecialchars($targetcompany->name);
    }
    echo '</h1>';
    echo '<div class="report-subtitle">';
    echo 'Intelligence Report | Run ' . $runid . ' | ' . userdate($run->timecompleted, '%B %d, %Y at %I:%M %p');
    echo '</div>';
    echo '</div>';

    // Report Content
    echo '<div class="report-content">';
    echo $synthesis_bundle['html'];
    echo '</div>';

    echo '</div>'; // Close report-container

    echo '</div>'; // Close synthesis-content
} else {
    // No synthesis available at all
    echo '<div class="alert alert-warning">';
    echo '<strong>Synthesis Unavailable:</strong> Unable to generate or retrieve synthesis for this report. ';
    echo 'Please try regenerating or contact support if the issue persists.';
    echo '</div>';
}

// Admin-only debug section for normalized inputs
if ($synthesis_bundle !== null && $can_manage) {
    echo '<div class="card mt-3">';
    echo '<div class="card-header">';
    echo '<h4 class="mb-0">';
    echo '<button class="btn btn-link text-left p-0 text-decoration-none" type="button" data-toggle="collapse" data-target="#debugInputs" aria-expanded="false" aria-controls="debugInputs">';
    echo '<i class="fa fa-chevron-right" id="debugInputsIcon"></i> Show Normalized Inputs <span class="badge badge-warning ml-2">ADMIN</span>';
    echo '</button>';
    echo '</h4>';
    echo '</div>';
    echo '<div id="debugInputs" class="collapse">';
    echo '<div class="card-body">';
    
    // v17.1 Unified Compatibility: Get normalized inputs via adapter for debugging
    try {
        $debug_inputs = $compatibility_adapter->load_artifact($runid, 'synthesis_inputs');
        if (!$debug_inputs) {
            // Fallback to synthesis engine if adapter doesn't have data
            $debug_inputs = $synthesis_engine->get_normalized_inputs($runid);
        }

        // Populate debug_inputs from canonical dataset if needed
        $canonical_artifact = $DB->get_record('local_ci_artifact', [
            'runid' => $runid,
            'artifacttype' => 'canonical_nb_dataset'
        ]);

        if ($canonical_artifact) {
            $canonical_data = json_decode($canonical_artifact->jsondata, true);

            // Create company objects from metadata
            if (isset($canonical_data['metadata']['source_company'])) {
                $debug_inputs['company_source'] = (object)[
                    'name' => $canonical_data['metadata']['source_company']['name'] ?? 'Unknown',
                    'sector' => $canonical_data['metadata']['source_company']['sector'] ?? 'No sector'
                ];
            }

            if (isset($canonical_data['metadata']['target_company'])) {
                $debug_inputs['company_target'] = (object)[
                    'name' => $canonical_data['metadata']['target_company']['name'] ?? 'None',
                    'sector' => $canonical_data['metadata']['target_company']['sector'] ?? 'No sector'
                ];
            }

            // Populate NB array
            $debug_inputs['nb'] = [];
            if (!empty($canonical_data['nb_data'])) {
                foreach ($canonical_data['nb_data'] as $nb_code => $nb_content) {
                    $debug_inputs['nb'][$nb_code] = [
                        'code' => $nb_code,
                        'title' => $nb_content['title'] ?? $nb_code,
                        'citation_count' => count($nb_content['citations'] ?? [])
                    ];
                }
            }

            // Update processing stats
            $total_citations = 0;
            if (!empty($canonical_data['nb_data'])) {
                foreach ($canonical_data['nb_data'] as $nb_content) {
                    $total_citations += count($nb_content['citations'] ?? []);
                }
            }

            $debug_inputs['processing_stats'] = [
                'nb_count' => count($canonical_data['nb_data'] ?? []),
                'citation_count' => $total_citations
            ];

            error_log("[DIAGNOSTIC-META-77] debug_inputs rebuilt - source: " . ($debug_inputs['company_source']->name ?? 'NULL'));
            error_log("[DIAGNOSTIC-META-77] debug_inputs rebuilt - target: " . ($debug_inputs['company_target']->name ?? 'NULL'));
            error_log("[DIAGNOSTIC-META-77] debug_inputs rebuilt - nb count: " . count($debug_inputs['nb']));
            error_log("[DIAGNOSTIC-META-77] debug_inputs rebuilt - citation total: " . $total_citations);
        }

        // Check for canonical dataset artifact
        $canonical_dataset = $compatibility_adapter->load_artifact($runid, 'canonical_dataset');
        if ($canonical_dataset) {
            echo '<div class="alert alert-success">✅ Canonical Dataset Found: ' . 
                 ($canonical_dataset['metadata']['nb_count'] ?? 'unknown') . ' NBs, ' .
                 count($canonical_dataset['citations'] ?? []) . ' citations</div>';
        } else {
            echo '<div class="alert alert-warning">⚠️ Canonical Dataset Missing - This may cause blank reports</div>';
        }
        
        // Display patterns in compact tree format
        echo '<h5>Patterns Detected</h5>';
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<h6>Pressure Themes</h6>';
        echo '<pre class="small bg-light p-2" style="max-height: 300px; overflow-y: auto;">';
        
        // Try to get actual patterns
        try {
            // M1T5-8: detect_patterns moved to analysis_engine
            require_once(__DIR__ . '/classes/services/analysis_engine.php');
            $analysis_engine = new \local_customerintel\services\analysis_engine($runid, $debug_inputs);
            $patterns = $analysis_engine->detect_patterns($debug_inputs);
            if (!empty($patterns['pressures'])) {
                foreach (array_slice($patterns['pressures'], 0, 5) as $i => $pressure) {
                    echo "• " . htmlspecialchars($pressure['theme'] ?? "Pressure " . ($i + 1)) . "\n";
                    echo "  - Source: " . htmlspecialchars($pressure['source'] ?? 'Unknown') . "\n";
                    if (!empty($pressure['text'])) {
                        echo "  - Text: " . htmlspecialchars(substr($pressure['text'], 0, 50)) . "...\n";
                    }
                }
            } else {
                echo "No pressure patterns detected\n";
            }
        } catch (Exception $e) {
            echo "Pattern detection not available\n";
        }
        
        echo '</pre>';
        echo '</div>';
        
        echo '<div class="col-md-6">';
        echo '<h6>Bridge Items</h6>';
        echo '<pre class="small bg-light p-2" style="max-height: 300px; overflow-y: auto;">';
        
        // Try to get actual bridge items
        try {
            // M1T5-8: build_target_bridge moved to analysis_engine
            // Reuse analysis_engine instance from above (or create new if needed)
            if (!isset($analysis_engine)) {
                require_once(__DIR__ . '/classes/services/analysis_engine.php');
                $analysis_engine = new \local_customerintel\services\analysis_engine($runid, $debug_inputs);
            }
            $bridge = $analysis_engine->build_target_bridge($debug_inputs['company_source'], $debug_inputs['company_target']);
            if (!empty($bridge['items'])) {
                foreach (array_slice($bridge['items'], 0, 5) as $i => $item) {
                    echo "• Bridge Item " . ($i + 1) . "\n";
                    echo "  - Relevance: " . htmlspecialchars($item['why_it_matters_to_target'] ?? 'Unknown') . "\n";
                    echo "  - Timing: " . htmlspecialchars($item['timing_sync'] ?? 'Unknown') . "\n";
                    if (!empty($item['supporting_evidence'])) {
                        echo "  - Evidence: " . count($item['supporting_evidence']) . " items\n";
                    }
                }
            } else {
                echo "No bridge items detected\n";
            }
        } catch (Exception $e) {
            echo "Bridge analysis not available\n";
        }
        
        echo '</pre>';
        echo '</div>';
        echo '</div>';

        // DIAGNOSTIC: Check debug_inputs data source
        error_log("[DIAGNOSTIC-META-77] debug_inputs structure check:");
        error_log("[DIAGNOSTIC-META-77] debug_inputs keys: " . implode(', ', array_keys($debug_inputs)));
        error_log("[DIAGNOSTIC-META-77] debug_inputs['company_source'] type: " . gettype($debug_inputs['company_source'] ?? null));
        error_log("[DIAGNOSTIC-META-77] debug_inputs['company_source']->name: " . ($debug_inputs['company_source']->name ?? 'NOT SET'));
        error_log("[DIAGNOSTIC-META-77] debug_inputs['company_target'] type: " . gettype($debug_inputs['company_target'] ?? null));
        if ($debug_inputs['company_target']) {
            error_log("[DIAGNOSTIC-META-77] debug_inputs['company_target']->name: " . ($debug_inputs['company_target']->name ?? 'NOT SET'));
        } else {
            error_log("[DIAGNOSTIC-META-77] debug_inputs['company_target']: NULL");
        }
        error_log("[DIAGNOSTIC-META-77] debug_inputs['nb'] count: " . count($debug_inputs['nb'] ?? []));
        error_log("[DIAGNOSTIC-META-77] debug_inputs['citations'] count: " . count($debug_inputs['citations'] ?? []));
        error_log("[DIAGNOSTIC-META-77] debug_inputs['processing_stats']: " . json_encode($debug_inputs['processing_stats'] ?? []));

        // Display raw input summary
        echo '<h5 class="mt-3">Input Summary</h5>';
        echo '<div class="row">';
        echo '<div class="col-md-3">';
        echo '<strong>Source Company:</strong><br>';
        echo htmlspecialchars($debug_inputs['company_source']->name ?? 'Unknown') . '<br>';
        echo '<small class="text-muted">' . htmlspecialchars($debug_inputs['company_source']->sector ?? 'No sector') . '</small>';
        echo '</div>';
        echo '<div class="col-md-3">';
        echo '<strong>Target Company:</strong><br>';
        if ($debug_inputs['company_target']) {
            echo htmlspecialchars($debug_inputs['company_target']->name) . '<br>';
            echo '<small class="text-muted">' . htmlspecialchars($debug_inputs['company_target']->sector ?? 'No sector') . '</small>';
        } else {
            echo '<em class="text-muted">None</em>';
        }
        echo '</div>';
        echo '<div class="col-md-3">';
        echo '<strong>NB Results:</strong><br>';
        echo count($debug_inputs['nb'] ?? []) . ' NBs processed<br>';
        $citation_count = $debug_inputs['processing_stats']['citation_count'] ?? 0;
        echo '<small class="text-muted">' . $citation_count . ' citations found</small>';
        echo '</div>';
        echo '<div class="col-md-3">';
        echo '<strong>Processing Stats:</strong><br>';
        $stats = $debug_inputs['processing_stats'] ?? [];
        echo ($stats['nb_count'] ?? 0) . ' NBs, ' . ($stats['citation_count'] ?? 0) . ' citations<br>';
        echo '<small class="text-muted">Run ID: ' . $runid . '</small>';
        echo '</div>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-warning">';
        echo '<strong>Debug Error:</strong> ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// Add JavaScript for collapsible sections
echo '<script type="text/javascript">
$(document).ready(function() {
    $("#qaDetails").on("show.bs.collapse", function() {
        $("#qaDetailsIcon").removeClass("fa-chevron-right").addClass("fa-chevron-down");
    });
    $("#qaDetails").on("hide.bs.collapse", function() {
        $("#qaDetailsIcon").removeClass("fa-chevron-down").addClass("fa-chevron-right");
    });
    
    $("#debugInputs").on("show.bs.collapse", function() {
        $("#debugInputsIcon").removeClass("fa-chevron-right").addClass("fa-chevron-down");
    });
    $("#debugInputs").on("hide.bs.collapse", function() {
        $("#debugInputsIcon").removeClass("fa-chevron-down").addClass("fa-chevron-right");
    });
});

// Diagnostic preview function
function previewDiagnostics(runid) {
    fetch("' . $CFG->wwwroot . '/local/customerintel/download_diagnostics.php?runid=" + runid + "&action=preview&format=json")
        .then(response => response.json())
        .then(data => {
            let content = "<h4>Diagnostic Preview for Run " + runid + "</h4>";
            content += "<p><strong>Run Status:</strong> " + data.run_status + "</p>";
            content += "<p><strong>Completed:</strong> " + data.run_completed + "</p>";
            
            content += "<h5>Available Data:</h5>";
            content += "<ul>";
            content += "<li><strong>Artifacts:</strong> " + data.available_data.artifacts.length + " found</li>";
            data.available_data.artifacts.forEach(artifact => {
                content += "<li class=\"ml-3\">📄 " + artifact.phase + "/" + artifact.type + " (" + Math.round(artifact.size_bytes/1024) + " KB)</li>";
            });
            content += "<li><strong>Compatibility Logs:</strong> " + data.available_data.compatibility_logs + " entries</li>";
            content += "<li><strong>Telemetry Records:</strong> " + data.available_data.telemetry_records + " entries</li>";
            content += "<li><strong>Synthesis Cache:</strong> " + (data.available_data.synthesis_cache.exists ? "Available (" + Math.round(data.available_data.synthesis_cache.size_bytes/1024) + " KB)" : "Not found") + "</li>";
            content += "</ul>";
            
            content += "<p class=\"mt-3\"><a href=\"' . $CFG->wwwroot . '/local/customerintel/download_diagnostics.php?runid=" + runid + "\" class=\"btn btn-warning\">Download Full Archive</a></p>";
            
            // Create modal dialog
            if ($("#diagnosticModal").length === 0) {
                $("body").append("<div class=\"modal fade\" id=\"diagnosticModal\" tabindex=\"-1\"><div class=\"modal-dialog modal-lg\"><div class=\"modal-content\"><div class=\"modal-header\"><h5 class=\"modal-title\">Diagnostic Preview</h5><button type=\"button\" class=\"close\" data-dismiss=\"modal\">&times;</button></div><div class=\"modal-body\" id=\"diagnosticModalBody\"></div></div></div></div>");
            }
            
            $("#diagnosticModalBody").html(content);
            $("#diagnosticModal").modal("show");
        })
        .catch(error => {
            alert("Error loading diagnostic preview: " + error);
        });
}
</script>';

// Close tab structure if trace mode is enabled
if ($show_trace_tab) {
    // Close Report tab content
    echo '</div>'; // End of report-content tab-pane
    
    // Add Data Trace tab content
    echo '<div class="tab-pane fade" id="trace-content" role="tabpanel" aria-labelledby="trace-tab">';
    echo '<div class="card-body">';
    echo '<div class="text-center mb-4">';
    echo '<h3>🔍 Transparent Pipeline View</h3>';
    echo '<p class="text-muted">Complete data lineage and artifacts for this intelligence run</p>';
    echo '</div>';
    
    // Check if artifacts exist for this run
    require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_repository.php');
    $artifact_repo = new \local_customerintel\services\artifact_repository();
    $artifacts = $artifact_repo->get_artifacts_for_run($runid);
    
    if (!empty($artifacts)) {
        $stats = $artifact_repo->get_artifact_stats($runid);
        
        echo '<div class="row mb-4">';
        echo '<div class="col-md-3 text-center">';
        echo '<div class="card border-primary">';
        echo '<div class="card-body">';
        echo '<h4 class="text-primary">' . $stats['total_count'] . '</h4>';
        echo '<small class="text-muted">Artifacts Captured</small>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="col-md-3 text-center">';
        echo '<div class="card border-info">';
        echo '<div class="card-body">';
        echo '<h4 class="text-info">' . count($stats['phases']) . '</h4>';
        echo '<small class="text-muted">Pipeline Phases</small>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="col-md-3 text-center">';
        echo '<div class="card border-success">';
        echo '<div class="card-body">';
        echo '<h4 class="text-success">' . \local_customerintel\services\artifact_repository::format_size($stats['total_size']) . '</h4>';
        echo '<small class="text-muted">Data Captured</small>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="col-md-3 text-center">';
        echo '<div class="card border-warning">';
        echo '<div class="card-body">';
        echo '<h4 class="text-warning">' . (isset($stats['timespan']['duration_seconds']) ? gmdate('H:i:s', $stats['timespan']['duration_seconds']) : 'N/A') . '</h4>';
        echo '<small class="text-muted">Capture Duration</small>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="text-center">';
        echo '<a href="' . new moodle_url('/local/customerintel/view_trace.php', ['runid' => $runid]) . '" class="btn btn-primary btn-lg">';
        echo '🔍 View Complete Pipeline Trace</a>';
        echo '<p class="text-muted mt-2">Explore all ' . $stats['total_count'] . ' artifacts across ' . count($stats['phases']) . ' pipeline phases</p>';
        echo '</div>';
        
    } else {
        echo '<div class="alert alert-info text-center">';
        echo '<h4>🔍 No Pipeline Data Available</h4>';
        echo '<p>No artifacts were captured for this run. This could happen if:</p>';
        echo '<ul class="text-left" style="display: inline-block;">';
        echo '<li>The run was completed before transparent pipeline tracing was enabled</li>';
        echo '<li>Trace mode was disabled during this run\'s execution</li>';
        echo '<li>The artifacts have been cleaned up due to retention policies</li>';
        echo '</ul>';
        echo '<p class="mt-3"><strong>Note:</strong> Trace mode is currently <span class="badge badge-success">enabled</span>. Future runs will capture pipeline artifacts.</p>';
        echo '</div>';
    }
    
    echo '</div>'; // End of card-body
    echo '</div>'; // End of trace-content tab-pane
    
    echo '</div>'; // End of tab-content
    echo '</div>'; // End of card for tabs
}

echo html_writer::tag('h3', 'Trace Log for Run ' . $runid);
 
$sql = "SELECT * FROM {local_ci_telemetry}
         WHERE runid = :runid
      ORDER BY timecreated ASC";
$params = ['runid' => $runid];
$tracelogs = $DB->get_records_sql($sql, $params);
 
if ($tracelogs) {
    echo html_writer::start_tag('div', ['class' => 'trace-log']);
    foreach ($tracelogs as $log) {
        $timestamp = date('Y-m-d H:i:s', $log->timecreated);
		$line = '[' . $timestamp . '] ' . strtoupper($log->metrickey);
		 
		if (!empty($log->payload)) {
			$decoded = json_decode($log->payload, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				$line .= '<pre style="margin:4px 0 12px 20px; padding:4px; background:#f7f7f7; border-left:3px solid #ccc;">' . $pretty . '</pre>';
			} else {
				$line .= ' — ' . htmlspecialchars($log->payload);
			}
		}
		 
		echo html_writer::tag('div', $line, ['style' => 'font-family: monospace; font-size: 13px; margin-bottom: 8px;']);
    }
    echo html_writer::end_tag('div');
} else {
    echo html_writer::tag('p', 'No trace logs found for this run.');
}

echo $OUTPUT->footer();

} catch (Throwable $e) {
    // Log the error to server logs
    error_log("CustomerIntel Report Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Display error on screen for debugging
    echo "<div style='background-color: #ffcccc; border: 2px solid #ff0000; padding: 20px; margin: 20px; font-family: monospace;'>";
    echo "<h2 style='color: #cc0000;'>CustomerIntel Report Error</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Error Code:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
    
    // Show additional context for Moodle exceptions
    if ($e instanceof moodle_exception) {
        echo "<p><strong>Debug Info:</strong> " . htmlspecialchars($e->debuginfo ?? 'None') . "</p>";
        echo "<p><strong>Error Code:</strong> " . htmlspecialchars($e->errorcode ?? 'None') . "</p>";
        if (isset($e->a)) {
            echo "<p><strong>Additional Data:</strong> ";
            if (is_array($e->a) || is_object($e->a)) {
                echo "<pre>" . htmlspecialchars(print_r($e->a, true)) . "</pre>";
            } else {
                echo htmlspecialchars($e->a);
            }
            echo "</p>";
        }
    }
    
    echo "<details style='margin-top: 20px;'>";
    echo "<summary style='cursor: pointer; color: #cc0000; font-weight: bold;'>Stack Trace (click to expand)</summary>";
    echo "<pre style='background-color: #f0f0f0; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</details>";
    echo "</div>";
    
    // Also try to output the footer if possible
    if (isset($OUTPUT)) {
        try {
            echo $OUTPUT->footer();
        } catch (Exception $footer_error) {
            // Ignore footer errors
        }
    }
    
    exit;
}