<?php
/**
 * Reuse Logic Tests - Customer data reused across Target
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/versioning_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/cost_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/job_queue.php');
require_once($CFG->dirroot . '/local/customerintel/tests/mocks/mock_llm_client.php');

use local_customerintel\services\versioning_service;
use local_customerintel\services\cost_service;
use local_customerintel\services\job_queue;
use local_customerintel\tests\mocks\mock_llm_client;

/**
 * Test class for reuse logic
 * 
 * Tests that customer data is properly reused when analyzing targets
 * 
 * @group local_customerintel
 * @group customerintel_reuse
 */
class reuse_logic_test extends \advanced_testcase {
    
    /** @var versioning_service Versioning service */
    private $versioning_service;
    
    /** @var cost_service Cost service */
    private $cost_service;
    
    /** @var job_queue Job queue service */
    private $job_queue;
    
    /** @var int Customer company ID */
    private $customer_id;
    
    /** @var int Target company ID */
    private $target_id;
    
    /** @var int Test user ID */
    private $test_user_id;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->versioning_service = new versioning_service();
        $this->cost_service = new cost_service();
        $this->job_queue = new job_queue();
        
        // Create test user
        $user = $this->getDataGenerator()->create_user();
        $this->test_user_id = $user->id;
        $this->setUser($user);
        
        // Create customer and target companies
        $this->customer_id = $this->create_test_company('Customer Corp', 'CUST');
        $this->target_id = $this->create_test_company('Target Inc', 'TARG');
        
        // Set freshness window for testing
        set_config('snapshot_freshness_days', 30, 'local_customerintel');
    }
    
    /**
     * Create test company
     */
    private function create_test_company(string $name, string $ticker): int {
        global $DB;
        
        $company = new \stdClass();
        $company->name = $name;
        $company->ticker = $ticker;
        $company->description = "Test company $name";
        $company->domain = strtolower(str_replace(' ', '', $name)) . '.com';
        $company->metadata = json_encode(['industry' => 'Technology']);
        $company->createdbyuserid = $this->test_user_id;
        $company->timecreated = time();
        $company->timemodified = time();
        
        return $DB->insert_record('local_ci_company', $company);
    }
    
    /**
     * Create completed run with snapshot
     */
    private function create_completed_run(int $companyid, int $targetid = null): array {
        global $DB;
        
        // Create run
        $run = new \stdClass();
        $run->companyid = $companyid;
        $run->targetcompanyid = $targetid;
        $run->initiatedbyuserid = $this->test_user_id;
        $run->userid = $this->test_user_id;
        $run->mode = $targetid ? 'comparison' : 'full';
        $run->status = 'completed';
        $run->timestarted = time() - 300;
        $run->timecompleted = time();
        $run->actualtokens = 25000;
        $run->actualcost = 2.5;
        $run->esttokens = 24000;
        $run->estcost = 2.4;
        $run->timecreated = time();
        $run->timemodified = time();
        
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Add NB results for all 15 NBs
        $mock_client = new mock_llm_client();
        for ($i = 1; $i <= 15; $i++) {
            $nbcode = "NB$i";
            $result = new \stdClass();
            $result->runid = $runid;
            $result->nbcode = $nbcode;
            $result->status = 'completed';
            
            $response = $mock_client->execute_prompt("Test for $nbcode");
            $result->jsonpayload = json_encode($response['payload']);
            $result->citations = json_encode($response['citations'] ?? []);
            $result->durationms = 1500 + ($i * 100);
            $result->tokensused = 1000 + ($i * 50);
            $result->timecreated = time();
            $result->timemodified = time();
            
            $DB->insert_record('local_ci_nb_result', $result);
        }
        
        // Create snapshot
        $snapshotid = $this->versioning_service->create_snapshot($runid);
        
        return ['runid' => $runid, 'snapshotid' => $snapshotid];
    }
    
    /**
     * Test snapshot reuse detection
     * 
     * @covers \local_customerintel\services\versioning_service::get_reusable_snapshot
     */
    public function test_reusable_snapshot_detection() {
        // Create initial run for customer
        $run1 = $this->create_completed_run($this->customer_id);
        
        // Check if snapshot can be reused
        $reusable = $this->versioning_service->get_reusable_snapshot($this->customer_id);
        
        $this->assertNotNull($reusable);
        $this->assertEquals($run1['snapshotid'], $reusable);
        
        // Test with old snapshot (beyond freshness window)
        global $DB;
        $DB->set_field('local_ci_snapshot', 'timecreated', 
            time() - (35 * 24 * 60 * 60), // 35 days ago
            ['id' => $run1['snapshotid']]);
        
        $reusable = $this->versioning_service->get_reusable_snapshot($this->customer_id);
        $this->assertNull($reusable);
    }
    
    /**
     * Test cost estimation with reuse
     * 
     * @covers \local_customerintel\services\cost_service::estimate_cost
     */
    public function test_cost_estimation_with_reuse() {
        // Create fresh snapshot for customer
        $this->create_completed_run($this->customer_id);
        
        // Estimate cost without forcing refresh
        $estimate1 = $this->cost_service->estimate_cost($this->customer_id, null, false);
        
        $this->assertArrayHasKey('reuse_savings', $estimate1);
        $this->assertGreaterThan(0, $estimate1['reuse_savings']);
        $this->assertArrayHasKey('reused_nbs', $estimate1);
        $this->assertNotEmpty($estimate1['reused_nbs']);
        
        // Cost should be minimal due to reuse
        $this->assertLessThan(0.5, $estimate1['total_cost']);
        
        // Estimate with force refresh
        $estimate2 = $this->cost_service->estimate_cost($this->customer_id, null, true);
        
        $this->assertEquals(0, $estimate2['reuse_savings']);
        $this->assertArrayNotHasKey('reused_nbs', $estimate2);
        
        // Full cost without reuse
        $this->assertGreaterThan(1.0, $estimate2['total_cost']);
        
        // Verify savings calculation
        $actual_savings = $estimate2['total_cost'] - $estimate1['total_cost'];
        $this->assertEqualsWithDelta($estimate1['reuse_savings'], $actual_savings, 0.1);
    }
    
    /**
     * Test customer data reuse for target comparison
     * 
     * @covers \local_customerintel\services\cost_service::estimate_cost
     */
    public function test_customer_reuse_for_target_comparison() {
        // Create fresh snapshot for customer
        $customer_run = $this->create_completed_run($this->customer_id);
        
        // Estimate cost for customer vs target comparison
        $estimate = $this->cost_service->estimate_cost($this->customer_id, $this->target_id, false);
        
        // Should detect customer data can be reused
        $this->assertArrayHasKey('reuse_savings', $estimate);
        $this->assertGreaterThan(0, $estimate['reuse_savings']);
        
        // Verify it's marked as comparison
        $this->assertTrue($estimate['is_comparison']);
        
        // Cost should be roughly half of full comparison (only target needs processing)
        $full_estimate = $this->cost_service->estimate_cost($this->customer_id, $this->target_id, true);
        $this->assertLessThan($full_estimate['total_cost'] * 0.6, $estimate['total_cost']);
    }
    
    /**
     * Test reuse with multiple targets
     */
    public function test_reuse_across_multiple_targets() {
        global $DB;
        
        // Create customer snapshot
        $customer_run = $this->create_completed_run($this->customer_id);
        
        // Create multiple target companies
        $target2_id = $this->create_test_company('Target 2', 'TGT2');
        $target3_id = $this->create_test_company('Target 3', 'TGT3');
        
        // Queue comparisons with each target
        $runid1 = $this->job_queue->queue_run($this->customer_id, $this->target_id, 
            $this->test_user_id, ['force_refresh' => false]);
        $runid2 = $this->job_queue->queue_run($this->customer_id, $target2_id, 
            $this->test_user_id, ['force_refresh' => false]);
        $runid3 = $this->job_queue->queue_run($this->customer_id, $target3_id, 
            $this->test_user_id, ['force_refresh' => false]);
        
        // All runs should reference the same reused snapshot
        $run1 = $DB->get_record('local_ci_run', ['id' => $runid1]);
        $run2 = $DB->get_record('local_ci_run', ['id' => $runid2]);
        $run3 = $DB->get_record('local_ci_run', ['id' => $runid3]);
        
        // Estimated costs should be similar (only target processing)
        $this->assertEqualsWithDelta($run1->estcost, $run2->estcost, 0.1);
        $this->assertEqualsWithDelta($run2->estcost, $run3->estcost, 0.1);
        
        // All should have lower cost than full comparison
        $full_estimate = $this->cost_service->estimate_cost($this->customer_id, $this->target_id, true);
        $this->assertLessThan($full_estimate['total_cost'] * 0.6, $run1->estcost);
    }
    
    /**
     * Test reuse freshness validation
     */
    public function test_reuse_freshness_validation() {
        global $DB;
        
        // Create snapshot
        $run = $this->create_completed_run($this->customer_id);
        
        // Test with fresh snapshot
        $estimate1 = $this->cost_service->estimate_cost($this->customer_id, $this->target_id);
        $this->assertGreaterThan(0, $estimate1['reuse_savings']);
        
        // Age the snapshot to just within limit (29 days)
        $DB->set_field('local_ci_snapshot', 'timecreated',
            time() - (29 * 24 * 60 * 60),
            ['id' => $run['snapshotid']]);
        
        $estimate2 = $this->cost_service->estimate_cost($this->customer_id, $this->target_id);
        $this->assertGreaterThan(0, $estimate2['reuse_savings']); // Still reusable
        
        // Age beyond limit (31 days)
        $DB->set_field('local_ci_snapshot', 'timecreated',
            time() - (31 * 24 * 60 * 60),
            ['id' => $run['snapshotid']]);
        
        $estimate3 = $this->cost_service->estimate_cost($this->customer_id, $this->target_id);
        $this->assertEquals(0, $estimate3['reuse_savings']); // Too old, no reuse
    }
    
    /**
     * Test partial reuse after failed run
     */
    public function test_partial_reuse_after_failure() {
        global $DB;
        
        // Create partial run (only first 8 NBs completed)
        $run = new \stdClass();
        $run->companyid = $this->customer_id;
        $run->initiatedbyuserid = $this->test_user_id;
        $run->userid = $this->test_user_id;
        $run->mode = 'full';
        $run->status = 'failed';
        $run->timestarted = time() - 300;
        $run->timecompleted = time();
        $run->actualtokens = 12000;
        $run->actualcost = 1.2;
        $run->timecreated = time();
        
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Add only first 8 NBs
        $mock_client = new mock_llm_client();
        for ($i = 1; $i <= 8; $i++) {
            $nbcode = "NB$i";
            $result = new \stdClass();
            $result->runid = $runid;
            $result->nbcode = $nbcode;
            $result->status = 'completed';
            
            $response = $mock_client->execute_prompt("Test for $nbcode");
            $result->jsonpayload = json_encode($response['payload']);
            $result->citations = '[]';
            $result->durationms = 1500;
            $result->tokensused = 1000;
            $result->timecreated = time();
            
            $DB->insert_record('local_ci_nb_result', $result);
        }
        
        // Failed runs should not create snapshots for reuse
        $reusable = $this->versioning_service->get_reusable_snapshot($this->customer_id);
        $this->assertNull($reusable);
        
        // Cost estimate should show no reuse from failed run
        $estimate = $this->cost_service->estimate_cost($this->customer_id);
        $this->assertEquals(0, $estimate['reuse_savings']);
    }
    
    /**
     * Test reuse tracking in telemetry
     */
    public function test_reuse_telemetry_tracking() {
        global $DB;
        
        // Create reusable snapshot
        $customer_run = $this->create_completed_run($this->customer_id);
        
        // Queue run with reuse
        $runid = $this->job_queue->queue_run($this->customer_id, $this->target_id,
            $this->test_user_id, ['force_refresh' => false]);
        
        // Check telemetry for reuse tracking
        $telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        
        $reuse_telemetry = null;
        foreach ($telemetry as $entry) {
            if ($entry->metrickey === 'estimated_cost') {
                $payload = json_decode($entry->payload, true);
                if (isset($payload['reuse_savings'])) {
                    $reuse_telemetry = $payload;
                    break;
                }
            }
        }
        
        $this->assertNotNull($reuse_telemetry);
        $this->assertGreaterThan(0, $reuse_telemetry['reuse_savings']);
    }
    
    /**
     * Test reuse with source changes
     */
    public function test_reuse_invalidation_on_source_change() {
        global $DB;
        
        // Create initial snapshot
        $run1 = $this->create_completed_run($this->customer_id);
        
        // Add source to company
        $source = new \stdClass();
        $source->companyid = $this->customer_id;
        $source->sourcetype = 'url';
        $source->title = 'New Annual Report';
        $source->url = 'https://example.com/annual-report-2024.pdf';
        $source->status = 'approved';
        $source->hash = md5('new_content');
        $source->createdbyuserid = $this->test_user_id;
        $source->timecreated = time();
        $source->timemodified = time();
        
        $DB->insert_record('local_ci_source', $source);
        
        // With forcerefresh=false, should still use old snapshot (sources tracked separately)
        $estimate1 = $this->cost_service->estimate_cost($this->customer_id, null, false);
        $this->assertGreaterThan(0, $estimate1['reuse_savings']);
        
        // With forcerefresh=true, should not reuse
        $estimate2 = $this->cost_service->estimate_cost($this->customer_id, null, true);
        $this->assertEquals(0, $estimate2['reuse_savings']);
    }
    
    /**
     * Test reuse statistics
     */
    public function test_reuse_statistics() {
        // Create customer snapshot
        $this->create_completed_run($this->customer_id);
        
        // Create multiple runs with reuse
        $targets = [];
        for ($i = 1; $i <= 5; $i++) {
            $targets[] = $this->create_test_company("Target $i", "TGT$i");
        }
        
        $total_savings = 0;
        foreach ($targets as $target_id) {
            $estimate = $this->cost_service->estimate_cost($this->customer_id, $target_id, false);
            $total_savings += $estimate['reuse_savings'];
        }
        
        // Total savings should be significant
        $this->assertGreaterThan(5.0, $total_savings);
        
        // Get cost history to verify savings
        $history = $this->cost_service->get_cost_history(10);
        $this->assertArrayHasKey('summary', $history);
    }
    
    /**
     * Test NB-level reuse granularity
     */
    public function test_nb_level_reuse() {
        // Create snapshot
        $this->create_completed_run($this->customer_id);
        
        // Estimate with detailed breakdown
        $estimate = $this->cost_service->estimate_cost($this->customer_id, null, false);
        
        // Check that reused NBs are tracked
        $this->assertArrayHasKey('reused_nbs', $estimate);
        $this->assertCount(15, $estimate['reused_nbs']); // All 15 NBs reused
        
        // Verify breakdown excludes reused NBs
        $this->assertArrayHasKey('breakdown', $estimate);
        $this->assertEmpty($estimate['breakdown']); // No NBs to process
    }
    
    /**
     * Test reuse with different run modes
     */
    public function test_reuse_with_run_modes() {
        global $DB;
        
        // Create full run
        $full_run = $this->create_completed_run($this->customer_id);
        
        // Reuse should work for comparison mode
        $estimate_comparison = $this->cost_service->estimate_cost(
            $this->customer_id, 
            $this->target_id, 
            false
        );
        $this->assertGreaterThan(0, $estimate_comparison['reuse_savings']);
        
        // Create partial run
        $partial_run = new \stdClass();
        $partial_run->companyid = $this->target_id;
        $partial_run->initiatedbyuserid = $this->test_user_id;
        $partial_run->userid = $this->test_user_id;
        $partial_run->mode = 'partial';
        $partial_run->status = 'completed';
        $partial_run->timestarted = time() - 200;
        $partial_run->timecompleted = time();
        $partial_run->actualtokens = 10000;
        $partial_run->actualcost = 1.0;
        $partial_run->timecreated = time();
        
        $partial_runid = $DB->insert_record('local_ci_run', $partial_run);
        
        // Add subset of NBs
        $mock_client = new mock_llm_client();
        foreach (['NB1', 'NB3', 'NB5', 'NB7', 'NB9'] as $nbcode) {
            $result = new \stdClass();
            $result->runid = $partial_runid;
            $result->nbcode = $nbcode;
            $result->status = 'completed';
            $response = $mock_client->execute_prompt("Test $nbcode");
            $result->jsonpayload = json_encode($response['payload']);
            $result->citations = '[]';
            $result->durationms = 1000;
            $result->tokensused = 800;
            $result->timecreated = time();
            
            $DB->insert_record('local_ci_nb_result', $result);
        }
        
        // Create snapshot for partial run
        $this->versioning_service->create_snapshot($partial_runid);
        
        // Partial snapshots can still be reused for what they have
        $reusable = $this->versioning_service->get_reusable_snapshot($this->target_id);
        $this->assertNotNull($reusable);
    }
}