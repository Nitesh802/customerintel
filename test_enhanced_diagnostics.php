<?php
/**
 * Enhanced Diagnostics System - Validation Test
 *
 * Tests the complete enhanced trace intelligence and diagnostics pipeline:
 * - Enhanced trace logging with structured JSON payloads
 * - Automated diagnostics service
 * - Performance trend analysis
 * - Predictive alert generation
 * - Export functionality
 *
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/diagnostics_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/telemetry_manager.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');

// Security checks
require_login();
$context = context_system::instance();
require_capability('local/customerintel:admin', $context);

echo "<!DOCTYPE html>\n<html>\n<head>\n";
echo "<title>Enhanced Diagnostics System - Validation Test</title>\n";
echo "<style>\n";
echo "body { font-family: Arial, sans-serif; margin: 20px; }\n";
echo ".test-section { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 15px 0; }\n";
echo ".test-pass { color: #28a745; font-weight: bold; }\n";
echo ".test-fail { color: #dc3545; font-weight: bold; }\n";
echo ".test-warning { color: #ffc107; font-weight: bold; }\n";
echo ".test-info { color: #17a2b8; font-weight: bold; }\n";
echo ".code-block { background: #f1f3f4; border: 1px solid #c1c7cd; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px; }\n";
echo "</style>\n</head>\n<body>\n";

echo "<h1>Enhanced Diagnostics System - Validation Test</h1>\n";
echo "<p>Testing the complete enhanced trace intelligence and diagnostics pipeline.</p>\n";

$test_results = [];

// Test 1: Database Schema Validation
echo "<div class='test-section'>\n";
echo "<h2>Test 1: Database Schema Validation</h2>\n";

try {
    // Check if local_ci_diagnostics table exists
    $table_exists = $DB->get_manager()->table_exists(new xmldb_table('local_ci_diagnostics'));
    
    if ($table_exists) {
        echo "<span class='test-pass'>✓ PASS</span> - local_ci_diagnostics table exists<br>\n";
        
        // Check table structure
        $columns = $DB->get_columns('local_ci_diagnostics');
        $expected_columns = ['id', 'runid', 'status', 'summary', 'issues_count', 'warnings_count', 'diagnostics_data', 'timecreated'];
        
        $missing_columns = [];
        foreach ($expected_columns as $col) {
            if (!isset($columns[$col])) {
                $missing_columns[] = $col;
            }
        }
        
        if (empty($missing_columns)) {
            echo "<span class='test-pass'>✓ PASS</span> - All required columns present<br>\n";
            $test_results['schema'] = true;
        } else {
            echo "<span class='test-fail'>✗ FAIL</span> - Missing columns: " . implode(', ', $missing_columns) . "<br>\n";
            $test_results['schema'] = false;
        }
    } else {
        echo "<span class='test-fail'>✗ FAIL</span> - local_ci_diagnostics table does not exist<br>\n";
        $test_results['schema'] = false;
    }
} catch (Exception $e) {
    echo "<span class='test-fail'>✗ ERROR</span> - Database schema check failed: " . $e->getMessage() . "<br>\n";
    $test_results['schema'] = false;
}

echo "</div>\n";

// Test 2: Service Class Instantiation
echo "<div class='test-section'>\n";
echo "<h2>Test 2: Service Class Instantiation</h2>\n";

try {
    $diagnostics_service = new \local_customerintel\services\diagnostics_service();
    echo "<span class='test-pass'>✓ PASS</span> - diagnostics_service instantiated<br>\n";
    
    $telemetry_manager = new \local_customerintel\services\telemetry_manager();
    echo "<span class='test-pass'>✓ PASS</span> - telemetry_manager instantiated<br>\n";
    
    $synthesis_engine = new \local_customerintel\services\synthesis_engine();
    echo "<span class='test-pass'>✓ PASS</span> - synthesis_engine instantiated<br>\n";
    
    $test_results['services'] = true;
} catch (Exception $e) {
    echo "<span class='test-fail'>✗ ERROR</span> - Service instantiation failed: " . $e->getMessage() . "<br>\n";
    $test_results['services'] = false;
}

echo "</div>\n";

// Test 3: Configuration Settings
echo "<div class='test-section'>\n";
echo "<h2>Test 3: Configuration Settings</h2>\n";

$config_tests = [
    'enable_detailed_trace_logging' => 'Enhanced trace logging',
    'enable_run_doctor' => 'Run Doctor functionality',
    'auto_run_diagnostics' => 'Auto-run diagnostics',
    'diagnostic_retention_days' => 'Diagnostic retention period'
];

$config_results = [];
foreach ($config_tests as $setting => $description) {
    $value = get_config('local_customerintel', $setting);
    if ($value !== false) {
        echo "<span class='test-pass'>✓ PASS</span> - {$description} setting exists (value: {$value})<br>\n";
        $config_results[] = true;
    } else {
        echo "<span class='test-warning'>⚠ WARNING</span> - {$description} setting not configured<br>\n";
        $config_results[] = false;
    }
}

$test_results['config'] = !in_array(false, $config_results);

echo "</div>\n";

// Test 4: Recent Telemetry Data
echo "<div class='test-section'>\n";
echo "<h2>Test 4: Recent Telemetry Data</h2>\n";

try {
    $recent_telemetry = $DB->get_records_sql(
        "SELECT * FROM {local_ci_telemetry} 
         WHERE timecreated >= ? 
         ORDER BY timecreated DESC 
         LIMIT 10",
        [time() - (7 * 24 * 60 * 60)] // Last 7 days
    );
    
    if (!empty($recent_telemetry)) {
        echo "<span class='test-pass'>✓ PASS</span> - Found " . count($recent_telemetry) . " recent telemetry records<br>\n";
        
        // Check for trace_phase records
        $trace_records = array_filter($recent_telemetry, function($r) { return $r->metrickey === 'trace_phase'; });
        if (!empty($trace_records)) {
            echo "<span class='test-pass'>✓ PASS</span> - Found " . count($trace_records) . " trace_phase records<br>\n";
            
            // Check for enhanced payload structure
            $enhanced_count = 0;
            foreach ($trace_records as $record) {
                $payload = json_decode($record->payload, true);
                if (isset($payload['phase_name']) && isset($payload['timestamp_ms'])) {
                    $enhanced_count++;
                }
            }
            
            if ($enhanced_count > 0) {
                echo "<span class='test-pass'>✓ PASS</span> - Found {$enhanced_count} records with enhanced payload structure<br>\n";
            } else {
                echo "<span class='test-warning'>⚠ WARNING</span> - No enhanced payload structures found<br>\n";
            }
        } else {
            echo "<span class='test-warning'>⚠ WARNING</span> - No trace_phase records found<br>\n";
        }
        
        $test_results['telemetry'] = true;
    } else {
        echo "<span class='test-warning'>⚠ WARNING</span> - No recent telemetry data found<br>\n";
        $test_results['telemetry'] = false;
    }
} catch (Exception $e) {
    echo "<span class='test-fail'>✗ ERROR</span> - Telemetry data check failed: " . $e->getMessage() . "<br>\n";
    $test_results['telemetry'] = false;
}

echo "</div>\n";

// Test 5: Diagnostics Service Functionality
echo "<div class='test-section'>\n";
echo "<h2>Test 5: Diagnostics Service Functionality</h2>\n";

try {
    // Get a recent run ID for testing
    $recent_run = $DB->get_record_sql(
        "SELECT id FROM {local_ci_run} 
         WHERE timecompleted IS NOT NULL 
         ORDER BY timecompleted DESC 
         LIMIT 1"
    );
    
    if ($recent_run) {
        echo "<span class='test-info'>ℹ INFO</span> - Testing with Run ID: {$recent_run->id}<br>\n";
        
        // Test diagnostics
        $diagnostics_results = $diagnostics_service->run_diagnostics($recent_run->id);
        
        if (is_array($diagnostics_results) && isset($diagnostics_results['overall_health'])) {
            echo "<span class='test-pass'>✓ PASS</span> - Diagnostics service executed successfully<br>\n";
            echo "<span class='test-info'>ℹ INFO</span> - Health Status: {$diagnostics_results['overall_health']}<br>\n";
            echo "<span class='test-info'>ℹ INFO</span> - Summary: " . substr($diagnostics_results['summary'], 0, 100) . "...<br>\n";
            
            $test_results['diagnostics'] = true;
        } else {
            echo "<span class='test-fail'>✗ FAIL</span> - Diagnostics service returned invalid results<br>\n";
            $test_results['diagnostics'] = false;
        }
    } else {
        echo "<span class='test-warning'>⚠ WARNING</span> - No completed runs found for testing<br>\n";
        $test_results['diagnostics'] = false;
    }
} catch (Exception $e) {
    echo "<span class='test-fail'>✗ ERROR</span> - Diagnostics service test failed: " . $e->getMessage() . "<br>\n";
    $test_results['diagnostics'] = false;
}

echo "</div>\n";

// Test 6: Telemetry Manager Performance Trends
echo "<div class='test-section'>\n";
echo "<h2>Test 6: Telemetry Manager Performance Trends</h2>\n";

try {
    $performance_trends = $telemetry_manager->get_performance_trends(7);
    
    if (is_array($performance_trends) && isset($performance_trends['period_days'])) {
        echo "<span class='test-pass'>✓ PASS</span> - Performance trends analysis completed<br>\n";
        echo "<span class='test-info'>ℹ INFO</span> - Analysis period: {$performance_trends['period_days']} days<br>\n";
        
        if (!empty($performance_trends['phase_durations'])) {
            echo "<span class='test-pass'>✓ PASS</span> - Phase duration analysis available (" . count($performance_trends['phase_durations']) . " phases)<br>\n";
        }
        
        if (!empty($performance_trends['overall_stats'])) {
            $stats = $performance_trends['overall_stats'];
            echo "<span class='test-info'>ℹ INFO</span> - Total runs: {$stats['total_runs']}, Success rate: {$stats['success_rate_percent']}%<br>\n";
        }
        
        $test_results['trends'] = true;
    } else {
        echo "<span class='test-fail'>✗ FAIL</span> - Performance trends analysis returned invalid results<br>\n";
        $test_results['trends'] = false;
    }
} catch (Exception $e) {
    echo "<span class='test-fail'>✗ ERROR</span> - Performance trends test failed: " . $e->getMessage() . "<br>\n";
    $test_results['trends'] = false;
}

echo "</div>\n";

// Test 7: Export Functionality
echo "<div class='test-section'>\n";
echo "<h2>Test 7: Export Functionality</h2>\n";

try {
    // Test telemetry export
    $export_data = $telemetry_manager->export_telemetry_data(null, 1);
    
    if (is_array($export_data) && isset($export_data['export_timestamp'])) {
        echo "<span class='test-pass'>✓ PASS</span> - Telemetry export functionality working<br>\n";
        echo "<span class='test-info'>ℹ INFO</span> - Exported " . $export_data['record_count'] . " telemetry records<br>\n";
        
        $test_results['export'] = true;
    } else {
        echo "<span class='test-fail'>✗ FAIL</span> - Telemetry export returned invalid data<br>\n";
        $test_results['export'] = false;
    }
} catch (Exception $e) {
    echo "<span class='test-fail'>✗ ERROR</span> - Export functionality test failed: " . $e->getMessage() . "<br>\n";
    $test_results['export'] = false;
}

echo "</div>\n";

// Test Summary
echo "<div class='test-section'>\n";
echo "<h2>Test Summary</h2>\n";

$total_tests = count($test_results);
$passed_tests = array_sum($test_results);
$success_rate = ($passed_tests / $total_tests) * 100;

echo "<p><strong>Overall Test Results:</strong></p>\n";
echo "<p>Passed: {$passed_tests} / {$total_tests} (" . round($success_rate, 1) . "%)</p>\n";

if ($success_rate >= 80) {
    echo "<span class='test-pass'>✓ SYSTEM READY</span> - Enhanced Diagnostics System is operational<br>\n";
} elseif ($success_rate >= 60) {
    echo "<span class='test-warning'>⚠ PARTIAL FUNCTIONALITY</span> - Some components need attention<br>\n";
} else {
    echo "<span class='test-fail'>✗ SYSTEM ISSUES</span> - Multiple components require fixes<br>\n";
}

echo "<h3>Individual Test Results:</h3>\n";
echo "<ul>\n";
foreach ($test_results as $test => $result) {
    $status = $result ? "✓ PASS" : "✗ FAIL";
    $class = $result ? "test-pass" : "test-fail";
    echo "<li><span class='{$class}'>{$status}</span> - " . ucfirst(str_replace('_', ' ', $test)) . "</li>\n";
}
echo "</ul>\n";

echo "<h3>Next Steps:</h3>\n";
echo "<ul>\n";
if (!$test_results['schema']) {
    echo "<li>Run database upgrade to create missing tables/columns</li>\n";
}
if (!$test_results['config']) {
    echo "<li>Configure diagnostic settings in admin interface</li>\n";
}
if (!$test_results['telemetry']) {
    echo "<li>Run a synthesis operation to generate telemetry data</li>\n";
}
echo "<li>Access the <a href='diagnostics.php'>Diagnostics & Health Hub</a> to start monitoring</li>\n";
echo "</ul>\n";

echo "</div>\n";

echo "</body>\n</html>\n";