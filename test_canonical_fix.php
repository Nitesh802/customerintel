<?php
/**
 * Test the canonical_builder fix
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/test_canonical_fix.php'));
$PAGE->set_title("Test Canonical Builder Fix");

echo $OUTPUT->header();

?>
<style>
.test { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
</style>

<div class="test">

<h1>🧪 Test Canonical Builder Fix - Run <?= $runid ?></h1>

<?php

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/canonical_builder.php');

$raw_collector = new \local_customerintel\services\raw_collector();
$canonical_builder = new \local_customerintel\services\canonical_builder();

// Step 1: Get inputs
echo "<div class='section'>";
echo "<h2>Step 1: Get Inputs from raw_collector</h2>";

$inputs = $raw_collector->get_normalized_inputs($runid);

$total_citations_in = 0;
foreach ($inputs['nb'] ?? [] as $nbcode => $nb_data) {
    $total_citations_in += count($nb_data['citations'] ?? []);
}

echo "<p>✅ Inputs received</p>";
echo "<p><strong>NBs:</strong> " . count($inputs['nb']) . "</p>";
echo "<p><strong>Total citations:</strong> {$total_citations_in}</p>";
echo "</div>";

// Step 2: Call canonical_builder
echo "<div class='section'>";
echo "<h2>Step 2: Call canonical_builder</h2>";

$canonical_nbkeys = array_keys($inputs['nb']);
$canonical_output = $canonical_builder->build_canonical_nb_dataset($inputs, $canonical_nbkeys, $runid);

$total_citations_out = 0;
if (isset($canonical_output['aggregated_citations'])) {
    $total_citations_out = count($canonical_output['aggregated_citations']);
}

// Also count from processing_stats
$stats_citations = $canonical_output['processing_stats']['total_citations'] ?? 0;

echo "<p>✅ canonical_builder executed</p>";
echo "<p><strong>NBs in output:</strong> " . count($canonical_output['nb_data'] ?? []) . "</p>";
echo "<p><strong>Aggregated citations:</strong> {$total_citations_out}</p>";
echo "<p><strong>Stats total citations:</strong> {$stats_citations}</p>";

// Show per-NB breakdown
echo "<h3>Per-NB Citation Counts:</h3>";
echo "<table>";
echo "<tr><th>NB Code</th><th>Citations</th><th>Status</th></tr>";

$total_per_nb = 0;
foreach ($canonical_output['nb_data'] ?? [] as $nbcode => $nb_data) {
    $cit_count = count($nb_data['citations'] ?? []);
    $total_per_nb += $cit_count;

    $class = $cit_count > 0 ? '' : 'style="background: #f8d7da;"';
    echo "<tr {$class}>";
    echo "<td><strong>{$nbcode}</strong></td>";
    echo "<td>{$cit_count}</td>";
    echo "<td>" . ($nb_data['status'] ?? 'unknown') . "</td>";
    echo "</tr>";
}

echo "<tr style='background: #e9ecef; font-weight: bold;'>";
echo "<td>TOTAL</td>";
echo "<td>{$total_per_nb}</td>";
echo "<td></td>";
echo "</tr>";

echo "</table>";

echo "</div>";

// Result
$class = $total_per_nb > 0 ? 'good' : 'bad';
echo "<div class='section {$class}'>";
echo "<h2>🎯 Result</h2>";

if ($total_per_nb > 0) {
    echo "<p><strong style='color: #28a745; font-size: 16px;'>✅ FIX SUCCESSFUL!</strong></p>";
    echo "<p>canonical_builder now correctly extracts {$total_per_nb} citations from database!</p>";
    echo "<p><strong>Citations flow:</strong></p>";
    echo "<ul>";
    echo "<li>raw_collector input: {$total_citations_in} citations</li>";
    echo "<li>canonical_builder output: {$total_per_nb} citations</li>";
    echo "<li>Success rate: " . round(($total_per_nb / max($total_citations_in, 1)) * 100, 1) . "%</li>";
    echo "</ul>";
} else {
    echo "<p><strong style='color: #dc3545;'>❌ Fix didn't work</strong></p>";
    echo "<p>Citations still showing 0.</p>";
}

echo "</div>";

if ($total_per_nb > 0) {
    echo "<div class='section warning'>";
    echo "<h2>📝 Next Steps</h2>";
    echo "<p><strong>The fix is working!</strong> Now you need to:</p>";
    echo "<ol>";
    echo "<li>Delete the old canonical_nb_dataset artifact for Run 192</li>";
    echo "<li>Regenerate synthesis to create a new artifact with citations</li>";
    echo "<li>Verify the final report has full content</li>";
    echo "</ol>";
    echo "<p><a href='DELETE_RUN192_ARTIFACTS.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗑️ Delete Artifacts</a></p>";
    echo "<p><a href='regenerate_run192.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔄 Regenerate Synthesis</a></p>";
    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
