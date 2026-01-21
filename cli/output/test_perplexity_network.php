<?php
/**
 * Perplexity Network Diagnostic Test
 * 
 * Tests network connectivity to api.perplexity.ai to isolate timeout issues
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

echo "=== Perplexity Network Diagnostic Test ===\n\n";

/**
 * Format time in milliseconds
 */
function format_time($seconds) {
    return round($seconds * 1000, 2) . 'ms';
}

/**
 * Interpret cURL errno
 */
function interpret_errno($errno) {
    switch ($errno) {
        case 0: return "No error";
        case 6: return "Could not resolve host";
        case 7: return "Failed to connect to host";
        case 28: return "Operation timed out";
        case 35: return "SSL connect error";
        case 51: return "Peer certificate cannot be authenticated";
        case 52: return "Got nothing from server";
        case 56: return "Failure in receiving network data";
        default: return "Unknown error ($errno)";
    }
}

try {
    echo "🌐 Testing basic connectivity to api.perplexity.ai\n";
    echo "📍 Target: https://api.perplexity.ai\n";
    echo "🔗 Method: HEAD request\n";
    echo "⏱️  Timeout: 10 seconds\n\n";
    
    // Initialize cURL for HEAD request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.perplexity.ai",
        CURLOPT_NOBODY => true,                    // HEAD request only
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE => true,
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => "Rubi/1.0 Network Diagnostic",
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);
    
    echo "🚀 Executing HEAD request...\n";
    $start_time = microtime(true);
    
    $response = curl_exec($ch);
    $elapsed = microtime(true) - $start_time;
    
    // Get detailed connection info
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $primary_ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    $connect_time = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $namelookup_time = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
    $pretransfer_time = curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    
    curl_close($ch);
    
    echo "\n📊 Connection Details:\n";
    echo "⏱️  Total elapsed: " . format_time($elapsed) . "\n";
    echo "🔢 HTTP Code: $http_code\n";
    echo "🌐 Resolved IP: " . ($primary_ip ?: 'N/A') . "\n";
    echo "🔍 DNS lookup time: " . format_time($namelookup_time) . "\n";
    echo "🔗 Connect time: " . format_time($connect_time) . "\n";
    echo "🔒 SSL handshake time: " . format_time($pretransfer_time - $connect_time) . "\n";
    echo "📡 Total time: " . format_time($total_time) . "\n";
    echo "❗ cURL errno: $errno\n";
    echo "💬 cURL error: " . ($error ?: 'None') . "\n";
    
    if ($errno > 0) {
        echo "🔍 Error interpretation: " . interpret_errno($errno) . "\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    // Analyze results
    if ($http_code >= 200 && $http_code < 400) {
        echo "✅ CONNECTION SUCCESSFUL\n\n";
        echo "🎉 Network path to api.perplexity.ai is working!\n";
        echo "📋 Analysis:\n";
        echo "  - DNS resolution: ✅ Working (" . format_time($namelookup_time) . ")\n";
        echo "  - TCP connection: ✅ Working (" . format_time($connect_time) . ")\n";
        echo "  - SSL handshake: ✅ Working (" . format_time($pretransfer_time - $connect_time) . ")\n";
        echo "  - HTTP response: ✅ Working (HTTP $http_code)\n";
        echo "  - Server IP: $primary_ip\n\n";
        echo "💡 Next steps:\n";
        echo "  - The basic connectivity works fine\n";
        echo "  - The timeout issue is likely in the POST request or payload\n";
        echo "  - Check if it's related to request size, headers, or API rate limiting\n";
        
    } else if ($errno == 28) {
        echo "❌ CONNECTION TIMEOUT\n\n";
        echo "⏰ The connection timed out after 10 seconds\n";
        echo "📋 Analysis:\n";
        if ($namelookup_time > 0) {
            echo "  - DNS resolution: ✅ Working (" . format_time($namelookup_time) . ")\n";
            echo "  - Server IP: $primary_ip\n";
        } else {
            echo "  - DNS resolution: ❌ Failed or very slow\n";
        }
        
        if ($connect_time > 0) {
            echo "  - TCP connection: ⚠️  Slow (" . format_time($connect_time) . ")\n";
        } else {
            echo "  - TCP connection: ❌ Failed\n";
        }
        
        echo "\n💡 Possible causes:\n";
        echo "  - Firewall blocking outbound HTTPS (port 443)\n";
        echo "  - Network routing issues\n";
        echo "  - Server overloaded or rate limiting\n";
        echo "  - IPv6/IPv4 routing problems\n";
        
    } else if ($errno == 7) {
        echo "❌ HOST UNREACHABLE / BLOCKED\n\n";
        echo "🚫 Cannot connect to api.perplexity.ai\n";
        echo "📋 Analysis:\n";
        if ($namelookup_time > 0) {
            echo "  - DNS resolution: ✅ Working (" . format_time($namelookup_time) . ")\n";
            echo "  - Server IP: $primary_ip\n";
            echo "  - TCP connection: ❌ Refused or blocked\n";
        } else {
            echo "  - DNS resolution: ❌ Failed\n";
        }
        
        echo "\n💡 Possible causes:\n";
        echo "  - Firewall blocking outbound connections\n";
        echo "  - Network policy restrictions\n";
        echo "  - Server IP blocking your location\n";
        echo "  - Port 443 blocked\n";
        
    } else if ($errno == 6) {
        echo "❌ DNS RESOLUTION FAILED\n\n";
        echo "🔍 Cannot resolve api.perplexity.ai to IP address\n";
        echo "📋 Analysis:\n";
        echo "  - DNS resolution: ❌ Failed\n";
        echo "  - DNS lookup time: " . format_time($namelookup_time) . "\n";
        
        echo "\n💡 Possible causes:\n";
        echo "  - DNS server configuration issues\n";
        echo "  - Network DNS blocking\n";
        echo "  - Incorrect DNS settings\n";
        echo "  - Internet connectivity problems\n";
        
    } else {
        echo "⚠️  UNEXPECTED RESULT\n\n";
        echo "🔍 HTTP Code: $http_code\n";
        echo "❗ Error: " . interpret_errno($errno) . "\n";
        
        if ($primary_ip) {
            echo "🌐 Resolved to: $primary_ip\n";
        }
        
        echo "\n💡 This may indicate:\n";
        echo "  - SSL/TLS configuration issues\n";
        echo "  - Unusual network proxy behavior\n";
        echo "  - Server-side blocking\n";
        echo "  - Intermediate network filtering\n";
    }
    
    echo "\n📋 Raw Response Headers:\n";
    if ($response && strlen($response) > 0) {
        $headers = explode("\n", $response);
        foreach (array_slice($headers, 0, 10) as $header) {
            if (trim($header)) {
                echo "  " . trim($header) . "\n";
            }
        }
    } else {
        echo "  [NO RESPONSE RECEIVED]\n";
    }
    
    echo "\n🔧 Debugging Recommendations:\n";
    if ($errno == 0 && $http_code >= 200 && $http_code < 400) {
        echo "  ✅ Basic connectivity works - investigate POST request specifics\n";
        echo "  - Check payload size and complexity\n";
        echo "  - Test with simpler POST data\n";
        echo "  - Verify Content-Length headers\n";
    } else {
        echo "  🔧 Network connectivity issues detected\n";
        echo "  - Check firewall rules for outbound HTTPS\n";
        echo "  - Verify DNS configuration\n";
        echo "  - Test from different network location\n";
        echo "  - Contact network administrator\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTest complete.\n";