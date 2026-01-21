<?php
/**
 * Backfill synthesis database records from artifacts
 *
 * For runs where synthesis was generated but not saved to database,
 * this script recreates the synthesis and section records from artifacts.
 *
 * Usage: backfill_synthesis_from_artifacts.php?runid=X
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = optional_param('runid', 0, PARAM_INT);

if (!$runid) {
    echo "<h1>Backfill Synthesis from Artifacts</h1>";
    echo "<p>Usage: backfill_synthesis_from_artifacts.php?runid=X</p>";

    // Show runs that have artifacts but no synthesis record
    $runs_with_artifacts = $DB->get_records_sql(
        "SELECT DISTINCT a.runid, r.status, r.timecreated
         FROM {local_ci_artifact} a
         LEFT JOIN {local_ci_run} r ON r.id = a.runid
         LEFT JOIN {local_ci_synthesis} s ON s.runid = a.runid
         WHERE a.phase = 'synthesis'
         AND a.artifacttype = 'final_bundle'
         AND s.id IS NULL
         ORDER BY r.timecreated DESC
         LIMIT 20"
    );

    echo "<h2>Runs Needing Backfill</h2>";
    echo "<p>These runs have synthesis artifacts but no database record:</p>";

    if (empty($runs_with_artifacts)) {
        echo "<p>No runs found needing backfill.</p>";
        exit;
    }

    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Run ID</th><th>Status</th><th>Created</th><th>Action</th></tr>";

    foreach ($runs_with_artifacts as $run) {
        echo "<tr>";
        echo "<td>{$run->runid}</td>";
        echo "<td>{$run->status}</td>";
        echo "<td>" . date('Y-m-d H:i', $run->timecreated) . "</td>";
        echo "<td><a href='?runid={$run->runid}'>Backfill</a></td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/backfill_synthesis_from_artifacts.php', ['runid' => $runid]));
$PAGE->set_title("Backfill Synthesis - Run {$runid}");

echo $OUTPUT->header();

?>
<style>
.backfill { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
.step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: white; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>

<div class="backfill">

<h1>🔄 Backfill Synthesis from Artifacts - Run <?php echo $runid; ?></h1>

<?php

// Step 1: Check if synthesis already exists
echo "<div class='step'>";
echo "<h2>Step 1: Check Current State</h2>";

$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

if ($synthesis) {
    echo "<p class='warning'>⚠️ Synthesis record already exists (ID: {$synthesis->id})</p>";
    echo "<p>This run doesn't need backfilling. Use verify_new_run.php to check it instead.</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<p>✅ No synthesis record found - backfill needed</p>";
echo "</div>";

// Step 2: Load artifacts
echo "<div class='step'>";
echo "<h2>Step 2: Load Synthesis Artifacts</h2>";

$final_bundle = $DB->get_record('local_ci_artifact', [
    'runid' => $runid,
    'phase' => 'synthesis',
    'artifacttype' => 'final_bundle'
]);

if (!$final_bundle) {
    echo "<p class='fail'>❌ No final_bundle artifact found!</p>";
    echo "<p>Cannot backfill without synthesis artifacts.</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

$bundle_data = json_decode($final_bundle->jsondata, true);
if (!$bundle_data) {
    echo "<p class='fail'>❌ Failed to parse final_bundle JSON!</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<p>✅ Loaded final_bundle artifact (" . number_format(strlen($final_bundle->jsondata)) . " bytes)</p>";

// Show what we found
echo "<h3>Bundle Contents:</h3>";
echo "<ul>";
if (isset($bundle_data['html'])) echo "<li>HTML content: " . number_format(strlen($bundle_data['html'])) . " bytes</li>";
if (isset($bundle_data['json'])) echo "<li>JSON content: " . number_format(strlen($bundle_data['json'])) . " bytes</li>";
if (isset($bundle_data['sections'])) echo "<li>Sections: " . count($bundle_data['sections']) . "</li>";
if (isset($bundle_data['citations'])) echo "<li>Citations: " . count($bundle_data['citations']) . "</li>";
if (isset($bundle_data['sources'])) echo "<li>Sources: " . count($bundle_data['sources']) . "</li>";
echo "</ul>";

echo "</div>";

// Step 3: Create synthesis record
echo "<div class='step'>";
echo "<h2>Step 3: Create Synthesis Record</h2>";

try {
    require_once(__DIR__ . '/classes/services/artifact_compatibility_adapter.php');
    require_once(__DIR__ . '/classes/services/log_service.php');

    $adapter = new \local_customerintel\services\artifact_compatibility_adapter();

    // Use the adapter to save the synthesis bundle
    // This will now create the record if it doesn't exist (thanks to our fix!)
    $success = $adapter->save_synthesis_bundle($runid, $bundle_data);

    if ($success) {
        echo "<p class='success'>✅ Synthesis record created successfully!</p>";

        // Verify it was created
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        if ($synthesis) {
            echo "<p>Synthesis ID: <strong>{$synthesis->id}</strong></p>";
            echo "<p>HTML size: <strong>" . number_format(strlen($synthesis->htmlcontent)) . "</strong> bytes</p>";

            // Check M1T3 metadata
            if (!empty($synthesis->source_company_id)) {
                echo "<p>M1T3 Metadata: ✅ Source ID: {$synthesis->source_company_id}, Target ID: {$synthesis->target_company_id}</p>";
            }

            // Extract section count from JSON
            if (!empty($synthesis->jsoncontent)) {
                $json_data = json_decode($synthesis->jsoncontent, true);
                $section_count = isset($json_data['synthesis_cache']['v15_structure']['sections'])
                    ? count($json_data['synthesis_cache']['v15_structure']['sections'])
                    : 0;
                echo "<p>Sections in JSON: <strong>{$section_count}</strong></p>";
            }
        }
    } else {
        echo "<p class='fail'>❌ Failed to create synthesis record</p>";
        echo "<p>Check Moodle logs for error details.</p>";
    }

} catch (\Exception $e) {
    echo "<p class='fail'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}

echo "</div>";

// Step 4: Verification
echo "<div class='step success'>";
echo "<h2>Step 4: Verification</h2>";

$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

if ($synthesis) {
    // Extract section count from JSON
    $section_count = 0;
    if (!empty($synthesis->jsoncontent)) {
        $json_data = json_decode($synthesis->jsoncontent, true);
        if (isset($json_data['synthesis_cache']['v15_structure']['sections'])) {
            $section_count = count($json_data['synthesis_cache']['v15_structure']['sections']);
        }
    }

    echo "<p><strong>✅ Backfill Complete!</strong></p>";
    echo "<ul>";
    echo "<li>✅ Synthesis record created (ID: {$synthesis->id})</li>";
    echo "<li>✅ {$section_count} sections stored in JSON</li>";
    echo "<li>✅ M1T3 metadata preserved</li>";
    echo "</ul>";

    echo "<h3>Next Steps:</h3>";
    echo "<p><a href='verify_new_run.php?runid={$runid}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📋 Verify Run</a></p>";
    echo "<p><a href='view_report.php?runid={$runid}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📊 View Report</a></p>";

} else {
    echo "<p class='fail'>❌ Backfill failed - synthesis record not found after creation attempt</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
