<?php
/**
 * Unit tests for Cost Service
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/cost_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');

use local_customerintel\services\cost_service;
use local_customerintel\services\company_service;

/**
 * Test class for cost_service
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cost_service_test extends \advanced_testcase {
    
    /** @var cost_service */
    protected $costservice;
    
    /** @var company_service */
    protected $companyservice;
    
    /** @var int Test company ID */
    protected $companyid;
    
    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        
        $this->costservice = new cost_service();
        $this->companyservice = new company_service();
        
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
        set_config('cost_warning_threshold', '10', 'local_customerintel');
        set_config('cost_hard_limit', '50', 'local_customerintel');
    }
    
    /**
     * Test cost estimation for single company
     */
    public function test_estimate_cost_single_company() {
        $estimate = $this->costservice->estimate_cost($this->companyid);
        
        $this->assertIsArray($estimate);
        $this->assertArrayHasKey('total_cost', $estimate);
        $this->assertArrayHasKey('total_tokens', $estimate);
        $this->assertArrayHasKey('breakdown', $estimate);
        $this->assertArrayHasKey('can_proceed', $estimate);
        
        // Check breakdown has all NBs
        $this->assertCount(15, $estimate['breakdown']);
        
        // Verify cost is positive
        $this->assertGreaterThan(0, $estimate['total_cost']);
        $this->assertGreaterThan(0, $estimate['total_tokens']);
        
        // Check provider info
        $this->assertArrayHasKey('provider', $estimate);
        $this->assertEquals('gpt-4', $estimate['provider']);
    }
    
    /**
     * Test cost estimation for comparison
     */
    public function test_estimate_cost_comparison() {
        // Create target company
        $targetid = $this->companyservice->create_company([
            'name' => 'Target Company',
            'ticker' => 'TARG',
            'type' => 'target',
            'website' => 'https://target.example.com',
            'sector' => 'Finance'
        ]);
        
        $estimate = $this->costservice->estimate_cost($this->companyid, $targetid);
        
        $this->assertIsArray($estimate);
        $this->assertArrayHasKey('is_comparison', $estimate);
        $this->assertTrue($estimate['is_comparison']);
        
        // Comparison should double the cost
        $singleestimate = $this->costservice->estimate_cost($this->companyid);
        $this->assertEquals($singleestimate['total_cost'] * 2, $estimate['total_cost']);
    }
    
    /**
     * Test warning threshold
     */
    public function test_warning_threshold() {
        // Set low warning threshold
        set_config('cost_warning_threshold', '0.01', 'local_customerintel');
        
        $estimate = $this->costservice->estimate_cost($this->companyid);
        
        $this->assertArrayHasKey('warnings', $estimate);
        $this->assertNotEmpty($estimate['warnings']);
        
        // Find warning message
        $haswarning = false;
        foreach ($estimate['warnings'] as $warning) {
            if ($warning['type'] === 'warning') {
                $haswarning = true;
                break;
            }
        }
        $this->assertTrue($haswarning);
        $this->assertTrue($estimate['can_proceed']);
    }
    
    /**
     * Test hard limit enforcement
     */
    public function test_hard_limit_enforcement() {
        // Set very low hard limit
        set_config('cost_hard_limit', '0.01', 'local_customerintel');
        
        $estimate = $this->costservice->estimate_cost($this->companyid);
        
        $this->assertFalse($estimate['can_proceed']);
        $this->assertArrayHasKey('warnings', $estimate);
        
        // Find error message
        $haserror = false;
        foreach ($estimate['warnings'] as $warning) {
            if ($warning['type'] === 'error' && !empty($warning['block_run'])) {
                $haserror = true;
                break;
            }
        }
        $this->assertTrue($haserror);
    }
    
    /**
     * Test recording actuals
     */
    public function test_record_actuals() {
        global $DB;
        
        // Create a run
        $run = new \stdClass();
        $run->companyid = $this->companyid;
        $run->initiatedbyuserid = get_admin()->id;
        $run->userid = get_admin()->id;
        $run->mode = 'full';
        $run->status = 'running';
        $run->esttokens = 10000;
        $run->estcost = 5.00;
        $run->timestarted = time();
        $run->timecreated = time();
        $run->timemodified = time();
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Record actuals
        $actualtokens = 12000;
        $actualcost = 6.50;
        $nbbreakdown = [
            'NB1' => ['tokens' => 800, 'duration_ms' => 1500, 'cost' => 0.40],
            'NB2' => ['tokens' => 1000, 'duration_ms' => 1800, 'cost' => 0.50]
        ];
        
        $this->costservice->record_actuals($runid, $actualtokens, $actualcost, $nbbreakdown);
        
        // Verify run was updated
        $updatedrun = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals($actualtokens, $updatedrun->actualtokens);
        $this->assertEquals($actualcost, $updatedrun->actualcost);
        
        // Verify telemetry was recorded
        $telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        $this->assertNotEmpty($telemetry);
        
        // Check for cost telemetry
        $hascosttelemetry = false;
        foreach ($telemetry as $entry) {
            if ($entry->metrickey === 'actual_cost') {
                $hascosttelemetry = true;
                $this->assertEquals($actualcost, $entry->metricvaluenum);
                break;
            }
        }
        $this->assertTrue($hascosttelemetry);
    }
    
    /**
     * Test variance calculation
     */
    public function test_calculate_variance() {
        // Test positive variance (overrun)
        $variance = $this->costservice->calculate_variance(100, 120);
        $this->assertEquals(20, $variance);
        
        // Test negative variance (underrun)
        $variance = $this->costservice->calculate_variance(100, 80);
        $this->assertEquals(-20, $variance);
        
        // Test zero variance
        $variance = $this->costservice->calculate_variance(100, 100);
        $this->assertEquals(0, $variance);
        
        // Test division by zero
        $variance = $this->costservice->calculate_variance(0, 100);
        $this->assertEquals(100, $variance);
    }
    
    /**
     * Test cost history retrieval
     */
    public function test_get_cost_history() {
        global $DB;
        
        // Create multiple completed runs
        for ($i = 0; $i < 5; $i++) {
            $run = new \stdClass();
            $run->companyid = $this->companyid;
            $run->initiatedbyuserid = get_admin()->id;
            $run->userid = get_admin()->id;
            $run->mode = 'full';
            $run->status = 'completed';
            $run->esttokens = 10000 + ($i * 100);
            $run->estcost = 5.00 + ($i * 0.10);
            $run->actualtokens = 11000 + ($i * 150);
            $run->actualcost = 5.50 + ($i * 0.15);
            $run->timestarted = time() - 3600;
            $run->timecompleted = time() - 1800;
            $run->timecreated = time() - 7200;
            $run->timemodified = time();
            $DB->insert_record('local_ci_run', $run);
        }
        
        $history = $this->costservice->get_cost_history(10);
        
        $this->assertArrayHasKey('runs', $history);
        $this->assertArrayHasKey('summary', $history);
        
        $this->assertCount(5, $history['runs']);
        $this->assertEquals(5, $history['summary']['total_runs']);
        $this->assertGreaterThan(0, $history['summary']['total_cost']);
        $this->assertGreaterThan(0, $history['summary']['avg_cost']);
    }
    
    /**
     * Test token cost calculation
     */
    public function test_calculate_token_cost() {
        // Test with default provider (gpt-4)
        $cost = $this->costservice->calculate_token_cost(1000, 'input');
        $expectedcost = 1000 / 1000 * 0.03; // GPT-4 input pricing
        $this->assertEquals($expectedcost, $cost);
        
        $cost = $this->costservice->calculate_token_cost(1000, 'output');
        $expectedcost = 1000 / 1000 * 0.06; // GPT-4 output pricing
        $this->assertEquals($expectedcost, $cost);
    }
    
    /**
     * Test dashboard data preparation
     */
    public function test_get_dashboard_data() {
        global $DB;
        
        // Create test runs with different statuses
        $statuses = ['completed', 'completed', 'failed', 'running'];
        foreach ($statuses as $i => $status) {
            $run = new \stdClass();
            $run->companyid = $this->companyid;
            $run->initiatedbyuserid = get_admin()->id;
            $run->userid = get_admin()->id;
            $run->mode = 'full';
            $run->status = $status;
            $run->esttokens = 10000;
            $run->estcost = 5.00;
            $run->actualtokens = $status === 'completed' ? 11000 : null;
            $run->actualcost = $status === 'completed' ? 5.50 : null;
            $run->timestarted = time() - 3600;
            $run->timecompleted = $status === 'completed' ? time() - 1800 : null;
            $run->timecreated = time() - 7200;
            $run->timemodified = time();
            $DB->insert_record('local_ci_run', $run);
        }
        
        $dashboard = $this->costservice->get_dashboard_data(5);
        
        $this->assertArrayHasKey('runs', $dashboard);
        $this->assertArrayHasKey('summary', $dashboard);
        
        // Should only include completed runs
        $this->assertCount(2, $dashboard['runs']);
        $this->assertEquals(2, $dashboard['summary']['total_runs']);
        
        // Check run data format
        if (!empty($dashboard['runs'])) {
            $firstrun = $dashboard['runs'][0];
            $this->assertArrayHasKey('run_id', $firstrun);
            $this->assertArrayHasKey('company', $firstrun);
            $this->assertArrayHasKey('estimated_cost', $firstrun);
            $this->assertArrayHasKey('actual_cost', $firstrun);
            $this->assertArrayHasKey('variance', $firstrun);
        }
    }
    
    /**
     * Test reuse detection
     */
    public function test_reusable_data_detection() {
        global $DB;
        
        // Create a recent snapshot
        $run = new \stdClass();
        $run->companyid = $this->companyid;
        $run->initiatedbyuserid = get_admin()->id;
        $run->userid = get_admin()->id;
        $run->mode = 'full';
        $run->status = 'completed';
        $run->esttokens = 10000;
        $run->estcost = 5.00;
        $run->actualtokens = 10500;
        $run->actualcost = 5.25;
        $run->timestarted = time() - 7200;
        $run->timecompleted = time() - 3600;
        $run->timecreated = time() - 10800;
        $run->timemodified = time();
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Create snapshot
        $snapshot = new \stdClass();
        $snapshot->companyid = $this->companyid;
        $snapshot->runid = $runid;
        $snapshot->snapshotjson = json_encode(['test' => 'data']);
        $snapshot->timecreated = time() - 3600; // Recent
        $snapshot->timemodified = time();
        $DB->insert_record('local_ci_snapshot', $snapshot);
        
        // Test estimation with reuse
        $estimate = $this->costservice->estimate_cost($this->companyid, null, false);
        
        $this->assertArrayHasKey('reuse_savings', $estimate);
        if ($estimate['reuse_savings'] > 0) {
            $this->assertArrayHasKey('reused_nbs', $estimate);
            $this->assertNotEmpty($estimate['reused_nbs']);
        }
    }
    
    /**
     * Test can_proceed method
     */
    public function test_can_proceed() {
        // Test within limit
        set_config('cost_hard_limit', '100', 'local_customerintel');
        $this->assertTrue($this->costservice->can_proceed(50));
        $this->assertTrue($this->costservice->can_proceed(100));
        
        // Test exceeding limit
        $this->assertFalse($this->costservice->can_proceed(101));
    }
    
    /**
     * Test cost report generation
     */
    public function test_get_run_cost_report() {
        global $DB;
        
        // Create a completed run
        $run = new \stdClass();
        $run->companyid = $this->companyid;
        $run->initiatedbyuserid = get_admin()->id;
        $run->userid = get_admin()->id;
        $run->mode = 'full';
        $run->status = 'completed';
        $run->esttokens = 10000;
        $run->estcost = 5.00;
        $run->actualtokens = 11000;
        $run->actualcost = 5.50;
        $run->timestarted = time() - 3600;
        $run->timecompleted = time();
        $run->timecreated = time() - 7200;
        $run->timemodified = time();
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Add telemetry data
        $telemetry = new \stdClass();
        $telemetry->runid = $runid;
        $telemetry->metrickey = 'nb_NB1_tokens';
        $telemetry->metricvaluenum = 800;
        $telemetry->payload = json_encode([
            'cost' => 0.40,
            'duration_ms' => 1500,
            'input_tokens' => 500,
            'output_tokens' => 300
        ]);
        $telemetry->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $telemetry);
        
        $report = $this->costservice->get_run_cost_report($runid);
        
        $this->assertEquals($runid, $report['run_id']);
        $this->assertEquals($this->companyid, $report['company_id']);
        $this->assertEquals('completed', $report['status']);
        
        $this->assertArrayHasKey('estimated', $report);
        $this->assertArrayHasKey('actual', $report);
        $this->assertArrayHasKey('variance', $report);
        $this->assertArrayHasKey('nb_breakdown', $report);
        
        $this->assertEquals(5.00, $report['estimated']['cost']);
        $this->assertEquals(5.50, $report['actual']['cost']);
        
        // Check variance calculation
        $this->assertEquals(10, $report['variance']['cost_pct']);
        $this->assertEquals(0.50, $report['variance']['cost_amount']);
    }
}