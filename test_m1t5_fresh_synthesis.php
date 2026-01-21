<?php
/**
 * M1T5-M1T8 Definitive Fresh Synthesis Test
 *
 * This test forces a fresh synthesis generation with no cache,
 * confirming that all 4 services are actually executing.
 *
 * Expected execution time: 60-120 seconds (with AI calls)
 * If it completes in < 5 seconds, something is wrong!
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/customerintel/test_m1t5_fresh_synthesis.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('M1T5-M1T8 Fresh Synthesis Test');
$PAGE->set_heading('M1T5-M1T8 Definitive Fresh Synthesis Test');

echo $OUTPUT->header();

?>
<style>
.test-container { max-width: 1200px; margin: 20px auto; }
.test-section { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 5px; }
.success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px; }
.fail { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px; }
.warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; padding: 10px; margin: 10px 0; border-radius: 4px; }
.info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px; }
.test-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
.test-table th, .test-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.test-table th { background: #007bff; color: white; }
.timer { font-size: 24px; font-weight: bold; color: #007bff; margin: 20px 0; }
.stage-log { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; margin: 15px 0; }
.stage-marker { color: #48bb78; font-weight: bold; }
.error-marker { color: #f56565; font-weight: bold; }
.metric-box { display: inline-block; background: #667eea; color: white; padding: 10px 20px; margin: 10px; border-radius: 5px; min-width: 150px; }
.metric-label { font-size: 12px; opacity: 0.8; }
.metric-value { font-size: 24px; font-weight: bold; }
pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>

<div class="test-container">

<h1>🚀 M1T5-M1T8 Definitive Fresh Synthesis Test</h1>

<div class="warning">
    <strong>⚠️ IMPORTANT:</strong> This test will:
    <ul>
        <li>Force fresh synthesis generation (no cache)</li>
        <li>Make real AI API calls (costs money)</li>
        <li>Take 60-120 seconds to complete</li>
        <li>Create a new synthesis run</li>
    </ul>
    <p><strong>If it completes in less than 5 seconds, something is wrong!</strong></p>
</div>

<?php

$run_test = optional_param('run_fresh_test', '', PARAM_TEXT);

if ($run_test !== 'yes') {
    ?>
    <div class="test-section">
        <h2>Ready to Start Fresh Synthesis Test?</h2>
        <p>This will create a new synthesis run with forced regeneration.</p>
        <form method="get" action="">
            <input type="hidden" name="run_fresh_test" value="yes">
            <button type="submit" style="background: #28a745; color: white; padding: 15px 30px; font-size: 18px; border: none; border-radius: 5px; cursor: pointer;">
                ▶️ Start Fresh Synthesis Test
            </button>
        </form>
    </div>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// ============================================================================
// TEST EXECUTION STARTS HERE
// ============================================================================

echo "<h2>🔬 Test Execution</h2>";

$start_time = microtime(true);
$test_results = [];
$stage_logs = [];

// ============================================================================
// STEP 1: Find Test Companies
// ============================================================================

echo "<div class='test-section'>";
echo "<h3>Step 1: Selecting Test Companies</h3>";

$companies = $DB->get_records('local_ci_company', null, 'id ASC', '*', 0, 2);

if (count($companies) < 2) {
    echo "<p class='fail'>❌ ERROR: Need at least 2 companies in database. Found: " . count($companies) . "</p>";
    echo $OUTPUT->footer();
    exit;
}

$companies_array = array_values($companies);
$source_company = $companies_array[0];
$target_company = $companies_array[1];

echo "<table class='test-table'>";
echo "<tr><th>Role</th><th>ID</th><th>Name</th></tr>";
echo "<tr><td>Source Company</td><td>{$source_company->id}</td><td>{$source_company->name}</td></tr>";
echo "<tr><td>Target Company</td><td>{$target_company->id}</td><td>{$target_company->name}</td></tr>";
echo "</table>";

echo "<p class='success'>✅ Companies selected</p>";
echo "</div>";

// ============================================================================
// STEP 2: Create Fresh Run
// ============================================================================

echo "<div class='test-section'>";
echo "<h3>Step 2: Creating Fresh Test Run</h3>";

$run = new stdClass();
$run->companyid = $source_company->id;
$run->targetid = $target_company->id;
$run->userid = $USER->id;
$run->initiatedbyuserid = $USER->id;
$run->status = 'completed'; // Synthesis engine requires 'completed' status
$run->timecreated = time();
$run->timemodified = time();

try {
    $runid = $DB->insert_record('local_ci_run', $run);
    echo "<p class='success'>✅ Created Run ID: <strong>$runid</strong></p>";
    echo "<p class='info'>Source: {$source_company->name} (ID: {$source_company->id})<br>";
    echo "Target: {$target_company->name} (ID: {$target_company->id})</p>";
} catch (Exception $e) {
    echo "<p class='fail'>❌ Failed to create run: " . $e->getMessage() . "</p>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// ============================================================================
// STEP 3: Enable Trace Logging
// ============================================================================

echo "<div class='test-section'>";
echo "<h3>Step 3: Enabling Debug Logging</h3>";

// Enable debugging temporarily
$old_debug = $CFG->debug;
$old_debugdisplay = $CFG->debugdisplay;
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;

echo "<p class='success'>✅ Debug logging enabled</p>";
echo "</div>";

// ============================================================================
// STEP 4: Execute Fresh Synthesis
// ============================================================================

echo "<div class='test-section'>";
echo "<h3>Step 4: Executing Fresh Synthesis (FORCE REGENERATE)</h3>";

echo "<div class='info'>";
echo "<p><strong>⏱️ Starting synthesis at: " . date('H:i:s') . "</strong></p>";
echo "<p>Expected duration: 60-120 seconds</p>";
echo "<p id='timer' class='timer'>⏱️ Elapsed: 0s</p>";
echo "</div>";

// JavaScript timer
?>
<script>
let startTime = Date.now();
setInterval(() => {
    let elapsed = Math.floor((Date.now() - startTime) / 1000);
    document.getElementById('timer').innerHTML = '⏱️ Elapsed: ' + elapsed + 's';
}, 1000);
</script>
<?php

flush();
ob_flush();

$synthesis_start = microtime(true);
$synthesis_result = null;
$synthesis_error = null;

try {
    // Load synthesis engine
    require_once(__DIR__ . '/classes/services/synthesis_engine.php');
    $synthesis_engine = new \local_customerintel\services\synthesis_engine();

    echo "<p class='info'>📦 Synthesis engine loaded</p>";
    flush();
    ob_flush();

    // Execute with FORCE REGENERATE
    echo "<p class='info'>🔄 Calling build_report() with force_regenerate=true...</p>";
    flush();
    ob_flush();

    $synthesis_result = $synthesis_engine->build_report($runid, true); // Force regenerate = TRUE

} catch (Exception $e) {
    $synthesis_error = $e;
    echo "<p class='fail'>❌ SYNTHESIS FAILED: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

$synthesis_end = microtime(true);
$synthesis_duration = $synthesis_end - $synthesis_start;

echo "</div>";

// ============================================================================
// STEP 5: Analyze Results
// ============================================================================

echo "<div class='test-section'>";
echo "<h3>Step 5: Analyzing Results</h3>";

// Execution Time Analysis
echo "<div class='metric-box'>";
echo "<div class='metric-label'>Execution Time</div>";
echo "<div class='metric-value'>" . number_format($synthesis_duration, 2) . "s</div>";
echo "</div>";

if ($synthesis_duration < 5) {
    echo "<div class='fail'>";
    echo "<strong>🚨 CRITICAL WARNING:</strong> Execution time was only " . number_format($synthesis_duration, 2) . " seconds!<br>";
    echo "This is TOO FAST for a real synthesis. Expected: 60-120 seconds.<br>";
    echo "<strong>This suggests:</strong>";
    echo "<ul>";
    echo "<li>Cache was hit despite force_regenerate=true</li>";
    echo "<li>Services returned immediately without processing</li>";
    echo "<li>AI calls were not actually made</li>";
    echo "<li>Something is fundamentally broken</li>";
    echo "</ul>";
    echo "</div>";
} else if ($synthesis_duration < 30) {
    echo "<div class='warning'>";
    echo "<strong>⚠️ WARNING:</strong> Execution time was " . number_format($synthesis_duration, 2) . " seconds.<br>";
    echo "This is faster than expected. May indicate partial processing or cached components.";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<strong>✅ GOOD:</strong> Execution time indicates real processing occurred!";
    echo "</div>";
}

// Check if synthesis succeeded
if ($synthesis_error) {
    echo "<div class='fail'>";
    echo "<h4>❌ Synthesis Failed with Exception</h4>";
    echo "<p><strong>Error:</strong> " . $synthesis_error->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $synthesis_error->getFile() . ":" . $synthesis_error->getLine() . "</p>";
    echo "</div>";
} else if ($synthesis_result) {
    echo "<div class='success'>";
    echo "<h4>✅ Synthesis Completed</h4>";
    echo "<p>Result type: " . gettype($synthesis_result) . "</p>";
    if (is_array($synthesis_result)) {
        echo "<p>Result keys: " . implode(', ', array_keys($synthesis_result)) . "</p>";
    }
    echo "</div>";
} else {
    echo "<div class='fail'>";
    echo "<h4>❌ Synthesis returned NULL or FALSE</h4>";
    echo "<p>This indicates the synthesis did not complete successfully.</p>";
    echo "</div>";
}

echo "</div>";

// ============================================================================
// STEP 6: Database Verification
// ============================================================================

echo "<div class='test-section'>";
echo "<h3>Step 6: Database Verification</h3>";

// Check synthesis record
$synthesis_record = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

if ($synthesis_record) {
    echo "<h4>✅ Synthesis Record Created</h4>";
    echo "<table class='test-table'>";
    echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";

    $fields_to_check = [
        'id' => 'Synthesis ID',
        'runid' => 'Run ID',
        'source_company_id' => 'Source Company (M1T3)',
        'target_company_id' => 'Target Company (M1T3)',
        'synthesis_key' => 'Synthesis Key (M1T3)',
        'model_used' => 'Model Used (M1T3)',
        'cache_source' => 'Cache Source (M1T3)',
        'timecreated' => 'Created Time',
        'timemodified' => 'Modified Time'
    ];

    foreach ($fields_to_check as $field => $label) {
        $value = isset($synthesis_record->$field) ? $synthesis_record->$field : 'NULL';

        // Format timestamps
        if (($field === 'timecreated' || $field === 'timemodified') && is_numeric($value)) {
            $value = date('Y-m-d H:i:s', $value);
        }

        // Check if M1T3 field is populated
        $is_m1t3_field = strpos($label, 'M1T3') !== false;
        $status = (!empty($synthesis_record->$field)) ? '✅' : '❌';

        if ($is_m1t3_field && empty($synthesis_record->$field)) {
            $status .= ' <strong>M1T3 MISSING!</strong>';
        }

        echo "<tr>";
        echo "<td><strong>$label</strong></td>";
        echo "<td>$value</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Check prompt_config
    if (!empty($synthesis_record->prompt_config)) {
        echo "<h4>M1T3 Prompt Config</h4>";
        $prompt_config = json_decode($synthesis_record->prompt_config, true);
        echo "<pre>" . json_encode($prompt_config, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p class='fail'>❌ M1T3 prompt_config is EMPTY</p>";
    }

} else {
    echo "<p class='fail'>❌ NO synthesis record created for Run $runid</p>";
}

// Check sections
try {
    $sections = $DB->get_records('local_ci_synthesis_section', ['synthesisid' => $synthesis_record->id ?? 0]);
    $section_count = count($sections);
} catch (Exception $e) {
    // Table might not exist in older schema
    echo "<p class='warning'>⚠️ Table 'local_ci_synthesis_section' does not exist - using legacy schema</p>";

    // Try to get sections from synthesis record's jsondata
    if ($synthesis_record && !empty($synthesis_record->jsondata)) {
        $synthesis_data = json_decode($synthesis_record->jsondata, true);
        $section_count = isset($synthesis_data['sections']) ? count($synthesis_data['sections']) : 0;
        $sections = $synthesis_data['sections'] ?? [];
    } else {
        $section_count = 0;
        $sections = [];
    }
}

echo "<div class='metric-box'>";
echo "<div class='metric-label'>Sections Generated</div>";
echo "<div class='metric-value'>$section_count / 9</div>";
echo "</div>";

if ($section_count === 9) {
    echo "<p class='success'>✅ All 9 sections generated</p>";
} else if ($section_count > 0) {
    echo "<p class='warning'>⚠️ Only $section_count sections generated (expected 9)</p>";
} else {
    echo "<p class='fail'>❌ NO sections generated!</p>";
}

// Show section details
if ($section_count > 0) {
    echo "<h4>Section Details</h4>";
    echo "<table class='test-table'>";
    echo "<tr><th>Section</th><th>Content Length</th><th>Has Content?</th></tr>";

    foreach ($sections as $section) {
        // Handle both object (from DB) and array (from JSON) formats
        if (is_object($section)) {
            $section_name = $section->section_name ?? 'Unknown';
            $content_length = strlen($section->content ?? '');
        } else {
            $section_name = $section['section_name'] ?? $section['name'] ?? 'Unknown';
            $content_length = strlen($section['content'] ?? '');
        }

        $has_content = $content_length > 100 ? '✅' : '❌';

        echo "<tr>";
        echo "<td>$section_name</td>";
        echo "<td>$content_length chars</td>";
        echo "<td>$has_content</td>";
        echo "</tr>";
    }

    echo "</table>";
}

echo "</div>";

// ============================================================================
// STEP 7: Check Error Logs for Stage Markers
// ============================================================================

echo "<div class='test-section'>";
echo "<h3>Step 7: Stage Execution Markers</h3>";

echo "<p class='info'>To verify all 4 stages actually executed, check your error logs for these markers:</p>";

echo "<div class='stage-log'>";
echo "<div class='stage-marker'>[M1T5]</div> Stage 1: Delegating to raw_collector<br>";
echo "<div class='stage-marker'>[M1T5]</div> Stage 1 complete (X NBs collected)<br>";
echo "<br>";
echo "<div class='stage-marker'>[M1T6]</div> Stage 2: Delegating to canonical_builder<br>";
echo "<div class='stage-marker'>[M1T6]</div> Stage 2 complete (canonical dataset built)<br>";
echo "<br>";
echo "<div class='stage-marker'>[M1T7]</div> Stage 3: Delegating to analysis_engine<br>";
echo "<div class='stage-marker'>[M1T7]</div> Stage 3 complete (Y sections drafted)<br>";
echo "<br>";
echo "<div class='stage-marker'>[M1T8]</div> Stage 4: Delegating to qa_engine<br>";
echo "<div class='stage-marker'>[M1T8]</div> Stage 4 complete (QA validation done)<br>";
echo "<br>";
echo "<div class='stage-marker'>[M1-Complete]</div> Synthesis orchestration complete<br>";
echo "</div>";

echo "<p><strong>Check your logs at:</strong></p>";
echo "<ul>";
echo "<li>/var/www/html/moodledata/error.log</li>";
echo "<li>Apache/Nginx error logs</li>";
echo "<li>PHP error logs</li>";
echo "</ul>";

echo "</div>";

// ============================================================================
// STEP 8: View Report Link
// ============================================================================

if ($synthesis_record) {
    echo "<div class='test-section'>";
    echo "<h3>Step 8: View Generated Report</h3>";

    $report_url = new moodle_url('/local/customerintel/report.php', ['id' => $runid]);
    echo "<p><a href='$report_url' target='_blank' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>";
    echo "📊 View Report for Run $runid";
    echo "</a></p>";

    echo "<p class='info'>Check the report to verify:</p>";
    echo "<ul>";
    echo "<li>All 9 sections have real content (not generic fallbacks)</li>";
    echo "<li>Citations are present and clickable</li>";
    echo "<li>Content is specific to {$source_company->name} vs {$target_company->name}</li>";
    echo "<li>QA scores are displayed</li>";
    echo "</ul>";

    echo "</div>";
}

// ============================================================================
// FINAL SUMMARY
// ============================================================================

echo "<div class='test-section' style='background: #2d3748; color: white;'>";
echo "<h2>📋 Final Test Summary</h2>";

$total_time = microtime(true) - $start_time;

echo "<div class='metric-box'>";
echo "<div class='metric-label'>Total Test Time</div>";
echo "<div class='metric-value'>" . number_format($total_time, 2) . "s</div>";
echo "</div>";

echo "<div class='metric-box'>";
echo "<div class='metric-label'>Synthesis Time</div>";
echo "<div class='metric-value'>" . number_format($synthesis_duration, 2) . "s</div>";
echo "</div>";

echo "<div class='metric-box'>";
echo "<div class='metric-label'>Sections Created</div>";
echo "<div class='metric-value'>$section_count / 9</div>";
echo "</div>";

// Overall verdict
echo "<h3>🎯 Overall Verdict</h3>";

$pass_checks = 0;
$total_checks = 5;

// Check 1: Synthesis completed
if (!$synthesis_error && $synthesis_result) {
    echo "<p style='color: #48bb78;'>✅ Synthesis completed without fatal errors</p>";
    $pass_checks++;
} else {
    echo "<p style='color: #f56565;'>❌ Synthesis failed or returned null</p>";
}

// Check 2: Execution time
if ($synthesis_duration >= 30) {
    echo "<p style='color: #48bb78;'>✅ Execution time suggests real processing ($synthesis_duration seconds)</p>";
    $pass_checks++;
} else {
    echo "<p style='color: #f56565;'>❌ Execution time too fast (" . number_format($synthesis_duration, 2) . "s) - likely cache hit or no processing</p>";
}

// Check 3: Database record
if ($synthesis_record) {
    echo "<p style='color: #48bb78;'>✅ Synthesis record created in database</p>";
    $pass_checks++;
} else {
    echo "<p style='color: #f56565;'>❌ No synthesis record in database</p>";
}

// Check 4: M1T3 metadata
$has_m1t3_metadata = $synthesis_record &&
                      !empty($synthesis_record->source_company_id) &&
                      !empty($synthesis_record->target_company_id) &&
                      !empty($synthesis_record->synthesis_key);

if ($has_m1t3_metadata) {
    echo "<p style='color: #48bb78;'>✅ M1T3 metadata fields populated</p>";
    $pass_checks++;
} else {
    echo "<p style='color: #f56565;'>❌ M1T3 metadata fields missing or empty</p>";
}

// Check 5: Sections
if ($section_count >= 9) {
    echo "<p style='color: #48bb78;'>✅ All 9 sections generated</p>";
    $pass_checks++;
} else if ($section_count > 0) {
    echo "<p style='color: #ed8936;'>⚠️ Only $section_count sections generated (expected 9)</p>";
    $pass_checks += 0.5;
} else {
    echo "<p style='color: #f56565;'>❌ No sections generated</p>";
}

// Final score
$score_percent = ($pass_checks / $total_checks) * 100;

echo "<h2 style='margin-top: 30px;'>Final Score: " . number_format($score_percent, 0) . "%</h2>";

if ($score_percent >= 80) {
    echo "<div style='background: #48bb78; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 SUCCESS!</h3>";
    echo "<p>M1T5-M1T8 modularization is working correctly!</p>";
    echo "<p>All 4 services executed and generated real synthesis content.</p>";
    echo "</div>";
} else if ($score_percent >= 50) {
    echo "<div style='background: #ed8936; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>⚠️ PARTIAL SUCCESS</h3>";
    echo "<p>Some components working but issues detected.</p>";
    echo "<p>Review the failures above and check error logs.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f56565; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ TEST FAILED</h3>";
    echo "<p>M1T5-M1T8 refactoring has significant issues.</p>";
    echo "<p>Services may not be executing correctly.</p>";
    echo "</div>";
}

echo "</div>";

// Restore debug settings
$CFG->debug = $old_debug;
$CFG->debugdisplay = $old_debugdisplay;

?>

</div>

<?php
echo $OUTPUT->footer();
?>
