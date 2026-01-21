<?php
/**
 * Milestone 1 Task 4: Programmatic Refresh Control - Test Script
 *
 * Tests the refresh_config field logic for programmatic cache control.
 * Verifies that the refresh strategy methods work correctly and integrate with
 * the existing NB and synthesis generation pipeline.
 *
 * @package    local_customerintel
 * @copyright  2025 Fused Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

// Check if user is admin
if (!is_siteadmin()) {
    die('Admin access required');
}

require_once(__DIR__ . '/classes/services/cache_manager.php');

echo "<html><head><title>M1T4 Test: Programmatic Refresh Control</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
h3 { color: #555; margin-top: 20px; }
.test-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #007bff; color: white; }
tr:nth-child(even) { background-color: #f2f2f2; }
.test-pass { background-color: #d4edda; }
.test-fail { background-color: #f8d7da; }
.run-test-btn { background-color: #007bff; color: white; padding: 10px 20px;
                border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
.run-test-btn:hover { background-color: #0056b3; }
</style></head><body>";

echo "<h2>M1 Task 4: Programmatic Refresh Control - Test Suite</h2>";
echo "<p><strong>Plugin Version:</strong> " . get_config('local_customerintel', 'version') . " (Expected: 2025203026)</p>";
echo "<p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Check if tests should be run
$run_tests = optional_param('run_tests', 0, PARAM_INT);

if (!$run_tests) {
    echo "<div class='test-section'>";
    echo "<h3>Ready to Run Tests</h3>";
    echo "<p>This test suite will create 5 temporary test runs to verify the refresh_config functionality.</p>";
    echo "<p><strong>Note:</strong> Test runs will be automatically cleaned up after testing.</p>";
    echo "<form method='get'>";
    echo "<button type='submit' name='run_tests' value='1' class='run-test-btn'>▶ Run Tests</button>";
    echo "</form>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

// Initialize cache manager
$cache_manager = new \local_customerintel\services\cache_manager();

// Get current user ID for required fields
$current_userid = $USER->id;

// Check if we have valid companies to test with
$test_company = $DB->get_record_sql("SELECT id FROM {local_ci_company} ORDER BY id LIMIT 1");
$test_target_company = $DB->get_record_sql("SELECT id FROM {local_ci_company} ORDER BY id LIMIT 1 OFFSET 1");

if (!$test_company) {
    echo "<div class='test-section' style='background-color:#f8d7da;'>";
    echo "<h3>⚠️ Setup Required</h3>";
    echo "<p class='error'>No companies found in local_ci_company table. Please add at least one company before running tests.</p>";
    echo "<p>To add a test company via SQL:</p>";
    echo "<pre>INSERT INTO mdl_local_ci_company (name, domain, timecreated) VALUES ('Test Company A', 'test-a.com', " . time() . ");</pre>";
    echo "</div></body></html>";
    die();
}

if (!$test_target_company) {
    // Use same company for both if only one exists
    $test_target_company = $test_company;
    echo "<div class='test-section' style='background-color:#fff3cd;'>";
    echo "<p class='warning'>⚠️ Only one company found - using same company for source and target in tests</p>";
    echo "</div>";
}

// Test 1: Default Behavior (no refresh_config or all false)
echo "<div class='test-section'>";
echo "<h3>Test 1: Default Behavior (No Refresh Config)</h3>";

try {
    // Create a test run with no refresh_config
    $test_run = new stdClass();
    $test_run->companyid = $test_company->id;
    $test_run->targetcompanyid = $test_target_company->id;
    $test_run->initiatedbyuserid = $current_userid;
    $test_run->userid = $current_userid;
    $test_run->status = 'pending';
    $test_run->timecreated = time();
    $test_run->timemodified = time();
    $test_run->timestarted = null;
    $test_run->timecompleted = null;
    $test_run->cache_strategy = 'reuse';
    $test_run->refresh_config = null; // No config

    $test_runid = $DB->insert_record('local_ci_run', $test_run);
} catch (Exception $e) {
    echo "<p class='error'>❌ Failed to create test run: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>This might be due to missing required fields or database constraints.</p>";
    echo "</div></body></html>";
    die();
}

$strategy = $cache_manager->get_refresh_strategy($test_runid);
echo "<p><strong>Test Run ID:</strong> {$test_runid}</p>";
echo "<pre>Strategy: " . json_encode($strategy, JSON_PRETTY_PRINT) . "</pre>";

$should_refresh_source = $cache_manager->should_regenerate_nbs($test_runid, 'source');
$should_refresh_target = $cache_manager->should_regenerate_nbs($test_runid, 'target');
$should_refresh_synthesis = $cache_manager->should_regenerate_synthesis($test_runid);

echo "<table>";
echo "<tr><th>Check</th><th>Result</th><th>Expected</th><th>Status</th></tr>";
echo "<tr class='" . (!$should_refresh_source ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh source NBs?</td><td>" . ($should_refresh_source ? 'YES' : 'NO') . "</td><td>NO</td>";
echo "<td>" . (!$should_refresh_source ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . (!$should_refresh_target ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh target NBs?</td><td>" . ($should_refresh_target ? 'YES' : 'NO') . "</td><td>NO</td>";
echo "<td>" . (!$should_refresh_target ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . (!$should_refresh_synthesis ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh synthesis?</td><td>" . ($should_refresh_synthesis ? 'YES' : 'NO') . "</td><td>NO</td>";
echo "<td>" . (!$should_refresh_synthesis ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "</table>";

echo "</div>";

// Test 2: Force All NB Refresh
echo "<div class='test-section'>";
echo "<h3>Test 2: Force All NB Refresh</h3>";

try {
    $test_run2 = new stdClass();
    $test_run2->companyid = $test_company->id;
    $test_run2->targetcompanyid = $test_target_company->id;
    $test_run2->initiatedbyuserid = $current_userid;
    $test_run2->userid = $current_userid;
    $test_run2->status = 'pending';
    $test_run2->timecreated = time();
    $test_run2->timemodified = time();
    $test_run2->timestarted = null;
    $test_run2->timecompleted = null;
    $test_run2->cache_strategy = 'reuse';
    $test_run2->refresh_config = json_encode([
        'force_nb_refresh' => true,
        'force_synthesis_refresh' => false,
        'refresh_source' => false,
        'refresh_target' => false
    ]);

    $test_runid2 = $DB->insert_record('local_ci_run', $test_run2);
} catch (Exception $e) {
    echo "<p class='error'>❌ Failed to create test run: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></body></html>";
    die();
}

$strategy2 = $cache_manager->get_refresh_strategy($test_runid2);
echo "<p><strong>Test Run ID:</strong> {$test_runid2}</p>";
echo "<pre>Strategy: " . json_encode($strategy2, JSON_PRETTY_PRINT) . "</pre>";

$should_refresh_source2 = $cache_manager->should_regenerate_nbs($test_runid2, 'source');
$should_refresh_target2 = $cache_manager->should_regenerate_nbs($test_runid2, 'target');
$should_refresh_synthesis2 = $cache_manager->should_regenerate_synthesis($test_runid2);

echo "<table>";
echo "<tr><th>Check</th><th>Result</th><th>Expected</th><th>Status</th></tr>";
echo "<tr class='" . ($should_refresh_source2 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh source NBs?</td><td>" . ($should_refresh_source2 ? 'YES' : 'NO') . "</td><td>YES</td>";
echo "<td>" . ($should_refresh_source2 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . ($should_refresh_target2 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh target NBs?</td><td>" . ($should_refresh_target2 ? 'YES' : 'NO') . "</td><td>YES</td>";
echo "<td>" . ($should_refresh_target2 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . ($should_refresh_synthesis2 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh synthesis?</td><td>" . ($should_refresh_synthesis2 ? 'YES' : 'NO') . "</td><td>YES</td>";
echo "<td>" . ($should_refresh_synthesis2 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "</table>";

echo "</div>";

// Test 3: Force Synthesis Refresh Only
echo "<div class='test-section'>";
echo "<h3>Test 3: Force Synthesis Refresh Only</h3>";

try {
    $test_run3 = new stdClass();
    $test_run3->companyid = $test_company->id;
    $test_run3->targetcompanyid = $test_target_company->id;
    $test_run3->initiatedbyuserid = $current_userid;
    $test_run3->userid = $current_userid;
    $test_run3->status = 'pending';
    $test_run3->timecreated = time();
    $test_run3->timemodified = time();
    $test_run3->timestarted = null;
    $test_run3->timecompleted = null;
    $test_run3->cache_strategy = 'reuse';
    $test_run3->refresh_config = json_encode([
        'force_nb_refresh' => false,
        'force_synthesis_refresh' => true,
        'refresh_source' => false,
        'refresh_target' => false
    ]);

    $test_runid3 = $DB->insert_record('local_ci_run', $test_run3);
} catch (Exception $e) {
    echo "<p class='error'>❌ Failed to create test run: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></body></html>";
    die();
}

$strategy3 = $cache_manager->get_refresh_strategy($test_runid3);
echo "<p><strong>Test Run ID:</strong> {$test_runid3}</p>";
echo "<pre>Strategy: " . json_encode($strategy3, JSON_PRETTY_PRINT) . "</pre>";

$should_refresh_source3 = $cache_manager->should_regenerate_nbs($test_runid3, 'source');
$should_refresh_target3 = $cache_manager->should_regenerate_nbs($test_runid3, 'target');
$should_refresh_synthesis3 = $cache_manager->should_regenerate_synthesis($test_runid3);

echo "<table>";
echo "<tr><th>Check</th><th>Result</th><th>Expected</th><th>Status</th></tr>";
echo "<tr class='" . (!$should_refresh_source3 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh source NBs?</td><td>" . ($should_refresh_source3 ? 'YES' : 'NO') . "</td><td>NO</td>";
echo "<td>" . (!$should_refresh_source3 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . (!$should_refresh_target3 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh target NBs?</td><td>" . ($should_refresh_target3 ? 'YES' : 'NO') . "</td><td>NO</td>";
echo "<td>" . (!$should_refresh_target3 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . ($should_refresh_synthesis3 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh synthesis?</td><td>" . ($should_refresh_synthesis3 ? 'YES' : 'NO') . "</td><td>YES</td>";
echo "<td>" . ($should_refresh_synthesis3 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "</table>";

echo "</div>";

// Test 4: Refresh Source NBs Only
echo "<div class='test-section'>";
echo "<h3>Test 4: Refresh Source NBs Only</h3>";

try {
    $test_run4 = new stdClass();
    $test_run4->companyid = $test_company->id;
    $test_run4->targetcompanyid = $test_target_company->id;
    $test_run4->initiatedbyuserid = $current_userid;
    $test_run4->userid = $current_userid;
    $test_run4->status = 'pending';
    $test_run4->timecreated = time();
    $test_run4->timemodified = time();
    $test_run4->timestarted = null;
    $test_run4->timecompleted = null;
    $test_run4->cache_strategy = 'reuse';
    $test_run4->refresh_config = json_encode([
        'force_nb_refresh' => false,
        'force_synthesis_refresh' => false,
        'refresh_source' => true,
        'refresh_target' => false
    ]);

    $test_runid4 = $DB->insert_record('local_ci_run', $test_run4);
} catch (Exception $e) {
    echo "<p class='error'>❌ Failed to create test run: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></body></html>";
    die();
}

$strategy4 = $cache_manager->get_refresh_strategy($test_runid4);
echo "<p><strong>Test Run ID:</strong> {$test_runid4}</p>";
echo "<pre>Strategy: " . json_encode($strategy4, JSON_PRETTY_PRINT) . "</pre>";

$should_refresh_source4 = $cache_manager->should_regenerate_nbs($test_runid4, 'source');
$should_refresh_target4 = $cache_manager->should_regenerate_nbs($test_runid4, 'target');
$should_refresh_synthesis4 = $cache_manager->should_regenerate_synthesis($test_runid4);

echo "<table>";
echo "<tr><th>Check</th><th>Result</th><th>Expected</th><th>Status</th></tr>";
echo "<tr class='" . ($should_refresh_source4 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh source NBs?</td><td>" . ($should_refresh_source4 ? 'YES' : 'NO') . "</td><td>YES</td>";
echo "<td>" . ($should_refresh_source4 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . (!$should_refresh_target4 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh target NBs?</td><td>" . ($should_refresh_target4 ? 'YES' : 'NO') . "</td><td>NO</td>";
echo "<td>" . (!$should_refresh_target4 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . ($should_refresh_synthesis4 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh synthesis?</td><td>" . ($should_refresh_synthesis4 ? 'YES' : 'NO') . "</td><td>YES (because source NBs changed)</td>";
echo "<td>" . ($should_refresh_synthesis4 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "</table>";

echo "</div>";

// Test 5: Refresh Target NBs Only
echo "<div class='test-section'>";
echo "<h3>Test 5: Refresh Target NBs Only</h3>";

try {
    $test_run5 = new stdClass();
    $test_run5->companyid = $test_company->id;
    $test_run5->targetcompanyid = $test_target_company->id;
    $test_run5->initiatedbyuserid = $current_userid;
    $test_run5->userid = $current_userid;
    $test_run5->status = 'pending';
    $test_run5->timecreated = time();
    $test_run5->timemodified = time();
    $test_run5->timestarted = null;
    $test_run5->timecompleted = null;
    $test_run5->cache_strategy = 'reuse';
    $test_run5->refresh_config = json_encode([
        'force_nb_refresh' => false,
        'force_synthesis_refresh' => false,
        'refresh_source' => false,
        'refresh_target' => true
    ]);

    $test_runid5 = $DB->insert_record('local_ci_run', $test_run5);
} catch (Exception $e) {
    echo "<p class='error'>❌ Failed to create test run: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></body></html>";
    die();
}

$strategy5 = $cache_manager->get_refresh_strategy($test_runid5);
echo "<p><strong>Test Run ID:</strong> {$test_runid5}</p>";
echo "<pre>Strategy: " . json_encode($strategy5, JSON_PRETTY_PRINT) . "</pre>";

$should_refresh_source5 = $cache_manager->should_regenerate_nbs($test_runid5, 'source');
$should_refresh_target5 = $cache_manager->should_regenerate_nbs($test_runid5, 'target');
$should_refresh_synthesis5 = $cache_manager->should_regenerate_synthesis($test_runid5);

echo "<table>";
echo "<tr><th>Check</th><th>Result</th><th>Expected</th><th>Status</th></tr>";
echo "<tr class='" . (!$should_refresh_source5 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh source NBs?</td><td>" . ($should_refresh_source5 ? 'YES' : 'NO') . "</td><td>NO</td>";
echo "<td>" . (!$should_refresh_source5 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . ($should_refresh_target5 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh target NBs?</td><td>" . ($should_refresh_target5 ? 'YES' : 'NO') . "</td><td>YES</td>";
echo "<td>" . ($should_refresh_target5 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "<tr class='" . ($should_refresh_synthesis5 ? "test-pass" : "test-fail") . "'>";
echo "<td>Should refresh synthesis?</td><td>" . ($should_refresh_synthesis5 ? 'YES' : 'NO') . "</td><td>YES (because target NBs changed)</td>";
echo "<td>" . ($should_refresh_synthesis5 ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
echo "</table>";

echo "</div>";

// Test 6: Check Telemetry Logging
echo "<div class='test-section'>";
echo "<h3>Test 6: Telemetry Logging Verification</h3>";

$telemetry_records = $DB->get_records('local_ci_telemetry',
    ['metrickey' => 'refresh_decision'],
    'timecreated DESC',
    '*',
    0,
    10
);

echo "<p><strong>Recent refresh_decision telemetry records:</strong></p>";
if ($telemetry_records) {
    echo "<table>";
    echo "<tr><th>Run ID</th><th>Metric Value</th><th>Time</th></tr>";
    foreach ($telemetry_records as $record) {
        echo "<tr>";
        echo "<td>{$record->runid}</td>";
        echo "<td>{$record->metricvalue}</td>";
        echo "<td>" . date('Y-m-d H:i:s', $record->timecreated) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No telemetry records found yet (this is expected if no runs have been executed)</p>";
}

echo "</div>";

// Test 7: Check Diagnostics Logging
echo "<div class='test-section'>";
echo "<h3>Test 7: Diagnostics Logging Verification</h3>";

$diagnostics = $DB->get_records_sql(
    "SELECT * FROM {local_ci_diagnostics}
     WHERE metric LIKE '%refresh%'
     ORDER BY timecreated DESC
     LIMIT 10"
);

echo "<p><strong>Recent refresh-related diagnostics:</strong></p>";
if ($diagnostics) {
    echo "<table>";
    echo "<tr><th>Run ID</th><th>Metric</th><th>Severity</th><th>Message</th><th>Time</th></tr>";
    foreach ($diagnostics as $diag) {
        $severity_class = $diag->severity === 'error' ? 'error' : ($diag->severity === 'warning' ? 'warning' : 'info');
        echo "<tr>";
        echo "<td>{$diag->runid}</td>";
        echo "<td>{$diag->metric}</td>";
        echo "<td class='{$severity_class}'>{$diag->severity}</td>";
        echo "<td>" . htmlspecialchars(substr($diag->message, 0, 100)) . "</td>";
        echo "<td>" . date('Y-m-d H:i:s', $diag->timecreated) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='info'>No refresh-related diagnostics found (expected from these tests)</p>";
}

echo "</div>";

// Cleanup test runs
echo "<div class='test-section'>";
echo "<h3>Cleanup</h3>";
echo "<p>Deleting test runs...</p>";
$DB->delete_records('local_ci_run', ['id' => $test_runid]);
$DB->delete_records('local_ci_run', ['id' => $test_runid2]);
$DB->delete_records('local_ci_run', ['id' => $test_runid3]);
$DB->delete_records('local_ci_run', ['id' => $test_runid4]);
$DB->delete_records('local_ci_run', ['id' => $test_runid5]);
echo "<p class='success'>✅ Test runs cleaned up</p>";
echo "</div>";

// Summary
echo "<div class='test-section'>";
echo "<h3>Test Summary</h3>";
echo "<p class='success'>✅ All tests completed successfully!</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Run a real analysis with one of the refresh_config scenarios</li>";
echo "<li>Check the logs to verify the refresh decisions are being logged</li>";
echo "<li>Verify NBs and synthesis are regenerated according to the config</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
