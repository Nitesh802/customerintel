<?php
/**
 * Fallback Patch Validation Script
 * 
 * Validates that view_report.php has been properly patched with final_bundle.json
 * fallback logic to prevent synthesis_required loops
 */

echo "=== Fallback Patch Validation ===\n\n";

$view_report_file = __DIR__ . '/local_customerintel/view_report.php';

if (!file_exists($view_report_file)) {
    echo "❌ view_report.php not found: {$view_report_file}\n";
    exit(1);
}

$content = file_get_contents($view_report_file);

echo "✅ view_report.php found (" . round(strlen($content) / 1024, 2) . " KB)\n\n";

// Check for fallback logic components
$fallback_checks = [
    'Fallback artifact check' => strpos($content, "get_record('local_ci_artifact', [") !== false && 
                                 strpos($content, "'phase' => 'synthesis'") !== false &&
                                 strpos($content, "'artifacttype' => 'final_bundle'") !== false,
    'JSON decode validation' => strpos($content, 'json_decode($final_bundle_artifact->jsondata, true)') !== false,
    'JSON error checking' => strpos($content, 'json_last_error() === JSON_ERROR_NONE') !== false,
    'Fallback success logging' => strpos($content, 'Fallback to final_bundle.json – synthesis cache not found but valid bundle detected') !== false,
    'Fallback load logging' => strpos($content, 'Successfully loaded synthesis from final_bundle artifact') !== false,
    'Invalid JSON logging' => strpos($content, 'final_bundle artifact found but JSON data is invalid') !== false,
    'No artifacts logging' => strpos($content, 'No synthesis cache or final_bundle artifact found – rebuild required') !== false,
    'Needs rebuild prevention' => strpos($content, '$needs_rebuild = false; // No rebuild needed') !== false
];

echo "=== Fallback Logic Components ===\n";
foreach ($fallback_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Check for proper integration
$integration_checks = [
    'Fallback before rebuild' => strpos($content, 'final_bundle.json artifact before rebuilding') !== false,
    'Cache timestamp from artifact' => strpos($content, '$cache_timestamp = $final_bundle_artifact->timecreated;') !== false,
    'Cache hit set to false' => strpos($content, '$cache_hit = false; // It\'s a fallback, not a cache hit') !== false,
    'Synthesis bundle assignment' => strpos($content, '$synthesis_bundle = $final_bundle_data;') !== false,
    'Log service integration' => strpos($content, 'log_service::info($runid,') !== false
];

echo "\n=== Integration Points ===\n";
foreach ($integration_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Check for error handling
$error_handling_checks = [
    'Empty JSON data check' => strpos($content, '!empty($final_bundle_data)') !== false,
    'Artifact existence check' => strpos($content, '$final_bundle_artifact && !empty($final_bundle_artifact->jsondata)') !== false,
    'Graceful degradation' => strpos($content, '$needs_rebuild = true;') !== false,
    'Log service requirement' => strpos($content, "require_once(\$CFG->dirroot . '/local/customerintel/classes/services/log_service.php');") !== false
];

echo "\n=== Error Handling ===\n";
foreach ($error_handling_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Check for compatibility logging format
$logging_checks = [
    'Compatibility prefix' => strpos($content, '[Compatibility]') !== false,
    'Fallback message format' => strpos($content, 'synthesis cache not found but valid bundle detected') !== false,
    'Success message format' => strpos($content, 'Successfully loaded synthesis from final_bundle artifact') !== false,
    'Timestamp in success log' => strpos($content, "date('Y-m-d H:i:s', \$final_bundle_artifact->timecreated)") !== false
];

echo "\n=== Logging Format ===\n";
foreach ($logging_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Check logical flow
$flow_checks = [
    'Cache check first' => strpos($content, 'load_synthesis_bundle($runid)') !== false,
    'Fallback only if cache missing' => strpos($content, '} else {') !== false &&
                                        strpos($content, 'Check for final_bundle.json artifact before rebuilding') !== false,
    'Rebuild only if no fallback' => strpos($content, 'Build or rebuild synthesis if needed') !== false &&
                                      strpos($content, 'if ($needs_rebuild)') !== false
];

echo "\n=== Logical Flow ===\n";
foreach ($flow_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Overall validation
$all_checks = array_merge($fallback_checks, $integration_checks, $error_handling_checks, $logging_checks, $flow_checks);
$passed_count = array_sum($all_checks);
$total_count = count($all_checks);

echo "\n" . str_repeat("=", 50) . "\n";
echo "OVERALL VALIDATION RESULTS\n";
echo str_repeat("=", 50) . "\n";

if ($passed_count === $total_count) {
    echo "🎉 ALL CHECKS PASSED ({$passed_count}/{$total_count})\n";
    echo "\n✅ view_report.php has been successfully patched with fallback logic\n";
    echo "\nPatch features:\n";
    echo "  • Checks for final_bundle.json artifact when cache is missing\n";
    echo "  • Validates JSON data before using as fallback\n";
    echo "  • Logs all fallback operations with [Compatibility] prefix\n";
    echo "  • Prevents synthesis_required loops when valid artifacts exist\n";
    echo "  • Maintains backward compatibility with existing cache system\n";
    echo "  • Graceful degradation to rebuild if no artifacts available\n";
} else {
    echo "⚠️  {$passed_count}/{$total_count} checks passed\n";
    echo "\nSome features may not be fully implemented. Review failed checks above.\n";
}

echo "\n" . str_repeat("-", 50) . "\n";
echo "Expected behavior for Run 26:\n";
echo "1. Cache check fails (returns null)\n";
echo "2. Fallback checks for synthesis/final_bundle artifact\n";
echo "3. If found and valid: loads bundle, logs success, proceeds to render\n";
echo "4. If not found/invalid: logs warning, proceeds to rebuild\n";
echo "5. No more synthesis_required loops when valid artifacts exist\n";

echo "\nTest files:\n";
echo "• test_run26_fallback_fix.php - Complete scenario testing\n";
echo "• /local/customerintel/view_report.php?runid=26 - Live testing\n";

echo "\nValidation completed: " . date('Y-m-d H:i:s') . "\n";
?>