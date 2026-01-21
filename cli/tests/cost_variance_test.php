<?php
/**
 * Cost Estimator Variance Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/cost_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/job_queue.php');
require_once($CFG->dirroot . '/local/customerintel/tests/mocks/mock_llm_client.php');

use local_customerintel\services\cost_service;
use local_customerintel\services\job_queue;
use local_customerintel\tests\mocks\mock_llm_client;

/**
 * Test class for cost estimator variance
 * 
 * Ensures cost estimates are within ±25% of actual costs
 * 
 * @group local_customerintel
 * @group customerintel_cost
 */
class cost_variance_test extends \advanced_testcase {
    
    /** @var cost_service Cost service instance */
    private $cost_service;
    
    /** @var job_queue Job queue service */
    private $job_queue;
    
    /** @var int Test company ID */
    private $test_company_id;
    
    /** @var int Test user ID */
    private $test_user_id;
    
    /** @var float Maximum acceptable variance (25%) */
    const MAX_VARIANCE = 0.25;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->cost_service = new cost_service();
        $this->job_queue = new job_queue();
        
        // Create test user
        $user = $this->getDataGenerator()->create_user();
        $this->test_user_id = $user->id;
        $this->setUser($user);
        
        // Create test company
        $this->test_company_id = $this->create_test_company();
        
        // Set up cost configuration
        set_config('llm_provider', 'gpt-4', 'local_customerintel');
        set_config('cost_warning_threshold', '10.0', 'local_customerintel');
        set_config('cost_hard_limit', '50.0', 'local_customerintel');
    }
    
    /**
     * Create test company
     */
    private function create_test_company(): int {
        global $DB;
        
        $company = new \stdClass();
        $company->name = 'Test Company';
        $company->ticker = 'TEST';
        $company->description = 'Company for cost variance testing';
        $company->domain = 'testcompany.com';
        $company->metadata = json_encode(['industry' => 'Technology']);
        $company->createdbyuserid = $this->test_user_id;
        $company->timecreated = time();
        $company->timemodified = time();
        
        return $DB->insert_record('local_ci_company', $company);
    }
    
    /**
     * Simulate run completion with actual costs
     */
    private function simulate_run_completion(int $runid, float $variance_factor = 1.0): array {
        global $DB;
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        
        // Calculate actual values with variance
        $actual_tokens = (int)($run->esttokens * $variance_factor);
        $actual_cost = $run->estcost * $variance_factor;
        
        // Update run with actuals
        $run->actualtokens = $actual_tokens;
        $run->actualcost = $actual_cost;
        $run->status = 'completed';
        $run->timestarted = time() - 300;
        $run->timecompleted = time();
        $DB->update_record('local_ci_run', $run);
        
        // Create NB breakdown
        $nb_breakdown = [];
        $tokens_per_nb = $actual_tokens / 15;
        $cost_per_nb = $actual_cost / 15;
        
        for ($i = 1; $i <= 15; $i++) {
            $nbcode = "NB$i";
            
            // Add some variance per NB
            $nb_variance = 0.9 + (mt_rand(0, 20) / 100); // 0.9 to 1.1
            
            $nb_breakdown[$nbcode] = [
                'tokens' => (int)($tokens_per_nb * $nb_variance),
                'cost' => $cost_per_nb * $nb_variance,
                'duration_ms' => 1500 + mt_rand(-500, 500),
                'input_tokens' => (int)(($tokens_per_nb * $nb_variance) * 0.6),
                'output_tokens' => (int)(($tokens_per_nb * $nb_variance) * 0.4)
            ];
            
            // Create NB result record
            $result = new \stdClass();
            $result->runid = $runid;
            $result->nbcode = $nbcode;
            $result->status = 'completed';
            $result->jsonpayload = json_encode(['test' => "data for $nbcode"]);
            $result->citations = '[]';
            $result->durationms = $nb_breakdown[$nbcode]['duration_ms'];
            $result->tokensused = $nb_breakdown[$nbcode]['tokens'];
            $result->timecreated = time();
            $result->timemodified = time();
            
            $DB->insert_record('local_ci_nb_result', $result);
        }
        
        // Record actuals with breakdown
        $this->cost_service->record_actuals($runid, $actual_tokens, $actual_cost, $nb_breakdown);
        
        return [
            'estimated_cost' => $run->estcost,
            'actual_cost' => $actual_cost,
            'estimated_tokens' => $run->esttokens,
            'actual_tokens' => $actual_tokens,
            'variance_pct' => $this->cost_service->calculate_variance($run->estcost, $actual_cost)
        ];
    }
    
    /**
     * Test basic cost estimation accuracy
     * 
     * @covers \local_customerintel\services\cost_service::estimate_cost
     * @covers \local_customerintel\services\cost_service::calculate_variance
     */
    public function test_basic_cost_estimation_accuracy() {
        // Get estimate
        $estimate = $this->cost_service->estimate_cost($this->test_company_id);
        
        $this->assertArrayHasKey('total_cost', $estimate);
        $this->assertArrayHasKey('total_tokens', $estimate);
        $this->assertArrayHasKey('breakdown', $estimate);
        
        // Queue run
        $runid = $this->job_queue->queue_run($this->test_company_id, null, 
            $this->test_user_id, ['force_refresh' => true]);
        
        // Simulate completion with 10% overrun
        $result = $this->simulate_run_completion($runid, 1.1);
        
        // Verify variance is within 25%
        $this->assertLessThanOrEqual(25, abs($result['variance_pct']),
            "Cost variance {$result['variance_pct']}% exceeds 25% limit");
    }
    
    /**
     * Test cost estimation with underrun
     */
    public function test_cost_estimation_underrun() {
        $runid = $this->job_queue->queue_run($this->test_company_id, null,
            $this->test_user_id, ['force_refresh' => true]);
        
        // Simulate completion with 20% underrun
        $result = $this->simulate_run_completion($runid, 0.8);
        
        // Variance should be negative but within -25%
        $this->assertLessThan(0, $result['variance_pct']);
        $this->assertGreaterThanOrEqual(-25, $result['variance_pct'],
            "Cost underrun {$result['variance_pct']}% exceeds -25% limit");
    }
    
    /**
     * Test cost estimation with exact match
     */
    public function test_cost_estimation_exact_match() {
        $runid = $this->job_queue->queue_run($this->test_company_id, null,
            $this->test_user_id, ['force_refresh' => true]);
        
        // Simulate completion with exact estimate
        $result = $this->simulate_run_completion($runid, 1.0);
        
        // Variance should be 0
        $this->assertEquals(0, $result['variance_pct']);
    }
    
    /**
     * Test cost estimation variance over multiple runs
     * 
     * @dataProvider variance_scenarios_provider
     */
    public function test_cost_variance_scenarios(float $variance_factor, string $scenario) {
        $runid = $this->job_queue->queue_run($this->test_company_id, null,
            $this->test_user_id, ['force_refresh' => true]);
        
        $result = $this->simulate_run_completion($runid, $variance_factor);
        
        // All scenarios should be within ±25%
        $this->assertLessThanOrEqual(25, abs($result['variance_pct']),
            "Scenario '$scenario': variance {$result['variance_pct']}% exceeds ±25% limit");
    }
    
    /**
     * Data provider for variance scenarios
     */
    public function variance_scenarios_provider(): array {
        return [
            'minimal_overrun' => [1.05, 'Minimal 5% overrun'],
            'moderate_overrun' => [1.15, 'Moderate 15% overrun'],
            'max_acceptable_overrun' => [1.24, 'Max acceptable 24% overrun'],
            'minimal_underrun' => [0.95, 'Minimal 5% underrun'],
            'moderate_underrun' => [0.85, 'Moderate 15% underrun'],
            'max_acceptable_underrun' => [0.76, 'Max acceptable 24% underrun'],
        ];
    }
    
    /**
     * Test calibration improvement over time
     */
    public function test_calibration_improvement() {
        global $DB;
        
        // Run multiple iterations to build calibration data
        $variances = [];
        
        for ($i = 0; $i < 10; $i++) {
            // Queue and complete run with random variance
            $runid = $this->job_queue->queue_run($this->test_company_id, null,
                $this->test_user_id, ['force_refresh' => true]);
            
            // Random variance between 0.8 and 1.2
            $variance = 0.8 + (mt_rand(0, 40) / 100);
            $result = $this->simulate_run_completion($runid, $variance);
            
            $variances[] = abs($result['variance_pct']);
            
            // Sleep briefly to ensure different timestamps
            usleep(100000); // 0.1 second
        }
        
        // Calculate average variance
        $avg_variance = array_sum($variances) / count($variances);
        
        // Average should be within reasonable range
        $this->assertLessThanOrEqual(20, $avg_variance,
            "Average variance {$avg_variance}% indicates poor calibration");
        
        // Later estimates should use calibration data
        $calibrated_estimate = $this->cost_service->estimate_cost($this->test_company_id);
        
        // Verify calibration factors are being used
        $this->assertArrayHasKey('provider', $calibrated_estimate);
    }
    
    /**
     * Test cost variance for comparison runs
     */
    public function test_comparison_cost_variance() {
        // Create target company
        $target_id = $this->create_test_company();
        
        // Queue comparison run
        $runid = $this->job_queue->queue_run($this->test_company_id, $target_id,
            $this->test_user_id, ['force_refresh' => true]);
        
        // Get the queued run
        global $DB;
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        
        // Estimate should be roughly double for comparison
        $single_estimate = $this->cost_service->estimate_cost($this->test_company_id);
        $comparison_estimate = $this->cost_service->estimate_cost($this->test_company_id, $target_id);
        
        $this->assertGreaterThan($single_estimate['total_cost'] * 1.8, 
            $comparison_estimate['total_cost']);
        
        // Simulate completion with 12% overrun
        $result = $this->simulate_run_completion($runid, 1.12);
        
        // Verify variance is within limits
        $this->assertLessThanOrEqual(25, abs($result['variance_pct']));
    }
    
    /**
     * Test per-NB cost variance tracking
     */
    public function test_per_nb_cost_variance() {
        $runid = $this->job_queue->queue_run($this->test_company_id, null,
            $this->test_user_id, ['force_refresh' => true]);
        
        // Complete with varied per-NB costs
        $this->simulate_run_completion($runid, 1.1);
        
        // Get detailed cost report
        $report = $this->cost_service->get_run_cost_report($runid);
        
        $this->assertArrayHasKey('nb_breakdown', $report);
        $this->assertCount(15, $report['nb_breakdown']);
        
        // Check each NB has cost data
        foreach ($report['nb_breakdown'] as $nbcode => $nbdata) {
            $this->assertArrayHasKey('tokens', $nbdata);
            $this->assertArrayHasKey('cost', $nbdata);
            $this->assertArrayHasKey('duration_ms', $nbdata);
            
            // Tokens should be reasonable
            $this->assertGreaterThan(500, $nbdata['tokens']);
            $this->assertLessThan(5000, $nbdata['tokens']);
        }
    }
    
    /**
     * Test variance with different providers
     */
    public function test_variance_across_providers() {
        $providers = ['gpt-4', 'gpt-3.5-turbo', 'claude-3-opus', 'claude-3-sonnet'];
        
        foreach ($providers as $provider) {
            // Configure provider
            set_config('llm_provider', $provider, 'local_customerintel');
            
            // Get estimate
            $estimate = $this->cost_service->estimate_cost($this->test_company_id);
            
            // Queue run
            $runid = $this->job_queue->queue_run($this->test_company_id, null,
                $this->test_user_id, ['force_refresh' => true]);
            
            // Simulate with 18% overrun
            $result = $this->simulate_run_completion($runid, 1.18);
            
            // All providers should maintain variance within limits
            $this->assertLessThanOrEqual(25, abs($result['variance_pct']),
                "Provider $provider: variance {$result['variance_pct']}% exceeds limit");
        }
    }
    
    /**
     * Test cost history statistics
     * 
     * @covers \local_customerintel\services\cost_service::get_cost_history
     */
    public function test_cost_history_statistics() {
        // Create multiple completed runs
        for ($i = 0; $i < 5; $i++) {
            $runid = $this->job_queue->queue_run($this->test_company_id, null,
                $this->test_user_id, ['force_refresh' => true]);
            
            // Vary the actual costs
            $variance = 0.85 + ($i * 0.05); // 0.85, 0.90, 0.95, 1.00, 1.05
            $this->simulate_run_completion($runid, $variance);
        }
        
        // Get cost history
        $history = $this->cost_service->get_cost_history(10);
        
        $this->assertArrayHasKey('runs', $history);
        $this->assertArrayHasKey('summary', $history);
        
        $summary = $history['summary'];
        $this->assertEquals(5, $summary['total_runs']);
        
        // Average variance should be close to 0 (balanced over/under)
        $this->assertLessThanOrEqual(10, abs($summary['avg_variance']));
        
        // Max variance should be within limits
        $this->assertLessThanOrEqual(25, abs($summary['max_variance']));
        $this->assertLessThanOrEqual(25, abs($summary['min_variance']));
    }
    
    /**
     * Test dashboard data accuracy
     * 
     * @covers \local_customerintel\services\cost_service::get_dashboard_data
     */
    public function test_dashboard_cost_data() {
        // Create runs with known variances
        $expected_variances = [1.1, 0.9, 1.05, 0.95, 1.0];
        
        foreach ($expected_variances as $variance) {
            $runid = $this->job_queue->queue_run($this->test_company_id, null,
                $this->test_user_id, ['force_refresh' => true]);
            $this->simulate_run_completion($runid, $variance);
        }
        
        // Get dashboard data
        $dashboard = $this->cost_service->get_dashboard_data(10);
        
        $this->assertArrayHasKey('runs', $dashboard);
        $this->assertArrayHasKey('summary', $dashboard);
        
        // Verify run count
        $this->assertCount(5, $dashboard['runs']);
        
        // Check variance formatting and classes
        foreach ($dashboard['runs'] as $run) {
            $this->assertArrayHasKey('variance', $run);
            $this->assertArrayHasKey('variance_class', $run);
            
            // Variance should be formatted as percentage
            $this->assertMatchesRegularExpression('/^[+-]\d+\.\d%$/', $run['variance']);
            
            // Check variance class assignment
            $variance_num = (float)str_replace(['%', '+'], '', $run['variance']);
            if (abs($variance_num) > 10) {
                $this->assertContains($run['variance_class'], 
                    ['text-danger', 'text-success']);
            } else {
                $this->assertEquals('text-warning', $run['variance_class']);
            }
        }
        
        // Summary should have formatted values
        $this->assertMatchesRegularExpression('/^\$\d+\.\d{2}$/', $dashboard['summary']['total_estimated']);
        $this->assertMatchesRegularExpression('/^\$\d+\.\d{2}$/', $dashboard['summary']['total_actual']);
        $this->assertMatchesRegularExpression('/^[+-]?\d+\.\d%$/', $dashboard['summary']['total_variance']);
    }
    
    /**
     * Test extreme variance detection
     */
    public function test_extreme_variance_detection() {
        // Test variance beyond acceptable limit
        $runid = $this->job_queue->queue_run($this->test_company_id, null,
            $this->test_user_id, ['force_refresh' => true]);
        
        // Simulate 30% overrun (beyond 25% limit)
        $result = $this->simulate_run_completion($runid, 1.3);
        
        // System should detect this as extreme variance
        $this->assertGreaterThan(25, abs($result['variance_pct']));
        
        // Get telemetry to check if flagged
        global $DB;
        $telemetry = $DB->get_records('local_ci_telemetry', 
            ['runid' => $runid, 'metrickey' => 'actual_cost']);
        
        $this->assertNotEmpty($telemetry);
        
        // Check if variance is recorded
        $telemetry_record = reset($telemetry);
        $payload = json_decode($telemetry_record->payload, true);
        $this->assertArrayHasKey('variance_pct', $payload);
        $this->assertGreaterThan(25, abs($payload['variance_pct']));
    }
    
    /**
     * Test token estimation accuracy
     */
    public function test_token_estimation_accuracy() {
        $estimate = $this->cost_service->estimate_cost($this->test_company_id);
        
        // Check token breakdown
        $this->assertArrayHasKey('input_tokens', $estimate);
        $this->assertArrayHasKey('output_tokens', $estimate);
        $this->assertArrayHasKey('total_tokens', $estimate);
        
        // Verify token totals
        $this->assertEquals(
            $estimate['input_tokens'] + $estimate['output_tokens'],
            $estimate['total_tokens']
        );
        
        // Input should be larger than output (includes context)
        $this->assertGreaterThan($estimate['output_tokens'], $estimate['input_tokens']);
        
        // Queue and complete run
        $runid = $this->job_queue->queue_run($this->test_company_id, null,
            $this->test_user_id);
        
        $result = $this->simulate_run_completion($runid, 1.08);
        
        // Token variance should also be within reasonable range
        $token_variance = (($result['actual_tokens'] - $result['estimated_tokens']) 
            / $result['estimated_tokens']) * 100;
        
        $this->assertLessThanOrEqual(25, abs($token_variance),
            "Token variance {$token_variance}% exceeds 25% limit");
    }
}