<?php
/**
 * Test NB mapping with Source vs Target context flow
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/services/synthesis_engine.php');

use local_customerintel\services\synthesis_engine;

/**
 * Tests for Source/Target context handling in synthesis
 */
class nb_mapping_context_test extends advanced_testcase {

    /**
     * Test ViiV → Duke Health scenario
     */
    public function test_viiv_duke_health_scenario() {
        global $DB;
        
        $this->resetAfterTest();
        
        // Create ViiV Healthcare (Source) company
        $viiv_data = [
            'name' => 'ViiV Healthcare',
            'ticker' => null,
            'sector' => 'Pharmaceutical',
            'website' => 'viivhealthcare.com',
            'type' => 'customer',
            'timecreated' => time(),
            'timemodified' => time()
        ];
        $viiv_id = $DB->insert_record('local_ci_company', $viiv_data);
        
        // Create Duke Health (Target) company
        $duke_data = [
            'name' => 'Duke Health',
            'ticker' => null,
            'sector' => 'Healthcare',
            'website' => 'dukehealth.org',
            'type' => 'target',
            'timecreated' => time(),
            'timemodified' => time()
        ];
        $duke_id = $DB->insert_record('local_ci_company', $duke_data);
        
        // Create a completed run
        $run_data = [
            'companyid' => $viiv_id,
            'targetcompanyid' => $duke_id,
            'userid' => 1,
            'initiatedbyuserid' => 1,
            'status' => 'completed',
            'timecompleted' => time() - 1800,
            'timecreated' => time() - 3600,
            'timemodified' => time() - 1800
        ];
        $run_id = $DB->insert_record('local_ci_run', $run_data);
        
        // Create mock NB results
        $this->create_mock_nb_results($run_id);
        
        $synthesis_engine = new synthesis_engine();
        
        // Test the normalized inputs include both companies
        $inputs = $synthesis_engine->get_normalized_inputs($run_id);
        
        $this->assertNotNull($inputs['company_source']);
        $this->assertEquals('ViiV Healthcare', $inputs['company_source']->name);
        $this->assertEquals('Pharmaceutical', $inputs['company_source']->sector);
        
        $this->assertNotNull($inputs['company_target']);
        $this->assertEquals('Duke Health', $inputs['company_target']->name);
        $this->assertEquals('Healthcare', $inputs['company_target']->sector);
        
        // Test target context generation
        $context = $this->call_private_method($synthesis_engine, 'generate_target_context', [$inputs['company_target']]);
        $this->assertStringContainsString('academic health system cadence', $context);
        $this->assertStringContainsString('mixed payer environment', $context);
        $this->assertStringContainsString('research calendar constraints', $context);
        
        // Test assumptions generation for Duke Health
        $json_data = json_decode($this->call_private_method($synthesis_engine, 'compile_json_output', [
            ['executive_summary' => 'Test summary'],
            [], // patterns
            [], // bridge
            $inputs,
            ['pass' => true, 'violations' => []]
        ]), true);
        
        $this->assertArrayHasKey('assumptions', $json_data);
        $this->assertContains('Academic health system cadence (quarterly research reviews, academic calendar constraints)', $json_data['assumptions']);
        $this->assertContains('Mixed payer environment (commercial, Medicaid, Medicare, self-pay)', $json_data['assumptions']);
        $this->assertContains('Research calendars align with fiscal year planning cycles', $json_data['assumptions']);
        
        // Test context roles
        $this->assertEquals('capability_holder', $json_data['context']['source_company']['role']);
        $this->assertEquals('beneficiary_or_risk_holder', $json_data['context']['target_company']['role']);
    }

    /**
     * Test Context header generation in HTML
     */
    public function test_context_header_in_html() {
        global $DB;
        
        $this->resetAfterTest();
        
        // Create test companies
        $source_id = $DB->insert_record('local_ci_company', [
            'name' => 'Source Corp',
            'sector' => 'Technology',
            'timecreated' => time(),
            'timemodified' => time()
        ]);
        
        $target_id = $DB->insert_record('local_ci_company', [
            'name' => 'Target Inc',
            'sector' => 'Financial Services',
            'timecreated' => time(),
            'timemodified' => time()
        ]);
        
        $run_id = $DB->insert_record('local_ci_run', [
            'companyid' => $source_id,
            'targetcompanyid' => $target_id,
            'userid' => 1,
            'initiatedbyuserid' => 1,
            'status' => 'completed',
            'timecompleted' => time(),
            'timecreated' => time(),
            'timemodified' => time()
        ]);
        
        $synthesis_engine = new synthesis_engine();
        $inputs = $synthesis_engine->get_normalized_inputs($run_id);
        
        $sections = [
            'executive_summary' => 'Test executive summary',
            'overlooked' => ['Test overlooked item'],
            'opportunities' => [['body' => 'Test opportunity']],
            'convergence' => 'Test convergence insight'
        ];
        
        $html = $this->call_private_method($synthesis_engine, 'render_playbook_html', [
            $sections,
            $inputs,
            ['pass' => true, 'violations' => []]
        ]);
        
        $this->assertStringContainsString('Context:', $html);
        $this->assertStringContainsString('Source: Source Corp', $html);
        $this->assertStringContainsString('→ Target: Target Inc', $html);
        $this->assertStringContainsString('playbook-context', $html);
    }

    /**
     * Helper method to create mock NB results
     */
    private function create_mock_nb_results(int $run_id) {
        global $DB;
        
        $nb_results = [
            'NB1' => [
                'executive_mandates' => ['Drive operational efficiency'],
                'pressure_points' => ['Cost reduction targets'],
                'time_markers' => ['Q4 2024 deadline']
            ],
            'NB3' => [
                'margin_pressures' => ['15% margin decline'],
                'guidance' => ['Lower guidance expectations']
            ]
        ];
        
        foreach ($nb_results as $nbcode => $data) {
            $DB->insert_record('local_ci_nb_result', [
                'runid' => $run_id,
                'nbcode' => $nbcode,
                'jsonpayload' => json_encode($data),
                'citations' => json_encode([]),
                'status' => 'completed',
                'timecreated' => time(),
                'timemodified' => time()
            ]);
        }
    }

    /**
     * Helper method to call private methods for testing
     */
    private function call_private_method($object, $method_name, $parameters = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}