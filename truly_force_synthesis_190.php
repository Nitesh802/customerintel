<?php
/**
 * Truly Force Synthesis for Run 190
 *
 * Uses reflection hack to bypass synthesis caching and force correct run ID
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/truly_force_synthesis_190.php'));
$PAGE->set_title('Truly Force Synthesis - Run 190');

echo $OUTPUT->header();

?>
<style>
.force { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
.step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #dc3545; }
.step h2 { margin-top: 0; color: #dc3545; }
.success { color: #28a745; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.alert { background: #dc3545; color: white; padding: 15px; border-radius: 5px; font-weight: bold; margin: 15px 0; }
pre { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
.big-button { display: inline-block; padding: 15px 30px; background: #28a745; color: white !important; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold; margin: 20px 0; }
</style>

<div class="force">

<h1>🔨 Truly Force Synthesis for Run 190</h1>
<p class="alert">⚠️ This script uses reflection to force the correct run ID and bypass caching mechanisms</p>

<?php

$runid = 190;

// =============================================================================
// STEP 1: DELETE ALL SYNTHESIS FOR SAME COMPANY PAIR
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 1: Nuclear Option - Delete All Related Synthesis</h2>";

try {
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);

    // Find all synthesis records for this company pair
    $sql = "SELECT s.* FROM {local_ci_synthesis} s
            JOIN {local_ci_run} r ON s.runid = r.id
            WHERE r.companyid = ? AND r.targetcompanyid = ?";

    $related_synthesis = $DB->get_records_sql($sql, [$run->companyid, $run->targetcompanyid]);

    if ($related_synthesis) {
        echo "<p>Found " . count($related_synthesis) . " synthesis records for company pair {$run->companyid} → {$run->targetcompanyid}</p>";

        foreach ($related_synthesis as $syn) {
            // Delete sections
            $section_count = $DB->count_records('local_ci_synthesis_section', ['synthesisid' => $syn->id]);
            if ($section_count > 0) {
                $DB->delete_records('local_ci_synthesis_section', ['synthesisid' => $syn->id]);
            }

            // Delete synthesis
            $DB->delete_records('local_ci_synthesis', ['id' => $syn->id]);

            echo "<p>Deleted synthesis for Run {$syn->runid} (ID: {$syn->id}, {$section_count} sections)</p>";
        }

        echo "<p class='success'>✅ Cleared all related synthesis records</p>";
    } else {
        echo "<p class='warning'>⚠️ No existing synthesis found (good - starting fresh)</p>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =============================================================================
// STEP 2: CLEAR ALL ARTIFACTS
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 2: Clear All Artifacts for Run {$runid}</h2>";

try {
    $artifact_count = $DB->count_records('local_ci_artifact', ['runid' => $runid]);
    if ($artifact_count > 0) {
        $DB->delete_records('local_ci_artifact', ['runid' => $runid]);
        echo "<p class='success'>✅ Deleted {$artifact_count} artifacts</p>";
    } else {
        echo "<p class='warning'>⚠️ No artifacts found</p>";
    }
} catch (Exception $e) {
    echo "<p class='fail'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =============================================================================
// STEP 3: VERIFY NBs
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 3: Verify NBs in Database</h2>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);
$nb_count = count($nbs);

echo "<p><strong>NBs found: {$nb_count}/15</strong></p>";

if ($nb_count < 15) {
    echo "<p class='fail'>❌ Missing NBs - cannot generate synthesis</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

$total_size = 0;
foreach ($nbs as $nb) {
    $total_size += strlen($nb->jsonpayload ?? '');
}

echo "<p class='success'>✅ All 15 NBs present ({$total_size} bytes total)</p>";

echo "</div>";

// =============================================================================
// STEP 4: DIRECT SYNTHESIS WITH REFLECTION HACK
// =============================================================================
echo "<div class='step' style='border-left-color: #dc3545;'>";
echo "<h2>Step 4: Force Synthesis with Reflection Hack</h2>";
echo "<p><strong>This will take 60-120 seconds if generating fresh...</strong></p>";

flush();
ob_flush();

$start = microtime(true);

try {
    require_once(__DIR__ . '/classes/services/synthesis_engine.php');

    echo "<p>Instantiating synthesis_engine...</p>";
    flush();

    $engine = new \local_customerintel\services\synthesis_engine($runid);

    // REFLECTION HACK: Force correct runid
    echo "<p>Applying reflection hack to force runid = {$runid}...</p>";
    flush();

    $reflection = new ReflectionClass($engine);

    // Force runid property
    if ($reflection->hasProperty('runid')) {
        $runid_prop = $reflection->getProperty('runid');
        $runid_prop->setAccessible(true);
        $current_runid = $runid_prop->getValue($engine);

        if ($current_runid != $runid) {
            echo "<p class='warning'>⚠️ Engine had runid = {$current_runid}, forcing to {$runid}</p>";
            $runid_prop->setValue($engine, $runid);
        } else {
            echo "<p class='success'>✅ Engine already has correct runid = {$runid}</p>";
        }
    }

    echo "<p>Calling build_report() with force_regenerate=true...</p>";
    flush();

    $result = $engine->build_report(
        $run->companyid,
        $run->targetcompanyid,
        true  // force_regenerate
    );

    $duration = microtime(true) - $start;

    echo "<p class='success'>✅ build_report() completed in " . round($duration, 2) . " seconds</p>";

    if ($duration < 5) {
        echo "<p class='warning'>⚠️ Very fast - may still be using cache!</p>";
    } else if ($duration >= 60) {
        echo "<p class='success'>✅ Long duration indicates fresh synthesis with AI calls</p>";
    } else {
        echo "<p class='warning'>⚠️ Moderate duration - check if synthesis has content</p>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Synthesis generation failed: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// =============================================================================
// STEP 5: VERIFY RESULTS
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 5: Verify Synthesis Results</h2>";

try {
    // Check for synthesis
    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    if (!$synthesis) {
        echo "<p class='fail'>❌ No synthesis record found for Run {$runid}!</p>";

        // Check if synthesis was created for a different run
        $all_synthesis = $DB->get_records_sql(
            "SELECT s.*, r.companyid, r.targetcompanyid
             FROM {local_ci_synthesis} s
             JOIN {local_ci_run} r ON s.runid = r.id
             WHERE r.companyid = ? AND r.targetcompanyid = ?
             ORDER BY s.createdat DESC LIMIT 3",
            [$run->companyid, $run->targetcompanyid]
        );

        if ($all_synthesis) {
            echo "<p class='warning'>⚠️ Found synthesis for other runs with same companies:</p>";
            echo "<ul>";
            foreach ($all_synthesis as $syn) {
                echo "<li>Run {$syn->runid} (created " . date('Y-m-d H:i:s', $syn->createdat) . ")</li>";
            }
            echo "</ul>";
            echo "<p class='alert'>🚨 The synthesis_engine caching mechanism is STILL redirecting to other runs!</p>";
            echo "<p>This is a BUG in synthesis_engine that needs to be fixed in the code.</p>";
        }

        echo "</div></div>";
        echo $OUTPUT->footer();
        exit;
    }

    echo "<p class='success'>✅ Synthesis record found (ID: {$synthesis->id})</p>";

    // Check metadata
    $html_size = strlen($synthesis->htmlcontent ?? '');
    $json_size = strlen($synthesis->jsoncontent ?? '');

    echo "<p>HTML Size: " . number_format($html_size) . " bytes</p>";
    echo "<p>JSON Size: " . number_format($json_size) . " bytes</p>";

    if ($html_size > 50000) {
        echo "<p class='success'>✅ Substantial content generated</p>";
    } else if ($html_size > 1000) {
        echo "<p class='warning'>⚠️ Moderate content size</p>";
    } else {
        echo "<p class='fail'>❌ Very small content - likely empty synthesis</p>";
    }

    // Check sections
    $sections = $DB->get_records('local_ci_synthesis_section', ['synthesisid' => $synthesis->id]);
    $section_count = count($sections);

    echo "<p>Sections: {$section_count}</p>";

    if ($section_count >= 9) {
        echo "<p class='success'>✅ All sections created</p>";
    } else if ($section_count > 0) {
        echo "<p class='warning'>⚠️ Only {$section_count} sections</p>";
    } else {
        echo "<p class='fail'>❌ No sections created</p>";
    }

    // Check M1T3 metadata
    if (!empty($synthesis->source_company_id)) {
        echo "<p class='success'>✅ M1T3 metadata present</p>";
    } else {
        echo "<p class='warning'>⚠️ M1T3 metadata missing</p>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Error verifying: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =============================================================================
// FINAL VERDICT
// =============================================================================
echo "<div class='step' style='border-left-color: #28a745; background: #d4edda;'>";
echo "<h2>🎯 Final Verdict</h2>";

if ($synthesis && $html_size > 10000) {
    echo "<p class='success' style='font-size: 20px;'>✅ SUCCESS! Synthesis created for Run {$runid}!</p>";
    echo "<p><a href='view_report.php?runid={$runid}' class='big-button'>📊 View Report</a></p>";
} else if ($synthesis) {
    echo "<p class='warning' style='font-size: 18px;'>⚠️ Synthesis exists but content is minimal</p>";
    echo "<p><a href='view_report.php?runid={$runid}' class='big-button' style='background: #ffc107;'>📊 View Report (Partial)</a></p>";
} else {
    echo "<p class='fail' style='font-size: 18px;'>❌ FAILED - Synthesis caching mechanism is broken</p>";
    echo "<p><strong>Root Cause:</strong> synthesis_engine is hardcoded to reuse synthesis from other runs with the same company pair, ignoring the requested run ID.</p>";
    echo "<p><strong>Solution Needed:</strong> Fix synthesis_engine to respect the exact runid parameter, not just company pair matching.</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
