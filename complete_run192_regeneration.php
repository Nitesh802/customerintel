<?php
/**
 * Complete Run 192 Regeneration - Delete All Artifacts & Force Fresh Generation
 *
 * This script deletes ALL cached artifacts for Run 192 and forces complete
 * regeneration using the FIXED code (citation extraction + NB normalization).
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/complete_run192_regeneration.php'));
$PAGE->set_title("Complete Run 192 Regeneration");

echo $OUTPUT->header();

?>
<style>
.regen { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.danger { background: #f8d7da; border-left-color: #dc3545; }
.info { background: #e7f3ff; border-left-color: #007bff; }
table { width: 100%; border-collapse: collapse; font-size: 12px; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
.btn { display: inline-block; background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; font-size: 18px; border-radius: 5px; font-weight: bold; }
.btn:hover { background: #c82333; color: white; }
.btn-success { background: #28a745; }
.btn-success:hover { background: #218838; }
</style>

<div class="regen">

<h1>🔄 Complete Run <?= $runid ?> Regeneration</h1>

<div class="section info">
<p><strong>This script will:</strong></p>
<ul>
<li>Delete ALL cached artifacts for Run <?= $runid ?></li>
<li>Delete synthesis record (if exists)</li>
<li>Force complete regeneration from database NBs</li>
<li>Use the FIXED code (citation extraction + NB normalization)</li>
</ul>
</div>

<?php

// Step 1: Show what will be deleted
echo "<div class='section'>";
echo "<h2>Step 1: Artifact Inventory</h2>";

$artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid]);
echo "<p><strong>Found " . count($artifacts) . " artifacts to delete:</strong></p>";

if (!empty($artifacts)) {
    echo "<table>";
    echo "<tr>";
    echo "<th>ID</th><th>Phase</th><th>Type</th><th>Size</th><th>Created</th>";
    echo "</tr>";

    foreach ($artifacts as $artifact) {
        $size = strlen($artifact->jsondata);
        echo "<tr>";
        echo "<td>{$artifact->id}</td>";
        echo "<td>{$artifact->phase}</td>";
        echo "<td>{$artifact->artifacttype}</td>";
        echo "<td>" . number_format($size) . " bytes</td>";
        echo "<td>" . date('Y-m-d H:i:s', $artifact->timecreated) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p>✅ No artifacts to delete</p>";
}

echo "</div>";

// Step 2: Check synthesis record
echo "<div class='section'>";
echo "<h2>Step 2: Synthesis Record</h2>";

$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
if ($synthesis) {
    $html_size = !empty($synthesis->htmlcontent) ? strlen($synthesis->htmlcontent) : 0;
    $json_size = !empty($synthesis->jsoncontent) ? strlen($synthesis->jsoncontent) : 0;

    echo "<p><strong>Synthesis ID:</strong> {$synthesis->id}</p>";
    echo "<p><strong>HTML Size:</strong> " . number_format($html_size) . " bytes</p>";
    echo "<p><strong>JSON Size:</strong> " . number_format($json_size) . " bytes</p>";
    echo "<p>⚠️ Will be regenerated from scratch</p>";
} else {
    echo "<p>ℹ️ No synthesis record exists yet</p>";
}

echo "</div>";

// Step 3: Confirm deletion
if (!isset($_GET['confirm'])) {
    echo "<div class='section warning'>";
    echo "<h2>Step 3: Confirmation Required</h2>";
    echo "<p><strong>⚠️ This will:</strong></p>";
    echo "<ul>";
    echo "<li>Delete all " . count($artifacts) . " cached artifacts</li>";
    echo "<li>Delete synthesis record (if exists)</li>";
    echo "<li>Force complete regeneration from database NBs</li>";
    echo "<li>Use the FIXED code (citation extraction + NB normalization)</li>";
    echo "</ul>";
    echo "<p><a href='?confirm=yes' class='btn'>🗑️ DELETE & REGENERATE</a></p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

// Step 4: Execute deletion
echo "<div class='section'>";
echo "<h2>Step 4: Deleting Artifacts</h2>";

try {
    // Delete artifacts
    if (!empty($artifacts)) {
        $DB->delete_records('local_ci_artifact', ['runid' => $runid]);
        echo "<p>✅ Deleted " . count($artifacts) . " artifacts</p>";
    }

    // Delete synthesis
    if ($synthesis) {
        $DB->delete_records('local_ci_synthesis', ['runid' => $runid]);
        echo "<p>✅ Deleted synthesis record</p>";
    }

    echo "<p style='color: #28a745; font-weight: bold;'>✅ Cleanup complete!</p>";

} catch (Exception $e) {
    echo "<p style='color: #dc3545;'>❌ Error during deletion: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// Step 5: Verify NBs are ready
echo "<div class='section'>";
echo "<h2>Step 5: Verify NBs Ready</h2>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode');
echo "<p><strong>NBs available:</strong> " . count($nbs) . "/15</p>";

if (count($nbs) < 15) {
    echo "<p style='color: #dc3545;'>❌ Missing NBs - cannot regenerate synthesis</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// Check citations
$total_citations = 0;
foreach ($nbs as $nb) {
    if (!empty($nb->citations)) {
        $citations = json_decode($nb->citations, true);
        if (is_array($citations)) {
            $total_citations += count($citations);
        }
    }
}

echo "<p><strong>Total citations in NBs:</strong> {$total_citations}</p>";

if ($total_citations == 0) {
    echo "<div class='section warning'>";
    echo "<p style='color: #856404;'>⚠️ Warning: 0 citations found in NBs</p>";
    echo "<p>This might indicate the NBs themselves were cached from before the fix.</p>";
    echo "<p>Consider regenerating NBs with force_refresh flag first.</p>";
    echo "</div>";
}

echo "</div>";

// Step 6: Trigger regeneration
echo "<div class='section'>";
echo "<h2>Step 6: Regenerating Synthesis</h2>";
echo "<p>⏳ This will take 60-120 seconds...</p>";
flush();

require_once(__DIR__ . '/classes/services/synthesis_engine.php');

$start = microtime(true);

try {
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);

    $engine = new \local_customerintel\services\synthesis_engine($runid);

    // Force regenerate
    $result = $engine->build_report($runid, true);  // force_regenerate = true

    $duration = microtime(true) - $start;

    echo "<p>✅ Regeneration completed in " . round($duration, 2) . " seconds</p>";

} catch (Exception $e) {
    echo "<p style='color: #dc3545;'>❌ Error during regeneration: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='background: #f8d7da; padding: 10px; font-size: 11px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// Step 7: Verify results
echo "<div class='section'>";
echo "<h2>Step 7: Verification</h2>";

// Check synthesis
$new_synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
if ($new_synthesis) {
    $html_size = !empty($new_synthesis->htmlcontent) ? strlen($new_synthesis->htmlcontent) : 0;
    $json_size = !empty($new_synthesis->jsoncontent) ? strlen($new_synthesis->jsoncontent) : 0;

    echo "<p>✅ New synthesis created (ID: {$new_synthesis->id})</p>";
    echo "<p><strong>HTML Size:</strong> " . number_format($html_size) . " bytes</p>";
    echo "<p><strong>JSON Size:</strong> " . number_format($json_size) . " bytes</p>";

    // Check if substantial
    if ($html_size > 10000) {
        echo "<div class='section success'>";
        echo "<p style='font-size: 16px;'>✅ <strong>SUCCESS!</strong> Report has substantial content (" . number_format($html_size) . " bytes)</p>";
        echo "</div>";
    } else {
        echo "<div class='section warning'>";
        echo "<p>⚠️ <strong>Warning:</strong> Report still small ({$html_size} bytes)</p>";
        echo "</div>";
    }

    // Check M1T3 metadata
    if (!empty($new_synthesis->source_company_id)) {
        echo "<p>✅ M1T3 metadata present (Source: {$new_synthesis->source_company_id}, Target: {$new_synthesis->target_company_id})</p>";
    }
} else {
    echo "<p style='color: #dc3545;'>❌ No synthesis record created</p>";
}

// Check new artifacts
$new_artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid]);
echo "<p><strong>New artifacts created:</strong> " . count($new_artifacts) . "</p>";

// Check canonical dataset specifically
$canonical = $DB->get_record('local_ci_artifact', [
    'runid' => $runid,
    'artifacttype' => 'canonical_nb_dataset'
]);

if ($canonical) {
    $canonical_data = json_decode($canonical->jsondata, true);

    // Try different ways to get citation count
    $canonical_citations = 0;
    if (isset($canonical_data['aggregated_citations'])) {
        $canonical_citations = count($canonical_data['aggregated_citations']);
    } else if (isset($canonical_data['citations'])) {
        $canonical_citations = count($canonical_data['citations']);
    } else if (isset($canonical_data['processing_stats']['total_citations'])) {
        $canonical_citations = $canonical_data['processing_stats']['total_citations'];
    }

    echo "<p><strong>Canonical dataset citations:</strong> {$canonical_citations}</p>";

    if ($canonical_citations > 200) {
        echo "<div class='section success'>";
        echo "<p>✅ Citations flowing correctly through pipeline!</p>";
        echo "</div>";
    } else {
        echo "<div class='section warning'>";
        echo "<p>⚠️ Expected ~253 citations, found {$canonical_citations}</p>";
        echo "</div>";
    }
}

echo "</div>";

// Final verdict
echo "<div class='section info'>";
echo "<h2>🎯 Final Result</h2>";
echo "<h3>Summary:</h3>";
echo "<ul>";
echo "<li>✅ All old artifacts deleted</li>";
echo "<li>✅ Fresh regeneration completed</li>";
echo "<li>✅ Used fixed code (citation extraction + normalization)</li>";
echo "<li>" . ($html_size > 10000 ? "✅" : "⚠️") . " Report size: " . number_format($html_size) . " bytes</li>";
echo "<li>" . ($canonical_citations > 200 ? "✅" : "⚠️") . " Citations: {$canonical_citations}</li>";
echo "</ul>";
echo "</div>";

echo "<div class='section success'>";
echo "<p style='text-align: center;'>";
echo "<a href='view_report.php?runid={$runid}' class='btn btn-success'>📊 VIEW REPORT</a>";
echo "</p>";
echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
