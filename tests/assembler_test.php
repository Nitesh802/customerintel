<?php
/**
 * Assembler unit tests
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

use advanced_testcase;
use local_customerintel\services\assembler;
use local_customerintel\services\versioning_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/assembler.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/versioning_service.php');

/**
 * Assembler test class
 * 
 * @group local_customerintel
 * @covers \local_customerintel\services\assembler
 */
class assembler_test extends advanced_testcase {

    /** @var assembler Assembler instance */
    protected $assembler;

    /** @var int Test company ID */
    protected $companyid;

    /** @var int Test run ID */
    protected $runid;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->assembler = new assembler();
        
        // Create test data
        $this->create_test_data();
    }

    /**
     * Test assemble_report method
     */
    public function test_assemble_report() {
        // Assemble report
        $reportdata = $this->assembler->assemble_report($this->runid);
        
        // Verify structure
        $this->assertIsArray($reportdata);
        $this->assertArrayHasKey('company', $reportdata);
        $this->assertArrayHasKey('run', $reportdata);
        $this->assertArrayHasKey('phases', $reportdata);
        $this->assertArrayHasKey('progress', $reportdata);
        $this->assertArrayHasKey('telemetry', $reportdata);
        $this->assertArrayHasKey('exporturl', $reportdata);
        $this->assertArrayHasKey('dashboardurl', $reportdata);
        
        // Verify company data
        $this->assertIsObject($reportdata['company']);
        $this->assertEquals($this->companyid, $reportdata['company']->id);
        
        // Verify progress
        $this->assertIsArray($reportdata['progress']);
        $this->assertArrayHasKey('completed', $reportdata['progress']);
        $this->assertArrayHasKey('total', $reportdata['progress']);
        $this->assertArrayHasKey('percentage', $reportdata['progress']);
        $this->assertEquals(15, $reportdata['progress']['total']);
        
        // Verify phases
        $this->assertIsArray($reportdata['phases']);
        $this->assertCount(9, $reportdata['phases']); // 9 phases as per TSX structure
        
        foreach ($reportdata['phases'] as $phase) {
            $this->assertArrayHasKey('id', $phase);
            $this->assertArrayHasKey('title', $phase);
            $this->assertArrayHasKey('items', $phase);
            $this->assertArrayHasKey('itemcount', $phase);
        }
    }

    /**
     * Test phase mapping
     */
    public function test_map_to_phases() {
        global $DB;
        
        // Get NB results
        $nbresults = $DB->get_records('local_ci_nb_result', ['runid' => $this->runid]);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('map_to_phases');
        $method->setAccessible(true);
        
        $phases = $method->invoke($this->assembler, $nbresults);
        
        $this->assertIsArray($phases);
        $this->assertCount(9, $phases);
        
        // Verify Phase 1 contains NB1 and NB2
        $phase1items = array_column($phases[0]['items'], 'id');
        $this->assertContains('NB1', $phase1items);
        $this->assertContains('NB2', $phase1items);
        
        // Verify Phase 8 contains NB14
        $phase8items = array_column($phases[7]['items'], 'id');
        $this->assertContains('NB14', $phase8items);
        
        // Verify Phase 9 contains NB15
        $phase9items = array_column($phases[8]['items'], 'id');
        $this->assertContains('NB15', $phase9items);
    }

    /**
     * Test citation generation
     */
    public function test_generate_citation_list() {
        $citations = [
            ['source_id' => 1, 'quote' => 'Test quote 1', 'page' => '10'],
            ['source_id' => 2, 'quote' => 'Test quote 2', 'page' => '20']
        ];
        
        $formattedcitations = $this->assembler->generate_citation_list('NB1', $citations);
        
        $this->assertIsArray($formattedcitations);
        $this->assertCount(2, $formattedcitations);
        
        foreach ($formattedcitations as $citation) {
            $this->assertArrayHasKey('id', $citation);
            $this->assertArrayHasKey('sourceid', $citation);
            $this->assertArrayHasKey('title', $citation);
            $this->assertArrayHasKey('url', $citation);
            $this->assertArrayHasKey('quote', $citation);
            $this->assertArrayHasKey('page', $citation);
            $this->assertArrayHasKey('hasurl', $citation);
            $this->assertArrayHasKey('icon', $citation);
        }
    }

    /**
     * Test diff view rendering
     */
    public function test_render_diff_view() {
        global $DB;
        
        // Create two snapshots
        $snapshot1 = new \stdClass();
        $snapshot1->companyid = $this->companyid;
        $snapshot1->runid = $this->runid;
        $snapshot1->snapshotjson = json_encode(['nb_results' => []]);
        $snapshot1->timecreated = time() - 3600;
        $snapshot1->timemodified = time() - 3600;
        $snapshot1id = $DB->insert_record('local_ci_snapshot', $snapshot1);
        
        $snapshot2 = new \stdClass();
        $snapshot2->companyid = $this->companyid;
        $snapshot2->runid = $this->runid;
        $snapshot2->snapshotjson = json_encode(['nb_results' => ['NB1' => ['test' => 'data']]]);
        $snapshot2->timecreated = time();
        $snapshot2->timemodified = time();
        $snapshot2id = $DB->insert_record('local_ci_snapshot', $snapshot2);
        
        // Create diff
        $diff = new \stdClass();
        $diff->fromsnapshotid = $snapshot1id;
        $diff->tosnapshotid = $snapshot2id;
        $diff->diffjson = json_encode([
            'nb_diffs' => [
                [
                    'nb_code' => 'NB1',
                    'added' => ['test' => 'data'],
                    'changed' => [],
                    'removed' => []
                ]
            ]
        ]);
        $diff->timecreated = time();
        $diff->timemodified = time();
        $DB->insert_record('local_ci_diff', $diff);
        
        // Render diff view
        $diffview = $this->assembler->render_diff_view($snapshot2id, $snapshot1id);
        
        $this->assertIsArray($diffview);
        $this->assertArrayHasKey('hasdiff', $diffview);
        $this->assertArrayHasKey('summary', $diffview);
        $this->assertArrayHasKey('nbchanges', $diffview);
        
        $this->assertTrue($diffview['hasdiff']);
        
        // Verify summary
        $this->assertIsArray($diffview['summary']);
        $this->assertArrayHasKey('total', $diffview['summary']);
        $this->assertArrayHasKey('added', $diffview['summary']);
        $this->assertArrayHasKey('changed', $diffview['summary']);
        $this->assertArrayHasKey('removed', $diffview['summary']);
    }

    /**
     * Test telemetry retrieval
     */
    public function test_get_run_telemetry() {
        global $DB;
        
        // Add telemetry records
        $telemetry1 = new \stdClass();
        $telemetry1->runid = $this->runid;
        $telemetry1->metrickey = 'NB1_tokens';
        $telemetry1->metricvaluenum = 1000;
        $telemetry1->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $telemetry1);
        
        $telemetry2 = new \stdClass();
        $telemetry2->runid = $this->runid;
        $telemetry2->metrickey = 'NB1_duration_ms';
        $telemetry2->metricvaluenum = 5000;
        $telemetry2->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $telemetry2);
        
        $telemetry3 = new \stdClass();
        $telemetry3->runid = $this->runid;
        $telemetry3->metrickey = 'NB1_cost';
        $telemetry3->metricvaluenum = 0.05;
        $telemetry3->timecreated = time();
        $DB->insert_record('local_ci_telemetry', $telemetry3);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('get_run_telemetry');
        $method->setAccessible(true);
        
        $telemetry = $method->invoke($this->assembler, $this->runid);
        
        $this->assertIsArray($telemetry);
        $this->assertArrayHasKey('tokens', $telemetry);
        $this->assertArrayHasKey('duration', $telemetry);
        $this->assertArrayHasKey('cost', $telemetry);
        $this->assertArrayHasKey('records', $telemetry);
        
        $this->assertEquals('1,000', $telemetry['tokens']);
        $this->assertEquals('5s', $telemetry['duration']);
        $this->assertEquals('$0.0500', $telemetry['cost']);
        $this->assertEquals(3, $telemetry['records']);
    }

    /**
     * Test format runtime
     */
    public function test_format_runtime() {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('format_runtime');
        $method->setAccessible(true);
        
        $start = time() - 125; // 2 minutes 5 seconds ago
        $end = time();
        
        $runtime = $method->invoke($this->assembler, $start, $end);
        
        $this->assertIsString($runtime);
        $this->assertEquals('2m 5s', $runtime);
        
        // Test with empty values
        $runtime = $method->invoke($this->assembler, 0, 0);
        $this->assertEquals('N/A', $runtime);
    }

    /**
     * Test format duration
     */
    public function test_format_duration() {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('format_duration');
        $method->setAccessible(true);
        
        // Test milliseconds
        $duration = $method->invoke($this->assembler, 500);
        $this->assertEquals('500ms', $duration);
        
        // Test seconds
        $duration = $method->invoke($this->assembler, 5500);
        $this->assertEquals('5.5s', $duration);
        
        // Test minutes
        $duration = $method->invoke($this->assembler, 125000);
        $this->assertEquals('2m 5s', $duration);
    }

    /**
     * Test apply diff highlighting
     */
    public function test_apply_diff_highlighting() {
        $reportdata = [
            'phases' => [
                [
                    'sections' => [
                        ['nbcode' => 'NB1', 'data' => 'test']
                    ]
                ]
            ]
        ];
        
        $diff = [
            'nb_diffs' => [
                [
                    'nb_code' => 'NB1',
                    'changed' => ['field1' => ['old' => 'a', 'new' => 'b']],
                    'added' => ['field2' => 'c'],
                    'removed' => []
                ]
            ]
        ];
        
        $highlighted = $this->assembler->apply_diff_highlighting($reportdata, $diff);
        
        $this->assertIsArray($highlighted);
        $this->assertArrayHasKey('change_summary', $highlighted);
        
        $summary = $highlighted['change_summary'];
        $this->assertArrayHasKey('total_nb_changes', $summary);
        $this->assertArrayHasKey('changes_by_type', $summary);
        $this->assertArrayHasKey('affected_nbs', $summary);
    }

    /**
     * Test source icon generation
     */
    public function test_get_source_icon() {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('get_source_icon');
        $method->setAccessible(true);
        
        $icon = $method->invoke($this->assembler, 'url');
        $this->assertStringContainsString('fa-link', $icon);
        
        $icon = $method->invoke($this->assembler, 'file');
        $this->assertStringContainsString('fa-file', $icon);
        
        $icon = $method->invoke($this->assembler, 'manual_text');
        $this->assertStringContainsString('fa-edit', $icon);
        
        $icon = $method->invoke($this->assembler, 'unknown');
        $this->assertStringContainsString('fa-circle', $icon);
    }

    /**
     * Test format generic response formatting
     */
    public function test_format_generic_response() {
        // Test data with various structures
        $testdata = [
            'summary' => 'This is a test summary of the analysis.',
            'key_findings' => [
                'Finding 1: Revenue growth declining',
                'Finding 2: Market share under pressure'
            ],
            'risk_assessment' => [
                'high_risks' => ['Competitive pressure', 'Regulatory changes'],
                'medium_risks' => ['Supply chain disruption'],
                'low_risks' => ['Currency fluctuation']
            ],
            'recommendation' => 'Focus on digital transformation initiatives',
            'confidence_score' => 85
        ];
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('format_generic_response');
        $method->setAccessible(true);
        
        $html = $method->invoke($this->assembler, $testdata);
        
        // Verify structure
        $this->assertIsString($html);
        $this->assertStringContainsString('<div class="nb-response">', $html);
        $this->assertStringContainsString('<div class="nb-summary">', $html);
        $this->assertStringContainsString('This is a test summary', $html);
        
        // Verify list formatting
        $this->assertStringContainsString('<h5>Key Findings</h5>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Finding 1: Revenue growth declining</li>', $html);
        
        // Verify nested object formatting
        $this->assertStringContainsString('<h5>Risk Assessment</h5>', $html);
        $this->assertStringContainsString('High Risks', $html);
        
        // Verify field formatting
        $this->assertStringContainsString('<h5>Recommendation</h5>', $html);
        $this->assertStringContainsString('Focus on digital transformation', $html);
        $this->assertStringContainsString('<h5>Confidence Score</h5>', $html);
        $this->assertStringContainsString('85', $html);
    }

    /**
     * Test structured response formatting
     */
    public function test_format_structured_response() {
        $testdata = [
            'summary' => 'Strategic analysis summary',
            'strategic_initiatives' => ['Digital transformation', 'Market expansion'],
            'competitive_positioning' => 'Strong market leader',
            'unknown_field' => 'This field is not in mapping'
        ];
        
        $fieldmapping = [
            'strategic_initiatives' => 'Strategic Initiatives',
            'competitive_positioning' => 'Competitive Position',
            'summary' => 'Executive Summary'
        ];
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('format_structured_response');
        $method->setAccessible(true);
        
        $html = $method->invoke($this->assembler, $testdata, $fieldmapping);
        
        // Verify structure
        $this->assertIsString($html);
        $this->assertStringContainsString('<div class="nb-response">', $html);
        $this->assertStringContainsString('Strategic analysis summary', $html);
        
        // Verify mapped fields use proper labels
        $this->assertStringContainsString('<h5>Strategic Initiatives</h5>', $html);
        $this->assertStringContainsString('<h5>Competitive Position</h5>', $html);
        
        // Verify unmapped fields are processed with auto-generated labels
        $this->assertStringContainsString('<h5>Unknown Field</h5>', $html);
        $this->assertStringContainsString('This field is not in mapping', $html);
    }

    /**
     * Test NB-specific formatting methods
     */
    public function test_nb_specific_formatting() {
        $testdata = [
            'summary' => 'Financial health appears stable',
            'revenue_trends' => ['Q1: +5%', 'Q2: +3%', 'Q3: +2%'],
            'profitability' => 'EBITDA margin at 15%',
            'cash_flow' => ['Operating: $100M', 'Free: $80M']
        ];
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->assembler);
        $method = $reflection->getMethod('format_financial_health');
        $method->setAccessible(true);
        
        $html = $method->invoke($this->assembler, $testdata);
        
        // Verify specific formatting for financial health
        $this->assertIsString($html);
        $this->assertStringContainsString('Financial health appears stable', $html);
        $this->assertStringContainsString('<h5>Revenue Trends</h5>', $html);
        $this->assertStringContainsString('<h5>Profitability Analysis</h5>', $html);
        $this->assertStringContainsString('EBITDA margin at 15%', $html);
    }

    /**
     * Create test data
     */
    protected function create_test_data() {
        global $DB, $USER;
        
        // Create test company
        $company = new \stdClass();
        $company->name = 'Test Company';
        $company->ticker = 'TEST';
        $company->type = 'customer';
        $company->website = 'https://test.com';
        $company->sector = 'Technology';
        $company->metadata = json_encode(['test' => true]);
        $company->timecreated = time();
        $company->timemodified = time();
        $this->companyid = $DB->insert_record('local_ci_company', $company);
        
        // Create test run
        $run = new \stdClass();
        $run->companyid = $this->companyid;
        $run->initiatedbyuserid = $USER->id;
        $run->userid = $USER->id;
        $run->mode = 'full';
        $run->status = 'completed';
        $run->timestarted = time() - 3600;
        $run->timecompleted = time();
        $run->actualtokens = 50000;
        $run->actualcost = 0.50;
        $run->timecreated = time();
        $run->timemodified = time();
        $this->runid = $DB->insert_record('local_ci_run', $run);
        
        // Create NB results for all 15 NBs
        for ($i = 1; $i <= 15; $i++) {
            $nbresult = new \stdClass();
            $nbresult->runid = $this->runid;
            $nbresult->nbcode = 'NB' . $i;
            $nbresult->jsonpayload = json_encode([
                'summary' => "Test summary for NB$i",
                'key_points' => ["Point 1", "Point 2"],
                'citations' => [['source_id' => 1]]
            ]);
            $nbresult->citations = json_encode([
                ['source_id' => 1, 'quote' => "Test quote for NB$i"]
            ]);
            $nbresult->durationms = rand(1000, 5000);
            $nbresult->tokensused = rand(100, 1000);
            $nbresult->status = 'completed';
            $nbresult->timecreated = time();
            $nbresult->timemodified = time();
            
            $DB->insert_record('local_ci_nb_result', $nbresult);
        }
        
        // Create test sources
        for ($i = 1; $i <= 3; $i++) {
            $source = new \stdClass();
            $source->companyid = $this->companyid;
            $source->type = 'url';
            $source->title = "Test Source $i";
            $source->url = "https://example.com/source$i";
            $source->addedbyuserid = $USER->id;
            $source->approved = 1;
            $source->rejected = 0;
            $source->hash = sha1("source$i");
            $source->timecreated = time();
            $source->timemodified = time();
            
            $DB->insert_record('local_ci_source', $source);
        }
    }
}