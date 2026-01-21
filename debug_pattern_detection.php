<?php
/**
 * Debug why pattern detection returns 0 patterns
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/debug_pattern_detection.php'));
$PAGE->set_title("Debug Pattern Detection");

echo $OUTPUT->header();

?>
<style>
.debug { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
</style>

<div class="debug">

<h1>🔍 Debug Pattern Detection - Run <?= $runid ?></h1>

<?php

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/canonical_builder.php');
require_once(__DIR__ . '/classes/services/analysis_engine.php');

$raw_collector = new \local_customerintel\services\raw_collector();
$canonical_builder = new \local_customerintel\services\canonical_builder();

// Get inputs
$inputs = $raw_collector->get_normalized_inputs($runid);

echo "<div class='section'>";
echo "<h2>Step 1: Inputs Structure</h2>";
echo "<p><strong>Keys in \$inputs['nb']:</strong></p>";
echo "<pre>";
print_r(array_keys($inputs['nb']));
echo "</pre>";
echo "</div>";

// Build canonical dataset
$canonical_nbkeys = array_keys($inputs['nb']);
$canonical_dataset = $canonical_builder->build_canonical_nb_dataset($inputs, $canonical_nbkeys, $runid);

echo "<div class='section'>";
echo "<h2>Step 2: Canonical Dataset Structure</h2>";
echo "<p><strong>Keys in canonical_dataset['nb_data']:</strong></p>";
echo "<pre>";
print_r(array_keys($canonical_dataset['nb_data'] ?? []));
echo "</pre>";

echo "<p><strong>Sample NB structure (NB1):</strong></p>";
if (isset($canonical_dataset['nb_data']['NB1'])) {
    echo "<p>✅ NB1 found in canonical dataset</p>";
    echo "<pre>";
    echo "Keys: " . implode(', ', array_keys($canonical_dataset['nb_data']['NB1'])) . "\n";
    echo "Has 'data' key: " . (isset($canonical_dataset['nb_data']['NB1']['data']) ? 'yes' : 'no') . "\n";
    echo "Has 'citations' key: " . (isset($canonical_dataset['nb_data']['NB1']['citations']) ? 'yes' : 'no') . "\n";
    echo "Citations count: " . count($canonical_dataset['nb_data']['NB1']['citations'] ?? []) . "\n";
    echo "</pre>";
} else {
    echo "<p class='bad'>❌ NB1 NOT found in canonical dataset</p>";
    echo "<p>Looking for 'NB1' but available keys are: " . implode(', ', array_keys($canonical_dataset['nb_data'] ?? [])) . "</p>";
}

echo "</div>";

// Call analysis_engine
echo "<div class='section'>";
echo "<h2>Step 3: Analysis Engine Pattern Detection</h2>";

$analysis_engine = new \local_customerintel\services\analysis_engine($runid, $canonical_dataset);

// Call detect_patterns with the inputs
$patterns = $analysis_engine->detect_patterns($inputs);

echo "<p><strong>Patterns detected:</strong></p>";
echo "<pre>";
echo "Pressure themes: " . count($patterns['pressure_themes'] ?? []) . "\n";
echo "Capability levers: " . count($patterns['capability_levers'] ?? []) . "\n";
echo "Timing signals: " . count($patterns['timing_signals'] ?? []) . "\n";
echo "Executives: " . count($patterns['executives'] ?? []) . "\n";
echo "Numeric proofs: " . count($patterns['numeric_proofs'] ?? []) . "\n";
echo "</pre>";

$class = (count($patterns['pressure_themes'] ?? []) > 0) ? 'good' : 'bad';
echo "<div class='section {$class}'>";
if (count($patterns['pressure_themes'] ?? []) > 0) {
    echo "<p>✅ Patterns detected successfully!</p>";
} else {
    echo "<p>❌ NO patterns detected!</p>";
    echo "<p>This means pattern detection is not finding data in the NBs.</p>";
}
echo "</div>";

echo "</div>";

// Diagnosis
echo "<div class='section warning'>";
echo "<h2>🔬 Diagnosis</h2>";

echo "<p><strong>Pattern detection expects:</strong></p>";
echo "<ul>";
echo "<li>NB data with keys: 'NB1', 'NB2', 'NB3', etc.</li>";
echo "<li>Each NB has a 'data' key with the payload</li>";
echo "<li>Specific fields in the payload (e.g., 'financial_pressures', 'challenges')</li>";
echo "</ul>";

echo "<p><strong>What it's receiving:</strong></p>";
echo "<ul>";
echo "<li>NB data keys: " . implode(', ', array_keys($inputs['nb'])) . "</li>";
echo "<li>Canonical dataset keys: " . implode(', ', array_keys($canonical_dataset['nb_data'] ?? [])) . "</li>";
echo "</ul>";

$nb_keys_in_inputs = array_keys($inputs['nb']);
$nb_keys_in_canonical = array_keys($canonical_dataset['nb_data'] ?? []);

if ($nb_keys_in_inputs === $nb_keys_in_canonical) {
    echo "<p class='good'>✅ Keys match between inputs and canonical dataset</p>";
} else {
    echo "<p class='bad'>❌ Keys DON'T match!</p>";
}

// Check if pattern detection is using the right data source
echo "<p><strong>Analysis:</strong></p>";
echo "<p>detect_patterns() receives \$inputs and looks at \$inputs['nb']</p>";
echo "<p>If \$inputs['nb'] has keys like 'NB1', pattern detection should work.</p>";
echo "<p>But if keys are normalized (NB1 vs NB-1), there might be a mismatch.</p>";

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
