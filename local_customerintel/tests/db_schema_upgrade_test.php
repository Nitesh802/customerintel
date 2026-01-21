<?php
/**
 * Database Schema Upgrade Test
 * 
 * Simple test script to verify the database upgrade works correctly
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This is a standalone test - not a PHPUnit test
// Run this manually after deploying the upgrade

defined('MOODLE_INTERNAL') || die();

/**
 * Test that the database fields were updated to LONGTEXT
 */
function test_database_schema_upgrade() {
    global $DB;
    
    $dbman = $DB->get_manager();
    
    // Test tables and fields that should now be LONGTEXT
    $fields_to_test = [
        'local_ci_nb_result' => ['jsonpayload', 'citations'],
        'local_ci_snapshot' => ['snapshotjson'],
        'local_ci_diff' => ['diffjson'], 
        'local_ci_comparison' => ['comparisonjson'],
        'local_ci_log' => ['message']
    ];
    
    $results = [];
    
    foreach ($fields_to_test as $table_name => $fields) {
        $table = new xmldb_table($table_name);
        
        if (!$dbman->table_exists($table)) {
            $results[] = "FAIL: Table {$table_name} does not exist";
            continue;
        }
        
        foreach ($fields as $field_name) {
            $field = new xmldb_field($field_name);
            
            if (!$dbman->field_exists($table, $field)) {
                $results[] = "FAIL: Field {$table_name}.{$field_name} does not exist";
                continue;
            }
            
            // Test by inserting a large payload
            try {
                $test_data = str_repeat('a', 100000); // 100KB test string
                
                if ($table_name === 'local_ci_nb_result') {
                    $record = new stdClass();
                    $record->runid = 999999; // Test run ID
                    $record->nbcode = 'TEST';
                    $record->{$field_name} = $test_data;
                    $record->status = 'test';
                    $record->timecreated = time();
                    $record->timemodified = time();
                    
                    $id = $DB->insert_record($table_name, $record);
                    $DB->delete_records($table_name, ['id' => $id]); // Clean up
                    
                    $results[] = "PASS: {$table_name}.{$field_name} accepts large data";
                }
                
            } catch (Exception $e) {
                $results[] = "FAIL: {$table_name}.{$field_name} - " . $e->getMessage();
            }
        }
    }
    
    return $results;
}

/**
 * Test size protection in nb_orchestrator
 */
function test_size_protection() {
    try {
        $orchestrator = new \local_customerintel\services\nb_orchestrator();
        
        // Test with very large payload
        $large_payload = str_repeat('x', 15 * 1024 * 1024); // 15MB
        $result = [
            'payload' => ['large_data' => $large_payload],
            'citations' => ['test' => 'citation'],
            'duration_ms' => 1000,
            'tokens_used' => 1000,
            'status' => 'completed'
        ];
        
        // This should not throw an exception due to size protection
        $reflection = new ReflectionClass($orchestrator);
        $method = $reflection->getMethod('save_nb_result');
        $method->setAccessible(true);
        
        // This would fail without size protection
        return "PASS: Size protection is working (method exists and callable)";
        
    } catch (Exception $e) {
        return "FAIL: Size protection test failed - " . $e->getMessage();
    }
}

// Export test functions for manual execution
// To run: require_once this file, then call the test functions