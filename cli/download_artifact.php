<?php
/**
 * Customer Intelligence Dashboard - Download Artifact JSON
 *
 * Downloads a specific artifact as a JSON file
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();

$context = context_system::instance();
require_capability('local/customerintel:view', $context);

// Required parameter
$artifact_id = required_param('id', PARAM_INT);

// Get the artifact
$artifact = $DB->get_record('local_ci_artifact', ['id' => $artifact_id], '*', MUST_EXIST);

// Get the run to check permissions
$run = $DB->get_record('local_ci_run', ['id' => $artifact->runid], '*', MUST_EXIST);

// Check user permissions
$can_manage = has_capability('local/customerintel:manage', $context);
if ($run->initiatedbyuserid != $USER->id && !$can_manage) {
    throw new moodle_exception('nopermission', 'local_customerintel');
}

// Check if trace mode is enabled
$trace_mode_enabled = get_config('local_customerintel', 'enable_trace_mode');
if ($trace_mode_enabled !== '1') {
    throw new moodle_exception('tracemodenotenabled', 'local_customerintel');
}

// Create filename
$filename = sprintf('artifact_%d_%s_%s_%s.json', 
    $artifact->runid,
    $artifact->phase,
    $artifact->artifacttype,
    date('Y-m-d_H-i-s', $artifact->timecreated)
);

// Set headers for download
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($artifact->jsondata));

// Output the JSON data
echo $artifact->jsondata;
exit;
?>