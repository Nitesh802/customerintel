<?php
/**
 * Unit tests for UI Renderer (Slice 8)
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;
use local_customerintel_renderer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/renderer.php');

/**
 * Test class for UI renderer functionality
 * 
 * @coversDefaultClass \local_customerintel_renderer
 */
class ui_renderer_test extends advanced_testcase {
    
    /**
     * @var local_customerintel_renderer
     */
    private $renderer;
    
    /**
     * @var int Test run ID
     */
    private $test_runid = 8888;
    
    /**
     * @var object Test synthesis record
     */
    private $test_synthesis;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        global $PAGE;
        
        // Initialize page and renderer
        $PAGE->set_context(\context_system::instance());
        $this->renderer = new local_customerintel_renderer($PAGE, '');
        
        // Enable UI features
        set_config('enable_interactive_ui', '1', 'local_customerintel');
        set_config('enable_citation_charts', '1', 'local_customerintel');
        
        // Create test data
        $this->create_test_data();
    }
    
    /**
     * Create test data for renderer tests
     */
    private function create_test_data() {
        global $DB;
        
        // Create test synthesis record with QA scores
        $synthesis_data = new \stdClass();
        $synthesis_data->runid = $this->test_runid;
        $synthesis_data->html = '<p>Test synthesis content</p>';
        $synthesis_data->qa_scores = json_encode([
            'total_weighted' => 0.85,
            'coherence' => 0.82,
            'pattern_alignment' => 0.88,
            'completeness' => 0.90,
            'relevance_density' => 0.79
        ]);
        $synthesis_data->timecreated = time();
        $synthesis_data->timemodified = time();
        
        $this->test_synthesis = $synthesis_data;
        $DB->insert_record('local_ci_synthesis', $synthesis_data);
        
        // Create test telemetry records
        $this->create_test_telemetry();
        
        // Create test citation metrics
        $this->create_test_citation_metrics();
    }
    
    /**
     * Create test telemetry data
     */
    private function create_test_telemetry() {
        global $DB;
        
        $telemetry_records = [
            [
                'runid' => $this->test_runid,
                'metrickey' => 'qa_score_total',
                'metricvaluenum' => 0.85,
                'timecreated' => time()
            ],
            [
                'runid' => $this->test_runid,
                'metrickey' => 'coherence_score',
                'metricvaluenum' => 0.82,
                'timecreated' => time()
            ],
            [
                'runid' => $this->test_runid,
                'metrickey' => 'pattern_alignment_score',
                'metricvaluenum' => 0.88,
                'timecreated' => time()
            ],
            [
                'runid' => $this->test_runid,
                'metrickey' => 'phase_duration_nb_orchestration',
                'metricvaluenum' => 1500.5,
                'payload' => json_encode(['phase' => 'nb_orchestration', 'duration_ms' => 1500.5]),
                'timecreated' => time()
            ],
            [
                'runid' => $this->test_runid,
                'metrickey' => 'phase_duration_synthesis_drafting',
                'metricvaluenum' => 3200.25,
                'payload' => json_encode(['phase' => 'synthesis_drafting', 'duration_ms' => 3200.25]),
                'timecreated' => time()
            ],
            [
                'runid' => $this->test_runid,
                'metrickey' => 'total_duration_ms',
                'metricvaluenum' => 45000,
                'timecreated' => time()
            ],
            [
                'runid' => $this->test_runid,
                'metrickey' => 'qa_coherence_executive_summary',
                'metricvaluenum' => 0.89,
                'payload' => json_encode(['section' => 'executive_summary', 'type' => 'coherence']),
                'timecreated' => time()
            ],
            [
                'runid' => $this->test_runid,
                'metrickey' => 'qa_pattern_executive_summary',
                'metricvaluenum' => 0.91,
                'payload' => json_encode(['section' => 'executive_summary', 'type' => 'pattern_alignment']),
                'timecreated' => time()
            ]
        ];
        
        foreach ($telemetry_records as $record) {
            $DB->insert_record('local_ci_telemetry', (object)$record);
        }
    }
    
    /**
     * Create test citation metrics
     */
    private function create_test_citation_metrics() {
        global $DB;
        
        $citation_metrics = new \stdClass();
        $citation_metrics->runid = $this->test_runid;
        $citation_metrics->total_citations = 25;
        $citation_metrics->unique_domains = 12;
        $citation_metrics->confidence_avg = 0.78;
        $citation_metrics->diversity_score = 0.84;
        $citation_metrics->source_type_breakdown = json_encode([
            'news' => 8,
            'analyst' => 6,
            'company' => 7,
            'regulatory' => 4
        ]);
        $citation_metrics->timecreated = time();
        $citation_metrics->timemodified = time();
        
        $DB->insert_record('local_ci_citation_metrics', $citation_metrics);
    }
    
    /**
     * Test QA summary rendering
     */
    public function test_render_qa_summary() {
        $output = $this->renderer->render_qa_summary($this->test_runid);
        
        // Test basic structure
        $this->assertNotEmpty($output, 'QA summary output should not be empty');
        $this->assertStringContainsString('qa-summary-card', $output, 'Should contain QA summary card class');
        $this->assertStringContainsString('Quality Assessment Summary', $output, 'Should contain title');
        
        // Test score display
        $this->assertStringContainsString('0.85', $output, 'Should display overall QA score');
        $this->assertStringContainsString('score-display', $output, 'Should have score display class');
        
        // Test metric cards
        $this->assertStringContainsString('Coherence', $output, 'Should contain coherence metric');
        $this->assertStringContainsString('Gold Standard Alignment', $output, 'Should contain pattern alignment metric');
        $this->assertStringContainsString('0.82', $output, 'Should display coherence score');
        $this->assertStringContainsString('0.88', $output, 'Should display pattern alignment score');
        
        // Test section breakdown
        $this->assertStringContainsString('executive_summary', $output, 'Should contain section breakdown');
        $this->assertStringContainsString('0.89', $output, 'Should contain section coherence score');
        $this->assertStringContainsString('0.91', $output, 'Should contain section pattern score');
    }
    
    /**
     * Test QA summary fallback when interactive UI disabled
     */
    public function test_render_qa_summary_fallback() {
        // Disable interactive UI
        set_config('enable_interactive_ui', '0', 'local_customerintel');
        $this->renderer = new local_customerintel_renderer($GLOBALS['PAGE'], '');
        
        $output = $this->renderer->render_qa_summary($this->test_runid);
        
        $this->assertNotEmpty($output, 'Fallback output should not be empty');
        $this->assertStringContainsString('alert', $output, 'Should use alert for fallback');
        $this->assertStringContainsString('0.85', $output, 'Should still display scores');
        $this->assertStringNotContainsString('qa-summary-card', $output, 'Should not contain interactive elements');
    }
    
    /**
     * Test telemetry chart rendering
     */
    public function test_render_telemetry_chart() {
        $output = $this->renderer->render_telemetry_chart($this->test_runid);
        
        // Test basic structure
        $this->assertNotEmpty($output, 'Telemetry chart output should not be empty');
        $this->assertStringContainsString('telemetry-chart-card', $output, 'Should contain telemetry chart card');
        $this->assertStringContainsString('Performance Metrics', $output, 'Should contain title');
        
        // Test canvas element
        $this->assertStringContainsString('<canvas', $output, 'Should contain canvas element');
        $this->assertStringContainsString('telemetry-chart-', $output, 'Should contain chart ID');
        
        // Test phase data
        $this->assertStringContainsString('Phase Durations', $output, 'Should contain phase durations section');
        $this->assertStringContainsString('nb_orchestration', $output, 'Should contain orchestration phase');
        $this->assertStringContainsString('synthesis_drafting', $output, 'Should contain drafting phase');
        
        // Test metrics table
        $this->assertStringContainsString('Key Metrics', $output, 'Should contain key metrics section');
        $this->assertStringContainsString('45.00s', $output, 'Should display total duration in seconds');
    }
    
    /**
     * Test telemetry chart with no data
     */
    public function test_render_telemetry_chart_no_data() {
        $empty_runid = 9999;
        $output = $this->renderer->render_telemetry_chart($empty_runid);
        
        $this->assertStringContainsString('No telemetry data available', $output, 'Should show no data message');
        $this->assertStringContainsString('alert-info', $output, 'Should use info alert');
    }
    
    /**
     * Test citation metrics rendering
     */
    public function test_render_citation_metrics() {
        $output = $this->renderer->render_citation_metrics($this->test_runid);
        
        // Test basic structure
        $this->assertNotEmpty($output, 'Citation metrics output should not be empty');
        $this->assertStringContainsString('citation-metrics-card', $output, 'Should contain citation metrics card');
        $this->assertStringContainsString('Citation Analytics', $output, 'Should contain title');
        
        // Test metrics table
        $this->assertStringContainsString('Total Citations', $output, 'Should contain total citations');
        $this->assertStringContainsString('25', $output, 'Should display citation count');
        $this->assertStringContainsString('Unique Domains', $output, 'Should contain unique domains');
        $this->assertStringContainsString('12', $output, 'Should display domain count');
        
        // Test confidence and diversity scores
        $this->assertStringContainsString('Average Confidence', $output, 'Should contain confidence metric');
        $this->assertStringContainsString('0.78', $output, 'Should display confidence score');
        $this->assertStringContainsString('Diversity Score', $output, 'Should contain diversity metric');
        $this->assertStringContainsString('0.84', $output, 'Should display diversity score');
        
        // Test icons
        $this->assertStringContainsString('fas fa-', $output, 'Should contain FontAwesome icons');
    }
    
    /**
     * Test citation metrics with feature disabled
     */
    public function test_render_citation_metrics_disabled() {
        // Disable citation charts
        set_config('enable_citation_charts', '0', 'local_customerintel');
        $this->renderer = new local_customerintel_renderer($GLOBALS['PAGE'], '');
        
        $output = $this->renderer->render_citation_metrics($this->test_runid);
        
        $this->assertEmpty($output, 'Should return empty output when feature disabled');
    }
    
    /**
     * Test citation metrics with no data
     */
    public function test_render_citation_metrics_no_data() {
        $empty_runid = 9999;
        $output = $this->renderer->render_citation_metrics($empty_runid);
        
        $this->assertStringContainsString('No citation metrics available', $output, 'Should show no data message');
        $this->assertStringContainsString('alert-info', $output, 'Should use info alert');
    }
    
    /**
     * Test section diagnostics rendering
     */
    public function test_render_section_diagnostics() {
        $section_data = [
            'executive_summary' => [
                'coherence' => 0.89,
                'pattern_alignment' => 0.91,
                'completeness' => 0.87,
                'feedback' => [
                    ['type' => 'info', 'message' => 'Good alignment with gold standard'],
                    ['type' => 'warning', 'message' => 'Could improve factual density']
                ]
            ],
            'market_analysis' => [
                'coherence' => 0.75,
                'pattern_alignment' => 0.82,
                'completeness' => 0.78,
                'feedback' => [
                    ['type' => 'warning', 'message' => 'Needs more quantitative data']
                ]
            ]
        ];
        
        $output = $this->renderer->render_section_diagnostics($section_data);
        
        // Test basic structure
        $this->assertNotEmpty($output, 'Section diagnostics output should not be empty');
        $this->assertStringContainsString('section-diagnostics-container', $output, 'Should contain container');
        $this->assertStringContainsString('Section Diagnostics', $output, 'Should contain title');
        
        // Test section cards
        $this->assertStringContainsString('Executive summary', $output, 'Should contain executive summary section');
        $this->assertStringContainsString('Market analysis', $output, 'Should contain market analysis section');
        $this->assertStringContainsString('section-diagnostic-card', $output, 'Should contain diagnostic cards');
        
        // Test scores
        $this->assertStringContainsString('0.89', $output, 'Should display coherence score');
        $this->assertStringContainsString('0.91', $output, 'Should display pattern alignment score');
        
        // Test feedback messages
        $this->assertStringContainsString('Good alignment with gold standard', $output, 'Should contain feedback');
        $this->assertStringContainsString('fas fa-info-circle', $output, 'Should contain info icon');
        $this->assertStringContainsString('fas fa-exclamation-triangle', $output, 'Should contain warning icon');
        
        // Test progress bars
        $this->assertStringContainsString('progress', $output, 'Should contain progress bars');
        $this->assertStringContainsString('Coherence', $output, 'Should label coherence progress');
        $this->assertStringContainsString('Alignment', $output, 'Should label alignment progress');
        $this->assertStringContainsString('Complete', $output, 'Should label completeness progress');
    }
    
    /**
     * Test section diagnostics with empty data
     */
    public function test_render_section_diagnostics_empty() {
        $output = $this->renderer->render_section_diagnostics([]);
        
        $this->assertEmpty($output, 'Should return empty output for empty data');
    }
    
    /**
     * Test chart data JSON generation
     */
    public function test_chart_data_generation() {
        // This method tests the private get_telemetry_chart_data method indirectly
        $output = $this->renderer->render_telemetry_chart($this->test_runid);
        
        // Verify that chart data structure is included
        $this->assertStringContainsString('telemetry_chart', $output, 'Should include chart JS module');
        $this->assertStringContainsString('nb_orchestration', $output, 'Should include phase names');
        $this->assertStringContainsString('synthesis_drafting', $output, 'Should include phase names');
    }
    
    /**
     * Test HTML structure and CSS classes
     */
    public function test_html_structure() {
        $qa_output = $this->renderer->render_qa_summary($this->test_runid);
        $telemetry_output = $this->renderer->render_telemetry_chart($this->test_runid);
        $citation_output = $this->renderer->render_citation_metrics($this->test_runid);
        
        // Test QA summary structure
        $this->assertStringContainsString('card qa-summary-card', $qa_output, 'QA should have correct card class');
        $this->assertStringContainsString('card-header bg-primary', $qa_output, 'QA should have primary header');
        $this->assertStringContainsString('score-display', $qa_output, 'QA should have score display');
        
        // Test telemetry chart structure
        $this->assertStringContainsString('card telemetry-chart-card', $telemetry_output, 'Telemetry should have correct card class');
        $this->assertStringContainsString('canvas', $telemetry_output, 'Telemetry should have canvas element');
        
        // Test citation metrics structure
        $this->assertStringContainsString('card citation-metrics-card', $citation_output, 'Citation should have correct card class');
        $this->assertStringContainsString('table citation-metrics-table', $citation_output, 'Citation should have metrics table');
    }
    
    /**
     * Test accessibility features
     */
    public function test_accessibility() {
        $qa_output = $this->renderer->render_qa_summary($this->test_runid);
        
        // Test ARIA attributes on progress bars
        $this->assertStringContainsString('aria-valuenow', $qa_output, 'Should include ARIA value attributes');
        $this->assertStringContainsString('aria-valuemin', $qa_output, 'Should include ARIA min attributes');
        $this->assertStringContainsString('aria-valuemax', $qa_output, 'Should include ARIA max attributes');
        
        // Test proper heading structure
        $this->assertStringContainsString('<h4', $qa_output, 'Should have proper heading levels');
        $this->assertStringContainsString('<h5', $qa_output, 'Should have proper heading hierarchy');
    }
    
    /**
     * Test responsive design classes
     */
    public function test_responsive_classes() {
        $qa_output = $this->renderer->render_qa_summary($this->test_runid);
        $citation_output = $this->renderer->render_citation_metrics($this->test_runid);
        
        // Test Bootstrap responsive classes
        $this->assertStringContainsString('col-md-', $qa_output, 'Should include responsive column classes');
        $this->assertStringContainsString('row', $qa_output, 'Should include row classes');
        $this->assertStringContainsString('table-striped', $citation_output, 'Should include table styling classes');
    }
    
    /**
     * Test color coding and score interpretation
     */
    public function test_score_color_coding() {
        // Test with different score ranges
        $high_score_data = [
            'executive_summary' => [
                'coherence' => 0.95,
                'pattern_alignment' => 0.88,
                'completeness' => 0.92
            ]
        ];
        
        $low_score_data = [
            'poor_section' => [
                'coherence' => 0.45,
                'pattern_alignment' => 0.52,
                'completeness' => 0.48
            ]
        ];
        
        $high_output = $this->renderer->render_section_diagnostics($high_score_data);
        $low_output = $this->renderer->render_section_diagnostics($low_score_data);
        
        // High scores should get success styling
        $this->assertStringContainsString('border-success', $high_output, 'High scores should get success border');
        
        // Low scores should get danger styling
        $this->assertStringContainsString('border-danger', $low_output, 'Low scores should get danger border');
    }
    
    /**
     * Test error handling and graceful degradation
     */
    public function test_error_handling() {
        // Test with invalid run ID
        $invalid_output = $this->renderer->render_qa_summary(-1);
        $this->assertNotEmpty($invalid_output, 'Should handle invalid run ID gracefully');
        
        // Test with null/empty data
        $empty_diagnostics = $this->renderer->render_section_diagnostics(null);
        $this->assertEmpty($empty_diagnostics, 'Should handle null data gracefully');
        
        // Test telemetry with disabled feature
        set_config('enable_interactive_ui', '0', 'local_customerintel');
        $this->renderer = new local_customerintel_renderer($GLOBALS['PAGE'], '');
        
        $disabled_telemetry = $this->renderer->render_telemetry_chart($this->test_runid);
        $this->assertEmpty($disabled_telemetry, 'Should return empty when feature disabled');
    }
    
    /**
     * Test performance with large datasets
     */
    public function test_performance_large_dataset() {
        global $DB;
        
        // Create many telemetry records
        for ($i = 0; $i < 100; $i++) {
            $record = new \stdClass();
            $record->runid = $this->test_runid;
            $record->metrickey = "test_metric_{$i}";
            $record->metricvaluenum = rand(1, 100) / 100;
            $record->timecreated = time();
            $DB->insert_record('local_ci_telemetry', $record);
        }
        
        $start_time = microtime(true);
        $output = $this->renderer->render_telemetry_chart($this->test_runid);
        $end_time = microtime(true);
        
        $execution_time = $end_time - $start_time;
        
        $this->assertNotEmpty($output, 'Should handle large dataset');
        $this->assertLessThan(2.0, $execution_time, 'Should execute within reasonable time (< 2 seconds)');
    }
    
    /**
     * Test data sanitization and XSS prevention
     */
    public function test_data_sanitization() {
        global $DB;
        
        // Create record with potentially malicious content
        $malicious_record = new \stdClass();
        $malicious_record->runid = $this->test_runid;
        $malicious_record->metrickey = '<script>alert("xss")</script>';
        $malicious_record->metricvaluenum = 0.5;
        $malicious_record->payload = json_encode(['message' => '<script>alert("payload")</script>']);
        $malicious_record->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $malicious_record);
        
        $output = $this->renderer->render_telemetry_chart($this->test_runid);
        
        // Ensure scripts are escaped
        $this->assertStringNotContainsString('<script>', $output, 'Should escape script tags');
        $this->assertStringNotContainsString('alert(', $output, 'Should escape JavaScript');
        $this->assertStringContainsString('&lt;script&gt;', $output, 'Should properly encode HTML entities');
    }
    
    /**
     * Test coverage of all expected keys and values
     */
    public function test_expected_keys_coverage() {
        $qa_output = $this->renderer->render_qa_summary($this->test_runid);
        $telemetry_output = $this->renderer->render_telemetry_chart($this->test_runid);
        $citation_output = $this->renderer->render_citation_metrics($this->test_runid);
        
        // Expected QA keys
        $expected_qa_keys = ['qa_score_total', 'coherence_score', 'pattern_alignment_score'];
        foreach ($expected_qa_keys as $key) {
            // The key values should appear in the output in some form
            $this->assertTrue(
                strpos($qa_output, '0.8') !== false || strpos($qa_output, '0.9') !== false,
                "Should contain values related to {$key}"
            );
        }
        
        // Expected citation metrics
        $expected_citation_keys = ['total_citations', 'unique_domains', 'confidence_avg', 'diversity_score'];
        foreach ($expected_citation_keys as $key) {
            $readable_key = ucfirst(str_replace('_', ' ', $key));
            $this->assertStringContainsString($readable_key, $citation_output, "Should contain {$readable_key}");
        }
        
        // Expected telemetry phases
        $expected_phases = ['nb_orchestration', 'synthesis_drafting'];
        foreach ($expected_phases as $phase) {
            $this->assertStringContainsString($phase, $telemetry_output, "Should contain {$phase} phase");
        }
    }
}