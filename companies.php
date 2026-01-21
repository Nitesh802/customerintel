<?php
/**
 * Company Management Page
 *
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT, $USER;

$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/customerintel/companies.php'));
$PAGE->set_title(get_string('managecompanies', 'local_customerintel'));
$PAGE->set_heading(get_string('managecompanies', 'local_customerintel'));
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$message = '';
$messagetype = '';

// Handle form actions
if ($action === 'add' && confirm_sesskey()) {
    $record = new stdClass();
    $record->name = required_param('name', PARAM_TEXT);
    $record->ticker = optional_param('ticker', '', PARAM_TEXT);
    $record->type = required_param('type', PARAM_TEXT);
    $record->website = optional_param('website', '', PARAM_RAW_TRIMMED);
    $record->sector = optional_param('sector', '', PARAM_TEXT);
    $record->metadata = '{}';
    $record->timecreated = time();
    $record->timemodified = time();
    
    try {
        $DB->insert_record('local_ci_company', $record);
        $message = get_string('companyadded', 'local_customerintel');
        $messagetype = 'success';
    } catch (Exception $e) {
        $message = 'Error adding company: ' . $e->getMessage();
        $messagetype = 'error';
    }
}

if ($action === 'edit' && confirm_sesskey()) {
    $record = $DB->get_record('local_ci_company', ['id' => $id], '*', MUST_EXIST);
    $record->name = required_param('name', PARAM_TEXT);
    $record->ticker = optional_param('ticker', '', PARAM_TEXT);
    $record->type = required_param('type', PARAM_TEXT);
    $record->website = optional_param('website', '', PARAM_RAW_TRIMMED);
    $record->sector = optional_param('sector', '', PARAM_TEXT);
    $record->timemodified = time();
    
    try {
        $DB->update_record('local_ci_company', $record);
        $message = get_string('companyupdated', 'local_customerintel');
        $messagetype = 'success';
    } catch (Exception $e) {
        $message = 'Error updating company: ' . $e->getMessage();
        $messagetype = 'error';
    }
}

if ($action === 'delete' && confirm_sesskey()) {
    try {
        // Check if company is being used
        $runs_count = $DB->count_records('local_ci_run', ['companyid' => $id]);
        $sources_count = $DB->count_records('local_ci_source', ['companyid' => $id]);
        
        if ($runs_count > 0 || $sources_count > 0) {
            $message = 'Cannot delete company - it has ' . $runs_count . ' runs and ' . $sources_count . ' sources';
            $messagetype = 'warning';
        } else {
            $DB->delete_records('local_ci_company', ['id' => $id]);
            $message = get_string('companydeleted', 'local_customerintel');
            $messagetype = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error deleting company: ' . $e->getMessage();
        $messagetype = 'error';
    }
}

// Get all companies
$companies = $DB->get_records('local_ci_company', null, 'name ASC');

// Prepare data for template
$data = [
    'dashboardurl' => new moodle_url('/local/customerintel/dashboard.php'),
    'companies' => [],
    'message' => $message,
    'messagetype' => $messagetype,
    'sesskey' => sesskey(),
    'hascompanies' => !empty($companies)
];

// Format companies for display
foreach ($companies as $company) {
    // Count related records
    $runs_count = $DB->count_records('local_ci_run', ['companyid' => $company->id]);
    $sources_count = $DB->count_records('local_ci_source', ['companyid' => $company->id]);
    
    $data['companies'][] = [
        'id' => $company->id,
        'name' => $company->name,
        'ticker' => $company->ticker ?: '-',
        'type' => ucfirst($company->type),
        'type_badge' => $company->type === 'customer' ? 'badge-primary' : 'badge-info',
        'website' => $company->website ?: '-',
        'sector' => $company->sector ?: '-',
        'runs_count' => $runs_count,
        'sources_count' => $sources_count,
        'can_delete' => ($runs_count == 0 && $sources_count == 0),
        'timecreated_h' => userdate($company->timecreated, get_string('strftimedatetime'))
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_customerintel/companies', $data);
echo $OUTPUT->footer();