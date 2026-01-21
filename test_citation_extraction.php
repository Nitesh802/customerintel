<?php
/**
 * Test the exact citation extraction logic from build_inputs_from_normalized_artifact
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/test_citation_extraction.php'));
$PAGE->set_title("Test Citation Extraction Logic");

echo $OUTPUT->header();

?>
<style>
.test { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
</style>

<div class="test">

<h1>🧪 Test Citation Extraction Logic - Run <?= $runid ?></h1>

<?php

// Simulate the exact logic from build_inputs_from_normalized_artifact
$nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');

echo "<div class='section'>";
echo "<h2>Testing Citation Extraction for Each NB</h2>";
echo "<p><strong>Total NBs:</strong> " . count($nb_results) . "</p>";
echo "</div>";

foreach ($nb_results as $result) {
    echo "<div class='section'>";
    echo "<h3>NB: {$result->nbcode}</h3>";

    // Step 1: Try to decode payload
    $payload = null;
    if (!empty($result->jsonpayload)) {
        $payload = json_decode($result->jsonpayload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $payload = null;
            echo "<p>❌ Payload decode failed: " . json_last_error_msg() . "</p>";
        } else {
            echo "<p>✅ Payload decoded successfully</p>";
        }
    } else {
        echo "<p>⚠️ jsonpayload is empty</p>";
    }

    // Step 2: Check if payload has citations
    $enhanced_citations = [];
    $path_taken = '';

    if ($payload && isset($payload['citations'])) {
        $path_taken = 'PATH A: Citations in payload';
        echo "<p><strong style='color: #28a745;'>✅ {$path_taken}</strong></p>";
        echo "<p>Citations count in payload: " . count($payload['citations']) . "</p>";
        $enhanced_citations = $payload['citations'];
    } else if (!empty($result->citations)) {
        $path_taken = 'PATH B: Fallback to citations column';
        echo "<p><strong style='color: #007bff;'>🔵 {$path_taken}</strong></p>";

        echo "<p>Citations column raw value:</p>";
        echo "<pre>" . htmlspecialchars(substr($result->citations, 0, 200)) . "...</pre>";

        $enhanced_citations = json_decode($result->citations, true);
        if (!is_array($enhanced_citations)) {
            echo "<p>❌ Failed to decode citations column: " . json_last_error_msg() . "</p>";
            $enhanced_citations = [];
        } else {
            echo "<p>✅ Citations column decoded successfully</p>";
            echo "<p>Citations count: " . count($enhanced_citations) . "</p>";
        }
    } else {
        $path_taken = 'PATH C: No citations found';
        echo "<p><strong style='color: #dc3545;'>❌ {$path_taken}</strong></p>";
        echo "<p>Payload: " . ($payload ? 'exists' : 'null') . "</p>";
        echo "<p>Payload has 'citations' key: " . ($payload && isset($payload['citations']) ? 'yes' : 'no') . "</p>";
        echo "<p>result->citations empty: " . (empty($result->citations) ? 'yes' : 'no') . "</p>";
    }

    // Final result
    $class = count($enhanced_citations) > 0 ? 'good' : 'bad';
    echo "<div class='section {$class}' style='margin: 10px 0;'>";
    echo "<p><strong>Final citations array count:</strong> " . count($enhanced_citations) . "</p>";
    echo "<p><strong>Path taken:</strong> {$path_taken}</p>";
    echo "</div>";

    echo "</div>";
}

// Now test what raw_collector actually returns
echo "<div class='section warning'>";
echo "<h2>🔬 Actual raw_collector Output Test</h2>";
echo "<p>Let's call the actual raw_collector method and see what it returns...</p>";

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/artifact_compatibility_adapter.php');

$raw_collector = new \local_customerintel\services\raw_collector();

try {
    $inputs = $raw_collector->get_normalized_inputs($runid);

    echo "<p>✅ raw_collector executed successfully</p>";
    echo "<p><strong>NB count:</strong> " . count($inputs['nb'] ?? []) . "</p>";

    $total_citations = 0;
    foreach ($inputs['nb'] ?? [] as $nbcode => $nb_data) {
        $cit_count = count($nb_data['citations'] ?? []);
        $total_citations += $cit_count;

        $class = $cit_count > 0 ? 'good' : 'bad';
        echo "<div class='section {$class}' style='margin: 5px 0; padding: 5px;'>";
        echo "<strong>{$nbcode}:</strong> {$cit_count} citations";
        echo "</div>";
    }

    $class = $total_citations > 0 ? 'good' : 'bad';
    echo "<div class='section {$class}'>";
    echo "<h3>Total Citations from raw_collector: {$total_citations}</h3>";
    echo "</div>";

} catch (\Exception $e) {
    echo "<p class='bad'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
