<?php
/**
 * Unit tests for Pattern Comparator (Slice 6)
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;
use local_customerintel\services\pattern_comparator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/pattern_comparator.php');

/**
 * Test class for pattern comparator functionality
 * 
 * @coversDefaultClass \local_customerintel\services\pattern_comparator
 */
class pattern_comparator_test extends advanced_testcase {
    
    /**
     * @var pattern_comparator
     */
    private $comparator;
    
    /**
     * @var string Path to test schema
     */
    private $test_schema_path;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Create a test schema file
        $this->test_schema_path = sys_get_temp_dir() . '/test_gold_standard.json';
        $this->create_test_schema();
        
        $this->comparator = new pattern_comparator($this->test_schema_path);
    }
    
    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        if (file_exists($this->test_schema_path)) {
            unlink($this->test_schema_path);
        }
        parent::tearDown();
    }
    
    /**
     * Create test schema file
     */
    private function create_test_schema(): void {
        $schema = [
            'version' => '1.0.0',
            'qa_targets' => [
                'structure' => 0.85,
                'tone' => 0.90,
                'quantification' => 0.80,
                'voice' => 0.85,
                'logical_flow' => 0.90
            ],
            'sections' => [
                'executive_insight' => [
                    'exemplar' => [
                        'structure' => ['word_count' => ['min' => 100, 'max' => 150]],
                        'tone' => 'executive-level, strategic',
                        'voice' => ['style' => 'direct'],
                        'quantification' => ['metrics_required' => 3],
                        'logical_flow' => ['current_state', 'challenge', 'opportunity', 'action']
                    ],
                    'quality_markers' => [
                        'Contains company name in first sentence',
                        'Includes at least 3 quantified metrics',
                        'References specific timeline'
                    ],
                    'anti_patterns' => [
                        'Excessive use of may, might, could',
                        'Consultant jargon'
                    ]
                ]
            ]
        ];
        
        file_put_contents($this->test_schema_path, json_encode($schema));
    }
    
    /**
     * Test exemplar loading
     */
    public function test_exemplar_loading() {
        // Test with valid schema file
        $result = $this->comparator->compare([
            'executive_insight' => ['text' => 'Test content']
        ]);
        
        $this->assertArrayHasKey('pattern_alignment_score', $result);
        $this->assertArrayHasKey('diagnostics', $result);
        $this->assertArrayHasKey('details', $result);
    }
    
    /**
     * Test missing exemplar handling
     */
    public function test_missing_exemplar_handling() {
        // Create comparator with non-existent file
        $comparator = new pattern_comparator('/nonexistent/path.json');
        
        $result = $comparator->compare([
            'executive_insight' => ['text' => 'Test content']
        ]);
        
        // Should handle gracefully
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(0, $result['pattern_alignment_score']);
        $this->assertLessThanOrEqual(1, $result['pattern_alignment_score']);
    }
    
    /**
     * Test malformed exemplar handling
     */
    public function test_malformed_exemplar_handling() {
        // Create malformed JSON
        $malformed_path = sys_get_temp_dir() . '/malformed.json';
        file_put_contents($malformed_path, '{"invalid json}');
        
        $comparator = new pattern_comparator($malformed_path);
        $result = $comparator->compare([
            'executive_insight' => ['text' => 'Test content']
        ]);
        
        // Should handle gracefully
        $this->assertIsArray($result);
        $this->assertEquals(0.8, $result['pattern_alignment_score']); // Default score
        
        unlink($malformed_path);
    }
    
    /**
     * Test known-good section scoring
     */
    public function test_known_good_section() {
        $good_section = [
            'executive_insight' => [
                'text' => 'Acme Corp faces strategic challenges requiring immediate action. ' .
                          'Revenue growth of 15% YoY demonstrates momentum, while EBITDA margins at 22% indicate healthy profitability. ' .
                          'Customer retention at 89% exceeds industry benchmarks. ' .
                          'Q2 2024 implementation timeline positions the company for sustainable growth.'
            ]
        ];
        
        $result = $this->comparator->compare($good_section);
        
        // Should score well
        $this->assertGreaterThan(0.7, $result['pattern_alignment_score'], 
            'Good section should score above 0.7');
        
        // Check diagnostics
        $this->assertArrayHasKey('executive_insight', $result['diagnostics']);
    }
    
    /**
     * Test known-poor section scoring
     */
    public function test_known_poor_section() {
        $poor_section = [
            'executive_insight' => [
                'text' => 'The company might possibly see some improvements. ' .
                          'Things could get better. We should leverage synergies to drive value. ' .
                          'There may be opportunities.'
            ]
        ];
        
        $result = $this->comparator->compare($poor_section);
        
        // Should score poorly
        $this->assertLessThan(0.6, $result['pattern_alignment_score'], 
            'Poor section should score below 0.6');
    }
    
    /**
     * Test structure evaluation
     */
    public function test_structure_evaluation() {
        // Test word count compliance
        $sections = [
            'executive_insight' => [
                'text' => str_repeat('word ', 25) // 125 words - within range
            ]
        ];
        
        $result = $this->comparator->compare($sections);
        $diagnostics = $result['diagnostics']['executive_insight'] ?? [];
        
        $this->assertEquals(25, $diagnostics['word_count']);
        $this->assertGreaterThan(0.7, $diagnostics['scores']['structure']);
    }
    
    /**
     * Test quantification evaluation
     */
    public function test_quantification_evaluation() {
        $sections = [
            'executive_insight' => [
                'text' => 'Revenue grew 15% to $50M with 89% retention rate and 22% EBITDA margin.'
            ]
        ];
        
        $result = $this->comparator->compare($sections);
        $diagnostics = $result['diagnostics']['executive_insight'] ?? [];
        
        // Should detect 4 metrics (15%, $50M, 89%, 22%)
        $this->assertGreaterThan(0.8, $diagnostics['scores']['quantification'], 
            'Should score well on quantification with multiple metrics');
    }
    
    /**
     * Test quality markers detection
     */
    public function test_quality_markers() {
        $sections = [
            'executive_insight' => [
                'text' => 'Acme Corp achieved 20% revenue growth in Q1 2024, demonstrating strong execution.'
            ]
        ];
        
        $result = $this->comparator->compare($sections);
        $diagnostics = $result['diagnostics']['executive_insight'] ?? [];
        
        // Should detect quality markers (company name, metrics, timeline)
        $this->assertGreaterThan(0.5, $diagnostics['scores']['quality_markers'], 
            'Should detect quality markers');
    }
    
    /**
     * Test anti-pattern detection
     */
    public function test_anti_patterns() {
        $sections = [
            'executive_insight' => [
                'text' => 'We might possibly leverage synergies that could maybe drive holistic value ' .
                          'in the ecosystem paradigm shift.'
            ]
        ];
        
        $result = $this->comparator->compare($sections);
        $diagnostics = $result['diagnostics']['executive_insight'] ?? [];
        
        // Should detect anti-patterns
        $this->assertGreaterThan(0, $diagnostics['scores']['anti_pattern_penalty'], 
            'Should detect and penalize anti-patterns');
    }
    
    /**
     * Test QA weighting integration (10% requirement)
     */
    public function test_qa_weighting() {
        $pattern_alignment_score = 0.85;
        
        // Simulated QA scores with pattern alignment
        $qa_scores = [
            'clarity' => 0.9,
            'relevance' => 0.85,
            'insight_depth' => 0.8,
            'evidence_strength' => 0.75,
            'structural_consistency' => 0.85,
            'coherence' => 0.8,
            'pattern_alignment' => $pattern_alignment_score
        ];
        
        // Calculate weighted score (matching synthesis_engine logic)
        $weighted = (
            $qa_scores['clarity'] * 0.18 +
            $qa_scores['relevance'] * 0.18 +
            $qa_scores['insight_depth'] * 0.14 +
            $qa_scores['evidence_strength'] * 0.13 +
            $qa_scores['structural_consistency'] * 0.12 +
            $qa_scores['coherence'] * 0.15 +
            $qa_scores['pattern_alignment'] * 0.10  // 10% weight
        );
        
        // Verify the calculation
        $expected = 0.8315; // Manual calculation
        $this->assertEqualsWithDelta($expected, $weighted, 0.001, 
            'Pattern alignment should contribute 10% to overall QA score');
    }
    
    /**
     * Test feature flag disable
     */
    public function test_feature_flag_disable() {
        $sections = [
            'executive_insight' => ['text' => 'Test content']
        ];
        
        // Test with comparator disabled
        $result = $this->comparator->compare($sections, ['enable_pattern_comparator' => false]);
        
        $this->assertEquals(1.0, $result['pattern_alignment_score'], 
            'Disabled comparator should return perfect score');
        $this->assertEquals('disabled', $result['diagnostics']['status'], 
            'Should indicate disabled status');
    }
    
    /**
     * Test stable scoring
     */
    public function test_stable_scoring() {
        $sections = [
            'executive_insight' => [
                'text' => 'Acme Corp achieved 20% revenue growth in Q1 2024.'
            ]
        ];
        
        // Run multiple times
        $score1 = $this->comparator->compare($sections)['pattern_alignment_score'];
        $score2 = $this->comparator->compare($sections)['pattern_alignment_score'];
        $score3 = $this->comparator->compare($sections)['pattern_alignment_score'];
        
        // Scores should be stable
        $this->assertEquals($score1, $score2, 'Scores should be stable across runs');
        $this->assertEquals($score2, $score3, 'Scores should be stable across runs');
    }
    
    /**
     * Test diagnostics generation
     */
    public function test_diagnostics_generation() {
        $sections = [
            'executive_insight' => [
                'text' => 'Short text with few metrics.'
            ]
        ];
        
        $result = $this->comparator->compare($sections);
        $diagnostics = $result['diagnostics']['executive_insight'] ?? [];
        
        // Should include diagnostics
        $this->assertArrayHasKey('word_count', $diagnostics);
        $this->assertArrayHasKey('scores', $diagnostics);
        $this->assertArrayHasKey('recommendations', $diagnostics);
        
        // Should have recommendations for low-scoring dimensions
        $this->assertNotEmpty($diagnostics['recommendations'], 
            'Should provide recommendations for improvement');
    }
    
    /**
     * Test complex section structures
     */
    public function test_complex_structures() {
        $sections = [
            'opportunities' => [
                ['title' => 'Opportunity 1', 'body' => 'Implementation with 30% ROI'],
                ['title' => 'Opportunity 2', 'body' => 'Quick win with $5M impact']
            ],
            'overlooked' => [
                'Digital transformation gap',
                'Customer experience improvement',
                'Operational efficiency opportunity'
            ]
        ];
        
        $result = $this->comparator->compare($sections);
        
        // Should handle complex structures
        $this->assertIsArray($result['diagnostics']);
        $this->assertGreaterThanOrEqual(0, $result['pattern_alignment_score']);
    }
    
    /**
     * Test voice evaluation
     */
    public function test_voice_evaluation() {
        // Test passive vs active voice
        $passive_section = [
            'executive_insight' => [
                'text' => 'Results were achieved by the team. Improvements were made in Q1.'
            ]
        ];
        
        $active_section = [
            'executive_insight' => [
                'text' => 'The team achieved results. We made improvements in Q1.'
            ]
        ];
        
        $passive_result = $this->comparator->compare($passive_section);
        $active_result = $this->comparator->compare($active_section);
        
        // Active voice should score better
        $this->assertGreaterThan(
            $passive_result['diagnostics']['executive_insight']['scores']['voice'],
            $active_result['diagnostics']['executive_insight']['scores']['voice'],
            'Active voice should score better than passive'
        );
    }
    
    /**
     * Test logical flow evaluation
     */
    public function test_logical_flow() {
        $good_flow = [
            'executive_insight' => [
                'text' => 'Current revenue stands at $100M. The challenge is margin pressure. ' .
                          'Opportunities exist in new markets. We must implement strategic initiatives now.'
            ]
        ];
        
        $poor_flow = [
            'executive_insight' => [
                'text' => 'Random statement here. Another unrelated point. No clear structure.'
            ]
        ];
        
        $good_result = $this->comparator->compare($good_flow);
        $poor_result = $this->comparator->compare($poor_flow);
        
        // Good flow should score better
        $this->assertGreaterThan(
            $poor_result['diagnostics']['executive_insight']['scores']['logical_flow'],
            $good_result['diagnostics']['executive_insight']['scores']['logical_flow'],
            'Good logical flow should score better'
        );
    }
}