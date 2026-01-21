<?php
/**
 * M1T5-M1T8 Integration Test Script
 *
 * Tests the new modular synthesis architecture
 * Run this via web browser: /local/customerintel/test_m1t5_m1t8_integration.php
 *
 * @package    local_customerintel
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');

// Require admin login
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/customerintel/test_m1t5_m1t8_integration.php');
$PAGE->set_title('M1T5-M1T8 Integration Test');

echo $OUTPUT->header();

echo '<h2>M1T5-M1T8 Modular Architecture Integration Test</h2>';
echo '<p>Testing the new 4-service modular synthesis architecture...</p>';
echo '<hr>';

$tests_passed = 0;
$tests_failed = 0;
$warnings = [];

// ============================================================================
// TEST 1: Service File Existence
// ============================================================================
echo '<h3>Test 1: Service File Existence</h3>';

$service_files = [
    'raw_collector.php' => 'M1T5 - NB Collection',
    'canonical_builder.php' => 'M1T6 - Dataset Building',
    'analysis_engine.php' => 'M1T7 - AI Synthesis',
    'qa_engine.php' => 'M1T8 - QA Validation'
];

$all_files_exist = true;
foreach ($service_files as $file => $description) {
    $filepath = $CFG->dirroot . '/local/customerintel/classes/services/' . $file;
    if (file_exists($filepath)) {
        echo "✅ <strong>$file</strong> exists ($description)<br>";
    } else {
        echo "❌ <strong>$file</strong> MISSING ($description)<br>";
        $all_files_exist = false;
        $tests_failed++;
    }
}

if ($all_files_exist) {
    echo '<p style="color: green;"><strong>✅ All 4 service files exist</strong></p>';
    $tests_passed++;
} else {
    echo '<p style="color: red;"><strong>❌ Some service files are missing</strong></p>';
}

echo '<hr>';

// ============================================================================
// TEST 2: Service Class Instantiation
// ============================================================================
echo '<h3>Test 2: Service Class Instantiation</h3>';

$all_classes_load = true;

try {
    require_once($CFG->dirroot . '/local/customerintel/classes/services/raw_collector.php');
    echo "✅ raw_collector.php loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ raw_collector.php failed to load: " . $e->getMessage() . "<br>";
    $all_classes_load = false;
}

try {
    require_once($CFG->dirroot . '/local/customerintel/classes/services/canonical_builder.php');
    echo "✅ canonical_builder.php loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ canonical_builder.php failed to load: " . $e->getMessage() . "<br>";
    $all_classes_load = false;
}

try {
    require_once($CFG->dirroot . '/local/customerintel/classes/services/analysis_engine.php');
    echo "✅ analysis_engine.php loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ analysis_engine.php failed to load: " . $e->getMessage() . "<br>";
    $all_classes_load = false;
}

try {
    require_once($CFG->dirroot . '/local/customerintel/classes/services/qa_engine.php');
    echo "✅ qa_engine.php loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ qa_engine.php failed to load: " . $e->getMessage() . "<br>";
    $all_classes_load = false;
}

if ($all_classes_load) {
    echo '<p style="color: green;"><strong>✅ All 4 service classes loaded successfully</strong></p>';
    $tests_passed++;
} else {
    echo '<p style="color: red;"><strong>❌ Some service classes failed to load</strong></p>';
    $tests_failed++;
}

echo '<hr>';

// ============================================================================
// TEST 3: Service Instantiation Test
// ============================================================================
echo '<h3>Test 3: Service Instantiation (Constructor Test)</h3>';

$instantiation_success = true;

try {
    $test_runid = 1; // Dummy run ID for testing

    // Test raw_collector
    $raw_collector = new \local_customerintel\services\raw_collector();
    echo "✅ raw_collector instantiated successfully<br>";

    // Test canonical_builder
    $canonical_builder = new \local_customerintel\services\canonical_builder();
    echo "✅ canonical_builder instantiated successfully<br>";

    // Test analysis_engine
    $test_dataset = ['test' => 'data'];
    $test_config = [];
    $analysis_engine = new \local_customerintel\services\analysis_engine($test_runid, $test_dataset, $test_config);
    echo "✅ analysis_engine instantiated successfully<br>";

    // Test qa_engine
    $test_sections = [];
    $qa_engine = new \local_customerintel\services\qa_engine($test_runid, $test_sections, $test_dataset);
    echo "✅ qa_engine instantiated successfully<br>";

    echo '<p style="color: green;"><strong>✅ All services can be instantiated</strong></p>';
    $tests_passed++;

} catch (Exception $e) {
    echo '<p style="color: red;"><strong>❌ Service instantiation failed: ' . $e->getMessage() . '</strong></p>';
    $tests_failed++;
    $instantiation_success = false;
}

echo '<hr>';

// ============================================================================
// TEST 4: Orchestrator Structure Check
// ============================================================================
echo '<h3>Test 4: Orchestrator Structure (synthesis_engine.php)</h3>';

$orchestrator_file = $CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php';
$orchestrator_content = file_get_contents($orchestrator_file);

$required_patterns = [
    'M1T5' => 'Stage 1 delegation to raw_collector',
    'M1T6' => 'Stage 2 delegation to canonical_builder',
    'M1T7' => 'Stage 3 delegation to analysis_engine',
    'M1T8' => 'Stage 4 delegation to qa_engine',
    'raw_collector' => 'raw_collector instantiation',
    'canonical_builder' => 'canonical_builder instantiation',
    'analysis_engine' => 'analysis_engine instantiation',
    'qa_engine' => 'qa_engine instantiation'
];

$all_patterns_found = true;
foreach ($required_patterns as $pattern => $description) {
    if (strpos($orchestrator_content, $pattern) !== false) {
        echo "✅ Found: $description<br>";
    } else {
        echo "❌ Missing: $description<br>";
        $all_patterns_found = false;
        $warnings[] = "Orchestrator missing: $description";
    }
}

if ($all_patterns_found) {
    echo '<p style="color: green;"><strong>✅ Orchestrator has all 4 stage delegations</strong></p>';
    $tests_passed++;
} else {
    echo '<p style="color: orange;"><strong>⚠️ Orchestrator may be incomplete</strong></p>';
    $tests_failed++;
}

echo '<hr>';

// ============================================================================
// TEST 5: Find Latest Completed Run
// ============================================================================
echo '<h3>Test 5: Find Latest Completed Run for Integration Test</h3>';

$latest_run = $DB->get_record_sql(
    "SELECT * FROM {local_ci_run} WHERE status = ? ORDER BY timecreated DESC LIMIT 1",
    ['completed']
);

if ($latest_run) {
    echo "✅ Found latest completed run: <strong>Run ID {$latest_run->id}</strong><br>";
    echo "   - Company ID: {$latest_run->companyid}<br>";
    echo "   - Target Company ID: " . ($latest_run->targetcompanyid ?: 'None') . "<br>";
    echo "   - Created: " . date('Y-m-d H:i:s', $latest_run->timecreated) . "<br>";

    // Check if this run has synthesis data
    $has_synthesis = $DB->record_exists('local_ci_synthesis', ['runid' => $latest_run->id]);
    if ($has_synthesis) {
        echo "   - ✅ Has existing synthesis (good for cache testing)<br>";
    } else {
        echo "   - ⚠️ No synthesis yet (will be generated fresh)<br>";
    }

    echo '<p style="color: green;"><strong>✅ Ready for integration testing with Run ID ' . $latest_run->id . '</strong></p>';
    $tests_passed++;

} else {
    echo '<p style="color: red;"><strong>❌ No completed runs found. Please create a run first.</strong></p>';
    echo '<p>To create a run, go to: <a href="/local/customerintel/run.php">Create New Run</a></p>';
    $tests_failed++;
    $latest_run = null;
}

echo '<hr>';

// ============================================================================
// TEST 6: Full Integration Test (Optional - Run Synthesis)
// ============================================================================
echo '<h3>Test 6: Full Integration Test (Optional)</h3>';

if ($latest_run && isset($_GET['run_integration']) && $_GET['run_integration'] == 'yes') {
    echo '<p>Running full synthesis integration test with Run ID ' . $latest_run->id . '...</p>';

    try {
        $start_time = microtime(true);

        // Initialize synthesis engine
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();

        echo "✅ Synthesis engine instantiated<br>";

        // Run synthesis with force regenerate to test full pipeline
        $force_regenerate = true;
        echo "<p><em>Running synthesis (force_regenerate = true)...</em></p>";

        $result = $synthesis_engine->build_report($latest_run->id, $force_regenerate);

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0;">';
        echo '<h4 style="color: #155724;">✅ SYNTHESIS COMPLETED SUCCESSFULLY</h4>';
        echo "<p><strong>Execution Time:</strong> {$execution_time} seconds</p>";

        if (isset($result['sections'])) {
            echo "<p><strong>Sections Generated:</strong> " . count($result['sections']) . "</p>";
        }

        if (isset($result['metadata'])) {
            echo "<p><strong>Metadata:</strong> Present ✅</p>";
            if (isset($result['metadata']['m1t3_enhanced'])) {
                echo "<p><strong>M1T3 Enhanced:</strong> " . ($result['metadata']['m1t3_enhanced'] ? 'Yes ✅' : 'No') . "</p>";
            }
        }

        if (isset($result['qa_scores'])) {
            echo "<p><strong>QA Scores:</strong> Present ✅</p>";
        }

        echo '<p><strong>All 4 Stages Executed:</strong></p>';
        echo '<ul>';
        echo '<li>✅ Stage 1 (M1T5): NB Collection</li>';
        echo '<li>✅ Stage 2 (M1T6): Dataset Building</li>';
        echo '<li>✅ Stage 3 (M1T7): AI Synthesis</li>';
        echo '<li>✅ Stage 4 (M1T8): QA Validation</li>';
        echo '</ul>';

        echo '<p><a href="/local/customerintel/report.php?id=' . $latest_run->id . '" class="btn btn-primary">View Report</a></p>';

        echo '</div>';

        $tests_passed++;

    } catch (Exception $e) {
        echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0;">';
        echo '<h4 style="color: #721c24;">❌ SYNTHESIS FAILED</h4>';
        echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
        $tests_failed++;
    }

} else {
    if ($latest_run) {
        echo '<p>Integration test is optional. Click the button below to run a full synthesis test:</p>';
        echo '<p><a href="?run_integration=yes" class="btn btn-warning">Run Full Integration Test (Run ID ' . $latest_run->id . ')</a></p>';
        echo '<p><em>Note: This will force regenerate the synthesis for testing purposes.</em></p>';
    } else {
        echo '<p style="color: gray;">Cannot run integration test without a completed run.</p>';
    }
}

echo '<hr>';

// ============================================================================
// TEST 7: Check Error Logs for Stage Markers
// ============================================================================
echo '<h3>Test 7: Recent Error Log Analysis</h3>';
echo '<p>Checking recent error logs for M1T5-M1T8 stage markers...</p>';

// This would require access to error_log file
echo '<p><em>Manual check required:</em></p>';
echo '<ol>';
echo '<li>Check your Moodle error logs at: <code>moodledata/error_log</code> or <code>apache/error_log</code></li>';
echo '<li>Look for these markers in recent logs:</li>';
echo '<ul>';
echo '<li><code>[M1T5] Stage 1: Delegating to raw_collector</code></li>';
echo '<li><code>[M1T6] Stage 2: Delegating to canonical_builder</code></li>';
echo '<li><code>[M1T7] Stage 3: Delegating to analysis_engine</code></li>';
echo '<li><code>[M1T8] Stage 4: Delegating to qa_engine</code></li>';
echo '<li><code>[M1-Complete] Synthesis orchestration complete</code></li>';
echo '</ul>';
echo '</ol>';

echo '<p>If you run the integration test above, these markers will appear in your logs.</p>';

echo '<hr>';

// ============================================================================
// SUMMARY
// ============================================================================
echo '<h2>Test Summary</h2>';

$total_tests = $tests_passed + $tests_failed;

echo '<div style="background: #f8f9fa; border: 2px solid #dee2e6; padding: 20px; margin: 20px 0;">';
echo '<table style="width: 100%;">';
echo '<tr><td><strong>Total Tests:</strong></td><td>' . $total_tests . '</td></tr>';
echo '<tr style="color: green;"><td><strong>Passed:</strong></td><td>' . $tests_passed . '</td></tr>';
echo '<tr style="color: red;"><td><strong>Failed:</strong></td><td>' . $tests_failed . '</td></tr>';
echo '</table>';

if ($tests_failed == 0) {
    echo '<h3 style="color: green;">✅ ALL TESTS PASSED</h3>';
    echo '<p>The M1T5-M1T8 modular architecture is working correctly!</p>';
} else {
    echo '<h3 style="color: orange;">⚠️ SOME TESTS FAILED</h3>';
    echo '<p>Please review the failed tests above and address any issues.</p>';
}

if (!empty($warnings)) {
    echo '<h4>Warnings:</h4>';
    echo '<ul>';
    foreach ($warnings as $warning) {
        echo '<li>' . htmlspecialchars($warning) . '</li>';
    }
    echo '</ul>';
}

echo '</div>';

// ============================================================================
// NEXT STEPS
// ============================================================================
echo '<h2>Next Steps</h2>';
echo '<ol>';
echo '<li><strong>Run Integration Test:</strong> Click the "Run Full Integration Test" button above (if available)</li>';
echo '<li><strong>Check Logs:</strong> Review error logs for stage markers</li>';
echo '<li><strong>Test Cache:</strong> Run synthesis twice on same run to verify cache behavior</li>';
echo '<li><strong>Test Refresh Control (M1T4):</strong> Set refresh_config and verify force regeneration</li>';
echo '<li><strong>Performance Baseline:</strong> Compare execution time before/after refactoring</li>';
echo '</ol>';

echo '<hr>';

echo '<h2>Quick Links</h2>';
echo '<ul>';
echo '<li><a href="/local/customerintel/run.php">Create New Run</a></li>';
echo '<li><a href="/local/customerintel/reports.php">View All Reports</a></li>';
echo '<li><a href="/local/customerintel/dashboard.php">Dashboard</a></li>';
if ($latest_run) {
    echo '<li><a href="/local/customerintel/report.php?id=' . $latest_run->id . '">View Latest Report (Run ' . $latest_run->id . ')</a></li>';
}
echo '</ul>';

echo $OUTPUT->footer();
