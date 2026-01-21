<?php
require_once('../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/customerintel/run.php');
$PAGE->set_title('Run Intelligence');
$PAGE->set_heading('Run Intelligence');

require_capability('local/customerintel:run', $context);

global $DB, $USER;

// Load company and target lists.
$companies = $DB->get_records_menu('local_ci_company', ['type' => 'customer'], '', 'id, name');
$targets = $DB->get_records_menu('local_ci_company', ['type' => 'target'], '', 'id, name');

require_once($CFG->dirroot . '/local/customerintel/classes/form/run_form.php');
$form = new \local_customerintel\form\run_form(null, ['companies' => $companies, 'targets' => $targets]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/customerintel/dashboard.php'));
} else if ($data = $form->get_data()) {
    // Milestone 0: Redirect to cache decision page instead of creating run immediately
    // The cache_decision.php will handle run creation after user makes cache choice
    redirect(new moodle_url('/local/customerintel/cache_decision.php', [
        'companyid' => $data->companyid,
        'targetcompanyid' => $data->targetcompanyid ?? 0
    ]));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();