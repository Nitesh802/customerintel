<?php
/**
 * Customer Intelligence Dashboard - Trace Log Viewer
 * 
 * Displays detailed trace logs for synthesis phase debugging
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:view', $context);

// Parameters
$runid = required_param('runid', PARAM_INT);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url('/local/customerintel/trace_viewer.php', ['runid' => $runid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Trace Log - Run ' . $runid);
$PAGE->set_heading('Synthesis Trace Log - Run ' . $runid);

// Check if trace mode is enabled
$trace_enabled = get_config('local_customerintel', 'enable_detailed_trace_logging');
if ($trace_enabled !== '1') {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Trace mode is not enabled. Please enable "Enable Trace Mode (Detailed Phase Logging)" in the CustomerIntel settings to view trace logs.', 'error');
    echo $OUTPUT->footer();
    exit;
}

// Get run details to verify it exists and user can access it
$run = $DB->get_record('local_ci_run', ['id' => $runid]);
if (!$run) {
    throw new moodle_exception('Run not found', 'local_customerintel');
}

// Get trace logs from telemetry
$sql = "SELECT * FROM {local_ci_telemetry} 
        WHERE runid = :runid 
        AND metrickey LIKE 'trace_%'
        ORDER BY timecreated ASC";

$trace_logs = $DB->get_records_sql($sql, ['runid' => $runid]);

// Also get any debugging logs that contain [TRACE]
$debug_sql = "SELECT * FROM {local_ci_telemetry} 
              WHERE runid = :runid 
              AND (metrickey LIKE '%trace%' OR payload LIKE '%TRACE%')
              ORDER BY timecreated ASC";

$debug_logs = $DB->get_records_sql($debug_sql, ['runid' => $runid]);

// Combine and deduplicate logs
$all_logs = array_merge($trace_logs, $debug_logs);
$unique_logs = [];
$seen_keys = [];

foreach ($all_logs as $log) {
    $key = $log->timecreated . '_' . $log->metrickey;
    if (!in_array($key, $seen_keys)) {
        $unique_logs[] = $log;
        $seen_keys[] = $key;
    }
}

// Sort by time
usort($unique_logs, function($a, $b) {
    return $a->timecreated - $b->timecreated;
});

echo $OUTPUT->header();

// Get company information
$company = $DB->get_record('local_ci_company', ['id' => $run->companyid]);

// Display run information
echo '<div class="alert alert-info">';
echo '<h4>Run Information</h4>';
echo '<p><strong>Run ID:</strong> ' . $runid . '</p>';
if ($company) {
    echo '<p><strong>Company:</strong> ' . s($company->name) . '</p>';
}
echo '<p><strong>Status:</strong> ' . s($run->status) . '</p>';
echo '<p><strong>Created:</strong> ' . userdate($run->timecreated) . '</p>';
if ($run->timemodified) {
    echo '<p><strong>Modified:</strong> ' . userdate($run->timemodified) . '</p>';
}
echo '</div>';

// Display trace logs
if (empty($unique_logs)) {
    echo $OUTPUT->notification('No trace logs found for this run. Make sure the synthesis process has run with trace mode enabled.', 'info');
} else {
    echo '<h3>Trace Log (' . count($unique_logs) . ' entries)</h3>';
    
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-sm">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Timestamp</th>';
    echo '<th>Phase</th>';
    echo '<th>Message</th>';
    echo '<th>Data</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($unique_logs as $log) {
        echo '<tr>';
        
        // Timestamp
        echo '<td><small>' . userdate($log->timecreated, '%H:%M:%S') . '</small></td>';
        
        // Phase (extract from metric key)
        $phase = str_replace('trace_', '', $log->metrickey);
        $phase = str_replace('_', ' ', $phase);
        $phase = ucwords($phase);
        echo '<td><span class="badge badge-primary">' . s($phase) . '</span></td>';
        
        // Message (clean up the metric key for display)
        $message = $phase;
        if (strpos($log->metrickey, 'start') !== false) {
            $message .= ' - Start';
        } elseif (strpos($log->metrickey, 'complete') !== false) {
            $message .= ' - Complete';
        } elseif (strpos($log->metrickey, 'entry') !== false) {
            $message .= ' - Entry';
        }
        echo '<td>' . s($message) . '</td>';
        
        // Data (payload)
        echo '<td>';
        if (!empty($log->payload)) {
            $payload = json_decode($log->payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo '<details>';
                echo '<summary><small>View Data</small></summary>';
                echo '<pre style="font-size: 11px; max-height: 200px; overflow-y: auto;">';
                echo htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT));
                echo '</pre>';
                echo '</details>';
            } else {
                echo '<small>' . s(substr($log->payload, 0, 100)) . '...</small>';
            }
        } else {
            echo '<em>No data</em>';
        }
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// Add a back link
echo '<div class="mt-3">';
echo '<a href="' . new moodle_url('/local/customerintel/reports.php') . '" class="btn btn-secondary">← Back to Reports</a>';
echo '</div>';

echo $OUTPUT->footer();
?>