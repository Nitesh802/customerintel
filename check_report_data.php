<?php
/**
 * Debug script to check what data is available for report display
 * Run via browser: /local/customerintel/check_report_data.php
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customerintel/check_report_data.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Check Report Data');

echo $OUTPUT->header();
echo $OUTPUT->heading('Report Data Availability Check');

// Test assembler directly on Run 104
echo html_writer::tag('h3', 'Testing Assembler on Run 104');

try {
    require_once($CFG->dirroot . '/local/customerintel/classes/services/assembler.php');
    $assembler = new \local_customerintel\services\assembler();

    echo html_writer::tag('p', 'Attempting to assemble report for Run 104...');

    $reportdata = $assembler->assemble_report(104);

    echo html_writer::tag('p', '✅ Assembler returned data successfully!');
    echo html_writer::tag('p', 'Keys in report data: ' . implode(', ', array_keys($reportdata)));

    // Check phases
    if (isset($reportdata['phases'])) {
        echo html_writer::tag('p', 'Phases found: ' . count($reportdata['phases']));
        echo html_writer::tag('pre', print_r(array_keys($reportdata['phases']), true));
    } else {
        echo html_writer::tag('p', '❌ No phases found in report data!');
    }

    // Check if there's any HTML content
    if (isset($reportdata['phases'])) {
        $has_content = false;
        foreach ($reportdata['phases'] as $phase_name => $phase_data) {
            if (isset($phase_data['html']) && !empty(trim($phase_data['html']))) {
                $has_content = true;
                echo html_writer::tag('p', "✅ Phase '{$phase_name}' has HTML content (" . strlen($phase_data['html']) . " bytes)");
            } else {
                echo html_writer::tag('p', "⚠️ Phase '{$phase_name}' has NO HTML content");
            }
        }

        if (!$has_content) {
            echo html_writer::tag('p', '❌ PROBLEM: No phases have HTML content!', ['style' => 'color: red; font-weight: bold;']);
        }
    }

    // Show first NB's processed data
    if (isset($reportdata['nb_results']) && !empty($reportdata['nb_results'])) {
        $first_nb = reset($reportdata['nb_results']);
        echo html_writer::tag('h4', 'First NB Data Sample:');
        echo html_writer::tag('p', 'NBCode: ' . ($first_nb->nbcode ?? 'unknown'));
        echo html_writer::tag('p', 'Has decoded data: ' . (isset($first_nb->data) ? 'YES ✅' : 'NO ❌'));

        if (isset($first_nb->data)) {
            echo html_writer::tag('p', 'Data keys: ' . implode(', ', array_keys($first_nb->data)));
            echo html_writer::tag('pre', substr(print_r($first_nb->data, true), 0, 500));
        }
    }

} catch (Exception $e) {
    echo $OUTPUT->notification('Error testing assembler: ' . $e->getMessage(), 'notifyproblem');
    echo html_writer::tag('pre', $e->getTraceAsString());
}

echo html_writer::empty_tag('hr');

// Compare with Run 103 (original source)
echo html_writer::tag('h3', 'Testing Assembler on Run 103 (Source)');

try {
    $reportdata_103 = $assembler->assemble_report(103);

    echo html_writer::tag('p', '✅ Assembler returned data for Run 103!');

    if (isset($reportdata_103['phases'])) {
        echo html_writer::tag('p', 'Phases found: ' . count($reportdata_103['phases']));

        foreach ($reportdata_103['phases'] as $phase_name => $phase_data) {
            if (isset($phase_data['html']) && !empty(trim($phase_data['html']))) {
                echo html_writer::tag('p', "✅ Phase '{$phase_name}' has HTML content (" . strlen($phase_data['html']) . " bytes)");
            } else {
                echo html_writer::tag('p', "⚠️ Phase '{$phase_name}' has NO HTML content");
            }
        }
    }

} catch (Exception $e) {
    echo $OUTPUT->notification('Error testing Run 103: ' . $e->getMessage(), 'notifyproblem');
}

echo html_writer::empty_tag('hr');
echo html_writer::tag('p', 'You can delete this file after reviewing: check_report_data.php');

echo $OUTPUT->footer();
