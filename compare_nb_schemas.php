<?php
/**
 * Compare NB schemas between different runs
 *
 * Shows the structure differences that may explain why pattern detection fails
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid1 = optional_param('run1', 0, PARAM_INT);
$runid2 = optional_param('run2', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/compare_nb_schemas.php'));
$PAGE->set_title("Compare NB Schemas");

echo $OUTPUT->header();

?>
<style>
.compare { font-family: Arial, sans-serif; max-width: 1400px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
th, td { padding: 8px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; font-weight: bold; }
.schema-box { background: white; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0; }
pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 400px; font-size: 11px; }
.field-centric { background: #d4edda; }
.company-centric { background: #fff3cd; }
.split { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
</style>

<div class="compare">

<h1>🔬 NB Schema Comparison</h1>

<?php

if (!$runid1 || !$runid2) {
    echo "<div class='section'>";
    echo "<h2>Select Runs to Compare</h2>";
    echo "<p>Usage: compare_nb_schemas.php?run1=X&run2=Y</p>";

    // Show available runs with NBs
    $runs = $DB->get_records_sql(
        "SELECT r.id, r.timecreated, r.status,
                COUNT(nb.id) as nb_count,
                s.id as synthesis_id,
                LENGTH(s.htmlcontent) as html_size
         FROM {local_ci_run} r
         LEFT JOIN {local_ci_nb} nb ON nb.runid = r.id
         LEFT JOIN {local_ci_synthesis} s ON s.runid = r.id
         WHERE r.status = 'completed'
         GROUP BY r.id
         HAVING nb_count > 0
         ORDER BY r.timecreated DESC
         LIMIT 20"
    );

    echo "<h3>Available Runs:</h3>";
    echo "<table>";
    echo "<tr><th>Run ID</th><th>Created</th><th>NBs</th><th>Synthesis Size</th><th>Action</th></tr>";

    $run_ids = [];
    foreach ($runs as $r) {
        $run_ids[] = $r->id;
        $html_info = $r->html_size ? number_format($r->html_size) . ' B' : 'No synthesis';
        $class = $r->html_size > 50000 ? 'success' : ($r->html_size > 5000 ? 'warning' : 'fail');

        echo "<tr class='{$class}'>";
        echo "<td>{$r->id}</td>";
        echo "<td>" . date('Y-m-d H:i', $r->timecreated) . "</td>";
        echo "<td>{$r->nb_count}</td>";
        echo "<td>{$html_info}</td>";
        echo "<td><a href='?run1={$r->id}&run2=192'>Compare with Run 192</a></td>";
        echo "</tr>";
    }

    echo "</table>";

    // Suggest comparison
    if (count($run_ids) >= 2) {
        echo "<p><strong>Suggested comparison:</strong> <a href='?run1={$run_ids[0]}&run2={$run_ids[1]}'>Run {$run_ids[0]} vs Run {$run_ids[1]}</a></p>";
    }

    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

// Compare the two runs
echo "<div class='section'>";
echo "<h2>Comparing Run {$runid1} vs Run {$runid2}</h2>";

$run1 = $DB->get_record('local_ci_run', ['id' => $runid1]);
$run2 = $DB->get_record('local_ci_run', ['id' => $runid2]);

if (!$run1 || !$run2) {
    echo "<p class='fail'>One or both runs not found!</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<table>";
echo "<tr><th>Metric</th><th>Run {$runid1}</th><th>Run {$runid2}</th></tr>";
echo "<tr><td>Created</td><td>" . date('Y-m-d H:i', $run1->timecreated) . "</td><td>" . date('Y-m-d H:i', $run2->timecreated) . "</td></tr>";
echo "<tr><td>Status</td><td>{$run1->status}</td><td>{$run2->status}</td></tr>";

$nb_count1 = $DB->count_records('local_ci_nb', ['runid' => $runid1]);
$nb_count2 = $DB->count_records('local_ci_nb', ['runid' => $runid2]);
echo "<tr><td>NB Count</td><td>{$nb_count1}</td><td>{$nb_count2}</td></tr>";

echo "</table>";
echo "</div>";

// Get sample NBs from each run
echo "<div class='section'>";
echo "<h2>NB Structure Comparison</h2>";

$nb1 = $DB->get_record_sql(
    "SELECT * FROM {local_ci_nb} WHERE runid = ? LIMIT 1",
    [$runid1]
);

$nb2 = $DB->get_record_sql(
    "SELECT * FROM {local_ci_nb} WHERE runid = ? LIMIT 1",
    [$runid2]
);

if (!$nb1 || !$nb2) {
    echo "<p class='fail'>Could not load NBs from one or both runs!</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<div class='split'>";

// Run 1 NB
echo "<div class='schema-box'>";
echo "<h3>Run {$runid1} - NB Schema</h3>";
echo "<p><strong>NB ID:</strong> {$nb1->id} (NB {$nb1->nbnumber})</p>";

$data1 = json_decode($nb1->payload, true);
if (!$data1) {
    echo "<p class='fail'>Failed to parse JSON</p>";
} else {
    // Analyze structure
    $top_keys = array_keys($data1);
    $structure_type = 'Unknown';

    // Check if it's field-centric (top-level keys are field names)
    $field_centric_keys = ['financial_pressures', 'strategic_capabilities', 'technological_infrastructure',
                           'market_evolution', 'organizational_dynamics'];
    $has_field_keys = count(array_intersect($top_keys, $field_centric_keys)) > 0;

    // Check if it's company-centric (top-level keys are company names)
    $has_company_keys = false;
    foreach ($top_keys as $key) {
        if (is_array($data1[$key]) && isset($data1[$key]['Section 1'])) {
            $has_company_keys = true;
            break;
        }
    }

    if ($has_field_keys) {
        $structure_type = 'Field-Centric ✅';
        echo "<p class='success'><strong>Structure:</strong> {$structure_type}</p>";
    } else if ($has_company_keys) {
        $structure_type = 'Company-Centric ⚠️';
        echo "<p class='warning'><strong>Structure:</strong> {$structure_type}</p>";
    } else {
        echo "<p class='fail'><strong>Structure:</strong> {$structure_type}</p>";
    }

    echo "<p><strong>Top-level keys:</strong></p>";
    echo "<ul>";
    foreach (array_slice($top_keys, 0, 10) as $key) {
        echo "<li>" . htmlspecialchars($key) . "</li>";
    }
    if (count($top_keys) > 10) {
        echo "<li><em>... and " . (count($top_keys) - 10) . " more</em></li>";
    }
    echo "</ul>";

    echo "<details>";
    echo "<summary>Full JSON structure (click to expand)</summary>";
    echo "<pre>" . htmlspecialchars(json_encode($data1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
    echo "</details>";
}

echo "</div>";

// Run 2 NB
echo "<div class='schema-box'>";
echo "<h3>Run {$runid2} - NB Schema</h3>";
echo "<p><strong>NB ID:</strong> {$nb2->id} (NB {$nb2->nbnumber})</p>";

$data2 = json_decode($nb2->payload, true);
if (!$data2) {
    echo "<p class='fail'>Failed to parse JSON</p>";
} else {
    // Analyze structure
    $top_keys = array_keys($data2);
    $structure_type = 'Unknown';

    $field_centric_keys = ['financial_pressures', 'strategic_capabilities', 'technological_infrastructure',
                           'market_evolution', 'organizational_dynamics'];
    $has_field_keys = count(array_intersect($top_keys, $field_centric_keys)) > 0;

    $has_company_keys = false;
    foreach ($top_keys as $key) {
        if (is_array($data2[$key]) && isset($data2[$key]['Section 1'])) {
            $has_company_keys = true;
            break;
        }
    }

    if ($has_field_keys) {
        $structure_type = 'Field-Centric ✅';
        echo "<p class='success'><strong>Structure:</strong> {$structure_type}</p>";
    } else if ($has_company_keys) {
        $structure_type = 'Company-Centric ⚠️';
        echo "<p class='warning'><strong>Structure:</strong> {$structure_type}</p>";
    } else {
        echo "<p class='fail'><strong>Structure:</strong> {$structure_type}</p>";
    }

    echo "<p><strong>Top-level keys:</strong></p>";
    echo "<ul>";
    foreach (array_slice($top_keys, 0, 10) as $key) {
        echo "<li>" . htmlspecialchars($key) . "</li>";
    }
    if (count($top_keys) > 10) {
        echo "<li><em>... and " . (count($top_keys) - 10) . " more</em></li>";
    }
    echo "</ul>";

    echo "<details>";
    echo "<summary>Full JSON structure (click to expand)</summary>";
    echo "<pre>" . htmlspecialchars(json_encode($data2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
    echo "</details>";
}

echo "</div>";

echo "</div>"; // end split

echo "</div>";

// Pattern detection compatibility check
echo "<div class='section'>";
echo "<h2>🔍 Pattern Detection Compatibility</h2>";

echo "<p>Pattern detection (used by M1T7 analysis_engine) expects field-centric structure:</p>";

echo "<div class='schema-box field-centric'>";
echo "<h4>✅ Expected Structure (Field-Centric):</h4>";
echo "<pre>{
  \"financial_pressures\": [
    {\"theme\": \"...\", \"evidence\": \"...\", \"citations\": [...]},
    {\"theme\": \"...\", \"evidence\": \"...\", \"citations\": [...]}
  ],
  \"strategic_capabilities\": [...],
  \"technological_infrastructure\": [...],
  ...
}</pre>";
echo "<p><strong>Why this works:</strong> Pattern detection can iterate over fields and extract themes directly.</p>";
echo "</div>";

echo "<div class='schema-box company-centric'>";
echo "<h4>⚠️ Incompatible Structure (Company-Centric):</h4>";
echo "<pre>{
  \"ViiV Healthcare\": {
    \"Section 1\": [...],
    \"Section 2\": [...],
    \"Section 3\": [...]
  },
  \"Merck\": {
    \"Section 1\": [...],
    \"Section 2\": [...],
    \"Section 3\": [...]
  },
  ...
}</pre>";
echo "<p><strong>Why this fails:</strong> Pattern detection looks for field keys, finds company names instead, extracts 0 patterns.</p>";
echo "</div>";

echo "</div>";

// Recommendation
echo "<div class='section'>";
echo "<h2>💡 Diagnosis & Solution</h2>";

$data1_structure = 'unknown';
$data2_structure = 'unknown';

if ($data1) {
    $top_keys1 = array_keys($data1);
    $field_keys = ['financial_pressures', 'strategic_capabilities', 'technological_infrastructure'];
    $data1_structure = count(array_intersect($top_keys1, $field_keys)) > 0 ? 'field-centric' : 'company-centric';
}

if ($data2) {
    $top_keys2 = array_keys($data2);
    $field_keys = ['financial_pressures', 'strategic_capabilities', 'technological_infrastructure'];
    $data2_structure = count(array_intersect($top_keys2, $field_keys)) > 0 ? 'field-centric' : 'company-centric';
}

if ($data1_structure === 'field-centric' && $data2_structure === 'company-centric') {
    echo "<div class='fail'>";
    echo "<h3>❌ Schema Incompatibility Detected</h3>";
    echo "<p><strong>Issue:</strong> Run {$runid1} has field-centric NBs (works with pattern detection), but Run {$runid2} has company-centric NBs (incompatible).</p>";
    echo "<p><strong>Result:</strong> Run {$runid2} will generate minimal content because pattern detection finds 0 patterns.</p>";
    echo "<h4>Solutions:</h4>";
    echo "<ol>";
    echo "<li><strong>Generate new NBs:</strong> Run a fresh report to get new NBs with field-centric schema</li>";
    echo "<li><strong>Update pattern detection:</strong> Modify pattern_comparator to handle company-centric structure</li>";
    echo "<li><strong>Convert cached NBs:</strong> Transform company-centric NBs to field-centric structure</li>";
    echo "</ol>";
    echo "</div>";

} else if ($data1_structure === $data2_structure) {
    echo "<div class='success'>";
    echo "<h3>✅ Schemas Match</h3>";
    echo "<p>Both runs use {$data1_structure} structure. If one generates full content and the other doesn't, the issue is elsewhere.</p>";
    echo "</div>";

} else {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Cannot Determine Schema Compatibility</h3>";
    echo "<p>Run {$runid1}: {$data1_structure}</p>";
    echo "<p>Run {$runid2}: {$data2_structure}</p>";
    echo "</div>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
