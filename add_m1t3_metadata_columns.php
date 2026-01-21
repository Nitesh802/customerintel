<?php
/**
 * Add M1T3 metadata columns to local_ci_synthesis table
 *
 * Adds source_company_id and target_company_id columns
 * for Milestone 1 Task 3 enhancement.
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/add_m1t3_metadata_columns.php'));
$PAGE->set_title("Add M1T3 Metadata Columns");

echo $OUTPUT->header();

?>
<style>
.upgrade { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
.step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>

<div class="upgrade">

<h1>🔧 Add M1T3 Metadata Columns</h1>

<p>This script adds <code>source_company_id</code> and <code>target_company_id</code> columns to the <code>local_ci_synthesis</code> table.</p>

<?php

$dbman = $DB->get_manager();

// Define the table
$table = new xmldb_table('local_ci_synthesis');

// Check if columns already exist
$columns_exist = true;

try {
    $field = new xmldb_field('source_company_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'selfcheck_report');
    $source_exists = $dbman->field_exists($table, $field);

    $field = new xmldb_field('target_company_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'source_company_id');
    $target_exists = $dbman->field_exists($table, $field);

    $columns_exist = $source_exists && $target_exists;

} catch (Exception $e) {
    echo "<div class='step fail'>";
    echo "<p>Error checking if columns exist: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

if ($columns_exist) {
    echo "<div class='step warning'>";
    echo "<h2>✅ Columns Already Exist</h2>";
    echo "<p>Both <code>source_company_id</code> and <code>target_company_id</code> columns already exist in the table.</p>";
    echo "<p>No changes needed.</p>";
    echo "</div>";
} else {
    echo "<div class='step'>";
    echo "<h2>Adding Columns</h2>";

    try {
        // Add source_company_id column
        if (!$source_exists) {
            $field = new xmldb_field('source_company_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'selfcheck_report');
            $dbman->add_field($table, $field);
            echo "<p>✅ Added <code>source_company_id</code> column</p>";
        } else {
            echo "<p>⏭️ <code>source_company_id</code> already exists</p>";
        }

        // Add target_company_id column
        if (!$target_exists) {
            $field = new xmldb_field('target_company_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'source_company_id');
            $dbman->add_field($table, $field);
            echo "<p>✅ Added <code>target_company_id</code> column</p>";
        } else {
            echo "<p>⏭️ <code>target_company_id</code> already exists</p>";
        }

        echo "</div>";

        echo "<div class='step success'>";
        echo "<h2>✅ Columns Added Successfully</h2>";
        echo "<p>The M1T3 metadata columns have been added to the <code>local_ci_synthesis</code> table.</p>";
        echo "</div>";

        // Now backfill existing records
        echo "<div class='step'>";
        echo "<h2>Backfilling Existing Records</h2>";

        $syntheses = $DB->get_records_sql(
            "SELECT s.id, s.runid, r.companyid, r.targetcompanyid
             FROM {local_ci_synthesis} s
             JOIN {local_ci_run} r ON r.id = s.runid
             WHERE s.source_company_id IS NULL"
        );

        $updated = 0;
        foreach ($syntheses as $synth) {
            $DB->set_field('local_ci_synthesis', 'source_company_id', $synth->companyid, ['id' => $synth->id]);
            $DB->set_field('local_ci_synthesis', 'target_company_id', $synth->targetcompanyid, ['id' => $synth->id]);
            $updated++;
        }

        echo "<p>✅ Updated <strong>{$updated}</strong> existing synthesis records with metadata</p>";
        echo "</div>";

        echo "<div class='step success'>";
        echo "<h2>🎉 Complete!</h2>";
        echo "<p>M1T3 metadata columns have been added and all existing records have been updated.</p>";
        echo "<p><a href='verify_new_run.php?runid=192' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 Verify Run 192</a></p>";
        echo "</div>";

    } catch (Exception $e) {
        echo "</div>";
        echo "<div class='step fail'>";
        echo "<h2>❌ Error</h2>";
        echo "<p>Failed to add columns: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
