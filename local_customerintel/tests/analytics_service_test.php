<?php
/**
 * Unit tests for Analytics Service (Slice 10)
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
require_once($CFG->dirroot . '/local/customerintel/classes/services/analytics_service.php');

/**
 * Test class for analytics service functionality
 * 
 * @coversDefaultClass \local_customerintel\services\analytics_service
 */
class analytics_service_test extends advanced_testcase {
    
    /**
     * @var \local_customerintel\services\analytics_service
     */
    private $analytics_service;
    
    /**
     * @var array Test run IDs
     */
    private $test_run_ids = [];
    
    /**
     * @var int Test company ID
     */
    private $test_company_id;
    
    /**
     * @var int Test target company ID
     */
    private $test_target_company_id;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Enable analytics features
        set_config('enable_analytics_dashboard', '1', 'local_customerintel');
        set_config('enable_telemetry_trends', '1', 'local_customerintel');
        set_config('enable_safe_mode', '0', 'local_customerintel');
        
        $this->analytics_service = new \local_customerintel\services\analytics_service();
        
        // Create test data
        $this->create_test_data();
    }
    
    /**
     * Create comprehensive test data
     */
    private function create_test_data() {
        global $DB;
        
        // Create test companies
        $company_data = new \stdClass();
        $company_data->name = 'Analytics Test Corp';
        $company_data->ticker = 'ANLZ';
        $company_data->website = 'https://analyticstest.com';
        $company_data->sector = 'Technology';
        $company_data->timecreated = time();
        $company_data->timemodified = time();
        
        $this->test_company_id = $DB->insert_record('local_ci_company', $company_data);
        
        $target_company_data = new \stdClass();
        $target_company_data->name = 'Target Analytics Corp';
        $target_company_data->ticker = 'TARG';
        $target_company_data->website = 'https://targetanalytics.com';
        $target_company_data->sector = 'Technology';
        $target_company_data->timecreated = time();
        $target_company_data->timemodified = time();
        
        $this->test_target_company_id = $DB->insert_record('local_ci_company', $target_company_data);
        
        // Create test runs with varying completion times
        $base_time = time() - (90 * 86400); // 90 days ago
        
        for ($i = 0; $i < 25; $i++) {
            $run_data = new \stdClass();
            $run_data->companyid = $this->test_company_id;
            $run_data->targetcompanyid = ($i % 3 === 0) ? $this->test_target_company_id : null;
            $run_data->status = 'completed';
            $run_data->initiatedbyuserid = 1;
            $run_data->timecreated = $base_time + ($i * 2 * 86400); // Every 2 days
            $run_data->timecompleted = $run_data->timecreated + (30 + ($i * 2)) * 60; // 30-80 minutes duration
            
            $run_id = $DB->insert_record('local_ci_run', $run_data);
            $this->test_run_ids[] = $run_id;
            
            // Create telemetry data for each run
            $this->create_telemetry_data($run_id, $i);
            
            // Create citation metrics for some runs
            if ($i % 2 === 0) {
                $this->create_citation_metrics($run_id, $i);
            }
        }
    }
    
    /**
     * Create telemetry data for a run
     * 
     * @param int $runid Run ID
     * @param int $index Index for variation
     */
    private function create_telemetry_data($runid, $index) {
        global $DB;
        
        $base_time = time() - ((25 - $index) * 2 * 86400);
        
        // QA scores with variation
        $qa_score = 0.6 + ($index * 0.015); // Gradual improvement
        $coherence_score = 0.5 + ($index * 0.02);
        $pattern_score = 0.7 + ($index * 0.01);
        
        $telemetry_records = [
            [
                'runid' => $runid,
                'metrickey' => 'qa_score_total',
                'metricvaluenum' => min(1.0, $qa_score),
                'timecreated' => $base_time + 1800 // 30 minutes after start
            ],
            [
                'runid' => $runid,
                'metrickey' => 'coherence_score',
                'metricvaluenum' => min(1.0, $coherence_score),
                'timecreated' => $base_time + 1200
            ],
            [
                'runid' => $runid,
                'metrickey' => 'pattern_alignment_score',
                'metricvaluenum' => min(1.0, $pattern_score),
                'timecreated' => $base_time + 1500
            ],
            [
                'runid' => $runid,
                'metrickey' => 'total_duration_ms',
                'metricvaluenum' => (30 + ($index * 2)) * 60 * 1000, // 30-80 minutes in ms
                'timecreated' => $base_time + 1800
            ],
            [
                'runid' => $runid,
                'metrickey' => 'phase_duration_nb_orchestration',
                'metricvaluenum' => (5 + ($index % 5)) * 1000, // 5-10 seconds
                'timecreated' => $base_time + 300
            ],
            [
                'runid' => $runid,
                'metrickey' => 'phase_duration_synthesis_drafting',
                'metricvaluenum' => (10 + ($index % 8)) * 1000, // 10-18 seconds
                'timecreated' => $base_time + 900
            ],
            [
                'runid' => $runid,
                'metrickey' => 'phase_duration_coherence_engine',
                'metricvaluenum' => (3 + ($index % 4)) * 1000, // 3-7 seconds
                'timecreated' => $base_time + 1200
            ],
            [
                'runid' => $runid,
                'metrickey' => 'synth_citation_count',
                'metricvaluenum' => 10 + ($index % 15), // 10-25 citations
                'timecreated' => $base_time + 1500
            ]
        ];
        
        foreach ($telemetry_records as $record) {
            $DB->insert_record('local_ci_telemetry', (object)$record);
        }
    }
    
    /**
     * Create citation metrics for a run
     * 
     * @param int $runid Run ID
     * @param int $index Index for variation
     */
    private function create_citation_metrics($runid, $index) {
        global $DB;
        
        $citation_metrics = new \stdClass();
        $citation_metrics->runid = $runid;
        $citation_metrics->total_citations = 15 + ($index % 20); // 15-35 citations
        $citation_metrics->unique_domains = 8 + ($index % 10); // 8-18 domains
        $citation_metrics->confidence_avg = 0.65 + ($index * 0.01); // Improving confidence
        $citation_metrics->diversity_score = 0.7 + ($index * 0.008); // Improving diversity
        $citation_metrics->source_type_breakdown = json_encode([
            'news' => 5 + ($index % 8),
            'analyst' => 3 + ($index % 5),
            'company' => 4 + ($index % 6),
            'regulatory' => 2 + ($index % 4)
        ]);
        $citation_metrics->timecreated = time() - ((25 - $index) * 2 * 86400);
        $citation_metrics->timemodified = $citation_metrics->timecreated;
        
        $DB->insert_record('local_ci_citation_metrics', $citation_metrics);
    }
    
    /**
     * Test get_recent_runs method
     */
    public function test_get_recent_runs() {
        $recent_runs = $this->analytics_service->get_recent_runs(10);
        
        $this->assertIsArray($recent_runs, 'Should return array of runs');
        $this->assertCount(10, $recent_runs, 'Should return requested number of runs');
        
        // Verify structure
        $first_run = $recent_runs[0];
        $this->assertObjectHasAttribute('id', $first_run);
        $this->assertObjectHasAttribute('company_name', $first_run);
        $this->assertObjectHasAttribute('qa_metrics', $first_run);
        $this->assertObjectHasAttribute('telemetry_summary', $first_run);
        
        // Verify ordering (most recent first)
        $this->assertGreaterThan($recent_runs[1]->timecompleted, $first_run->timecompleted);
        
        // Test QA metrics structure
        $this->assertIsArray($first_run->qa_metrics);
        if (!empty($first_run->qa_metrics)) {
            $this->assertArrayHasKey('qa_score_total', $first_run->qa_metrics);
        }
    }
    
    /**
     * Test get_recent_runs with safe mode
     */
    public function test_get_recent_runs_safe_mode() {
        // Enable safe mode
        set_config('enable_safe_mode', '1', 'local_customerintel');
        $safe_analytics = new \local_customerintel\services\analytics_service();
        
        // Request more than safe mode limit
        $recent_runs = $safe_analytics->get_recent_runs(20);
        
        $this->assertLessThanOrEqual(10, count($recent_runs), 'Should limit to 10 runs in safe mode');
    }
    
    /**
     * Test get_run_trends method
     */
    public function test_get_run_trends() {
        $trends = $this->analytics_service->get_run_trends('qa_score_total', 30);
        
        $this->assertIsArray($trends, 'Should return array of trend data');
        $this->assertArrayHasKey('labels', $trends, 'Should have labels array');
        $this->assertArrayHasKey('datasets', $trends, 'Should have datasets array');
        
        if (!empty($trends['labels'])) {
            $this->assertIsArray($trends['datasets'], 'Datasets should be array');
            $this->assertNotEmpty($trends['datasets'], 'Should have at least one dataset');
            
            $first_dataset = $trends['datasets'][0];
            $this->assertArrayHasKey('label', $first_dataset);
            $this->assertArrayHasKey('data', $first_dataset);
            $this->assertIsArray($first_dataset['data']);
        }
    }
    
    /**
     * Test get_run_trends with different metrics
     */
    public function test_get_run_trends_different_metrics() {
        $metrics = ['qa_score_total', 'coherence_score', 'pattern_alignment_score'];
        
        foreach ($metrics as $metric) {
            $trends = $this->analytics_service->get_run_trends($metric, 30);
            $this->assertIsArray($trends, "Should return trends for metric: {$metric}");
            
            if (!empty($trends['datasets'])) {
                $this->assertStringContainsString($metric, $trends['datasets'][0]['label'], 
                    "Label should contain metric name for {$metric}");
            }
        }
    }
    
    /**
     * Test get_qa_distribution method
     */
    public function test_get_qa_distribution() {
        $distribution = $this->analytics_service->get_qa_distribution();
        
        $this->assertIsArray($distribution, 'Should return array');
        $this->assertArrayHasKey('labels', $distribution, 'Should have labels');
        $this->assertArrayHasKey('datasets', $distribution, 'Should have datasets');
        
        if (!empty($distribution['labels'])) {
            $this->assertIsArray($distribution['datasets']);
            $this->assertNotEmpty($distribution['datasets']);
            
            $dataset = $distribution['datasets'][0];
            $this->assertArrayHasKey('data', $dataset);
            $this->assertArrayHasKey('backgroundColor', $dataset);
            $this->assertCount(count($distribution['labels']), $dataset['data'], 
                'Data count should match labels count');
        }
    }
    
    /**
     * Test get_coherence_vs_pattern_correlation method
     */
    public function test_get_coherence_vs_pattern_correlation() {
        $correlation = $this->analytics_service->get_coherence_vs_pattern_correlation();
        
        $this->assertIsArray($correlation, 'Should return array');
        
        if (!empty($correlation)) {
            $this->assertArrayHasKey('datasets', $correlation);
            $this->assertNotEmpty($correlation['datasets']);
            
            $dataset = $correlation['datasets'][0];
            $this->assertArrayHasKey('data', $dataset);
            $this->assertIsArray($dataset['data']);
            
            // Check data point structure
            if (!empty($dataset['data'])) {
                $point = $dataset['data'][0];
                $this->assertArrayHasKey('x', $point, 'Should have x coordinate (coherence)');
                $this->assertArrayHasKey('y', $point, 'Should have y coordinate (pattern)');
                $this->assertArrayHasKey('runid', $point, 'Should have runid');
            }
        }
    }
    
    /**
     * Test correlation chart disabled in safe mode
     */
    public function test_correlation_disabled_safe_mode() {
        // Enable safe mode
        set_config('enable_safe_mode', '1', 'local_customerintel');
        $safe_analytics = new \local_customerintel\services\analytics_service();
        
        $correlation = $safe_analytics->get_coherence_vs_pattern_correlation();
        
        $this->assertEmpty($correlation, 'Should return empty array in safe mode');
    }
    
    /**
     * Test get_citation_diversity_vs_confidence method
     */
    public function test_get_citation_diversity_vs_confidence() {
        $citations = $this->analytics_service->get_citation_diversity_vs_confidence();
        
        $this->assertIsArray($citations, 'Should return array');
        
        if (!empty($citations)) {
            $this->assertArrayHasKey('datasets', $citations);
            $this->assertNotEmpty($citations['datasets']);
            
            $dataset = $citations['datasets'][0];
            $this->assertArrayHasKey('data', $dataset);
            
            // Check bubble data structure
            if (!empty($dataset['data'])) {
                $bubble = $dataset['data'][0];
                $this->assertArrayHasKey('x', $bubble, 'Should have x coordinate (confidence)');
                $this->assertArrayHasKey('y', $bubble, 'Should have y coordinate (diversity)');
                $this->assertArrayHasKey('r', $bubble, 'Should have r (bubble size)');
                $this->assertArrayHasKey('runid', $bubble, 'Should have runid');
                $this->assertArrayHasKey('company', $bubble, 'Should have company name');
            }
        }
    }
    
    /**
     * Test get_phase_duration_breakdown method
     */
    public function test_get_phase_duration_breakdown() {
        $phases = $this->analytics_service->get_phase_duration_breakdown(30);
        
        $this->assertIsArray($phases, 'Should return array');
        
        if (!empty($phases)) {
            $this->assertArrayHasKey('labels', $phases);
            $this->assertArrayHasKey('datasets', $phases);
            $this->assertNotEmpty($phases['datasets']);
            
            // Check that phases are present
            $phase_names = [];
            foreach ($phases['datasets'] as $dataset) {
                $phase_names[] = $dataset['label'];
                $this->assertArrayHasKey('data', $dataset);
                $this->assertArrayHasKey('backgroundColor', $dataset);
            }
            
            // Should have multiple phases
            $this->assertGreaterThan(1, count($phase_names), 'Should have multiple phases');
        }
    }
    
    /**
     * Test get_summary_statistics method
     */
    public function test_get_summary_statistics() {
        $summary = $this->analytics_service->get_summary_statistics();
        
        $this->assertIsArray($summary, 'Should return array');
        $this->assertArrayHasKey('avg_qa_score', $summary);
        $this->assertArrayHasKey('fastest_phase', $summary);
        $this->assertArrayHasKey('success_rate', $summary);
        $this->assertArrayHasKey('common_error', $summary);
        $this->assertArrayHasKey('total_runs', $summary);
        
        // Test data types
        $this->assertIsNumeric($summary['avg_qa_score']);
        $this->assertIsNumeric($summary['success_rate']);
        $this->assertIsNumeric($summary['total_runs']);
        
        // Test ranges
        $this->assertGreaterThanOrEqual(0, $summary['avg_qa_score']);
        $this->assertLessThanOrEqual(1, $summary['avg_qa_score']);
        $this->assertGreaterThanOrEqual(0, $summary['success_rate']);
        $this->assertLessThanOrEqual(100, $summary['success_rate']);
        
        // Test fastest phase structure
        if ($summary['fastest_phase']) {
            $this->assertArrayHasKey('name', $summary['fastest_phase']);
            $this->assertArrayHasKey('duration', $summary['fastest_phase']);
            $this->assertIsNumeric($summary['fastest_phase']['duration']);
        }
    }
    
    /**
     * Test analytics usage logging
     */
    public function test_log_analytics_usage() {
        global $DB;
        
        $initial_count = $DB->count_records('local_ci_telemetry', ['runid' => 0, 'metrickey' => 'analytics_test_action']);
        
        $this->analytics_service->log_analytics_usage('test_action', ['test' => 'data']);
        
        $final_count = $DB->count_records('local_ci_telemetry', ['runid' => 0, 'metrickey' => 'analytics_test_action']);
        
        $this->assertEquals($initial_count + 1, $final_count, 'Should log analytics usage');
        
        // Verify the logged record
        $record = $DB->get_record('local_ci_telemetry', [
            'runid' => 0,
            'metrickey' => 'analytics_test_action'
        ], '*', IGNORE_MULTIPLE);
        
        $this->assertNotEmpty($record, 'Should create telemetry record');
        $this->assertEquals(1, $record->metricvaluenum, 'Should log value of 1');
        
        $payload = json_decode($record->payload, true);
        $this->assertArrayHasKey('test', $payload, 'Should include metadata');
        $this->assertEquals('data', $payload['test']);
        $this->assertArrayHasKey('timestamp', $payload, 'Should include timestamp');
    }
    
    /**
     * Test feature flag checks
     */
    public function test_feature_flags() {
        $this->assertTrue($this->analytics_service->is_analytics_enabled(), 'Analytics should be enabled');
        $this->assertFalse($this->analytics_service->is_safe_mode_enabled(), 'Safe mode should be disabled');
        
        // Test with analytics disabled
        set_config('enable_analytics_dashboard', '0', 'local_customerintel');
        $disabled_analytics = new \local_customerintel\services\analytics_service();
        
        $this->assertEmpty($disabled_analytics->get_recent_runs(), 'Should return empty when disabled');
        $this->assertEmpty($disabled_analytics->get_qa_distribution(), 'Should return empty when disabled');
        $this->assertEmpty($disabled_analytics->get_summary_statistics(), 'Should return empty when disabled');
    }
    
    /**
     * Test performance with large dataset
     */
    public function test_performance_large_dataset() {
        global $DB;
        
        // Create additional runs for performance testing
        $base_time = time() - (10 * 86400);
        for ($i = 0; $i < 75; $i++) { // Total will be 100 runs
            $run_data = new \stdClass();
            $run_data->companyid = $this->test_company_id;
            $run_data->status = 'completed';
            $run_data->initiatedbyuserid = 1;
            $run_data->timecreated = $base_time + ($i * 3600); // Every hour
            $run_data->timecompleted = $run_data->timecreated + (30 * 60);
            
            $run_id = $DB->insert_record('local_ci_run', $run_data);
            
            // Add minimal telemetry
            $telemetry = new \stdClass();
            $telemetry->runid = $run_id;
            $telemetry->metrickey = 'qa_score_total';
            $telemetry->metricvaluenum = 0.5 + ($i * 0.005);
            $telemetry->timecreated = $run_data->timecompleted;
            
            $DB->insert_record('local_ci_telemetry', $telemetry);
        }
        
        // Test performance
        $start_time = microtime(true);
        
        $recent_runs = $this->analytics_service->get_recent_runs(50);
        $trends = $this->analytics_service->get_run_trends('qa_score_total', 30);
        $distribution = $this->analytics_service->get_qa_distribution();
        $summary = $this->analytics_service->get_summary_statistics();
        
        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        
        // Performance assertions
        $this->assertLessThan(2.0, $execution_time, 'Analytics queries should complete within 2 seconds');
        $this->assertNotEmpty($recent_runs, 'Should return recent runs');
        $this->assertNotEmpty($summary, 'Should return summary statistics');
        
        // Log performance for analysis
        error_log("Analytics Performance Test: {$execution_time} seconds for 100 runs");
    }
    
    /**
     * Test error handling with invalid data
     */
    public function test_error_handling() {
        // Test with non-existent metric
        $trends = $this->analytics_service->get_run_trends('invalid_metric', 30);
        $this->assertIsArray($trends, 'Should return array even for invalid metric');
        
        // Test with extreme date ranges
        $extreme_trends = $this->analytics_service->get_run_trends('qa_score_total', 365);
        $this->assertIsArray($extreme_trends, 'Should handle extreme date ranges');
        
        // Test safe mode with extreme limits
        set_config('enable_safe_mode', '1', 'local_customerintel');
        $safe_analytics = new \local_customerintel\services\analytics_service();
        
        $safe_trends = $safe_analytics->get_run_trends('qa_score_total', 365);
        $this->assertIsArray($safe_trends, 'Should handle safe mode limits');
    }
    
    /**
     * Test data consistency across methods
     */
    public function test_data_consistency() {
        // Get data from different methods that should be consistent
        $recent_runs = $this->analytics_service->get_recent_runs(5);
        $summary = $this->analytics_service->get_summary_statistics();
        
        $this->assertNotEmpty($recent_runs, 'Should have recent runs for consistency test');
        
        // Check that QA scores are in valid range
        foreach ($recent_runs as $run) {
            if (!empty($run->qa_metrics['qa_score_total'])) {
                $qa_score = $run->qa_metrics['qa_score_total'];
                $this->assertGreaterThanOrEqual(0, $qa_score, 'QA score should be >= 0');
                $this->assertLessThanOrEqual(1, $qa_score, 'QA score should be <= 1');
            }
        }
        
        // Summary statistics should be reasonable
        if ($summary['avg_qa_score'] > 0) {
            $this->assertGreaterThanOrEqual(0, $summary['avg_qa_score']);
            $this->assertLessThanOrEqual(1, $summary['avg_qa_score']);
        }
        
        $this->assertGreaterThanOrEqual(0, $summary['success_rate']);
        $this->assertLessThanOrEqual(100, $summary['success_rate']);
    }
}