<?php
/**
 * Unit tests for Job Queue Service
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/job_queue.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/cost_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');

use local_customerintel\services\job_queue;
use local_customerintel\services\cost_service;
use local_customerintel\services\company_service;

/**
 * Test class for job_queue
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_queue_test extends \advanced_testcase {
    
    /** @var job_queue */
    protected $jobqueue;
    
    /** @var cost_service */
    protected $costservice;
    
    /** @var company_service */
    protected $companyservice;
    
    /** @var int Test company ID */
    protected $companyid;
    
    /** @var int Test user ID */
    protected $userid;
    
    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        
        $this->jobqueue = new job_queue();
        $this->costservice = new cost_service();
        $this->companyservice = new company_service();
        
        // Create test user
        $user = $this->getDataGenerator()->create_user();
        $this->userid = $user->id;
        $this->setUser($user);
        
        // Create test company
        $this->companyid = $this->companyservice->create_company([
            'name' => 'Test Company',
            'ticker' => 'TEST',
            'type' => 'customer',
            'website' => 'https://test.example.com',
            'sector' => 'Technology'
        ]);
        
        // Set default configuration
        set_config('llm_provider', 'gpt-4', 'local_customerintel');
        set_config('cost_warning_threshold', '100', 'local_customerintel');
        set_config('cost_hard_limit', '500', 'local_customerintel');
        set_config('llm_mock_mode', '1', 'local_customerintel'); // Enable mock mode
    }
    
    /**
     * Test queueing a run
     */
    public function test_queue_run() {
        global $DB;
        
        $runid = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        
        $this->assertIsInt($runid);
        $this->assertGreaterThan(0, $runid);
        
        // Verify run was created
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertNotEmpty($run);
        $this->assertEquals($this->companyid, $run->companyid);
        $this->assertEquals($this->userid, $run->userid);
        $this->assertEquals('queued', $run->status);
        $this->assertEquals('full', $run->mode);
        
        // Verify cost estimate was stored
        $this->assertGreaterThan(0, $run->esttokens);
        $this->assertGreaterThan(0, $run->estcost);
        
        // Verify telemetry was recorded
        $telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        $this->assertNotEmpty($telemetry);
    }
    
    /**
     * Test queueing a comparison run
     */
    public function test_queue_comparison_run() {
        global $DB;
        
        // Create target company
        $targetid = $this->companyservice->create_company([
            'name' => 'Target Company',
            'ticker' => 'TARG',
            'type' => 'target',
            'website' => 'https://target.example.com',
            'sector' => 'Finance'
        ]);
        
        $runid = $this->jobqueue->queue_run($this->companyid, $targetid, $this->userid, [
            'mode' => 'comparison'
        ]);
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals($targetid, $run->targetcompanyid);
        $this->assertEquals('comparison', $run->mode);
        
        // Comparison should have higher cost estimate
        $singlerun = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        $singlerundata = $DB->get_record('local_ci_run', ['id' => $singlerun]);
        $this->assertGreaterThan($singlerundata->estcost, $run->estcost);
    }
    
    /**
     * Test cost limit enforcement
     */
    public function test_cost_limit_enforcement() {
        // Set very low cost limit
        set_config('cost_hard_limit', '0.01', 'local_customerintel');
        
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('cost_exceeds_limit');
        
        $this->jobqueue->queue_run($this->companyid, null, $this->userid);
    }
    
    /**
     * Test run status updates
     */
    public function test_update_run_status() {
        global $DB;
        
        $runid = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        
        // Get initial status
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals('queued', $run->status);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->jobqueue);
        $method = $reflection->getMethod('update_run_status');
        $method->setAccessible(true);
        
        // Update to running
        $method->invoke($this->jobqueue, $runid, 'running');
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals('running', $run->status);
        $this->assertNotNull($run->timestarted);
        
        // Update to completed
        $method->invoke($this->jobqueue, $runid, 'completed');
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals('completed', $run->status);
        $this->assertNotNull($run->timecompleted);
        
        // Update to failed with error
        $runid2 = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        $method->invoke($this->jobqueue, $runid2, 'failed', 'Test error message');
        $run2 = $DB->get_record('local_ci_run', ['id' => $runid2]);
        $this->assertEquals('failed', $run2->status);
        $this->assertNotNull($run2->error);
        
        $error = json_decode($run2->error, true);
        $this->assertEquals('Test error message', $error['message']);
    }
    
    /**
     * Test run progress tracking
     */
    public function test_get_run_progress() {
        global $DB;
        
        $runid = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        
        // Update status to running
        $DB->set_field('local_ci_run', 'status', 'running', ['id' => $runid]);
        $DB->set_field('local_ci_run', 'timestarted', time() - 300, ['id' => $runid]);
        
        // Add some completed NBs
        for ($i = 1; $i <= 5; $i++) {
            $nbresult = new \stdClass();
            $nbresult->runid = $runid;
            $nbresult->nbcode = 'NB' . $i;
            $nbresult->status = 'completed';
            $nbresult->jsonpayload = json_encode(['test' => 'data']);
            $nbresult->durationms = 1000;
            $nbresult->tokensused = 500;
            $nbresult->timecreated = time();
            $nbresult->timemodified = time();
            $DB->insert_record('local_ci_nb_result', $nbresult);
        }
        
        // Add current running NB
        $nbresult = new \stdClass();
        $nbresult->runid = $runid;
        $nbresult->nbcode = 'NB6';
        $nbresult->status = 'running';
        $nbresult->timecreated = time();
        $nbresult->timemodified = time();
        $DB->insert_record('local_ci_nb_result', $nbresult);
        
        $progress = $this->jobqueue->get_run_progress($runid);
        
        $this->assertEquals($runid, $progress['run_id']);
        $this->assertEquals('running', $progress['status']);
        $this->assertEquals(5, $progress['completed_nbs']);
        $this->assertEquals(15, $progress['total_nbs']);
        $this->assertEquals('NB6', $progress['current_nb']);
        $this->assertEqualsWithDelta(33.3, $progress['percentage'], 0.1);
        $this->assertNotNull($progress['eta']);
    }
    
    /**
     * Test retry count tracking
     */
    public function test_retry_tracking() {
        global $DB;
        
        $runid = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        
        // Use reflection to access protected methods
        $reflection = new \ReflectionClass($this->jobqueue);
        
        $getretrymethod = $reflection->getMethod('get_retry_count');
        $getretrymethod->setAccessible(true);
        
        $incrementmethod = $reflection->getMethod('increment_retry_count');
        $incrementmethod->setAccessible(true);
        
        // Initial count should be 0
        $count = $getretrymethod->invoke($this->jobqueue, $runid);
        $this->assertEquals(0, $count);
        
        // Increment retry count
        $incrementmethod->invoke($this->jobqueue, $runid);
        $count = $getretrymethod->invoke($this->jobqueue, $runid);
        $this->assertEquals(1, $count);
        
        // Increment again
        $incrementmethod->invoke($this->jobqueue, $runid);
        $count = $getretrymethod->invoke($this->jobqueue, $runid);
        $this->assertEquals(2, $count);
    }
    
    /**
     * Test cancel run
     */
    public function test_cancel_run() {
        global $DB;
        
        // Queue a run
        $runid = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        
        // Cancel should work for queued runs
        $result = $this->jobqueue->cancel_run($runid);
        $this->assertTrue($result);
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals('cancelled', $run->status);
        
        // Queue another run and start it
        $runid2 = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        $DB->set_field('local_ci_run', 'status', 'running', ['id' => $runid2]);
        
        // Cancel should not work for running runs
        $result = $this->jobqueue->cancel_run($runid2);
        $this->assertFalse($result);
        
        $run2 = $DB->get_record('local_ci_run', ['id' => $runid2]);
        $this->assertEquals('running', $run2->status);
    }
    
    /**
     * Test queue statistics
     */
    public function test_get_queue_stats() {
        global $DB;
        
        // Create runs with different statuses
        $statuses = ['queued', 'queued', 'running', 'completed', 'completed', 'failed', 'cancelled'];
        $starttime = time() - 7200;
        
        foreach ($statuses as $status) {
            $run = new \stdClass();
            $run->companyid = $this->companyid;
            $run->initiatedbyuserid = $this->userid;
            $run->userid = $this->userid;
            $run->mode = 'full';
            $run->status = $status;
            $run->esttokens = 10000;
            $run->estcost = 5.00;
            $run->timecreated = $starttime;
            $run->timemodified = time();
            
            if (in_array($status, ['running', 'completed', 'failed'])) {
                $run->timestarted = $starttime + 300;
            }
            
            if (in_array($status, ['completed', 'failed', 'cancelled'])) {
                $run->timecompleted = $starttime + 1800;
            }
            
            $DB->insert_record('local_ci_run', $run);
        }
        
        $stats = $this->jobqueue->get_queue_stats();
        
        $this->assertEquals(2, $stats['queued']);
        $this->assertEquals(1, $stats['running']);
        $this->assertEquals(2, $stats['completed']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(1, $stats['cancelled']);
        
        $this->assertGreaterThan(0, $stats['avg_wait_time']);
        $this->assertGreaterThan(0, $stats['avg_execution_time']);
    }
    
    /**
     * Test cleanup old runs
     */
    public function test_cleanup_old_runs() {
        global $DB;
        
        $oldtime = time() - (100 * 24 * 60 * 60); // 100 days ago
        $recenttime = time() - (10 * 24 * 60 * 60); // 10 days ago
        
        // Create old completed run
        $oldrun = new \stdClass();
        $oldrun->companyid = $this->companyid;
        $oldrun->initiatedbyuserid = $this->userid;
        $oldrun->userid = $this->userid;
        $oldrun->mode = 'full';
        $oldrun->status = 'completed';
        $oldrun->timecreated = $oldtime;
        $oldrun->timestarted = $oldtime + 300;
        $oldrun->timecompleted = $oldtime + 1800;
        $oldrun->timemodified = $oldtime + 1800;
        $oldrunid = $DB->insert_record('local_ci_run', $oldrun);
        
        // Add NB results and telemetry for old run
        $nbresult = new \stdClass();
        $nbresult->runid = $oldrunid;
        $nbresult->nbcode = 'NB1';
        $nbresult->status = 'completed';
        $nbresult->jsonpayload = json_encode(['test' => 'data']);
        $nbresult->timecreated = $oldtime;
        $nbresult->timemodified = $oldtime;
        $DB->insert_record('local_ci_nb_result', $nbresult);
        
        $telemetry = new \stdClass();
        $telemetry->runid = $oldrunid;
        $telemetry->metrickey = 'test_metric';
        $telemetry->metricvaluenum = 100;
        $telemetry->timecreated = $oldtime;
        $DB->insert_record('local_ci_telemetry', $telemetry);
        
        // Create recent run
        $recentrun = new \stdClass();
        $recentrun->companyid = $this->companyid;
        $recentrun->initiatedbyuserid = $this->userid;
        $recentrun->userid = $this->userid;
        $recentrun->mode = 'full';
        $recentrun->status = 'completed';
        $recentrun->timecreated = $recenttime;
        $recentrun->timestarted = $recenttime + 300;
        $recentrun->timecompleted = $recenttime + 1800;
        $recentrun->timemodified = $recenttime + 1800;
        $recentrunid = $DB->insert_record('local_ci_run', $recentrun);
        
        // Cleanup runs older than 90 days
        $cleaned = $this->jobqueue->cleanup_old_runs(90);
        
        $this->assertEquals(1, $cleaned);
        
        // Verify old run was archived
        $oldrunafter = $DB->get_record('local_ci_run', ['id' => $oldrunid]);
        $this->assertEquals('archived', $oldrunafter->status);
        
        // Verify NB results were deleted
        $nbresults = $DB->get_records('local_ci_nb_result', ['runid' => $oldrunid]);
        $this->assertEmpty($nbresults);
        
        // Verify recent run was not affected
        $recentrunafter = $DB->get_record('local_ci_run', ['id' => $recentrunid]);
        $this->assertEquals('completed', $recentrunafter->status);
    }
    
    /**
     * Test failure handling with retry
     */
    public function test_handle_failure_with_retry() {
        global $DB;
        
        $runid = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->jobqueue);
        $method = $reflection->getMethod('handle_failure');
        $method->setAccessible(true);
        
        // Simulate failure
        $exception = new \Exception('Test failure');
        $result = $method->invoke($this->jobqueue, $runid, $exception);
        
        $this->assertFalse($result);
        
        // Check run status updated to retrying
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals('retrying', $run->status);
        
        // Check retry count incremented
        $getretrymethod = $reflection->getMethod('get_retry_count');
        $getretrymethod->setAccessible(true);
        $count = $getretrymethod->invoke($this->jobqueue, $runid);
        $this->assertEquals(1, $count);
        
        // Simulate max retries exceeded
        for ($i = 1; $i < 3; $i++) {
            $result = $method->invoke($this->jobqueue, $runid, $exception);
            $this->assertFalse($result);
        }
        
        // After max retries, should be failed
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals('failed', $run->status);
    }
    
    /**
     * Test NB breakdown retrieval
     */
    public function test_get_nb_breakdown() {
        global $DB;
        
        $runid = $this->jobqueue->queue_run($this->companyid, null, $this->userid);
        
        // Add NB results
        $nbs = ['NB1' => 500, 'NB2' => 600, 'NB3' => 700];
        foreach ($nbs as $nbcode => $tokens) {
            $nbresult = new \stdClass();
            $nbresult->runid = $runid;
            $nbresult->nbcode = $nbcode;
            $nbresult->status = 'completed';
            $nbresult->jsonpayload = json_encode(['test' => 'data']);
            $nbresult->durationms = 1500;
            $nbresult->tokensused = $tokens;
            $nbresult->timecreated = time();
            $nbresult->timemodified = time();
            $DB->insert_record('local_ci_nb_result', $nbresult);
        }
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->jobqueue);
        $method = $reflection->getMethod('get_nb_breakdown');
        $method->setAccessible(true);
        
        $breakdown = $method->invoke($this->jobqueue, $runid);
        
        $this->assertCount(3, $breakdown);
        $this->assertArrayHasKey('NB1', $breakdown);
        $this->assertEquals(500, $breakdown['NB1']['tokens']);
        $this->assertEquals(1500, $breakdown['NB1']['duration_ms']);
        $this->assertEquals('completed', $breakdown['NB1']['status']);
    }
}