<?php
/**
 * Compare Run 189 vs Run 190 - Database Analysis
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_run_189_vs_190.php'));
$PAGE->set_title('Run 189 vs 190 Comparison');

echo $OUTPUT->header();

?>
<style>
.comparison { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; }
.run-section { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.run-section h2 { margin-top: 0; color: #007bff; }
.metric { display: inline-block; background: white; padding: 10px 15px; margin: 5px; border-radius: 3px; border: 1px solid #dee2e6; }
.metric-label { font-weight: bold; margin-right: 10px; color: #495057; }
.metric-value { color: #007bff; font-size: 1.1em; }
.success { color: #28a745; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
th, td { padding: 12px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; font-weight: bold; }
.nb-row { font-family: monospace; font-size: 0.9em; }
</style>

<div class="comparison">

<h1>🔍 Run 189 vs 190 Database Comparison</h1>

<?php

foreach ([189, 190] as $runid) {
    echo "<div class='run-section'>";
    echo "<h2>Run {$runid}</h2>";

    // Get run details
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);
    if (!$run) {
        echo "<p class='fail'>❌ Run {$runid} not found in database!</p>";
        echo "</div>";
        continue;
    }

    echo "<div class='metric'><span class='metric-label'>Status:</span><span class='metric-value'>{$run->status}</span></div>";
    echo "<div class='metric'><span class='metric-label'>Created:</span><span class='metric-value'>" . date('Y-m-d H:i:s', $run->timecreated) . "</span></div>";

    if ($run->timecompleted) {
        $duration = $run->timecompleted - $run->timestarted;
        echo "<div class='metric'><span class='metric-label'>Duration:</span><span class='metric-value'>" . gmdate('i:s', $duration) . " (mm:ss)</span></div>";
    }

    echo "<h3>NB Records Analysis</h3>";

    // Get all NBs
    $nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');
    $nb_count = count($nbs);

    echo "<p><strong>NBs in local_ci_nb_result: {$nb_count}/15</strong></p>";

    if ($nb_count === 0) {
        echo "<p class='fail'>❌ NO NBs in database for Run {$runid}!</p>";
    } else {
        // Calculate totals
        $total_size = 0;
        $total_tokens = 0;
        $total_duration = 0;
        $completed = 0;
        $cache_hits = 0;

        echo "<table>";
        echo "<tr><th>NB Code</th><th>Status</th><th>Data Size</th><th>Tokens</th><th>Duration (ms)</th><th>Created</th></tr>";

        foreach ($nbs as $nb) {
            $data_size = !empty($nb->jsonpayload) ? strlen($nb->jsonpayload) : 0;
            $total_size += $data_size;
            $total_tokens += $nb->tokensused ?? 0;
            $total_duration += $nb->durationms ?? 0;

            if ($nb->status === 'completed') {
                $completed++;
            }

            if (($nb->tokensused ?? 0) === 0 && ($nb->durationms ?? 0) === 0) {
                $cache_hits++;
            }

            $status_class = $nb->status === 'completed' ? 'success' : 'fail';
            $created = date('H:i:s', $nb->timecreated);

            echo "<tr class='nb-row'>";
            echo "<td>{$nb->nbcode}</td>";
            echo "<td class='{$status_class}'>{$nb->status}</td>";
            echo "<td>" . number_format($data_size) . " bytes</td>";
            echo "<td>" . ($nb->tokensused ?? 0) . "</td>";
            echo "<td>" . ($nb->durationms ?? 0) . "</td>";
            echo "<td>{$created}</td>";
            echo "</tr>";
        }

        echo "</table>";

        echo "<h4>Summary Metrics</h4>";
        echo "<div class='metric'><span class='metric-label'>Total Data:</span><span class='metric-value'>" . number_format($total_size) . " bytes</span></div>";
        echo "<div class='metric'><span class='metric-label'>Total Tokens:</span><span class='metric-value'>" . number_format($total_tokens) . "</span></div>";
        echo "<div class='metric'><span class='metric-label'>Total Duration:</span><span class='metric-value'>" . round($total_duration / 1000, 2) . " sec</span></div>";
        echo "<div class='metric'><span class='metric-label'>Completed:</span><span class='metric-value'>{$completed}/15</span></div>";

        if ($cache_hits > 0) {
            echo "<div class='metric' style='background: #fff3cd;'><span class='metric-label'>Suspected Cache Hits:</span><span class='metric-value'>{$cache_hits} NBs</span></div>";
        }

        // Check if all NBs created at same time (copied run indicator)
        $timestamps = array_map(function($nb) { return $nb->timecreated; }, $nbs);
        $unique_timestamps = array_unique($timestamps);

        if (count($unique_timestamps) === 1) {
            echo "<p class='warning'>⚠️ All NBs created at EXACT same timestamp - likely a COPIED RUN</p>";
        } else {
            echo "<p class='success'>✅ NBs created at different times - likely GENERATED</p>";
            echo "<p style='font-size: 0.9em;'>Timestamp spread: " . count($unique_timestamps) . " different timestamps</p>";
        }
    }

    // Check synthesis
    echo "<h3>Synthesis Record</h3>";
    $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

    if ($synthesis) {
        echo "<p class='success'>✅ Synthesis record found (ID: {$synthesis->id})</p>";

        $html_size = strlen($synthesis->htmlcontent ?? '');
        $json_size = strlen($synthesis->jsoncontent ?? '');

        echo "<div class='metric'><span class='metric-label'>HTML Size:</span><span class='metric-value'>" . number_format($html_size) . " bytes</span></div>";
        echo "<div class='metric'><span class='metric-label'>JSON Size:</span><span class='metric-value'>" . number_format($json_size) . " bytes</span></div>";

        if ($html_size < 1000) {
            echo "<p class='warning'>⚠️ Very small synthesis content - likely ran on empty NBs</p>";
        }
    } else {
        echo "<p class='fail'>❌ No synthesis record found</p>";
    }

    // Check trace logs
    echo "<h3>Trace Log Markers</h3>";
    $traces = $DB->get_records_sql(
        "SELECT * FROM {local_ci_trace}
         WHERE runid = ?
         AND (event LIKE '%CACHE%' OR event LIKE '%COPIED%' OR event LIKE '%NB_ORCHESTRATION%')
         ORDER BY timecreated ASC",
        [$runid]
    );

    if ($traces) {
        echo "<table>";
        echo "<tr><th>Event</th><th>Payload</th><th>Time</th></tr>";

        foreach ($traces as $trace) {
            $time = date('H:i:s', $trace->timecreated);
            $payload = !empty($trace->payload) ? substr($trace->payload, 0, 100) : '-';

            echo "<tr>";
            echo "<td style='font-family: monospace;'>{$trace->event}</td>";
            echo "<td style='font-family: monospace; font-size: 0.85em;'>" . htmlspecialchars($payload) . "</td>";
            echo "<td>{$time}</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p>No relevant trace markers found</p>";
    }

    echo "</div>"; // run-section
}

?>

<div class="run-section" style="border-left-color: #6c757d;">
<h2>📊 Comparison Summary</h2>

<?php

$run189_nbs = $DB->count_records('local_ci_nb_result', ['runid' => 189]);
$run190_nbs = $DB->count_records('local_ci_nb_result', ['runid' => 190]);

echo "<table>";
echo "<tr><th>Metric</th><th>Run 189</th><th>Run 190</th><th>Analysis</th></tr>";

echo "<tr>";
echo "<td><strong>NB Count</strong></td>";
echo "<td>{$run189_nbs}/15</td>";
echo "<td>{$run190_nbs}/15</td>";

if ($run189_nbs === 15 && $run190_nbs === 15) {
    echo "<td class='success'>✅ Both have all 15 NBs</td>";
} else {
    echo "<td class='fail'>❌ At least one missing NBs</td>";
}
echo "</tr>";

// Check if runs have synthesis
$syn189 = $DB->get_record('local_ci_synthesis', ['runid' => 189]);
$syn190 = $DB->get_record('local_ci_synthesis', ['runid' => 190]);

echo "<tr>";
echo "<td><strong>Synthesis</strong></td>";
echo "<td>" . ($syn189 ? "✅ Yes" : "❌ No") . "</td>";
echo "<td>" . ($syn190 ? "✅ Yes" : "❌ No") . "</td>";

if (!$syn189 && !$syn190) {
    echo "<td class='fail'>❌ Neither has synthesis</td>";
} else if ($syn189 && $syn190) {
    echo "<td class='success'>✅ Both have synthesis</td>";
} else {
    echo "<td class='warning'>⚠️ Only one has synthesis</td>";
}
echo "</tr>";

echo "</table>";

?>

<h3>Key Findings</h3>
<ul>
<li>Both runs should be examined for trace markers indicating if they were:
  <ul>
    <li><strong>Generated</strong>: NB_ORCHESTRATION with 180,000-300,000ms duration</li>
    <li><strong>Cached</strong>: Cache hit markers with 0 tokens, <10s duration</li>
    <li><strong>Copied</strong>: NB_COPIED_COUNT, CACHED_FROM_RUNID markers</li>
  </ul>
</li>
<li>The timestamp spread tells us if NBs were generated sequentially (different times) or copied/cached (same time)</li>
<li>Zero tokens + zero duration = either cache hits OR copied records</li>
</ul>

</div>

</div>

<?php

echo $OUTPUT->footer();

?>
