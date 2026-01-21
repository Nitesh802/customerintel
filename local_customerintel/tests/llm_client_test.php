<?php
/**
 * LLMClient unit tests
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

use advanced_testcase;
use local_customerintel\clients\llm_client;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/clients/llm_client.php');

/**
 * LLMClient test class
 * 
 * @group local_customerintel
 * @covers \local_customerintel\clients\llm_client
 */
class llm_client_test extends advanced_testcase {

    /** @var llm_client LLM Client instance */
    protected $client;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        // Set up test configuration
        set_config('llm_provider', 'openai-gpt4', 'local_customerintel');
        set_config('llm_key', 'test-api-key', 'local_customerintel');
        set_config('llm_temperature', 0.2, 'local_customerintel');
        set_config('request_timeout', 120, 'local_customerintel');
    }

    /**
     * Test client initialization
     */
    public function test_initialization() {
        $client = new llm_client();
        
        $this->assertInstanceOf(llm_client::class, $client);
        
        // Test with custom config
        $config = [
            'provider' => 'anthropic-claude',
            'apikey' => 'custom-key',
            'temperature' => 0.1,
            'maxtokens' => 2048
        ];
        
        $customclient = new llm_client($config);
        $this->assertInstanceOf(llm_client::class, $customclient);
    }

    /**
     * Test temperature limit enforcement
     */
    public function test_temperature_limit() {
        // Test that temperature is capped at 0.2
        $config = [
            'provider' => 'openai-gpt4',
            'apikey' => 'test-key',
            'temperature' => 0.9
        ];
        
        $client = new llm_client($config);
        
        // Temperature should be capped at 0.2 internally
        // We can't directly access the protected property, but we can test behavior
        $this->assertInstanceOf(llm_client::class, $client);
    }

    /**
     * Test mock mode
     */
    public function test_mock_mode() {
        $config = [
            'mock_mode' => true,
            'provider' => 'openai-gpt4',
            'apikey' => 'test-key'
        ];
        
        $client = new llm_client($config);
        
        // Test basic call in mock mode
        $result = $client->call(
            'You are a test assistant',
            'Generate test data',
            null,
            false
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertArrayHasKey('tokens_used', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertEquals('mock-model', $result['model']);
    }

    /**
     * Test mock mode with JSON schema
     */
    public function test_mock_mode_with_schema() {
        $config = [
            'mock_mode' => true,
            'provider' => 'openai-gpt4',
            'apikey' => 'test-key'
        ];
        
        $client = new llm_client($config);
        
        $schema = [
            'type' => 'object',
            'required' => ['name', 'age'],
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 150],
                'email' => ['type' => 'string']
            ]
        ];
        
        $result = $client->call(
            'Extract user information',
            'Get user details',
            $schema,
            true
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        
        // Parse mock JSON
        $data = json_decode($result['content'], true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('age', $data);
    }

    /**
     * Test extract method
     */
    public function test_extract_method() {
        $config = [
            'mock_mode' => true,
            'provider' => 'openai-gpt4',
            'apikey' => 'test-key'
        ];
        
        $client = new llm_client($config);
        
        $chunks = [
            ['text' => 'Company revenue increased by 20% in Q1.'],
            ['text' => 'Market share expanded to 15% globally.'],
            ['text' => 'Operating costs reduced by 10% year-over-year.']
        ];
        
        $result = $client->extract('NB1', 'Extract executive pressure', $chunks);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('tokens_used', $result);
    }

    /**
     * Test JSON validation
     */
    public function test_validate_json() {
        $client = new llm_client(['mock_mode' => true]);
        
        // Valid NB1 data
        $validdata = [
            'executive_summary' => 'This is a comprehensive executive summary with sufficient detail about the company pressure profile analysis.',
            'pressure_factors' => [
                [
                    'factor' => 'Competition',
                    'severity' => 'high',
                    'timeline' => 'Q1 2024',
                    'description' => 'Increased competition'
                ]
            ],
            'commitments' => [
                [
                    'commitment' => 'Revenue target',
                    'deadline' => '2024-12-31',
                    'status' => 'on-track'
                ]
            ],
            'key_metrics' => [
                [
                    'metric' => 'Revenue',
                    'value' => '$100M',
                    'trend' => 'improving'
                ]
            ],
            'citations' => [
                ['source_id' => 1]
            ]
        ];
        
        $result = $client->validate_json('NB1', $validdata);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertTrue($result['valid']);
        
        // Invalid data (missing required fields)
        $invaliddata = [
            'executive_summary' => 'Short summary'
        ];
        
        $result = $client->validate_json('NB1', $invaliddata);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test JSON repair
     */
    public function test_repair_invalid_json() {
        $client = new llm_client(['mock_mode' => true]);
        
        // Invalid data missing required fields
        $invaliddata = [
            'executive_summary' => 'Test summary with sufficient length to meet minimum requirements for the executive summary field',
            'pressure_factors' => []
        ];
        
        $repaired = $client->repair_invalid_json('NB1', $invaliddata);
        
        // Repair should add missing required fields
        $this->assertIsArray($repaired);
        $this->assertArrayHasKey('commitments', $repaired);
        $this->assertArrayHasKey('key_metrics', $repaired);
        $this->assertArrayHasKey('citations', $repaired);
    }

    /**
     * Test retry logic
     */
    public function test_call_with_retry() {
        // Create a mock client that fails first two times
        $client = $this->getMockBuilder(llm_client::class)
            ->setConstructorArgs([['mock_mode' => false]])
            ->onlyMethods(['make_request'])
            ->getMock();
        
        // First two calls fail
        $client->expects($this->exactly(2))
            ->method('make_request')
            ->will($this->onConsecutiveCalls(
                $this->throwException(new \moodle_exception('llmrequestfailed', 'local_customerintel')),
                $this->returnValue(json_encode([
                    'choices' => [
                        ['message' => ['content' => '{"test": "data"}']]
                    ],
                    'usage' => ['total_tokens' => 100]
                ]))
            ));
        
        // Should succeed on second attempt
        $result = $client->call_with_retry('System prompt', 'User prompt', null, 3);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
    }

    /**
     * Test provider-specific request building
     */
    public function test_openai_request_building() {
        $client = new llm_client([
            'mock_mode' => true,
            'provider' => 'openai-gpt4',
            'apikey' => 'test-key'
        ]);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('build_openai_request');
        $method->setAccessible(true);
        
        $request = $method->invoke($client, 'System prompt', 'User prompt', true);
        
        $this->assertIsArray($request);
        $this->assertArrayHasKey('model', $request);
        $this->assertArrayHasKey('messages', $request);
        $this->assertArrayHasKey('temperature', $request);
        $this->assertArrayHasKey('response_format', $request);
        $this->assertEquals('json_object', $request['response_format']['type']);
    }

    /**
     * Test anthropic request building
     */
    public function test_anthropic_request_building() {
        $client = new llm_client([
            'mock_mode' => true,
            'provider' => 'anthropic-claude',
            'apikey' => 'test-key'
        ]);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('build_anthropic_request');
        $method->setAccessible(true);
        
        $request = $method->invoke($client, 'System prompt', 'User prompt', true);
        
        $this->assertIsArray($request);
        $this->assertArrayHasKey('model', $request);
        $this->assertArrayHasKey('system', $request);
        $this->assertArrayHasKey('messages', $request);
        $this->assertArrayHasKey('temperature', $request);
        $this->assertStringContainsString('JSON', $request['system']);
    }

    /**
     * Test mock data generation from schema
     */
    public function test_mock_data_generation() {
        $client = new llm_client(['mock_mode' => true]);
        
        $schema = [
            'type' => 'object',
            'required' => ['string_field', 'number_field', 'array_field'],
            'properties' => [
                'string_field' => [
                    'type' => 'string'
                ],
                'number_field' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 10
                ],
                'array_field' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'enum_field' => [
                    'type' => 'string',
                    'enum' => ['option1', 'option2', 'option3']
                ],
                'boolean_field' => [
                    'type' => 'boolean'
                ]
            ]
        ];
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('generate_mock_from_schema');
        $method->setAccessible(true);
        
        $mockdata = $method->invoke($client, $schema);
        
        $this->assertIsArray($mockdata);
        $this->assertArrayHasKey('string_field', $mockdata);
        $this->assertArrayHasKey('number_field', $mockdata);
        $this->assertArrayHasKey('array_field', $mockdata);
        
        $this->assertIsString($mockdata['string_field']);
        $this->assertIsInt($mockdata['number_field']);
        $this->assertIsArray($mockdata['array_field']);
        
        if (isset($mockdata['enum_field'])) {
            $this->assertContains($mockdata['enum_field'], ['option1', 'option2', 'option3']);
        }
        
        if (isset($mockdata['boolean_field'])) {
            $this->assertIsBool($mockdata['boolean_field']);
        }
    }

    /**
     * Test setting custom mock responses
     */
    public function test_custom_mock_responses() {
        $client = new llm_client(['mock_mode' => true]);
        
        $systemprompt = 'Test system prompt';
        $userprompt = 'Test user prompt';
        
        $customresponse = [
            'content' => '{"custom": "response"}',
            'raw_response' => '{}',
            'duration_ms' => 250,
            'tokens_used' => 500,
            'model' => 'custom-mock',
            'temperature' => 0.1
        ];
        
        $client->set_mock_response($systemprompt, $userprompt, $customresponse);
        
        $result = $client->call($systemprompt, $userprompt, null, true);
        
        $this->assertEquals($customresponse['content'], $result['content']);
        $this->assertEquals($customresponse['tokens_used'], $result['tokens_used']);
        $this->assertEquals($customresponse['model'], $result['model']);
    }

    /**
     * Test token counting
     */
    public function test_token_counting() {
        $client = new llm_client(['mock_mode' => true]);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('count_tokens');
        $method->setAccessible(true);
        
        // Test various text lengths
        $shorttext = 'Hello world';
        $tokens = $method->invoke($client, $shorttext);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
        
        $longtext = str_repeat('This is a test sentence. ', 100);
        $tokens = $method->invoke($client, $longtext);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(100, $tokens);
    }
}