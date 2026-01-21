<?php
/**
 * OpenAI API Test Script for CustomerIntel
 * 
 * Tests connectivity and authentication with the OpenAI API
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

echo "=== CustomerIntel OpenAI API Test ===\n\n";

try {
    // Test API key configuration using config helper
    echo "🔍 Checking API key configuration...\n";
    config_helper::log_api_key_status('test_script');
    
    if (!config_helper::has_openai_api_key()) {
        echo "❌ No valid OpenAI API key found in plugin settings.\n";
        echo "Please configure the OpenAI API key in:\n";
        echo "Site administration > Plugins > Local plugins > Customer Intelligence Dashboard\n";
        exit(1);
    }
    
    $api_key = config_helper::get_openai_api_key();
    echo "✅ API key found and validated\n";
    echo "Key length: " . strlen($api_key) . " characters\n";
    echo "Masked key: " . config_helper::mask_api_key($api_key) . "\n\n";
    
    // Test API connectivity
    echo "🌐 Testing API connectivity...\n";
    $test_result = config_helper::test_api_key('openai');
    
    if ($test_result['status']) {
        echo "✅ " . $test_result['message'] . "\n\n";
    } else {
        echo "❌ " . $test_result['message'] . "\n\n";
        exit(1);
    }
    
    // Test simple completion request
    echo "🤖 Testing completion request...\n";
    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    $payload = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Respond with exactly one word: "success"'
            ]
        ],
        'max_tokens' => 10,
        'temperature' => 0
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "❌ cURL error: $error\n";
        exit(1);
    }
    
    echo "📊 HTTP Response Code: $http_code\n";
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        
        if (!$data) {
            echo "❌ Failed to decode JSON response\n";
            echo "Raw response: " . substr($response, 0, 500) . "\n";
            exit(1);
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            $content = trim($data['choices'][0]['message']['content']);
            echo "✅ API Response: \"$content\"\n";
            echo "✅ Token usage: " . ($data['usage']['total_tokens'] ?? 'unknown') . " tokens\n";
            echo "✅ Model used: " . ($data['model'] ?? 'unknown') . "\n\n";
            
            echo "🎉 OpenAI API test completed successfully!\n";
            echo "The API key is working and returning valid responses.\n";
        } else {
            echo "❌ Unexpected response structure\n";
            echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            exit(1);
        }
    } else {
        echo "❌ API request failed with HTTP $http_code\n";
        echo "Response: " . substr($response, 0, 1000) . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}