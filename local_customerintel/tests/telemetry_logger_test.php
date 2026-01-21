<?php
/**
 * Unit tests for Telemetry Logger (Slice 7)
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;
use local_customerintel\services\telemetry_logger;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/telemetry_logger.php');

/**
 * Test class for telemetry logger functionality
 * 
 * @coversDefaultClass \local_customerintel\services\telemetry_logger
 */
class telemetry_logger_test extends advanced_testcase {
    
    /**
     * @var telemetry_logger
     */
    private $logger;
    
    /**
     * @var int Test run ID
     */
    private $test_runid = 9999;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Enable detailed telemetry for testing
        set_config('enable_detailed_telemetry', '1', 'local_customerintel');
        
        $this->logger = new telemetry_logger();
    }
    
    /**
     * Test basic metric logging
     */
    public function test_log_metric_basic() {
        global $DB;
        
        // Log a simple metric
        $result = $this->logger->log_metric($this->test_runid, 'test_metric', 42.5);
        $this->assertTrue($result, 'Metric logging should return true');
        
        // Verify it was inserted
        $record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'test_metric'
        ]);
        
        $this->assertNotEmpty($record, 'Metric should be in database');
        $this->assertEquals(42.5, $record->metricvaluenum, 'Numeric value should match');
        $this->assertGreaterThan(0, $record->timecreated, 'Timestamp should be set');
    }
    
    /**
     * Test metric logging with JSON payload
     */
    public function test_log_metric_with_payload() {
        global $DB;
        
        $payload = [
            'section' => 'test_section',
            'scores' => [
                'coherence' => 0.85,
                'pattern' => 0.92
            ],
            'metadata' => 'test_data'
        ];
        
        $result = $this->logger->log_metric($this->test_runid, 'complex_metric', 75, $payload);
        $this->assertTrue($result, 'Metric with payload should log successfully');
        
        $record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'complex_metric'
        ]);
        
        $this->assertNotEmpty($record, 'Complex metric should be in database');
        $this->assertEquals(75, $record->metricvaluenum, 'Numeric value should match');
        
        // Verify JSON payload
        $decoded_payload = json_decode($record->payload, true);
        $this->assertIsArray($decoded_payload, 'Payload should be valid JSON');
        $this->assertEquals('test_section', $decoded_payload['section'], 'Section should match');
        $this->assertEquals(0.85, $decoded_payload['scores']['coherence'], 'Coherence score should match');
    }
    
    /**
     * Test phase tracking
     */
    public function test_phase_tracking() {
        global $DB;
        
        // Start a phase
        $result = $this->logger->log_phase_start($this->test_runid, 'test_phase');
        $this->assertTrue($result, 'Phase start should log successfully');
        
        // Simulate some work
        usleep(100000); // Sleep for 100ms
        
        // End the phase
        $result = $this->logger->log_phase_end($this->test_runid, 'test_phase');
        $this->assertTrue($result, 'Phase end should log successfully');
        
        // Check the duration metric
        $record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'phase_duration_test_phase'
        ]);
        
        $this->assertNotEmpty($record, 'Phase duration should be logged');
        $this->assertGreaterThan(90, $record->metricvaluenum, 'Duration should be at least 90ms');
        $this->assertLessThan(200, $record->metricvaluenum, 'Duration should be less than 200ms');
    }
    
    /**
     * Test phase tracking with manual duration
     */
    public function test_phase_with_manual_duration() {
        global $DB;
        
        // Log phase end with manual duration
        $result = $this->logger->log_phase_end($this->test_runid, 'manual_phase', 500.5);
        $this->assertTrue($result, 'Phase with manual duration should log successfully');
        
        $record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'phase_duration_manual_phase'
        ]);
        
        $this->assertNotEmpty($record, 'Manual phase duration should be logged');
        $this->assertEquals(500.5, $record->metricvaluenum, 'Manual duration should match exactly');
    }
    
    /**
     * Test section QA logging
     */
    public function test_section_qa_logging() {
        global $DB;
        
        $scores = [
            'coherence' => 0.87,
            'pattern_alignment' => 0.93,
            'total' => 0.90
        ];
        
        $result = $this->logger->log_section_qa($this->test_runid, 'executive_summary', $scores);
        $this->assertTrue($result, 'Section QA should log successfully');
        
        // Check individual metrics
        $coherence_record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'qa_coherence_executive_summary'
        ]);
        $this->assertNotEmpty($coherence_record, 'Coherence score should be logged');
        $this->assertEquals(0.87, $coherence_record->metricvaluenum, 'Coherence value should match');
        
        $pattern_record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'qa_pattern_executive_summary'
        ]);
        $this->assertNotEmpty($pattern_record, 'Pattern alignment score should be logged');
        $this->assertEquals(0.93, $pattern_record->metricvaluenum, 'Pattern value should match');
        
        $total_record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'qa_total_executive_summary'
        ]);
        $this->assertNotEmpty($total_record, 'Total QA score should be logged');
        $this->assertEquals(0.90, $total_record->metricvaluenum, 'Total value should match');
    }
    
    /**
     * Test aggregate metrics logging
     */
    public function test_aggregate_metrics() {
        global $DB;
        
        $metrics = [
            'coherence_score' => 0.88,
            'pattern_alignment_score' => 0.91,
            'qa_warnings_count' => 3,
            'total_sections' => 12
        ];
        
        $result = $this->logger->log_aggregate_metrics($this->test_runid, $metrics);
        $this->assertTrue($result, 'Aggregate metrics should log successfully');
        
        // Check each metric
        foreach ($metrics as $key => $value) {
            $record = $DB->get_record('local_ci_telemetry', [
                'runid' => $this->test_runid,
                'metrickey' => "aggregate_{$key}"
            ]);
            $this->assertNotEmpty($record, "Aggregate metric {$key} should be logged");
            if (is_numeric($value)) {
                $this->assertEquals($value, $record->metricvaluenum, "Value for {$key} should match");
            }
        }
    }
    
    /**
     * Test payload truncation for large data
     */
    public function test_payload_truncation() {
        global $DB;
        
        // Create a large payload
        $large_data = str_repeat('x', 3000);
        $payload = ['data' => $large_data];
        
        $result = $this->logger->log_metric($this->test_runid, 'large_payload_metric', null, $payload);
        $this->assertTrue($result, 'Large payload should log successfully');
        
        $record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'large_payload_metric'
        ]);
        
        $this->assertNotEmpty($record, 'Large payload metric should be in database');
        $this->assertLessThanOrEqual(2000, strlen($record->payload), 'Payload should be truncated to 2000 chars');
        $this->assertStringEndsWith('...', $record->payload, 'Truncated payload should end with ...');
    }
    
    /**
     * Test feature flag respect
     */
    public function test_feature_flag_disabled() {
        global $DB;
        
        // Disable detailed telemetry
        set_config('enable_detailed_telemetry', '0', 'local_customerintel');
        $this->logger = new telemetry_logger(); // Recreate with new config
        
        // Try to log a metric
        $result = $this->logger->log_metric($this->test_runid, 'disabled_metric', 100);
        $this->assertTrue($result, 'Should return true even when disabled');
        
        // Verify nothing was logged
        $count = $DB->count_records('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'disabled_metric'
        ]);
        $this->assertEquals(0, $count, 'No metrics should be logged when disabled');
    }
    
    /**
     * Test silent failure on database errors
     */
    public function test_silent_failure() {
        // Create logger with invalid data that would cause DB error
        $result = $this->logger->log_metric(null, null, null);
        $this->assertFalse($result, 'Should return false on error');
        
        // Test should complete without throwing exception
        $this->assertTrue(true, 'Silent failure test completed');
    }
    
    /**
     * Test cleanup of old telemetry
     */
    public function test_cleanup_old_telemetry() {
        global $DB;
        
        // Insert old records
        $old_record = new \stdClass();
        $old_record->runid = $this->test_runid - 1;
        $old_record->metrickey = 'old_metric';
        $old_record->timecreated = time() - (35 * 86400); // 35 days old
        $DB->insert_record('local_ci_telemetry', $old_record);
        
        // Insert recent record
        $recent_record = new \stdClass();
        $recent_record->runid = $this->test_runid;
        $recent_record->metrickey = 'recent_metric';
        $recent_record->timecreated = time() - (5 * 86400); // 5 days old
        $DB->insert_record('local_ci_telemetry', $recent_record);
        
        // Run cleanup
        $deleted_count = $this->logger->cleanup_old_telemetry(30);
        $this->assertGreaterThanOrEqual(1, $deleted_count, 'At least one old record should be deleted');
        
        // Verify old record is gone
        $old_exists = $DB->record_exists('local_ci_telemetry', [
            'runid' => $this->test_runid - 1,
            'metrickey' => 'old_metric'
        ]);
        $this->assertFalse($old_exists, 'Old record should be deleted');
        
        // Verify recent record still exists
        $recent_exists = $DB->record_exists('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'recent_metric'
        ]);
        $this->assertTrue($recent_exists, 'Recent record should still exist');
    }
    
    /**
     * Test concurrent phase tracking
     */
    public function test_concurrent_phases() {
        global $DB;
        
        // Start multiple phases
        $this->logger->log_phase_start($this->test_runid, 'phase_a');
        $this->logger->log_phase_start($this->test_runid, 'phase_b');
        
        // End them in different order
        usleep(50000); // 50ms
        $this->logger->log_phase_end($this->test_runid, 'phase_b');
        usleep(50000); // Another 50ms
        $this->logger->log_phase_end($this->test_runid, 'phase_a');
        
        // Check both were tracked correctly
        $phase_a = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'phase_duration_phase_a'
        ]);
        $phase_b = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'phase_duration_phase_b'
        ]);
        
        $this->assertNotEmpty($phase_a, 'Phase A should be logged');
        $this->assertNotEmpty($phase_b, 'Phase B should be logged');
        
        // Phase A should have longer duration (started first, ended last)
        $this->assertGreaterThan($phase_b->metricvaluenum, $phase_a->metricvaluenum, 
            'Phase A should have longer duration');
    }
    
    /**
     * Test JSON encoding of complex objects
     */
    public function test_complex_json_encoding() {
        global $DB;
        
        // Create complex nested structure
        $payload = [
            'user' => [
                'id' => 123,
                'name' => 'Test User',
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true
                ]
            ],
            'scores' => [0.1, 0.2, 0.3, 0.4, 0.5],
            'unicode' => 'Test with émojis 🎉 and special chars: €£¥',
            'null_value' => null,
            'boolean' => false
        ];
        
        $result = $this->logger->log_metric($this->test_runid, 'complex_json', null, $payload);
        $this->assertTrue($result, 'Complex JSON should log successfully');
        
        $record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'complex_json'
        ]);
        
        $decoded = json_decode($record->payload, true);
        $this->assertIsArray($decoded, 'Should decode to array');
        $this->assertEquals('Test User', $decoded['user']['name'], 'Nested values should be preserved');
        $this->assertEquals('dark', $decoded['user']['preferences']['theme'], 'Deep nesting should work');
        $this->assertContains(0.3, $decoded['scores'], 'Array values should be preserved');
        $this->assertStringContainsString('🎉', $record->payload, 'Unicode should be preserved');
    }
}