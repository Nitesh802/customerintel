<?php
/**
 * Sources Management Page
 *
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT, $USER;

$context = context_system::instance();
require_capability('local/customerintel:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/customerintel/sources.php'));
$PAGE->set_title(get_string('managesources', 'local_customerintel'));
$PAGE->set_heading(get_string('managesources', 'local_customerintel'));
$PAGE->set_pagelayout('admin');

$companyid = optional_param('companyid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Fetch companies for dropdowns
$companies = $DB->get_records_menu('local_ci_company', null, 'name ASC', 'id, name');

// Prepare message feedback
$message = '';
$messagetype = '';

if ($action === 'add' && confirm_sesskey()) {
    $companyid = required_param('companyid', PARAM_INT);
    $sourcetype = required_param('sourcetype', PARAM_TEXT);
    $sourceurl = optional_param('sourceurl', '', PARAM_RAW_TRIMMED);
    $description = optional_param('description', '', PARAM_RAW_TRIMMED);
    
    if ($companyid > 0 && $sourcetype) {
        $record = new stdClass();
        $record->companyid = $companyid;
        $record->type = $sourcetype;
        $record->title = $description ?: 'Untitled Source';
        $record->url = $sourceurl ?: '';
        $record->status = 'pending';
        $record->metadata = '{}';
        $record->timecreated = time();
        $record->timemodified = time();
        
        try {
            $DB->insert_record('local_ci_source', $record);
            $message = get_string('sourceadded', 'local_customerintel');
            $messagetype = 'success';
            // Clear form by resetting companyid
            $companyid = 0;
        } catch (dml_exception $e) {
            $message = 'DB Error: ' . $e->getMessage();
            $messagetype = 'error';
        }
    } else {
        $message = 'Missing company or source type.';
        $messagetype = 'error';
    }
}

// Get sources for display (show all or filtered by company)
$sources = [];
if ($companyid > 0) {
    $sources = $DB->get_records('local_ci_source', ['companyid' => $companyid], 'timecreated DESC');
} else {
    // Show recent sources from all companies
    $sources = $DB->get_records('local_ci_source', null, 'timecreated DESC', '*', 0, 20);
}

// Prepare dropdown for source types
$sourcetypes = [
    'url' => get_string('sourcetype_url', 'local_customerintel'),
    'file' => get_string('sourcetype_file', 'local_customerintel'),
    'text' => get_string('sourcetype_text', 'local_customerintel')
];

// Build template context
$data = [
    'dashboardurl' => new moodle_url('/local/customerintel/dashboard.php'),
    'sesskey' => sesskey(),
    'companies' => [],
    'sourcetypes' => [],
    'selectedcompany' => $companyid,
    'sources' => [],
    'message' => $message,
    'messagetype' => $messagetype,
    'hassources' => !empty($sources)
];

// Format companies for template
foreach ($companies as $id => $name) {
    $data['companies'][] = [
        'id' => $id,
        'name' => $name,
        'selected' => ($id == $companyid)
    ];
}

// Format source types for template
foreach ($sourcetypes as $value => $label) {
    $data['sourcetypes'][] = [
        'value' => $value,
        'label' => $label
    ];
}

// Format sources for template
foreach ($sources as $source) {
    // Get company name for display
    $companyname = '';
    if (isset($companies[$source->companyid])) {
        $companyname = $companies[$source->companyid];
    }
    
    $data['sources'][] = [
        'id' => $source->id,
        'title' => !empty($source->title) ? $source->title : '(No description)',
        'url' => $source->url,
        'type' => $source->type,
        'companyname' => $companyname,
        'approved_h' => ($source->status === 'approved') ? '✅' : '❌',
        'timecreated_h' => userdate($source->timecreated, get_string('strftimedatetime'))
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_customerintel/sources', $data);
echo $OUTPUT->footer();