<?php
/**
 * Comprehensive API Keys Test Script for CustomerIntel
 * 
 * Tests both OpenAI and Perplexity API keys to ensure they are configured
 * correctly and can return valid HTTP responses.
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

echo "=== CustomerIntel API Keys Comprehensive Test ===\n\n";

$overall_success = true;

try {
    // Enable debug logging for detailed output
    config_helper::enable_debug();
    
    echo "🔍 Checking API key configuration...\n";
    config_helper::log_api_key_status('comprehensive_test');
    
    $status = config_helper::get_api_key_status();
    
    echo "\n📋 API Key Status Summary:\n";
    echo "Perplexity: " . ($status['perplexity']['configured'] ? "✅ Configured" : "❌ Not configured") . "\n";
    echo "OpenAI: " . ($status['openai']['configured'] ? "✅ Configured" : "❌ Not configured") . "\n\n";
    
    if (!$status['perplexity']['configured'] && !$status['openai']['configured']) {
        echo "❌ No API keys are configured!\n";
        echo "Please configure at least one API key in:\n";
        echo "Site administration > Plugins > Local plugins > Customer Intelligence Dashboard\n";
        exit(1);
    }
    
    // Test Perplexity API if configured
    if ($status['perplexity']['configured']) {
        echo "🌐 Testing Perplexity API...\n";
        echo "Key length: " . $status['perplexity']['key_length'] . " characters\n";
        echo "Masked key: " . $status['perplexity']['masked_key'] . "\n";
        
        $test_result = config_helper::test_api_key('perplexity');
        
        if ($test_result['status']) {
            echo "✅ Perplexity: " . $test_result['message'] . "\n\n";
        } else {
            echo "❌ Perplexity: " . $test_result['message'] . "\n\n";
            $overall_success = false;
        }
    } else {
        echo "⚠️  Perplexity API key not configured - skipping test\n\n";
    }
    
    // Test OpenAI API if configured
    if ($status['openai']['configured']) {
        echo "🤖 Testing OpenAI API...\n";
        echo "Key length: " . $status['openai']['key_length'] . " characters\n";
        echo "Masked key: " . $status['openai']['masked_key'] . "\n";
        
        $test_result = config_helper::test_api_key('openai');
        
        if ($test_result['status']) {
            echo "✅ OpenAI: " . $test_result['message'] . "\n\n";
        } else {
            echo "❌ OpenAI: " . $test_result['message'] . "\n\n";
            $overall_success = false;
        }
    } else {
        echo "⚠️  OpenAI API key not configured - skipping test\n\n";
    }
    
    // Summary
    echo "═══════════════════════════════════════════════\n";
    if ($overall_success) {
        echo "🎉 ALL TESTS PASSED!\n";
        echo "All configured API keys are working correctly.\n";
        echo "The system is ready to process requests.\n";
    } else {
        echo "❌ SOME TESTS FAILED!\n";
        echo "Please check the API key configuration and try again.\n";
        echo "Make sure your API keys are valid and have sufficient credits.\n";
    }
    echo "═══════════════════════════════════════════════\n";
    
    exit($overall_success ? 0 : 1);
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    exit(1);
}