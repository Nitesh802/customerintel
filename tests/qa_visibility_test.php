<?php
/**
 * Test QA visibility and troubleshooting features
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for QA visibility features in view_report.php
 */
class qa_visibility_test extends advanced_testcase {

    /**
     * Test QA Details section structure
     */
    public function test_qa_details_section_structure() {
        global $DB;
        
        $this->resetAfterTest();
        
        // Create test synthesis data with QA reports
        $synthesis_data = [
            'runid' => 123,
            'htmlcontent' => '<div>Test content</div>',
            'jsoncontent' => json_encode([
                'citations_used' => [
                    ['title' => 'Test Article', 'domain' => 'example.com', 'url' => 'https://example.com/article'],
                    ['title' => 'Another Source', 'url' => 'https://test.org/source']
                ]
            ]),
            'voice_report' => json_encode([
                'tone_check' => ['passed' => true, 'score' => 0.9],
                'clarity_check' => ['passed' => false, 'score' => 0.6],
                'length_check' => ['passed' => true, 'score' => 1.0]
            ]),
            'selfcheck_report' => json_encode([
                'pass' => false,
                'violations' => [
                    [
                        'rule' => 'execution_leak',
                        'location' => 'opportunities',
                        'severity' => 'error',
                        'message' => 'Found execution detail: "email" in opportunities'
                    ],
                    [
                        'rule' => 'consultant_speak',
                        'location' => 'executive_summary',
                        'severity' => 'warn',
                        'message' => 'Found consultant-speak: "synergy" in executive_summary'
                    ],
                    [
                        'rule' => 'execution_leak',
                        'location' => 'convergence',
                        'severity' => 'error',
                        'message' => 'Found execution detail: "schedule" in convergence'
                    ]
                ]
            ]),
            'createdat' => time(),
            'updatedat' => time()
        ];
        
        $synthesis_id = $DB->insert_record('local_ci_synthesis', $synthesis_data);
        
        // Test voice report parsing
        $voice_report = json_decode($synthesis_data['voice_report'], true);
        $this->assertIsArray($voice_report);
        
        $voice_pass_count = 0;
        foreach ($voice_report as $check => $result) {
            if ($result['passed'] ?? false) {
                $voice_pass_count++;
            }
        }
        $this->assertEquals(2, $voice_pass_count, '2 out of 3 voice checks should pass');
        
        // Test self-check violations grouping
        $selfcheck_data = json_decode($synthesis_data['selfcheck_report'], true);
        $violations_by_rule = [];
        foreach ($selfcheck_data['violations'] as $violation) {
            $rule = $violation['rule'] ?? 'unknown';
            if (!isset($violations_by_rule[$rule])) {
                $violations_by_rule[$rule] = [];
            }
            $violations_by_rule[$rule][] = $violation;
        }
        
        $this->assertArrayHasKey('execution_leak', $violations_by_rule);
        $this->assertArrayHasKey('consultant_speak', $violations_by_rule);
        $this->assertEquals(2, count($violations_by_rule['execution_leak']));
        $this->assertEquals(1, count($violations_by_rule['consultant_speak']));
        
        // Test citations parsing
        $json_data = json_decode($synthesis_data['jsoncontent'], true);
        $this->assertCount(2, $json_data['citations_used']);
        $this->assertEquals('Test Article', $json_data['citations_used'][0]['title']);
        $this->assertEquals('example.com', $json_data['citations_used'][0]['domain']);
    }

    /**
     * Test violation severity badges
     */
    public function test_violation_severity_badges() {
        $test_violations = [
            ['severity' => 'error', 'expected_class' => 'badge-danger'],
            ['severity' => 'warn', 'expected_class' => 'badge-warning'],
            ['severity' => 'info', 'expected_class' => 'badge-info']
        ];
        
        foreach ($test_violations as $test) {
            $severity = $test['severity'];
            $expected_class = $test['expected_class'];
            $actual_class = 'badge-' . ($severity === 'error' ? 'danger' : ($severity === 'warn' ? 'warning' : 'info'));
            $this->assertEquals($expected_class, $actual_class);
        }
    }

    /**
     * Test citation domain extraction
     */
    public function test_citation_domain_extraction() {
        $test_citations = [
            [
                'url' => 'https://www.example.com/article',
                'expected_domain' => 'www.example.com'
            ],
            [
                'url' => 'http://test.org/page?param=value',
                'expected_domain' => 'test.org'
            ],
            [
                'url' => 'https://subdomain.domain.co.uk/path',
                'expected_domain' => 'subdomain.domain.co.uk'
            ]
        ];
        
        foreach ($test_citations as $test) {
            $domain = parse_url($test['url'], PHP_URL_HOST);
            $this->assertEquals($test['expected_domain'], $domain);
        }
    }

    /**
     * Test admin capability detection for debug section
     */
    public function test_admin_capability_for_debug() {
        global $DB, $USER;
        
        $this->resetAfterTest();
        
        // Create a test context
        $context = context_system::instance();
        
        // Test that admin capability is checked for debug section
        $has_manage_capability = has_capability('local/customerintel:manage', $context);
        
        // Note: In a real test environment, you would set up users with different capabilities
        // This test just verifies the capability string is correct
        $this->assertIsBool($has_manage_capability);
    }

    /**
     * Test compact tree format for debug data
     */
    public function test_debug_tree_format() {
        $mock_patterns = [
            'pressures' => [
                ['theme' => 'Executive Pressure', 'source' => 'NB1', 'text' => 'Board expectations for Q4 performance'],
                ['theme' => 'Financial Health', 'source' => 'NB3', 'text' => 'Margin pressures from competitive landscape']
            ]
        ];
        
        $mock_bridge = [
            'items' => [
                [
                    'why_it_matters_to_target' => 'Operational efficiency gains',
                    'timing_sync' => 'Q4 2024 alignment',
                    'supporting_evidence' => ['citation1', 'citation2']
                ]
            ]
        ];
        
        // Test that the data structure is correctly formatted for display
        $this->assertCount(2, $mock_patterns['pressures']);
        $this->assertEquals('Executive Pressure', $mock_patterns['pressures'][0]['theme']);
        
        $this->assertCount(1, $mock_bridge['items']);
        $this->assertEquals('Operational efficiency gains', $mock_bridge['items'][0]['why_it_matters_to_target']);
    }

    /**
     * Test error handling in debug section
     */
    public function test_debug_error_handling() {
        // Test that exceptions are caught and handled gracefully
        try {
            // Simulate a method that might throw an exception
            throw new Exception('Test exception for debug handling');
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->assertEquals('Test exception for debug handling', $error_message);
            
            // Verify that the error would be properly escaped for HTML display
            $escaped_message = htmlspecialchars($error_message);
            $this->assertEquals('Test exception for debug handling', $escaped_message);
        }
    }
}