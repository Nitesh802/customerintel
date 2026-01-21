<?php
/**
 * Customer Intelligence Dashboard - Report View
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_customerintel\services\assembler;
use local_customerintel\services\company_service;
use local_customerintel\services\versioning_service;

// Parameters
$runid = required_param('runid', PARAM_INT);
$comparisonid = optional_param('comparisonid', null, PARAM_INT);
$versionid = optional_param('versionid', null, PARAM_INT);
$showchanges = optional_param('showchanges', false, PARAM_BOOL);
$format = optional_param('format', 'html', PARAM_ALPHA);

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:viewreports', $context);

// Get run details
$run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
$company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);

// Check user can view this company's reports
if ($run->initiatedbyuserid != $USER->id && !has_capability('local/customerintel:viewallreports', $context)) {
    throw new moodle_exception('nopermission', 'local_customerintel');
}

// Set up page
$PAGE->set_context($context);
$PAGE->set_url('/local/customerintel/report.php', ['runid' => $runid]);
$PAGE->set_title(get_string('report', 'local_customerintel') . ': ' . $company->name);
$PAGE->set_heading($company->name . ' - ' . get_string('report', 'local_customerintel'));
$PAGE->set_pagelayout('admin');

// Add CSS for report styling
$PAGE->requires->css('/local/customerintel/styles.css');

// Add jQuery for interactions (Moodle includes it)
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');

// Handle export formats
if ($format !== 'html') {
    switch ($format) {
        case 'pdf':
            // TODO: Implement PDF export
            redirect(new moodle_url('/local/customerintel/export.php', [
                'runid' => $runid,
                'format' => 'pdf'
            ]));
            break;
            
        case 'markdown':
            // TODO: Implement Markdown export
            redirect(new moodle_url('/local/customerintel/export.php', [
                'runid' => $runid,
                'format' => 'markdown'
            ]));
            break;
            
        case 'notebooklm':
            // TODO: Implement NotebookLM export
            redirect(new moodle_url('/local/customerintel/export.php', [
                'runid' => $runid,
                'format' => 'notebooklm'
            ]));
            break;
    }
}

// Initialize services
$assembler = new assembler();
$versioningservice = new versioning_service();

// Get report data
$reportdata = $assembler->assemble_report($runid, $comparisonid);

// Get version history for version selector
$versionhistory = $versioningservice->get_history($company->id);
$reportdata['version_history'] = $versionhistory;

// If viewing a specific version, get that snapshot instead
if ($versionid) {
    $snapshot = $versioningservice->get_snapshot($versionid);
    // Override report data with snapshot data
    if ($snapshot && isset($snapshot->data['nb_results'])) {
        $reportdata['phases'] = $assembler->map_to_phases($snapshot->data['nb_results']);
        $reportdata['viewing_version'] = true;
        $reportdata['version_info'] = [
            'id' => $versionid,
            'created' => userdate($snapshot->timecreated, '%Y-%m-%d %H:%M'),
            'run_id' => $snapshot->data['run_id']
        ];
    }
}

// Handle diff/changes display if requested
if ($showchanges) {
    $currentsnapshotid = null;
    
    // Get current snapshot ID for this run
    $currentsnapshot = $DB->get_record('local_ci_snapshot', ['runid' => $runid]);
    if ($currentsnapshot) {
        $currentsnapshotid = $currentsnapshot->id;
    }
    
    // Get diff between versions
    if ($versionid && $currentsnapshotid) {
        $diff = $versioningservice->get_diff($currentsnapshotid, $versionid);
        if ($diff) {
            $reportdata['diff'] = $diff;
            $reportdata['showchanges'] = true;
            
            // Apply diff highlighting to report data
            $reportdata = $assembler->apply_diff_highlighting($reportdata, $diff);
        }
    } else if ($currentsnapshotid) {
        // Show changes from previous version
        $diff = $versioningservice->get_diff($currentsnapshotid);
        if ($diff) {
            $reportdata['diff'] = $diff;
            $reportdata['showchanges'] = true;
            
            // Apply diff highlighting to report data
            $reportdata = $assembler->apply_diff_highlighting($reportdata, $diff);
        }
    }
}

// Add page-specific data
$reportdata['runid'] = $runid;
$reportdata['comparisonid'] = $comparisonid;
$reportdata['versionid'] = $versionid;
$reportdata['showchanges'] = $showchanges;
$reportdata['currenturl'] = $PAGE->url->out(false);

// Add user info
$reportdata['user'] = [
    'id' => $USER->id,
    'fullname' => fullname($USER),
    'canexport' => has_capability('local/customerintel:exportreports', $context),
    'cancompare' => has_capability('local/customerintel:runanalysis', $context)
];

// Add navigation breadcrumbs
$PAGE->navbar->add(get_string('dashboard', 'local_customerintel'), 
    new moodle_url('/local/customerintel/dashboard.php'));
$PAGE->navbar->add($company->name, 
    new moodle_url('/local/customerintel/company.php', ['id' => $company->id]));
$PAGE->navbar->add(get_string('report', 'local_customerintel'));

// Output header
echo $OUTPUT->header();

// Render report using Mustache template
echo $OUTPUT->render_from_template('local_customerintel/report', $reportdata);

// Output footer
echo $OUTPUT->footer();