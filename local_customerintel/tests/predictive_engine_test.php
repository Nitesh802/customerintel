<?php
/**
 * Unit tests for Predictive Engine (Slice 11)
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
require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');

/**
 * Test class for predictive engine functionality
 * 
 * @coversDefaultClass \local_customerintel\services\predictive_engine
 */
class predictive_engine_test extends advanced_testcase {
    
    /**
     * @var \local_customerintel\services\predictive_engine
     */
    private $predictive_engine;
    
    /**
     * @var array Test run IDs
     */
    private $test_run_ids = [];
    
    /**
     * @var int Test company ID
     */
    private $test_company_id;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        
        // Enable predictive features for testing
        set_config('enable_predictive_engine', 1, 'local_customerintel');
        set_config('enable_anomaly_alerts', 1, 'local_customerintel');
        set_config('forecast_horizon_days', 30, 'local_customerintel');
        set_config('enable_safe_mode', 0, 'local_customerintel');
        
        $this->predictive_engine = new \local_customerintel\services\predictive_engine();
        
        // Create test data
        $this->create_test_data();
    }
    
    /**
     * Create test data for predictive analysis
     */
    private function create_test_data() {
        global $DB;
        
        // Create test company
        $company_data = new \stdClass();
        $company_data->name = 'Predictive Test Corp';
        $company_data->ticker = 'PTC';
        $company_data->timecreated = time();
        $company_data->timemodified = time();
        $this->test_company_id = $DB->insert_record('local_ci_company', $company_data);
        
        // Create test runs with telemetry data over the last 60 days
        $base_time = time() - (60 * 86400); // 60 days ago
        
        for ($day = 0; $day < 60; $day++) {
            $run_time = $base_time + ($day * 86400);
            
            // Create run
            $run_data = new \stdClass();
            $run_data->companyid = $this->test_company_id;
            $run_data->status = 'completed';
            $run_data->timecreated = $run_time;
            $run_data->timecompleted = $run_time + 300; // 5 minutes later
            $run_data->initiatedbyuserid = 1;
            
            $run_id = $DB->insert_record('local_ci_run', $run_data);
            $this->test_run_ids[] = $run_id;
            
            // Create telemetry data with trends and some anomalies
            $this->create_telemetry_for_run($run_id, $day, $run_time);
        }
    }
    
    /**
     * Create telemetry data for a specific run
     * 
     * @param int $run_id Run ID
     * @param int $day_offset Day offset from start
     * @param int $timestamp Timestamp for the data
     */
    private function create_telemetry_for_run($run_id, $day_offset, $timestamp) {
        global $DB;
        
        $metrics = [
            'qa_score_total' => [
                'base' => 0.8,
                'trend' => 0.001, // Slight upward trend
                'noise' => 0.05
            ],
            'coherence_score' => [
                'base' => 0.75,
                'trend' => 0.0005,
                'noise' => 0.03
            ],
            'pattern_alignment_score' => [
                'base' => 0.7,
                'trend' => -0.0005, // Slight downward trend
                'noise' => 0.04
            ],
            'total_duration_ms' => [
                'base' => 45000,
                'trend' => -10,
                'noise' => 5000
            ]
        ];
        
        foreach ($metrics as $metric_key => $config) {
            $base_value = $config['base'] + ($config['trend'] * $day_offset);
            $noise = (rand(-100, 100) / 100) * $config['noise'];
            $value = $base_value + $noise;
            
            // Add some anomalies on specific days
            if (in_array($day_offset, [15, 30, 45]) && $metric_key === 'qa_score_total') {
                $value = $base_value + ($config['noise'] * 3); // Create anomaly
            }
            
            if ($day_offset === 25 && $metric_key === 'total_duration_ms') {
                $value = $base_value + ($config['noise'] * 4); // Duration anomaly
            }
            
            // Ensure values are within reasonable bounds
            if ($metric_key !== 'total_duration_ms') {
                $value = max(0, min(1, $value));
            } else {
                $value = max(1000, $value);
            }
            
            $telemetry_data = new \stdClass();
            $telemetry_data->runid = $run_id;
            $telemetry_data->metrickey = $metric_key;
            $telemetry_data->metricvaluenum = $value;
            $telemetry_data->metricvaluetext = '';
            $telemetry_data->payload = json_encode(['test_data' => true]);
            $telemetry_data->timecreated = $timestamp;
            
            $DB->insert_record('local_ci_telemetry', $telemetry_data);
        }
    }
    
    /**
     * Test predictive engine initialization
     * 
     * @covers ::__construct
     */
    public function test_predictive_engine_initialization() {
        $this->assertInstanceOf('\local_customerintel\services\predictive_engine', $this->predictive_engine);
        $this->assertTrue($this->predictive_engine->is_predictive_enabled());
        $this->assertTrue($this->predictive_engine->is_anomaly_alerts_enabled());
        $this->assertFalse($this->predictive_engine->is_safe_mode_enabled());
        $this->assertEquals(30, $this->predictive_engine->get_forecast_horizon_days());
    }
    
    /**
     * Test safe mode enforcement
     * 
     * @covers ::forecast_metric_trend
     * @covers ::is_safe_mode_enabled
     */
    public function test_safe_mode_enforcement() {
        // Enable safe mode
        set_config('enable_safe_mode', 1, 'local_customerintel');
        $safe_engine = new \local_customerintel\services\predictive_engine();
        
        $this->assertTrue($safe_engine->is_safe_mode_enabled());
        
        // Forecast should be disabled in safe mode
        $forecast = $safe_engine->forecast_metric_trend('qa_score_total', 30);
        $this->assertArrayHasKey('error', $forecast);
        $this->assertStringContainsString('disabled', $forecast['error']);
    }
    
    /**
     * Test metric forecasting functionality
     * 
     * @covers ::forecast_metric_trend
     * @covers ::calculate_linear_regression
     */
    public function test_metric_forecasting() {
        $start_time = microtime(true);
        
        // Test QA score forecasting
        $forecast = $this->predictive_engine->forecast_metric_trend('qa_score_total', 30);
        
        $execution_time = microtime(true) - $start_time;
        
        // Verify forecast structure
        $this->assertIsArray($forecast);
        $this->assertArrayHasKey('metrickey', $forecast);
        $this->assertArrayHasKey('historical', $forecast);
        $this->assertArrayHasKey('forecast', $forecast);
        $this->assertArrayHasKey('regression', $forecast);
        
        $this->assertEquals('qa_score_total', $forecast['metrickey']);
        
        // Verify historical data
        $historical = $forecast['historical'];
        $this->assertArrayHasKey('labels', $historical);
        $this->assertArrayHasKey('values', $historical);
        $this->assertGreaterThan(0, count($historical['labels']));
        
        // Verify forecast data
        $forecast_data = $forecast['forecast'];
        $this->assertArrayHasKey('labels', $forecast_data);
        $this->assertArrayHasKey('values', $forecast_data);
        $this->assertArrayHasKey('confidence_upper', $forecast_data);
        $this->assertArrayHasKey('confidence_lower', $forecast_data);
        $this->assertEquals(30, count($forecast_data['labels']));
        
        // Verify regression statistics
        $regression = $forecast['regression'];
        $this->assertArrayHasKey('slope', $regression);
        $this->assertArrayHasKey('intercept', $regression);
        $this->assertArrayHasKey('r_squared', $regression);
        $this->assertArrayHasKey('confidence', $regression);
        
        // Performance assertion (should complete under 1 second)
        $this->assertLessThan(1.0, $execution_time, 'Forecast should complete under 1 second');
        
        error_log("Forecast Performance Test: {$execution_time} seconds for 30-day forecast");
    }
    
    /**
     * Test anomaly detection functionality
     * 
     * @covers ::detect_anomalies
     * @covers ::classify_anomaly_severity
     */
    public function test_anomaly_detection() {
        // Test anomaly detection with default threshold
        $anomalies = $this->predictive_engine->detect_anomalies('qa_score_total', 2.0);
        
        $this->assertIsArray($anomalies);
        
        if (!empty($anomalies)) {
            $anomaly = $anomalies[0];
            
            // Verify anomaly structure
            $this->assertArrayHasKey('id', $anomaly);
            $this->assertArrayHasKey('runid', $anomaly);
            $this->assertArrayHasKey('metrickey', $anomaly);
            $this->assertArrayHasKey('value', $anomaly);
            $this->assertArrayHasKey('expected_value', $anomaly);
            $this->assertArrayHasKey('deviation', $anomaly);
            $this->assertArrayHasKey('z_score', $anomaly);
            $this->assertArrayHasKey('severity', $anomaly);
            $this->assertArrayHasKey('possible_cause', $anomaly);
            
            $this->assertEquals('qa_score_total', $anomaly['metrickey']);
            $this->assertGreaterThanOrEqual(2.0, $anomaly['z_score']);
            $this->assertContains($anomaly['severity'], ['low', 'medium', 'high', 'critical']);
        }
        
        // Test with different threshold
        $high_threshold_anomalies = $this->predictive_engine->detect_anomalies('qa_score_total', 3.0);
        $this->assertLessThanOrEqual(count($anomalies), count($high_threshold_anomalies));
    }
    
    /**
     * Test risk signal ranking
     * 
     * @covers ::rank_risk_signals
     * @covers ::calculate_risk_score
     */
    public function test_risk_signal_ranking() {
        $risk_signals = $this->predictive_engine->rank_risk_signals();
        
        $this->assertIsArray($risk_signals);
        $this->assertLessThanOrEqual(5, count($risk_signals)); // Should return top 5
        
        if (!empty($risk_signals)) {
            $signal = $risk_signals[0];
            
            // Verify risk signal structure
            $this->assertArrayHasKey('metric', $signal);
            $this->assertArrayHasKey('metric_display_name', $signal);
            $this->assertArrayHasKey('risk_score', $signal);
            $this->assertArrayHasKey('severity', $signal);
            $this->assertArrayHasKey('anomaly_count', $signal);
            $this->assertArrayHasKey('recommendation', $signal);
            
            $this->assertContains($signal['severity'], ['low', 'medium', 'high', 'critical']);
            $this->assertGreaterThan(0, $signal['risk_score']);
            $this->assertLessThanOrEqual(100, $signal['risk_score']);
        }
    }
    
    /**
     * Test anomaly summary generation
     * 
     * @covers ::get_anomaly_summary
     */
    public function test_anomaly_summary() {
        $summary = $this->predictive_engine->get_anomaly_summary();
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('by_severity', $summary);
        $this->assertArrayHasKey('recent', $summary);
        
        $this->assertIsInt($summary['total']);
        $this->assertIsArray($summary['by_severity']);
        $this->assertIsArray($summary['recent']);
        
        // Verify severity breakdown structure
        $by_severity = $summary['by_severity'];
        $this->assertArrayHasKey('critical', $by_severity);
        $this->assertArrayHasKey('high', $by_severity);
        $this->assertArrayHasKey('medium', $by_severity);
        $this->assertArrayHasKey('low', $by_severity);
    }
    
    /**
     * Test supported metrics
     * 
     * @covers ::get_supported_metrics
     */
    public function test_supported_metrics() {
        $metrics = $this->predictive_engine->get_supported_metrics();
        
        $this->assertIsArray($metrics);
        $this->assertContains('qa_score_total', $metrics);
        $this->assertContains('coherence_score', $metrics);
        $this->assertContains('pattern_alignment_score', $metrics);
        $this->assertContains('total_duration_ms', $metrics);
    }
    
    /**
     * Test forecasting with insufficient data
     * 
     * @covers ::forecast_metric_trend
     */
    public function test_forecast_insufficient_data() {
        // Test with unsupported metric
        $forecast = $this->predictive_engine->forecast_metric_trend('unsupported_metric', 30);
        $this->assertArrayHasKey('error', $forecast);
        $this->assertStringContainsString('Unsupported metric', $forecast['error']);
        
        // Create new engine with no telemetry data (fresh database)
        $this->resetAfterTest();
        set_config('enable_predictive_engine', 1, 'local_customerintel');
        $fresh_engine = new \local_customerintel\services\predictive_engine();
        
        $forecast = $fresh_engine->forecast_metric_trend('qa_score_total', 30);
        $this->assertArrayHasKey('error', $forecast);
        $this->assertStringContainsString('Insufficient historical data', $forecast['error']);
    }
    
    /**
     * Test telemetry logging for anomalies
     * 
     * @covers ::log_anomaly_detection
     */
    public function test_anomaly_telemetry_logging() {
        global $DB;
        
        // Create test anomaly
        $anomaly = [
            'runid' => $this->test_run_ids[0],
            'metrickey' => 'qa_score_total',
            'z_score' => 2.5,
            'deviation' => 0.15,
            'severity' => 'medium',
            'timestamp' => time(),
            'possible_cause' => 'Test anomaly'
        ];
        
        // Count telemetry records before
        $before_count = $DB->count_records('local_ci_telemetry', ['metrickey' => 'anomaly_detected']);
        
        // Log the anomaly
        $this->predictive_engine->log_anomaly_detection($anomaly);
        
        // Count telemetry records after
        $after_count = $DB->count_records('local_ci_telemetry', ['metrickey' => 'anomaly_detected']);
        
        $this->assertEquals($before_count + 1, $after_count, 'Anomaly should be logged to telemetry');
        
        // Verify the logged data
        $logged_anomaly = $DB->get_record('local_ci_telemetry', [
            'metrickey' => 'anomaly_detected',
            'runid' => $anomaly['runid']
        ], '*', IGNORE_MULTIPLE);
        
        $this->assertNotFalse($logged_anomaly);
        $this->assertEquals($anomaly['z_score'], $logged_anomaly->metricvaluenum);
        
        $payload = json_decode($logged_anomaly->payload, true);
        $this->assertEquals($anomaly['metrickey'], $payload['metric']);
        $this->assertEquals($anomaly['severity'], $payload['severity']);
    }
    
    /**
     * Test event triggering for anomalies
     * 
     * @covers ::trigger_anomaly_event
     */
    public function test_anomaly_event_triggering() {
        // Create a sink for events
        $sink = $this->redirectEvents();
        
        // Create test anomaly
        $anomaly = [
            'runid' => $this->test_run_ids[0],
            'metrickey' => 'qa_score_total',
            'z_score' => 3.0,
            'deviation' => 0.2,
            'severity' => 'high',
            'timestamp' => time(),
            'possible_cause' => 'Test anomaly event',
            'value' => 0.9,
            'expected_value' => 0.7,
            'company_name' => 'Test Company'
        ];
        
        // Trigger the event
        $this->predictive_engine->trigger_anomaly_event($anomaly);
        
        // Check if event was triggered
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        
        $event = $events[0];
        $this->assertInstanceOf('\local_customerintel\event\anomaly_detected', $event);
        $this->assertEquals($anomaly['runid'], $event->objectid);
        $this->assertEquals($anomaly['metrickey'], $event->other['metric']);
        $this->assertEquals($anomaly['z_score'], $event->other['z_score']);
        $this->assertEquals($anomaly['severity'], $event->other['severity']);
        
        $sink->close();
    }
    
    /**
     * Test forecast accuracy with known data patterns
     * 
     * @covers ::forecast_metric_trend
     * @covers ::calculate_linear_regression
     */
    public function test_forecast_accuracy() {
        // The test data has a slight upward trend for qa_score_total
        $forecast = $this->predictive_engine->forecast_metric_trend('qa_score_total', 7);
        
        $this->assertArrayNotHasKey('error', $forecast);
        
        $regression = $forecast['regression'];
        
        // With the upward trend in test data, slope should be positive
        $this->assertGreaterThan(0, $regression['slope'], 'Should detect positive trend in QA scores');
        
        // R-squared should indicate some correlation (though may be low due to noise)
        $this->assertGreaterThanOrEqual(0, $regression['r_squared']);
        $this->assertLessThanOrEqual(1, $regression['r_squared']);
        
        // Forecast values should be reasonable
        $forecast_values = $forecast['forecast']['values'];
        foreach ($forecast_values as $value) {
            $this->assertGreaterThanOrEqual(0, $value, 'Forecast values should be non-negative');
            $this->assertLessThanOrEqual(1.5, $value, 'Forecast values should be reasonable for QA scores');
        }
    }
    
    /**
     * Test performance with large datasets
     * 
     * @covers ::detect_anomalies
     */
    public function test_performance_with_large_dataset() {
        $start_time = microtime(true);
        
        // Test anomaly detection which processes all recent data
        $anomalies = $this->predictive_engine->detect_anomalies('qa_score_total', 2.0);
        
        $execution_time = microtime(true) - $start_time;
        
        // Performance assertion (should complete under 1 second even with 60 days of data)
        $this->assertLessThan(1.0, $execution_time, 'Anomaly detection should complete under 1 second');
        
        error_log("Anomaly Detection Performance Test: {$execution_time} seconds for 60 days of data");
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        parent::tearDown();
    }
}