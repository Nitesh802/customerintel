<?php
/**
 * Delete the citation_normalization artifact for Run 192
 * This will force raw_collector to rebuild it with the fixed code
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/delete_citation_normalization_artifact.php'));
$PAGE->set_title("Delete Citation Normalization Artifact");

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

<h1>🗑️ Delete Citation Normalization Artifact - Run 192</h1>

<div class="section warning">
<h2>⚠️ Why Delete This Artifact?</h2>
<p>The <code>normalized_inputs_v16</code> artifact was created BEFORE we fixed the citation extraction bug in raw_collector.php.</p>
<p>Even though we fixed the code, raw_collector is loading the OLD cached artifact instead of rebuilding with the new code.</p>
<p><strong>Solution:</strong> Delete the old artifact to force it to rebuild with citations!</p>
</div>

<?php

$confirm = optional_param('confirm', '', PARAM_ALPHA);

if ($confirm !== 'yes') {
    echo "<div class='section'>";
    echo "<h2>Citation Normalization Artifact</h2>";

    $artifact = $DB->get_record('local_ci_artifact', [
        'runid' => $runid,
        'phase' => 'citation_normalization',
        'artifacttype' => 'normalized_inputs_v16'
    ]);

    if (!$artifact) {
        echo "<p class='warning'>No citation_normalization artifact found. It may have already been deleted.</p>";
        echo "<p><a href='regenerate_run192.php'>Regenerate Run 192</a></p>";
    } else {
        echo "<p>Found artifact:</p>";
        echo "<table style='width: 100%; border-collapse: collapse; font-size: 12px;'>";
        echo "<tr style='background: #e9ecef;'>";
        echo "<th style='padding: 8px; border: 1px solid #dee2e6;'>Field</th>";
        echo "<th style='padding: 8px; border: 1px solid #dee2e6;'>Value</th>";
        echo "</tr>";

        echo "<tr><td style='padding: 8px; border: 1px solid #dee2e6;'><strong>ID</strong></td><td style='padding: 8px; border: 1px solid #dee2e6;'>{$artifact->id}</td></tr>";
        echo "<tr><td style='padding: 8px; border: 1px solid #dee2e6;'><strong>Run ID</strong></td><td style='padding: 8px; border: 1px solid #dee2e6;'>{$artifact->runid}</td></tr>";
        echo "<tr><td style='padding: 8px; border: 1px solid #dee2e6;'><strong>Phase</strong></td><td style='padding: 8px; border: 1px solid #dee2e6;'>{$artifact->phase}</td></tr>";
        echo "<tr><td style='padding: 8px; border: 1px solid #dee2e6;'><strong>Type</strong></td><td style='padding: 8px; border: 1px solid #dee2e6;'>{$artifact->artifacttype}</td></tr>";
        echo "<tr><td style='padding: 8px; border: 1px solid #dee2e6;'><strong>Size</strong></td><td style='padding: 8px; border: 1px solid #dee2e6;'>" . number_format(strlen($artifact->jsondata)) . " bytes</td></tr>";
        echo "<tr><td style='padding: 8px; border: 1px solid #dee2e6;'><strong>Created</strong></td><td style='padding: 8px; border: 1px solid #dee2e6;'>" . date('Y-m-d H:i:s', $artifact->timecreated) . "</td></tr>";

        echo "</table>";

        echo "<div class='section warning'>";
        echo "<h3>🔴 This artifact was created with the OLD buggy code!</h3>";
        echo "<p>The NB data structure in this artifact does NOT have the 'citations' key.</p>";
        echo "<p>Even though we fixed the code, raw_collector loads this OLD artifact instead of rebuilding.</p>";
        echo "</div>";
    }

    echo "</div>";

    if ($artifact) {
        echo "<div class='section warning'>";
        echo "<h2>🚨 Confirm Deletion</h2>";
        echo "<p>Click the button below to DELETE this artifact.</p>";
        echo "<p>After deletion, regenerate synthesis to create a NEW artifact with citations!</p>";
        echo "<p><a href='?confirm=yes' style='background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🗑️ DELETE ARTIFACT</a></p>";
        echo "</div>";
    }

} else {
    // Perform deletion
    echo "<div class='section'>";
    echo "<h2>Deleting Artifact...</h2>";

    $deleted = $DB->delete_records('local_ci_artifact', [
        'runid' => $runid,
        'phase' => 'citation_normalization',
        'artifacttype' => 'normalized_inputs_v16'
    ]);

    if ($deleted) {
        echo "<div class='success'>";
        echo "<h3>✅ Artifact Deleted Successfully!</h3>";
        echo "<p><strong>The old citation_normalization artifact has been removed.</strong></p>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<h3>⚠️ No Artifact to Delete</h3>";
        echo "<p>The artifact may have already been deleted.</p>";
        echo "</div>";
    }

    echo "</div>";

    echo "<div class='section success'>";
    echo "<h2>✅ Next Step</h2>";
    echo "<p><strong>Regenerate Synthesis:</strong></p>";
    echo "<p><a href='regenerate_run192.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🔄 Regenerate Run 192</a></p>";
    echo "<p>This will force raw_collector to rebuild the artifact using the FIXED code with citations!</p>";
    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
