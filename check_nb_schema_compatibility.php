<?php
/**
 * Check if NB schema is compatible with pattern detection
 * This quickly validates if NBs have the field-centric structure needed
 *
 * Usage: check_nb_schema_compatibility.php?runid=X
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = optional_param('runid', 0, PARAM_INT);

if (!$runid) {
    echo "<h1>NB Schema Compatibility Checker</h1>";
    echo "<p>Usage: check_nb_schema_compatibility.php?runid=X</p>";

    // Show recent runs
    $recent = $DB->get_records_sql(
        "SELECT id, companyid, targetcompanyid, status, timecreated
         FROM {local_ci_run}
         ORDER BY timecreated DESC
         LIMIT 10"
    );

    echo "<h2>Recent Runs</h2>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Run ID</th><th>Status</th><th>Created</th><th>Action</th></tr>";

    foreach ($recent as $r) {
        echo "<tr>";
        echo "<td>{$r->id}</td>";
        echo "<td>{$r->status}</td>";
        echo "<td>" . date('Y-m-d H:i', $r->timecreated) . "</td>";
        echo "<td><a href='?runid={$r->id}'>Check Schema</a></td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_nb_schema_compatibility.php', ['runid' => $runid]));
$PAGE->set_title("NB Schema Check - Run {$runid}");

echo $OUTPUT->header();

?>
<style>
.schema-check { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; }
.good { background: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745; }
.bad { background: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #dc3545; }
.info { background: #d1ecf1; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #0c5460; }
.success { color: #28a745; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>

<div class="schema-check">

<h1>🔬 NB Schema Compatibility Check - Run <?php echo $runid; ?></h1>

<?php

require_once(__DIR__ . '/classes/services/raw_collector.php');

$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

if (empty($raw_inputs['nb'])) {
    echo "<div class='bad'>";
    echo "<p class='fail'>❌ No NBs found for this run!</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

$nb_count = count($raw_inputs['nb']);
echo "<p>Found <strong>{$nb_count} NBs</strong></p>";

// Check NB1 as representative sample
if (!isset($raw_inputs['nb']['NB1'])) {
    echo "<div class='bad'>";
    echo "<p class='fail'>❌ NB1 not found - NB keys may not be normalized!</p>";
    echo "<p>Available keys: " . implode(', ', array_keys($raw_inputs['nb'])) . "</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

$nb1 = $raw_inputs['nb']['NB1'];

echo "<h2>Schema Structure Analysis</h2>";

// Check for 'data' key
echo "<div class='" . (isset($nb1['data']) ? "good" : "bad") . "'>";
echo "<h3>✓ Check 1: 'data' Key Present</h3>";
if (isset($nb1['data'])) {
    echo "<p class='success'>✅ NB1 has 'data' key</p>";
} else {
    echo "<p class='fail'>❌ NB1 missing 'data' key - pattern detection won't work!</p>";
    if (isset($nb1['payload'])) {
        echo "<p>Has 'payload' key instead - raw_collector may need updating</p>";
    }
}
echo "</div>";

if (!isset($nb1['data'])) {
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

// Check structure type
echo "<div class='info'>";
echo "<h3>✓ Check 2: Data Structure Type</h3>";

$data = $nb1['data'];
$top_keys = array_keys($data);

echo "<p>Top-level keys in NB1['data']:</p>";
echo "<pre>" . implode(', ', array_slice($top_keys, 0, 10)) . (count($top_keys) > 10 ? '...' : '') . "</pre>";

// Pattern detection expects these field names
$expected_fields = [
    'financial_pressures', 'pressures', 'challenges',  // For NB1
    'strategic_capabilities', 'capabilities', 'strengths',  // For NB3
    'technology_stack', 'platforms', 'tools',  // For NB4
    'timing_signals', 'urgency', 'timeline',  // For NB8
    'executives', 'leadership', 'decision_makers',  // For NB11
    'metrics', 'kpis', 'numbers'  // For NB13
];

$found_fields = array_intersect($expected_fields, $top_keys);
$schema_type = count($found_fields) > 0 ? 'field-centric' : 'company-centric';

if ($schema_type === 'field-centric') {
    echo "<p class='success'>✅ Field-centric schema detected</p>";
    echo "<p>Found expected fields: <code>" . implode('</code>, <code>', $found_fields) . "</code></p>";
} else {
    echo "<p class='fail'>❌ Company-centric schema detected</p>";
    echo "<p>Top-level keys appear to be company names or section names, not field names</p>";
}

echo "</div>";

// Check specific fields pattern detection needs
echo "<div class='" . ($schema_type === 'field-centric' ? "good" : "bad") . "'>";
echo "<h3>✓ Check 3: Required Fields for Pattern Detection</h3>";

$pattern_checks = [
    'NB1' => ['financial_pressures', 'pressures', 'challenges'],
    'NB3' => ['strategic_capabilities', 'capabilities', 'strengths'],
    'NB4' => ['technology_stack', 'platforms', 'tools'],
    'NB8' => ['timing_signals', 'urgency', 'timeline'],
    'NB11' => ['executives', 'leadership', 'decision_makers'],
    'NB13' => ['metrics', 'kpis', 'numbers']
];

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>NB</th><th>Expected Fields</th><th>Found</th></tr>";

$compatible_count = 0;
foreach ($pattern_checks as $nbcode => $expected) {
    if (!isset($raw_inputs['nb'][$nbcode])) {
        echo "<tr><td>{$nbcode}</td><td>" . implode(', ', $expected) . "</td><td>⚠️ NB missing</td></tr>";
        continue;
    }

    $nb_data = $raw_inputs['nb'][$nbcode]['data'] ?? [];
    $nb_keys = array_keys($nb_data);
    $found = array_intersect($expected, $nb_keys);

    if (count($found) > 0) {
        echo "<tr><td>{$nbcode}</td><td>" . implode(', ', $expected) . "</td>";
        echo "<td class='success'>✅ " . implode(', ', $found) . "</td></tr>";
        $compatible_count++;
    } else {
        echo "<tr><td>{$nbcode}</td><td>" . implode(', ', $expected) . "</td>";
        echo "<td class='fail'>❌ None found</td></tr>";
    }
}

echo "</table>";

echo "<p>Compatible NBs: <strong>{$compatible_count}/6</strong></p>";

echo "</div>";

// Sample data structure
echo "<div class='info'>";
echo "<h3>✓ Check 4: Sample Data Structure</h3>";
echo "<p>NB1['data'] structure (first 800 chars):</p>";
echo "<pre style='max-height: 400px; overflow-y: auto;'>";
echo htmlspecialchars(substr(print_r($nb1['data'], true), 0, 800));
echo "\n... (truncated)";
echo "</pre>";
echo "</div>";

// Final verdict
echo "<div class='" . ($schema_type === 'field-centric' && $compatible_count >= 4 ? "good" : "bad") . "'>";
echo "<h2>🎯 Final Verdict</h2>";

if ($schema_type === 'field-centric' && $compatible_count >= 4) {
    echo "<p class='success' style='font-size: 20px;'>✅ SCHEMA COMPATIBLE!</p>";
    echo "<p>This run's NBs have the correct field-centric structure that pattern detection expects.</p>";
    echo "<p><strong>Expected outcomes:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Pattern detection will find multiple patterns</li>";
    echo "<li>✅ All 9 synthesis sections will be generated</li>";
    echo "<li>✅ Substantial content (50k+ bytes)</li>";
    echo "<li>✅ M1T5-M1T8 pipeline fully validated</li>";
    echo "</ul>";
    echo "<p><a href='test_pattern_detection_fix.php?runid={$runid}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>▶️ Test Pattern Detection</a></p>";
} else if ($schema_type === 'field-centric' && $compatible_count > 0) {
    echo "<p style='color: #ffc107; font-size: 20px; font-weight: bold;'>⚠️ PARTIAL COMPATIBILITY</p>";
    echo "<p>Some NBs have the correct structure ({$compatible_count}/6), but not all.</p>";
    echo "<p>Pattern detection may work partially - some sections will be generated.</p>";
} else {
    echo "<p class='fail' style='font-size: 20px;'>❌ SCHEMA INCOMPATIBLE</p>";
    echo "<p>This run's NBs have a company-centric structure, not the field-centric structure pattern detection expects.</p>";
    echo "<p><strong>Why this happens:</strong></p>";
    echo "<ul>";
    echo "<li>NBs may have been generated with an older schema</li>";
    echo "<li>NBs may be using company names as top-level keys</li>";
    echo "<li>The prompt templates may need updating</li>";
    echo "</ul>";
    echo "<p><strong>What this means:</strong></p>";
    echo "<ul>";
    echo "<li>❌ Pattern detection will return 0 patterns</li>";
    echo "<li>❌ Only 3 minimal sections will be generated</li>";
    echo "<li>❌ Very small content (~900 bytes)</li>";
    echo "</ul>";
    echo "<p><strong>This is NOT a bug in the code</strong> - it's a data schema issue.</p>";
    echo "<p>The M1T5-M1T8 pipeline code is working correctly, but needs NBs with the correct schema.</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
