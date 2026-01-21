<?php
/**
 * Customer Intelligence Dashboard - Reports View
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

// Parameters - runid is now optional
$runid = optional_param('runid', 0, PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url('/local/customerintel/reports.php', ['runid' => $runid]);
$PAGE->set_pagelayout('admin');

// Add CSS for report styling
$PAGE->requires->css('/local/customerintel/styles/customerintel.css');

// Output header
echo $OUTPUT->header();

// If runid is provided, display the report
if ($runid > 0) {
    // Get run details
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
    $company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
    $target = null;
    if ($run->targetcompanyid) {
        $target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid], '*');
    }
    
    // Check user can view this company's reports
    if ($run->initiatedbyuserid != $USER->id && !has_capability('local/customerintel:manage', $context)) {
        throw new moodle_exception('nopermission', 'local_customerintel');
    }
    
    $PAGE->set_title(get_string('report', 'local_customerintel') . ': ' . $company->name);
    $PAGE->set_heading($company->name . ' - ' . get_string('report', 'local_customerintel'));
    
    // Display single report details
    echo '<p>Report details for run ID ' . $runid . '</p>';
} else {
    // Display list of all reports
    $PAGE->set_title(get_string('viewreports', 'local_customerintel'));
    $PAGE->set_heading(get_string('viewreports', 'local_customerintel'));
    
    // Get all runs
    $sql = "SELECT r.*, 
                   c.name as customer_name, 
                   t.name as target_name
            FROM {local_ci_run} r
            JOIN {local_ci_company} c ON r.companyid = c.id
            LEFT JOIN {local_ci_company} t ON r.targetcompanyid = t.id
            ORDER BY r.timecreated DESC";
    
    $runs = $DB->get_records_sql($sql);
    
    // Prepare template data
    $templatedata = new stdClass();
    $templatedata->runs = [];
    
    foreach ($runs as $run) {
        $rundata = new stdClass();
        $rundata->id = $run->id;
        $rundata->customername = $run->customer_name;
        $rundata->targetname = $run->target_name ?? '-';
        $rundata->status = ucfirst($run->status);
        $rundata->timecompleted_h = ($run->status == 'completed' && !empty($run->timemodified)) 
            ? userdate($run->timemodified, get_string('strftimedatetime'))
            : '-';
        
        // Status flags for template conditionals
        $rundata->status_completed = ($run->status === 'completed');
        $rundata->status_failed = ($run->status === 'failed');
        $rundata->status_running = ($run->status === 'running');
        $rundata->status_pending = ($run->status === 'pending');
        
        // Show View Report link only for completed runs
        $rundata->is_completed = ($run->status === 'completed');
        if ($rundata->is_completed) {
            $rundata->viewreport_url = new moodle_url('/local/customerintel/view_report.php', ['runid' => $run->id]);
        }
        
        $templatedata->runs[] = $rundata;
    }
    
    // Render template
    echo $OUTPUT->render_from_template('local_customerintel/reports', $templatedata);
}

echo $OUTPUT->footer();