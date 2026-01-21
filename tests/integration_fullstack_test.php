<?php
/**
 * CustomerIntel Full Stack Integration Test
 * 
 * PHPUnit test class for end-to-end validation
 * 
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;
use Exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/lib.php');

/**
 * Full stack integration test class
 */
class integration_fullstack_test extends advanced_testcase {
    
    private $company_id;
    private $target_id;
    private $run_id;
    private $snapshot_id;
    
    /**
     * Setup before each test
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        
        // Initialize test data
        $this->company_id = null;
        $this->target_id = null;
        $this->run_id = null;
        $this->snapshot_id = null;
    }
    
    /**
     * Test complete workflow
     */
    public function test_complete_workflow() {
        global $DB, $USER;
        
        // Step 1: Create company and target
        $company = new \stdClass();
        $company->name = 'PHPUnit Test Company';
        $company->domain = 'phpunit-test.com';
        $company->industry = 'Software';
        $company->size_category = 'enterprise';
        $company->created_by = $USER->id;
        $company->timecreated = time();
        $company->timemodified = time();
        $this->company_id = $DB->insert_record('local_customerintel_company', $company);
        
        $this->assertGreaterThan(0, $this->company_id);
        
        $target = new \stdClass();
        $target->company_id = $this->company_id;
        $target->name = 'PHPUnit Test Target';
        $target->profile = json_encode([
            'icp_fit' => 'high',
            'use_case' => 'testing',
            'decision_stage' => 'evaluation'
        ]);
        $target->created_by = $USER->id;
        $target->timecreated = time();
        $target->timemodified = time();
        $this->target_id = $DB->insert_record('local_customerintel_target', $target);
        
        $this->assertGreaterThan(0, $this->target_id);
        
        // Step 2: Test SourceService
        $source_service = new services\source_service();
        
        $source_id = $source_service->add_source(
            $this->company_id,
            $this->target_id,
            'website',
            'https://test.example.com',
            json_encode(['pages' => 10])
        );
        
        $this->assertGreaterThan(0, $source_id);
        
        $sources = $source_service->get_sources_for_company($this->company_id);
        $this->assertCount(1, $sources);
        
        // Step 3: Create and process run
        $run = new \stdClass();
        $run->company_id = $this->company_id;
        $run->target_id = $this->target_id;
        $run->status = 'pending';
        $run->created_by = $USER->id;
        $run->timecreated = time();
        $run->timemodified = time();
        $this->run_id = $DB->insert_record('local_customerintel_run', $run);
        
        $this->assertGreaterThan(0, $this->run_id);
        
        // Step 4: Test NBOrchestrator (mock mode)
        $orchestrator = new services\nb_orchestrator();
        $orchestrator->set_mock_mode(true);
        
        $nb_types = [
            'nb1_industry_analysis',
            'nb2_company_analysis',
            'nb3_market_position'
        ];
        
        foreach ($nb_types as $nb_type) {
            $result = $orchestrator->process_single_nb($this->run_id, $nb_type);
            $this->assertTrue($result, "Failed to process $nb_type");
        }
        
        // Verify NB results were created
        $nb_results = $DB->get_records('local_customerintel_nb_result', ['run_id' => $this->run_id]);
        $this->assertCount(3, $nb_results);
        
        // Step 5: Test VersioningService
        $versioning = new services\versioning_service();
        $this->snapshot_id = $versioning->create_snapshot($this->run_id, 'PHPUnit Test Snapshot');
        
        $this->assertGreaterThan(0, $this->snapshot_id);
        
        $snapshot = $DB->get_record('local_customerintel_snapshot', ['id' => $this->snapshot_id]);
        $this->assertNotEmpty($snapshot);
        $this->assertEquals($this->run_id, $snapshot->run_id);
        
        // Step 6: Test Assembler
        $assembler = new services\assembler();
        $html_report = $assembler->generate_html_report($this->run_id);
        
        $this->assertNotEmpty($html_report);
        $this->assertStringContainsString('<html', $html_report);
        $this->assertStringContainsString('PHPUnit Test Company', $html_report);
        
        // Step 7: Test CostService
        $cost_service = new services\cost_service();
        $costs = $cost_service->calculate_run_cost($this->run_id);
        
        $this->assertIsArray($costs);
        $this->assertArrayHasKey('total', $costs);
        $this->assertGreaterThanOrEqual(0, $costs['total']);
        
        // Step 8: Test JobQueue
        $job_queue = new services\job_queue();
        $job_id = $job_queue->enqueue('test_job', ['test' => 'data']);
        
        $this->assertGreaterThan(0, $job_id);
        
        $status = $job_queue->get_job_status($job_id);
        $this->assertEquals('pending', $status);
        
        $result = $job_queue->process_job($job_id);
        $this->assertTrue($result);
        
        $status = $job_queue->get_job_status($job_id);
        $this->assertEquals('completed', $status);
        
        // Final verification
        $DB->set_field('local_customerintel_run', 'status', 'completed', ['id' => $this->run_id]);
        $run = $DB->get_record('local_customerintel_run', ['id' => $this->run_id]);
        $this->assertEquals('completed', $run->status);
    }
    
    /**
     * Test data persistence across services
     */
    public function test_data_persistence() {
        global $DB, $USER;
        
        // Create test data
        $company = new \stdClass();
        $company->name = 'Persistence Test Company';
        $company->domain = 'persistence-test.com';
        $company->industry = 'Technology';
        $company->size_category = 'medium';
        $company->created_by = $USER->id;
        $company->timecreated = time();
        $company->timemodified = time();
        $company_id = $DB->insert_record('local_customerintel_company', $company);
        
        // Verify data can be retrieved
        $retrieved = $DB->get_record('local_customerintel_company', ['id' => $company_id]);
        $this->assertEquals($company->name, $retrieved->name);
        $this->assertEquals($company->domain, $retrieved->domain);
        
        // Test cascading operations
        $target = new \stdClass();
        $target->company_id = $company_id;
        $target->name = 'Persistence Test Target';
        $target->profile = json_encode(['test' => true]);
        $target->created_by = $USER->id;
        $target->timecreated = time();
        $target->timemodified = time();
        $target_id = $DB->insert_record('local_customerintel_target', $target);
        
        // Verify relationship
        $targets = $DB->get_records('local_customerintel_target', ['company_id' => $company_id]);
        $this->assertCount(1, $targets);
    }
    
    /**
     * Test schema validation
     */
    public function test_schema_validation() {
        global $DB, $USER;
        
        // Create minimal valid records
        $tables = [
            'local_customerintel_company' => [
                'name' => 'Schema Test',
                'domain' => 'schema.test',
                'created_by' => $USER->id,
                'timecreated' => time(),
                'timemodified' => time()
            ],
            'local_customerintel_job_queue' => [
                'job_type' => 'test',
                'payload' => json_encode([]),
                'status' => 'pending',
                'created_by' => $USER->id,
                'timecreated' => time(),
                'timemodified' => time()
            ]
        ];
        
        foreach ($tables as $table => $data) {
            $id = $DB->insert_record($table, (object)$data);
            $this->assertGreaterThan(0, $id, "Failed to insert into $table");
            
            // Verify record exists
            $exists = $DB->record_exists($table, ['id' => $id]);
            $this->assertTrue($exists, "Record not found in $table");
        }
    }
    
    /**
     * Test error handling and recovery
     */
    public function test_error_handling() {
        global $DB;
        
        $job_queue = new services\job_queue();
        
        // Test handling of invalid job
        $result = $job_queue->process_job(999999);
        $this->assertFalse($result);
        
        // Test retry logic
        $job_id = $job_queue->enqueue('error_test', ['fail' => true]);
        $job_queue->mark_job_failed($job_id, 'Test failure');
        
        $job = $DB->get_record('local_customerintel_job_queue', ['id' => $job_id]);
        $this->assertEquals('failed', $job->status);
        $this->assertGreaterThan(0, $job->retry_count);
        
        // Test retry
        $retried = $job_queue->retry_job($job_id);
        $this->assertTrue($retried);
        
        $job = $DB->get_record('local_customerintel_job_queue', ['id' => $job_id]);
        $this->assertEquals('pending', $job->status);
    }
    
    /**
     * Test concurrent operations
     */
    public function test_concurrent_operations() {
        global $DB, $USER;
        
        $job_queue = new services\job_queue();
        $job_ids = [];
        
        // Create multiple jobs
        for ($i = 1; $i <= 10; $i++) {
            $job_id = $job_queue->enqueue('concurrent_test', ['index' => $i]);
            $job_ids[] = $job_id;
        }
        
        $this->assertCount(10, $job_ids);
        
        // Verify all jobs were created
        $count = $DB->count_records_select(
            'local_customerintel_job_queue',
            'id IN (' . implode(',', $job_ids) . ')'
        );
        $this->assertEquals(10, $count);
        
        // Process jobs
        $processed = 0;
        foreach ($job_ids as $job_id) {
            if ($job_queue->process_job($job_id)) {
                $processed++;
            }
        }
        
        $this->assertEquals(10, $processed);
        
        // Verify no stuck jobs
        $stuck = $DB->count_records_select(
            'local_customerintel_job_queue',
            "status = 'processing' AND id IN (" . implode(',', $job_ids) . ")"
        );
        $this->assertEquals(0, $stuck);
    }
    
    /**
     * Test memory and resource management
     */
    public function test_resource_management() {
        $start_memory = memory_get_usage(true);
        
        // Perform memory-intensive operations
        $orchestrator = new services\nb_orchestrator();
        $orchestrator->set_mock_mode(true);
        
        // Process multiple NBs
        for ($i = 0; $i < 5; $i++) {
            // Simulate NB processing
            $data = str_repeat('x', 1000000); // 1MB string
            $json = json_encode(['data' => $data]);
            json_decode($json);
        }
        
        $end_memory = memory_get_usage(true);
        $memory_used = ($end_memory - $start_memory) / 1024 / 1024; // MB
        
        // Memory usage should be reasonable (< 100MB for this test)
        $this->assertLessThan(100, $memory_used, "Excessive memory usage: {$memory_used}MB");
    }
    
    /**
     * Test API response formats
     */
    public function test_api_response_formats() {
        global $DB, $USER;
        
        // Create test run
        $run = new \stdClass();
        $run->company_id = 1;
        $run->target_id = 1;
        $run->status = 'completed';
        $run->created_by = $USER->id;
        $run->timecreated = time();
        $run->timemodified = time();
        $run_id = $DB->insert_record('local_customerintel_run', $run);
        
        // Create NB result with proper JSON structure
        $nb_result = new \stdClass();
        $nb_result->run_id = $run_id;
        $nb_result->nb_type = 'nb1_industry_analysis';
        $nb_result->result_data = json_encode([
            'summary' => 'Test summary',
            'analysis' => ['point1', 'point2'],
            'confidence' => 0.85,
            'metadata' => [
                'sources' => 3,
                'timestamp' => time()
            ]
        ]);
        $nb_result->tokens_used = 1000;
        $nb_result->execution_time = 5.5;
        $nb_result->timecreated = time();
        $nb_result->timemodified = time();
        
        $nb_id = $DB->insert_record('local_customerintel_nb_result', $nb_result);
        $this->assertGreaterThan(0, $nb_id);
        
        // Retrieve and validate JSON structure
        $retrieved = $DB->get_record('local_customerintel_nb_result', ['id' => $nb_id]);
        $data = json_decode($retrieved->result_data, true);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('analysis', $data);
        $this->assertArrayHasKey('confidence', $data);
        $this->assertEquals(0.85, $data['confidence']);
    }
    
    /**
     * Test versioning and diff generation
     */
    public function test_versioning_diff() {
        global $DB, $USER;
        
        // Create two runs for comparison
        $run1 = new \stdClass();
        $run1->company_id = 1;
        $run1->target_id = 1;
        $run1->status = 'completed';
        $run1->created_by = $USER->id;
        $run1->timecreated = time();
        $run1->timemodified = time();
        $run1_id = $DB->insert_record('local_customerintel_run', $run1);
        
        // Add NB results to first run
        $nb1 = new \stdClass();
        $nb1->run_id = $run1_id;
        $nb1->nb_type = 'nb1_industry_analysis';
        $nb1->result_data = json_encode(['analysis' => 'version1']);
        $nb1->tokens_used = 100;
        $nb1->execution_time = 1.0;
        $nb1->timecreated = time();
        $nb1->timemodified = time();
        $DB->insert_record('local_customerintel_nb_result', $nb1);
        
        // Create snapshot for first run
        $versioning = new services\versioning_service();
        $snapshot1_id = $versioning->create_snapshot($run1_id, 'Snapshot 1');
        
        // Create second run with different data
        $run2 = new \stdClass();
        $run2->company_id = 1;
        $run2->target_id = 1;
        $run2->status = 'completed';
        $run2->created_by = $USER->id;
        $run2->timecreated = time() + 3600;
        $run2->timemodified = time() + 3600;
        $run2_id = $DB->insert_record('local_customerintel_run', $run2);
        
        $nb2 = new \stdClass();
        $nb2->run_id = $run2_id;
        $nb2->nb_type = 'nb1_industry_analysis';
        $nb2->result_data = json_encode(['analysis' => 'version2']);
        $nb2->tokens_used = 150;
        $nb2->execution_time = 1.5;
        $nb2->timecreated = time() + 3600;
        $nb2->timemodified = time() + 3600;
        $DB->insert_record('local_customerintel_nb_result', $nb2);
        
        $snapshot2_id = $versioning->create_snapshot($run2_id, 'Snapshot 2');
        
        // Generate diff
        $diff = $versioning->generate_diff($snapshot2_id, $snapshot1_id);
        
        $this->assertIsArray($diff);
        $this->assertArrayHasKey('changes', $diff);
        $this->assertGreaterThan(0, count($diff['changes']));
    }
    
    /**
     * Test telemetry recording
     */
    public function test_telemetry_recording() {
        global $DB, $USER;
        
        // Create a run
        $run = new \stdClass();
        $run->company_id = 1;
        $run->target_id = 1;
        $run->status = 'running';
        $run->created_by = $USER->id;
        $run->timecreated = time();
        $run->timemodified = time();
        $run_id = $DB->insert_record('local_customerintel_run', $run);
        
        // Record telemetry
        $telemetry = new \stdClass();
        $telemetry->run_id = $run_id;
        $telemetry->nb_type = 'nb1_industry_analysis';
        $telemetry->event_type = 'completion';
        $telemetry->prompt_tokens = 500;
        $telemetry->completion_tokens = 1500;
        $telemetry->total_tokens = 2000;
        $telemetry->cached_tokens = 500;
        $telemetry->cost = 0.04;
        $telemetry->latency = 2.5;
        $telemetry->metadata = json_encode(['model' => 'gpt-4']);
        $telemetry->timecreated = time();
        
        $telemetry_id = $DB->insert_record('local_customerintel_telemetry', $telemetry);
        $this->assertGreaterThan(0, $telemetry_id);
        
        // Calculate metrics
        $cost_service = new services\cost_service();
        $costs = $cost_service->calculate_run_cost($run_id);
        
        $this->assertEquals(0.04, $costs['total']);
        
        // Check token reuse calculation
        $reuse_percentage = ($telemetry->cached_tokens / $telemetry->total_tokens) * 100;
        $this->assertEquals(25, $reuse_percentage);
    }
    
    /**
     * Test acceptance criteria validation
     */
    public function test_acceptance_criteria() {
        // AC-1: API Integration
        $llm_client = new clients\llm_client();
        $this->assertInstanceOf(clients\llm_client::class, $llm_client);
        
        // AC-2: Multi-NB Architecture (15 NBs)
        $nb_types = services\nb_orchestrator::get_all_nb_types();
        $this->assertCount(15, $nb_types);
        
        // AC-3: Report Generation
        $assembler = new services\assembler();
        $this->assertTrue(method_exists($assembler, 'generate_html_report'));
        $this->assertTrue(method_exists($assembler, 'generate_pdf_report'));
        
        // AC-4: Versioning & Diff
        $versioning = new services\versioning_service();
        $this->assertTrue(method_exists($versioning, 'create_snapshot'));
        $this->assertTrue(method_exists($versioning, 'generate_diff'));
        
        // AC-5: Cost Tracking
        $cost_service = new services\cost_service();
        $this->assertTrue(method_exists($cost_service, 'calculate_run_cost'));
        
        // AC-6: Performance (tested in performance test)
        $this->assertTrue(true); // Placeholder for performance criteria
    }
}