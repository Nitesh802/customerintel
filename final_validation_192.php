<?php
/**
 * Final Validation Script for Run 192 - Pattern Detection Fixes
 *
 * This script performs a complete end-to-end validation:
 * 1. Deletes ALL cached artifacts
 * 2. Forces fresh regeneration with fixed code
 * 3. Validates pattern detection finds patterns
 * 4. Validates report generation produces full content
 * 5. Provides comprehensive diagnostics
 *
 * Expected Results After Fixes:
 * - Pattern detection: >0 pressure themes, >0 levers, >0 signals
 * - Citation flow: ~253 citations through all stages
 * - Report size: >10KB HTML (not 224 bytes)
 * - Sections: 9-15 sections with actual content
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/final_validation_192.php'));
$PAGE->set_title("Final Validation - Run $runid Pattern Detection Fixes");

echo $OUTPUT->header();

?>
<style>
.validation { font-family: 'Segoe UI', Arial, sans-serif; max-width: 1400px; margin: 20px auto; }
.section { background: #fff; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 5px solid #007bff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.danger { background: #f8d7da; border-left-color: #dc3545; }
.info { background: #e7f3ff; border-left-color: #17a2b8; }
.progress { background: #f0f0f0; border-left-color: #6c757d; }
table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 15px 0; }
th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: 600; }
.metric { display: inline-block; background: #f8f9fa; padding: 8px 15px; margin: 5px; border-radius: 5px; border: 1px solid #dee2e6; }
.metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
.metric-label { font-size: 12px; color: #6c757d; text-transform: uppercase; }
.btn { display: inline-block; padding: 12px 30px; text-decoration: none; font-size: 16px; border-radius: 5px; font-weight: 600; cursor: pointer; border: none; }
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }
.btn-success { background: #28a745; color: white; }
.btn-success:hover { background: #218838; }
.btn-primary { background: #007bff; color: white; }
.btn-primary:hover { background: #0056b3; }
.status-icon { font-size: 20px; margin-right: 8px; }
.pass { color: #28a745; }
.fail { color: #dc3545; }
.warn { color: #ffc107; }
h1 { color: #333; margin-bottom: 10px; }
h2 { color: #555; margin-top: 0; font-size: 20px; }
h3 { color: #666; font-size: 16px; margin: 15px 0 10px 0; }
.code-sample { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; }
.dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0; }
.dashboard-card { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; text-align: center; }
</style>

<div class="validation">

<h1>🔬 Final Validation - Run <?= $runid ?> Pattern Detection Fixes</h1>

<div class="section info">
<h2>📋 Validation Overview</h2>
<p><strong>Purpose:</strong> Validate that pattern detection fixes work end-to-end</p>
<p><strong>Fixes Applied:</strong></p>
<ul>
<li>✅ analysis_engine.php line 322: 'sources' → 'citations'</li>
<li>✅ analysis_engine.php line 343: populate_citations uses 'citations' field</li>
<li>✅ collect_pressure_themes: Uses actual NB schema (pressure_factors, competitive_threats)</li>
<li>✅ collect_numeric_proofs: Uses actual NB schema (key_metrics)</li>
<li>✅ collect_executive_accountabilities: Uses actual NB schema (leadership_team)</li>
</ul>
<p><strong>Expected Results:</strong></p>
<ul>
<li>Pattern detection finds >0 patterns (not 0)</li>
<li>Citations flow through all stages (~253 citations)</li>
<li>Report generates with >10KB content (not 224 bytes)</li>
<li>9-15 sections with actual content</li>
</ul>
</div>

<?php

// ============================================================================
// STEP 1: INVENTORY - Show what will be deleted
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 1: Artifact Inventory</h2>";

$artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid]);
$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

echo "<p><strong>Artifacts to delete:</strong> " . count($artifacts) . "</p>";

if (!empty($artifacts)) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Phase</th><th>Type</th><th>Size</th><th>Created</th></tr>";

    foreach ($artifacts as $artifact) {
        $size = strlen($artifact->jsondata);
        echo "<tr>";
        echo "<td>{$artifact->id}</td>";
        echo "<td>{$artifact->phase}</td>";
        echo "<td>{$artifact->artifacttype}</td>";
        echo "<td>" . number_format($size) . " bytes</td>";
        echo "<td>" . date('Y-m-d H:i:s', $artifact->timecreated) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

if ($synthesis) {
    $html_size = strlen($synthesis->htmlcontent ?? '');
    $json_size = strlen($synthesis->jsoncontent ?? '');
    echo "<p><strong>Synthesis record:</strong> ID {$synthesis->id} (HTML: " . number_format($html_size) . " bytes, JSON: " . number_format($json_size) . " bytes)</p>";
}

echo "</div>";

// ============================================================================
// STEP 2: CONFIRMATION
// ============================================================================

if (!isset($_GET['confirm'])) {
    echo "<div class='section warning'>";
    echo "<h2>⚠️ Step 2: Confirmation Required</h2>";
    echo "<p><strong>This will:</strong></p>";
    echo "<ul>";
    echo "<li>Delete all " . count($artifacts) . " cached artifacts</li>";
    echo "<li>Delete synthesis record (if exists)</li>";
    echo "<li>Clear PHP OPcache</li>";
    echo "<li>Force complete regeneration from database NBs (~60-120 seconds)</li>";
    echo "<li>Test pattern detection with FIXED code</li>";
    echo "</ul>";
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='?confirm=yes' class='btn btn-danger'>🗑️ DELETE & VALIDATE</a>";
    echo "</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

// ============================================================================
// STEP 3: CLEANUP
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 3: Cleanup</h2>";

try {
    // Delete artifacts
    if (!empty($artifacts)) {
        $DB->delete_records('local_ci_artifact', ['runid' => $runid]);
        echo "<p>✅ Deleted " . count($artifacts) . " artifacts</p>";
    } else {
        echo "<p>ℹ️ No artifacts to delete</p>";
    }

    // Delete synthesis
    if ($synthesis) {
        $DB->delete_records('local_ci_synthesis', ['runid' => $runid]);
        echo "<p>✅ Deleted synthesis record ID {$synthesis->id}</p>";
    } else {
        echo "<p>ℹ️ No synthesis record to delete</p>";
    }

    // Clear OPcache
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "<p>✅ Cleared PHP OPcache</p>";
    }

    echo "<p style='color: #28a745; font-weight: bold; margin-top: 15px;'>✅ Cleanup Complete!</p>";

} catch (Exception $e) {
    echo "<p style='color: #dc3545;'>❌ Error during cleanup: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// ============================================================================
// STEP 4: VERIFY NBs READY
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 4: Verify NBs Available</h2>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode');
echo "<p><strong>NBs in database:</strong> " . count($nbs) . "/15</p>";

if (count($nbs) < 15) {
    echo "<p style='color: #dc3545;'>❌ Missing NBs - cannot regenerate synthesis</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// Count citations in NBs
$total_nb_citations = 0;
foreach ($nbs as $nb) {
    if (!empty($nb->citations)) {
        $citations = json_decode($nb->citations, true);
        if (is_array($citations)) {
            $total_nb_citations += count($citations);
        }
    }
}

echo "<p><strong>Total citations in NBs:</strong> {$total_nb_citations}</p>";

if ($total_nb_citations < 200) {
    echo "<div class='section warning'>";
    echo "<p style='color: #856404;'>⚠️ Warning: Expected ~253 citations, found {$total_nb_citations}</p>";
    echo "<p>Pattern detection may have limited data to work with.</p>";
    echo "</div>";
}

echo "<p style='color: #28a745; font-weight: bold;'>✅ NBs Ready for Synthesis</p>";
echo "</div>";

// ============================================================================
// STEP 5: REGENERATE WITH MONITORING
// ============================================================================

echo "<div class='section progress'>";
echo "<h2>Step 5: Regenerating Synthesis</h2>";
echo "<p>⏳ Starting regeneration... (this will take 60-120 seconds)</p>";
echo "<p style='font-size: 12px; color: #6c757d;'>Monitoring pattern detection, citation flow, and report generation...</p>";
flush();
ob_flush();

require_once(__DIR__ . '/classes/services/synthesis_engine.php');

$start = microtime(true);
$regeneration_success = false;
$regeneration_error = null;
$pattern_stats = null;
$citation_stats = null;

try {
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);

    // Enable debugging to capture pattern detection output
    $old_debug = $CFG->debug ?? 0;
    $CFG->debug = DEBUG_DEVELOPER;

    // Start output buffering to capture debug messages
    ob_start();

    $engine = new \local_customerintel\services\synthesis_engine($runid);
    $result = $engine->build_report($runid, true);  // force_regenerate = true

    // Capture debug output
    $debug_output = ob_get_clean();

    // Restore debug level
    $CFG->debug = $old_debug;

    $duration = microtime(true) - $start;

    // Extract pattern detection stats from debug output
    if (preg_match('/Pattern detection for run \d+: (\d+) pressure themes, (\d+) capability levers, (\d+) timing signals, (\d+) executives, (\d+) numeric proofs/', $debug_output, $matches)) {
        $pattern_stats = [
            'pressure_themes' => (int)$matches[1],
            'capability_levers' => (int)$matches[2],
            'timing_signals' => (int)$matches[3],
            'executives' => (int)$matches[4],
            'numeric_proofs' => (int)$matches[5]
        ];
    }

    // Extract citation stats
    if (preg_match('/Generated V15 Intelligence Playbook with (\d+) sections and (\d+) citations/', $debug_output, $matches)) {
        $citation_stats = [
            'sections' => (int)$matches[1],
            'citations' => (int)$matches[2]
        ];
    }

    $regeneration_success = true;

    echo "<p>✅ Regeneration completed in " . round($duration, 2) . " seconds</p>";

    // Show debug output
    echo "<h3>Debug Output:</h3>";
    echo "<div class='code-sample'>" . htmlspecialchars($debug_output) . "</div>";

} catch (Exception $e) {
    $regeneration_error = $e->getMessage();
    echo "<p style='color: #dc3545;'>❌ Error during regeneration: " . htmlspecialchars($regeneration_error) . "</p>";
    echo "<pre style='background: #f8d7da; padding: 10px; font-size: 11px; max-height: 300px; overflow-y: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>";

if (!$regeneration_success) {
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

// ============================================================================
// STEP 6: COMPREHENSIVE VALIDATION
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 6: Validation Results</h2>";

// Load regenerated data
$new_synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
$new_artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid]);

// Validation checks
$checks = [];

// A) Pattern Detection Validation
echo "<h3>A. Pattern Detection Validation</h3>";

if ($pattern_stats) {
    $total_patterns = $pattern_stats['pressure_themes'] + $pattern_stats['capability_levers'] +
                     $pattern_stats['timing_signals'] + $pattern_stats['numeric_proofs'];

    $checks['pattern_detection'] = $total_patterns > 0;

    echo "<div class='dashboard'>";

    echo "<div class='dashboard-card'>";
    echo "<div class='metric-value' style='color: " . ($pattern_stats['pressure_themes'] > 0 ? '#28a745' : '#dc3545') . "'>" . $pattern_stats['pressure_themes'] . "</div>";
    echo "<div class='metric-label'>Pressure Themes</div>";
    echo "</div>";

    echo "<div class='dashboard-card'>";
    echo "<div class='metric-value' style='color: " . ($pattern_stats['capability_levers'] > 0 ? '#28a745' : '#dc3545') . "'>" . $pattern_stats['capability_levers'] . "</div>";
    echo "<div class='metric-label'>Capability Levers</div>";
    echo "</div>";

    echo "<div class='dashboard-card'>";
    echo "<div class='metric-value' style='color: " . ($pattern_stats['timing_signals'] > 0 ? '#28a745' : '#dc3545') . "'>" . $pattern_stats['timing_signals'] . "</div>";
    echo "<div class='metric-label'>Timing Signals</div>";
    echo "</div>";

    echo "<div class='dashboard-card'>";
    echo "<div class='metric-value' style='color: " . ($pattern_stats['numeric_proofs'] > 0 ? '#28a745' : '#dc3545') . "'>" . $pattern_stats['numeric_proofs'] . "</div>";
    echo "<div class='metric-label'>Numeric Proofs</div>";
    echo "</div>";

    echo "</div>";

    if ($total_patterns > 0) {
        echo "<p><span class='status-icon pass'>✅</span><strong>PASS:</strong> Pattern detection found {$total_patterns} total patterns</p>";
    } else {
        echo "<p><span class='status-icon fail'>❌</span><strong>FAIL:</strong> Pattern detection found 0 patterns</p>";
    }
} else {
    $checks['pattern_detection'] = false;
    echo "<p><span class='status-icon fail'>❌</span><strong>FAIL:</strong> Pattern detection stats not found in debug output</p>";
}

// B) Citation Flow Validation
echo "<h3>B. Citation Flow Validation</h3>";

$citation_flow = [];

// Check M1T5 (raw_collector output)
$m1t5_artifact = null;
foreach ($new_artifacts as $artifact) {
    if ($artifact->artifacttype === 'normalized_inputs_v16' ||
        ($artifact->phase === 'citation_normalization' && strpos($artifact->artifacttype, 'normalized') !== false)) {
        $m1t5_artifact = $artifact;
        break;
    }
}

if ($m1t5_artifact) {
    $m1t5_data = json_decode($m1t5_artifact->jsondata, true);
    $m1t5_citations = 0;

    // Try different places citations might be
    if (isset($m1t5_data['citations'])) {
        $m1t5_citations = is_array($m1t5_data['citations']) ? count($m1t5_data['citations']) : 0;
    } else if (isset($m1t5_data['normalized_citations'])) {
        $m1t5_citations = is_array($m1t5_data['normalized_citations']) ? count($m1t5_data['normalized_citations']) : 0;
    }

    $citation_flow['M1T5'] = $m1t5_citations;
}

// Check M1T6 (canonical_builder output)
$m1t6_artifact = null;
foreach ($new_artifacts as $artifact) {
    if ($artifact->artifacttype === 'canonical_nb_dataset' ||
        ($artifact->phase === 'synthesis_core' && strpos($artifact->artifacttype, 'canonical') !== false)) {
        $m1t6_artifact = $artifact;
        break;
    }
}

if ($m1t6_artifact) {
    $m1t6_data = json_decode($m1t6_artifact->jsondata, true);
    $m1t6_citations = 0;

    if (isset($m1t6_data['aggregated_citations'])) {
        $m1t6_citations = count($m1t6_data['aggregated_citations']);
    } else if (isset($m1t6_data['citations'])) {
        $m1t6_citations = count($m1t6_data['citations']);
    } else if (isset($m1t6_data['processing_stats']['total_citations'])) {
        $m1t6_citations = $m1t6_data['processing_stats']['total_citations'];
    }

    $citation_flow['M1T6'] = $m1t6_citations;
}

// Check final synthesis
if ($new_synthesis && !empty($new_synthesis->jsoncontent)) {
    $synthesis_json = json_decode($new_synthesis->jsoncontent, true);
    if (isset($synthesis_json['citations'])) {
        $citation_flow['Final'] = is_array($synthesis_json['citations']) ? count($synthesis_json['citations']) : 0;
    }
}

echo "<table>";
echo "<tr><th>Stage</th><th>Citations</th><th>Status</th></tr>";

foreach (['M1T5', 'M1T6', 'Final'] as $stage) {
    $count = $citation_flow[$stage] ?? 0;
    $status = $count > 200 ? '✅ PASS' : ($count > 0 ? '⚠️ PARTIAL' : '❌ FAIL');
    $color = $count > 200 ? '#28a745' : ($count > 0 ? '#ffc107' : '#dc3545');

    echo "<tr>";
    echo "<td><strong>{$stage}</strong></td>";
    echo "<td style='color: {$color}; font-weight: bold;'>{$count}</td>";
    echo "<td style='color: {$color};'>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

$checks['citation_flow'] = ($citation_flow['M1T6'] ?? 0) > 200;

// C) Report Quality Validation
echo "<h3>C. Report Quality Validation</h3>";

if ($new_synthesis) {
    $html_size = strlen($new_synthesis->htmlcontent ?? '');
    $json_size = strlen($new_synthesis->jsoncontent ?? '');

    $checks['html_size'] = $html_size > 10000;
    $checks['json_size'] = $json_size > 1000;

    echo "<div class='dashboard'>";

    echo "<div class='dashboard-card'>";
    echo "<div class='metric-value' style='color: " . ($html_size > 10000 ? '#28a745' : '#dc3545') . "'>" . number_format($html_size) . "</div>";
    echo "<div class='metric-label'>HTML Size (bytes)</div>";
    echo "<div style='font-size: 11px; color: #6c757d; margin-top: 5px;'>Target: >10,000</div>";
    echo "</div>";

    echo "<div class='dashboard-card'>";
    echo "<div class='metric-value' style='color: " . ($json_size > 1000 ? '#28a745' : '#dc3545') . "'>" . number_format($json_size) . "</div>";
    echo "<div class='metric-label'>JSON Size (bytes)</div>";
    echo "<div style='font-size: 11px; color: #6c757d; margin-top: 5px;'>Target: >1,000</div>";
    echo "</div>";

    // Count sections
    $section_count = 0;
    if (!empty($new_synthesis->jsoncontent)) {
        $synthesis_json = json_decode($new_synthesis->jsoncontent, true);
        if (isset($synthesis_json['sections'])) {
            $section_count = count($synthesis_json['sections']);
        }
    }

    $checks['section_count'] = $section_count >= 9;

    echo "<div class='dashboard-card'>";
    echo "<div class='metric-value' style='color: " . ($section_count >= 9 ? '#28a745' : '#dc3545') . "'>" . $section_count . "</div>";
    echo "<div class='metric-label'>Sections Generated</div>";
    echo "<div style='font-size: 11px; color: #6c757d; margin-top: 5px;'>Target: 9-15</div>";
    echo "</div>";

    echo "</div>";

    // Show sample content
    if ($html_size > 1000) {
        echo "<h3>Sample Content:</h3>";
        $sample = substr($new_synthesis->htmlcontent, 0, 500);
        echo "<div class='code-sample'>" . htmlspecialchars($sample) . "...</div>";
    }

} else {
    $checks['html_size'] = false;
    $checks['json_size'] = false;
    $checks['section_count'] = false;
    echo "<p><span class='status-icon fail'>❌</span><strong>FAIL:</strong> No synthesis record created</p>";
}

// D) Artifact Validation
echo "<h3>D. Artifact Validation</h3>";

$expected_artifacts = [
    'normalized_inputs_v16' => false,
    'canonical_nb_dataset' => false,
    'drafted_sections' => false,
    'final_bundle' => false
];

foreach ($new_artifacts as $artifact) {
    foreach ($expected_artifacts as $type => $found) {
        if (strpos($artifact->artifacttype, $type) !== false) {
            $expected_artifacts[$type] = strlen($artifact->jsondata);
        }
    }
}

echo "<table>";
echo "<tr><th>Artifact</th><th>Size</th><th>Status</th></tr>";

foreach ($expected_artifacts as $type => $size) {
    $status = $size > 0 ? '✅ CREATED' : '❌ MISSING';
    $color = $size > 0 ? '#28a745' : '#dc3545';

    echo "<tr>";
    echo "<td><strong>{$type}</strong></td>";
    echo "<td>" . ($size > 0 ? number_format($size) . " bytes" : "N/A") . "</td>";
    echo "<td style='color: {$color};'>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

$checks['artifacts'] = $expected_artifacts['final_bundle'] > 0;

echo "</div>";

// ============================================================================
// STEP 7: FINAL VERDICT
// ============================================================================

$all_pass = !in_array(false, $checks, true);
$pass_count = count(array_filter($checks));
$total_count = count($checks);

if ($all_pass) {
    echo "<div class='section success'>";
    echo "<h2>🎉 SUCCESS - All Checks Passed!</h2>";
    echo "<p><strong>Pattern detection fixes are working correctly!</strong></p>";
} else if ($pass_count >= ($total_count / 2)) {
    echo "<div class='section warning'>";
    echo "<h2>⚠️ PARTIAL SUCCESS - Some Issues Remain</h2>";
    echo "<p><strong>Pattern detection is working but needs optimization.</strong></p>";
} else {
    echo "<div class='section danger'>";
    echo "<h2>❌ VALIDATION FAILED - Critical Issues</h2>";
    echo "<p><strong>Pattern detection fixes did not resolve all issues.</strong></p>";
}

echo "<h3>Validation Summary:</h3>";
echo "<table>";
echo "<tr><th>Check</th><th>Status</th></tr>";

$check_labels = [
    'pattern_detection' => 'Pattern Detection (>0 patterns)',
    'citation_flow' => 'Citation Flow (>200 citations in M1T6)',
    'html_size' => 'Report HTML Size (>10KB)',
    'json_size' => 'Report JSON Size (>1KB)',
    'section_count' => 'Section Count (≥9 sections)',
    'artifacts' => 'Artifact Creation (final_bundle exists)'
];

foreach ($checks as $key => $passed) {
    $label = $check_labels[$key] ?? $key;
    $status = $passed ? '✅ PASS' : '❌ FAIL';
    $color = $passed ? '#28a745' : '#dc3545';

    echo "<tr>";
    echo "<td>{$label}</td>";
    echo "<td style='color: {$color}; font-weight: bold;'>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><strong>Overall Result:</strong> {$pass_count}/{$total_count} checks passed</p>";

// Comparison with previous attempts
echo "<h3>Improvements vs Previous Attempts:</h3>";
echo "<table>";
echo "<tr><th>Metric</th><th>Before Fix</th><th>After Fix</th><th>Change</th></tr>";

$comparisons = [
    ['Pattern Detection', '0 patterns', ($pattern_stats ? array_sum($pattern_stats) : 0) . ' patterns', $pattern_stats ? '✅ IMPROVED' : '❌ NO CHANGE'],
    ['Report Size', '224 bytes', ($new_synthesis ? strlen($new_synthesis->htmlcontent) : 0) . ' bytes', ($new_synthesis && strlen($new_synthesis->htmlcontent) > 1000) ? '✅ IMPROVED' : '❌ NO CHANGE'],
    ['Section Count', '0 sections', (isset($section_count) ? $section_count : 0) . ' sections', (isset($section_count) && $section_count > 0) ? '✅ IMPROVED' : '❌ NO CHANGE'],
    ['M1T6 Citations', '0 citations', ($citation_flow['M1T6'] ?? 0) . ' citations', ($citation_flow['M1T6'] ?? 0) > 200 ? '✅ IMPROVED' : '⚠️ PARTIAL']
];

foreach ($comparisons as $row) {
    $change_color = strpos($row[3], '✅') !== false ? '#28a745' : (strpos($row[3], '⚠️') !== false ? '#ffc107' : '#dc3545');
    echo "<tr>";
    echo "<td><strong>{$row[0]}</strong></td>";
    echo "<td>{$row[1]}</td>";
    echo "<td style='font-weight: bold;'>{$row[2]}</td>";
    echo "<td style='color: {$change_color}; font-weight: bold;'>{$row[3]}</td>";
    echo "</tr>";
}

echo "</table>";

echo "</div>";

// ============================================================================
// STEP 8: NEXT STEPS
// ============================================================================

echo "<div class='section info'>";
echo "<h2>📝 Next Steps</h2>";

if ($all_pass) {
    echo "<p>✅ <strong>Pattern detection is working correctly!</strong></p>";
    echo "<p><strong>Recommended actions:</strong></p>";
    echo "<ul>";
    echo "<li>Review the generated report for quality</li>";
    echo "<li>Test with other company runs to confirm consistency</li>";
    echo "<li>Consider implementing M1T5 artifact structure optimization (optional)</li>";
    echo "</ul>";
} else {
    echo "<p><strong>Issues to investigate:</strong></p>";
    echo "<ul>";

    if (!$checks['pattern_detection']) {
        echo "<li>❌ <strong>Pattern detection still returning 0 patterns</strong> - Check NB data structure in database</li>";
    }

    if (!$checks['citation_flow']) {
        echo "<li>❌ <strong>Citations not flowing correctly</strong> - Review M1T5/M1T6 artifact structure</li>";
    }

    if (!$checks['html_size']) {
        echo "<li>❌ <strong>Report HTML too small</strong> - Check section drafting logic</li>";
    }

    if (!$checks['section_count']) {
        echo "<li>❌ <strong>Not enough sections generated</strong> - Review draft_sections method</li>";
    }

    echo "</ul>";

    echo "<p><strong>Diagnostic tools:</strong></p>";
    echo "<ul>";
    echo "<li><a href='diagnose_canonical_builder_192.php'>diagnose_canonical_builder_192.php</a> - Check citation flow at each stage</li>";
    echo "<li>Review debug output above for detailed error messages</li>";
    echo "</ul>";
}

echo "</div>";

// ============================================================================
// VIEW REPORT BUTTON
// ============================================================================

if ($new_synthesis) {
    echo "<div class='section success' style='text-align: center;'>";
    echo "<p style='font-size: 18px; margin-bottom: 20px;'><strong>Report Generated Successfully</strong></p>";
    echo "<p>";
    echo "<a href='view_report.php?runid={$runid}' class='btn btn-success' style='margin-right: 10px;'>📊 VIEW REPORT</a>";
    echo "<a href='?confirm=yes' class='btn btn-primary'>🔄 RUN VALIDATION AGAIN</a>";
    echo "</p>";
    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
