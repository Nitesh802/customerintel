<?php
/**
 * Download Diagnostics - One-click diagnostic archive generation
 *
 * Generates and serves comprehensive diagnostic archives for completed runs
 * including artifacts, logs, telemetry, and system configuration.
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();

$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

// Required parameter
$runid = required_param('runid', PARAM_INT);

// Optional parameters
$action = optional_param('action', 'generate', PARAM_ALPHA);
$format = optional_param('format', 'download', PARAM_ALPHA); // download or json

try {
    // Verify the run exists and is completed
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
    
    if ($run->status !== 'completed') {
        throw new moodle_exception('diagnostics_run_not_completed', 'local_customerintel', '', null, 
            'Run ' . $runid . ' is not completed. Status: ' . $run->status);
    }
    
    // Check user permissions
    $can_manage = has_capability('local/customerintel:manage', $context);
    if ($run->initiatedbyuserid != $USER->id && !$can_manage) {
        throw new moodle_exception('nopermission', 'local_customerintel');
    }
    
    // Initialize diagnostic service
    require_once($CFG->dirroot . '/local/customerintel/classes/services/diagnostic_archive_service.php');
    $diagnostic_service = new \local_customerintel\services\diagnostic_archive_service();
    
    if ($action === 'generate') {
        // Generate diagnostic archive
        $result = $diagnostic_service->generate_diagnostic_archive($runid);
        
        if (!$result['success']) {
            throw new moodle_exception('diagnostics_generation_failed', 'local_customerintel', '', null, 
                'Failed to generate diagnostic archive: ' . $result['error']);
        }
        
        if ($format === 'json') {
            // Return JSON response for AJAX requests
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
        
        // Serve the ZIP file for download
        $filepath = $result['filepath'];
        $filename = $result['filename'];
        
        if (!file_exists($filepath)) {
            throw new moodle_exception('diagnostics_file_not_found', 'local_customerintel', '', null, 
                'Diagnostic archive file not found: ' . $filename);
        }
        
        // Set headers for file download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Stream the file
        readfile($filepath);
        
        // Clean up the temporary file
        unlink($filepath);
        
        exit;
        
    } else if ($action === 'preview') {
        // Generate preview of what would be included (without creating the actual archive)
        
        // Get basic info about available data
        $preview_data = [
            'runid' => $runid,
            'run_status' => $run->status,
            'run_completed' => userdate($run->timecompleted),
            'available_data' => []
        ];
        
        // Check for artifacts
        $artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid], 'phase ASC, artifacttype ASC');
        $preview_data['available_data']['artifacts'] = [];
        foreach ($artifacts as $artifact) {
            $preview_data['available_data']['artifacts'][] = [
                'phase' => $artifact->phase,
                'type' => $artifact->artifacttype,
                'size_bytes' => strlen($artifact->jsondata ?? ''),
                'created' => userdate($artifact->timecreated)
            ];
        }
        
        // Check for logs
        $log_count = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_ci_log} 
             WHERE runid = :runid 
             AND (message LIKE '%[Compatibility]%' OR message LIKE '%compatibility%' OR message LIKE '%adapter%')",
            ['runid' => $runid]
        );
        $preview_data['available_data']['compatibility_logs'] = $log_count;
        
        // Check for telemetry
        $telemetry_count = $DB->count_records('local_ci_telemetry', ['runid' => $runid]);
        $preview_data['available_data']['telemetry_records'] = $telemetry_count;
        
        // Check for synthesis cache
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        $preview_data['available_data']['synthesis_cache'] = [
            'exists' => !empty($synthesis),
            'size_bytes' => $synthesis ? strlen($synthesis->jsoncontent ?? '') : 0,
            'last_updated' => $synthesis ? userdate($synthesis->updatedat) : null
        ];
        
        header('Content-Type: application/json');
        echo json_encode($preview_data, JSON_PRETTY_PRINT);
        exit;
        
    } else {
        throw new moodle_exception('invalid_action', 'local_customerintel', '', null, 
            'Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("CustomerIntel Diagnostics Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode()
        ]);
        exit;
    }
    
    // Display error page
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title('Diagnostic Download Error');
    $PAGE->set_heading('Diagnostic Download Error');
    $PAGE->set_pagelayout('admin');
    
    echo $OUTPUT->header();
    
    echo '<div class="alert alert-danger">';
    echo '<h4>Diagnostic Download Failed</h4>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    if ($e instanceof moodle_exception && !empty($e->debuginfo)) {
        echo '<p><strong>Debug info:</strong> ' . htmlspecialchars($e->debuginfo) . '</p>';
    }
    echo '<p><a href="' . new moodle_url('/local/customerintel/view_report.php', ['runid' => $runid]) . '" class="btn btn-primary">Back to Report</a></p>';
    echo '</div>';
    
    echo $OUTPUT->footer();
}