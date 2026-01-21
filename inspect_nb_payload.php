<?php
/**
 * Inspect actual NB payload to see what fields exist
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/inspect_nb_payload.php'));
$PAGE->set_title("Inspect NB Payload");

echo $OUTPUT->header();

?>
<style>
.inspect { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 10px; max-height: 400px; }
</style>

<div class="inspect">

<h1>🔍 Inspect NB Payload Structure - Run <?= $runid ?></h1>

<?php

require_once(__DIR__ . '/classes/services/raw_collector.php');

$raw_collector = new \local_customerintel\services\raw_collector();
$inputs = $raw_collector->get_normalized_inputs($runid);

// Inspect NB1
echo "<div class='section'>";
echo "<h2>NB1 Payload</h2>";

if (isset($inputs['nb']['NB1'])) {
    $nb1 = $inputs['nb']['NB1'];

    echo "<p><strong>Top-level keys in NB1:</strong></p>";
    echo "<pre>";
    print_r(array_keys($nb1));
    echo "</pre>";

    if (isset($nb1['data'])) {
        echo "<p><strong>Keys in NB1['data']:</strong></p>";
        echo "<pre>";
        print_r(array_keys($nb1['data']));
        echo "</pre>";

        echo "<p><strong>Full NB1['data'] structure:</strong></p>";
        echo "<pre>";
        print_r($nb1['data']);
        echo "</pre>";
    } else {
        echo "<p class='bad'>❌ No 'data' key in NB1</p>";
    }

    echo "<p><strong>Citations count:</strong> " . count($nb1['citations'] ?? []) . "</p>";
} else {
    echo "<p class='bad'>❌ NB1 not found in inputs</p>";
}

echo "</div>";

// Check what pattern detection is looking for
echo "<div class='section warning'>";
echo "<h2>What Pattern Detection Expects</h2>";
echo "<p>From analysis_engine.php line 1111:</p>";
echo "<pre>";
echo "\$financial_pressures = \$this->extract_field(\n";
echo "    \$nb1_data['data'],\n";
echo "    ['financial_pressures', 'pressures', 'challenges']\n";
echo ");\n";
echo "</pre>";

echo "<p>It's looking for keys named:</p>";
echo "<ul>";
echo "<li>'financial_pressures'</li>";
echo "<li>'pressures'</li>";
echo "<li>'challenges'</li>";
echo "</ul>";

echo "<p><strong>Check if these exist in NB1['data']:</strong></p>";
if (isset($inputs['nb']['NB1']['data'])) {
    $nb1_data = $inputs['nb']['NB1']['data'];
    echo "<ul>";
    echo "<li>'financial_pressures': " . (isset($nb1_data['financial_pressures']) ? '✅ EXISTS' : '❌ NOT FOUND') . "</li>";
    echo "<li>'pressures': " . (isset($nb1_data['pressures']) ? '✅ EXISTS' : '❌ NOT FOUND') . "</li>";
    echo "<li>'challenges': " . (isset($nb1_data['challenges']) ? '✅ EXISTS' : '❌ NOT FOUND') . "</li>";
    echo "</ul>";
}

echo "</div>";

// Check NB3 for operational issues
echo "<div class='section'>";
echo "<h2>NB3 Payload</h2>";

if (isset($inputs['nb']['NB3'])) {
    $nb3 = $inputs['nb']['NB3'];

    if (isset($nb3['data'])) {
        echo "<p><strong>Keys in NB3['data']:</strong></p>";
        echo "<pre>";
        print_r(array_keys($nb3['data']));
        echo "</pre>";

        echo "<p><strong>What pattern detection looks for in NB3:</strong></p>";
        echo "<ul>";
        echo "<li>'inefficiencies': " . (isset($nb3['data']['inefficiencies']) ? '✅ EXISTS' : '❌ NOT FOUND') . "</li>";
        echo "<li>'operational_issues': " . (isset($nb3['data']['operational_issues']) ? '✅ EXISTS' : '❌ NOT FOUND') . "</li>";
        echo "<li>'gaps': " . (isset($nb3['data']['gaps']) ? '✅ EXISTS' : '❌ NOT FOUND') . "</li>";
        echo "</ul>";
    }
}

echo "</div>";

// Diagnosis
echo "<div class='section bad'>";
echo "<h2>🔬 Diagnosis</h2>";
echo "<p><strong>If the expected keys are missing:</strong></p>";
echo "<p>The NBs were generated with a different schema than what pattern detection expects.</p>";
echo "<p>Pattern detection expects specific field names that were defined in the NB schema.</p>";
echo "<p>But the actual NB payloads might have different field names or structure.</p>";
echo "<p><strong>This is a schema mismatch issue.</strong></p>";
echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
