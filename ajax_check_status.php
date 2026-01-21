<?php
/**
 * AJAX endpoint to check run status for auto-refresh
 * M2 Task 0.2: Allow viewing reports during 'processing' status
 *
 * @package    local_customerintel
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$runid = required_param('runid', PARAM_INT);

// Get run status
$run = $DB->get_record('local_ci_run', ['id' => $runid], 'status, timecompleted');

if (!$run) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Run not found']);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'status' => $run->status,
    'completed' => ($run->status === 'completed'),
    'timestamp' => $run->timecompleted ?? 0
]);
