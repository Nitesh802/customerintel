<?php
/**
 * Debug Run 190 - Why is synthesis_engine using Run 4?
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/debug_run_190.php'));
$PAGE->set_title('Debug Run 190');

echo $OUTPUT->header();

?>
<style>
.debug { font-family: monospace; max-width: 1200px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.section h2 { margin-top: 0; color: #007bff; }
.alert { background: #dc3545; color: white; padding: 15px; border-radius: 5px; font-weight: bold; margin: 15px 0; }
.success { background: #28a745; color: white; padding: 15px; border-radius: 5px; font-weight: bold; margin: 15px 0; }
pre { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
th, td { padding: 10px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; font-weight: bold; }
.highlight { background: #fff3cd; font-weight: bold; }
</style>

<div class="debug">

<h1>🔍 Debug Run 190 - Run ID Mismatch Investigation</h1>

<?php

$runid = 190;

// =============================================================================
// CHECK RUN RECORD
// =============================================================================
echo "<div class='section'>";
echo "<h2>Run 190 Record</h2>";

$run = $DB->get_record('local_ci_run', ['id' => $runid]);

if (!$run) {
    echo "<p class='alert'>❌ Run {$runid} not found!</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<table>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>Run ID</td><td><strong>{$run->id}</strong></td></tr>";
echo "<tr><td>Company ID</td><td>{$run->companyid}</td></tr>";
echo "<tr><td>Target Company ID</td><td>{$run->targetcompanyid}</td></tr>";
echo "<tr><td>Status</td><td>{$run->status}</td></tr>";
echo "<tr><td>Created</td><td>" . date('Y-m-d H:i:s', $run->timecreated) . "</td></tr>";

// Check for cache decision field
if (isset($run->cache_strategy)) {
    echo "<tr class='highlight'><td>Cache Strategy</td><td>{$run->cache_strategy}</td></tr>";
}

if (isset($run->reusedfromrunid)) {
    echo "<tr class='highlight'><td>Reused From Run ID</td><td>{$run->reusedfromrunid}</td></tr>";
}

echo "</table>";

echo "<h3>Full Run Object</h3>";
echo "<pre>";
print_r($run);
echo "</pre>";

echo "</div>";

// =============================================================================
// FIND RUNS WITH SAME COMPANIES
// =============================================================================
echo "<div class='section'>";
echo "<h2>Other Runs with Same Companies</h2>";

$sql = "SELECT * FROM {local_ci_run}
        WHERE companyid = ? AND targetcompanyid = ?
        ORDER BY id DESC LIMIT 10";

$similar_runs = $DB->get_records_sql($sql, [$run->companyid, $run->targetcompanyid]);

if ($similar_runs) {
    echo "<p>Found " . count($similar_runs) . " runs with Company {$run->companyid} → {$run->targetcompanyid}</p>";

    echo "<table>";
    echo "<tr><th>Run ID</th><th>Status</th><th>Created</th><th>NBs</th><th>Synthesis?</th><th>Cache From</th></tr>";

    foreach ($similar_runs as $r) {
        $nb_count = $DB->count_records('local_ci_nb_result', ['runid' => $r->id]);
        $syn_exists = $DB->record_exists('local_ci_synthesis', ['runid' => $r->id]);
        $reused = $r->reusedfromrunid ?? '-';

        $row_class = ($r->id == $runid) ? " class='highlight'" : "";
        $row_class = ($r->id == 4) ? " style='background: #f8d7da;'" : $row_class;

        echo "<tr{$row_class}>";
        echo "<td><strong>{$r->id}</strong></td>";
        echo "<td>{$r->status}</td>";
        echo "<td>" . date('Y-m-d H:i', $r->timecreated) . "</td>";
        echo "<td>{$nb_count}/15</td>";
        echo "<td>" . ($syn_exists ? '✅ YES' : '❌ NO') . "</td>";
        echo "<td>{$reused}</td>";
        echo "</tr>";
    }

    echo "</table>";

    if ($DB->record_exists('local_ci_synthesis', ['runid' => 4])) {
        echo "<p class='alert'>🚨 Run 4 has synthesis! This may be why synthesis_engine redirects to it.</p>";
    }
} else {
    echo "<p>No other runs found with this company pair.</p>";
}

echo "</div>";

// =============================================================================
// TEST SYNTHESIS ENGINE CONSTRUCTOR
// =============================================================================
echo "<div class='section'>";
echo "<h2>Test synthesis_engine Constructor Behavior</h2>";

try {
    require_once(__DIR__ . '/classes/services/synthesis_engine.php');

    echo "<p>Creating synthesis_engine with runid = {$runid}...</p>";

    $engine = new \local_customerintel\services\synthesis_engine($runid);

    echo "<p>✅ Engine instantiated successfully</p>";

    // Use reflection to inspect the engine's internal runid
    echo "<h3>Reflection Inspection</h3>";

    $reflection = new ReflectionClass($engine);
    $properties = $reflection->getProperties();

    echo "<table>";
    echo "<tr><th>Property</th><th>Value</th></tr>";

    foreach ($properties as $prop) {
        $prop->setAccessible(true);
        $value = $prop->getValue($engine);
        $name = $prop->getName();

        if ($name === 'runid' || $name === 'companyid' || $name === 'targetid' || $name === 'targetcompanyid') {
            $highlight = ($name === 'runid' && $value != $runid) ? " class='highlight'" : "";
            echo "<tr{$highlight}>";
            echo "<td><strong>{$name}</strong></td>";
            echo "<td>" . var_export($value, true) . "</td>";
            echo "</tr>";

            if ($name === 'runid' && $value != $runid) {
                echo "<tr style='background: #dc3545; color: white;'>";
                echo "<td colspan='2'>🚨 BUG FOUND: Engine has runid = {$value}, expected {$runid}!</td>";
                echo "</tr>";
            }
        }
    }

    echo "</table>";

} catch (Exception $e) {
    echo "<p class='alert'>❌ Error instantiating synthesis_engine: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div>";

// =============================================================================
// CHECK SYNTHESIS RECORDS
// =============================================================================
echo "<div class='section'>";
echo "<h2>Synthesis Records Analysis</h2>";

// Check for Run 190
$syn_190 = $DB->get_record('local_ci_synthesis', ['runid' => 190]);
echo "<h3>Run 190 Synthesis</h3>";
if ($syn_190) {
    echo "<p>✅ Exists (ID: {$syn_190->id})</p>";
    echo "<p>Created: " . date('Y-m-d H:i:s', $syn_190->createdat) . "</p>";
    echo "<p>HTML Size: " . strlen($syn_190->htmlcontent ?? '') . " bytes</p>";
} else {
    echo "<p>❌ No synthesis for Run 190</p>";
}

// Check for Run 4
$syn_4 = $DB->get_record('local_ci_synthesis', ['runid' => 4]);
echo "<h3>Run 4 Synthesis</h3>";
if ($syn_4) {
    echo "<p style='background: #f8d7da; padding: 10px;'>⚠️ Exists (ID: {$syn_4->id})</p>";
    echo "<p>Created: " . date('Y-m-d H:i:s', $syn_4->createdat) . "</p>";
    echo "<p>HTML Size: " . strlen($syn_4->htmlcontent ?? '') . " bytes</p>";

    // Check if companies match
    if (isset($syn_4->source_company_id) && $syn_4->source_company_id == $run->companyid) {
        echo "<p class='alert'>🚨 Run 4 synthesis has SAME source_company_id ({$syn_4->source_company_id})!</p>";
        echo "<p class='alert'>This explains why synthesis_engine might redirect to Run 4!</p>";
    }
} else {
    echo "<p>❌ No synthesis for Run 4</p>";
}

echo "</div>";

// =============================================================================
// CHECK ARTIFACTS
// =============================================================================
echo "<div class='section'>";
echo "<h2>Artifact Records</h2>";

$artifacts_190 = $DB->get_records('local_ci_artifact', ['runid' => 190]);
$artifacts_4 = $DB->get_records('local_ci_artifact', ['runid' => 4]);

echo "<p>Run 190 artifacts: " . count($artifacts_190) . "</p>";
echo "<p>Run 4 artifacts: " . count($artifacts_4) . "</p>";

if (count($artifacts_4) > 0 && count($artifacts_190) == 0) {
    echo "<p class='alert'>🚨 Run 4 has artifacts but Run 190 doesn't - this suggests artifact caching issue!</p>";
}

echo "</div>";

// =============================================================================
// RECOMMENDATIONS
// =============================================================================
echo "<div class='section' style='border-left-color: #28a745; background: #d4edda;'>";
echo "<h2>🎯 Recommendations</h2>";

echo "<h3>Likely Root Cause</h3>";
echo "<p>The synthesis_engine appears to have a <strong>synthesis caching mechanism</strong> that finds existing synthesis for the same company pair and reuses it, regardless of the requested run ID.</p>";

echo "<h3>Solutions</h3>";
echo "<ol>";
echo "<li><strong>Delete Run 4 synthesis</strong> - This will force fresh generation for Run 190</li>";
echo "<li><strong>Fix synthesis_engine caching logic</strong> - Should respect the exact runid parameter</li>";
echo "<li><strong>Use truly_force_synthesis_190.php</strong> - Script with reflection hack to force correct runid</li>";
echo "</ol>";

if ($syn_4) {
    echo "<p style='margin-top: 20px;'><a href='?delete_run4_synthesis=1' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🗑️ Delete Run 4 Synthesis</a></p>";
}

echo "</div>";

// Handle deletion request
if (isset($_GET['delete_run4_synthesis']) && $_GET['delete_run4_synthesis'] == 1) {
    echo "<div class='section' style='border-left-color: #dc3545;'>";
    echo "<h2>Deleting Run 4 Synthesis...</h2>";

    try {
        // Delete sections first
        $sections = $DB->get_records('local_ci_synthesis_section', ['synthesisid' => $syn_4->id]);
        foreach ($sections as $section) {
            $DB->delete_records('local_ci_synthesis_section', ['id' => $section->id]);
        }
        echo "<p>Deleted " . count($sections) . " sections</p>";

        // Delete synthesis
        $DB->delete_records('local_ci_synthesis', ['id' => $syn_4->id]);
        echo "<p class='success'>✅ Deleted Run 4 synthesis (ID: {$syn_4->id})</p>";

        echo "<p><a href='force_fresh_synthesis_190.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Now Force Run 190 Synthesis</a></p>";

    } catch (Exception $e) {
        echo "<p class='alert'>❌ Error deleting: " . $e->getMessage() . "</p>";
    }

    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
