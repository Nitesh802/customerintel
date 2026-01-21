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
    // Create a new run record.
    $now = time();
    $run = new stdClass();
    $run->companyid = $data->companyid;
    $run->targetcompanyid = $data->targetcompanyid;
    $run->status = 'pending';
    $run->userid = $USER->id;
    $run->initiatedbyuserid = $USER->id;
    $run->timecreated = $now;
    $run->timemodified = $now;
    $runid = $DB->insert_record('local_ci_run', $run);

    // Log the run creation with key details
    require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');
    \local_customerintel\services\log_service::info($runid, "Run {$runid} created: companyid={$run->companyid}, targetcompanyid={$run->targetcompanyid}, userid={$USER->id}");

    // Queue the adhoc task.
    $task = new \local_customerintel\task\execute_run_task();
    $task->set_custom_data((object)['runid' => $runid]);
    $task->set_userid($USER->id);
    $task->set_component('local_customerintel');
    $task->set_fail_delay(60);
    \core\task\manager::queue_adhoc_task($task);

    \core\notification::success(get_string('runqueued', 'local_customerintel'));
    redirect(new \moodle_url('/local/customerintel/dashboard.php'));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();