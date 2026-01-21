<?php
/**
 * CustomerIntel API Keys Web Test
 * 
 * Web-accessible test for verifying API key configuration and connectivity.
 * Requires admin privileges and outputs plain text for browser viewing.
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include Moodle config and enforce security
require_once(__DIR__ . '/../../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set content type for plain text output
header('Content-Type: text/plain');

echo "=== CustomerIntel API Key Web Test ===\n\n";

/**
 * Mask API key for safe display
 * @param string $key The API key to mask
 * @return string Masked key showing first 6 and last 4 characters
 */
function mask_key($key) {
    if (empty($key) || strlen($key) < 10) {
        return '[INVALID]';
    }
    return substr($key, 0, 6) . '...' . substr($key, -4);
}

/**
 * Test OpenAI API connectivity
 * @param string $url The URL to test
 * @param string $key The API key for authorization
 * @return array Result with status and message
 */
function test_openai_connectivity($url, $key) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'User-Agent: CustomerIntel/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['status' => false, 'message' => "Connection error: $error"];
    }
    
    return ['status' => true, 'http_code' => $http_code, 'message' => "HTTP $http_code"];
}

/**
 * Test Perplexity API connectivity with POST request
 * @param string $key The API key for authorization
 * @return array Result with status and message
 */
function test_perplexity_connectivity($key) {
    $url = 'https://api.perplexity.ai/chat/completions';
    $payload = json_encode([
        'model' => 'sonar-pro',
        'messages' => [
            [
                'role' => 'user',
                'content' => 'ping'
            ]
        ],
        'max_tokens' => 5
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'User-Agent: CustomerIntel/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['status' => false, 'message' => "Connection error: $error"];
    }
    
    $response_preview = substr($response, 0, 200);
    return [
        'status' => true, 
        'http_code' => $http_code, 
        'message' => "HTTP $http_code",
        'response' => $response_preview
    ];
}

// Fetch API keys from configuration
$perplexitykey = get_config('local_customerintel', 'perplexityapikey');
$openaikey = get_config('local_customerintel', 'openaiapikey');

// Display key status
if (!empty($perplexitykey) && strlen(trim($perplexitykey)) > 10) {
    echo "✅ Perplexity key detected: " . mask_key($perplexitykey) . "\n";
    $has_perplexity = true;
} else {
    echo "❌ Perplexity key not found\n";
    $has_perplexity = false;
}

if (!empty($openaikey) && strlen(trim($openaikey)) > 10) {
    echo "✅ OpenAI key detected: " . mask_key($openaikey) . "\n";
    $has_openai = true;
} else {
    echo "❌ OpenAI key not found\n";
    $has_openai = false;
}

echo "\n=== Connectivity Test ===\n";

// Test Perplexity API if key is available
if ($has_perplexity) {
    try {
        $result = test_perplexity_connectivity($perplexitykey);
        if ($result['status']) {
            echo "Perplexity: " . $result['message'] . "\n";
            echo "Response: " . $result['response'] . "\n";
        } else {
            echo "Perplexity: " . $result['message'] . "\n";
        }
    } catch (Exception $e) {
        echo "Perplexity: Exception - " . $e->getMessage() . "\n";
    }
} else {
    echo "Perplexity: Skipped (no key)\n";
}

// Test OpenAI API if key is available
if ($has_openai) {
    try {
        $result = test_openai_connectivity('https://api.openai.com/v1/models', $openaikey);
        if ($result['status']) {
            echo "OpenAI: " . $result['message'] . "\n";
        } else {
            echo "OpenAI: " . $result['message'] . "\n";
        }
    } catch (Exception $e) {
        echo "OpenAI: Exception - " . $e->getMessage() . "\n";
    }
} else {
    echo "OpenAI: Skipped (no key)\n";
}

echo "\nTest complete.\n";