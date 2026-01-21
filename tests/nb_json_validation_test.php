<?php
/**
 * NB JSON Schema Validation Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/helpers/json_validator.php');
require_once($CFG->dirroot . '/local/customerintel/tests/mocks/mock_llm_client.php');

use local_customerintel\helpers\json_validator;
use local_customerintel\tests\mocks\mock_llm_client;

/**
 * Test class for NB JSON schema validation
 * 
 * @group local_customerintel
 * @group customerintel_nb_validation
 */
class nb_json_validation_test extends \advanced_testcase {
    
    /** @var json_validator Validator instance */
    private $validator;
    
    /** @var mock_llm_client Mock LLM client */
    private $mock_client;
    
    /** @var array Schema cache */
    private $schemas = [];
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->validator = new json_validator();
        $this->mock_client = new mock_llm_client();
        
        // Load schemas
        $this->load_schemas();
    }
    
    /**
     * Load all NB schemas
     */
    private function load_schemas(): void {
        global $CFG;
        
        for ($i = 1; $i <= 15; $i++) {
            $schemafile = $CFG->dirroot . "/local/customerintel/schemas/nb$i.json";
            if (file_exists($schemafile)) {
                $this->schemas["NB$i"] = json_decode(file_get_contents($schemafile), true);
            }
        }
    }
    
    /**
     * Test NB1 Executive Pressure schema validation
     * 
     * @covers \local_customerintel\helpers\json_validator::validate
     */
    public function test_nb1_executive_pressure_validation() {
        $nbcode = 'NB1';
        
        // Get valid response from mock
        $response = $this->mock_client->execute_prompt("Analyze executive pressure for NB1");
        $this->assertTrue($response['success']);
        
        // Validate against schema
        $validation = $this->validator->validate($response['payload'], $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid'], "NB1 validation failed: " . json_encode($validation['errors'] ?? []));
        
        // Test with invalid data - missing required field
        $invalid_data = [
            'board_expectations' => ['Some expectation'],
            // Missing investor_commitments, executive_mandates, pressure_points
        ];
        
        $validation = $this->validator->validate($invalid_data, $this->schemas[$nbcode]);
        $this->assertFalse($validation['valid']);
        $this->assertContains('investor_commitments', $validation['errors'][0] ?? '');
    }
    
    /**
     * Test NB3 Financial Health schema validation
     * 
     * @covers \local_customerintel\helpers\json_validator::validate
     */
    public function test_nb3_financial_health_validation() {
        $nbcode = 'NB3';
        
        // Get valid response from mock
        $response = $this->mock_client->execute_prompt("Analyze financial health for NB3");
        $this->assertTrue($response['success']);
        
        // Validate against schema
        $validation = $this->validator->validate($response['payload'], $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid'], "NB3 validation failed: " . json_encode($validation['errors'] ?? []));
        
        // Test numeric validation
        $invalid_data = $response['payload'];
        $invalid_data['revenue_metrics']['growth_rate'] = 'not_a_number';
        
        $validation = $this->validator->validate($invalid_data, $this->schemas[$nbcode]);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('must be number', $validation['errors'][0] ?? '');
        
        // Test range validation
        $invalid_data = $response['payload'];
        $invalid_data['profitability']['gross_margin'] = 150; // Invalid percentage
        
        $validation = $this->validator->validate($invalid_data, $this->schemas[$nbcode]);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('out of range', $validation['errors'][0] ?? '');
    }
    
    /**
     * Data provider for all NB codes
     */
    public function nb_codes_provider(): array {
        $codes = [];
        for ($i = 1; $i <= 15; $i++) {
            $codes["NB$i"] = ["NB$i"];
        }
        return $codes;
    }
    
    /**
     * Test all NB schemas with mock responses
     * 
     * @dataProvider nb_codes_provider
     * @covers \local_customerintel\helpers\json_validator::validate
     */
    public function test_all_nb_schemas_validation(string $nbcode) {
        if (!isset($this->schemas[$nbcode])) {
            $this->markTestSkipped("Schema for $nbcode not found");
        }
        
        // Get mock response
        $response = $this->mock_client->execute_prompt("Test prompt for $nbcode");
        $this->assertTrue($response['success'], "Failed to get mock response for $nbcode");
        
        // Validate against schema
        $validation = $this->validator->validate($response['payload'], $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid'], 
            "$nbcode validation failed: " . json_encode($validation['errors'] ?? []));
        
        // Verify payload structure matches expectations
        $this->assertIsArray($response['payload']);
        $this->assertNotEmpty($response['payload']);
    }
    
    /**
     * Test schema validation with nested objects
     * 
     * @covers \local_customerintel\helpers\json_validator::validate
     */
    public function test_nested_object_validation() {
        $nbcode = 'NB6'; // Technology & Digital Maturity has nested objects
        
        $response = $this->mock_client->execute_prompt("Analyze technology for NB6");
        
        // Validate nested structure
        $validation = $this->validator->validate($response['payload'], $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid']);
        
        // Test deep nesting
        $this->assertArrayHasKey('digital_maturity', $response['payload']);
        $this->assertArrayHasKey('current_stage', $response['payload']['digital_maturity']);
        $this->assertArrayHasKey('technology_stack', $response['payload']);
        $this->assertArrayHasKey('cloud_adoption', $response['payload']['technology_stack']);
    }
    
    /**
     * Test array validation in schemas
     * 
     * @covers \local_customerintel\helpers\json_validator::validate
     */
    public function test_array_validation() {
        $nbcode = 'NB4'; // Strategic Priorities has arrays
        
        $response = $this->mock_client->execute_prompt("Strategic priorities for NB4");
        
        // Validate array fields
        $validation = $this->validator->validate($response['payload'], $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid']);
        
        // Test array requirements
        $this->assertIsArray($response['payload']['strategic_priorities']);
        $this->assertNotEmpty($response['payload']['strategic_priorities']);
        
        // Test with empty array (should fail if required)
        $invalid_data = $response['payload'];
        $invalid_data['strategic_priorities'] = [];
        
        $validation = $this->validator->validate($invalid_data, $this->schemas[$nbcode]);
        $this->assertFalse($validation['valid']);
    }
    
    /**
     * Test enum validation
     * 
     * @covers \local_customerintel\helpers\json_validator::validate
     */
    public function test_enum_validation() {
        $nbcode = 'NB10'; // Risk & Resilience has enum fields
        
        $response = $this->mock_client->execute_prompt("Risk assessment for NB10");
        $validation = $this->validator->validate($response['payload'], $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid']);
        
        // Test invalid enum value
        $invalid_data = $response['payload'];
        $invalid_data['risk_assessment']['risk_score'] = 'InvalidScore';
        
        $validation = $this->validator->validate($invalid_data, $this->schemas[$nbcode]);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('enum', $validation['errors'][0] ?? '');
    }
    
    /**
     * Test optional vs required fields
     * 
     * @covers \local_customerintel\helpers\json_validator::validate
     */
    public function test_optional_required_fields() {
        $nbcode = 'NB11'; // Leadership & Culture
        
        // Test with all required fields
        $response = $this->mock_client->execute_prompt("Leadership analysis for NB11");
        $validation = $this->validator->validate($response['payload'], $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid']);
        
        // Remove optional field - should still pass
        $data = $response['payload'];
        unset($data['culture_metrics']['values_alignment']); // If optional
        $validation = $this->validator->validate($data, $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid']);
        
        // Remove required field - should fail
        $data = $response['payload'];
        unset($data['leadership_assessment']);
        $validation = $this->validator->validate($data, $this->schemas[$nbcode]);
        $this->assertFalse($validation['valid']);
    }
    
    /**
     * Test validation error messages
     * 
     * @covers \local_customerintel\helpers\json_validator::get_error_messages
     */
    public function test_validation_error_messages() {
        $nbcode = 'NB5';
        
        // Create invalid data with multiple errors
        $invalid_data = [
            'gross_margin_analysis' => [
                'current' => 'not_a_number', // Should be number
                'target' => 200, // Out of range
                // Missing 'trend' field
            ],
            // Missing 'cost_structure' field
        ];
        
        $validation = $this->validator->validate($invalid_data, $this->schemas[$nbcode]);
        $this->assertFalse($validation['valid']);
        
        // Check error messages are informative
        $this->assertIsArray($validation['errors']);
        $this->assertNotEmpty($validation['errors']);
        
        // Should have multiple specific errors
        $errors_string = implode(' ', $validation['errors']);
        $this->assertStringContainsString('number', $errors_string);
        $this->assertStringContainsString('required', $errors_string);
    }
    
    /**
     * Test schema repair functionality
     * 
     * @covers \local_customerintel\helpers\json_validator::repair
     */
    public function test_schema_repair() {
        $nbcode = 'NB2';
        
        // Create slightly malformed data
        $malformed_data = [
            'market_conditions' => [
                'Growing market at 8% CAGR', // Should be array
            ],
            'competitive_landscape' => [
                'market_position' => 3, // Should be string
                'market_share' => '18%', // Correct
                // Missing 'key_competitors'
            ],
            // Missing 'regulatory_environment'
        ];
        
        // Attempt repair
        $repaired = $this->validator->repair($malformed_data, $this->schemas[$nbcode]);
        
        // Validate repaired data
        $validation = $this->validator->validate($repaired, $this->schemas[$nbcode]);
        $this->assertTrue($validation['valid'], "Repair failed: " . json_encode($validation['errors'] ?? []));
        
        // Check repairs were made
        $this->assertIsArray($repaired['market_conditions']);
        $this->assertIsString($repaired['competitive_landscape']['market_position']);
        $this->assertArrayHasKey('key_competitors', $repaired['competitive_landscape']);
        $this->assertArrayHasKey('regulatory_environment', $repaired);
    }
    
    /**
     * Test cross-NB consistency
     */
    public function test_cross_nb_consistency() {
        // Get responses for related NBs
        $nb3_response = $this->mock_client->execute_prompt("Financial health NB3");
        $nb5_response = $this->mock_client->execute_prompt("Margin analysis NB5");
        
        // Both should have related financial metrics
        $nb3_margin = $nb3_response['payload']['profitability']['gross_margin'] ?? null;
        $nb5_margin = $nb5_response['payload']['gross_margin_analysis']['current'] ?? null;
        
        $this->assertNotNull($nb3_margin);
        $this->assertNotNull($nb5_margin);
        
        // Should be consistent (within reasonable variance due to mock noise)
        $this->assertEquals($nb3_margin, $nb5_margin, '', 2.0); // Allow 2% delta
    }
    
    /**
     * Test citation validation
     * 
     * @covers \local_customerintel\helpers\json_validator::validate_citations
     */
    public function test_citation_validation() {
        // Get response with citations
        $response = $this->mock_client->execute_prompt("Test with citations NB1");
        
        $this->assertArrayHasKey('citations', $response);
        $this->assertIsArray($response['citations']);
        
        // Validate citation structure
        foreach ($response['citations'] as $citation) {
            $this->assertArrayHasKey('source_id', $citation);
            $this->assertArrayHasKey('title', $citation);
            $this->assertIsInt($citation['source_id']);
            $this->assertIsString($citation['title']);
            
            if (isset($citation['page'])) {
                $this->assertIsInt($citation['page']);
            }
            if (isset($citation['relevance'])) {
                $this->assertIsFloat($citation['relevance']);
                $this->assertGreaterThanOrEqual(0, $citation['relevance']);
                $this->assertLessThanOrEqual(1, $citation['relevance']);
            }
        }
    }
    
    /**
     * Test handling of additional properties
     */
    public function test_additional_properties() {
        $nbcode = 'NB7';
        
        $response = $this->mock_client->execute_prompt("Operational excellence NB7");
        
        // Add extra property not in schema
        $data_with_extra = $response['payload'];
        $data_with_extra['unexpected_field'] = 'extra_value';
        
        // Should either pass (if additionalProperties allowed) or fail with clear error
        $validation = $this->validator->validate($data_with_extra, $this->schemas[$nbcode]);
        
        if (!$validation['valid']) {
            $this->assertStringContainsString('additional', implode(' ', $validation['errors']));
        }
    }
}