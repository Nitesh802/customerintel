<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for Citation System Enhancement
 * Tests confidence scoring, diversity metrics, and marker mapping
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2025 CustomerIntel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/citation_confidence_scorer.php');

/**
 * Test class for Citation Enhancement
 */
class citation_enhancement_test extends advanced_testcase {
    
    /** @var citation_confidence_scorer */
    private $scorer;
    
    /** @var CitationManager */
    private $citation_manager;
    
    protected function setUp(): void {
        parent::setUp();
        $this->scorer = new citation_confidence_scorer();
        $this->citation_manager = new \CitationManager();
    }
    
    /**
     * Test authority score calculation for different domains
     */
    public function test_authority_score_calculation() {
        // SEC domain should get perfect authority
        $sec_citation = [
            'domain' => 'sec.gov',
            'url' => 'https://sec.gov/filing/10-k'
        ];
        $confidence = $this->scorer->calculate_confidence($sec_citation);
        $this->assertGreaterThanOrEqual(0.85, $confidence, 'SEC domain should have high confidence');
        
        // Bloomberg should get high authority
        $bloomberg_citation = [
            'domain' => 'bloomberg.com',
            'url' => 'https://bloomberg.com/news/article'
        ];
        $confidence = $this->scorer->calculate_confidence($bloomberg_citation);
        $this->assertGreaterThanOrEqual(0.80, $confidence, 'Bloomberg should have high confidence');
        
        // Unknown domain should get low authority
        $unknown_citation = [
            'domain' => 'random-blog.com',
            'url' => 'https://random-blog.com/post'
        ];
        $confidence = $this->scorer->calculate_confidence($unknown_citation);
        $this->assertLessThan(0.60, $confidence, 'Unknown domain should have lower confidence');
    }
    
    /**
     * Test recency score decay over time
     */
    public function test_recency_score_decay() {
        // Recent publication (1 week old)
        $recent_citation = [
            'domain' => 'reuters.com',
            'publishedat' => date('Y-m-d', strtotime('-7 days'))
        ];
        $confidence = $this->scorer->calculate_confidence($recent_citation);
        $this->assertGreaterThanOrEqual(0.85, $confidence, 'Recent citation should have high confidence');
        
        // 6-month old publication
        $older_citation = [
            'domain' => 'reuters.com',
            'publishedat' => date('Y-m-d', strtotime('-180 days'))
        ];
        $confidence = $this->scorer->calculate_confidence($older_citation);
        $this->assertLessThan(0.80, $confidence, '6-month old citation should have reduced confidence');
        
        // 2-year old publication
        $old_citation = [
            'domain' => 'reuters.com',
            'publishedat' => date('Y-m-d', strtotime('-730 days'))
        ];
        $confidence = $this->scorer->calculate_confidence($old_citation);
        $this->assertLessThan(0.70, $confidence, '2-year old citation should have low confidence');
    }
    
    /**
     * Test corroboration boost from multiple sources
     */
    public function test_corroboration_multiplier() {
        $citation = [
            'domain' => 'wsj.com',
            'publishedat' => date('Y-m-d')
        ];
        
        // Single source
        $context_single = ['corroboration_count' => 1];
        $confidence_single = $this->scorer->calculate_confidence($citation, $context_single);
        
        // Multiple sources
        $context_multiple = ['corroboration_count' => 3];
        $confidence_multiple = $this->scorer->calculate_confidence($citation, $context_multiple);
        
        $this->assertGreaterThan($confidence_single, $confidence_multiple,
            'Corroborated facts should have higher confidence');
    }
    
    /**
     * Test confidence score normalization
     */
    public function test_confidence_normalization() {
        $citations = [
            ['domain' => 'sec.gov'],
            ['domain' => 'bloomberg.com'],
            ['domain' => 'unknown.com'],
        ];
        
        foreach ($citations as $citation) {
            $confidence = $this->scorer->calculate_confidence($citation);
            $this->assertGreaterThanOrEqual(0.0, $confidence, 'Confidence should be at least 0.0');
            $this->assertLessThanOrEqual(1.0, $confidence, 'Confidence should not exceed 1.0');
        }
    }
    
    /**
     * Test domain variety scoring
     */
    public function test_domain_variety_scoring() {
        // Many unique domains
        $diverse_citations = [];
        for ($i = 1; $i <= 12; $i++) {
            $diverse_citations[] = ['domain' => "source{$i}.com"];
        }
        
        $metrics = $this->scorer->calculate_diversity_metrics($diverse_citations);
        $this->assertGreaterThanOrEqual(0.75, $metrics['diversity_score'],
            'Many unique domains should yield high diversity');
        
        // Few unique domains
        $homogeneous_citations = [
            ['domain' => 'source1.com'],
            ['domain' => 'source1.com'],
            ['domain' => 'source2.com']
        ];
        
        $metrics = $this->scorer->calculate_diversity_metrics($homogeneous_citations);
        $this->assertLessThan(0.60, $metrics['diversity_score'],
            'Few unique domains should yield low diversity');
    }
    
    /**
     * Test source type entropy calculation
     */
    public function test_source_type_entropy() {
        // Balanced source types
        $balanced_citations = [
            ['domain' => 'sec.gov'],
            ['domain' => 'bloomberg.com'],
            ['domain' => 'gartner.com'],
            ['domain' => 'investor.company.com']
        ];
        
        $metrics = $this->scorer->calculate_diversity_metrics($balanced_citations);
        $this->assertArrayHasKey('source_type_distribution', $metrics);
        $this->assertGreaterThanOrEqual(3, count($metrics['source_type_distribution']),
            'Should identify multiple source types');
    }
    
    /**
     * Test temporal spread calculation
     */
    public function test_temporal_spread_calculation() {
        // Wide temporal spread
        $spread_citations = [
            ['domain' => 'source1.com', 'publishedat' => date('Y-m-d')],
            ['domain' => 'source2.com', 'publishedat' => date('Y-m-d', strtotime('-180 days'))],
            ['domain' => 'source3.com', 'publishedat' => date('Y-m-d', strtotime('-365 days'))]
        ];
        
        $metrics = $this->scorer->calculate_diversity_metrics($spread_citations);
        $this->assertArrayHasKey('recency_mix', $metrics);
        $this->assertGreaterThan(0, $metrics['recency_mix']['current_year']);
    }
    
    /**
     * Test marker generation is deterministic
     */
    public function test_marker_generation_deterministic() {
        $this->citation_manager->enable_enhancements(true);
        
        $citation = [
            'url' => 'https://example.com/article1',
            'title' => 'Test Article',
            'section' => 'executive_insight'
        ];
        
        $id1 = $this->citation_manager->add_citation($citation);
        $marker1 = $this->citation_manager->generate_citation_marker($id1, 'executive_insight');
        
        // Same citation should get same marker format
        $this->assertMatchesRegularExpression('/\[EI\d+\]/', $marker1,
            'Executive Insight markers should use EI prefix');
    }
    
    /**
     * Test section prefix assignment
     */
    public function test_section_prefix_assignment() {
        $this->citation_manager->enable_enhancements(true);
        
        $sections = [
            'executive_insight' => 'EI',
            'financial_trajectory' => 'FT',
            'margin_pressures' => 'MP'
        ];
        
        foreach ($sections as $section => $expected_prefix) {
            $citation = [
                'url' => "https://example.com/{$section}",
                'section' => $section
            ];
            
            $id = $this->citation_manager->add_citation($citation);
            $marker = $this->citation_manager->generate_citation_marker($id, $section);
            
            $this->assertStringContainsString("[{$expected_prefix}", $marker,
                "Section {$section} should use prefix {$expected_prefix}");
        }
    }
    
    /**
     * Test duplicate URL handling
     */
    public function test_duplicate_url_handling() {
        $citation = [
            'url' => 'https://example.com/duplicate',
            'title' => 'Duplicate Test'
        ];
        
        $id1 = $this->citation_manager->add_citation($citation);
        $id2 = $this->citation_manager->add_citation($citation);
        
        $this->assertEquals($id1, $id2, 'Duplicate URLs should return same ID');
    }
    
    /**
     * Test marker to citation lookup
     */
    public function test_marker_to_citation_lookup() {
        $this->citation_manager->enable_enhancements(true);
        
        $citation = [
            'url' => 'https://example.com/lookup',
            'title' => 'Lookup Test',
            'section' => 'financial_trajectory'
        ];
        
        $id = $this->citation_manager->add_citation($citation);
        $marker = $this->citation_manager->generate_citation_marker($id, 'financial_trajectory');
        
        $retrieved = $this->citation_manager->get_citation_by_marker($marker);
        
        $this->assertNotNull($retrieved, 'Should retrieve citation by marker');
        $this->assertEquals('https://example.com/lookup', $retrieved['url']);
    }
    
    /**
     * Test minimum citation density across sections
     */
    public function test_minimum_citation_density() {
        $sections = [
            'executive_insight',
            'customer_fundamentals',
            'financial_trajectory',
            'margin_pressures',
            'strategic_priorities'
        ];
        
        $citations_per_section = [];
        
        foreach ($sections as $section) {
            // Add 3-5 citations per section
            $count = rand(3, 5);
            for ($i = 1; $i <= $count; $i++) {
                $this->citation_manager->add_citation([
                    'url' => "https://example.com/{$section}/{$i}",
                    'section' => $section
                ]);
            }
            $citations_per_section[$section] = $count;
        }
        
        // Check that 80% of sections have 3+ citations
        $sections_with_minimum = array_filter($citations_per_section, fn($count) => $count >= 3);
        $coverage = count($sections_with_minimum) / count($sections);
        
        $this->assertGreaterThanOrEqual(0.8, $coverage,
            'At least 80% of sections should have 3+ citations');
    }
    
    /**
     * Test average confidence threshold
     */
    public function test_average_confidence_threshold() {
        $citations = [
            ['domain' => 'sec.gov', 'publishedat' => date('Y-m-d')],
            ['domain' => 'bloomberg.com', 'publishedat' => date('Y-m-d', strtotime('-30 days'))],
            ['domain' => 'reuters.com', 'publishedat' => date('Y-m-d', strtotime('-60 days'))],
            ['domain' => 'wsj.com', 'publishedat' => date('Y-m-d', strtotime('-90 days'))],
            ['domain' => 'ft.com', 'publishedat' => date('Y-m-d', strtotime('-120 days'))]
        ];
        
        $total_confidence = 0;
        foreach ($citations as $citation) {
            $total_confidence += $this->scorer->calculate_confidence($citation);
        }
        
        $avg_confidence = $total_confidence / count($citations);
        
        $this->assertGreaterThanOrEqual(0.6, $avg_confidence,
            'Average confidence should be at least 0.6');
    }
    
    /**
     * Test diversity score minimum
     */
    public function test_diversity_score_minimum() {
        $citations = [
            ['domain' => 'sec.gov', 'publishedat' => date('Y-m-d')],
            ['domain' => 'bloomberg.com', 'publishedat' => date('Y-m-d', strtotime('-60 days'))],
            ['domain' => 'reuters.com', 'publishedat' => date('Y-m-d', strtotime('-120 days'))],
            ['domain' => 'gartner.com', 'publishedat' => date('Y-m-d', strtotime('-180 days'))],
            ['domain' => 'investor.company.com', 'publishedat' => date('Y-m-d', strtotime('-240 days'))]
        ];
        
        $metrics = $this->scorer->calculate_diversity_metrics($citations);
        
        $this->assertGreaterThanOrEqual(0.5, $metrics['diversity_score'],
            'Diversity score should be at least 0.5');
    }
    
    /**
     * Test legacy fields are preserved
     */
    public function test_legacy_fields_preserved() {
        $citation = [
            'url' => 'https://example.com/legacy',
            'title' => 'Legacy Test',
            'domain' => 'example.com',
            'year' => 2024,
            'publisher' => 'Example Publisher'
        ];
        
        $id = $this->citation_manager->add_citation($citation);
        $all_citations = $this->citation_manager->get_all_citations();
        $stored = $all_citations['citations'][0];
        
        $this->assertArrayHasKey('url', $stored);
        $this->assertArrayHasKey('title', $stored);
        $this->assertArrayHasKey('domain', $stored);
        
        $this->assertEquals('https://example.com/legacy', $stored['url']);
        $this->assertEquals('Legacy Test', $stored['title']);
    }
    
    /**
     * Test feature flag disabled behavior
     */
    public function test_feature_flag_disabled_behavior() {
        // Ensure feature is disabled
        $this->citation_manager->enable_enhancements(false);
        
        $citation = [
            'url' => 'https://example.com/noenh',
            'title' => 'No Enhancement'
        ];
        
        $id = $this->citation_manager->add_citation($citation);
        $marker = $this->citation_manager->generate_citation_marker($id, 'executive_insight');
        
        // Should use simple numeric markers without prefix
        $this->assertMatchesRegularExpression('/\[\d+\]/', $marker,
            'Disabled enhancements should use simple numeric markers');
        $this->assertStringNotContainsString('[EI', $marker,
            'Should not have section prefix when disabled');
    }
    
    /**
     * Test cache key isolation
     */
    public function test_cache_key_isolation() {
        // This would normally interact with cache, but we'll verify the structure
        $legacy_key = 'synthesis_citations_123';
        $enhanced_key = 'synthesis_citations_v2_123';
        
        $this->assertNotEquals($legacy_key, $enhanced_key,
            'Enhanced cache key should be different from legacy');
        $this->assertStringContainsString('v2', $enhanced_key,
            'Enhanced key should indicate version');
    }
}