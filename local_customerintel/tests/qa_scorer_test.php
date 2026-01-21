<?php
/**
 * PHPUnit tests for QA Scorer
 *
 * @package    local_customerintel
 * @copyright  2024 CustomerIntel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/qa_scorer.php');

use local_customerintel\services\qa_scorer;

/**
 * Test cases for QA Scorer
 */
class qa_scorer_test extends \advanced_testcase {

    /** @var qa_scorer */
    private $scorer;

    /**
     * Setup before each test
     */
    protected function setUp(): void {
        parent::setUp();
        $this->scorer = new qa_scorer();
    }

    /**
     * Test clarity score with simple text
     */
    public function test_clarity_score_with_simple_text() {
        $text = "The company achieved strong revenue growth. Margins improved significantly. Customer satisfaction increased.";
        
        $score = $this->scorer->calculate_clarity_score($text);
        
        $this->assertGreaterThan(0.7, $score, "Simple, clear text should score high for clarity");
        $this->assertLessThanOrEqual(1.0, $score, "Score should not exceed 1.0");
    }

    /**
     * Test clarity score with complex jargon
     */
    public function test_clarity_score_with_complex_jargon() {
        $text = "We need to leverage synergies to optimize our paradigm shift and maximize mindshare in the ecosystem touchpoint deliverables bandwidth.";
        
        $score = $this->scorer->calculate_clarity_score($text);
        
        $this->assertLessThan(0.5, $score, "Jargon-heavy text should score low for clarity");
        $this->assertGreaterThanOrEqual(0.0, $score, "Score should not be negative");
    }

    /**
     * Test relevance score with aligned content
     */
    public function test_relevance_score_with_aligned_content() {
        $text = "Acme Corp demonstrated strong performance in Q4 2024, with digital transformation driving growth.";
        $context = [
            'source_company' => 'Acme Corp',
            'target_company' => '',
            'themes' => ['digital transformation', 'growth', 'performance']
        ];
        
        $score = $this->scorer->calculate_relevance_score($text, $context);
        
        $this->assertGreaterThan(0.6, $score, "Content aligned with context should score high");
    }

    /**
     * Test relevance score with misaligned content
     */
    public function test_relevance_score_with_misaligned_content() {
        $text = "Generic business operations continue as usual with no specific focus areas identified.";
        $context = [
            'source_company' => 'Acme Corp',
            'target_company' => 'Partner Inc',
            'themes' => ['digital transformation', 'innovation', 'cloud migration']
        ];
        
        $score = $this->scorer->calculate_relevance_score($text, $context);
        
        $this->assertLessThan(0.6, $score, "Content misaligned with context should score low");
    }

    /**
     * Test insight depth with surface observations
     */
    public function test_insight_depth_with_surface_observations() {
        $text = "Sales went up. Costs went down. Things are good.";
        $patterns = ['strategic analysis', 'root cause', 'market dynamics'];
        
        $score = $this->scorer->calculate_insight_depth($text, $patterns);
        
        $this->assertLessThan(0.5, $score, "Surface-level observations should score low for insight depth");
    }

    /**
     * Test insight depth with analytical insights
     */
    public function test_insight_depth_with_analytical_insights() {
        $text = "The 15% revenue growth indicates strong market positioning, which suggests that our strategic initiatives are gaining traction. This demonstrates a clear correlation between investment in digital capabilities and market share expansion, revealing an underlying trend toward technology-driven competitive advantage.";
        $patterns = ['market positioning', 'strategic initiatives', 'competitive advantage'];
        
        $score = $this->scorer->calculate_insight_depth($text, $patterns);
        
        $this->assertGreaterThan(0.6, $score, "Analytical insights should score high for depth");
    }

    /**
     * Test evidence strength with multiple citations
     */
    public function test_evidence_strength_with_multiple_citations() {
        $citations = [
            ['id' => 1, 'domain' => 'company.com', 'year' => 2024],
            ['id' => 2, 'domain' => 'industry.org', 'year' => 2024],
            ['id' => 3, 'domain' => 'research.edu', 'year' => 2023],
            ['id' => 4, 'domain' => 'news.com', 'year' => 2024]
        ];
        
        $score = $this->scorer->calculate_evidence_strength($citations);
        
        $this->assertGreaterThan(0.7, $score, "Multiple recent citations from diverse sources should score high");
    }

    /**
     * Test evidence strength with no citations
     */
    public function test_evidence_strength_with_no_citations() {
        $citations = [];
        
        $score = $this->scorer->calculate_evidence_strength($citations);
        
        $this->assertLessThan(0.3, $score, "No citations should result in low evidence score");
        $this->assertGreaterThan(0.0, $score, "Should have minimal base score");
    }

    /**
     * Test structural consistency across sections
     */
    public function test_structural_consistency_across_sections() {
        $sections = [
            'section1' => ['text' => str_repeat('Word ', 100)],  // 100 words
            'section2' => ['text' => str_repeat('Word ', 95)],   // 95 words
            'section3' => ['text' => str_repeat('Word ', 105)],  // 105 words
        ];
        
        $score = $this->scorer->calculate_structural_consistency($sections);
        
        $this->assertGreaterThan(0.6, $score, "Consistent section lengths should score well");
    }

    /**
     * Test weighted aggregation accuracy
     */
    public function test_weighted_aggregation_accuracy() {
        $scores = [
            'clarity' => 0.8,
            'relevance' => 0.7,
            'insight_depth' => 0.6,
            'evidence_strength' => 0.5,
            'structural_consistency' => 0.9
        ];
        
        $result = $this->scorer->aggregate_scores($scores);
        
        // Manual calculation: 0.8*0.30 + 0.7*0.25 + 0.6*0.20 + 0.5*0.15 + 0.9*0.10 = 0.7
        $expected = 0.7;
        $this->assertEqualsWithDelta($expected, $result['overall_weighted'], 0.01, "Weighted aggregation should match expected calculation");
        
        // Verify all components are present
        $this->assertArrayHasKey('clarity', $result);
        $this->assertArrayHasKey('relevance', $result);
        $this->assertArrayHasKey('insight_depth', $result);
        $this->assertArrayHasKey('evidence_strength', $result);
        $this->assertArrayHasKey('structural_consistency', $result);
    }

    /**
     * Test empty content handling
     */
    public function test_empty_content_handling() {
        $empty_text = "";
        
        $clarity = $this->scorer->calculate_clarity_score($empty_text);
        $relevance = $this->scorer->calculate_relevance_score($empty_text, []);
        $insight = $this->scorer->calculate_insight_depth($empty_text, []);
        
        $this->assertEquals(0.0, $clarity, "Empty text should return minimum score");
        $this->assertEquals(0.0, $relevance, "Empty text should return minimum score");
        $this->assertEquals(0.0, $insight, "Empty text should return minimum score");
    }

    /**
     * Test malformed section data
     */
    public function test_malformed_section_data() {
        $malformed_section = [
            'no_text_key' => 'missing text field',
            'random_data' => null
        ];
        
        $score = $this->scorer->score_section($malformed_section);
        
        $this->assertIsArray($score, "Should return array even with malformed data");
        $this->assertArrayHasKey('overall_weighted', $score, "Should have overall score");
        $this->assertGreaterThanOrEqual(0.0, $score['overall_weighted'], "Score should be valid");
    }

    /**
     * Test benchmark against Gold Standard content
     */
    public function test_benchmark_against_gold_standard() {
        // Simulate Gold Standard quality content
        $gold_standard_text = "Acme Corporation's Q4 2024 performance demonstrates exceptional growth trajectory, " .
                             "with revenue increasing 25% year-over-year to $1.2B. This growth indicates strong " .
                             "market positioning, particularly in the enterprise segment where deal sizes expanded 40%. " .
                             "The underlying driver appears to be successful digital transformation initiatives, which " .
                             "correlates with the 15% improvement in operational efficiency metrics. Consequently, " .
                             "EBITDA margins expanded 300 basis points, suggesting sustainable profitability improvements. " .
                             "These results reveal a fundamental shift in competitive dynamics, as evidenced by " .
                             "market share gains of 5 percentage points.";
        
        $context = [
            'source_company' => 'Acme Corporation',
            'themes' => ['growth', 'digital transformation', 'efficiency', 'profitability']
        ];
        
        $patterns = ['market positioning', 'digital transformation', 'operational efficiency'];
        
        $citations = [
            ['id' => 1, 'domain' => 'acme.com', 'year' => 2024],
            ['id' => 2, 'domain' => 'analyst.com', 'year' => 2024],
            ['id' => 3, 'domain' => 'industry.org', 'year' => 2024]
        ];
        
        $section_data = [
            'text' => $gold_standard_text,
            'context' => $context,
            'patterns' => $patterns,
            'inline_citations' => $citations
        ];
        
        $scores = $this->scorer->score_section($section_data);
        
        $this->assertGreaterThan(0.75, $scores['overall_weighted'], "Gold Standard content should score above 0.75 threshold");
        $this->assertGreaterThan(0.7, $scores['clarity'], "Gold Standard should have high clarity");
        $this->assertGreaterThan(0.7, $scores['relevance'], "Gold Standard should have high relevance");
        $this->assertGreaterThan(0.65, $scores['insight_depth'], "Gold Standard should have good insight depth");
    }
}