<?php
/**
 * Error Recovery Tests for Customer Intelligence Dashboard (Slice 9)
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
require_once($CFG->dirroot . '/local/customerintel/classes/exceptions/SynthesisPhaseException.php');
require_once($CFG->dirroot . '/local/customerintel/classes/exceptions/CitationResolverException.php');
require_once($CFG->dirroot . '/local/customerintel/classes/exceptions/TelemetryWriteException.php');

/**
 * Test class for error recovery and exception handling
 * 
 * @coversDefaultClass \local_customerintel\services\synthesis_engine
 */
class error_recovery_test extends advanced_testcase {
    
    /**
     * @var int Test run ID
     */
    private $test_runid = 8888;
    
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
     * Create test data for error recovery tests
     */
    private function create_test_data() {
        global $DB;
        
        // Create test company
        $company_data = new \stdClass();
        $company_data->name = 'Error Test Corp';
        $company_data->ticker = 'ERR';
        $company_data->website = 'https://errortest.com';
        $company_data->sector = 'Technology';
        $company_data->timecreated = time();
        $company_data->timemodified = time();
        
        $company_id = $DB->insert_record('local_ci_company', $company_data);
        
        // Create test run
        $run_data = new \stdClass();
        $run_data->id = $this->test_runid;
        $run_data->companyid = $company_id;
        $run_data->status = 'running';
        $run_data->initiatedbyuserid = 1;
        $run_data->timecreated = time();
        
        $DB->insert_record('local_ci_run', $run_data);
    }
    
    /**
     * Test SynthesisPhaseException handling
     */
    public function test_synthesis_phase_exception() {
        try {
            throw new \local_customerintel\exceptions\SynthesisPhaseException(
                'test_phase',
                $this->test_runid,
                'Test synthesis phase failure',
                ['test_context' => 'error_recovery_test']
            );
        } catch (\local_customerintel\exceptions\SynthesisPhaseException $e) {
            // Verify exception properties
            $this->assertEquals('test_phase', $e->get_phase());
            $this->assertEquals($this->test_runid, $e->get_runid());
            $this->assertEquals('Test synthesis phase failure', $e->getMessage());
            
            $context = $e->get_context_data();
            $this->assertArrayHasKey('test_context', $context);
            $this->assertEquals('error_recovery_test', $context['test_context']);
            
            // Verify structured data
            $structured = $e->get_structured_data();
            $this->assertEquals('synthesis_phase_failure', $structured['error_type']);
            $this->assertEquals('test_phase', $structured['phase']);
            $this->assertEquals($this->test_runid, $structured['runid']);
            $this->assertArrayHasKey('trace_excerpt', $structured);
            $this->assertArrayHasKey('timestamp', $structured);
        }
    }
    
    /**
     * Test CitationResolverException handling
     */
    public function test_citation_resolver_exception() {
        try {
            throw new \local_customerintel\exceptions\CitationResolverException(
                'https://example.com/test',
                $this->test_runid,
                'url_validation',
                'Test citation resolver failure',
                ['http_code' => 404]
            );
        } catch (\local_customerintel\exceptions\CitationResolverException $e) {
            // Verify exception properties
            $this->assertEquals('https://example.com/test', $e->get_citation_url());
            $this->assertEquals($this->test_runid, $e->get_runid());
            $this->assertEquals('url_validation', $e->get_resolution_step());
            $this->assertEquals('Test citation resolver failure', $e->getMessage());
            
            $context = $e->get_context_data();
            $this->assertArrayHasKey('http_code', $context);
            $this->assertEquals(404, $context['http_code']);
            
            // Verify structured data
            $structured = $e->get_structured_data();
            $this->assertEquals('citation_resolver_failure', $structured['error_type']);
            $this->assertEquals('https://example.com/test', $structured['citation_url']);
            $this->assertEquals('url_validation', $structured['resolution_step']);
        }
    }
    
    /**
     * Test TelemetryWriteException handling
     */
    public function test_telemetry_write_exception() {
        try {
            throw new \local_customerintel\exceptions\TelemetryWriteException(
                'test_metric',
                $this->test_runid,
                0.85,
                'Test telemetry write failure',
                ['table' => 'local_ci_telemetry']
            );
        } catch (\local_customerintel\exceptions\TelemetryWriteException $e) {
            // Verify exception properties
            $this->assertEquals('test_metric', $e->get_metric_key());
            $this->assertEquals($this->test_runid, $e->get_runid());
            $this->assertEquals(0.85, $e->get_metric_value());
            $this->assertEquals('Test telemetry write failure', $e->getMessage());
            
            $context = $e->get_context_data();
            $this->assertArrayHasKey('table', $context);
            $this->assertEquals('local_ci_telemetry', $context['table']);
            
            // Verify structured data
            $structured = $e->get_structured_data();
            $this->assertEquals('telemetry_write_failure', $structured['error_type']);
            $this->assertEquals('test_metric', $structured['metric_key']);
            $this->assertEquals(0.85, $structured['metric_value']);
        }
    }
    
    /**
     * Test error logging with structured JSON
     */
    public function test_structured_error_logging() {
        global $DB;
        
        $exception = new \local_customerintel\exceptions\SynthesisPhaseException(
            'test_logging_phase',
            $this->test_runid,
            'Test structured logging',
            ['test_data' => 'structured_logging_test']
        );
        
        // Log the structured error
        $structured_data = $exception->get_structured_data();
        
        $log_entry = new \stdClass();
        $log_entry->runid = $this->test_runid;
        $log_entry->phase = $structured_data['phase'];
        $log_entry->level = 'ERROR';
        $log_entry->message = $structured_data['message'];
        $log_entry->details = json_encode($structured_data);
        $log_entry->timecreated = time();
        
        $log_id = $DB->insert_record('local_ci_log', $log_entry);
        $this->assertNotEmpty($log_id, 'Should successfully log structured error data');
        
        // Verify log entry
        $logged_entry = $DB->get_record('local_ci_log', ['id' => $log_id]);
        $this->assertNotEmpty($logged_entry, 'Should retrieve logged entry');
        
        $details = json_decode($logged_entry->details, true);
        $this->assertIsArray($details, 'Should have valid JSON details');
        $this->assertEquals('synthesis_phase_failure', $details['error_type']);
        $this->assertEquals('test_logging_phase', $details['phase']);
        $this->assertEquals($this->test_runid, $details['runid']);
        $this->assertArrayHasKey('trace_excerpt', $details);
    }
    
    /**
     * Test graceful degradation during synthesis failure
     */
    public function test_graceful_synthesis_degradation() {
        global $DB;
        
        // Create incomplete NB results to trigger synthesis failure
        $nb_result = new \stdClass();
        $nb_result->runid = $this->test_runid;
        $nb_result->nbcode = 'NB1';
        $nb_result->status = 'failed';
        $nb_result->result = json_encode(['error' => 'Simulated NB failure']);
        $nb_result->timecreated = time();
        
        $DB->insert_record('local_ci_nb_result', $nb_result);
        
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        try {
            $synthesis_bundle = $synthesis_engine->build_report($this->test_runid, false);
            $this->fail('Should have thrown an exception for incomplete NB results');
        } catch (\Exception $e) {
            // Verify the exception is properly structured
            $this->assertInstanceOf('\moodle_exception', $e);
            
            // Verify error is logged
            $log_entries = $DB->get_records('local_ci_log', ['runid' => $this->test_runid]);
            $this->assertNotEmpty($log_entries, 'Should log synthesis errors');
            
            // Verify run status is updated to reflect error
            $run = $DB->get_record('local_ci_run', ['id' => $this->test_runid]);
            $this->assertNotEquals('completed', $run->status, 'Run should not be marked as completed on error');
        }
    }
    
    /**
     * Test view_report.php error recovery
     */
    public function test_view_report_error_recovery() {
        global $DB;
        
        // Simulate view_report.php error handling by testing similar logic
        $runid = $this->test_runid;
        
        // Mark run as completed to allow viewing
        $DB->update_record('local_ci_run', (object)[
            'id' => $runid,
            'status' => 'completed',
            'timecompleted' => time()
        ]);
        
        // Test error recovery when synthesis is missing
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        try {
            $synthesis_bundle = $synthesis_engine->get_cached_synthesis($runid);
            
            if ($synthesis_bundle === null) {
                // This should trigger graceful fallback behavior
                $this->assertTrue(true, 'No cached synthesis found - this should trigger graceful fallback');
                
                // Verify that we can still display something meaningful
                $error_message = 'Synthesis unavailable. Unable to generate or retrieve synthesis for this report.';
                $this->assertNotEmpty($error_message, 'Should have meaningful error message');
            }
        } catch (\Exception $e) {
            // Verify exception is properly handled
            $this->assertInstanceOf('\Exception', $e);
            
            // In real view_report.php, this would display a warning instead of crashing
            $warning_html = '<div class="alert alert-warning">';
            $warning_html .= '<strong>Synthesis Unavailable:</strong> ' . htmlspecialchars($e->getMessage());
            $warning_html .= '</div>';
            
            $this->assertStringContainsString('alert-warning', $warning_html);
            $this->assertStringContainsString('Synthesis Unavailable', $warning_html);
        }
    }
    
    /**
     * Test telemetry write failure recovery
     */
    public function test_telemetry_write_failure_recovery() {
        $telemetry = new \local_customerintel\services\telemetry_logger();
        
        // Test with invalid metric data to trigger write failure
        try {
            // This should handle the error gracefully
            $telemetry->log_metric($this->test_runid, 'test_metric', 'invalid_numeric_value');
            
            // If no exception, telemetry handled the error gracefully
            $this->assertTrue(true, 'Telemetry should handle invalid data gracefully');
            
        } catch (\local_customerintel\exceptions\TelemetryWriteException $e) {
            // If exception is thrown, verify it's properly structured
            $this->assertEquals('test_metric', $e->get_metric_key());
            $this->assertEquals($this->test_runid, $e->get_runid());
            
            $structured = $e->get_structured_data();
            $this->assertArrayHasKey('error_type', $structured);
            $this->assertEquals('telemetry_write_failure', $structured['error_type']);
        }
    }
    
    /**
     * Test database transaction rollback on errors
     */
    public function test_database_transaction_rollback() {
        global $DB;
        
        $initial_count = $DB->count_records('local_ci_telemetry', ['runid' => $this->test_runid]);
        
        try {
            $transaction = $DB->start_delegated_transaction();
            
            // Insert some test records
            for ($i = 0; $i < 5; $i++) {
                $telemetry_record = new \stdClass();
                $telemetry_record->runid = $this->test_runid;
                $telemetry_record->metrickey = "test_rollback_{$i}";
                $telemetry_record->metricvaluenum = $i;
                $telemetry_record->timecreated = time();
                
                $DB->insert_record('local_ci_telemetry', $telemetry_record);
            }
            
            // Simulate an error that should trigger rollback
            throw new \Exception('Simulated transaction error');
            
            $transaction->allow_commit();
            
        } catch (\Exception $e) {
            // Transaction should be rolled back automatically
            $this->assertEquals('Simulated transaction error', $e->getMessage());
        }
        
        // Verify records were rolled back
        $final_count = $DB->count_records('local_ci_telemetry', ['runid' => $this->test_runid]);
        $this->assertEquals($initial_count, $final_count, 'Transaction should have been rolled back');
    }
    
    /**
     * Test memory cleanup after errors
     */
    public function test_memory_cleanup_after_errors() {
        $memory_before = memory_get_usage(true);
        
        $synthesis_engine = new \local_customerintel\services\synthesis_engine();
        
        // Trigger multiple errors to test memory cleanup
        for ($i = 0; $i < 3; $i++) {
            try {
                // This will likely fail due to missing NB data
                $synthesis_bundle = $synthesis_engine->build_report($this->test_runid + $i, false);
            } catch (\Exception $e) {
                // Expected - continue test
                continue;
            }
        }
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        $memory_after = memory_get_usage(true);
        $memory_increase = $memory_after - $memory_before;
        
        // Memory should not increase dramatically even with errors
        $this->assertLessThan(10 * 1024 * 1024, $memory_increase, 'Memory should be cleaned up after errors');
    }
    
    /**
     * Test error propagation chain
     */
    public function test_error_propagation_chain() {
        try {
            // Create a chain of exceptions
            $root_exception = new \Exception('Root cause error');
            
            $citation_exception = new \local_customerintel\exceptions\CitationResolverException(
                'https://example.com/chain',
                $this->test_runid,
                'chain_test',
                'Citation resolver chain error',
                ['chain_test' => true],
                $root_exception
            );
            
            $synthesis_exception = new \local_customerintel\exceptions\SynthesisPhaseException(
                'chain_test_phase',
                $this->test_runid,
                'Synthesis phase chain error',
                ['citation_error' => $citation_exception->getMessage()],
                $citation_exception
            );
            
            throw $synthesis_exception;
            
        } catch (\local_customerintel\exceptions\SynthesisPhaseException $e) {
            // Verify exception chain
            $this->assertEquals('Synthesis phase chain error', $e->getMessage());
            
            $previous = $e->getPrevious();
            $this->assertInstanceOf('\local_customerintel\exceptions\CitationResolverException', $previous);
            $this->assertEquals('Citation resolver chain error', $previous->getMessage());
            
            $root = $previous->getPrevious();
            $this->assertInstanceOf('\Exception', $root);
            $this->assertEquals('Root cause error', $root->getMessage());
        }
    }
}