<?php
/**
 * Debug script to investigate empty cached reports
 * Run via browser: /local/customerintel/debug_cache_issue.php
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customerintel/debug_cache_issue.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Debug Cache Issue');

echo $OUTPUT->header();
echo $OUTPUT->heading('Cache Issue Diagnostic Report');

// Check Run 103 (source)
echo html_writer::tag('h3', 'Run 103 (Source - Should Have 15 NBs)');
$run103 = $DB->get_record('local_ci_run', ['id' => 103]);
if ($run103) {
    echo html_writer::tag('pre', print_r($run103, true));

    $nb_count_103 = $DB->count_records('local_ci_nb_result', ['runid' => 103]);
    echo html_writer::tag('p', "NB Count for Run 103: <strong>{$nb_count_103}</strong>");

    if ($nb_count_103 > 0) {
        $nbs_103 = $DB->get_records('local_ci_nb_result', ['runid' => 103], 'nbcode ASC', 'id, nbcode, status');
        echo html_writer::tag('p', 'NBs in Run 103:');
        echo html_writer::tag('pre', print_r($nbs_103, true));
    }
} else {
    echo $OUTPUT->notification('Run 103 not found!', 'notifyproblem');
}

echo html_writer::empty_tag('hr');

// Check Run 104 (first cached attempt)
echo html_writer::tag('h3', 'Run 104 (First Cached Attempt - Empty)');
$run104 = $DB->get_record('local_ci_run', ['id' => 104]);
if ($run104) {
    echo html_writer::tag('pre', print_r($run104, true));

    $nb_count_104 = $DB->count_records('local_ci_nb_result', ['runid' => 104]);
    echo html_writer::tag('p', "NB Count for Run 104: <strong>{$nb_count_104}</strong>");

    if ($nb_count_104 > 0) {
        $nbs_104 = $DB->get_records('local_ci_nb_result', ['runid' => 104], 'nbcode ASC', 'id, nbcode, status');
        echo html_writer::tag('p', 'NBs in Run 104:');
        echo html_writer::tag('pre', print_r($nbs_104, true));
    }

    // Check diagnostics
    $diag_104 = $DB->get_records('local_ci_diagnostics', ['runid' => 104], 'timecreated DESC');
    if ($diag_104) {
        echo html_writer::tag('p', 'Diagnostics for Run 104:');
        echo html_writer::tag('pre', print_r($diag_104, true));
    } else {
        echo $OUTPUT->notification('No diagnostics found for Run 104', 'notifywarning');
    }
} else {
    echo $OUTPUT->notification('Run 104 not found!', 'notifyproblem');
}

echo html_writer::empty_tag('hr');

// Check Run 105 (second cached attempt)
echo html_writer::tag('h3', 'Run 105 (Second Cached Attempt - Empty)');
$run105 = $DB->get_record('local_ci_run', ['id' => 105]);
if ($run105) {
    echo html_writer::tag('pre', print_r($run105, true));

    $nb_count_105 = $DB->count_records('local_ci_nb_result', ['runid' => 105]);
    echo html_writer::tag('p', "NB Count for Run 105: <strong>{$nb_count_105}</strong>");

    if ($nb_count_105 > 0) {
        $nbs_105 = $DB->get_records('local_ci_nb_result', ['runid' => 105], 'nbcode ASC', 'id, nbcode, status');
        echo html_writer::tag('p', 'NBs in Run 105:');
        echo html_writer::tag('pre', print_r($nbs_105, true));
    }

    // Check diagnostics
    $diag_105 = $DB->get_records('local_ci_diagnostics', ['runid' => 105], 'timecreated DESC');
    if ($diag_105) {
        echo html_writer::tag('p', 'Diagnostics for Run 105:');
        echo html_writer::tag('pre', print_r($diag_105, true));
    } else {
        echo $OUTPUT->notification('No diagnostics found for Run 105', 'notifywarning');
    }
} else {
    echo $OUTPUT->notification('Run 105 not found!', 'notifyproblem');
}

echo html_writer::empty_tag('hr');

// Test cache_manager directly
echo html_writer::tag('h3', 'Direct Cache Manager Test');
try {
    require_once($CFG->dirroot . '/local/customerintel/classes/services/cache_manager.php');
    $cache_manager = new \local_customerintel\services\cache_manager();

    // Get company IDs from Run 103
    if ($run103) {
        echo html_writer::tag('p', "Testing cache check for companyid={$run103->companyid}, targetcompanyid={$run103->targetcompanyid}");

        $cache_info = $cache_manager->check_nb_cache($run103->companyid, $run103->targetcompanyid);
        echo html_writer::tag('p', 'Cache Check Result:');
        echo html_writer::tag('pre', print_r($cache_info, true));
    }
} catch (Exception $e) {
    echo $OUTPUT->notification('Error testing cache manager: ' . $e->getMessage(), 'notifyproblem');
    echo html_writer::tag('pre', $e->getTraceAsString());
}

echo html_writer::empty_tag('hr');
echo html_writer::tag('p', 'You can delete this file after reviewing: debug_cache_issue.php');

echo $OUTPUT->footer();
