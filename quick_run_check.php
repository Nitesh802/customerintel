<?php
/**
 * Quick Run Check - Simple diagnostics for a specific run
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$runid = required_param('runid', PARAM_INT);

header('Content-Type: text/plain');

echo "=" . str_repeat("=", 70) . "\n";
echo "QUICK RUN CHECK - Run {$runid}\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// Get run details
$run = $DB->get_record('local_ci_run', ['id' => $runid]);
if (!$run) {
    echo "ERROR: Run {$runid} not found\n";
    exit;
}

$source = $DB->get_record('local_ci_company', ['id' => $run->companyid], 'name');
$target = $DB->get_record('local_ci_company', ['id' => $run->targetid], 'name');

echo "RUN DETAILS:\n";
echo "  Source: {$source->name} (ID: {$run->companyid})\n";
echo "  Target: {$target->name} (ID: {$run->targetid})\n";
echo "  Status: {$run->status}\n";
echo "  Created: " . date('Y-m-d H:i:s', $run->timecreated) . "\n";

if ($run->timestarted) {
    echo "  Started: " . date('Y-m-d H:i:s', $run->timestarted) . "\n";
}
if ($run->timecompleted) {
    echo "  Completed: " . date('Y-m-d H:i:s', $run->timecompleted) . "\n";
    $duration = $run->timecompleted - $run->timestarted;
    echo "  Duration: " . gmdate('i:s', $duration) . " (mm:ss)\n";
}

echo "\n";

// Check NBs
$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');
$nb_count = count($nbs);

echo "NB GENERATION:\n";
echo "  NBs Found: {$nb_count}/15\n";

if ($nb_count === 0) {
    echo "  ❌ NO NBs GENERATED!\n\n";
    echo "  This means execute_protocol() or execute_all_nbs() did NOT run,\n";
    echo "  or NBs were retrieved from cache without saving to local_ci_nb_result.\n\n";

    // Check cache
    echo "CACHE CHECK:\n";
    $source_cache = $DB->count_records('local_ci_nb_cache', ['companyid' => $run->companyid]);
    $target_cache = $DB->count_records('local_ci_nb_cache', ['companyid' => $run->targetid]);
    echo "  Source Company Cache: {$source_cache} NBs\n";
    echo "  Target Company Cache: {$target_cache} NBs\n";

    if ($source_cache > 0 || $target_cache > 0) {
        echo "  ✅ Cache exists - NBs may have been loaded from cache\n";
        echo "  BUT: Bug #8 fixed execute_nb() to save even cached NBs\n";
        echo "  SO: Either cache loading bypasses execute_nb(), or bug still exists\n";
    } else {
        echo "  ❌ No cache either - NBs were never generated!\n";
    }
} else if ($nb_count === 15) {
    echo "  ✅ All 15 NBs present\n";

    // Show summary
    $total_tokens = 0;
    $total_duration = 0;
    foreach ($nbs as $nb) {
        $total_tokens += $nb->tokensused;
        $total_duration += $nb->durationms;
    }

    echo "  Total Tokens: " . number_format($total_tokens) . "\n";
    echo "  Total Duration: " . round($total_duration / 60000, 2) . " min\n";
    echo "  Avg per NB: " . round($total_duration / 15 / 1000, 2) . " sec\n";
} else {
    echo "  ⚠️ Only {$nb_count}/15 NBs generated\n";

    echo "\n  Missing NBs:\n";
    $expected = array_map(function($i) { return 'NB-' . $i; }, range(1, 15));
    $found = array_map(function($nb) { return $nb->nbcode; }, $nbs);
    $missing = array_diff($expected, $found);
    foreach ($missing as $nb) {
        echo "    - {$nb}\n";
    }
}

echo "\n";

// Check synthesis
$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

echo "SYNTHESIS:\n";
if ($synthesis) {
    echo "  ✅ Synthesis record exists (ID: {$synthesis->id})\n";

    $html_size = strlen($synthesis->htmlcontent);
    $json_size = strlen($synthesis->jsoncontent);

    echo "  HTML Size: " . number_format($html_size) . " bytes\n";
    echo "  JSON Size: " . number_format($json_size) . " bytes\n";

    if ($html_size < 1000) {
        echo "  ⚠️ Very small content - likely ran on empty NBs\n";
    }

    // Check M1T3 metadata
    if (!empty($synthesis->source_company_id)) {
        echo "  ✅ M1T3 metadata present\n";
    } else {
        echo "  ❌ M1T3 metadata missing\n";
    }
} else {
    echo "  ❌ No synthesis record\n";
}

echo "\n";

// Trace analysis
echo "TRACE ANALYSIS:\n";
$traces = $DB->get_records('local_ci_trace', ['runid' => $runid], 'timecreated ASC');

if ($traces) {
    echo "  Total Trace Entries: " . count($traces) . "\n\n";

    // Find key phases
    $phases = [];
    foreach ($traces as $trace) {
        if (strpos($trace->event, 'PHASE_DURATION_') === 0) {
            $phase = str_replace('PHASE_DURATION_', '', $trace->event);
            $payload = json_decode($trace->payload, true);
            $duration_ms = $payload['duration_ms'] ?? 0;
            $phases[$phase] = $duration_ms;
        }
    }

    if (!empty($phases)) {
        echo "  Phase Durations:\n";
        foreach ($phases as $phase => $duration_ms) {
            echo "    {$phase}: " . round($duration_ms, 2) . " ms\n";
        }

        echo "\n";

        if (isset($phases['NB_ORCHESTRATION']) && $phases['NB_ORCHESTRATION'] < 100) {
            echo "  ❌ NB_ORCHESTRATION took only " . round($phases['NB_ORCHESTRATION'], 2) . " ms\n";
            echo "     Normal: 180,000-300,000 ms (3-5 minutes)\n";
            echo "     This confirms NBs were NOT generated!\n";
        } else if (isset($phases['NB_ORCHESTRATION'])) {
            echo "  ✅ NB_ORCHESTRATION took " . round($phases['NB_ORCHESTRATION'] / 1000, 2) . " seconds\n";
            echo "     This indicates NBs were generated properly\n";
        }
    }
} else {
    echo "  No trace entries found\n";
}

echo "\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "DIAGNOSIS:\n";
echo "=" . str_repeat("=", 70) . "\n\n";

if ($nb_count === 0) {
    echo "🔴 PROBLEM: No NBs were generated for this run\n\n";
    echo "Possible causes:\n";
    echo "  1. execute_protocol() was never called\n";
    echo "  2. Cache strategy bypassed NB generation entirely\n";
    echo "  3. NBs loaded from cache but not saved to local_ci_nb_result\n";
    echo "  4. execute_all_nbs() has a bug that skips generation\n\n";
    echo "Solution:\n";
    echo "  - Check if run.php properly calls job_queue->queue_run()\n";
    echo "  - Check if background task is running\n";
    echo "  - Try creating run with explicit 'force_refresh' flag\n";
    echo "  - Check execute_protocol() implementation\n\n";
} else if ($nb_count === 15 && isset($phases['NB_ORCHESTRATION']) && $phases['NB_ORCHESTRATION'] > 100000) {
    echo "🟢 SUCCESS: All 15 NBs generated properly\n\n";
    echo "This run is suitable for M1T5-8 pipeline testing.\n\n";
} else if ($nb_count === 15) {
    echo "🟡 PARTIAL: All 15 NBs present but duration data missing\n\n";
} else {
    echo "🟡 PARTIAL: Only {$nb_count}/15 NBs generated\n\n";
}

echo "Verification script: /local/customerintel/verify_full_pipeline.php?runid={$runid}\n";
echo "View report: /local/customerintel/view_report.php?runid={$runid}\n";
?>
