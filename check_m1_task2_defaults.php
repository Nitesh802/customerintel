<?php
/**
 * Quick check for M1 Task 2 default values
 *
 * This script shows the most recent runs and their prompt_config/refresh_config values
 *
 * Usage: Navigate to /local/customerintel/check_m1_task2_defaults.php in browser
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/customerintel:run', context_system::instance());

global $DB, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/customerintel/check_m1_task2_defaults.php');
$PAGE->set_title('M1 Task 2 - Default Values Check');
$PAGE->set_heading('M1 Task 2 - Default Values Check');

echo $OUTPUT->header();
echo $OUTPUT->heading('Milestone 1 Task 2: Check Default Values');

// Get the 10 most recent runs
$runs = $DB->get_records('local_ci_run', [], 'id DESC', 'id, companyid, targetcompanyid, status, prompt_config, refresh_config, timecreated', 0, 10);

if (empty($runs)) {
    echo html_writer::div('No runs found in database.', 'alert alert-warning');
} else {
    echo html_writer::div('Showing the 10 most recent runs:', 'mb-3');

    foreach ($runs as $run) {
        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-header');
        echo html_writer::tag('h5', "Run ID: {$run->id}", ['class' => 'mb-0']);
        echo html_writer::end_div();

        echo html_writer::start_div('card-body');

        // Basic info
        echo html_writer::tag('p', "<strong>Company ID:</strong> {$run->companyid}");
        echo html_writer::tag('p', "<strong>Target ID:</strong> " . ($run->targetcompanyid ?? 'NULL'));
        echo html_writer::tag('p', "<strong>Status:</strong> {$run->status}");
        echo html_writer::tag('p', "<strong>Created:</strong> " . userdate($run->timecreated));

        // prompt_config
        echo html_writer::start_div('mt-3');
        echo html_writer::tag('h6', 'prompt_config:');

        if ($run->prompt_config === null) {
            echo html_writer::tag('div', '❌ NULL (expected for runs created before upgrade)', ['class' => 'alert alert-secondary']);
        } else {
            $prompt_data = json_decode($run->prompt_config, true);
            if ($prompt_data) {
                echo html_writer::tag('pre', json_encode($prompt_data, JSON_PRETTY_PRINT), ['class' => 'border p-2 bg-light']);

                // Validate structure
                if (isset($prompt_data['tone']) && isset($prompt_data['persona'])) {
                    echo html_writer::tag('div', '✅ Valid structure', ['class' => 'alert alert-success mt-2']);

                    if ($prompt_data['tone'] === 'Default' && $prompt_data['persona'] === 'Consultative') {
                        echo html_writer::tag('div', '✅ Has expected default values', ['class' => 'alert alert-success']);
                    } else {
                        echo html_writer::tag('div', '⚠️ Different values than default', ['class' => 'alert alert-warning']);
                    }
                } else {
                    echo html_writer::tag('div', '❌ Invalid structure (missing tone or persona)', ['class' => 'alert alert-danger mt-2']);
                }
            } else {
                echo html_writer::tag('div', '❌ Invalid JSON', ['class' => 'alert alert-danger']);
            }
        }
        echo html_writer::end_div();

        // refresh_config
        echo html_writer::start_div('mt-3');
        echo html_writer::tag('h6', 'refresh_config:');

        if ($run->refresh_config === null) {
            echo html_writer::tag('div', '❌ NULL (expected for runs created before upgrade)', ['class' => 'alert alert-secondary']);
        } else {
            $refresh_data = json_decode($run->refresh_config, true);
            if ($refresh_data) {
                echo html_writer::tag('pre', json_encode($refresh_data, JSON_PRETTY_PRINT), ['class' => 'border p-2 bg-light']);

                // Validate structure
                $required_keys = ['force_nb_refresh', 'force_synthesis_refresh', 'refresh_source', 'refresh_target'];
                $has_all_keys = true;
                foreach ($required_keys as $key) {
                    if (!isset($refresh_data[$key])) {
                        $has_all_keys = false;
                        break;
                    }
                }

                if ($has_all_keys) {
                    echo html_writer::tag('div', '✅ Valid structure', ['class' => 'alert alert-success mt-2']);
                } else {
                    echo html_writer::tag('div', '❌ Invalid structure (missing required keys)', ['class' => 'alert alert-danger mt-2']);
                }
            } else {
                echo html_writer::tag('div', '❌ Invalid JSON', ['class' => 'alert alert-danger']);
            }
        }
        echo html_writer::end_div();

        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
    }
}

// Summary statistics
echo html_writer::start_div('alert alert-info mt-4');
echo html_writer::tag('h5', 'Summary:');

$total = $DB->count_records('local_ci_run');
$with_prompt = $DB->count_records_select('local_ci_run', 'prompt_config IS NOT NULL');
$with_refresh = $DB->count_records_select('local_ci_run', 'refresh_config IS NOT NULL');

echo html_writer::tag('p', "Total runs: {$total}");
echo html_writer::tag('p', "Runs with prompt_config: {$with_prompt}");
echo html_writer::tag('p', "Runs with refresh_config: {$with_refresh}");

if ($with_prompt === 0 && $with_refresh === 0) {
    echo html_writer::div(
        '⚠️ No runs with default values yet. Create a new run to test the default value population.',
        'alert alert-warning mt-3'
    );
} else {
    echo html_writer::div(
        "✅ Found {$with_prompt} runs with populated config values!",
        'alert alert-success mt-3'
    );
}
echo html_writer::end_div();

// Action buttons
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/customerintel/run.php'),
        '→ Create New Test Run',
        ['class' => 'btn btn-primary mr-2']
    ) .
    html_writer::link(
        new moodle_url('/local/customerintel/validate_m1_task2.php'),
        '→ Full Validation',
        ['class' => 'btn btn-secondary mr-2']
    ) .
    html_writer::link(
        new moodle_url('/local/customerintel/dashboard.php'),
        '→ Dashboard',
        ['class' => 'btn btn-secondary']
    ),
    'mt-4'
);

echo $OUTPUT->footer();
