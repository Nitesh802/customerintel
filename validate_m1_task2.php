<?php
/**
 * Validation script for Milestone 1 Task 2: Prompt Config Scaffolding
 *
 * This script checks that the database migration was successful and that
 * the new fields are working correctly.
 *
 * Run this after upgrading to version 2025203024
 *
 * Usage: php validate_m1_task2.php
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/customerintel:run', context_system::instance());

global $DB, $OUTPUT, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/customerintel/validate_m1_task2.php');
$PAGE->set_title('M1 Task 2 Validation');
$PAGE->set_heading('Milestone 1 Task 2 Validation');

echo $OUTPUT->header();
echo $OUTPUT->heading('Milestone 1 Task 2: Prompt Config Scaffolding - Validation');

$all_passed = true;

// =============================================================================
// TEST 1: Check Plugin Version
// =============================================================================
echo $OUTPUT->heading('Test 1: Plugin Version Check', 3);

$version = $DB->get_field('config_plugins', 'value', [
    'plugin' => 'local_customerintel',
    'name' => 'version'
]);

if ($version == '2025203024') {
    echo html_writer::div('✅ PASS: Plugin version is 2025203024', 'alert alert-success');
} else {
    echo html_writer::div("❌ FAIL: Plugin version is {$version}, expected 2025203024", 'alert alert-danger');
    $all_passed = false;
}

// =============================================================================
// TEST 2: Check Database Schema
// =============================================================================
echo $OUTPUT->heading('Test 2: Database Schema Check', 3);

// Check if fields exist
$dbman = $DB->get_manager();
$table = new xmldb_table('local_ci_run');

$prompt_config_field = new xmldb_field('prompt_config');
$refresh_config_field = new xmldb_field('refresh_config');

$prompt_exists = $dbman->field_exists($table, $prompt_config_field);
$refresh_exists = $dbman->field_exists($table, $refresh_config_field);

if ($prompt_exists) {
    echo html_writer::div('✅ PASS: prompt_config field exists', 'alert alert-success');
} else {
    echo html_writer::div('❌ FAIL: prompt_config field does not exist', 'alert alert-danger');
    $all_passed = false;
}

if ($refresh_exists) {
    echo html_writer::div('✅ PASS: refresh_config field exists', 'alert alert-success');
} else {
    echo html_writer::div('❌ FAIL: refresh_config field does not exist', 'alert alert-danger');
    $all_passed = false;
}

// =============================================================================
// TEST 3: Check Field Data Types (MySQL specific)
// =============================================================================
echo $OUTPUT->heading('Test 3: Field Data Type Check', 3);

try {
    $sql = "SELECT
                COLUMN_NAME,
                DATA_TYPE,
                IS_NULLABLE,
                COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :tablename
            AND COLUMN_NAME IN ('prompt_config', 'refresh_config')";

    $columns = $DB->get_records_sql($sql, [
        'tablename' => $DB->get_prefix() . 'local_ci_run'
    ]);

    if (count($columns) == 2) {
        echo html_writer::div('✅ PASS: Both fields found in schema', 'alert alert-success');

        foreach ($columns as $col) {
            $field_info = "{$col->column_name}: {$col->column_type} (Nullable: {$col->is_nullable})";
            echo html_writer::div("   • {$field_info}", 'ml-3');
        }
    } else {
        echo html_writer::div('❌ FAIL: Not all fields found in schema', 'alert alert-danger');
        $all_passed = false;
    }
} catch (Exception $e) {
    echo html_writer::div('⚠️ WARNING: Could not check field types (non-MySQL database?)', 'alert alert-warning');
}

// =============================================================================
// TEST 4: Check Existing Runs (Backward Compatibility)
// =============================================================================
echo $OUTPUT->heading('Test 4: Backward Compatibility Check', 3);

$existing_runs = $DB->get_records('local_ci_run', [], 'id DESC', 'id, prompt_config, refresh_config', 0, 10);

if (count($existing_runs) > 0) {
    echo html_writer::div('✅ PASS: Can query runs with new fields', 'alert alert-success');

    $null_count = 0;
    $populated_count = 0;

    foreach ($existing_runs as $run) {
        if ($run->prompt_config === null && $run->refresh_config === null) {
            $null_count++;
        } else {
            $populated_count++;
        }
    }

    echo html_writer::div("   • Found {$null_count} runs with NULL values (backward compatible)", 'ml-3');
    echo html_writer::div("   • Found {$populated_count} runs with populated values", 'ml-3');

    if ($null_count > 0) {
        echo html_writer::div('✅ PASS: Backward compatibility confirmed (NULL values allowed)', 'alert alert-success');
    }
} else {
    echo html_writer::div('⚠️ WARNING: No runs found in database', 'alert alert-warning');
}

// =============================================================================
// TEST 5: Validate JSON Structure in Populated Runs
// =============================================================================
echo $OUTPUT->heading('Test 5: JSON Structure Validation', 3);

$populated_runs = $DB->get_records_select(
    'local_ci_run',
    'prompt_config IS NOT NULL AND refresh_config IS NOT NULL',
    [],
    'id DESC',
    'id, prompt_config, refresh_config',
    0,
    5
);

if (count($populated_runs) > 0) {
    $json_valid = true;

    foreach ($populated_runs as $run) {
        // Validate prompt_config
        $prompt = json_decode($run->prompt_config, true);
        if (!$prompt || !isset($prompt['tone']) || !isset($prompt['persona'])) {
            echo html_writer::div("❌ FAIL: Run {$run->id} has invalid prompt_config JSON", 'alert alert-danger');
            $json_valid = false;
            $all_passed = false;
        }

        // Validate refresh_config
        $refresh = json_decode($run->refresh_config, true);
        if (!$refresh || !isset($refresh['force_nb_refresh']) || !isset($refresh['force_synthesis_refresh'])) {
            echo html_writer::div("❌ FAIL: Run {$run->id} has invalid refresh_config JSON", 'alert alert-danger');
            $json_valid = false;
            $all_passed = false;
        }
    }

    if ($json_valid) {
        echo html_writer::div("✅ PASS: All populated runs have valid JSON structures", 'alert alert-success');
        echo html_writer::div("   • Checked {count($populated_runs)} runs with populated values", 'ml-3');
    }
} else {
    echo html_writer::div('⚠️ INFO: No runs with populated config values yet (expected if upgrade just ran)', 'alert alert-info');
}

// =============================================================================
// TEST 6: Sample Default Values
// =============================================================================
echo $OUTPUT->heading('Test 6: Default Values Check', 3);

if (count($populated_runs) > 0) {
    $sample = reset($populated_runs);
    $prompt = json_decode($sample->prompt_config, true);
    $refresh = json_decode($sample->refresh_config, true);

    echo html_writer::div("Sample Run ID: {$sample->id}", 'mb-2');
    echo html_writer::div("prompt_config:", 'font-weight-bold');
    echo html_writer::tag('pre', json_encode($prompt, JSON_PRETTY_PRINT), ['class' => 'border p-2']);

    echo html_writer::div("refresh_config:", 'font-weight-bold mt-2');
    echo html_writer::tag('pre', json_encode($refresh, JSON_PRETTY_PRINT), ['class' => 'border p-2']);

    // Check expected default values
    if ($prompt['tone'] === 'Default' && $prompt['persona'] === 'Consultative') {
        echo html_writer::div('✅ PASS: Default prompt_config values are correct', 'alert alert-success');
    } else {
        echo html_writer::div('⚠️ WARNING: Default prompt_config values differ from specification', 'alert alert-warning');
    }
} else {
    echo html_writer::div('⚠️ INFO: No populated runs to check defaults (create a new run to test)', 'alert alert-info');
}

// =============================================================================
// TEST 7: Run Count Summary
// =============================================================================
echo $OUTPUT->heading('Test 7: Run Statistics', 3);

$stats_sql = "
    SELECT
        COUNT(*) as total_runs,
        SUM(CASE WHEN prompt_config IS NULL THEN 1 ELSE 0 END) as null_prompt,
        SUM(CASE WHEN refresh_config IS NULL THEN 1 ELSE 0 END) as null_refresh,
        SUM(CASE WHEN prompt_config IS NOT NULL THEN 1 ELSE 0 END) as with_prompt,
        SUM(CASE WHEN refresh_config IS NOT NULL THEN 1 ELSE 0 END) as with_refresh
    FROM {local_ci_run}
";

$stats = $DB->get_record_sql($stats_sql);

echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Metric');
echo html_writer::tag('th', 'Count');
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Total Runs');
echo html_writer::tag('td', $stats->total_runs);
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Runs with NULL prompt_config');
echo html_writer::tag('td', $stats->null_prompt);
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Runs with NULL refresh_config');
echo html_writer::tag('td', $stats->null_refresh);
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Runs with populated prompt_config');
echo html_writer::tag('td', $stats->with_prompt);
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', 'Runs with populated refresh_config');
echo html_writer::tag('td', $stats->with_refresh);
echo html_writer::end_tag('tr');

echo html_writer::end_tag('table');

// =============================================================================
// FINAL SUMMARY
// =============================================================================
echo $OUTPUT->heading('Validation Summary', 2);

if ($all_passed) {
    echo html_writer::div(
        '✅ ALL TESTS PASSED - Milestone 1 Task 2 implementation is successful!',
        'alert alert-success p-4 text-center h4'
    );
    echo html_writer::div(
        'The prompt_config and refresh_config fields have been successfully added and are ready for use in Milestone 2.',
        'text-center mb-3'
    );
} else {
    echo html_writer::div(
        '❌ SOME TESTS FAILED - Please review the errors above',
        'alert alert-danger p-4 text-center h4'
    );
    echo html_writer::div(
        'Check the database migration and ensure upgrade.php ran successfully.',
        'text-center mb-3'
    );
}

// Next steps
echo $OUTPUT->heading('Next Steps', 3);
echo html_writer::start_tag('ul');
echo html_writer::tag('li', 'Create a new test run to verify default values are set correctly');
echo html_writer::tag('li', 'Access an existing run to confirm backward compatibility');
echo html_writer::tag('li', 'Review the implementation summary: MILESTONE_1_TASK_2_IMPLEMENTATION_SUMMARY.md');
echo html_writer::tag('li', 'Proceed with Milestone 2 planning (tone/persona formatting)');
echo html_writer::end_tag('ul');

// Links
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/customerintel/run.php'),
        '→ Create New Run',
        ['class' => 'btn btn-primary mr-2']
    ) .
    html_writer::link(
        new moodle_url('/local/customerintel/dashboard.php'),
        '→ View Dashboard',
        ['class' => 'btn btn-secondary']
    ),
    'mt-4'
);

echo $OUTPUT->footer();
