<?php
/**
 * PHPUnit tests for Enhanced Section Methods (Slice 2)
 *
 * @package    local_customerintel
 * @copyright  2024 CustomerIntel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/qa_scorer.php');

use local_customerintel\services\synthesis_engine;
use local_customerintel\services\qa_scorer;

/**
 * Test cases for Enhanced Section Methods
 */
class section_enhancement_test extends \advanced_testcase {

    /** @var synthesis_engine */
    private $engine;
    
    /** @var qa_scorer */
    private $scorer;

    /**
     * Setup before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        
        // Create test instance using reflection to access private methods
        $this->engine = new synthesis_engine();
        $this->scorer = new qa_scorer();
    }
    
    /**
     * Helper to invoke private methods
     */
    private function invoke_private_method($object, $method_name, $params = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $params);
    }
    
    /**
     * Test executive insight depth and patterns
     */
    public function test_executive_insight_gold_standard_depth() {
        // Mock inputs with rich context
        $inputs = [
            'company_source' => ['name' => 'Acme Corporation'],
            'company_target' => ['name' => 'Strategic Partner Inc'],
            'nb' => ['key_insights' => ['digital transformation', 'cost optimization']]
        ];
        
        $patterns = [
            'pressures' => [
                ['text' => 'margin compression', 'impact' => '300 basis points']
            ],
            'numeric_proofs' => [
                ['value' => '25%', 'timeframe' => 'next 18 months']
            ],
            'themes' => ['operational excellence', 'market expansion'],
            'market_signals' => [
                ['signal' => 'industry consolidation'],
                ['signal' => 'regulatory changes'],
                ['signal' => 'technology disruption']
            ]
        ];
        
        // Mock citation manager
        $citation_manager = $this->createMock(\stdClass::class);
        $citation_manager->method('process_section_citations')
            ->willReturnCallback(function($text, $section) {
                return ['text' => $text, 'citations' => []];
            });
        
        $result = $this->invoke_private_method(
            $this->engine, 
            'draft_executive_insight', 
            [$inputs, $patterns, $citation_manager]
        );
        
        // Verify depth indicators
        $this->assertStringContainsString('Acme Corporation', $result['text']);
        $this->assertStringContainsString('Strategic Partner Inc', $result['text']);
        $this->assertStringContainsString('margin compression', $result['text']);
        $this->assertStringContainsString('300 basis points', $result['text']);
        $this->assertStringContainsString('25% growth', $result['text']);
        
        // Check for analytical depth
        $this->assertStringContainsString('convergence', $result['text']);
        $this->assertStringContainsString('cascades', $result['text']);
        $this->assertStringContainsString('tension between', $result['text']);
        
        // Verify Gold Standard quality score
        $qa_score = $this->scorer->calculate_insight_depth(
            $result['text'],
            ['strategic imperatives', 'market dynamics', 'decision framework']
        );
        $this->assertGreaterThan(0.6, $qa_score, "Executive insight should have high depth score");
    }
    
    /**
     * Test customer fundamentals richness
     */
    public function test_customer_fundamentals_segment_analysis() {
        $inputs = [
            'company_source' => ['name' => 'TechCorp'],
            'industry' => ['sector' => 'enterprise software']
        ];
        
        $patterns = [
            'segments' => [
                ['name' => 'Enterprise', 'revenue_share' => '65%', 'growth_rate' => '18%'],
                ['name' => 'Mid-Market', 'revenue_share' => '25%', 'growth_rate' => '22%'],
                ['name' => 'SMB', 'revenue_share' => '10%', 'growth_rate' => '-5%']
            ],
            'revenue_dynamics' => [
                ['pattern' => 'subscription growth', 'impact' => 'positive']
            ]
        ];
        
        $citation_manager = $this->createMock(\stdClass::class);
        $citation_manager->method('process_section_citations')
            ->willReturnCallback(function($text, $section) {
                return ['text' => $text, 'citations' => []];
            });
        
        $result = $this->invoke_private_method(
            $this->engine,
            'draft_customer_fundamentals',
            [$inputs, $patterns, $citation_manager]
        );
        
        // Verify comprehensive segment coverage
        $this->assertStringContainsString('Enterprise', $result['text']);
        $this->assertStringContainsString('65%', $result['text']);
        $this->assertStringContainsString('18%', $result['text']);
        
        // Check for buyer-payer dynamics
        $this->assertStringContainsString('buyer-payer dynamic', $result['text']);
        $this->assertStringContainsString('procurement', $result['text']);
        $this->assertStringContainsString('friction points', $result['text']);
        
        // Verify macro forces analysis
        $this->assertStringContainsString('macro forces', $result['text']);
        $this->assertStringContainsString('budget consolidation', $result['text']);
        
        // Check revenue quality metrics
        $this->assertStringContainsString('net retention', $result['text']);
        $this->assertStringContainsString('gross margins', $result['text']);
        
        // Verify narrative vs reality insight
        $this->assertStringContainsString('Management', $result['text']);
        $this->assertStringContainsString('disconnect', $result['text']);
    }
    
    /**
     * Test financial trajectory pattern recognition
     */
    public function test_financial_trajectory_inflection_analysis() {
        $inputs = [
            'company_source' => ['name' => 'GlobalTech Inc']
        ];
        
        $patterns = [
            'financial_signals' => [
                ['metric' => 'EBITDA margin', 'trend' => 'declining'],
                ['metric' => 'FCF conversion', 'trend' => 'improving']
            ],
            'growth_patterns' => [
                ['segment' => 'cloud services', 'rate' => '35%'],
                ['segment' => 'legacy products', 'rate' => '-12%']
            ]
        ];
        
        $citation_manager = $this->createMock(\stdClass::class);
        $citation_manager->method('process_section_citations')
            ->willReturnCallback(function($text, $section) {
                return ['text' => $text, 'citations' => []];
            });
        
        $result = $this->invoke_private_method(
            $this->engine,
            'draft_financial_trajectory',
            [$inputs, $patterns, $citation_manager]
        );
        
        // Verify inflection point analysis
        $this->assertStringContainsString('inflection point', $result['text']);
        $this->assertStringContainsString('24 months', $result['text']);
        
        // Check revenue momentum layering
        $this->assertStringContainsString('revenue momentum', $result['text']);
        $this->assertStringContainsString('nuanced story', $result['text']);
        $this->assertStringContainsString('segment analysis', $result['text']);
        
        // Verify margin compression analysis
        $this->assertStringContainsString('margin compression', $result['text']);
        $this->assertStringContainsString('structural factors', $result['text']);
        $this->assertStringContainsString('basis points', $result['text']);
        
        // Check cost structure flexibility
        $this->assertStringContainsString('cost structure', $result['text']);
        $this->assertStringContainsString('fixed costs', $result['text']);
        $this->assertStringContainsString('maneuverability', $result['text']);
        
        // Verify forward trajectory analysis
        $this->assertStringContainsString('Q2 2025', $result['text']);
        $this->assertStringContainsString('execution risk', $result['text']);
    }
    
    /**
     * Test clarity scores for enhanced sections
     */
    public function test_enhanced_sections_clarity_scores() {
        $inputs = [
            'company_source' => ['name' => 'TestCorp']
        ];
        
        $patterns = [
            'pressures' => [['text' => 'competition', 'impact' => 'high']],
            'numeric_proofs' => [['value' => '20%', 'timeframe' => '2025']]
        ];
        
        $citation_manager = $this->createMock(\stdClass::class);
        $citation_manager->method('process_section_citations')
            ->willReturnCallback(function($text, $section) {
                return ['text' => $text, 'citations' => []];
            });
        
        // Test all three enhanced methods
        $methods = ['draft_executive_insight', 'draft_customer_fundamentals', 'draft_financial_trajectory'];
        
        foreach ($methods as $method) {
            $result = $this->invoke_private_method(
                $this->engine,
                $method,
                [$inputs, $patterns, $citation_manager]
            );
            
            $clarity_score = $this->scorer->calculate_clarity_score($result['text']);
            
            $this->assertGreaterThan(0.6, $clarity_score, 
                "Method {$method} should produce clear, readable content");
        }
    }
    
    /**
     * Test citation integration in enhanced sections
     */
    public function test_citation_markers_in_enhanced_sections() {
        $inputs = [
            'company_source' => ['name' => 'DataCorp']
        ];
        
        $patterns = [
            'pressures' => [['text' => 'cost pressure', 'impact' => '15%']],
            'segments' => [['name' => 'Enterprise', 'revenue_share' => '60%', 'growth_rate' => '15%']]
        ];
        
        // Mock citation manager that adds markers
        $citation_manager = $this->createMock(\stdClass::class);
        $citation_manager->method('process_section_citations')
            ->willReturnCallback(function($text, $section) {
                // Simulate citation processing by checking for marker positions
                $citations_added = substr_count($text, '[');
                return [
                    'text' => $text,
                    'citations' => array_fill(0, $citations_added, ['id' => 1, 'source' => 'test'])
                ];
            });
        
        // Test executive insight citations
        $exec_result = $this->invoke_private_method(
            $this->engine,
            'draft_executive_insight',
            [$inputs, $patterns, $citation_manager]
        );
        
        $this->assertStringContainsString('[1]', $exec_result['text'], 
            "Executive insight should contain citation markers");
        $this->assertStringContainsString('[2]', $exec_result['text'], 
            "Executive insight should have multiple citations");
        
        // Test customer fundamentals citations
        $customer_result = $this->invoke_private_method(
            $this->engine,
            'draft_customer_fundamentals',
            [$inputs, $patterns, $citation_manager]
        );
        
        $this->assertStringContainsString('[1]', $customer_result['text'],
            "Customer fundamentals should contain citation markers");
        $this->assertStringContainsString('[3]', $customer_result['text'],
            "Customer fundamentals should have multiple citations");
        
        // Test financial trajectory citations
        $financial_result = $this->invoke_private_method(
            $this->engine,
            'draft_financial_trajectory',
            [$inputs, $patterns, $citation_manager]
        );
        
        $this->assertStringContainsString('[1]', $financial_result['text'],
            "Financial trajectory should contain citation markers");
        $this->assertStringContainsString('[4]', $financial_result['text'],
            "Financial trajectory should have multiple citations");
    }
    
    /**
     * Test Gold Standard benchmark for enhanced sections
     */
    public function test_gold_standard_benchmark_enhanced_sections() {
        // Rich inputs simulating real data
        $inputs = [
            'company_source' => ['name' => 'Fortune500Corp'],
            'company_target' => ['name' => 'InnovativePartner'],
            'industry' => ['sector' => 'technology', 'subsector' => 'cloud services']
        ];
        
        $patterns = [
            'pressures' => [
                ['text' => 'digital transformation', 'impact' => '25% cost increase'],
                ['text' => 'talent retention', 'impact' => '15% wage inflation']
            ],
            'numeric_proofs' => [
                ['value' => '30%', 'timeframe' => 'FY2025'],
                ['value' => '$1.5B', 'context' => 'revenue target']
            ],
            'themes' => ['AI adoption', 'operational efficiency', 'market expansion'],
            'segments' => [
                ['name' => 'Enterprise', 'revenue_share' => '70%', 'growth_rate' => '25%'],
                ['name' => 'Government', 'revenue_share' => '20%', 'growth_rate' => '15%'],
                ['name' => 'SMB', 'revenue_share' => '10%', 'growth_rate' => '5%']
            ],
            'market_signals' => [
                ['signal' => 'M&A acceleration'],
                ['signal' => 'regulatory tightening'],
                ['signal' => 'competitor consolidation']
            ]
        ];
        
        $citation_manager = $this->createMock(\stdClass::class);
        $citation_manager->method('process_section_citations')
            ->willReturnCallback(function($text, $section) {
                return ['text' => $text, 'citations' => [
                    ['id' => 1, 'source' => 'Annual Report 2024'],
                    ['id' => 2, 'source' => 'Investor Call Q4'],
                    ['id' => 3, 'source' => 'Industry Analysis']
                ]];
            });
        
        // Generate all enhanced sections
        $sections = [];
        $methods = [
            'executive_insight' => 'draft_executive_insight',
            'customer_fundamentals' => 'draft_customer_fundamentals', 
            'financial_trajectory' => 'draft_financial_trajectory'
        ];
        
        foreach ($methods as $key => $method) {
            $result = $this->invoke_private_method(
                $this->engine,
                $method,
                [$inputs, $patterns, $citation_manager]
            );
            $sections[$key] = $result;
        }
        
        // Score against Gold Standard criteria
        $overall_scores = [];
        foreach ($sections as $name => $section) {
            $section_data = [
                'text' => $section['text'],
                'context' => [
                    'source_company' => 'Fortune500Corp',
                    'target_company' => 'InnovativePartner',
                    'themes' => ['AI adoption', 'operational efficiency', 'market expansion']
                ],
                'patterns' => array_column($patterns['themes'], 'theme'),
                'inline_citations' => $section['citations'] ?? []
            ];
            
            $scores = $this->scorer->score_section($section_data);
            $overall_scores[$name] = $scores['overall_weighted'];
            
            // Each section should meet Gold Standard threshold
            $this->assertGreaterThan(0.7, $scores['overall_weighted'],
                "Section {$name} should meet Gold Standard quality threshold");
            
            // Verify component scores
            $this->assertGreaterThan(0.65, $scores['clarity'],
                "Section {$name} should have high clarity");
            $this->assertGreaterThan(0.6, $scores['relevance'],
                "Section {$name} should be highly relevant");
            $this->assertGreaterThan(0.5, $scores['insight_depth'],
                "Section {$name} should demonstrate analytical depth");
        }
        
        // Overall average should exceed Gold Standard
        $average_score = array_sum($overall_scores) / count($overall_scores);
        $this->assertGreaterThan(0.75, $average_score,
            "Enhanced sections should collectively exceed Gold Standard benchmark");
    }
}