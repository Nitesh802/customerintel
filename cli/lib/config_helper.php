<?php
/**
 * Configuration Helper for CustomerIntel
 * 
 * Provides unified access to API keys and configuration settings
 * 
 * @package    local_customerintel
 * @copyright  2025 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\lib;

defined('MOODLE_INTERNAL') || die();

/**
 * Configuration helper class for API keys and settings
 */
class config_helper {
    
    /** @var array Cache for loaded configuration */
    private static $config_cache = null;
    
    /** @var bool Enable debug logging */
    private static $debug = false;
    
    /**
     * Get Perplexity API key
     * 
     * @return string|null The API key or null if not configured
     */
    public static function get_perplexity_api_key() {
        return self::get_config_value('perplexityapikey');
    }
    
    /**
     * Get OpenAI API key
     * 
     * @return string|null The API key or null if not configured
     */
    public static function get_openai_api_key() {
        return self::get_config_value('openaiapikey');
    }
    
    /**
     * Check if Perplexity API key is configured
     * 
     * @return bool True if configured and not empty
     */
    public static function has_perplexity_api_key() {
        $key = self::get_perplexity_api_key();
        return !empty($key) && strlen(trim($key)) > 10;
    }
    
    /**
     * Check if OpenAI API key is configured
     * 
     * @return bool True if configured and not empty
     */
    public static function has_openai_api_key() {
        $key = self::get_openai_api_key();
        return !empty($key) && strlen(trim($key)) > 10;
    }
    
    /**
     * Get masked version of API key for logging
     * 
     * @param string $key The API key to mask
     * @return string Masked key showing only first 4 and last 4 characters
     */
    public static function mask_api_key($key) {
        if (empty($key) || strlen($key) < 8) {
            return '[INVALID/EMPTY]';
        }
        
        $length = strlen($key);
        $visible_chars = 4;
        $mask_length = $length - (2 * $visible_chars);
        
        return substr($key, 0, $visible_chars) . str_repeat('*', $mask_length) . substr($key, -$visible_chars);
    }
    
    /**
     * Get configuration status for both API keys
     * 
     * @return array Status information for both API keys
     */
    public static function get_api_key_status() {
        $status = [
            'perplexity' => [
                'configured' => self::has_perplexity_api_key(),
                'key_length' => 0,
                'masked_key' => '[NOT CONFIGURED]'
            ],
            'openai' => [
                'configured' => self::has_openai_api_key(),
                'key_length' => 0,
                'masked_key' => '[NOT CONFIGURED]'
            ]
        ];
        
        if ($status['perplexity']['configured']) {
            $key = self::get_perplexity_api_key();
            $status['perplexity']['key_length'] = strlen($key);
            $status['perplexity']['masked_key'] = self::mask_api_key($key);
        }
        
        if ($status['openai']['configured']) {
            $key = self::get_openai_api_key();
            $status['openai']['key_length'] = strlen($key);
            $status['openai']['masked_key'] = self::mask_api_key($key);
        }
        
        return $status;
    }
    
    /**
     * Log API key status to system log
     * 
     * @param string $context Context string for logging (e.g., 'orchestrator_startup')
     */
    public static function log_api_key_status($context = 'config_check') {
        $status = self::get_api_key_status();
        
        if (self::$debug) {
            error_log("CustomerIntel [$context] API Key Status:");
            error_log("  Perplexity: " . ($status['perplexity']['configured'] ? 
                "CONFIGURED (length: {$status['perplexity']['key_length']}, masked: {$status['perplexity']['masked_key']})" : 
                "NOT CONFIGURED"));
            error_log("  OpenAI: " . ($status['openai']['configured'] ? 
                "CONFIGURED (length: {$status['openai']['key_length']}, masked: {$status['openai']['masked_key']})" : 
                "NOT CONFIGURED"));
        }
        
        // Also use Moodle's debugging if available
        if (function_exists('debugging')) {
            debugging("CustomerIntel [$context] Perplexity API: " . 
                ($status['perplexity']['configured'] ? "✓ Configured" : "✗ Not configured"), DEBUG_DEVELOPER);
            debugging("CustomerIntel [$context] OpenAI API: " . 
                ($status['openai']['configured'] ? "✓ Configured" : "✗ Not configured"), DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Validate API key format
     * 
     * @param string $key The API key to validate
     * @param string $provider The provider name ('perplexity' or 'openai')
     * @return bool True if key appears to be valid format
     */
    public static function validate_api_key($key, $provider) {
        if (empty($key) || !is_string($key)) {
            return false;
        }
        
        $key = trim($key);
        
        switch ($provider) {
            case 'perplexity':
                // Perplexity keys typically start with 'pplx-' and are about 50+ chars
                return (strpos($key, 'pplx-') === 0 && strlen($key) >= 40);
                
            case 'openai':
                // OpenAI keys typically start with 'sk-' and are about 50+ chars
                return (strpos($key, 'sk-') === 0 && strlen($key) >= 40);
                
            default:
                // Generic validation - just check minimum length
                return strlen($key) >= 20;
        }
    }
    
    /**
     * Test API key connectivity
     * 
     * @param string $provider The provider to test ('perplexity' or 'openai')
     * @return array Test result with status and message
     */
    public static function test_api_key($provider) {
        switch ($provider) {
            case 'perplexity':
                $key = self::get_perplexity_api_key();
                if (!$key) {
                    return ['status' => false, 'message' => 'No Perplexity API key configured'];
                }
                
                if (!self::validate_api_key($key, 'perplexity')) {
                    return ['status' => false, 'message' => 'Perplexity API key format appears invalid'];
                }
                
                return self::test_perplexity_api($key);
                
            case 'openai':
                $key = self::get_openai_api_key();
                if (!$key) {
                    return ['status' => false, 'message' => 'No OpenAI API key configured'];
                }
                
                if (!self::validate_api_key($key, 'openai')) {
                    return ['status' => false, 'message' => 'OpenAI API key format appears invalid'];
                }
                
                return self::test_openai_api($key);
                
            default:
                return ['status' => false, 'message' => 'Unknown provider'];
        }
    }
    
    /**
     * Enable debug logging
     */
    public static function enable_debug() {
        self::$debug = true;
    }
    
    /**
     * Get configuration value with caching
     * 
     * @param string $key The configuration key
     * @return mixed The configuration value
     */
    private static function get_config_value($key) {
        if (self::$config_cache === null) {
            self::load_config();
        }
        
        return isset(self::$config_cache->$key) ? self::$config_cache->$key : null;
    }
    
    /**
     * Load configuration from Moodle
     */
    private static function load_config() {
        self::$config_cache = get_config('local_customerintel');
    }
    
    /**
     * Clear configuration cache
     */
    public static function clear_cache() {
        self::$config_cache = null;
    }
    
    /**
     * Test Perplexity API connectivity
     * 
     * @param string $api_key The API key to test
     * @return array Test result
     */
    private static function test_perplexity_api($api_key) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.perplexity.ai/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'llama-3.1-sonar-small-128k-online',
                'messages' => [
                    ['role' => 'user', 'content' => 'test']
                ],
                'max_tokens' => 1,
                'temperature' => 0
            ])
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['status' => false, 'message' => 'Connection error: ' . $error];
        }
        
        if ($http_code === 200) {
            return ['status' => true, 'message' => 'API key is valid and working'];
        } else if ($http_code === 401) {
            return ['status' => false, 'message' => 'Invalid API key'];
        } else {
            return ['status' => false, 'message' => "API returned HTTP $http_code"];
        }
    }
    
    /**
     * Test OpenAI API connectivity
     * 
     * @param string $api_key The API key to test
     * @return array Test result
     */
    private static function test_openai_api($api_key) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/models',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['status' => false, 'message' => 'Connection error: ' . $error];
        }
        
        if ($http_code === 200) {
            return ['status' => true, 'message' => 'API key is valid and working'];
        } else if ($http_code === 401) {
            return ['status' => false, 'message' => 'Invalid API key'];
        } else {
            return ['status' => false, 'message' => "API returned HTTP $http_code"];
        }
    }
}