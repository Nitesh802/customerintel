<?php
/**
 * Fix M1T3 metadata for existing synthesis records
 *
 * Adds source_company_id and target_company_id to synthesis records
 * that were created before the M1T3 enhancement.
 *
 * Usage: fix_synthesis_metadata.php?runid=X
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = optional_param('runid', 0, PARAM_INT);
$all = optional_param('all', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/fix_synthesis_metadata.php'));
$PAGE->set_title("Fix Synthesis M1T3 Metadata");

echo $OUTPUT->header();

?>
<style>
.fix { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
.step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 10px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; }
</style>

<div class="fix">

<h1>🔧 Fix M1T3 Metadata</h1>

<?php

if (!$runid && !$all) {
    echo "<p>Usage: fix_synthesis_metadata.php?runid=X or ?all=1</p>";

    // Show synthesis records missing metadata
    $missing = $DB->get_records_sql(
        "SELECT s.id, s.runid, r.companyid, r.targetcompanyid, s.createdat
         FROM {local_ci_synthesis} s
         JOIN {local_ci_run} r ON r.id = s.runid
         WHERE (s.source_company_id IS NULL OR s.source_company_id = 0)
         ORDER BY s.createdat DESC
         LIMIT 20"
    );

    if (empty($missing)) {
        echo "<p class='success'>✅ All synthesis records have M1T3 metadata!</p>";
    } else {
        echo "<h2>Synthesis Records Missing M1T3 Metadata</h2>";
        echo "<p>Found " . count($missing) . " records needing metadata:</p>";

        echo "<table>";
        echo "<tr><th>Synthesis ID</th><th>Run ID</th><th>Created</th><th>Action</th></tr>";

        foreach ($missing as $synth) {
            echo "<tr>";
            echo "<td>{$synth->id}</td>";
            echo "<td>{$synth->runid}</td>";
            echo "<td>" . date('Y-m-d H:i', $synth->createdat) . "</td>";
            echo "<td><a href='?runid={$synth->runid}'>Fix</a></td>";
            echo "</tr>";
        }

        echo "</table>";

        echo "<p><a href='?all=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fix All</a></p>";
    }

    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

// Fix specific run or all
if ($all) {
    echo "<h2>Fixing All Synthesis Records</h2>";

    $missing = $DB->get_records_sql(
        "SELECT s.id, s.runid, r.companyid, r.targetcompanyid
         FROM {local_ci_synthesis} s
         JOIN {local_ci_run} r ON r.id = s.runid
         WHERE (s.source_company_id IS NULL OR s.source_company_id = 0)"
    );

    $fixed_count = 0;

    foreach ($missing as $synth) {
        $DB->set_field('local_ci_synthesis', 'source_company_id', $synth->companyid, ['id' => $synth->id]);
        $DB->set_field('local_ci_synthesis', 'target_company_id', $synth->targetcompanyid, ['id' => $synth->id]);
        $DB->set_field('local_ci_synthesis', 'updatedat', time(), ['id' => $synth->id]);
        $fixed_count++;
    }

    echo "<div class='step success'>";
    echo "<p class='success'>✅ Fixed {$fixed_count} synthesis records</p>";
    echo "</div>";

} else {
    // Fix specific run
    echo "<h2>Fixing Run {$runid}</h2>";

    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    if (!$synthesis) {
        echo "<div class='step fail'>";
        echo "<p class='fail'>❌ No synthesis record found for run {$runid}</p>";
        echo "</div>";
        echo "</div>";
        echo $OUTPUT->footer();
        exit;
    }

    $run = $DB->get_record('local_ci_run', ['id' => $runid]);

    if (!$run) {
        echo "<div class='step fail'>";
        echo "<p class='fail'>❌ No run record found for ID {$runid}</p>";
        echo "</div>";
        echo "</div>";
        echo $OUTPUT->footer();
        exit;
    }

    echo "<div class='step'>";
    echo "<h3>Current State</h3>";
    echo "<p>Synthesis ID: {$synthesis->id}</p>";
    echo "<p>Source Company ID: " . ($synthesis->source_company_id ?? 'NULL') . "</p>";
    echo "<p>Target Company ID: " . ($synthesis->target_company_id ?? 'NULL') . "</p>";
    echo "</div>";

    echo "<div class='step'>";
    echo "<h3>Updating with M1T3 Metadata</h3>";

    $synthesis->source_company_id = $run->companyid;
    $synthesis->target_company_id = $run->targetcompanyid ?? null;
    $synthesis->updatedat = time();

    $DB->update_record('local_ci_synthesis', $synthesis);

    echo "<p class='success'>✅ Updated synthesis record</p>";
    echo "<p>Source Company ID: <strong>{$synthesis->source_company_id}</strong></p>";
    echo "<p>Target Company ID: <strong>{$synthesis->target_company_id}</strong></p>";
    echo "</div>";

    echo "<div class='step success'>";
    echo "<h3>✅ Fix Complete!</h3>";
    echo "<p><a href='verify_new_run.php?runid={$runid}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 Verify Run</a></p>";
    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
