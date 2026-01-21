<?php
/**
 * Compare NB schemas between Run 128 (working) and Run 192 (broken)
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/compare_run_schemas.php'));
$PAGE->set_title("Compare Run Schemas");

echo $OUTPUT->header();

?>
<style>
.compare { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 10px; max-height: 300px; }
</style>

<div class="compare">

<h1>🔍 Compare NB Schemas - Run 128 vs Run 192</h1>

<?php

// Get Run 128 NB-1
$run128_nb = $DB->get_record('local_ci_nb_result', ['runid' => 128, 'nbcode' => 'NB-1']);

echo "<div class='section'>";
echo "<h2>Run 128 (Working) - NB-1 Structure</h2>";

if ($run128_nb && $run128_nb->jsonpayload) {
    $data128 = json_decode($run128_nb->jsonpayload, true);

    echo "<p><strong>Top-level keys:</strong></p>";
    echo "<pre>";
    print_r(array_keys($data128));
    echo "</pre>";

    echo "<p><strong>Sample of first 1000 characters:</strong></p>";
    echo "<pre>";
    echo htmlspecialchars(substr(json_encode($data128, JSON_PRETTY_PRINT), 0, 1000));
    echo "\n...\n";
    echo "</pre>";
} else {
    echo "<p class='bad'>❌ Run 128 NB-1 not found</p>";
}

echo "</div>";

// Get Run 192 NB-1
$run192_nb = $DB->get_record('local_ci_nb_result', ['runid' => 192, 'nbcode' => 'NB-1']);

echo "<div class='section'>";
echo "<h2>Run 192 (Broken) - NB-1 Structure</h2>";

if ($run192_nb && $run192_nb->jsonpayload) {
    $data192 = json_decode($run192_nb->jsonpayload, true);

    echo "<p><strong>Top-level keys:</strong></p>";
    echo "<pre>";
    print_r(array_keys($data192));
    echo "</pre>";

    echo "<p><strong>Sample of first 1000 characters:</strong></p>";
    echo "<pre>";
    echo htmlspecialchars(substr(json_encode($data192, JSON_PRETTY_PRINT), 0, 1000));
    echo "\n...\n";
    echo "</pre>";
} else {
    echo "<p class='bad'>❌ Run 192 NB-1 not found</p>";
}

echo "</div>";

// Comparison
echo "<div class='section warning'>";
echo "<h2>🔬 Schema Comparison</h2>";

if ($run128_nb && $run192_nb) {
    $data128 = json_decode($run128_nb->jsonpayload, true);
    $data192 = json_decode($run192_nb->jsonpayload, true);

    $keys128 = array_keys($data128);
    $keys192 = array_keys($data192);

    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Run 128 Keys</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Run 192 Keys</th>";
    echo "</tr>";
    echo "<tr>";
    echo "<td style='padding: 8px; border: 1px solid #ddd; vertical-align: top;'><pre>" . implode("\n", $keys128) . "</pre></td>";
    echo "<td style='padding: 8px; border: 1px solid #ddd; vertical-align: top;'><pre>" . implode("\n", $keys192) . "</pre></td>";
    echo "</tr>";
    echo "</table>";

    if ($keys128 === $keys192) {
        echo "<p class='good'>✅ Schemas match!</p>";
    } else {
        echo "<p class='bad'>❌ Schemas DON'T match!</p>";
        echo "<p>Run 128 and Run 192 have different NB payload structures.</p>";
    }
}

echo "</div>";

// Check what pattern detection expects
echo "<div class='section'>";
echo "<h2>What Pattern Detection Expects</h2>";
echo "<p>From analysis_engine.php, it looks for these fields:</p>";
echo "<ul>";
echo "<li><strong>NB1:</strong> 'financial_pressures', 'pressures', 'challenges'</li>";
echo "<li><strong>NB2:</strong> 'revenue_trends', 'growth_metrics'</li>";
echo "<li><strong>NB3:</strong> 'inefficiencies', 'operational_issues', 'gaps'</li>";
echo "<li><strong>NB4:</strong> 'priorities', 'strategic_priorities'</li>";
echo "</ul>";

echo "<p><strong>Check Run 128:</strong></p>";
if ($run128_nb) {
    $data128 = json_decode($run128_nb->jsonpayload, true);
    echo "<ul>";
    echo "<li>'financial_pressures': " . (isset($data128['financial_pressures']) ? '✅' : '❌') . "</li>";
    echo "<li>'pressures': " . (isset($data128['pressures']) ? '✅' : '❌') . "</li>";
    echo "<li>'challenges': " . (isset($data128['challenges']) ? '✅' : '❌') . "</li>";
    echo "</ul>";
}

echo "<p><strong>Check Run 192:</strong></p>";
if ($run192_nb) {
    $data192 = json_decode($run192_nb->jsonpayload, true);
    echo "<ul>";
    echo "<li>'financial_pressures': " . (isset($data192['financial_pressures']) ? '✅' : '❌') . "</li>";
    echo "<li>'pressures': " . (isset($data192['pressures']) ? '✅' : '❌') . "</li>";
    echo "<li>'challenges': " . (isset($data192['challenges']) ? '✅' : '❌') . "</li>";
    echo "</ul>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
