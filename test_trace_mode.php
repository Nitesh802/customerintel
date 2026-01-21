<?php
/**
 * Test script to verify Trace Mode functionality
 * 
 * This script tests the trace logging system without requiring synthesis execution
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "<h2>CustomerIntel Trace Mode Test</h2>";

// Test 1: Check if trace mode setting exists
echo "<h3>Test 1: Admin Setting Check</h3>";
$trace_enabled = get_config('local_customerintel', 'enable_detailed_trace_logging');
echo "Trace mode setting value: " . ($trace_enabled ? $trace_enabled : 'not set') . "<br>";
echo "Status: " . ($trace_enabled === '1' ? '✅ ENABLED' : '❌ DISABLED') . "<br><br>";

// Test 2: Test telemetry logger functionality
echo "<h3>Test 2: Telemetry Logger Test</h3>";
try {
    require_once(__DIR__ . '/classes/services/telemetry_logger.php');
    $telemetry = new \local_customerintel\services\telemetry_logger();
    
    // Create a test log entry
    $test_runid = 999999; // Use a high number to avoid conflicts
    $result = $telemetry->log_metric($test_runid, 'trace_test_message', null, [
        'test' => true,
        'timestamp' => time(),
        'trace_mode_enabled' => $trace_enabled === '1'
    ]);
    
    echo "Telemetry logger: " . ($result ? '✅ WORKING' : '❌ FAILED') . "<br>";
    
    // Check if test log was created
    $test_log = $DB->get_record('local_ci_telemetry', [
        'runid' => $test_runid,
        'metrickey' => 'trace_test_message'
    ]);
    
    echo "Test log created: " . ($test_log ? '✅ YES' : '❌ NO') . "<br>";
    
    // Clean up test log
    if ($test_log) {
        $DB->delete_records('local_ci_telemetry', ['id' => $test_log->id]);
        echo "Test log cleaned up: ✅ YES<br>";
    }
    
} catch (Exception $e) {
    echo "Telemetry test failed: ❌ " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 3: Check synthesis engine trace helper
echo "<h3>Test 3: Synthesis Engine Trace Helper</h3>";
try {
    require_once(__DIR__ . '/classes/services/synthesis_engine.php');
    $synthesis_engine = new \local_customerintel\services\synthesis_engine();
    
    // Check if log_trace method exists using reflection
    $reflection = new ReflectionClass($synthesis_engine);
    $has_trace_method = $reflection->hasMethod('log_trace');
    
    echo "log_trace method exists: " . ($has_trace_method ? '✅ YES' : '❌ NO') . "<br>";
    
    if ($has_trace_method) {
        $trace_method = $reflection->getMethod('log_trace');
        $trace_method->setAccessible(true);
        echo "log_trace method accessible: ✅ YES<br>";
    }
    
} catch (Exception $e) {
    echo "Synthesis engine test failed: ❌ " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 4: Check UI components
echo "<h3>Test 4: UI Components Check</h3>";

// Check if trace viewer exists
$trace_viewer_path = __DIR__ . '/trace_viewer.php';
echo "Trace viewer file: " . (file_exists($trace_viewer_path) ? '✅ EXISTS' : '❌ MISSING') . "<br>";

// Check if reports template was updated
$template_path = __DIR__ . '/templates/reports.mustache';
if (file_exists($template_path)) {
    $template_content = file_get_contents($template_path);
    $has_trace_button = strpos($template_content, 'trace_enabled') !== false;
    echo "Reports template updated: " . ($has_trace_button ? '✅ YES' : '❌ NO') . "<br>";
} else {
    echo "Reports template: ❌ NOT FOUND<br>";
}

echo "<br>";

// Test 5: Configuration URLs
echo "<h3>Test 5: Configuration Links</h3>";
$settings_url = new moodle_url('/admin/settings.php', ['section' => 'local_customerintel_settings']);
echo "Admin settings: <a href='{$settings_url}' target='_blank'>Configure Trace Mode</a><br>";

$reports_url = new moodle_url('/local/customerintel/reports.php');
echo "Reports page: <a href='{$reports_url}' target='_blank'>View Reports</a><br>";

if ($trace_enabled === '1') {
    echo "<br><div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
    echo "<strong>✅ Trace Mode is ENABLED</strong><br>";
    echo "You should see 'View Trace Log' buttons on completed reports in the reports page.";
    echo "</div>";
} else {
    echo "<br><div style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;'>";
    echo "<strong>⚠️ Trace Mode is DISABLED</strong><br>";
    echo "Enable it in the admin settings to see trace logs for future synthesis runs.";
    echo "</div>";
}

echo "<br><p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Enable trace mode in <a href='{$settings_url}' target='_blank'>CustomerIntel settings</a></li>";
echo "<li>Run or re-run a synthesis report (any run ID)</li>";
echo "<li>Check the <a href='{$reports_url}' target='_blank'>reports page</a> for the 'View Trace Log' button</li>";
echo "<li>Click 'View Trace Log' to see detailed synthesis phase debugging</li>";
echo "</ol>";
?>