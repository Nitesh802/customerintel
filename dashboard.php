<?php
/**
 * Customer Intelligence Dashboard - Main Dashboard Page
 *
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:view', $context);

// Set up page
$PAGE->set_url('/local/customerintel/dashboard.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard', 'local_customerintel'));
$PAGE->set_heading(get_string('pluginname', 'local_customerintel'));
$PAGE->set_pagelayout('admin');

// Output header
echo $OUTPUT->header();

// Get queue statistics from run table - handle if table doesn't exist
try {
    $queue_stats = [
        'queued' => $DB->count_records('local_ci_run', ['status' => 'pending']),
        'running' => $DB->count_records('local_ci_run', ['status' => 'processing']),
        'completed' => $DB->count_records('local_ci_run', ['status' => 'completed']),
        'failed' => $DB->count_records('local_ci_run', ['status' => 'failed'])
    ];
} catch (Exception $e) {
    $queue_stats = [
        'queued' => 0,
        'running' => 0,
        'completed' => 0,
        'failed' => 0
    ];
}

// Get recent runs - simplified query
$recent_runs = [];
try {
    $sql = "SELECT r.* FROM {local_ci_run} r ORDER BY r.timecreated DESC";
    $recent_runs = $DB->get_records_sql($sql, [], 0, 5);
    
    // Enrich with company names
    foreach ($recent_runs as $run) {
        try {
            if ($company = $DB->get_record('local_ci_company', ['id' => $run->companyid])) {
                $run->customer_name = $company->name;
            } else {
                $run->customer_name = 'Unknown';
            }
            if ($run->targetcompanyid && $target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid])) {
                $run->target_name = $target->name;
            } else {
                $run->target_name = '-';
            }
        } catch (Exception $e) {
            $run->customer_name = 'Unknown';
            $run->target_name = '-';
        }
    }
} catch (Exception $e) {
    $recent_runs = [];
}

// Prepare template data
$templatedata = new stdClass();
$templatedata->runurl = new moodle_url('/local/customerintel/run.php');
$templatedata->reportsurl = new moodle_url('/local/customerintel/reports.php');
$templatedata->sourcesurl = new moodle_url('/local/customerintel/sources.php');
$templatedata->queued = $queue_stats['queued'];
$templatedata->running = $queue_stats['running'];
$templatedata->completed = $queue_stats['completed'];
$templatedata->failed = $queue_stats['failed'];

// Add logs URL if user has manage capability
if (has_capability('local/customerintel:manage', $context)) {
    $templatedata->logsurl = new moodle_url('/local/customerintel/logs.php');
    $templatedata->canviewlogs = true;
}

// Format runs for template
$templatedata->runs = [];
foreach ($recent_runs as $run) {
    $rundata = new stdClass();
    $rundata->id = $run->id;
    $rundata->customername = isset($run->customer_name) ? $run->customer_name : 'Unknown';
    $rundata->targetname = isset($run->target_name) ? $run->target_name : '-';
    $rundata->status = $run->status;
    $rundata->timestarted_h = userdate($run->timecreated, get_string('strftimedatetime'));
    $rundata->timecompleted_h = ($run->status == 'completed' && !empty($run->timecompleted))
        ? userdate($run->timecompleted, get_string('strftimedatetime'))
        : '-';

    // M2 Task 0.2: Add view report button for pending, processing, and completed runs
    $rundata->can_view_report = ($run->status === 'completed' || $run->status === 'processing' || $run->status === 'pending');
    $rundata->is_processing = ($run->status === 'processing' || $run->status === 'pending');
    $rundata->report_url = new moodle_url('/local/customerintel/view_report.php', ['runid' => $run->id]);

    $templatedata->runs[] = $rundata;
}

// Render using template
echo $OUTPUT->render_from_template('local_customerintel/dashboard', $templatedata);

// Output footer
echo $OUTPUT->footer();