<?php
/**
 * Delete Run 192 artifacts to force fresh database loading
 *
 * This will delete old cached artifacts that have 0 citations,
 * forcing raw_collector to load fresh NB data from database.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/DELETE_RUN192_ARTIFACTS.php'));
$PAGE->set_title("Delete Run 192 Artifacts");

echo $OUTPUT->header();

?>
<style>
.cleanup { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
</style>

<div class="cleanup">

<h1>🗑️ Delete Run 192 Artifacts</h1>

<div class="section warning">
<h2>⚠️ Warning</h2>
<p><strong>This will delete all cached artifacts for Run 192!</strong></p>
<p>This is necessary because the old artifacts have 0 citations (created before Bug #9 fix).</p>
<p>After deletion, regenerating synthesis will force raw_collector to load fresh data from database.</p>
</div>

<?php

$confirm = optional_param('confirm', '', PARAM_ALPHA);

if ($confirm !== 'yes') {
    echo "<div class='section'>";
    echo "<h2>Current Artifacts for Run $runid</h2>";

    $artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid], 'phase ASC, artifacttype ASC');

    if (empty($artifacts)) {
        echo "<p>No artifacts found for Run {$runid}</p>";
    } else {
        echo "<p>Found " . count($artifacts) . " artifacts:</p>";
        echo "<table style='width: 100%; border-collapse: collapse; font-size: 12px;'>";
        echo "<tr style='background: #e9ecef;'>";
        echo "<th style='padding: 8px; border: 1px solid #dee2e6;'>ID</th>";
        echo "<th style='padding: 8px; border: 1px solid #dee2e6;'>Phase</th>";
        echo "<th style='padding: 8px; border: 1px solid #dee2e6;'>Type</th>";
        echo "<th style='padding: 8px; border: 1px solid #dee2e6;'>Size</th>";
        echo "<th style='padding: 8px; border: 1px solid #dee2e6;'>Created</th>";
        echo "</tr>";

        foreach ($artifacts as $artifact) {
            echo "<tr>";
            echo "<td style='padding: 8px; border: 1px solid #dee2e6;'>{$artifact->id}</td>";
            echo "<td style='padding: 8px; border: 1px solid #dee2e6;'>{$artifact->phase}</td>";
            echo "<td style='padding: 8px; border: 1px solid #dee2e6;'>{$artifact->artifacttype}</td>";
            echo "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . number_format(strlen($artifact->jsondata)) . " B</td>";
            echo "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . date('Y-m-d H:i:s', $artifact->timecreated) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    echo "</div>";

    echo "<div class='section warning'>";
    echo "<h2>🚨 Confirm Deletion</h2>";
    echo "<p>Click the button below to DELETE all artifacts for Run {$runid}.</p>";
    echo "<p>After deletion, you'll need to regenerate synthesis to create fresh artifacts.</p>";
    echo "<p><a href='?confirm=yes' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🗑️ DELETE ARTIFACTS</a></p>";
    echo "</div>";

} else {
    // Perform deletion
    echo "<div class='section'>";
    echo "<h2>Deleting Artifacts...</h2>";

    $count = $DB->count_records('local_ci_artifact', ['runid' => $runid]);

    if ($count === 0) {
        echo "<p class='warning'>No artifacts to delete.</p>";
    } else {
        $deleted = $DB->delete_records('local_ci_artifact', ['runid' => $runid]);

        if ($deleted) {
            echo "<div class='success'>";
            echo "<h3>✅ Artifacts Deleted Successfully!</h3>";
            echo "<p><strong>Deleted {$count} artifacts for Run {$runid}</strong></p>";
            echo "</div>";
        } else {
            echo "<div class='fail'>";
            echo "<h3>❌ Deletion Failed</h3>";
            echo "<p>Check Moodle logs for details.</p>";
            echo "</div>";
        }
    }

    echo "</div>";

    echo "<div class='section success'>";
    echo "<h2>✅ Next Steps</h2>";
    echo "<ol>";
    echo "<li><strong>Regenerate Synthesis:</strong> <a href='regenerate_run192.php'>Run regenerate_run192.php</a></li>";
    echo "<li>This will force raw_collector to load fresh NBs from database (with citations!)</li>";
    echo "<li>Verify Run 192 report has full content</li>";
    echo "</ol>";
    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
