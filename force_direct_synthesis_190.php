<?php
/**
 * Force Direct Synthesis Generation for Run 190
 *
 * Bypasses normal workflow and directly calls M1T5-8 pipeline
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/force_direct_synthesis_190.php'));
$PAGE->set_title('Force Direct Synthesis - Run 190');

echo $OUTPUT->header();

?>
<style>
.direct-synth { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
.stage { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.stage h3 { margin-top: 0; color: #007bff; }
.success { color: #28a745; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
pre { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
.big-button { display: inline-block; padding: 15px 30px; background: #28a745; color: white !important; text-decoration: none; border-radius: 5px; font-size: 20px; font-weight: bold; margin: 20px 0; }
.metric { display: inline-block; background: white; padding: 10px 15px; margin: 5px; border-radius: 3px; border: 1px solid #dee2e6; }
</style>

<div class="direct-synth">

<h1>🚀 Force Direct Synthesis - Run 190</h1>
<p><strong>Bypassing workflow to directly invoke M1T5-8 pipeline</strong></p>

<?php

$runid = 190;

// =============================================================================
// SETUP
// =============================================================================
echo "<div class='stage'>";
echo "<h3>Setup & Validation</h3>";

try {
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
    $company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
    $target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid], '*', MUST_EXIST);

    echo "<p>Run {$runid}: <strong>{$company->name}</strong> → <strong>{$target->name}</strong></p>";

    // Verify NBs
    $nb_count = $DB->count_records('local_ci_nb_result', ['runid' => $runid]);
    echo "<div class='metric'>NBs Available: <strong>{$nb_count}/15</strong></div>";

    if ($nb_count < 15) {
        echo "<p class='fail'>❌ Need 15 NBs to generate synthesis</p>";
        echo "</div></div>";
        echo $OUTPUT->footer();
        exit;
    }

    echo "<p class='success'>✅ All prerequisites met</p>";

    // Clear cached artifacts
    $artifact_count = $DB->count_records('local_ci_artifact', ['runid' => $runid]);
    if ($artifact_count > 0) {
        $DB->delete_records('local_ci_artifact', ['runid' => $runid]);
        echo "<p>Cleared {$artifact_count} cached artifacts</p>";
    }

    // Delete old synthesis if exists
    $old_syn = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
    if ($old_syn) {
        $DB->delete_records('local_ci_synthesis_section', ['synthesisid' => $old_syn->id]);
        $DB->delete_records('local_ci_synthesis', ['id' => $old_syn->id]);
        echo "<p>Deleted old synthesis record</p>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Setup failed: " . $e->getMessage() . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// =============================================================================
// SYNTHESIS GENERATION
// =============================================================================
echo "<div class='stage'>";
echo "<h3>Generating Synthesis</h3>";
echo "<p class='warning'>⏱️ This will take 60-120 seconds...</p>";

flush();
ob_flush();

$start = microtime(true);
$synthesis_id = null;
$section_count = 0;

try {
    // Load synthesis engine
    require_once(__DIR__ . '/classes/services/synthesis_engine.php');

    echo "<p>Loading synthesis_engine...</p>";
    flush();

    $engine = new \local_customerintel\services\synthesis_engine($runid);

    echo "<p>Calling build_report() with force_regenerate=true...</p>";
    flush();

    // Call build_report - this should trigger M1T5-8 pipeline
    $result = $engine->build_report(
        $run->companyid,
        $run->targetcompanyid,
        true  // force_regenerate = true
    );

    $duration = microtime(true) - $start;

    echo "<p class='success'>✅ build_report() completed in " . round($duration, 2) . " seconds</p>";

    // Check what was returned
    if (is_array($result)) {
        echo "<p>Result type: Array</p>";
        if (isset($result['synthesis_id'])) {
            $synthesis_id = $result['synthesis_id'];
            echo "<p class='success'>✅ Synthesis ID returned: {$synthesis_id}</p>";
        }
    } else if (is_object($result)) {
        echo "<p>Result type: Object</p>";
        if (isset($result->id)) {
            $synthesis_id = $result->id;
            echo "<p class='success'>✅ Synthesis ID: {$synthesis_id}</p>";
        }
    } else if (is_int($result)) {
        $synthesis_id = $result;
        echo "<p class='success'>✅ Synthesis ID: {$synthesis_id}</p>";
    }

    if ($duration < 5) {
        echo "<p class='warning'>⚠️ Very fast completion - likely used cache or shortcuts</p>";
    } else if ($duration >= 30) {
        echo "<p class='success'>✅ Duration indicates substantial processing</p>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Synthesis generation failed</p>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";

// =============================================================================
// VERIFICATION
// =============================================================================
echo "<div class='stage'>";
echo "<h3>Verification</h3>";

try {
    // Check database for synthesis
    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    if (!$synthesis) {
        echo "<p class='fail'>❌ No synthesis record found in database for Run {$runid}</p>";

        // Check if synthesis was saved to a different run
        $other_synthesis = $DB->get_records_sql(
            "SELECT s.*, r.companyid, r.targetcompanyid
             FROM {local_ci_synthesis} s
             JOIN {local_ci_run} r ON s.runid = r.id
             WHERE r.companyid = ? AND r.targetcompanyid = ?
             ORDER BY s.createdat DESC LIMIT 3",
            [$run->companyid, $run->targetcompanyid]
        );

        if ($other_synthesis) {
            echo "<p class='warning'>⚠️ Synthesis found for other runs with same companies:</p>";
            echo "<ul>";
            foreach ($other_synthesis as $syn) {
                echo "<li>Run {$syn->runid} - created " . date('Y-m-d H:i:s', $syn->createdat) . "</li>";
            }
            echo "</ul>";
            echo "<p class='fail'>🚨 Synthesis caching redirected to a different run!</p>";
        } else {
            echo "<p class='fail'>❌ No synthesis exists for this company pair at all</p>";
            echo "<p>This indicates synthesis_engine may have an error preventing save.</p>";
        }

        echo "</div></div>";
        echo $OUTPUT->footer();
        exit;
    }

    echo "<p class='success'>✅ Synthesis record found!</p>";
    echo "<div class='metric'>Synthesis ID: <strong>{$synthesis->id}</strong></div>";
    echo "<div class='metric'>Created: <strong>" . date('Y-m-d H:i:s', $synthesis->createdat) . "</strong></div>";

    // Check content sizes
    $html_size = strlen($synthesis->htmlcontent ?? '');
    $json_size = strlen($synthesis->jsoncontent ?? '');

    echo "<div class='metric'>HTML Size: <strong>" . number_format($html_size) . "</strong> bytes</div>";
    echo "<div class='metric'>JSON Size: <strong>" . number_format($json_size) . "</strong> bytes</div>";

    if ($html_size < 1000) {
        echo "<p class='fail'>❌ Very small content ({$html_size} bytes) - synthesis may be empty</p>";
    } else if ($html_size < 10000) {
        echo "<p class='warning'>⚠️ Small content ({$html_size} bytes) - may be incomplete</p>";
    } else {
        echo "<p class='success'>✅ Substantial content generated ({$html_size} bytes)</p>";
    }

    // Check M1T3 metadata
    echo "<h4>M1T3 Metadata</h4>";
    if (!empty($synthesis->source_company_id)) {
        echo "<div class='metric'>Source Company ID: <strong>{$synthesis->source_company_id}</strong></div>";
        echo "<div class='metric'>Target Company ID: <strong>{$synthesis->target_company_id}</strong></div>";
        echo "<p class='success'>✅ M1T3 metadata present</p>";
    } else {
        echo "<p class='warning'>⚠️ M1T3 metadata missing</p>";
    }

    // Check sections
    echo "<h4>Sections</h4>";
    $sections = $DB->get_records('local_ci_synthesis_section', ['synthesisid' => $synthesis->id], 'sectioncode ASC');
    $section_count = count($sections);

    echo "<div class='metric'>Sections: <strong>{$section_count}</strong></div>";

    if ($section_count >= 9) {
        echo "<p class='success'>✅ All expected sections created</p>";
    } else if ($section_count > 0) {
        echo "<p class='warning'>⚠️ Only {$section_count} sections (expected 9)</p>";
    } else {
        echo "<p class='fail'>❌ No sections created</p>";
    }

    if ($section_count > 0) {
        echo "<p>Sections created:</p>";
        echo "<ul>";
        foreach ($sections as $section) {
            $size = strlen($section->htmlcontent ?? '');
            echo "<li>{$section->sectioncode} ({$size} bytes)</li>";
        }
        echo "</ul>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Verification error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";

// =============================================================================
// FINAL RESULT
// =============================================================================
echo "<div class='stage' style='border-left-color: #28a745; background: #d4edda;'>";
echo "<h3>🎉 Final Result</h3>";

if ($synthesis && $html_size > 1000) {
    echo "<p class='success' style='font-size: 20px;'>✅ SUCCESS! Synthesis generated for Run {$runid}!</p>";
    echo "<p><a href='view_report.php?runid={$runid}' class='big-button'>📊 View Full Report</a></p>";
    echo "<p><a href='verify_full_pipeline.php?runid={$runid}' class='big-button' style='background: #007bff;'>🔍 Verify Complete Pipeline</a></p>";

    // Summary
    echo "<h4>Summary</h4>";
    echo "<ul>";
    echo "<li>NBs Used: {$nb_count}/15</li>";
    echo "<li>Synthesis Duration: " . round($duration, 2) . " seconds</li>";
    echo "<li>HTML Content: " . number_format($html_size) . " bytes</li>";
    echo "<li>Sections: {$section_count}</li>";
    echo "<li>M1T3 Metadata: " . (!empty($synthesis->source_company_id) ? "✅ Present" : "❌ Missing") . "</li>";
    echo "</ul>";

} else if ($synthesis) {
    echo "<p class='warning' style='font-size: 18px;'>⚠️ Synthesis created but content is minimal</p>";
    echo "<p><a href='view_report.php?runid={$runid}' class='big-button' style='background: #ffc107;'>📊 View Report (Partial)</a></p>";
} else {
    echo "<p class='fail' style='font-size: 18px;'>❌ Synthesis generation failed</p>";
    echo "<p>Possible causes:</p>";
    echo "<ul>";
    echo "<li>Synthesis caching mechanism redirected to different run</li>";
    echo "<li>synthesis_engine has a bug preventing save</li>";
    echo "<li>M1T5-8 pipeline threw an exception during generation</li>";
    echo "</ul>";
    echo "<p>Check the error messages above for details.</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
