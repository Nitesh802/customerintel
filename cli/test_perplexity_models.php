<?php
/**
 * Perplexity API Models Test
 * 
 * Tests whether the Perplexity API key is valid for server-side API access
 * by checking the /models endpoint.
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

echo "=== Perplexity API Models Test ===\n\n";

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

try {
    // Get API key from configuration
    $api_key = get_config('local_customerintel', 'perplexityapikey');
    
    if (empty($api_key) || strlen(trim($api_key)) < 10) {
        echo "❌ No valid Perplexity API key found in configuration\n";
        echo "Please configure the API key in plugin settings.\n";
        exit(1);
    }
    
    echo "🔍 Testing API key: " . mask_key($api_key) . "\n";
    echo "📍 Endpoint: https://api.perplexity.ai/models\n";
    echo "🔗 Method: GET\n\n";
    
    // Prepare the request
    $endpoint = 'https://api.perplexity.ai/models';
    $headers = [
        "Authorization: Bearer " . trim($api_key),
        "Content-Type: application/json",
        "User-Agent: Rubi/1.0 (+https://rubi.digital)"
    ];
    
    echo "📋 Headers being sent:\n";
    foreach ($headers as $header) {
        if (strpos($header, 'Authorization:') === 0) {
            echo "  " . substr($header, 0, 20) . "..." . substr($header, -4) . "\n";
        } else {
            echo "  $header\n";
        }
    }
    echo "\n";
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    echo "🚀 Sending request...\n";
    $start_time = microtime(true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $elapsed = round(microtime(true) - $start_time, 2);
    
    echo "\n📊 Response Details:\n";
    echo "⏱️  Time: {$elapsed}s\n";
    echo "🔢 HTTP Code: $http_code\n";
    
    if ($error) {
        echo "❌ cURL Error: $error\n";
    } else {
        echo "✅ cURL Error: None\n";
    }
    
    echo "\n📄 Raw Response (first 300 chars):\n";
    if ($response) {
        $response_preview = substr($response, 0, 300);
        echo "$response_preview\n";
        if (strlen($response) > 300) {
            echo "... (truncated)\n";
        }
    } else {
        echo "[NO RESPONSE BODY]\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    // Analyze the results
    if ($http_code === 200) {
        echo "🎉 SUCCESS: API key is valid for server-side API access!\n\n";
        
        // Try to parse and show available models
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            echo "📋 Available Models:\n";
            foreach ($data['data'] as $model) {
                $model_id = $model['id'] ?? 'unknown';
                echo "  - $model_id\n";
                
                // Check specifically for sonar-pro
                if ($model_id === 'sonar-pro') {
                    echo "    ✅ sonar-pro is available!\n";
                }
            }
        } else {
            echo "⚠️  Could not parse models list from response\n";
        }
        
    } else if ($http_code === 401) {
        echo "❌ FAILED: Perplexity key is web-only or invalid for API access.\n\n";
        echo "Possible causes:\n";
        echo "  - Key is for web interface only (not API access)\n";
        echo "  - Key has expired or been revoked\n";
        echo "  - Key requires additional permissions/billing setup\n";
        echo "  - Account needs API access upgrade\n";
        
    } else if ($http_code === 403) {
        echo "❌ FAILED: API key valid but lacks permission for models endpoint.\n\n";
        echo "Possible causes:\n";
        echo "  - Account plan doesn't include API access\n";
        echo "  - API quota exceeded\n";
        echo "  - IP address restrictions\n";
        
    } else {
        echo "⚠️  UNEXPECTED: HTTP $http_code response.\n\n";
        echo "This may indicate:\n";
        echo "  - Service temporarily unavailable\n";
        echo "  - Rate limiting (429)\n";
        echo "  - Server error (5xx)\n";
        echo "  - Network/proxy issues\n";
    }
    
    echo "\n💡 Next Steps:\n";
    if ($http_code === 200) {
        echo "  - The API key works! Check ChatCompletion endpoint usage\n";
        echo "  - Verify model 'sonar-pro' is in the list above\n";
    } else {
        echo "  - Check Perplexity account settings and billing\n";
        echo "  - Verify API access is enabled for your account\n";
        echo "  - Consider generating a new API key\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTest complete.\n";