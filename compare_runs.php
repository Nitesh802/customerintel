<?php
/**
 * Compare NB Data Between Runs
 *
 * This diagnostic script compares NB generation between different runs
 * to determine if NBs are being generated at all.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url('/local/customerintel/compare_runs.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Compare Runs - NB Data Check');

echo $OUTPUT->header();

?>
<style>
.run-comparison { max-width: 1200px; margin: 20px auto; }
.run-card { background: #f9f9f9; border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin: 15px 0; }
.run-card.has-data { border-color: #28a745; background: #d4edda; }
.run-card.no-data { border-color: #dc3545; background: #f8d7da; }
.run-header { font-size: 24px; font-weight: bold; margin-bottom: 15px; }
.metric { display: inline-block; background: white; padding: 10px 20px; margin: 5px; border-radius: 5px; border: 1px solid #ddd; }
.metric-label { font-size: 12px; color: #666; }
.metric-value { font-size: 28px; font-weight: bold; color: #007bff; }
.nb-list { background: white; padding: 15px; border-radius: 5px; margin-top: 10px; max-height: 400px; overflow-y: auto; }
.nb-item { padding: 8px; border-bottom: 1px solid #eee; }
.nb-item:last-child { border-bottom: none; }
.nb-has-data { color: #28a745; }
.nb-no-data { color: #dc3545; }
.comparison-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; }
.comparison-table th, .comparison-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
.comparison-table th { background: #007bff; color: white; }
.verdict { padding: 20px; border-radius: 8px; margin: 20px 0; font-size: 18px; font-weight: bold; }
.verdict.good { background: #28a745; color: white; }
.verdict.bad { background: #dc3545; color: white; }
.verdict.mixed { background: #ffc107; color: #000; }
</style>

<div class="run-comparison">

<h1>🔍 Run NB Data Comparison</h1>

<p><strong>Purpose:</strong> Determine if NB generation is working by comparing different runs.</p>

<?php

// Allow specifying runs via URL parameters
$run_ids = [];
if (isset($_GET['runs'])) {
    $run_ids = explode(',', $_GET['runs']);
    $run_ids = array_map('intval', $run_ids);
} else {
    // Default: Compare Run 122 (known working) vs Run 177 (current test)
    $run_ids = [122, 177];
}

echo "<p><strong>Comparing Runs:</strong> " . implode(', ', $run_ids) . "</p>";

// Collect data for each run
$run_data = [];

foreach ($run_ids as $runid) {
    echo "<div class='run-card' id='run-$runid'>";
    echo "<div class='run-header'>Run #$runid</div>";

    // Get run record
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);

    if (!$run) {
        echo "<p class='text-danger'>❌ Run not found in database</p>";
        echo "</div>";
        continue;
    }

    // Display run details
    echo "<p><strong>Company:</strong> " . ($run->companyid ?? 'N/A') . "</p>";
    echo "<p><strong>Target:</strong> " . ($run->targetid ?? 'N/A') . "</p>";
    echo "<p><strong>Status:</strong> " . ($run->status ?? 'N/A') . "</p>";
    echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $run->timecreated) . "</p>";

    // Get NB results
    $nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);
    $nb_count = count($nbs);

    // Count NBs with actual data
    $nbs_with_data = 0;
    $total_data_size = 0;
    $nb_details = [];

    foreach ($nbs as $nb) {
        $data_size = !empty($nb->jsondata) ? strlen($nb->jsondata) : 0;
        $has_data = $data_size > 100; // More than 100 chars = real data

        if ($has_data) {
            $nbs_with_data++;
            $total_data_size += $data_size;
        }

        $nb_details[] = [
            'nbid' => $nb->nbid ?? 'Unknown',
            'type' => $nb->type ?? 'Unknown',
            'status' => $nb->status ?? 'Unknown',
            'data_size' => $data_size,
            'has_data' => $has_data
        ];
    }

    // Display metrics
    echo "<div style='margin: 20px 0;'>";

    echo "<div class='metric'>";
    echo "<div class='metric-label'>Total NBs</div>";
    echo "<div class='metric-value'>$nb_count</div>";
    echo "</div>";

    echo "<div class='metric'>";
    echo "<div class='metric-label'>NBs with Data</div>";
    echo "<div class='metric-value'>$nbs_with_data</div>";
    echo "</div>";

    if ($nb_count > 0) {
        $data_percentage = round(($nbs_with_data / $nb_count) * 100);
        echo "<div class='metric'>";
        echo "<div class='metric-label'>Data Coverage</div>";
        echo "<div class='metric-value'>$data_percentage%</div>";
        echo "</div>";
    }

    echo "<div class='metric'>";
    echo "<div class='metric-label'>Total Data Size</div>";
    echo "<div class='metric-value'>" . number_format($total_data_size) . " bytes</div>";
    echo "</div>";

    echo "</div>";

    // Verdict for this run
    if ($nbs_with_data > 0) {
        echo "<p class='text-success' style='font-size: 18px; font-weight: bold;'>✅ This run HAS NB data</p>";
        $verdict = 'has-data';
    } else {
        echo "<p class='text-danger' style='font-size: 18px; font-weight: bold;'>❌ This run has NO NB data</p>";
        $verdict = 'no-data';
    }

    // Show NB details
    if ($nb_count > 0) {
        echo "<h4>NB Details:</h4>";
        echo "<div class='nb-list'>";
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='border-bottom: 2px solid #ddd;'>";
        echo "<th>NB ID</th><th>Type</th><th>Status</th><th>Data Size</th><th>Has Data?</th>";
        echo "</tr>";

        foreach ($nb_details as $detail) {
            $data_class = $detail['has_data'] ? 'nb-has-data' : 'nb-no-data';
            $data_icon = $detail['has_data'] ? '✅' : '❌';

            echo "<tr class='nb-item'>";
            echo "<td>NB{$detail['nbid']}</td>";
            echo "<td>{$detail['type']}</td>";
            echo "<td>{$detail['status']}</td>";
            echo "<td>" . number_format($detail['data_size']) . " bytes</td>";
            echo "<td class='$data_class'>$data_icon " . ($detail['has_data'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "</div>";
    } else {
        echo "<p class='text-warning'>⚠️ No NB records found for this run</p>";
    }

    // Update run card class
    echo "<script>document.getElementById('run-$runid').classList.add('$verdict');</script>";

    echo "</div>"; // End run-card

    // Store data for comparison
    $run_data[] = [
        'runid' => $runid,
        'nb_count' => $nb_count,
        'nbs_with_data' => $nbs_with_data,
        'total_data_size' => $total_data_size,
        'has_data' => $nbs_with_data > 0
    ];
}

// Comparison summary
if (count($run_data) >= 2) {
    echo "<h2>📊 Comparison Summary</h2>";

    echo "<table class='comparison-table'>";
    echo "<tr>";
    echo "<th>Run ID</th>";
    echo "<th>Total NBs</th>";
    echo "<th>NBs with Data</th>";
    echo "<th>Total Data Size</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    foreach ($run_data as $data) {
        $status = $data['has_data'] ? '✅ Has Data' : '❌ No Data';
        $status_color = $data['has_data'] ? '#28a745' : '#dc3545';

        echo "<tr>";
        echo "<td><strong>Run {$data['runid']}</strong></td>";
        echo "<td>{$data['nb_count']}</td>";
        echo "<td>{$data['nbs_with_data']}</td>";
        echo "<td>" . number_format($data['total_data_size']) . " bytes</td>";
        echo "<td style='color: $status_color; font-weight: bold;'>$status</td>";
        echo "</tr>";
    }

    echo "</table>";
}

// Overall verdict
echo "<h2>🎯 Diagnosis</h2>";

$runs_with_data = array_filter($run_data, function($d) { return $d['has_data']; });
$runs_without_data = array_filter($run_data, function($d) { return !$d['has_data']; });

if (count($runs_with_data) > 0 && count($runs_without_data) > 0) {
    echo "<div class='verdict mixed'>";
    echo "⚠️ MIXED RESULTS: Some runs have data, some don't";
    echo "</div>";

    echo "<div class='alert alert-warning'>";
    echo "<h4>Analysis:</h4>";
    echo "<ul>";
    echo "<li><strong>" . count($runs_with_data) . " run(s) HAVE NB data</strong> - NB generation CAN work</li>";
    echo "<li><strong>" . count($runs_without_data) . " run(s) HAVE NO NB data</strong> - NB generation failed for these runs</li>";
    echo "</ul>";

    echo "<h4>Possible Reasons:</h4>";
    echo "<ol>";
    echo "<li><strong>Timing:</strong> Newer runs haven't had NBs generated yet (async process)</li>";
    echo "<li><strong>Configuration:</strong> Different settings/API keys between runs</li>";
    echo "<li><strong>Company Pairing:</strong> Some company pairs may be cached/skipped</li>";
    echo "<li><strong>NB Trigger:</strong> NBs may need manual trigger or cron job</li>";
    echo "</ol>";

    echo "<h4>Recommended Action:</h4>";
    echo "<p>1. Wait 5-10 minutes and refresh this page (NBs may be generating in background)</p>";
    echo "<p>2. Check if there's a 'Generate NBs' button on the run page</p>";
    echo "<p>3. Check cron job logs for NB generation</p>";
    echo "<p>4. Verify API keys are configured correctly</p>";
    echo "</div>";

} else if (count($runs_without_data) === count($run_data)) {
    echo "<div class='verdict bad'>";
    echo "❌ CRITICAL: NO RUNS HAVE NB DATA";
    echo "</div>";

    echo "<div class='alert alert-danger'>";
    echo "<h4>Problem:</h4>";
    echo "<p>None of the tested runs have any NB data. This indicates <strong>NB generation is completely broken</strong>.</p>";

    echo "<h4>Possible Causes:</h4>";
    echo "<ol>";
    echo "<li><strong>NB Orchestrator Not Running:</strong> The nb_orchestrator service isn't being called</li>";
    echo "<li><strong>API Keys Missing:</strong> OpenAI/Perplexity API keys not configured</li>";
    echo "<li><strong>Database Issue:</strong> NB results aren't being saved</li>";
    echo "<li><strong>Cron Job Disabled:</strong> Scheduled NB generation isn't running</li>";
    echo "<li><strong>Network Issue:</strong> Can't reach external APIs</li>";
    echo "</ol>";

    echo "<h4>Immediate Actions:</h4>";
    echo "<p>1. Check admin settings: /local/customerintel/admin_settings.php</p>";
    echo "<p>2. Verify API keys are set</p>";
    echo "<p>3. Check error logs for NB generation failures</p>";
    echo "<p>4. Test API connectivity: /local/customerintel/cli/test_api_keys.php</p>";
    echo "</div>";

} else {
    echo "<div class='verdict good'>";
    echo "✅ SUCCESS: ALL RUNS HAVE NB DATA";
    echo "</div>";

    echo "<div class='alert alert-success'>";
    echo "<h4>Good News:</h4>";
    echo "<p>All tested runs have NB data, which means <strong>NB generation is working correctly</strong>.</p>";

    echo "<h4>This Means:</h4>";
    echo "<ul>";
    echo "<li>✅ NB orchestrator is functioning</li>";
    echo "<li>✅ API calls are being made successfully</li>";
    echo "<li>✅ NB results are being saved to database</li>";
    echo "<li>✅ System is ready for synthesis</li>";
    echo "</ul>";

    echo "<h4>Next Step:</h4>";
    echo "<p>If synthesis is still failing, the issue is in the synthesis pipeline (M1T5-8), not NB generation.</p>";
    echo "</div>";
}

?>

<h2>🔧 Additional Diagnostics</h2>

<div class="alert alert-info">
    <h4>Want to check more runs?</h4>
    <p>Add run IDs to URL: <code>?runs=122,177,128,175</code></p>
    <p>Example: <a href="?runs=122,177,128,175">Compare Runs 122, 177, 128, 175</a></p>
</div>

<div class="alert alert-info">
    <h4>Next Steps Based on Results:</h4>
    <ul>
        <li><strong>If Run 122 has data:</strong> Good! That means NB generation worked before. Check what's different about Run 177.</li>
        <li><strong>If Run 122 has NO data:</strong> NB generation was never working. The previous "success" was likely cached/fallback content.</li>
        <li><strong>If both have data:</strong> Synthesis issue, not NB issue. M1T5-8 services may need debugging.</li>
        <li><strong>If neither has data:</strong> NB generation is broken. Check API keys, cron jobs, and orchestrator.</li>
    </ul>
</div>

</div>

<?php
echo $OUTPUT->footer();
?>
