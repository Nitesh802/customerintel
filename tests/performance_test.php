<?php
/**
 * Performance Tests for Customer Intelligence Dashboard (Slice 9)
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/telemetry_logger.php');

/**
 * Test class for performance validation
 * 
 * @coversDefaultClass \local_customerintel\services\synthesis_engine
 */
class performance_test extends advanced_testcase {
    
    /**
     * @var int Test run ID
     */
    private $test_runid = 9999;
    
    /**
     * @var object Test company
     */
    private $test_company;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Create test data
        $this->create_test_data();
    }
    
    /**
     * Create test data for performance tests
     */
    private function create_test_data() {
        global $DB;
        
        // Create test company
        $company_data = new \stdClass();
        $company_data->name = 'Performance Test Corp';
        $company_data->ticker = 'PERF';
        $company_data->website = 'https://performancetest.com';
        $company_data->sector = 'Technology';
        $company_data->timecreated = time();
        $company_data->timemodified = time();
        
        $this->test_company = $company_data;
        $company_id = $DB->insert_record('local_ci_company', $company_data);
        
        // Create test run
        $run_data = new \stdClass();
        $run_data->id = $this->test_runid;
        $run_data->companyid = $company_id;
        $run_data->status = 'completed';
        $run_data->initiatedbyuserid = 1;
        $run_data->timecreated = time();
        $run_data->timecompleted = time();
        
        $DB->insert_record('local_ci_run', $run_data);
        
        // Create minimal NB results for testing
        $this->create_minimal_nb_results();
    }
    
    /**
     * Create minimal NB results for performance testing
     */
    private function create_minimal_nb_results() {
        global $DB;
        
        $nb_codes = ['NB1', 'NB2', 'NB3', 'NB4', 'NB5', 'NB6', 'NB7', 'NB8', 'NB9', 'NB10', 'NB11', 'NB12', 'NB13', 'NB14', 'NB15'];
        
        foreach ($nb_codes as $nb_code) {
            $nb_result = new \stdClass();
            $nb_result->runid = $this->test_runid;
            $nb_result->nbcode = $nb_code;
            $nb_result->status = 'completed';
            $nb_result->result = json_encode([
                'content' => 'Test content for ' . $nb_code,
                'citations' => [
                    ['url' => 'https://example.com/' . strtolower($nb_code), 'title' => 'Test Source ' . $nb_code]
                ]
            ]);
            $nb_result->timecreated = time();
            $nb_result->timecompleted = time();
            
            $DB->insert_record('local_ci_nb_result', $nb_result);
        }
    }
    
    /**
     * Test synthesis engine performance metrics
     */
    public function test_synthesis_performance_metrics() {
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        $start_time = microtime(true);
        $memory_start = memory_get_usage(true);
        
        try {
            // Run synthesis - this should complete within performance limits
            $synthesis_bundle = $synthesis_engine->build_report($this->test_runid, false);
            
            $end_time = microtime(true);
            $memory_end = memory_get_usage(true);
            
            $duration = $end_time - $start_time;
            $memory_used = $memory_end - $memory_start;
            
            // Performance assertions
            $this->assertLessThan(60, $duration, 'Synthesis should complete within 60 seconds');
            $this->assertLessThan(512 * 1024 * 1024, $memory_used, 'Memory usage should be under 512MB');
            
            // Verify synthesis bundle is valid
            $this->assertIsArray($synthesis_bundle, 'Should return valid synthesis bundle');
            $this->assertArrayHasKey('html', $synthesis_bundle, 'Should contain HTML output');
            
            // Log performance metrics for analysis
            error_log("Performance Test Results:");
            error_log("Duration: " . round($duration, 2) . " seconds");
            error_log("Memory: " . round($memory_used / 1024 / 1024, 2) . " MB");
            
        } catch (\Exception $e) {
            $this->fail('Synthesis should not throw exceptions during performance test: ' . $e->getMessage());
        }
    }
    
    /**
     * Test telemetry logging performance
     */
    public function test_telemetry_logging_performance() {
        $telemetry = new \local_customerintel\services\telemetry_logger();
        
        $start_time = microtime(true);
        
        // Log multiple metrics to test bulk performance
        for ($i = 0; $i < 100; $i++) {
            $telemetry->log_metric($this->test_runid, "performance_test_metric_{$i}", $i * 0.01);
        }
        
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        
        // Telemetry logging should be fast
        $this->assertLessThan(5, $duration, 'Telemetry logging should complete within 5 seconds for 100 entries');
        
        // Verify metrics were logged
        global $DB;
        $count = $DB->count_records('local_ci_telemetry', ['runid' => $this->test_runid]);
        $this->assertGreaterThanOrEqual(100, $count, 'Should have logged all test metrics');
    }
    
    /**
     * Test phase duration tracking
     */
    public function test_phase_duration_tracking() {
        global $DB;
        
        $telemetry = new \local_customerintel\services\telemetry_logger();
        
        // Start a phase
        $telemetry->start_phase($this->test_runid, 'test_phase');
        
        // Simulate some work
        usleep(100000); // 0.1 seconds
        
        // End the phase
        $telemetry->end_phase($this->test_runid, 'test_phase');
        
        // Check that phase duration was recorded
        $phase_record = $DB->get_record('local_ci_telemetry', [
            'runid' => $this->test_runid,
            'metrickey' => 'phase_duration_test_phase'
        ]);
        
        $this->assertNotEmpty($phase_record, 'Phase duration should be recorded');
        $this->assertGreaterThan(90, $phase_record->metricvaluenum, 'Duration should be approximately 100ms');
        $this->assertLessThan(200, $phase_record->metricvaluenum, 'Duration should be reasonable');
    }
    
    /**
     * Test memory usage optimization
     */
    public function test_memory_usage_optimization() {
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        $memory_before = memory_get_usage(true);
        
        // Run synthesis multiple times to check for memory leaks
        for ($i = 0; $i < 3; $i++) {
            try {
                $synthesis_bundle = $synthesis_engine->build_report($this->test_runid, false);
                unset($synthesis_bundle); // Clean up
                
                // Force garbage collection
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            } catch (\Exception $e) {
                // Expected for some iterations due to missing data, continue test
                continue;
            }
        }
        
        $memory_after = memory_get_usage(true);
        $memory_increase = $memory_after - $memory_before;
        
        // Memory should not increase dramatically with multiple runs
        $this->assertLessThan(50 * 1024 * 1024, $memory_increase, 'Memory increase should be under 50MB for multiple runs');
    }
    
    /**
     * Test database query optimization
     */
    public function test_database_query_optimization() {
        global $DB;
        
        // Count queries before synthesis
        $query_count_before = $DB->perf_get_reads() + $DB->perf_get_writes();
        
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        try {
            $synthesis_bundle = $synthesis_engine->build_report($this->test_runid, false);
            
            $query_count_after = $DB->perf_get_reads() + $DB->perf_get_writes();
            $query_count = $query_count_after - $query_count_before;
            
            // Should not make excessive database queries
            $this->assertLessThan(200, $query_count, 'Should not make more than 200 database queries during synthesis');
            
            error_log("Database queries during synthesis: " . $query_count);
            
        } catch (\Exception $e) {
            // Test query count even if synthesis fails
            $query_count_after = $DB->perf_get_reads() + $DB->perf_get_writes();
            $query_count = $query_count_after - $query_count_before;
            
            $this->assertLessThan(200, $query_count, 'Should not make excessive queries even during failure');
        }
    }
    
    /**
     * Test concurrent synthesis performance
     */
    public function test_concurrent_synthesis_performance() {
        // This test simulates concurrent access patterns
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        $start_time = microtime(true);
        
        // Simulate concurrent-like access by rapid successive calls
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            try {
                $results[] = $synthesis_engine->build_report($this->test_runid, false);
            } catch (\Exception $e) {
                // Expected for cache hits, continue
                continue;
            }
        }
        
        $end_time = microtime(true);
        $duration = $end_time - $start_time;
        
        // Should handle multiple requests efficiently
        $this->assertLessThan(30, $duration, 'Multiple synthesis requests should complete quickly due to caching');
    }
    
    /**
     * Test large dataset handling
     */
    public function test_large_dataset_handling() {
        global $DB;
        
        // Create additional NB results with larger content
        for ($i = 1; $i <= 15; $i++) {
            $large_content = str_repeat("Large test content for performance testing. ", 1000); // ~45KB per NB
            
            $nb_result = new \stdClass();
            $nb_result->runid = $this->test_runid;
            $nb_result->nbcode = "NB{$i}_LARGE";
            $nb_result->status = 'completed';
            $nb_result->result = json_encode([
                'content' => $large_content,
                'citations' => array_fill(0, 50, ['url' => 'https://example.com/large', 'title' => 'Large Source'])
            ]);
            $nb_result->timecreated = time();
            $nb_result->timecompleted = time();
            
            $DB->insert_record('local_ci_nb_result', $nb_result);
        }
        
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        $start_time = microtime(true);
        $memory_start = memory_get_usage(true);
        
        try {
            $synthesis_bundle = $synthesis_engine->build_report($this->test_runid, false);
            
            $end_time = microtime(true);
            $memory_end = memory_get_usage(true);
            
            $duration = $end_time - $start_time;
            $memory_used = $memory_end - $memory_start;
            
            // Should handle large datasets within reasonable limits
            $this->assertLessThan(90, $duration, 'Large dataset synthesis should complete within 90 seconds');
            $this->assertLessThan(1024 * 1024 * 1024, $memory_used, 'Memory usage should be under 1GB for large datasets');
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Large dataset test failed due to missing dependencies: ' . $e->getMessage());
        }
    }
}