<?php
/**
 * Complete Artifact Cleanup for Run 192
 *
 * This script performs a COMPLETE cleanup of ALL artifacts and synthesis data,
 * then forces fresh regeneration using the fixed code.
 *
 * IMPORTANT: This deletes EVERYTHING including normalized_inputs_v16 to ensure
 * fresh generation with the citation extraction fix.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/complete_artifact_cleanup_192.php'));
$PAGE->set_title("Complete Artifact Cleanup - Run 192");

echo $OUTPUT->header();

?>
<style>
.cleanup { font-family: 'Segoe UI', Arial, sans-serif; max-width: 1400px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 5px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.danger { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.info { background: #e7f3ff; border-left-color: #17a2b8; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
.btn { display: inline-block; padding: 15px 30px; text-decoration: none; font-size: 16px; border-radius: 5px; font-weight: bold; margin: 10px 5px; border: none; cursor: pointer; }
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }
.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }
.stat { font-size: 20px; font-weight: bold; padding: 10px; margin: 5px 0; }
.good { color: #28a745; }
.bad { color: #dc3545; }
h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
.progress { background: #e9ecef; height: 30px; border-radius: 5px; margin: 10px 0; overflow: hidden; }
.progress-bar { background: #007bff; height: 100%; text-align: center; line-height: 30px; color: white; font-weight: bold; transition: width 0.3s; }
</style>

<div class="cleanup">

<h1>🗑️ Complete Artifact Cleanup - Run <?= $runid ?></h1>

<?php

$step = isset($_GET['step']) ? $_GET['step'] : 'inventory';

// ============================================================================
// STEP 1: INVENTORY - Show what will be deleted
// ============================================================================

if ($step === 'inventory') {
    echo "<div class='section info'>";
    echo "<h2>📋 Step 1: Artifact Inventory</h2>";
    echo "<p>This script will perform a COMPLETE cleanup to force fresh generation.</p>";
    echo "</div>";

    // Get all artifacts
    $artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid], 'phase, artifacttype');

    echo "<div class='section'>";
    echo "<h3>Artifacts to Delete (" . count($artifacts) . " total)</h3>";

    if (empty($artifacts)) {
        echo "<p>ℹ️ No artifacts found.</p>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th><th>Phase</th><th>Type</th><th>Size</th><th>Created</th>";
        echo "</tr>";

        $total_size = 0;
        foreach ($artifacts as $artifact) {
            $size = strlen($artifact->jsondata);
            $total_size += $size;

            echo "<tr>";
            echo "<td>{$artifact->id}</td>";
            echo "<td>{$artifact->phase}</td>";
            echo "<td>{$artifact->artifacttype}</td>";
            echo "<td>" . number_format($size) . " bytes</td>";
            echo "<td>" . date('Y-m-d H:i:s', $artifact->timecreated) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "<p><strong>Total artifact data:</strong> " . number_format($total_size) . " bytes</p>";
    }
    echo "</div>";

    // Check synthesis record
    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    echo "<div class='section'>";
    echo "<h3>Synthesis Record</h3>";

    if ($synthesis) {
        $html_size = !empty($synthesis->htmlcontent) ? strlen($synthesis->htmlcontent) : 0;
        $json_size = !empty($synthesis->jsoncontent) ? strlen($synthesis->jsoncontent) : 0;

        echo "<p>✅ Synthesis record found (ID: {$synthesis->id})</p>";
        echo "<p><strong>HTML Size:</strong> " . number_format($html_size) . " bytes</p>";
        echo "<p><strong>JSON Size:</strong> " . number_format($json_size) . " bytes</p>";
        echo "<p>Will be deleted and regenerated.</p>";
    } else {
        echo "<p>ℹ️ No synthesis record found.</p>";
    }
    echo "</div>";

    // Confirmation
    echo "<div class='section warning'>";
    echo "<h2>⚠️ Confirmation Required</h2>";
    echo "<p><strong>This will DELETE:</strong></p>";
    echo "<ul>";
    echo "<li>✗ All " . count($artifacts) . " artifacts (including normalized_inputs_v16)</li>";
    if ($synthesis) {
        echo "<li>✗ Synthesis record (ID: {$synthesis->id})</li>";
    }
    echo "<li>✗ ALL cached M1T5-M1T8 pipeline data</li>";
    echo "</ul>";
    echo "<p><strong>Then REGENERATE with:</strong></p>";
    echo "<ul>";
    echo "<li>✓ Fixed citation extraction code</li>";
    echo "<li>✓ Fixed NB code normalization</li>";
    echo "<li>✓ Fresh data from database (253 citations)</li>";
    echo "</ul>";
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='?step=delete' class='btn btn-danger'>🗑️ DELETE ALL & CONTINUE</a> ";
    echo "<a href='view_report.php?runid={$runid}' class='btn' style='background: #6c757d; color: white;'>← Cancel</a>";
    echo "</p>";
    echo "</div>";
}

// ============================================================================
// STEP 2: DELETE - Execute deletion
// ============================================================================

else if ($step === 'delete') {
    echo "<div class='section'>";
    echo "<h2>🗑️ Step 2: Deleting Artifacts</h2>";

    try {
        // Get counts before deletion
        $artifact_count = $DB->count_records('local_ci_artifact', ['runid' => $runid]);
        $synthesis_exists = $DB->record_exists('local_ci_synthesis', ['runid' => $runid]);

        // Delete artifacts
        if ($artifact_count > 0) {
            $DB->delete_records('local_ci_artifact', ['runid' => $runid]);
            echo "<p>✅ Deleted {$artifact_count} artifacts</p>";
        } else {
            echo "<p>ℹ️ No artifacts to delete</p>";
        }

        // Delete synthesis
        if ($synthesis_exists) {
            $DB->delete_records('local_ci_synthesis', ['runid' => $runid]);
            echo "<p>✅ Deleted synthesis record</p>";
        } else {
            echo "<p>ℹ️ No synthesis record to delete</p>";
        }

        // Clear OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
            echo "<p>✅ Cleared OPcache</p>";
        }

        echo "<p class='stat good'>✅ CLEANUP COMPLETE</p>";

    } catch (Exception $e) {
        echo "<p class='bad'>❌ Error during deletion: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div></div>";
        echo $OUTPUT->footer();
        exit;
    }

    echo "</div>";

    // Verify NBs are ready
    echo "<div class='section info'>";
    echo "<h2>💾 Step 3: Verify Database NBs Ready</h2>";

    $nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode');
    echo "<p><strong>NBs in database:</strong> " . count($nbs) . "/15</p>";

    if (count($nbs) < 15) {
        echo "<p class='bad'>❌ Missing NBs - cannot regenerate</p>";
        echo "</div></div>";
        echo $OUTPUT->footer();
        exit;
    }

    // Check citation count
    $total_citations = 0;
    foreach ($nbs as $nb) {
        if (!empty($nb->citations)) {
            $citations = json_decode($nb->citations, true);
            if (is_array($citations)) {
                $total_citations += count($citations);
            }
        }
    }

    echo "<p class='stat'><strong>Citations in database:</strong> <span class='" . ($total_citations > 200 ? "good" : "bad") . "'>{$total_citations}</span></p>";

    if ($total_citations < 200) {
        echo "<div class='section warning'>";
        echo "<p>⚠️ Warning: Only {$total_citations} citations found in NBs</p>";
        echo "<p>Expected ~253. NBs may be from before Bug #9 fix.</p>";
        echo "<p>Consider regenerating NBs if synthesis still fails.</p>";
        echo "</div>";
    } else {
        echo "<p class='good'>✅ Sufficient citations available for synthesis</p>";
    }

    echo "</div>";

    // Regeneration button
    echo "<div class='section success'>";
    echo "<h2>🚀 Step 4: Force Fresh Regeneration</h2>";
    echo "<p>All artifacts deleted. Ready to regenerate with fixed code.</p>";
    echo "<p><a href='?step=regenerate' class='btn btn-success'>▶️ REGENERATE NOW</a></p>";
    echo "</div>";
}

// ============================================================================
// STEP 3: REGENERATE - Force fresh generation
// ============================================================================

else if ($step === 'regenerate') {
    echo "<div class='section info'>";
    echo "<h2>⏳ Step 4: Regenerating Synthesis</h2>";
    echo "<p>This will take 60-120 seconds...</p>";
    echo "<div class='progress'>";
    echo "<div class='progress-bar' id='progress'>Starting...</div>";
    echo "</div>";
    echo "</div>";

    flush();
    ob_flush();

    require_once(__DIR__ . '/classes/services/synthesis_engine.php');
    require_once(__DIR__ . '/classes/services/artifact_repository.php');

    $start_time = microtime(true);

    try {
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);

        echo "<script>document.getElementById('progress').style.width='25%'; document.getElementById('progress').innerText='Loading engine...';</script>";
        flush();

        $engine = new \local_customerintel\services\synthesis_engine($runid);

        echo "<script>document.getElementById('progress').style.width='50%'; document.getElementById('progress').innerText='Generating synthesis...';</script>";
        flush();

        // Force regenerate
        $result = $engine->build_report($runid, true);

        $duration = microtime(true) - $start_time;

        echo "<script>document.getElementById('progress').style.width='100%'; document.getElementById('progress').innerText='Complete!';</script>";
        flush();

        echo "<div class='section success'>";
        echo "<p class='stat good'>✅ Regeneration completed in " . round($duration, 2) . " seconds</p>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='section danger'>";
        echo "<p class='bad'>❌ Error during regeneration:</p>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre style='font-size: 11px; background: #f8d7da; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div></div>";
        echo $OUTPUT->footer();
        exit;
    }

    // ============================================================================
    // VERIFICATION - Check results
    // ============================================================================

    echo "<div class='section'>";
    echo "<h2>✅ Step 5: Verification</h2>";

    $artifact_repo = new \local_customerintel\services\artifact_repository();

    // Check M1T5 artifact
    echo "<h3>M1T5: normalized_inputs_v16</h3>";
    $m1t5 = $artifact_repo->get_artifact($runid, 'citation_normalization', 'normalized_inputs_v16');

    if ($m1t5) {
        $m1t5_data = json_decode($m1t5->jsondata, true);
        $m1t5_citations = 0;

        foreach ($m1t5_data['nb'] ?? [] as $nb) {
            $m1t5_citations += count($nb['citations'] ?? []);
        }

        $class = $m1t5_citations > 200 ? 'good' : 'bad';
        echo "<p class='stat'><span class='{$class}'>M1T5 Citations: {$m1t5_citations}</span></p>";

        if ($m1t5_citations > 200) {
            echo "<p class='good'>✅ raw_collector working correctly!</p>";
        } else {
            echo "<p class='bad'>❌ raw_collector still returning {$m1t5_citations} citations</p>";
        }
    } else {
        echo "<p class='bad'>❌ M1T5 artifact not created</p>";
    }

    // Check M1T6 artifact
    echo "<h3>M1T6: canonical_nb_dataset</h3>";
    $m1t6 = $artifact_repo->get_artifact($runid, 'synthesis', 'canonical_nb_dataset');

    if ($m1t6) {
        $m1t6_data = json_decode($m1t6->jsondata, true);
        $m1t6_citations = 0;

        // Try multiple locations
        if (isset($m1t6_data['processing_stats']['total_citations'])) {
            $m1t6_citations = $m1t6_data['processing_stats']['total_citations'];
        } else {
            foreach ($m1t6_data['nb_data'] ?? [] as $nb) {
                $m1t6_citations += count($nb['citations'] ?? []);
            }
        }

        $class = $m1t6_citations > 200 ? 'good' : 'bad';
        echo "<p class='stat'><span class='{$class}'>M1T6 Citations: {$m1t6_citations}</span></p>";

        if ($m1t6_citations > 200) {
            echo "<p class='good'>✅ canonical_builder working correctly!</p>";
        } else {
            echo "<p class='bad'>❌ canonical_builder returning {$m1t6_citations} citations</p>";
        }
    } else {
        echo "<p class='bad'>❌ M1T6 artifact not created</p>";
    }

    // Check pattern detection
    echo "<h3>M1T7: Pattern Detection</h3>";
    $patterns = $artifact_repo->get_artifact($runid, 'discovery', 'detected_patterns');

    if ($patterns) {
        $pattern_data = json_decode($patterns->jsondata, true);
        $pressure_count = count($pattern_data['pressure_themes'] ?? []);
        $capability_count = count($pattern_data['capability_levers'] ?? []);
        $timing_count = count($pattern_data['timing_signals'] ?? []);

        $total_patterns = $pressure_count + $capability_count + $timing_count;

        $class = $total_patterns > 0 ? 'good' : 'bad';
        echo "<p class='stat'><span class='{$class}'>Patterns Detected: {$total_patterns}</span></p>";
        echo "<ul>";
        echo "<li>Pressure themes: {$pressure_count}</li>";
        echo "<li>Capability levers: {$capability_count}</li>";
        echo "<li>Timing signals: {$timing_count}</li>";
        echo "</ul>";

        if ($total_patterns > 0) {
            echo "<p class='good'>✅ Pattern detection working!</p>";
        } else {
            echo "<p class='bad'>❌ Pattern detection found 0 patterns</p>";
        }
    } else {
        echo "<p class='bad'>❌ Pattern detection artifact not created</p>";
    }

    // Check final synthesis
    echo "<h3>Final Synthesis</h3>";
    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    if ($synthesis) {
        $html_size = !empty($synthesis->htmlcontent) ? strlen($synthesis->htmlcontent) : 0;
        $json_size = !empty($synthesis->jsoncontent) ? strlen($synthesis->jsoncontent) : 0;

        $class = $html_size > 10000 ? 'good' : 'bad';
        echo "<p class='stat'><span class='{$class}'>HTML Size: " . number_format($html_size) . " bytes</span></p>";
        echo "<p>JSON Size: " . number_format($json_size) . " bytes</p>";

        if ($html_size > 10000) {
            echo "<p class='good'>✅ FULL REPORT GENERATED!</p>";
        } else {
            echo "<p class='bad'>❌ Report still minimal ({$html_size} bytes)</p>";
        }

        // Check M1T3 metadata
        if (!empty($synthesis->source_company_id)) {
            echo "<p class='good'>✅ M1T3 metadata present</p>";
        }
    } else {
        echo "<p class='bad'>❌ Synthesis record not created</p>";
    }

    echo "</div>";

    // Final summary
    $all_good = ($m1t5_citations > 200) && ($m1t6_citations > 200) && ($total_patterns > 0) && ($html_size > 10000);

    if ($all_good) {
        echo "<div class='section success'>";
        echo "<h2>🎉 SUCCESS!</h2>";
        echo "<p style='font-size: 18px; font-weight: bold;'>All systems working correctly:</p>";
        echo "<ul>";
        echo "<li>✅ M1T5 raw_collector: {$m1t5_citations} citations</li>";
        echo "<li>✅ M1T6 canonical_builder: {$m1t6_citations} citations</li>";
        echo "<li>✅ M1T7 pattern detection: {$total_patterns} patterns</li>";
        echo "<li>✅ Final report: " . number_format($html_size) . " bytes</li>";
        echo "</ul>";
        echo "<p style='margin-top: 20px;'>";
        echo "<a href='view_report.php?runid={$runid}' class='btn btn-success'>📊 VIEW FULL REPORT</a>";
        echo "</p>";
        echo "</div>";
    } else {
        echo "<div class='section warning'>";
        echo "<h2>⚠️ Issues Detected</h2>";
        echo "<p>Some components did not work as expected:</p>";
        echo "<ul>";
        if ($m1t5_citations < 200) echo "<li>❌ M1T5 citations: {$m1t5_citations} (expected ~253)</li>";
        if ($m1t6_citations < 200) echo "<li>❌ M1T6 citations: {$m1t6_citations} (expected ~253)</li>";
        if ($total_patterns == 0) echo "<li>❌ Pattern detection: 0 patterns</li>";
        if ($html_size < 10000) echo "<li>❌ Report size: {$html_size} bytes (expected >10KB)</li>";
        echo "</ul>";
        echo "<p>The fixes may not be fully applied or there may be additional issues.</p>";
        echo "<p><a href='diagnose_canonical_builder_192.php' class='btn' style='background: #ffc107; color: #000;'>🔍 RUN DIAGNOSTICS</a></p>";
        echo "</div>";
    }
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
