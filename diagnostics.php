<?php
/**
 * Diagnostics & Health Hub - Unified admin interface for pipeline monitoring
 *
 * Provides comprehensive diagnostics interface with four main tabs:
 * - Trace Timeline: Chronological log of all trace events
 * - Run Health: Run Doctor diagnostic analysis 
 * - Performance Trends: Charts and analytics
 * - Common Issues: Recurring problems and fixes
 *
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/diagnostics_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/telemetry_manager.php');

// Security checks
require_login();
$context = context_system::instance();
require_capability('local/customerintel:admin', $context);

// Page setup
$PAGE->set_url('/local/customerintel/diagnostics.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('diagnostics_hub', 'local_customerintel', '', true));
$PAGE->set_heading(get_string('diagnostics_hub', 'local_customerintel', '', true));
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add(get_string('pluginname', 'local_customerintel'), new moodle_url('/local/customerintel/dashboard.php'));
$PAGE->navbar->add(get_string('diagnostics_hub', 'local_customerintel', '', true));

// Parameters
$action = optional_param('action', 'overview', PARAM_ALPHANUMEXT);
$runid = optional_param('runid', 0, PARAM_INT);
$tab = optional_param('tab', 'trace', PARAM_ALPHANUMEXT);
$days = optional_param('days', 30, PARAM_INT);

// Initialize services
$diagnostics_service = new \local_customerintel\services\diagnostics_service();
$telemetry_manager = new \local_customerintel\services\telemetry_manager();

// Handle actions
$output_data = [];
$export_filename = '';

switch ($action) {
    case 'run_diagnostics':
        if ($runid > 0) {
            $results = $diagnostics_service->run_diagnostics($runid);
            $output_data['diagnostic_results'] = $results;
            $output_data['message'] = "Diagnostics completed for Run {$runid}";
        } else {
            $output_data['error'] = 'Invalid run ID';
        }
        break;
        
    case 'export_diagnostics':
        if ($runid > 0) {
            $diagnostic_data = $diagnostics_service->get_diagnostics($runid);
            if ($diagnostic_data) {
                $export_data = [
                    'export_type' => 'diagnostic_report',
                    'runid' => $runid,
                    'generated_at' => date('Y-m-d H:i:s'),
                    'diagnostics' => $diagnostic_data
                ];
                
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="diagnostics_run_' . $runid . '_' . date('Y-m-d') . '.json"');
                echo json_encode($export_data, JSON_PRETTY_PRINT);
                exit;
            }
        }
        break;
        
    case 'export_telemetry':
        $export_data = $telemetry_manager->export_telemetry_data($runid, $days);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="telemetry_export_' . date('Y-m-d') . '.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
        
    case 'get_trends':
        $output_data['trends'] = $telemetry_manager->get_performance_trends($days);
        break;
        
    default:
        // Default overview
        break;
}

// Get recent runs for dropdowns
$recent_runs = $DB->get_records_sql(
    "SELECT DISTINCT r.id, r.id as runid, r.timecompleted, d.status 
     FROM {local_ci_run} r 
     LEFT JOIN {local_ci_diagnostics} d ON r.id = d.runid 
     WHERE r.timecompleted IS NOT NULL 
     ORDER BY r.timecompleted DESC 
     LIMIT 50"
);

// Get performance trends for dashboard
$performance_trends = $telemetry_manager->get_performance_trends($days);

echo $OUTPUT->header();

// CSS for diagnostics interface
echo '<style>
.diagnostics-container {
    max-width: 1200px;
    margin: 0 auto;
}

.diagnostics-tabs {
    display: flex;
    border-bottom: 2px solid #ddd;
    margin-bottom: 20px;
}

.diagnostics-tab {
    padding: 12px 24px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-bottom: none;
    margin-right: 2px;
    cursor: pointer;
    text-decoration: none;
    color: #333;
}

.diagnostics-tab.active {
    background: #fff;
    border-bottom: 2px solid #fff;
    margin-bottom: -2px;
    font-weight: bold;
}

.diagnostics-tab:hover {
    background: #e9ecef;
    text-decoration: none;
}

.tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-top: none;
    min-height: 500px;
}

.health-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 4px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 12px;
}

.health-status.ok { background: #d4edda; color: #155724; }
.health-status.degraded { background: #fff3cd; color: #856404; }
.health-status.failed { background: #f8d7da; color: #721c24; }

.metric-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
}

.metric-value {
    font-size: 24px;
    font-weight: bold;
    color: #495057;
}

.metric-label {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}

.phase-chart {
    display: flex;
    align-items: center;
    margin: 10px 0;
}

.phase-bar {
    height: 20px;
    margin-left: 10px;
    border-radius: 4px;
    position: relative;
    min-width: 50px;
}

.phase-duration {
    position: absolute;
    right: 5px;
    top: 2px;
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.export-section {
    background: #e9ecef;
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
}

.alert-box {
    padding: 15px;
    border-radius: 8px;
    margin: 10px 0;
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.trace-timeline {
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
}

.trace-entry {
    padding: 8px;
    border-bottom: 1px solid #eee;
    font-family: monospace;
    font-size: 13px;
}

.trace-entry:nth-child(even) {
    background: #f8f9fa;
}

.flex-container {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.flex-item {
    flex: 1;
    min-width: 300px;
}
</style>';

// JavaScript for tab functionality
echo '<script>
function showTab(tabName) {
    // Hide all tab contents
    var contents = document.querySelectorAll(".tab-content");
    contents.forEach(function(content) {
        content.style.display = "none";
    });
    
    // Remove active class from all tabs
    var tabs = document.querySelectorAll(".diagnostics-tab");
    tabs.forEach(function(tab) {
        tab.classList.remove("active");
    });
    
    // Show selected tab content
    document.getElementById(tabName + "-content").style.display = "block";
    
    // Add active class to clicked tab
    document.querySelector("[onclick*=\"" + tabName + "\"]").classList.add("active");
}

function runDiagnostics() {
    var runid = document.getElementById("diagnostic-runid").value;
    if (runid) {
        window.location.href = "?action=run_diagnostics&runid=" + runid;
    } else {
        alert("Please select a run ID");
    }
}

function exportDiagnostics() {
    var runid = document.getElementById("export-runid").value;
    if (runid) {
        window.location.href = "?action=export_diagnostics&runid=" + runid;
    } else {
        alert("Please select a run ID");
    }
}

function exportTelemetry() {
    var runid = document.getElementById("telemetry-runid").value || "";
    var days = document.getElementById("telemetry-days").value || "30";
    window.location.href = "?action=export_telemetry&runid=" + runid + "&days=" + days;
}
</script>';

echo '<div class="diagnostics-container">';

// Header
echo '<h2>CustomerIntel Diagnostics & Health Hub</h2>';
echo '<p>Comprehensive pipeline monitoring and diagnostic tools for CustomerIntel synthesis operations.</p>';

// Display messages
if (!empty($output_data['message'])) {
    echo '<div class="alert-box alert-info">' . htmlspecialchars($output_data['message']) . '</div>';
}
if (!empty($output_data['error'])) {
    echo '<div class="alert-box alert-error">' . htmlspecialchars($output_data['error']) . '</div>';
}

// Tab navigation
echo '<div class="diagnostics-tabs">';
echo '<a href="#" class="diagnostics-tab' . ($tab === 'trace' ? ' active' : '') . '" onclick="showTab(\'trace\')">Trace Timeline</a>';
echo '<a href="#" class="diagnostics-tab' . ($tab === 'health' ? ' active' : '') . '" onclick="showTab(\'health\')">Run Health</a>';
echo '<a href="#" class="diagnostics-tab' . ($tab === 'performance' ? ' active' : '') . '" onclick="showTab(\'performance\')">Performance Trends</a>';
echo '<a href="#" class="diagnostics-tab' . ($tab === 'issues' ? ' active' : '') . '" onclick="showTab(\'issues\')">Common Issues</a>';
echo '</div>';

// Tab 1: Trace Timeline
echo '<div id="trace-content" class="tab-content"' . ($tab !== 'trace' ? ' style="display:none"' : '') . '>';
echo '<h3>Trace Timeline</h3>';
echo '<p>Chronological log of all trace events for synthesis runs.</p>';

echo '<div class="flex-container">';
echo '<div class="flex-item">';
echo '<label for="trace-runid">Select Run ID:</label>';
echo '<select id="trace-runid" onchange="loadTraceTimeline(this.value)">';
echo '<option value="">-- Select Run --</option>';
foreach ($recent_runs as $run) {
    echo '<option value="' . $run->runid . '">Run ' . $run->runid . ' (' . date('Y-m-d H:i', $run->timecompleted) . ')</option>';
}
echo '</select>';
echo '</div>';
echo '</div>';

if ($runid > 0) {
    // Get trace data for the selected run
    $trace_records = $DB->get_records('local_ci_telemetry', 
        ['runid' => $runid, 'metrickey' => 'trace_phase'], 
        'timecreated ASC'
    );
    
    echo '<div class="trace-timeline">';
    if (empty($trace_records)) {
        echo '<p>No trace data found for Run ' . $runid . '</p>';
    } else {
        foreach ($trace_records as $record) {
            $payload = json_decode($record->payload, true);
            $timestamp = date('H:i:s', $record->timecreated);
            $phase = $payload['phase_name'] ?? 'unknown';
            $message = $payload['message'] ?? '';
            $status = $payload['status'] ?? 'info';
            
            $status_color = $status === 'error' ? '#dc3545' : 
                           ($status === 'warning' ? '#ffc107' : '#28a745');
            
            echo '<div class="trace-entry">';
            echo '<span style="color: #666">[' . $timestamp . ']</span> ';
            echo '<span style="color: ' . $status_color . '; font-weight: bold">[' . strtoupper($phase) . ']</span> ';
            echo htmlspecialchars($message);
            
            if (isset($payload['duration_ms'])) {
                echo ' <span style="color: #666">(' . $payload['duration_ms'] . 'ms)</span>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
}

echo '</div>';

// Tab 2: Run Health
echo '<div id="health-content" class="tab-content"' . ($tab !== 'health' ? ' style="display:none"' : '') . '>';
echo '<h3>Run Health - Diagnostic Analysis</h3>';
echo '<p>Run Doctor diagnostic results for synthesis health assessment.</p>';

echo '<div class="flex-container">';
echo '<div class="flex-item">';
echo '<label for="diagnostic-runid">Select Run for Diagnostics:</label>';
echo '<select id="diagnostic-runid">';
echo '<option value="">-- Select Run --</option>';
foreach ($recent_runs as $run) {
    $selected = ($runid == $run->runid) ? ' selected' : '';
    echo '<option value="' . $run->runid . '"' . $selected . '>Run ' . $run->runid . ' (' . date('Y-m-d H:i', $run->timecompleted) . ')</option>';
}
echo '</select>';
echo '<button onclick="runDiagnostics()" style="margin-left: 10px;">Run Diagnostics</button>';
echo '</div>';
echo '</div>';

// Display diagnostic results if available
if (!empty($output_data['diagnostic_results'])) {
    $results = $output_data['diagnostic_results'];
    
    echo '<div class="metric-card">';
    echo '<h4>Overall Health Status</h4>';
    echo '<span class="health-status ' . strtolower($results['overall_health']) . '">' . $results['overall_health'] . '</span>';
    echo '<p style="margin-top: 10px;">' . htmlspecialchars($results['summary']) . '</p>';
    echo '</div>';
    
    if (!empty($results['issues'])) {
        echo '<div class="alert-box alert-error">';
        echo '<h5>Critical Issues (' . count($results['issues']) . ')</h5>';
        foreach ($results['issues'] as $issue) {
            echo '<p><strong>' . htmlspecialchars($issue['message']) . '</strong><br>';
            echo '<small>' . htmlspecialchars($issue['details']) . '</small></p>';
        }
        echo '</div>';
    }
    
    if (!empty($results['warnings'])) {
        echo '<div class="alert-box alert-warning">';
        echo '<h5>Warnings (' . count($results['warnings']) . ')</h5>';
        foreach ($results['warnings'] as $warning) {
            echo '<p><strong>' . htmlspecialchars($warning['message']) . '</strong><br>';
            echo '<small>' . htmlspecialchars($warning['details']) . '</small></p>';
        }
        echo '</div>';
    }
    
    // Artifacts status
    if (!empty($results['artifacts'])) {
        echo '<div class="metric-card">';
        echo '<h4>Artifacts Status</h4>';
        echo '<p>Found: ' . count($results['artifacts']['found']) . ' | ';
        echo 'Missing: ' . count($results['artifacts']['missing']) . ' | ';
        echo 'Corrupted: ' . count($results['artifacts']['corrupted']) . '</p>';
        echo '</div>';
    }
    
    // Phase coverage
    if (!empty($results['phases'])) {
        echo '<div class="metric-card">';
        echo '<h4>Phase Coverage</h4>';
        echo '<p>Completed: ' . count($results['phases']['completed']) . ' | ';
        echo 'Skipped: ' . count($results['phases']['skipped']) . ' | ';
        echo 'Zero Duration: ' . count($results['phases']['zero_duration']) . '</p>';
        echo '</div>';
    }
}

echo '</div>';

// Tab 3: Performance Trends
echo '<div id="performance-content" class="tab-content"' . ($tab !== 'performance' ? ' style="display:none"' : '') . '>';
echo '<h3>Performance Trends</h3>';
echo '<p>Historical performance analysis and trend visualization.</p>';

echo '<div class="flex-container">';
echo '<div class="flex-item">';
echo '<label for="trend-days">Analysis Period (days):</label>';
echo '<select id="trend-days" onchange="updateTrends(this.value)">';
echo '<option value="7"' . ($days == 7 ? ' selected' : '') . '>Last 7 days</option>';
echo '<option value="30"' . ($days == 30 ? ' selected' : '') . '>Last 30 days</option>';
echo '<option value="90"' . ($days == 90 ? ' selected' : '') . '>Last 90 days</option>';
echo '</select>';
echo '</div>';
echo '</div>';

// Overall statistics
if (!empty($performance_trends['overall_stats'])) {
    $stats = $performance_trends['overall_stats'];
    
    echo '<div class="flex-container">';
    echo '<div class="metric-card flex-item">';
    echo '<div class="metric-value">' . $stats['total_runs'] . '</div>';
    echo '<div class="metric-label">Total Runs</div>';
    echo '</div>';
    
    echo '<div class="metric-card flex-item">';
    echo '<div class="metric-value">' . $stats['success_rate_percent'] . '%</div>';
    echo '<div class="metric-label">Success Rate</div>';
    echo '</div>';
    
    echo '<div class="metric-card flex-item">';
    echo '<div class="metric-value">' . round($stats['average_duration_ms']/1000, 1) . 's</div>';
    echo '<div class="metric-label">Avg Duration</div>';
    echo '</div>';
    
    echo '<div class="metric-card flex-item">';
    echo '<div class="metric-value">' . $stats['runs_per_day'] . '</div>';
    echo '<div class="metric-label">Runs Per Day</div>';
    echo '</div>';
    echo '</div>';
}

// Phase duration charts
if (!empty($performance_trends['phase_durations'])) {
    echo '<div class="metric-card">';
    echo '<h4>Phase Duration Trends</h4>';
    
    foreach ($performance_trends['phase_durations'] as $phase => $data) {
        $health_color = $data['health_status'] === 'green' ? '#28a745' : 
                       ($data['health_status'] === 'yellow' ? '#ffc107' : '#dc3545');
        
        echo '<div class="phase-chart">';
        echo '<div style="width: 120px; text-align: right;">' . ucfirst($phase) . ':</div>';
        echo '<div class="phase-bar" style="background-color: ' . $health_color . '; width: ' . min(300, $data['average_ms']/10) . 'px;">';
        echo '<span class="phase-duration">' . round($data['average_ms']/1000, 1) . 's</span>';
        echo '</div>';
        echo '<span style="margin-left: 10px; color: #666;">trend: ' . $data['trend_direction'] . '</span>';
        echo '</div>';
    }
    echo '</div>';
}

// Health distribution
if (!empty($performance_trends['health_distribution'])) {
    $health = $performance_trends['health_distribution'];
    $total = array_sum($health);
    
    if ($total > 0) {
        echo '<div class="metric-card">';
        echo '<h4>Health Status Distribution</h4>';
        echo '<div style="display: flex; height: 30px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">';
        
        if ($health['OK'] > 0) {
            $width = ($health['OK'] / $total) * 100;
            echo '<div style="background: #28a745; width: ' . $width . '%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">';
            echo 'OK (' . $health['OK'] . ')';
            echo '</div>';
        }
        
        if ($health['DEGRADED'] > 0) {
            $width = ($health['DEGRADED'] / $total) * 100;
            echo '<div style="background: #ffc107; width: ' . $width . '%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">';
            echo 'DEGRADED (' . $health['DEGRADED'] . ')';
            echo '</div>';
        }
        
        if ($health['FAILED'] > 0) {
            $width = ($health['FAILED'] / $total) * 100;
            echo '<div style="background: #dc3545; width: ' . $width . '%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">';
            echo 'FAILED (' . $health['FAILED'] . ')';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
}

echo '</div>';

// Tab 4: Common Issues
echo '<div id="issues-content" class="tab-content"' . ($tab !== 'issues' ? ' style="display:none"' : '') . '>';
echo '<h3>Common Issues</h3>';
echo '<p>Auto-generated list of recurring anomalies and recommended fixes.</p>';

// Error frequency analysis
if (!empty($performance_trends['error_frequency'])) {
    $errors = $performance_trends['error_frequency'];
    
    echo '<div class="metric-card">';
    echo '<h4>Error Summary</h4>';
    echo '<p>Total Errors: ' . $errors['total_errors'] . ' | Total Warnings: ' . $errors['total_warnings'] . '</p>';
    echo '</div>';
    
    if (!empty($errors['most_common'])) {
        echo '<div class="metric-card">';
        echo '<h4>Most Common Issues</h4>';
        echo '<ul>';
        foreach ($errors['most_common'] as $issue_type => $count) {
            echo '<li><strong>' . htmlspecialchars($issue_type) . '</strong>: ' . $count . ' occurrences</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    if (!empty($errors['by_phase'])) {
        echo '<div class="metric-card">';
        echo '<h4>Issues by Phase</h4>';
        foreach ($errors['by_phase'] as $phase => $counts) {
            if ($counts['errors'] > 0 || $counts['warnings'] > 0) {
                echo '<p><strong>' . ucfirst($phase) . '</strong>: ';
                echo $counts['errors'] . ' errors, ' . $counts['warnings'] . ' warnings</p>';
            }
        }
        echo '</div>';
    }
}

// Recommendations
echo '<div class="metric-card">';
echo '<h4>Common Troubleshooting Steps</h4>';
echo '<ul>';
echo '<li><strong>Empty NB Data:</strong> Check NB module configuration and API connectivity</li>';
echo '<li><strong>Low Citation Count:</strong> Verify citation rebalancing settings and source diversity</li>';
echo '<li><strong>Missing Artifacts:</strong> Ensure artifact repository is properly configured and writable</li>';
echo '<li><strong>Phase Timeouts:</strong> Check LLM API rate limits and increase timeout settings</li>';
echo '<li><strong>Validation Failures:</strong> Review input data quality and validation thresholds</li>';
echo '</ul>';
echo '</div>';

echo '</div>';

// Export section
echo '<div class="export-section">';
echo '<h3>Export & Download</h3>';
echo '<div class="flex-container">';

echo '<div class="flex-item">';
echo '<h4>Export Diagnostic Report</h4>';
echo '<label for="export-runid">Run ID:</label>';
echo '<select id="export-runid">';
echo '<option value="">-- Select Run --</option>';
foreach ($recent_runs as $run) {
    echo '<option value="' . $run->runid . '">Run ' . $run->runid . '</option>';
}
echo '</select>';
echo '<button onclick="exportDiagnostics()" style="margin-left: 10px;">Export JSON</button>';
echo '</div>';

echo '<div class="flex-item">';
echo '<h4>Export Telemetry Data</h4>';
echo '<label for="telemetry-runid">Run ID (optional):</label>';
echo '<select id="telemetry-runid">';
echo '<option value="">-- All Runs --</option>';
foreach ($recent_runs as $run) {
    echo '<option value="' . $run->runid . '">Run ' . $run->runid . '</option>';
}
echo '</select>';
echo '<label for="telemetry-days" style="margin-left: 15px;">Days:</label>';
echo '<input type="number" id="telemetry-days" value="30" min="1" max="365" style="width: 60px;">';
echo '<button onclick="exportTelemetry()" style="margin-left: 10px;">Export JSON</button>';
echo '</div>';

echo '</div>';
echo '</div>';

echo '</div>'; // Close diagnostics-container

// Additional JavaScript
echo '<script>
function updateTrends(days) {
    window.location.href = "?tab=performance&days=" + days;
}

function loadTraceTimeline(runid) {
    if (runid) {
        window.location.href = "?tab=trace&runid=" + runid;
    }
}

// Auto-show appropriate tab based on URL parameters
document.addEventListener("DOMContentLoaded", function() {
    var urlParams = new URLSearchParams(window.location.search);
    var tab = urlParams.get("tab") || "trace";
    showTab(tab);
});
</script>';

echo $OUTPUT->footer();