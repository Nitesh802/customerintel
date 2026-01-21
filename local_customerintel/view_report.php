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

// Verify the run exists and is completed
$run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);

if ($run->status !== 'completed') {
    throw new moodle_exception('reportnotready', 'local_customerintel', '', null, 
        'Run ' . $runid . ' is not completed. Status: ' . $run->status);
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
    if ($synthesis_bundle !== null) {
        $cache_hit = true;
        $cache_timestamp = $synthesis_engine->get_cache_timestamp($runid);
    } else {
        // v17.1 Compatibility: Check for final_bundle.json or synthesis_record.json artifacts before rebuilding
        $final_bundle_artifact = $DB->get_record('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'synthesis',
            'artifacttype' => 'final_bundle'
        ]);
        
        $synthesis_record_artifact = $DB->get_record('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'synthesis',
            'artifacttype' => 'synthesis_record'
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
if ($synthesis_bundle !== null && !empty($synthesis_bundle['html'])) {
    // v17.1 Compatibility: Auto-map bundle fields to v15 viewer schema
    $synthesis_bundle = apply_v17_1_compatibility_mapping($synthesis_bundle, $runid);
    
    // Count citations from the mapped synthesis bundle
    if (!empty($synthesis_bundle['citations']) && is_array($synthesis_bundle['citations'])) {
        $citation_count = count($synthesis_bundle['citations']);
    } else if (!empty($synthesis_bundle['sources']) && is_array($synthesis_bundle['sources'])) {
        $citation_count = count($synthesis_bundle['sources']);
    }
    
    // Parse synthesis metadata for QA summary
    $qa_summary = null;
    $qa_pass = false;
    $violation_count = 0;
    
    if (!empty($synthesis_bundle['selfcheck_report'])) {
        $selfcheck_data = json_decode($synthesis_bundle['selfcheck_report'], true);
        if ($selfcheck_data) {
            $qa_pass = $selfcheck_data['pass'] ?? false;
            $violation_count = isset($selfcheck_data['violations']) ? count($selfcheck_data['violations']) : 0;
        }
    }
    
    echo '<div id="synthesis-content">';
    
    // Main Playbook Content
    echo '<div class="card mb-4">';
    echo '<div class="card-header" style="background-color: #6f42c1; color: white;">';
    echo '<h3 class="mb-0">V15 Intelligence Playbook</h3>';
    echo '<small>S1 Precision Mode - Strategic + Direct Voice</small>';
    echo '</div>';
    echo '<div class="card-body">';
    echo $synthesis_bundle['html'];
    echo '</div>';
    echo '</div>';
    
    // Interactive QA Summary (Slice 8 Enhancement)
    echo $output->render_qa_summary($runid);
    
    // Interactive Telemetry Chart (Slice 8 Enhancement)
    echo $output->render_telemetry_chart($runid);
    
    // Citation Metrics (Slice 8 Enhancement)
    echo $output->render_citation_metrics($runid);
    
    // Legacy QA Details Section (fallback/collapsible)
    echo '<div class="card mt-3">';
    echo '<div class="card-header">';
    echo '<h4 class="mb-0">';
    echo '<button class="btn btn-link text-left p-0 text-decoration-none" type="button" data-toggle="collapse" data-target="#qaDetailsLegacy" aria-expanded="false" aria-controls="qaDetailsLegacy">';
    echo '<i class="fa fa-chevron-right" id="qaDetailsLegacyIcon"></i> View Legacy QA Details';
    if (!$qa_pass) {
        echo ' <span class="badge badge-warning ml-2">Issues Found</span>';
    }
    echo '</button>';
    echo '</h4>';
    echo '</div>';
    echo '<div id="qaDetailsLegacy" class="collapse">';
    echo '<div class="card-body">';
    
    // Parse synthesis reports
    $voice_report_data = !empty($synthesis_bundle['voice_report']) ? json_decode($synthesis_bundle['voice_report'], true) : null;
    $selfcheck_data = !empty($synthesis_bundle['selfcheck_report']) ? json_decode($synthesis_bundle['selfcheck_report'], true) : null;
    $json_data = !empty($synthesis_bundle['json']) ? json_decode($synthesis_bundle['json'], true) : null;
    
    // Parse V15 structure if available
    $v15_structure = !empty($synthesis_bundle['v15_structure']) ? $synthesis_bundle['v15_structure'] : null;
    if (!$v15_structure && $json_data) {
        $v15_structure = $json_data; // Fallback to JSON data
    }
    
    // V15 QA Scores Section
    echo '<div class="row">';
    echo '<div class="col-md-4">';
    echo '<h5>V15 QA Scores</h5>';
    
    // v17.1 Compatibility: Try multiple sources for QA scores
    $scores = null;
    if ($v15_structure && isset($v15_structure['qa']['scores'])) {
        $scores = $v15_structure['qa']['scores'];
    } elseif (!empty($synthesis_bundle['qa_score'])) {
        $scores = $synthesis_bundle['qa_score'];
    } elseif (!empty($json_data['qa']['scores'])) {
        $scores = $json_data['qa']['scores'];
    }
    
    if ($scores) {
        $score_labels = [
            'relevance_density' => 'Relevance Density',
            'pov_strength' => 'POV Strength',
            'evidence_health' => 'Evidence Health',
            'precision' => 'Precision',
            'target_awareness' => 'Target Awareness',
            'coherence' => 'Coherence (Slice 5)',
            'pattern_alignment' => 'Gold Standard Alignment'
        ];
        foreach ($score_labels as $key => $label) {
            $score = $scores[$key] ?? 0;
            $badge_color = $score >= 0.7 ? 'success' : ($score >= 0.6 ? 'warning' : 'danger');
            echo '<div class="small mb-1">';
            echo '<span class="badge badge-' . $badge_color . ' mr-2">' . number_format($score, 2) . '</span>';
            echo htmlspecialchars($label);
            echo '</div>';
        }
        
        // Show warnings if any
        if (!empty($v15_structure['qa']['warnings'])) {
            echo '<div class="mt-2 small text-warning">' . count($v15_structure['qa']['warnings']) . ' warnings</div>';
        }
    } else {
        echo '<div class="text-muted small">V15 QA scores not available</div>';
    }
    echo '</div>';
    
    // Self-Check Violations Section
    echo '<div class="col-md-4">';
    echo '<h5>Self-Check Violations</h5>';
    if ($selfcheck_data && !empty($selfcheck_data['violations'])) {
        $violations_by_rule = [];
        foreach ($selfcheck_data['violations'] as $violation) {
            $rule = $violation['rule'] ?? 'unknown';
            if (!isset($violations_by_rule[$rule])) {
                $violations_by_rule[$rule] = [];
            }
            $violations_by_rule[$rule][] = $violation;
        }
        
        foreach ($violations_by_rule as $rule => $violations) {
            echo '<div class="mb-2">';
            echo '<strong>' . htmlspecialchars(ucwords(str_replace('_', ' ', $rule))) . '</strong> (' . count($violations) . ')';
            foreach ($violations as $violation) {
                echo '<div class="small text-muted ml-2">';
                echo '<span class="badge badge-' . ($violation['severity'] === 'error' ? 'danger' : 'warning') . ' mr-1">';
                echo strtoupper($violation['severity']);
                echo '</span>';
                echo htmlspecialchars($violation['location'] ?? 'Unknown location');
                if (!empty($violation['message'])) {
                    echo '<br><em>' . htmlspecialchars(substr($violation['message'], 0, 100));
                    if (strlen($violation['message']) > 100) echo '...';
                    echo '</em>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    } else {
        echo '<div class="text-success small">No violations found</div>';
    }
    echo '</div>';
    
    // Citations Section
    echo '<div class="col-md-4">';
    echo '<h5>Enriched Citations</h5>';
    
    // v17.1 Compatibility: Try multiple sources for citations
    $citation_sources = null;
    if (!empty($synthesis_bundle['sources']) && is_array($synthesis_bundle['sources'])) {
        $citation_sources = $synthesis_bundle['sources'];
    } elseif (!empty($synthesis_bundle['citations']) && is_array($synthesis_bundle['citations'])) {
        $citation_sources = $synthesis_bundle['citations'];
    }
    
    if ($citation_sources) {
        $source_count = 0;
        foreach ($citation_sources as $source) {
            $source_count++;
            echo '<div class="small mb-1">';
            echo '<strong>[' . $source_count . ']</strong> ';
            if (!empty($source['title'])) {
                echo htmlspecialchars($source['title']);
            } else {
                echo 'Untitled Source';
            }
            if (!empty($source['domain'])) {
                echo '<br><span class="text-muted">' . htmlspecialchars($source['domain']) . '</span>';
            } else if (!empty($source['url'])) {
                $domain = parse_url($source['url'], PHP_URL_HOST);
                echo '<br><span class="text-muted">' . htmlspecialchars($domain ?? 'Unknown domain') . '</span>';
            }
            echo '</div>';
        }
        if ($source_count === 0) {
            echo '<div class="text-muted small">No citations found</div>';
        }
    } else {
        echo '<div class="text-muted small">Citations not available</div>';
    }
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
} else {
    // No synthesis available - show error
    echo '<div class="alert alert-warning">';
    echo '<strong>Synthesis Unavailable:</strong> Unable to generate or retrieve synthesis for this report. ';
    echo 'Please try refreshing the page or contact support if the issue persists.';
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
        
        // Display patterns in compact tree format
        echo '<h5>Patterns Detected</h5>';
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<h6>Pressure Themes</h6>';
        echo '<pre class="small bg-light p-2" style="max-height: 300px; overflow-y: auto;">';
        
        // Try to get actual patterns
        try {
            $patterns = $synthesis_engine->detect_patterns($debug_inputs);
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
            $bridge = $synthesis_engine->build_target_bridge($debug_inputs['company_source'], $debug_inputs['company_target']);
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
        echo '<small class="text-muted">' . count($debug_inputs['citations'] ?? []) . ' citations found</small>';
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