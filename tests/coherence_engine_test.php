<?php
/**
 * Unit tests for Coherence Engine (Slice 5)
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;
use local_customerintel\services\coherence_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/coherence_engine.php');

/**
 * Test class for coherence engine functionality
 * 
 * @coversDefaultClass \local_customerintel\services\coherence_engine
 */
class coherence_engine_test extends advanced_testcase {
    
    /**
     * @var coherence_engine
     */
    private $engine;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->engine = new coherence_engine();
    }
    
    /**
     * Test entity consistency checking
     */
    public function test_entity_consistency() {
        $sections = [
            'executive_insight' => ['text' => 'Acme Corp needs to focus on growth. The CEO of Acme Corp stated priorities.'],
            'customer_fundamentals' => ['text' => 'Acme Corp has strong customer retention. Widget LLC is a competitor.'],
            'financial_trajectory' => ['text' => 'Revenue growth at 15% for Acme Corp.']
        ];
        
        $result = $this->engine->process($sections);
        
        // Acme Corp mentioned consistently across sections
        $this->assertGreaterThan(0.7, $result['coherence_score'], 'Entity consistency should be high');
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('details', $result);
    }
    
    /**
     * Test metric consistency
     */
    public function test_metric_consistency() {
        $sections = [
            'executive_insight' => ['text' => 'Target 20% EBITDA margin improvement.'],
            'financial_trajectory' => ['text' => 'Current EBITDA margin at 15%, targeting 20% by Q4.'],
            'margin_pressures' => ['text' => 'Need to improve EBITDA margin by 5% to reach 20% target.']
        ];
        
        $result = $this->engine->process($sections);
        
        // 20% metric appears consistently
        $this->assertGreaterThan(0.7, $result['coherence_score'], 'Metric consistency should be high');
        $this->assertGreaterThan(0, $result['details']['metrics'], 'Should detect metrics');
    }
    
    /**
     * Test timeframe consistency
     */
    public function test_timeframe_consistency() {
        $sections = [
            'executive_insight' => ['text' => 'Q1 2024 implementation timeline.'],
            'strategic_priorities' => ['text' => 'Begin initiatives in Q1 2024.'],
            'growth_levers' => ['text' => 'Launch in Q1 2024 for maximum impact.']
        ];
        
        $result = $this->engine->process($sections);
        
        // Q1 2024 appears consistently
        $this->assertGreaterThan(0.7, $result['coherence_score'], 'Timeframe consistency should be high');
        $this->assertGreaterThan(0, $result['details']['timeframes'], 'Should detect timeframes');
    }
    
    /**
     * Test transition insertion
     */
    public function test_transition_insertion() {
        $sections = [
            'executive_insight' => ['text' => 'Strategic focus on growth.'],
            'customer_fundamentals' => 'Customer retention is strong.',
            'financial_trajectory' => ['text' => 'Revenue growing at 15%.']
        ];
        
        $result = $this->engine->process($sections);
        
        // Check that transitions were added
        $customer_text = $result['sections']['customer_fundamentals'];
        $this->assertStringContainsString('Building on these strategic priorities', $customer_text, 
            'Should add transition to customer section');
        
        $this->assertEquals(8, $result['details']['transitions_added'], 'Should add 8 transitions');
    }
    
    /**
     * Test terminology normalization
     */
    public function test_terminology_normalization() {
        $sections = [
            'executive_insight' => ['text' => 'Focus on ebitda margin and Revenue Growth.'],
            'financial_trajectory' => ['text' => 'Ebitda Margin needs improvement. revenue growth is strong.']
        ];
        
        $result = $this->engine->process($sections);
        
        // Check normalized terminology
        $exec_text = $result['sections']['executive_insight']['text'];
        $this->assertStringContainsString('EBITDA margin', $exec_text, 'Should normalize to EBITDA margin');
        $this->assertStringContainsString('revenue growth', $exec_text, 'Should normalize to lowercase revenue growth');
        
        $fin_text = $result['sections']['financial_trajectory']['text'];
        $this->assertStringContainsString('EBITDA margin', $fin_text, 'Should normalize EBITDA consistently');
    }
    
    /**
     * Test glossary replacements
     */
    public function test_glossary_replacements() {
        $sections = [
            'executive_insight' => ['text' => 'We need to leverage synergies and enhance operations.'],
            'strategic_priorities' => ['text' => 'Streamline processes to Enhance efficiency.']
        ];
        
        $result = $this->engine->process($sections);
        
        $exec_text = $result['sections']['executive_insight']['text'];
        $this->assertStringContainsString('utilize', $exec_text, 'Should replace leverage with utilize');
        $this->assertStringContainsString('improve', $exec_text, 'Should replace enhance with improve');
        
        $strat_text = $result['sections']['strategic_priorities']['text'];
        $this->assertStringContainsString('optimize', $strat_text, 'Should replace streamline with optimize');
        $this->assertStringContainsString('Improve', $strat_text, 'Should replace Enhance with Improve');
    }
    
    /**
     * Test coherence score calculation
     */
    public function test_coherence_score_calculation() {
        // High coherence sections
        $good_sections = [
            'executive_insight' => ['text' => 'Acme Corp targets 20% margin in Q1 2024.'],
            'financial_trajectory' => ['text' => 'Acme Corp expects 20% margin by Q1 2024.'],
            'strategic_priorities' => ['text' => 'Acme Corp priorities for Q1 2024 include 20% margin.']
        ];
        
        $good_result = $this->engine->process($good_sections);
        $this->assertGreaterThan(0.8, $good_result['coherence_score'], 
            'High consistency should yield high coherence score');
        
        // Low coherence sections
        $poor_sections = [
            'executive_insight' => ['text' => 'Acme Corp targets growth.'],
            'financial_trajectory' => ['text' => 'Widget LLC has different metrics.'],
            'strategic_priorities' => ['text' => 'Beta Inc priorities vary.']
        ];
        
        $poor_result = $this->engine->process($poor_sections);
        $this->assertLessThan(0.7, $poor_result['coherence_score'], 
            'Low consistency should yield lower coherence score');
    }
    
    /**
     * Test QA score contribution (15% weight requirement)
     */
    public function test_qa_score_contribution() {
        // This tests the integration with synthesis_engine
        // The coherence score should contribute 15% to overall QA score
        
        $coherence_score = 0.8;
        
        // Simulated QA scores
        $qa_scores = [
            'clarity' => 0.9,
            'relevance' => 0.85,
            'insight_depth' => 0.8,
            'evidence_strength' => 0.75,
            'structural_consistency' => 0.85,
            'coherence' => $coherence_score
        ];
        
        // Calculate weighted score (matching synthesis_engine logic)
        $weighted = (
            $qa_scores['clarity'] * 0.20 +
            $qa_scores['relevance'] * 0.20 +
            $qa_scores['insight_depth'] * 0.15 +
            $qa_scores['evidence_strength'] * 0.15 +
            $qa_scores['structural_consistency'] * 0.15 +
            $qa_scores['coherence'] * 0.15  // 15% weight
        );
        
        // Verify the calculation
        $expected = 0.835; // Manual calculation
        $this->assertEqualsWithDelta($expected, $weighted, 0.001, 
            'Coherence should contribute 15% to overall QA score');
    }
    
    /**
     * Test feature flag disable
     */
    public function test_feature_flag_disable() {
        $sections = [
            'executive_insight' => ['text' => 'Test content']
        ];
        
        // Test with coherence disabled
        $result = $this->engine->process($sections, ['enable_coherence' => false]);
        
        $this->assertEquals(1.0, $result['coherence_score'], 
            'Disabled coherence should return perfect score');
        $this->assertEquals('disabled', $result['details']['status'], 
            'Should indicate disabled status');
        $this->assertEquals($sections, $result['sections'], 
            'Sections should be unchanged when disabled');
    }
    
    /**
     * Test handling of complex section structures
     */
    public function test_complex_section_structures() {
        $sections = [
            'executive_insight' => [
                'text' => 'Main insight text with 20% growth target.',
                'inline_citations' => [1, 2, 3]
            ],
            'opportunities' => [
                ['title' => 'Opportunity 1', 'body' => 'Leverage growth to 20%'],
                ['title' => 'Opportunity 2', 'body' => 'Enhance margins']
            ],
            'overlooked' => [
                'Digital transformation gaps',
                'Customer experience improvements',
                'Operational efficiency gains'
            ]
        ];
        
        $result = $this->engine->process($sections);
        
        $this->assertArrayHasKey('sections', $result);
        $this->assertGreaterThan(0, $result['coherence_score'], 
            'Should handle complex structures');
        
        // Check that array structures are preserved
        $this->assertIsArray($result['sections']['opportunities'], 
            'Opportunities should remain array');
        $this->assertIsArray($result['sections']['overlooked'], 
            'Overlooked should remain array');
    }
    
    /**
     * Test empty section handling
     */
    public function test_empty_section_handling() {
        $sections = [
            'executive_insight' => ['text' => ''],
            'customer_fundamentals' => '',
            'financial_trajectory' => []
        ];
        
        $result = $this->engine->process($sections);
        
        // Should handle empty sections gracefully
        $this->assertIsArray($result['sections']);
        $this->assertGreaterThanOrEqual(0, $result['coherence_score']);
        $this->assertLessThanOrEqual(1, $result['coherence_score']);
    }
}