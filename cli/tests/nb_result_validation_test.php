<?php
/**
 * NB Result Validation Test
 * 
 * Test script to verify the new validation logic in nb_orchestrator
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test the new NB result validation and error handling
 */
function test_nb_result_validation() {
    require_once(__DIR__ . '/../classes/services/nb_orchestrator.php');
    
    echo "Testing NB Result Validation\n";
    echo "============================\n\n";
    
    // Test 1: Valid NB codes
    $orchestrator = new \local_customerintel\services\nb_orchestrator();
    $reflection = new ReflectionClass($orchestrator);
    $validate_nbcode = $reflection->getMethod('validate_nbcode');
    $validate_nbcode->setAccessible(true);
    
    echo "Test 1: NB Code Validation\n";
    echo "Valid codes:\n";
    for ($i = 1; $i <= 15; $i++) {
        $code = "NB-{$i}";
        $valid = $validate_nbcode->invoke($orchestrator, $code);
        echo "  {$code}: " . ($valid ? 'PASS' : 'FAIL') . "\n";
    }
    
    echo "\nInvalid codes:\n";
    $invalid_codes = ['NB-0', 'NB-16', 'NB1', 'nb-1', 'NB-1A', ''];
    foreach ($invalid_codes as $code) {
        $valid = $validate_nbcode->invoke($orchestrator, $code);
        echo "  '{$code}': " . ($valid ? 'FAIL (should be invalid)' : 'PASS') . "\n";
    }
    
    // Test 2: Record validation
    echo "\nTest 2: Record Validation\n";
    $validate_record = $reflection->getMethod('validate_nb_record');
    $validate_record->setAccessible(true);
    
    // Valid record
    $valid_record = new stdClass();
    $valid_record->runid = 123;
    $valid_record->nbcode = 'NB-1';
    $valid_record->jsonpayload = '{"test": "data"}';
    $valid_record->citations = '[]';
    $valid_record->durationms = 1000;
    $valid_record->tokensused = 500;
    $valid_record->status = 'completed';
    $valid_record->timecreated = time();
    $valid_record->timemodified = time();
    
    $errors = $validate_record->invoke($orchestrator, $valid_record);
    echo "Valid record: " . (empty($errors) ? 'PASS' : 'FAIL - ' . implode(', ', $errors)) . "\n";
    
    // Test missing fields
    $invalid_record = new stdClass();
    $invalid_record->runid = 123;
    // Missing other fields
    
    $errors = $validate_record->invoke($orchestrator, $invalid_record);
    echo "Missing fields: " . (!empty($errors) ? 'PASS (expected errors)' : 'FAIL') . "\n";
    echo "  Errors: " . implode(', ', $errors) . "\n";
    
    // Test invalid field types
    $invalid_record2 = new stdClass();
    $invalid_record2->runid = '123'; // String instead of int
    $invalid_record2->nbcode = 'INVALID';
    $invalid_record2->jsonpayload = 123; // Int instead of string
    $invalid_record2->citations = null;
    $invalid_record2->status = 'invalid_status';
    $invalid_record2->timecreated = 'not_timestamp';
    $invalid_record2->timemodified = -1;
    
    $errors = $validate_record->invoke($orchestrator, $invalid_record2);
    echo "\nInvalid field types: " . (!empty($errors) ? 'PASS (expected errors)' : 'FAIL') . "\n";
    echo "  Errors: " . implode(', ', $errors) . "\n";
    
    echo "\nValidation tests completed.\n\n";
}

// Export test function for manual execution
// To run: require_once this file, then call test_nb_result_validation()