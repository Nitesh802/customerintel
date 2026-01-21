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
 * Unit tests for V15 Section Depth Enhancement Part 2
 * Tests enhanced implementations of 6 synthesis methods for Gold Standard alignment
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
require_once($CFG->dirroot . '/local/customerintel/classes/services/qa_scorer.php');

/**
 * Test class for Section Enhancement Part 2
 */
class section_enhancement_part2_test extends advanced_testcase {
    
    /** @var synthesis_engine */
    private $synthesis_engine;
    
    /** @var qa_scorer */
    private $qa_scorer;
    
    /** @var object Mock citation manager */
    private $citation_manager;
    
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->synthesis_engine = new synthesis_engine();
        $this->qa_scorer = new qa_scorer();
        
        // Create mock citation manager
        $this->citation_manager = $this->create_mock_citation_manager();
    }
    
    /**
     * Create a mock citation manager for testing
     */
    private function create_mock_citation_manager() {
        return new class {
            public function process_section_citations($text, $section_name) {
                return [
                    'text' => $text,
                    'inline_citations' => [1, 2, 3, 4, 5],
                    'notes' => "Enhanced {$section_name} with Gold Standard patterns"
                ];
            }
        };
    }
    
    /**
     * Test enhanced draft_margin_pressures method
     */
    public function test_draft_margin_pressures_enhancement() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = ['pressures' => ['cost inflation', 'margin compression']];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        $method = $reflection->getMethod('draft_margin_pressures');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Verify 5-layer structure
        $this->assertStringContainsString('Cost Structure Breakdown', $result['text']);
        $this->assertStringContainsString('Operational Inefficiencies', $result['text']);
        $this->assertStringContainsString('External Pressures', $result['text']);
        $this->assertStringContainsString('Control Points', $result['text']);
        $this->assertStringContainsString('Partner Engagement Strategy', $result['text']);
        
        // Verify quantification
        $this->assertMatchesRegularExpression('/\d+%/', $result['text']);
        $this->assertMatchesRegularExpression('/\$\d+M/', $result['text']);
        
        // Verify citations
        $this->assertCount(5, $result['inline_citations']);
        
        // Test QA score
        $score = $this->qa_scorer->score_section([
            'text' => $result['text'],
            'inline_citations' => $result['inline_citations']
        ], []);
        
        $this->assertGreaterThan(0.75, $score['overall_score']);
    }
    
    /**
     * Test enhanced draft_strategic_priorities method
     */
    public function test_draft_strategic_priorities_enhancement() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = ['levers' => ['digital transformation', 'operational excellence']];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        $method = $reflection->getMethod('draft_strategic_priorities');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Verify 5-layer structure
        $this->assertStringContainsString('Strategic Imperatives', $result['text']);
        $this->assertStringContainsString('Executive Ownership Matrix', $result['text']);
        $this->assertStringContainsString('Implementation Roadmap', $result['text']);
        $this->assertStringContainsString('Success Metrics', $result['text']);
        $this->assertStringContainsString('Partnership Alignment', $result['text']);
        
        // Verify specific metrics
        $this->assertStringContainsString('$45M investment', $result['text']);
        $this->assertStringContainsString('15% efficiency', $result['text']);
        $this->assertStringContainsString('25% revenue growth', $result['text']);
        
        // Test QA score
        $score = $this->qa_scorer->score_section([
            'text' => $result['text'],
            'inline_citations' => $result['inline_citations']
        ], []);
        
        $this->assertGreaterThan(0.75, $score['overall_score']);
    }
    
    /**
     * Test enhanced draft_growth_levers method
     */
    public function test_draft_growth_levers_enhancement() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = ['timing' => ['Q1 2025', 'market expansion']];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        $method = $reflection->getMethod('draft_growth_levers');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Verify 5-layer structure
        $this->assertStringContainsString('Market Expansion Opportunities', $result['text']);
        $this->assertStringContainsString('Product & Solution Evolution', $result['text']);
        $this->assertStringContainsString('Channel & Partnership Strategy', $result['text']);
        $this->assertStringContainsString('Growth Investment Philosophy', $result['text']);
        $this->assertStringContainsString('Enablement Requirements', $result['text']);
        
        // Verify market sizing
        $this->assertStringContainsString('$450M addressable', $result['text']);
        $this->assertStringContainsString('healthcare ($180M)', $result['text']);
        
        // Test QA score
        $score = $this->qa_scorer->score_section([
            'text' => $result['text'],
            'inline_citations' => $result['inline_citations']
        ], []);
        
        $this->assertGreaterThan(0.75, $score['overall_score']);
    }
    
    /**
     * Test enhanced draft_buying_behavior method
     */
    public function test_draft_buying_behavior_enhancement() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = ['executives' => ['CFO', 'CTO', 'CPO']];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        $method = $reflection->getMethod('draft_buying_behavior');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Verify 5-layer structure
        $this->assertStringContainsString('Decision Authority Matrix', $result['text']);
        $this->assertStringContainsString('Evaluation Criteria Hierarchy', $result['text']);
        $this->assertStringContainsString('Procurement Process Dynamics', $result['text']);
        $this->assertStringContainsString('Budget Cycles', $result['text']);
        $this->assertStringContainsString('Influence Patterns', $result['text']);
        
        // Verify thresholds
        $this->assertStringContainsString('$500K threshold', $result['text']);
        $this->assertStringContainsString('18-month payback', $result['text']);
        
        // Test QA score
        $score = $this->qa_scorer->score_section([
            'text' => $result['text'],
            'inline_citations' => $result['inline_citations']
        ], []);
        
        $this->assertGreaterThan(0.75, $score['overall_score']);
    }
    
    /**
     * Test enhanced draft_current_initiatives method
     */
    public function test_draft_current_initiatives_enhancement() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = ['timing' => ['Q1 2025', 'phase 2']];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        $method = $reflection->getMethod('draft_current_initiatives');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Verify 5-layer structure
        $this->assertStringContainsString('Major Transformation Programs', $result['text']);
        $this->assertStringContainsString('Technology Modernization', $result['text']);
        $this->assertStringContainsString('Active Procurements', $result['text']);
        $this->assertStringContainsString('Business Capability Development', $result['text']);
        $this->assertStringContainsString('Risk Factors & Dependencies', $result['text']);
        
        // Verify specific initiatives
        $this->assertStringContainsString('SAP S/4HANA', $result['text']);
        $this->assertStringContainsString('Azure', $result['text']);
        $this->assertStringContainsString('$28M invested', $result['text']);
        
        // Test QA score
        $score = $this->qa_scorer->score_section([
            'text' => $result['text'],
            'inline_citations' => $result['inline_citations']
        ], []);
        
        $this->assertGreaterThan(0.75, $score['overall_score']);
    }
    
    /**
     * Test enhanced draft_risk_signals method
     */
    public function test_draft_risk_signals_enhancement() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = ['timing' => ['Q4 budget', 'January 2025']];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        $method = $reflection->getMethod('draft_risk_signals');
        $method->setAccessible(true);
        
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Verify 5-layer structure
        $this->assertStringContainsString('Decision Timing Windows', $result['text']);
        $this->assertStringContainsString('Regulatory & Compliance Pressures', $result['text']);
        $this->assertStringContainsString('Operational Constraints', $result['text']);
        $this->assertStringContainsString('Competitive Dynamics', $result['text']);
        $this->assertStringContainsString('Inaction Consequences', $result['text']);
        
        // Verify urgency indicators
        $this->assertStringContainsString('90-day decision window', $result['text']);
        $this->assertStringContainsString('$500K monthly', $result['text']);
        
        // Test QA score
        $score = $this->qa_scorer->score_section([
            'text' => $result['text'],
            'inline_citations' => $result['inline_citations']
        ], []);
        
        $this->assertGreaterThan(0.75, $score['overall_score']);
    }
    
    /**
     * Test cross-section coherence for all Part 2 enhancements
     */
    public function test_cross_section_coherence_part2() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = [
            'pressures' => ['margin compression', 'cost inflation'],
            'levers' => ['digital transformation', 'operational excellence'],
            'timing' => ['Q1 2025', 'Q4 budget'],
            'executives' => ['CEO', 'CFO', 'CTO']
        ];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        $sections = [];
        
        // Generate all enhanced sections
        $method_names = [
            'draft_margin_pressures',
            'draft_strategic_priorities',
            'draft_growth_levers',
            'draft_buying_behavior',
            'draft_current_initiatives',
            'draft_risk_signals'
        ];
        
        foreach ($method_names as $method_name) {
            $method = $reflection->getMethod($method_name);
            $method->setAccessible(true);
            $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
            $sections[$method_name] = $result;
        }
        
        // Test thematic consistency
        $combined_text = '';
        foreach ($sections as $section) {
            $combined_text .= $section['text'] . ' ';
        }
        
        // Verify consistent themes appear across sections
        $this->assertStringContainsString('digital transformation', $combined_text);
        $this->assertStringContainsString('operational', $combined_text);
        $this->assertStringContainsString('2025', $combined_text);
        
        // Test all sections meet QA threshold
        foreach ($sections as $name => $section_data) {
            $score = $this->qa_scorer->score_section([
                'text' => $section_data['text'],
                'inline_citations' => $section_data['inline_citations']
            ], []);
            
            $this->assertGreaterThan(0.75, $score['overall_score'], 
                "Section {$name} failed to meet QA threshold");
        }
    }
    
    /**
     * Test narrative depth and analytical layers
     */
    public function test_narrative_depth_part2() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = [];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        
        $method = $reflection->getMethod('draft_margin_pressures');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Test for presence of all 5 analytical layers
        $layers = [
            'Layer 1' => ['18% YoY', '200+ vendors', '8.2% of revenue'],
            'Layer 2' => ['2,300 FTE hours', '7x data entry', '35% of IT budget'],
            'Layer 3' => ['Q1 2025', '8-12% discounting', '22% above historical'],
            'Layer 4' => ['CFO controls', '$500K', 'P&L accountability'],
            'Layer 5' => ['15-20% cost reduction', '$3M savings', '300bps']
        ];
        
        foreach ($layers as $layer_name => $expected_content) {
            foreach ($expected_content as $content) {
                $this->assertStringContainsString($content, $result['text'],
                    "Missing expected content '{$content}' in {$layer_name}");
            }
        }
    }
    
    /**
     * Test quantification and specificity
     */
    public function test_quantification_specificity_part2() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = [];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        
        // Test each enhanced method for quantification
        $methods_to_test = [
            'draft_strategic_priorities' => ['$45M', '15%', '25%', 'Q4 2025'],
            'draft_growth_levers' => ['$450M', '$180M', '30%', '20%'],
            'draft_current_initiatives' => ['$28M', '60%', '$4.2M', '3,200 users'],
            'draft_risk_signals' => ['90-day', '$3M', '$500K monthly', '8%']
        ];
        
        foreach ($methods_to_test as $method_name => $expected_metrics) {
            $method = $reflection->getMethod($method_name);
            $method->setAccessible(true);
            $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
            
            foreach ($expected_metrics as $metric) {
                $this->assertStringContainsString($metric, $result['text'],
                    "Missing metric '{$metric}' in {$method_name}");
            }
        }
    }
    
    /**
     * Test causal relationships and second-order effects
     */
    public function test_causal_relationships_part2() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = [];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        
        $method = $reflection->getMethod('draft_buying_behavior');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
        
        // Test for causal language
        $causal_indicators = [
            'requires', 'drives', 'creates', 'enables', 'affects',
            'depends', 'results in', 'leads to', 'causing', 'determining'
        ];
        
        $causal_count = 0;
        foreach ($causal_indicators as $indicator) {
            if (stripos($result['text'], $indicator) !== false) {
                $causal_count++;
            }
        }
        
        $this->assertGreaterThanOrEqual(3, $causal_count,
            "Insufficient causal relationships in narrative");
    }
    
    /**
     * Test citation integration and evidence strength
     */
    public function test_citation_integration_part2() {
        $inputs = ['company_source' => ['name' => 'TestCorp']];
        $patterns = [];
        
        $reflection = new \ReflectionClass($this->synthesis_engine);
        
        // Test all enhanced methods have proper citation integration
        $method_names = [
            'draft_margin_pressures',
            'draft_strategic_priorities',
            'draft_growth_levers',
            'draft_buying_behavior',
            'draft_current_initiatives',
            'draft_risk_signals'
        ];
        
        foreach ($method_names as $method_name) {
            $method = $reflection->getMethod($method_name);
            $method->setAccessible(true);
            $result = $method->invokeArgs($this->synthesis_engine, [$inputs, $patterns, $this->citation_manager]);
            
            // Each section should have 5 citations
            $this->assertCount(5, $result['inline_citations'],
                "Section {$method_name} should have exactly 5 citations");
            
            // Text should contain citation markers [1] through [5]
            for ($i = 1; $i <= 5; $i++) {
                $this->assertStringContainsString("[{$i}]", $result['text'],
                    "Missing citation marker [{$i}] in {$method_name}");
            }
            
            // Verify evidence strength score
            $evidence_score = $this->qa_scorer->calculate_evidence_strength(
                array_map(function($id) {
                    return ['id' => $id, 'url' => "https://example.com/source{$id}"];
                }, $result['inline_citations'])
            );
            
            $this->assertGreaterThan(0.6, $evidence_score,
                "Evidence strength below threshold for {$method_name}");
        }
    }
}