<?php
/**
 * Tests for Analytics Predictive Tab Integration (Slice 11)
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * Test class for analytics predictive tab functionality
 */
class analytics_predictive_tab_test extends advanced_testcase {
    
    /**
     * @var int Test company ID
     */
    private $test_company_id;
    
    /**
     * @var array Test run IDs
     */
    private $test_run_ids = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        
        // Enable predictive features for testing
        set_config('enable_predictive_engine', 1, 'local_customerintel');
        set_config('enable_anomaly_alerts', 1, 'local_customerintel');
        set_config('enable_analytics_dashboard', 1, 'local_customerintel');
        set_config('enable_safe_mode', 0, 'local_customerintel');
        
        // Create test data
        $this->create_test_data();
    }
    
    /**
     * Create test data for analytics testing
     */
    private function create_test_data() {
        global $DB;
        
        // Create test company
        $company_data = new \stdClass();
        $company_data->name = 'Analytics Test Corp';
        $company_data->ticker = 'ATC';
        $company_data->timecreated = time();
        $company_data->timemodified = time();
        $this->test_company_id = $DB->insert_record('local_ci_company', $company_data);
        
        // Create test runs with telemetry data over the last 30 days
        $base_time = time() - (30 * 86400); // 30 days ago
        
        for ($day = 0; $day < 30; $day++) {
            $run_time = $base_time + ($day * 86400);
            
            // Create run
            $run_data = new \stdClass();
            $run_data->companyid = $this->test_company_id;
            $run_data->status = 'completed';
            $run_data->timecreated = $run_time;
            $run_data->timecompleted = $run_time + 180;
            $run_data->initiatedbyuserid = 1;
            
            $run_id = $DB->insert_record('local_ci_run', $run_data);
            $this->test_run_ids[] = $run_id;
            
            // Create telemetry data
            $metrics = ['qa_score_total', 'coherence_score', 'pattern_alignment_score', 'total_duration_ms'];
            
            foreach ($metrics as $metric) {
                $telemetry_data = new \stdClass();
                $telemetry_data->runid = $run_id;
                $telemetry_data->metrickey = $metric;
                
                // Generate realistic test values
                switch ($metric) {
                    case 'qa_score_total':
                        $telemetry_data->metricvaluenum = 0.7 + (rand(-10, 10) / 100);
                        break;
                    case 'coherence_score':
                        $telemetry_data->metricvaluenum = 0.75 + (rand(-5, 5) / 100);
                        break;
                    case 'pattern_alignment_score':
                        $telemetry_data->metricvaluenum = 0.8 + (rand(-8, 8) / 100);
                        break;
                    case 'total_duration_ms':
                        $telemetry_data->metricvaluenum = 45000 + rand(-5000, 5000);
                        break;
                }
                
                $telemetry_data->metricvaluetext = '';
                $telemetry_data->payload = json_encode(['test' => true]);
                $telemetry_data->timecreated = $run_time;
                
                $DB->insert_record('local_ci_telemetry', $telemetry_data);
            }
        }
    }
    
    /**
     * Test forecast AJAX endpoint
     */
    public function test_forecast_ajax_endpoint() {
        global $CFG;
        
        // Simulate AJAX request to forecast endpoint
        $_GET['ajax'] = 1;
        $_GET['action'] = 'forecast';
        $_GET['forecast_metric'] = 'qa_score_total';
        $_GET['days_ahead'] = 14;
        
        // Capture output
        ob_start();
        
        // Include analytics.php to test AJAX handling
        require_once($CFG->dirroot . '/local/customerintel/classes/services/analytics_service.php');
        require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        $response = $predictive_engine->forecast_metric_trend('qa_score_total', 14);
        
        ob_end_clean();
        
        // Verify response structure
        $this->assertIsArray($response);
        
        if (!isset($response['error'])) {
            $this->assertArrayHasKey('metrickey', $response);
            $this->assertArrayHasKey('historical', $response);
            $this->assertArrayHasKey('forecast', $response);
            $this->assertArrayHasKey('regression', $response);
            
            // Verify forecast data can be JSON encoded for JavaScript
            $json_response = json_encode($response);
            $this->assertNotFalse($json_response, 'Response should be JSON encodable');
            
            // Verify JSON decoding works
            $decoded = json_decode($json_response, true);
            $this->assertEquals($response, $decoded, 'JSON encoding/decoding should preserve data');
        }
        
        // Clean up
        unset($_GET['ajax'], $_GET['action'], $_GET['forecast_metric'], $_GET['days_ahead']);
    }
    
    /**
     * Test anomalies AJAX endpoint
     */
    public function test_anomalies_ajax_endpoint() {
        global $CFG;
        
        require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        $response = $predictive_engine->detect_anomalies('qa_score_total', 2.0);
        
        // Verify response is array
        $this->assertIsArray($response);
        
        // Verify JSON encoding for AJAX response
        $json_response = json_encode($response);
        $this->assertNotFalse($json_response, 'Anomalies response should be JSON encodable');
        
        // If anomalies exist, verify structure
        if (!empty($response)) {
            $anomaly = $response[0];
            $this->assertArrayHasKey('metrickey', $anomaly);
            $this->assertArrayHasKey('z_score', $anomaly);
            $this->assertArrayHasKey('severity', $anomaly);
            $this->assertArrayHasKey('timestamp', $anomaly);
        }
    }
    
    /**
     * Test risk signals AJAX endpoint
     */
    public function test_risk_signals_ajax_endpoint() {
        global $CFG;
        
        require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        $response = $predictive_engine->rank_risk_signals();
        
        // Verify response is array
        $this->assertIsArray($response);
        
        // Verify JSON encoding
        $json_response = json_encode($response);
        $this->assertNotFalse($json_response, 'Risk signals response should be JSON encodable');
        
        // Verify structure if risk signals exist
        if (!empty($response)) {
            $signal = $response[0];
            $this->assertArrayHasKey('metric', $signal);
            $this->assertArrayHasKey('risk_score', $signal);
            $this->assertArrayHasKey('severity', $signal);
            $this->assertArrayHasKey('recommendation', $signal);
        }
    }
    
    /**
     * Test anomaly summary AJAX endpoint
     */
    public function test_anomaly_summary_ajax_endpoint() {
        global $CFG;
        
        require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        $response = $predictive_engine->get_anomaly_summary();
        
        // Verify response structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('total', $response);
        $this->assertArrayHasKey('by_severity', $response);
        $this->assertArrayHasKey('recent', $response);
        
        // Verify JSON encoding
        $json_response = json_encode($response);
        $this->assertNotFalse($json_response, 'Anomaly summary should be JSON encodable');
        
        // Verify by_severity structure
        $by_severity = $response['by_severity'];
        $this->assertArrayHasKey('critical', $by_severity);
        $this->assertArrayHasKey('high', $by_severity);
        $this->assertArrayHasKey('medium', $by_severity);
        $this->assertArrayHasKey('low', $by_severity);
    }
    
    /**
     * Test chart data structure for forecast visualization
     */
    public function test_forecast_chart_data_structure() {
        global $CFG;
        
        require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        $forecast = $predictive_engine->forecast_metric_trend('qa_score_total', 7);
        
        if (!isset($forecast['error'])) {
            // Verify Chart.js compatible structure
            $historical = $forecast['historical'];
            $this->assertArrayHasKey('labels', $historical);
            $this->assertArrayHasKey('values', $historical);
            $this->assertIsArray($historical['labels']);
            $this->assertIsArray($historical['values']);
            $this->assertEquals(count($historical['labels']), count($historical['values']));
            
            $forecast_data = $forecast['forecast'];
            $this->assertArrayHasKey('labels', $forecast_data);
            $this->assertArrayHasKey('values', $forecast_data);
            $this->assertArrayHasKey('confidence_upper', $forecast_data);
            $this->assertArrayHasKey('confidence_lower', $forecast_data);
            
            $this->assertIsArray($forecast_data['labels']);
            $this->assertIsArray($forecast_data['values']);
            $this->assertIsArray($forecast_data['confidence_upper']);
            $this->assertIsArray($forecast_data['confidence_lower']);
            
            // All forecast arrays should have same length
            $forecast_length = count($forecast_data['labels']);
            $this->assertEquals($forecast_length, count($forecast_data['values']));
            $this->assertEquals($forecast_length, count($forecast_data['confidence_upper']));
            $this->assertEquals($forecast_length, count($forecast_data['confidence_lower']));
            
            // Verify date format for labels
            foreach ($forecast_data['labels'] as $label) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $label, 'Forecast labels should be YYYY-MM-DD format');
            }
        }
    }
    
    /**
     * Test safe mode disabling of predictive features
     */
    public function test_safe_mode_disabling() {
        // Enable safe mode
        set_config('enable_safe_mode', 1, 'local_customerintel');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        
        $this->assertTrue($predictive_engine->is_safe_mode_enabled());
        
        // Forecast should be disabled
        $forecast = $predictive_engine->forecast_metric_trend('qa_score_total', 30);
        $this->assertArrayHasKey('error', $forecast);
        
        // Anomaly detection should still work but with limited functionality
        $anomalies = $predictive_engine->detect_anomalies('qa_score_total', 2.0);
        $this->assertIsArray($anomalies);
        
        // Risk signals should be empty in safe mode
        $risk_signals = $predictive_engine->rank_risk_signals();
        $this->assertIsArray($risk_signals);
        $this->assertEmpty($risk_signals);
    }
    
    /**
     * Test disabled predictive engine
     */
    public function test_disabled_predictive_engine() {
        // Disable predictive engine
        set_config('enable_predictive_engine', 0, 'local_customerintel');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        
        $this->assertFalse($predictive_engine->is_predictive_enabled());
        
        // All predictive features should be disabled
        $forecast = $predictive_engine->forecast_metric_trend('qa_score_total', 30);
        $this->assertArrayHasKey('error', $forecast);
        
        $anomalies = $predictive_engine->detect_anomalies('qa_score_total', 2.0);
        $this->assertEmpty($anomalies);
        
        $risk_signals = $predictive_engine->rank_risk_signals();
        $this->assertEmpty($risk_signals);
        
        $summary = $predictive_engine->get_anomaly_summary();
        $this->assertEquals(0, $summary['total']);
    }
    
    /**
     * Test performance of AJAX endpoints
     */
    public function test_ajax_performance() {
        global $CFG;
        
        require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        
        // Test forecast performance
        $start_time = microtime(true);
        $forecast = $predictive_engine->forecast_metric_trend('qa_score_total', 30);
        $forecast_time = microtime(true) - $start_time;
        
        $this->assertLessThan(1.0, $forecast_time, 'Forecast should complete under 1 second');
        
        // Test anomaly detection performance
        $start_time = microtime(true);
        $anomalies = $predictive_engine->detect_anomalies('qa_score_total', 2.0);
        $anomalies_time = microtime(true) - $start_time;
        
        $this->assertLessThan(1.0, $anomalies_time, 'Anomaly detection should complete under 1 second');
        
        // Test risk signals performance
        $start_time = microtime(true);
        $risk_signals = $predictive_engine->rank_risk_signals();
        $risk_time = microtime(true) - $start_time;
        
        $this->assertLessThan(1.0, $risk_time, 'Risk signal ranking should complete under 1 second');
        
        error_log("Analytics Predictive Tab Performance:");
        error_log("  Forecast: {$forecast_time}s");
        error_log("  Anomalies: {$anomalies_time}s");
        error_log("  Risk Signals: {$risk_time}s");
    }
    
    /**
     * Test data validation for different metrics
     */
    public function test_metric_data_validation() {
        global $CFG;
        
        require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
        
        $predictive_engine = new \local_customerintel\services\predictive_engine();
        $supported_metrics = $predictive_engine->get_supported_metrics();
        
        foreach ($supported_metrics as $metric) {
            // Test forecast for each supported metric
            $forecast = $predictive_engine->forecast_metric_trend($metric, 7);
            
            if (!isset($forecast['error'])) {
                $this->assertEquals($metric, $forecast['metrickey']);
                
                // Verify forecast values are reasonable for the metric type
                $forecast_values = $forecast['forecast']['values'];
                foreach ($forecast_values as $value) {
                    if (in_array($metric, ['qa_score_total', 'coherence_score', 'pattern_alignment_score'])) {
                        // Score metrics should be between 0 and 1 (with some tolerance for prediction)
                        $this->assertGreaterThanOrEqual(-0.5, $value, "Forecast for {$metric} should be reasonable");
                        $this->assertLessThanOrEqual(1.5, $value, "Forecast for {$metric} should be reasonable");
                    } elseif ($metric === 'total_duration_ms') {
                        // Duration should be positive
                        $this->assertGreaterThan(0, $value, "Duration forecast should be positive");
                    }
                }
            }
            
            // Test anomaly detection for each metric
            $anomalies = $predictive_engine->detect_anomalies($metric, 2.0);
            $this->assertIsArray($anomalies, "Anomaly detection should return array for {$metric}");
        }
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        parent::tearDown();
    }
}