<?php
/**
 * Temporary Synthesis Diagnostics Endpoint
 * 
 * Admin-only script for diagnosing synthesis pipeline issues.
 * DELETE AFTER USE.
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security - require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

// Guarantee output flush
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(true);

$runid = optional_param('runid', 0, PARAM_INT);

// Set up page
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/customerintel/synthesis_diag.php', ['runid' => $runid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Synthesis Diagnostics');
$PAGE->set_heading('Synthesis Diagnostics');

echo $OUTPUT->header();

echo '<div class="alert alert-warning">';
echo '<strong>Warning:</strong> This is a temporary diagnostics endpoint. DELETE after use.';
echo '</div>';

if (!$runid) {
    echo '<div class="alert alert-info">';
    echo 'Provide ?runid=X to test synthesis for a specific run.';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h2>Testing Synthesis for Run ID: ' . $runid . '</h2>';

// Capture all output and diagnostics
ob_start();
$success = false;
$error_message = '';
$exception_details = null;

try {
    // Load synthesis engine
    require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
    $synthesis_engine = new \local_customerintel\services\synthesis_engine();
    
    // Attempt synthesis
    $bundle = $synthesis_engine->build_report($runid);
    
    $success = true;
    echo '<div class="alert alert-success">';
    echo '<strong>SYNTHESIS_OK run=' . $runid . '</strong>';
    echo '</div>';
    
    // Always retrieve debug data after build_report
    $dbg = \local_customerintel\services\synthesis_engine::get_last_debug_data();
    
    // Display citation debug info if available
    if (isset($dbg['citations_input']) || isset($dbg['citations_normalized'])) {
        echo "<pre style='background:#111;color:#0f0;padding:10px;white-space:pre-wrap'>";
        echo "RAW CITATIONS INPUT:\n";
        echo htmlspecialchars(print_r($dbg['citations_input'] ?? '(none)', true));
        echo "\n\nNORMALIZED CITATIONS LIST:\n";
        echo htmlspecialchars(print_r($dbg['citations_normalized'] ?? '(none)', true));
        echo "</pre>";
    }
    
    echo '<h3>Generated Content Summary</h3>';
    echo '<ul>';
    echo '<li>HTML Content: ' . strlen($bundle['html']) . ' characters</li>';
    echo '<li>JSON Content: ' . strlen($bundle['json']) . ' characters</li>';
    echo '<li>Voice Report: ' . (empty($bundle['voice_report']) ? 'Empty' : 'Generated') . '</li>';
    echo '<li>Self-check Report: ' . (empty($bundle['selfcheck_report']) ? 'Empty' : 'Generated') . '</li>';
    echo '<li>Citations: ' . count($bundle['citations'] ?? []) . ' items</li>';
    echo '</ul>';
    
    // Show HTML preview (first 500 chars)
    echo '<h3>HTML Preview</h3>';
    echo '<div style="border: 1px solid #ccc; padding: 10px; max-height: 200px; overflow: auto;">';
    echo '<pre>' . htmlspecialchars(substr($bundle['html'], 0, 500)) . '...</pre>';
    echo '</div>';
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $exception_details = [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    // Always retrieve debug data even on failure
    $dbg = \local_customerintel\services\synthesis_engine::get_last_debug_data();
    
    // Display citation debug info if available
    if (isset($dbg['citations_input']) || isset($dbg['citations_normalized'])) {
        echo "<pre style='background:#111;color:#0f0;padding:10px;white-space:pre-wrap'>";
        echo "RAW CITATIONS INPUT:\n";
        echo htmlspecialchars(print_r($dbg['citations_input'] ?? '(none)', true));
        echo "\n\nNORMALIZED CITATIONS LIST:\n";
        echo htmlspecialchars(print_r($dbg['citations_normalized'] ?? '(none)', true));
        echo "</pre>";
    }
    
    // Include citation debug data in exception details JSON
    if (!empty($dbg)) {
        $exception_details['raw_citations'] = $dbg['citations_input'] ?? null;
        $exception_details['normalized_citations'] = $dbg['citations_normalized'] ?? null;
    }
    
    echo '<div class="alert alert-danger">';
    echo '<strong>SYNTHESIS_FAILED</strong><br>';
    echo 'Exception: ' . get_class($e) . '<br>';
    echo 'Message: ' . htmlspecialchars($e->getMessage()) . '<br>';
    echo 'File: ' . $e->getFile() . ':' . $e->getLine();
    echo '</div>';
}

// Capture all diagnostics output
$diagnostics_output = ob_get_clean();

echo $diagnostics_output;

// Show diagnostics logs
echo '<h3>Diagnostics Logs</h3>';
echo '<div class="alert alert-info">';
echo 'Check your Moodle logs or error logs for SYNTHESIS_DIAG entries.';
echo '</div>';

// Show run details
echo '<h3>Run Details</h3>';
$run = $DB->get_record('local_ci_run', ['id' => $runid]);
if ($run) {
    echo '<ul>';
    echo '<li>Status: ' . $run->status . '</li>';
    echo '<li>Company ID: ' . $run->companyid . '</li>';
    echo '<li>Target Company ID: ' . ($run->targetcompanyid ?: 'None') . '</li>';
    echo '<li>Created: ' . userdate($run->timecreated) . '</li>';
    echo '<li>Completed: ' . ($run->timecompleted ? userdate($run->timecompleted) : 'Not completed') . '</li>';
    echo '</ul>';
    
    // Show NB results count
    $nb_count = $DB->count_records('local_ci_nb_result', ['runid' => $runid, 'status' => 'completed']);
    echo '<p>Completed NB Results: ' . $nb_count . '</p>';
    
    // Show specific NB codes present
    $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC', 'nbcode, status');
    echo '<h4>NB Results Status:</h4>';
    echo '<ul>';
    foreach ($nb_results as $nb) {
        echo '<li>' . $nb->nbcode . ': ' . $nb->status . '</li>';
    }
    echo '</ul>';
    
} else {
    echo '<div class="alert alert-warning">Run ID ' . $runid . ' not found.</div>';
}

// Show companies
if ($run) {
    $source_company = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
    echo '<h4>Source Company:</h4>';
    if ($source_company) {
        echo '<ul>';
        echo '<li>Name: ' . htmlspecialchars($source_company->name) . '</li>';
        echo '<li>Sector: ' . htmlspecialchars($source_company->sector ?: 'Not specified') . '</li>';
        echo '</ul>';
    }
    
    if ($run->targetcompanyid) {
        $target_company = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
        echo '<h4>Target Company:</h4>';
        if ($target_company) {
            echo '<ul>';
            echo '<li>Name: ' . htmlspecialchars($target_company->name) . '</li>';
            echo '<li>Sector: ' . htmlspecialchars($target_company->sector ?: 'Not specified') . '</li>';
            echo '</ul>';
        }
    }
}

echo '<hr>';
echo '<p><a href="/local/customerintel/view_report.php?runid=' . $runid . '">View Report (Normal View)</a></p>';

echo $OUTPUT->footer();