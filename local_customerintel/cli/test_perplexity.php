<?php
/**
 * Perplexity API Test Script for CustomerIntel
 * 
 * Tests connectivity and authentication with the Perplexity API
 * using the stored plugin configuration.
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include Moodle config to access the environment
require_once('../../../config.php');
require_once(__DIR__ . '/../lib/config_helper.php');

use local_customerintel\lib\config_helper;

// Set content type for browser output
header('Content-Type: text/plain; charset=utf-8');

echo "=== CustomerIntel Perplexity API Test ===\n\n";

try {
    // Test API key configuration using config helper
    echo "🔍 Checking API key configuration...\n";
    config_helper::log_api_key_status('test_script');
    
    if (!config_helper::has_perplexity_api_key()) {
        echo "❌ No valid Perplexity API key found in plugin settings.\n";
        echo "Please configure the Perplexity API key in:\n";
        echo "Site administration > Plugins > Local plugins > Customer Intelligence Dashboard\n";
        exit(1);
    }
    
    $api_key = config_helper::get_perplexity_api_key();
    echo "✅ API key found and validated\n";
    echo "Key length: " . strlen($api_key) . " characters\n";
    echo "Masked key: " . config_helper::mask_api_key($api_key) . "\n\n";
    
    // Prepare API request
    $url = 'https://api.perplexity.ai/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    $request_body = json_encode([
        'model' => 'sonar-small-chat',
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Hello from the SuccessLab CustomerIntel test script.'
            ]
        ]
    ]);
    
    echo "📡 Testing connection to: $url\n";
    echo "Request model: sonar-small-chat\n";
    echo "Request body size: " . strlen($request_body) . " bytes\n\n";
    
    // Initialize cURL
    $ch = curl_init();
    
    if ($ch === false) {
        echo "❌ Failed to initialize cURL\n";
        echo "Note: If Moodle curl class is available, consider using that instead.\n";
        exit(1);
    }
    
    // Configure cURL options
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $request_body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Moodle CustomerIntel Plugin Test Script'
    ]);
    
    echo "🔄 Sending request to Perplexity API...\n";
    
    // Execute request
    $response = curl_exec($ch);
    
    if ($response === false) {
        $curl_error = curl_error($ch);
        curl_close($ch);
        echo "❌ cURL error: $curl_error\n";
        exit(1);
    }
    
    // Get response information
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    
    curl_close($ch);
    
    // Display results
    echo "\n=== API Response ===\n";
    echo "HTTP Status Code: $http_code\n";
    echo "Content Type: $content_type\n";
    echo "Response Time: " . round($total_time, 2) . " seconds\n";
    echo "Response Length: " . strlen($response) . " bytes\n\n";
    
    // Show response preview
    $response_preview = strlen($response) > 500 ? substr($response, 0, 500) . "..." : $response;
    echo "Response Body (first 500 chars):\n";
    echo $response_preview . "\n\n";
    
    // Determine success
    if ($http_code === 200) {
        echo "✅ Connection OK - Perplexity API responded successfully!\n";
        
        // Try to parse JSON response
        $json_response = json_decode($response, true);
        if ($json_response !== null) {
            echo "✅ Valid JSON response received\n";
            
            if (isset($json_response['choices'][0]['message']['content'])) {
                echo "✅ AI response content found\n";
                echo "AI Response: " . substr($json_response['choices'][0]['message']['content'], 0, 200) . "...\n";
            }
            
            if (isset($json_response['usage']['total_tokens'])) {
                echo "Tokens used: " . $json_response['usage']['total_tokens'] . "\n";
            }
        } else {
            echo "⚠️  Response is not valid JSON\n";
        }
    } else {
        echo "❌ API or network error (HTTP $http_code)\n";
        
        // Try to parse error response
        $error_response = json_decode($response, true);
        if ($error_response !== null && isset($error_response['error'])) {
            echo "Error details: " . $error_response['error']['message'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    if ($e instanceof moodle_exception) {
        echo "Moodle error code: " . $e->errorcode . "\n";
    }
} catch (Error $e) {
    echo "❌ PHP Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";

// Check if Moodle curl class is available
if (class_exists('curl')) {
    echo "Note: Moodle curl class is available for future use.\n";
} else {
    echo "Note: Moodle curl class is not available, using native PHP cURL.\n";
}