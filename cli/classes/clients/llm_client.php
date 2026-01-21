<?php
/**
 * LLM Client - Handles LLM API interactions
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\clients;

defined('MOODLE_INTERNAL') || die();

/**
 * LLMClient class
 * 
 * Handles LLM API calls with strict JSON mode and low temperature
 * PRD Section 8.3 - Temperature ≤ 0.2 for extraction tasks
 */
class llm_client {
    
    /** @var string Provider type */
    protected $provider;
    
    /** @var string API key */
    protected $apikey;
    
    /** @var string API endpoint */
    protected $endpoint;
    
    /** @var float Temperature setting */
    protected $temperature;
    
    /** @var int Max tokens */
    protected $maxtokens;
    
    /** @var int Timeout in seconds */
    protected $timeout;
    
    /** @var bool Mock mode for testing */
    protected $mockmode;
    
    /** @var array Mock responses */
    protected $mockresponses = [];
    
    /**
     * Constructor
     * 
     * @param array $config Optional configuration override
     */
    public function __construct(array $config = []) {
        // Load from plugin settings
        $this->provider = $config['provider'] ?? get_config('local_customerintel', 'llm_provider');
        $this->apikey = $config['apikey'] ?? get_config('local_customerintel', 'llm_key');
        $this->temperature = floatval($config['temperature'] ?? get_config('local_customerintel', 'llm_temperature') ?? 0.2);
        $this->maxtokens = $config['maxtokens'] ?? 4096;
        $this->timeout = intval(get_config('local_customerintel', 'request_timeout') ?? 120);
        $this->mockmode = $config['mock_mode'] ?? get_config('local_customerintel', 'llm_mock_mode') ?? false;
        
        // Ensure temperature is within PRD limits
        if ($this->temperature > 0.2) {
            debugging('Temperature exceeds PRD limit of 0.2, capping at 0.2', DEBUG_DEVELOPER);
            $this->temperature = 0.2;
        }
        
        // Set endpoint based on provider
        $this->set_endpoint();
    }
    
    /**
     * Set API endpoint based on provider
     */
    protected function set_endpoint() {
        switch ($this->provider) {
            case 'openai-gpt4':
                $this->endpoint = 'https://api.openai.com/v1/chat/completions';
                break;
            case 'openai-gpt35':
                $this->endpoint = 'https://api.openai.com/v1/chat/completions';
                break;
            case 'anthropic-claude':
                $this->endpoint = 'https://api.anthropic.com/v1/messages';
                break;
            case 'custom':
                $this->endpoint = get_config('local_customerintel', 'llm_endpoint');
                break;
            default:
                throw new \moodle_exception('invalidllmprovider', 'local_customerintel');
        }
    }
    
    /**
     * Call LLM with strict JSON mode
     * 
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param array $schema JSON schema for validation
     * @param bool $jsonmode Enable JSON mode
     * @return array Response data
     * @throws \moodle_exception
     */
    public function call(string $systemprompt, string $userprompt, array $schema = null, bool $jsonmode = true): array {
        $starttime = microtime(true);
        
        // Mock mode for testing
        if ($this->mockmode) {
            return $this->generate_mock_response($systemprompt, $userprompt, $schema, $jsonmode);
        }
        
        // Build request based on provider
        $request = $this->build_request($systemprompt, $userprompt, $jsonmode);
        
        // Make API call
        $response = $this->make_request($request);
        
        // Parse response
        $result = $this->parse_response($response);
        
        // Calculate metrics
        $duration = (microtime(true) - $starttime) * 1000;
        $tokensused = $this->count_tokens($result['content']);
        
        return [
            'content' => $result['content'],
            'raw_response' => $response,
            'duration_ms' => $duration,
            'tokens_used' => $tokensused,
            'model' => $this->get_model_name(),
            'temperature' => $this->temperature
        ];
    }
    
    /**
     * Build request payload based on provider
     * 
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param bool $jsonmode Enable JSON mode
     * @return array Request payload
     */
    protected function build_request(string $systemprompt, string $userprompt, bool $jsonmode): array {
        if (strpos($this->provider, 'openai') === 0) {
            return $this->build_openai_request($systemprompt, $userprompt, $jsonmode);
        } elseif ($this->provider === 'anthropic-claude') {
            return $this->build_anthropic_request($systemprompt, $userprompt, $jsonmode);
        } else {
            // Generic format
            return $this->build_generic_request($systemprompt, $userprompt, $jsonmode);
        }
    }
    
    /**
     * Build OpenAI request
     */
    protected function build_openai_request(string $systemprompt, string $userprompt, bool $jsonmode): array {
        $request = [
            'model' => $this->get_model_name(),
            'messages' => [
                ['role' => 'system', 'content' => $systemprompt],
                ['role' => 'user', 'content' => $userprompt]
            ],
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxtokens
        ];
        
        // Enable JSON mode for GPT-4
        if ($jsonmode && $this->provider === 'openai-gpt4') {
            $request['response_format'] = ['type' => 'json_object'];
            // Append JSON instruction to system prompt
            $request['messages'][0]['content'] .= "\n\nYou must respond with valid JSON only.";
        }
        
        return $request;
    }
    
    /**
     * Build Anthropic request
     */
    protected function build_anthropic_request(string $systemprompt, string $userprompt, bool $jsonmode): array {
        $request = [
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => $this->maxtokens,
            'temperature' => $this->temperature,
            'system' => $systemprompt,
            'messages' => [
                ['role' => 'user', 'content' => $userprompt]
            ]
        ];
        
        if ($jsonmode) {
            $request['system'] .= "\n\nRespond with valid JSON only. Do not include any text outside the JSON structure.";
        }
        
        return $request;
    }
    
    /**
     * Build generic request
     */
    protected function build_generic_request(string $systemprompt, string $userprompt, bool $jsonmode): array {
        return [
            'prompt' => $systemprompt . "\n\n" . $userprompt,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxtokens,
            'json_mode' => $jsonmode
        ];
    }
    
    /**
     * Make HTTP request to LLM API
     * 
     * @param array $request Request payload
     * @return string Response body
     * @throws \moodle_exception
     */
    protected function make_request(array $request): string {
        $ch = curl_init($this->endpoint);
        
        // Set headers based on provider
        $headers = $this->get_headers();
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new \moodle_exception('llmrequestfailed', 'local_customerintel', '', $error);
        }
        
        if ($httpcode !== 200) {
            debugging('LLM API error: HTTP ' . $httpcode . ' - ' . $response, DEBUG_DEVELOPER);
            throw new \moodle_exception('llmhttperror', 'local_customerintel', '', $httpcode);
        }
        
        return $response;
    }
    
    /**
     * Get headers for API request
     * 
     * @return array Headers
     */
    protected function get_headers(): array {
        $headers = ['Content-Type: application/json'];
        
        if (strpos($this->provider, 'openai') === 0) {
            $headers[] = 'Authorization: Bearer ' . $this->apikey;
        } elseif ($this->provider === 'anthropic-claude') {
            $headers[] = 'x-api-key: ' . $this->apikey;
            $headers[] = 'anthropic-version: 2023-06-01';
        } else {
            $headers[] = 'Authorization: Bearer ' . $this->apikey;
        }
        
        return $headers;
    }
    
    /**
     * Parse response based on provider
     * 
     * @param string $response Raw response
     * @return array Parsed response
     * @throws \moodle_exception
     */
    protected function parse_response(string $response): array {
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('llminvalidjson', 'local_customerintel');
        }
        
        if (strpos($this->provider, 'openai') === 0) {
            if (!isset($data['choices'][0]['message']['content'])) {
                throw new \moodle_exception('llmnoresponse', 'local_customerintel');
            }
            return [
                'content' => $data['choices'][0]['message']['content'],
                'usage' => $data['usage'] ?? []
            ];
        } elseif ($this->provider === 'anthropic-claude') {
            if (!isset($data['content'][0]['text'])) {
                throw new \moodle_exception('llmnoresponse', 'local_customerintel');
            }
            return [
                'content' => $data['content'][0]['text'],
                'usage' => ['total_tokens' => $data['usage']['input_tokens'] + $data['usage']['output_tokens']]
            ];
        } else {
            // Generic response format
            return [
                'content' => $data['response'] ?? $data['content'] ?? '',
                'usage' => $data['usage'] ?? []
            ];
        }
    }
    
    /**
     * Get model name based on provider
     * 
     * @return string Model name
     */
    protected function get_model_name(): string {
        switch ($this->provider) {
            case 'openai-gpt4':
                return 'gpt-4-turbo-preview';
            case 'openai-gpt35':
                return 'gpt-3.5-turbo';
            case 'anthropic-claude':
                return 'claude-3-sonnet-20240229';
            default:
                return 'custom-model';
        }
    }
    
    /**
     * Count tokens in text (rough estimate)
     * 
     * @param string $text Text to count
     * @return int Estimated token count
     */
    protected function count_tokens(string $text): int {
        // Rough estimate: ~4 characters per token
        return intval(strlen($text) / 4);
    }
    
    /**
     * Call with retry logic
     * 
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param array $schema JSON schema
     * @param int $maxretries Maximum retry attempts
     * @return array Response
     * @throws \moodle_exception
     */
    public function call_with_retry(string $systemprompt, string $userprompt, array $schema = null, int $maxretries = 3): array {
        $lastexception = null;
        
        for ($i = 0; $i < $maxretries; $i++) {
            try {
                return $this->call($systemprompt, $userprompt, $schema);
            } catch (\moodle_exception $e) {
                $lastexception = $e;
                
                // Exponential backoff
                if ($i < $maxretries - 1) {
                    $delay = pow(2, $i) * 1000000; // microseconds
                    usleep($delay);
                }
            }
        }
        
        throw $lastexception;
    }
    
    /**
     * Extract structured data for NB
     * 
     * @param string $nbcode NB code
     * @param string $prompt Extraction prompt
     * @param array $contextchunks Context chunks
     * @return array Extracted data
     * @throws \moodle_exception
     */
    public function extract(string $nbcode, string $prompt, array $contextchunks): array {
        // Build context from chunks
        $context = "";
        foreach ($contextchunks as $chunk) {
            if (is_array($chunk)) {
                $context .= $chunk['text'] ?? $chunk['content'] ?? '';
            } else {
                $context .= $chunk;
            }
            $context .= "\n\n";
        }
        
        // Build system prompt for extraction
        $systemprompt = "You are an expert analyst performing structured data extraction for $nbcode.\n";
        $systemprompt .= "Extract information according to the provided schema.\n";
        $systemprompt .= "Be precise, comprehensive, and include citations for all facts.";
        
        // Combine prompt with context
        $userprompt = $prompt . "\n\nContext:\n" . $context;
        
        // Load schema for NB
        global $CFG;
        $schemafile = $CFG->dirroot . '/local/customerintel/schemas/' . strtolower($nbcode) . '.json';
        $schema = null;
        if (file_exists($schemafile)) {
            $schemajson = file_get_contents($schemafile);
            $schema = json_decode($schemajson, true);
        }
        
        // Call with JSON mode enabled
        return $this->call($systemprompt, $userprompt, $schema, true);
    }
    
    /**
     * Validate JSON against NB schema
     * 
     * @param string $nbcode NB code
     * @param array $payload JSON payload
     * @return array Validation result
     */
    public function validate_json(string $nbcode, array $payload): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/customerintel/classes/helpers/json_validator.php');
        
        // Load schema
        $schemafile = $CFG->dirroot . '/local/customerintel/schemas/' . strtolower($nbcode) . '.json';
        if (!file_exists($schemafile)) {
            return ['valid' => false, 'errors' => ['Schema file not found for ' . $nbcode]];
        }
        
        $schemajson = file_get_contents($schemafile);
        $schema = json_decode($schemajson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['valid' => false, 'errors' => ['Invalid schema file for ' . $nbcode]];
        }
        
        // Validate
        return \local_customerintel\helpers\json_validator::validate($payload, $schema);
    }
    
    /**
     * Repair invalid JSON to match schema
     * 
     * @param string $nbcode NB code
     * @param array $payload Invalid payload
     * @return array|null Repaired payload or null
     */
    public function repair_invalid_json(string $nbcode, array $payload): ?array {
        global $CFG;
        require_once($CFG->dirroot . '/local/customerintel/classes/helpers/json_validator.php');
        
        // Load schema
        $schemafile = $CFG->dirroot . '/local/customerintel/schemas/' . strtolower($nbcode) . '.json';
        if (!file_exists($schemafile)) {
            return null;
        }
        
        $schemajson = file_get_contents($schemafile);
        $schema = json_decode($schemajson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Attempt repair
        $repaired = \local_customerintel\helpers\json_validator::repair($payload, $schema);
        
        // Validate repaired data
        if ($repaired !== null) {
            $validation = \local_customerintel\helpers\json_validator::validate($repaired, $schema);
            if ($validation['valid']) {
                return $repaired;
            }
        }
        
        return null;
    }
    
    /**
     * Generate mock response for testing
     * 
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param array $schema JSON schema
     * @param bool $jsonmode JSON mode flag
     * @return array Mock response
     */
    protected function generate_mock_response(string $systemprompt, string $userprompt, ?array $schema, bool $jsonmode): array {
        // Check for preset mock response
        $key = md5($systemprompt . $userprompt);
        if (isset($this->mockresponses[$key])) {
            return $this->mockresponses[$key];
        }
        
        // Generate predictable mock data based on schema
        $content = '';
        if ($jsonmode && $schema !== null) {
            $mockdata = $this->generate_mock_from_schema($schema);
            $content = json_encode($mockdata);
        } else {
            $content = "Mock response for: " . substr($userprompt, 0, 100);
        }
        
        return [
            'content' => $content,
            'raw_response' => json_encode(['mock' => true]),
            'duration_ms' => rand(100, 500),
            'tokens_used' => rand(100, 1000),
            'model' => 'mock-model',
            'temperature' => $this->temperature
        ];
    }
    
    /**
     * Generate mock data from schema
     * 
     * @param array $schema JSON schema
     * @return array Mock data
     */
    protected function generate_mock_from_schema(array $schema): array {
        if (!isset($schema['type']) || $schema['type'] !== 'object') {
            return [];
        }
        
        $mockdata = [];
        
        // Generate required fields
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (isset($schema['properties'][$field])) {
                    $mockdata[$field] = $this->generate_mock_value($schema['properties'][$field]);
                }
            }
        }
        
        // Generate optional fields (50% chance)
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $field => $fieldschema) {
                if (!isset($mockdata[$field]) && rand(0, 1) === 1) {
                    $mockdata[$field] = $this->generate_mock_value($fieldschema);
                }
            }
        }
        
        return $mockdata;
    }
    
    /**
     * Generate mock value for schema type
     * 
     * @param array $schema Field schema
     * @return mixed Mock value
     */
    protected function generate_mock_value(array $schema) {
        $type = $schema['type'] ?? 'string';
        
        switch ($type) {
            case 'string':
                if (isset($schema['enum'])) {
                    return $schema['enum'][array_rand($schema['enum'])];
                }
                return 'Mock ' . uniqid();
                
            case 'integer':
                $min = $schema['minimum'] ?? 0;
                $max = $schema['maximum'] ?? 100;
                return rand($min, $max);
                
            case 'number':
                $min = $schema['minimum'] ?? 0;
                $max = $schema['maximum'] ?? 100;
                return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
                
            case 'boolean':
                return rand(0, 1) === 1;
                
            case 'array':
                $items = [];
                $count = rand(1, 3);
                if (isset($schema['items'])) {
                    for ($i = 0; $i < $count; $i++) {
                        $items[] = $this->generate_mock_value($schema['items']);
                    }
                }
                return $items;
                
            case 'object':
                return $this->generate_mock_from_schema($schema);
                
            default:
                return null;
        }
    }
    
    /**
     * Set mock response for testing
     * 
     * @param string $systemprompt System prompt
     * @param string $userprompt User prompt
     * @param array $response Mock response
     */
    public function set_mock_response(string $systemprompt, string $userprompt, array $response): void {
        $key = md5($systemprompt . $userprompt);
        $this->mockresponses[$key] = $response;
    }
}