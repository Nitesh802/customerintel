<?php
/**
 * Force Fresh Synthesis for Run 190
 *
 * Clears cached artifacts and regenerates synthesis from database NBs
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/force_fresh_synthesis_190.php'));
$PAGE->set_title('Force Fresh Synthesis - Run 190');

echo $OUTPUT->header();

?>
<style>
.force-synth { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
.step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.step h2 { margin-top: 0; color: #007bff; }
.success { color: #28a745; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.metric { display: inline-block; background: white; padding: 10px 15px; margin: 5px; border-radius: 3px; border: 1px solid #dee2e6; }
.metric-label { font-weight: bold; margin-right: 10px; }
.metric-value { color: #007bff; font-size: 1.1em; }
pre { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
.big-button { display: inline-block; padding: 15px 30px; background: #28a745; color: white !important; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold; margin: 20px 0; }
.big-button:hover { background: #218838; }
</style>

<div class="force-synth">

<h1>🔄 Force Fresh Synthesis for Run 190</h1>
<p>This will clear cached artifacts and regenerate synthesis from the 15 NBs in the database.</p>

<?php

$runid = 190;

// =============================================================================
// STEP 1: CLEAR CACHED ARTIFACTS
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 1: Clear Cached Artifacts</h2>";

try {
    $artifact_count = $DB->count_records('local_ci_artifact', ['runid' => $runid]);
    echo "<p>Found {$artifact_count} cached artifacts for Run {$runid}</p>";

    if ($artifact_count > 0) {
        $DB->delete_records('local_ci_artifact', ['runid' => $runid]);
        echo "<p class='success'>✅ Cleared {$artifact_count} cached artifacts</p>";
    } else {
        echo "<p class='warning'>⚠️ No cached artifacts found (this is okay)</p>";
    }
} catch (Exception $e) {
    echo "<p class='fail'>❌ Error clearing artifacts: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =============================================================================
// STEP 2: VERIFY NBs IN DATABASE
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 2: Verify NBs in Database</h2>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');
$nb_count = count($nbs);

echo "<div class='metric'><span class='metric-label'>NBs Found:</span><span class='metric-value'>{$nb_count}/15</span></div>";

if ($nb_count < 15) {
    echo "<p class='fail'>❌ Missing NBs - cannot proceed with synthesis</p>";
    echo "<p>Expected 15 NBs, found {$nb_count}</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<p class='success'>✅ All 15 NBs present in database</p>";

// Show NB summary
$total_size = 0;
$total_tokens = 0;
foreach ($nbs as $nb) {
    $total_size += strlen($nb->jsonpayload ?? '');
    $total_tokens += $nb->tokensused ?? 0;
}

echo "<div class='metric'><span class='metric-label'>Total Data:</span><span class='metric-value'>" . number_format($total_size) . " bytes</span></div>";
echo "<div class='metric'><span class='metric-label'>Total Tokens:</span><span class='metric-value'>" . number_format($total_tokens) . "</span></div>";

if ($total_size < 10000) {
    echo "<p class='warning'>⚠️ Warning: Very small total data size - NBs may be empty or minimal</p>";
}

echo "</div>";

// =============================================================================
// STEP 3: GET RUN AND COMPANY INFO
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 3: Load Run Information</h2>";

try {
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
    $source = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
    $target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid], '*', MUST_EXIST);

    echo "<div class='metric'><span class='metric-label'>Source:</span><span class='metric-value'>{$source->name}</span></div>";
    echo "<div class='metric'><span class='metric-label'>Target:</span><span class='metric-value'>{$target->name}</span></div>";
    echo "<div class='metric'><span class='metric-label'>Status:</span><span class='metric-value'>{$run->status}</span></div>";

    echo "<p class='success'>✅ Run information loaded</p>";
} catch (Exception $e) {
    echo "<p class='fail'>❌ Error loading run: " . $e->getMessage() . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// =============================================================================
// STEP 4: DELETE OLD SYNTHESIS RECORD
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 4: Clear Old Synthesis Record</h2>";

try {
    $old_synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    if ($old_synthesis) {
        // Delete related sections first
        $section_count = $DB->count_records('local_ci_synthesis_section', ['synthesisid' => $old_synthesis->id]);
        if ($section_count > 0) {
            $DB->delete_records('local_ci_synthesis_section', ['synthesisid' => $old_synthesis->id]);
            echo "<p>Deleted {$section_count} old sections</p>";
        }

        // Delete synthesis record
        $DB->delete_records('local_ci_synthesis', ['id' => $old_synthesis->id]);
        echo "<p class='success'>✅ Cleared old synthesis record (ID: {$old_synthesis->id})</p>";
    } else {
        echo "<p class='warning'>⚠️ No old synthesis record found (creating fresh)</p>";
    }
} catch (Exception $e) {
    echo "<p class='fail'>❌ Error clearing old synthesis: " . $e->getMessage() . "</p>";
}

echo "</div>";

// =============================================================================
// STEP 5: GENERATE FRESH SYNTHESIS
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 5: Generate Fresh Synthesis</h2>";
echo "<p><strong>This will take 60-120 seconds...</strong></p>";
echo "<p>Starting synthesis generation...</p>";

flush();
ob_flush();

$start = microtime(true);

try {
    // Load synthesis engine
    require_once(__DIR__ . '/classes/services/synthesis_engine.php');

    echo "<p>Creating synthesis engine...</p>";
    flush();
    ob_flush();

    $engine = new \local_customerintel\services\synthesis_engine($runid);

    echo "<p>Calling build_report() with force_regenerate=true...</p>";
    flush();
    ob_flush();

    // Force regenerate - this should build fresh from database NBs
    $result = $engine->build_report(
        $run->companyid,
        $run->targetcompanyid,
        true  // force_regenerate = true
    );

    $duration = microtime(true) - $start;

    echo "<p class='success'>✅ Synthesis completed in " . round($duration, 2) . " seconds</p>";

    if ($duration < 5) {
        echo "<p class='warning'>⚠️ Suspiciously fast - may have used cached data instead of regenerating</p>";
    } else if ($duration >= 60) {
        echo "<p class='success'>✅ Duration indicates full synthesis with AI calls</p>";
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
// STEP 6: VERIFY RESULTS
// =============================================================================
echo "<div class='step'>";
echo "<h2>Step 6: Verify Synthesis Results</h2>";

try {
    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    if (!$synthesis) {
        echo "<p class='fail'>❌ No synthesis record found after generation!</p>";
        echo "<p>This indicates synthesis_engine may not have saved the result.</p>";
        echo "</div></div>";
        echo $OUTPUT->footer();
        exit;
    }

    echo "<p class='success'>✅ Synthesis record created (ID: {$synthesis->id})</p>";

    // Check M1T3 metadata
    echo "<h3>M1T3 Metadata</h3>";
    $metadata_fields = ['source_company_id', 'target_company_id', 'source_company_name', 'target_company_name'];
    $has_metadata = false;

    foreach ($metadata_fields as $field) {
        if (isset($synthesis->$field) && !empty($synthesis->$field)) {
            $has_metadata = true;
            echo "<div class='metric'><span class='metric-label'>{$field}:</span><span class='metric-value'>{$synthesis->$field}</span></div>";
        }
    }

    if ($has_metadata) {
        echo "<p class='success'>✅ M1T3 metadata present</p>";
    } else {
        echo "<p class='fail'>❌ M1T3 metadata missing</p>";
    }

    // Check content sizes
    echo "<h3>Content Analysis</h3>";
    $html_size = strlen($synthesis->htmlcontent ?? '');
    $json_size = strlen($synthesis->jsoncontent ?? '');

    echo "<div class='metric'><span class='metric-label'>HTML Size:</span><span class='metric-value'>" . number_format($html_size) . " bytes</span></div>";
    echo "<div class='metric'><span class='metric-label'>JSON Size:</span><span class='metric-value'>" . number_format($json_size) . " bytes</span></div>";

    if ($html_size > 50000) {
        echo "<p class='success'>✅ Substantial content generated (>{$html_size} bytes)</p>";
    } else if ($html_size > 10000) {
        echo "<p class='warning'>⚠️ Moderate content size ({$html_size} bytes)</p>";
    } else {
        echo "<p class='fail'>❌ Very small content ({$html_size} bytes) - may indicate empty synthesis</p>";
    }

    // Check sections
    echo "<h3>Sections Generated</h3>";
    $sections = $DB->get_records('local_ci_synthesis_section', ['synthesisid' => $synthesis->id], 'sectioncode ASC');
    $section_count = count($sections);

    echo "<div class='metric'><span class='metric-label'>Sections:</span><span class='metric-value'>{$section_count}</span></div>";

    if ($section_count >= 9) {
        echo "<p class='success'>✅ All expected sections created</p>";
    } else if ($section_count > 0) {
        echo "<p class='warning'>⚠️ Only {$section_count} sections created (expected 9)</p>";
    } else {
        echo "<p class='fail'>❌ No sections created</p>";
    }

    if ($section_count > 0) {
        echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th style='border: 1px solid #ddd; padding: 8px;'>Section</th><th style='border: 1px solid #ddd; padding: 8px;'>Content Size</th></tr>";

        foreach ($sections as $section) {
            $size = strlen($section->htmlcontent ?? '');
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$section->sectioncode}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . number_format($size) . " bytes</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Error verifying results: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";

// =============================================================================
// FINAL VERDICT
// =============================================================================
echo "<div class='step' style='border-left-color: #28a745; background: #d4edda;'>";
echo "<h2>🎉 Final Verdict</h2>";

if ($synthesis && $html_size > 10000 && $section_count >= 5) {
    echo "<p class='success' style='font-size: 20px;'>✅ SUCCESS! Synthesis generated with real data!</p>";
    echo "<p><a href='view_report.php?runid={$runid}' class='big-button'>📊 View Full Report</a></p>";
    echo "<p><a href='verify_full_pipeline.php?runid={$runid}' class='big-button' style='background: #007bff;'>🔍 Verify Pipeline</a></p>";
} else if ($synthesis && $section_count > 0) {
    echo "<p class='warning' style='font-size: 18px;'>⚠️ PARTIAL SUCCESS - Synthesis created but may be incomplete</p>";
    echo "<p><a href='view_report.php?runid={$runid}' class='big-button' style='background: #ffc107;'>📊 View Report (Partial)</a></p>";
} else {
    echo "<p class='fail' style='font-size: 18px;'>❌ FAILED - Synthesis generation did not produce expected results</p>";
    echo "<p>Next steps:</p>";
    echo "<ul>";
    echo "<li>Check if synthesis_engine is reading from database or artifacts</li>";
    echo "<li>Verify M1T5-M1T8 services are integrated correctly</li>";
    echo "<li>Check trace logs for errors during synthesis</li>";
    echo "</ul>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
