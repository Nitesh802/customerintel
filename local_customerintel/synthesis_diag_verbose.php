<?php
/**
 * Synthesis Diagnostic Verbose Endpoint
 * 
 * Admin-only diagnostic tool for detailed synthesis build analysis
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/services/synthesis_engine.php');

// Require admin access
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set content type to JSON
header('Content-Type: application/json');

// Get runid parameter
$runid = optional_param('runid', 0, PARAM_INT);

if (empty($runid)) {
    echo json_encode([
        'error' => 'Missing required parameter: runid',
        'usage' => 'synthesis_diag_verbose.php?runid=N'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    // Create synthesis engine instance
    $engine = new \local_customerintel\services\synthesis_engine();
    
    // Attempt to build the report
    $result = $engine->build_report($runid);
    
    // Success - analyze the result structure
    $sections = $result['html'] ? 'present' : 'missing';
    $voice_report = json_decode($result['voice_report'], true);
    $selfcheck_report = json_decode($result['selfcheck_report'], true);
    $citations = $result['citations'];
    
    // Extract section data from HTML for analysis
    $html_content = $result['html'];
    $exec_summary_len = 0;
    $overlooked_bullets = 0;
    $blueprints_count = 0;
    $convergence_len = 0;
    
    // Parse HTML to get section lengths
    if (preg_match('/<h2>Executive Summary<\/h2><p>(.*?)<\/p>/s', $html_content, $matches)) {
        $exec_summary_len = str_word_count(strip_tags($matches[1]));
    }
    
    if (preg_match_all('/<li>(.*?)<\/li>/s', $html_content, $matches)) {
        $overlooked_bullets = count($matches[1]);
    }
    
    if (preg_match_all('/<h3>(.*?)<\/h3>/s', $html_content, $matches)) {
        $blueprints_count = count($matches[1]);
    }
    
    if (preg_match('/<h2>Convergence Insight<\/h2><p>(.*?)<\/p>/s', $html_content, $matches)) {
        $convergence_len = str_word_count(strip_tags($matches[1]));
    }
    
    // Success response
    echo json_encode([
        'runid' => $runid,
        'status' => 'SYNTHESIS_OK',
        'sections' => [
            'exec_summary_len' => $exec_summary_len,
            'overlooked_bullets' => $overlooked_bullets,
            'blueprints_count' => $blueprints_count,
            'convergence_len' => $convergence_len
        ],
        'voice_keys' => is_array($voice_report) && isset($voice_report['sections']) ? array_keys($voice_report['sections']) : [],
        'citations_count' => is_array($citations) ? count($citations) : 0,
        'debug_info' => [
            'html_length' => strlen($html_content),
            'json_length' => strlen($result['json']),
            'voice_status' => is_array($voice_report) ? ($voice_report['status'] ?? 'unknown') : 'invalid',
            'selfcheck_status' => is_array($selfcheck_report) ? 'present' : 'missing'
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (\moodle_exception $e) {
    // Synthesis build failed - extract detailed error context
    $error_data = [
        'runid' => $runid,
        'status' => 'SYNTHESIS_FAILED',
        'error_code' => $e->errorcode,
        'error_message' => $e->getMessage()
    ];
    
    // Add detailed context if available
    if (isset($e->a) && is_array($e->a)) {
        $error_data = array_merge($error_data, $e->a);
    } elseif (isset($e->a) && is_object($e->a)) {
        $error_data = array_merge($error_data, (array)$e->a);
    }
    
    // Ensure we have the expected context fields
    $expected_fields = ['method', 'phase', 'section', 'nbkeys_seen', 'inner'];
    foreach ($expected_fields as $field) {
        if (!isset($error_data[$field])) {
            $error_data[$field] = '';
        }
    }
    
    echo json_encode($error_data, JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    // Unexpected error
    echo json_encode([
        'runid' => $runid,
        'status' => 'UNEXPECTED_ERROR',
        'error_message' => $e->getMessage(),
        'error_class' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'method' => 'unknown',
        'phase' => 'unknown',
        'section' => '',
        'nbkeys_seen' => [],
        'inner' => substr($e->getMessage(), 0, 240)
    ], JSON_PRETTY_PRINT);
}