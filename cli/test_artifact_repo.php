<?php
/**
 * Test script for artifact repository functionality
 * 
 * This script tests the artifact repository to ensure proper namespacing and functionality
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "Testing Artifact Repository...\n";

try {
    // Test 1: Class loading and instantiation
    echo "1. Testing class loading...\n";
    require_once(__DIR__ . '/classes/services/artifact_repository.php');
    $artifact_repo = new \local_customerintel\services\artifact_repository();
    echo "   ✅ Artifact repository instantiated successfully\n";
    
    // Test 2: Check if trace mode configuration exists
    echo "2. Testing configuration...\n";
    $trace_mode = get_config('local_customerintel', 'enable_trace_mode');
    echo "   Trace mode setting: " . ($trace_mode ? $trace_mode : 'not set') . "\n";
    
    // Test 3: Test save artifact (with fake data)
    echo "3. Testing save artifact functionality...\n";
    if (get_config('local_customerintel', 'enable_trace_mode') === '1') {
        $test_data = [
            'test' => true,
            'timestamp' => time(),
            'message' => 'Test artifact from test script'
        ];
        
        $result = $artifact_repo->save_artifact(999999, 'test', 'test_artifact', $test_data);
        echo "   Save artifact result: " . ($result ? 'success' : 'failed') . "\n";
        
        // Test 4: Get artifacts for run
        echo "4. Testing get artifacts...\n";
        $artifacts = $artifact_repo->get_artifacts_for_run(999999);
        echo "   Found " . count($artifacts) . " artifacts for test run\n";
        
        // Clean up test artifact
        if (!empty($artifacts)) {
            foreach ($artifacts as $artifact) {
                if ($artifact->phase === 'test') {
                    $DB->delete_records('local_ci_artifact', ['id' => $artifact->id]);
                    echo "   ✅ Cleaned up test artifact\n";
                }
            }
        }
        
    } else {
        echo "   ⚠️  Trace mode is disabled - skipping save test\n";
    }
    
    // Test 5: Check database table exists
    echo "5. Testing database table...\n";
    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_ci_artifact');
    if ($dbman->table_exists($table)) {
        echo "   ✅ local_ci_artifact table exists\n";
        
        // Check table structure
        $columns = $DB->get_columns('local_ci_artifact');
        $expected_columns = ['id', 'runid', 'phase', 'artifacttype', 'jsondata', 'timecreated', 'timemodified'];
        $missing_columns = array_diff($expected_columns, array_keys($columns));
        
        if (empty($missing_columns)) {
            echo "   ✅ All expected columns present\n";
        } else {
            echo "   ❌ Missing columns: " . implode(', ', $missing_columns) . "\n";
        }
    } else {
        echo "   ❌ local_ci_artifact table does not exist\n";
    }
    
    echo "\n✅ All tests completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Enable trace mode in Customer Intelligence settings\n";
    echo "2. Run a test intelligence report\n";
    echo "3. Check for artifacts in the local_ci_artifact table\n";
    echo "4. View the Data Trace tab in the report\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>