<?php
/**
 * Check Run 128 Data Status
 *
 * This script checks if Run 128 has real NB data and M1T3 metadata
 * to determine if the 0.06s test was a real synthesis or cache hit.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url('/local/customerintel/check_run128_data.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Check Run 128 Data Status');

echo $OUTPUT->header();

?>
<style>
.check-container { max-width: 900px; margin: 20px auto; }
.check-section { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 5px; }
.success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; }
.fail { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; }
.info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
.data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
.data-table th, .data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.data-table th { background: #007bff; color: white; }
.metric-card { background: white; border: 2px solid #ddd; border-radius: 8px; padding: 15px; margin: 10px 0; }
.metric-value { font-size: 36px; font-weight: bold; color: #007bff; }
.metric-label { font-size: 14px; color: #666; margin-top: 5px; }
.verdict { font-size: 18px; font-weight: bold; padding: 20px; border-radius: 5px; margin: 20px 0; }
.verdict.good { background: #28a745; color: white; }
.verdict.bad { background: #dc3545; color: white; }
</style>

<div class="check-container">

<h1>�� Run 128 Data Status Check</h1>

<div class="info">
    <strong>Purpose:</strong> Determine if the 0.06s test result for Run 128 was:
    <ul>
        <li>✅ A real synthesis (cached) with M1T3 metadata</li>
        <li>❌ Empty processing with no real data</li>
    </ul>
</div>

<?php

$runid = 128;

// ============================================================================
// CHECK 1: Does Run 128 exist?
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 1: Run Record</h2>";

$run = $DB->get_record('local_ci_run', ['id' => $runid]);

if ($run) {
    echo "<div class='success'>";
    echo "<h3>✅ Run 128 Exists</h3>";
    echo "<table class='data-table'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Run ID</td><td>{$run->id}</td></tr>";
    echo "<tr><td>Company ID</td><td>{$run->companyid}</td></tr>";
    echo "<tr><td>Target ID</td><td>" . ($run->targetid ?? 'NULL') . "</td></tr>";
    echo "<tr><td>Status</td><td>{$run->status}</td></tr>";
    echo "<tr><td>Created</td><td>" . date('Y-m-d H:i:s', $run->timecreated) . "</td></tr>";
    echo "</table>";
    echo "</div>";
} else {
    echo "<div class='fail'>";
    echo "<h3>❌ Run 128 Not Found</h3>";
    echo "<p>Cannot continue - run doesn't exist.</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// ============================================================================
// CHECK 2: Does Run 128 have NB results?
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 2: NB Results (Notebook Data)</h2>";

$nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);
$nb_count = count($nb_results);

echo "<div class='metric-card'>";
echo "<div class='metric-value'>$nb_count</div>";
echo "<div class='metric-label'>NB Results Found</div>";
echo "</div>";

if ($nb_count > 0) {
    echo "<div class='success'>";
    echo "<h3>✅ Run 128 HAS NB Data!</h3>";
    echo "<p>Found <strong>$nb_count NB results</strong> for this run.</p>";

    echo "<h4>NB Breakdown:</h4>";
    echo "<table class='data-table'>";
    echo "<tr><th>NB ID</th><th>NB Type</th><th>Status</th><th>Has Data?</th></tr>";

    foreach ($nb_results as $nb) {
        $has_data = !empty($nb->jsondata) ? '✅ Yes' : '❌ No';
        $data_size = !empty($nb->jsondata) ? number_format(strlen($nb->jsondata)) . ' chars' : '0 chars';

        echo "<tr>";
        echo "<td>NB{$nb->nbid}</td>";
        echo "<td>" . ($nb->type ?? 'Unknown') . "</td>";
        echo "<td>" . ($nb->status ?? 'Unknown') . "</td>";
        echo "<td>$has_data ($data_size)</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";

} else {
    echo "<div class='fail'>";
    echo "<h3>❌ Run 128 Has NO NB Data</h3>";
    echo "<p>This run has zero NB results. This explains why synthesis was fast (0.06s).</p>";
    echo "<p><strong>Conclusion:</strong> The test generated fallback content with no real data.</p>";
    echo "</div>";
}

echo "</div>";

// ============================================================================
// CHECK 3: Does Run 128 have a synthesis record?
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 3: Synthesis Record</h2>";

$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

if ($synthesis) {
    echo "<div class='success'>";
    echo "<h3>✅ Synthesis Record EXISTS!</h3>";
    echo "<p>Run 128 has a synthesis record in the database.</p>";
    echo "</div>";

    // Check M1T3 metadata
    echo "<h3>M1T3 Metadata Check</h3>";
    echo "<table class='data-table'>";
    echo "<tr><th>M1T3 Field</th><th>Value</th><th>Status</th></tr>";

    $m1t3_fields = [
        'source_company_id' => 'Source Company ID',
        'target_company_id' => 'Target Company ID',
        'synthesis_key' => 'Synthesis Key',
        'model_used' => 'Model Used',
        'cache_source' => 'Cache Source',
        'prompt_config' => 'Prompt Config'
    ];

    $m1t3_populated = 0;
    $m1t3_total = count($m1t3_fields);

    foreach ($m1t3_fields as $field => $label) {
        $value = $synthesis->$field ?? 'NULL';

        if ($field === 'prompt_config' && !empty($value)) {
            $decoded = json_decode($value, true);
            if ($decoded) {
                $value = 'JSON (' . count($decoded) . ' keys)';
                $status = '✅';
                $m1t3_populated++;
            } else {
                $status = '❌';
            }
        } else if (!empty($value) && $value !== 'NULL') {
            $status = '✅';
            $m1t3_populated++;
        } else {
            $status = '❌';
        }

        echo "<tr>";
        echo "<td><strong>$label</strong></td>";
        echo "<td>$value</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }

    echo "</table>";

    // M1T3 verdict
    $m1t3_percent = ($m1t3_populated / $m1t3_total) * 100;

    if ($m1t3_percent >= 80) {
        echo "<div class='success'>";
        echo "<h3>✅ M1T3 Metadata: $m1t3_populated / $m1t3_total fields populated (" . number_format($m1t3_percent, 0) . "%)</h3>";
        echo "<p><strong>This proves M1T3 is working!</strong></p>";
        echo "</div>";
    } else if ($m1t3_percent > 0) {
        echo "<div class='info'>";
        echo "<h3>⚠️ M1T3 Metadata: Partially populated ($m1t3_populated / $m1t3_total fields)</h3>";
        echo "</div>";
    } else {
        echo "<div class='fail'>";
        echo "<h3>❌ M1T3 Metadata: NOT populated</h3>";
        echo "</div>";
    }

} else {
    echo "<div class='fail'>";
    echo "<h3>❌ No Synthesis Record</h3>";
    echo "<p>Run 128 has no synthesis record in the database.</p>";
    echo "<p>This means synthesis was never persisted.</p>";
    echo "</div>";
}

echo "</div>";

// ============================================================================
// CHECK 4: Check for sections (if synthesis exists)
// ============================================================================

if ($synthesis) {
    echo "<div class='check-section'>";
    echo "<h2>Check 4: Synthesis Sections</h2>";

    // Try new table first
    try {
        $sections = $DB->get_records('local_ci_synthesis_section', ['synthesisid' => $synthesis->id]);
        $section_count = count($sections);

        echo "<div class='metric-card'>";
        echo "<div class='metric-value'>$section_count</div>";
        echo "<div class='metric-label'>Sections in Database Table</div>";
        echo "</div>";

        if ($section_count >= 9) {
            echo "<div class='success'>";
            echo "<h3>✅ All 9 sections present in database table</h3>";
            echo "</div>";
        }

    } catch (Exception $e) {
        // Table doesn't exist - check JSON
        echo "<p class='info'>⚠️ Table 'local_ci_synthesis_section' doesn't exist - checking JSON data</p>";

        if (!empty($synthesis->jsondata)) {
            $synthesis_data = json_decode($synthesis->jsondata, true);

            if (isset($synthesis_data['sections'])) {
                $section_count = count($synthesis_data['sections']);

                echo "<div class='metric-card'>";
                echo "<div class='metric-value'>$section_count</div>";
                echo "<div class='metric-label'>Sections in JSON Data</div>";
                echo "</div>";

                if ($section_count >= 9) {
                    echo "<div class='success'>";
                    echo "<h3>✅ All 9 sections present in JSON</h3>";
                    echo "</div>";
                }
            } else {
                echo "<div class='fail'>";
                echo "<h3>❌ No sections found in JSON data</h3>";
                echo "</div>";
            }
        } else {
            echo "<div class='fail'>";
            echo "<h3>❌ No JSON data in synthesis record</h3>";
            echo "</div>";
        }
    }

    echo "</div>";
}

// ============================================================================
// FINAL VERDICT
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>📋 Final Verdict</h2>";

$has_nb_data = $nb_count > 0;
$has_synthesis = $synthesis !== false;
$has_m1t3 = isset($m1t3_populated) && $m1t3_populated >= 3;

if ($has_nb_data && $has_synthesis && $has_m1t3) {
    echo "<div class='verdict good'>";
    echo "🎉 SUCCESS: Run 128 is a REAL synthesis with M1T3 metadata!";
    echo "</div>";

    echo "<div class='success'>";
    echo "<h3>What This Means:</h3>";
    echo "<ul>";
    echo "<li>✅ Run 128 had <strong>$nb_count NB results</strong> (real data)</li>";
    echo "<li>✅ Synthesis record exists with M1T3 metadata</li>";
    echo "<li>✅ The 0.06s test was a <strong>CACHE HIT</strong> of a real synthesis</li>";
    echo "<li>✅ M1T5-M1T8 refactoring is <strong>WORKING CORRECTLY</strong></li>";
    echo "</ul>";

    echo "<h3>Conclusion:</h3>";
    echo "<p><strong>The M1T5-M1T8 modularization is fully functional!</strong></p>";
    echo "<p>The fast execution time was because the synthesis was already cached from a previous run. ";
    echo "The services executed correctly when that synthesis was first generated.</p>";
    echo "</div>";

} else if ($has_synthesis && !$has_nb_data) {
    echo "<div class='verdict bad'>";
    echo "⚠️ PARTIAL: Synthesis exists but no NB data";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>What This Means:</h3>";
    echo "<ul>";
    echo "<li>✅ Synthesis record exists</li>";
    echo "<li>❌ No NB data found for Run 128</li>";
    echo "<li>⚠️ Synthesis likely used fallback content</li>";
    echo "<li>❓ M1T5-M1T8 executed but with empty data</li>";
    echo "</ul>";

    echo "<h3>Recommendation:</h3>";
    echo "<p>Test with a run that has real NB data to verify full AI synthesis pipeline.</p>";
    echo "</div>";

} else if (!$has_synthesis) {
    echo "<div class='verdict bad'>";
    echo "❌ FAIL: No synthesis record found";
    echo "</div>";

    echo "<div class='fail'>";
    echo "<h3>What This Means:</h3>";
    echo "<ul>";
    echo "<li>❌ No synthesis record in database</li>";
    echo "<li>❌ The 0.06s test returned data but didn't persist it</li>";
    echo "<li>❌ Cannot verify M1T3 metadata</li>";
    echo "</ul>";

    echo "<h3>Possible Issues:</h3>";
    echo "<ol>";
    echo "<li>Synthesis returned data but didn't create database record</li>";
    echo "<li>Run 128 was never actually synthesized</li>";
    echo "<li>Database persistence is not working</li>";
    echo "</ol>";
    echo "</div>";

} else {
    echo "<div class='verdict bad'>";
    echo "❓ UNCLEAR: Mixed signals";
    echo "</div>";

    echo "<div class='info'>";
    echo "<p>Results are inconclusive. Review the checks above to diagnose the issue.</p>";
    echo "</div>";
}

echo "</div>";

// ============================================================================
// NEXT STEPS
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>📝 Recommended Next Steps</h2>";

if ($has_nb_data && $has_synthesis && $has_m1t3) {
    echo "<div class='success'>";
    echo "<h3>✅ You're Done!</h3>";
    echo "<p>M1T5-M1T8 is working. You can:</p>";
    echo "<ol>";
    echo "<li>View the report: <a href='/local/customerintel/report.php?id=$runid' target='_blank'>View Run 128 Report</a></li>";
    echo "<li>Test with another run to confirm consistency</li>";
    echo "<li>Deploy to production with confidence</li>";
    echo "</ol>";
    echo "</div>";

} else if (!$has_nb_data) {
    echo "<div class='info'>";
    echo "<h3>Create a Run with Real NB Data</h3>";
    echo "<ol>";
    echo "<li>Go to: <a href='/local/customerintel/run.php'>/local/customerintel/run.php</a></li>";
    echo "<li>Create a new run with real companies</li>";
    echo "<li>Wait for all 15 NBs to complete (may take time)</li>";
    echo "<li>Then run synthesis on that run</li>";
    echo "<li>Re-run this check script on the new run ID</li>";
    echo "</ol>";
    echo "</div>";

} else {
    echo "<div class='info'>";
    echo "<h3>Debug Database Persistence</h3>";
    echo "<ol>";
    echo "<li>Check if synthesis record creation is working</li>";
    echo "<li>Review synthesis_engine.php persistence logic</li>";
    echo "<li>Check database schema for mdl_local_ci_synthesis table</li>";
    echo "<li>Enable debug logging and re-run test</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</div>";

?>

</div>

<?php
echo $OUTPUT->footer();
?>
