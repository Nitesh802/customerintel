<?php
/**
 * Check if citations flow from raw_collector → canonical_builder
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_canonical_flow.php'));
$PAGE->set_title("Check Citation Flow - Raw Collector to Canonical Builder");

echo $OUTPUT->header();

?>
<style>
.flow { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
</style>

<div class="flow">

<h1>🔍 Citation Flow Analysis - Run <?= $runid ?></h1>

<?php

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/canonical_builder.php');
require_once(__DIR__ . '/classes/services/artifact_repository.php');

$raw_collector = new \local_customerintel\services\raw_collector();
$canonical_builder = new \local_customerintel\services\canonical_builder();
$artifact_repo = new \local_customerintel\services\artifact_repository();

// Step 1: Get inputs from raw_collector
echo "<div class='section'>";
echo "<h2>Step 1: Raw Collector Output</h2>";

try {
    $inputs = $raw_collector->get_normalized_inputs($runid);

    $total_citations_in_inputs = 0;
    foreach ($inputs['nb'] ?? [] as $nbcode => $nb_data) {
        $total_citations_in_inputs += count($nb_data['citations'] ?? []);
    }

    echo "<p>✅ raw_collector executed successfully</p>";
    echo "<p><strong>NBs returned:</strong> " . count($inputs['nb'] ?? []) . "</p>";
    echo "<p><strong>Total citations in inputs:</strong> {$total_citations_in_inputs}</p>";

} catch (\Exception $e) {
    echo "<p class='bad'>❌ Error: " . $e->getMessage() . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// Step 2: Call canonical_builder
echo "<div class='section'>";
echo "<h2>Step 2: Canonical Builder Execution</h2>";

try {
    // Get canonical NB keys (all NBs)
    $canonical_nbkeys = array_keys($inputs['nb']);

    $canonical_output = $canonical_builder->build_canonical_nb_dataset($inputs, $canonical_nbkeys, $runid);

    $total_citations_in_canonical = 0;
    if (isset($canonical_output['aggregated_citations'])) {
        $total_citations_in_canonical = count($canonical_output['aggregated_citations']);
    }

    echo "<p>✅ canonical_builder executed successfully</p>";
    echo "<p><strong>NBs in canonical dataset:</strong> " . count($canonical_output['nb_data'] ?? []) . "</p>";
    echo "<p><strong>Total citations in canonical output:</strong> {$total_citations_in_canonical}</p>";

    // Show per-NB breakdown
    echo "<h3>Per-NB Citation Counts in Canonical Dataset:</h3>";
    echo "<table>";
    echo "<tr><th>NB Code</th><th>Citations</th></tr>";

    foreach ($canonical_output['nb_data'] ?? [] as $nbcode => $nb_data) {
        $cit_count = count($nb_data['citations'] ?? []);
        $class = $cit_count > 0 ? '' : 'style="background: #f8d7da;"';
        echo "<tr {$class}>";
        echo "<td><strong>{$nbcode}</strong></td>";
        echo "<td>{$cit_count}</td>";
        echo "</tr>";
    }

    echo "</table>";

} catch (\Exception $e) {
    echo "<p class='bad'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";

// Step 3: Check artifact
echo "<div class='section'>";
echo "<h2>Step 3: Canonical Dataset Artifact</h2>";

$canonical_artifact = $artifact_repo->get_artifact($runid, 'synthesis', 'canonical_nb_dataset');

if ($canonical_artifact) {
    echo "<p>✅ Artifact found</p>";
    echo "<p><strong>Artifact ID:</strong> {$canonical_artifact->id}</p>";
    echo "<p><strong>Size:</strong> " . number_format(strlen($canonical_artifact->jsondata)) . " bytes</p>";
    echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $canonical_artifact->timecreated) . "</p>";

    $artifact_data = json_decode($canonical_artifact->jsondata, true);
    if ($artifact_data) {
        $artifact_citations = count($artifact_data['aggregated_citations'] ?? []);
        echo "<p><strong>Citations in artifact:</strong> {$artifact_citations}</p>";

        $class = $artifact_citations > 0 ? 'good' : 'bad';
        echo "<div class='section {$class}'>";
        echo "<p><strong>Artifact has " . ($artifact_citations > 0 ? $artifact_citations : 'NO') . " citations</strong></p>";
        echo "</div>";
    }
} else {
    echo "<p class='bad'>❌ No canonical dataset artifact found</p>";
}

echo "</div>";

// Summary
echo "<div class='section warning'>";
echo "<h2>🔬 Flow Summary</h2>";
echo "<table>";
echo "<tr><th>Stage</th><th>Citations Count</th><th>Status</th></tr>";
echo "<tr><td>Raw Collector Input</td><td>{$total_citations_in_inputs}</td><td>" . ($total_citations_in_inputs > 0 ? '✅' : '❌') . "</td></tr>";
echo "<tr><td>Canonical Builder Output</td><td>{$total_citations_in_canonical}</td><td>" . ($total_citations_in_canonical > 0 ? '✅' : '❌') . "</td></tr>";
echo "<tr><td>Canonical Artifact</td><td>" . ($artifact_citations ?? 0) . "</td><td>" . (($artifact_citations ?? 0) > 0 ? '✅' : '❌') . "</td></tr>";
echo "</table>";

if ($total_citations_in_inputs > 0 && $total_citations_in_canonical === 0) {
    echo "<div class='section bad'>";
    echo "<h3>❌ PROBLEM: Citations lost in canonical_builder!</h3>";
    echo "<p>raw_collector has {$total_citations_in_inputs} citations, but canonical_builder outputs 0.</p>";
    echo "<p>The issue is in canonical_builder.php</p>";
    echo "</div>";
} else if ($total_citations_in_inputs > 0 && $total_citations_in_canonical > 0 && ($artifact_citations ?? 0) === 0) {
    echo "<div class='section bad'>";
    echo "<h3>❌ PROBLEM: Citations not saved to artifact!</h3>";
    echo "<p>canonical_builder has {$total_citations_in_canonical} citations, but artifact has 0.</p>";
    echo "<p>The issue is in artifact saving.</p>";
    echo "</div>";
} else if ($total_citations_in_inputs > 0 && $total_citations_in_canonical > 0 && ($artifact_citations ?? 0) > 0) {
    echo "<div class='section good'>";
    echo "<h3>✅ Citations flow correctly through all stages!</h3>";
    echo "<p>If the report is still minimal, the issue is in pattern_detection or report assembly.</p>";
    echo "</div>";
} else {
    echo "<div class='section bad'>";
    echo "<h3>❌ No citations at any stage</h3>";
    echo "</div>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
