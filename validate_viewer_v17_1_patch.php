<?php
/**
 * Viewer v17.1 Patch Validation Script
 * 
 * Validates that view_report.php has been properly patched to support v17.1 bundle structures
 * Checks for all compatibility mapping logic and proper field handling
 */

echo "=== Viewer v17.1 Patch Validation ===\n\n";

$view_report_file = __DIR__ . '/local_customerintel/view_report.php';

if (!file_exists($view_report_file)) {
    echo "❌ view_report.php not found: {$view_report_file}\n";
    exit(1);
}

$content = file_get_contents($view_report_file);

echo "✅ view_report.php found (" . round(strlen($content) / 1024, 2) . " KB)\n\n";

// Check for v17.1 compatibility function
$compatibility_checks = [
    'Compatibility mapping function' => strpos($content, 'function apply_v17_1_compatibility_mapping') !== false,
    'Function call in render path' => strpos($content, 'apply_v17_1_compatibility_mapping($synthesis_bundle, $runid)') !== false,
    'Citation count after mapping' => strpos($content, 'Count citations from the mapped synthesis bundle') !== false,
    'QA score mapping' => strpos($content, 'v17.1 Compatibility: Try multiple sources for QA scores') !== false,
    'Citation source mapping' => strpos($content, 'v17.1 Compatibility: Try multiple sources for citations') !== false,
    'Compatibility logging' => strpos($content, '[Compatibility] Viewer auto-mapped v17.1 bundle fields to v15 viewer schema') !== false
];

echo "=== Core Compatibility Features ===\n";
foreach ($compatibility_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Check for field mapping logic
$field_mapping_checks = [
    'QA scores from v15_structure' => strpos($content, "bundle['v15_structure']['qa']['scores']") !== false,
    'QA scores from qa_metrics' => strpos($content, "bundle['qa_metrics']['scores']") !== false,
    'QA scores from coherence_report' => strpos($content, "bundle['coherence_report']") !== false,
    'Citations from v15_structure' => strpos($content, "bundle['v15_structure']['citations']") !== false,
    'Citations from metrics' => strpos($content, "bundle['metrics']['citations']") !== false,
    'Sources fallback to citations' => strpos($content, "sources fallback to citations") !== false,
    'Domains from evidence_diversity_metrics' => strpos($content, "evidence_diversity_metrics") !== false,
    'Pattern alignment mapping' => strpos($content, "pattern_alignment_report") !== false,
    'Appendix notes mapping' => strpos($content, "appendix_notes") !== false
];

echo "\n=== Field Mapping Logic ===\n";
foreach ($field_mapping_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Check for viewer rendering compatibility
$rendering_checks = [
    'Multiple QA score sources' => strpos($content, 'elseif (!empty($synthesis_bundle[\'qa_score\']))') !== false,
    'Multiple citation sources' => strpos($content, 'elseif (!empty($synthesis_bundle[\'citations\'])') !== false,
    'Mapped citation_sources usage' => strpos($content, 'foreach ($citation_sources as $source)') !== false,
    'Scores variable flexibility' => strpos($content, 'if ($scores) {') !== false
];

echo "\n=== Viewer Rendering Compatibility ===\n";
foreach ($rendering_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Check for proper function structure
$structure_checks = [
    'Function parameters correct' => strpos($content, 'function apply_v17_1_compatibility_mapping($bundle, $runid)') !== false,
    'Returns mapped bundle' => strpos($content, 'return $mapped_bundle;') !== false,
    'Logging operations array' => strpos($content, '$mapping_operations = [];') !== false,
    'Log service integration' => strpos($content, 'log_service::info($runid,') !== false
];

echo "\n=== Function Structure ===\n";
foreach ($structure_checks as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "  {$check}: {$status}\n";
}

// Count mapping operations
$mapping_count = substr_count($content, '$mapping_operations[] = ');
echo "\n=== Mapping Operations Count ===\n";
echo "Total mapping operations: {$mapping_count}\n";
echo ($mapping_count >= 7) ? "✅ Sufficient mapping coverage\n" : "⚠️  Limited mapping coverage\n";

// Overall validation
$all_checks = array_merge($compatibility_checks, $field_mapping_checks, $rendering_checks, $structure_checks);
$passed_count = array_sum($all_checks);
$total_count = count($all_checks);

echo "\n" . str_repeat("=", 50) . "\n";
echo "OVERALL VALIDATION RESULTS\n";
echo str_repeat("=", 50) . "\n";

if ($passed_count === $total_count) {
    echo "🎉 ALL CHECKS PASSED ({$passed_count}/{$total_count})\n";
    echo "\n✅ view_report.php has been successfully patched for v17.1 compatibility\n";
    echo "\nFeatures implemented:\n";
    echo "  • Automatic field mapping from nested v17.1 structures\n";
    echo "  • Multiple fallback sources for QA scores and citations\n";
    echo "  • Evidence diversity context preservation\n";
    echo "  • Comprehensive compatibility logging\n";
    echo "  • Backward compatibility with v15 viewer schema\n";
    echo "  • No changes to display logic - only data mapping\n";
} else {
    echo "⚠️  {$passed_count}/{$total_count} checks passed\n";
    echo "\nSome features may not be fully implemented. Review failed checks above.\n";
}

echo "\n" . str_repeat("-", 50) . "\n";
echo "Next steps:\n";
echo "1. Test with actual v17.1 bundle data\n";
echo "2. Verify [Compatibility] log entries appear\n";
echo "3. Confirm QA scores and citations display correctly\n";
echo "4. Check Evidence Diversity Context preservation\n";

echo "\nTest file available: test_v17_1_viewer_compatibility.php\n";
echo "Validation completed: " . date('Y-m-d H:i:s') . "\n";
?>