<?php
/**
 * Trace how Run 192 NBs were executed - from cache or fresh API calls?
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/trace_run192_nb_execution.php'));
$PAGE->set_title("Trace Run 192 NB Execution");

echo $OUTPUT->header();

?>
<style>
.trace { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
.cache-hit { background: #d4edda; }
.cache-miss { background: #fff3cd; }
</style>

<div class="trace">

<h1>🔍 Trace Run 192 NB Execution Path</h1>

<?php

// Get run info
$run = $DB->get_record('local_ci_run', ['id' => $runid]);
if (!$run) {
    echo "<div class='section bad'>";
    echo "<h3>❌ Run {$runid} not found</h3>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<div class='section'>";
echo "<h2>Run Information</h2>";
echo "<ul>";
echo "<li><strong>Run ID:</strong> {$run->id}</li>";
echo "<li><strong>Company ID:</strong> {$run->companyid}</li>";
echo "<li><strong>Target Company ID:</strong> " . ($run->targetcompanyid ?? 'None') . "</li>";
echo "<li><strong>Status:</strong> {$run->status}</li>";
echo "<li><strong>Cache Strategy:</strong> " . ($run->cache_strategy ?? 'default') . "</li>";
echo "<li><strong>Created:</strong> " . date('Y-m-d H:i:s', $run->timecreated) . "</li>";
echo "</ul>";
echo "</div>";

// Get all NB results
$nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');

echo "<div class='section'>";
echo "<h2>NB Execution Analysis</h2>";
echo "<p><strong>Total NBs:</strong> " . count($nb_results) . "</p>";

if (empty($nb_results)) {
    echo "<p class='bad'>❌ No NB results found!</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<table>";
echo "<tr>";
echo "<th>NB Code</th>";
echo "<th>Status</th>";
echo "<th>Duration</th>";
echo "<th>Tokens</th>";
echo "<th>Citations in DB</th>";
echo "<th>Citations in Payload</th>";
echo "<th>Likely Source</th>";
echo "<th>Created</th>";
echo "</tr>";

$cache_hits = 0;
$api_calls = 0;
$nbs_with_citations = 0;

foreach ($nb_results as $nb) {
    $citations_in_column = 0;
    $citations_in_payload = 0;

    // Check citations column
    if (!empty($nb->citations)) {
        $citations_data = json_decode($nb->citations, true);
        if (is_array($citations_data)) {
            $citations_in_column = count($citations_data);
        }
    }

    // Check payload
    if (!empty($nb->jsonpayload)) {
        $payload = json_decode($nb->jsonpayload, true);
        if ($payload && isset($payload['citations']) && is_array($payload['citations'])) {
            $citations_in_payload = count($payload['citations']);
        }
    }

    // Determine likely source
    $likely_source = 'Unknown';
    $row_class = '';

    if ($nb->tokensused == 0 || $nb->tokensused === null) {
        $likely_source = '🔵 Cache HIT (0 tokens)';
        $row_class = 'cache-hit';
        $cache_hits++;
    } else {
        $likely_source = '🟢 Fresh API call';
        $row_class = 'cache-miss';
        $api_calls++;
    }

    if ($citations_in_column > 0 || $citations_in_payload > 0) {
        $nbs_with_citations++;
    }

    echo "<tr class='{$row_class}'>";
    echo "<td><strong>{$nb->nbcode}</strong></td>";
    echo "<td>{$nb->status}</td>";
    echo "<td>" . number_format($nb->durationms ?? 0) . " ms</td>";
    echo "<td>" . number_format($nb->tokensused ?? 0) . "</td>";
    echo "<td>" . ($citations_in_column > 0 ? "<strong style='color: #28a745;'>{$citations_in_column}</strong>" : "<span style='color: #999;'>0</span>") . "</td>";
    echo "<td>" . ($citations_in_payload > 0 ? "<strong style='color: #28a745;'>{$citations_in_payload}</strong>" : "<span style='color: #999;'>0</span>") . "</td>";
    echo "<td>{$likely_source}</td>";
    echo "<td>" . date('Y-m-d H:i:s', $nb->timecreated) . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// Summary
echo "<div class='section'>";
echo "<h2>Execution Summary</h2>";
echo "<ul>";
echo "<li><strong>Total NBs:</strong> " . count($nb_results) . "</li>";
echo "<li><strong>Cache HITs (0 tokens):</strong> {$cache_hits}</li>";
echo "<li><strong>Fresh API calls (tokens > 0):</strong> {$api_calls}</li>";
echo "<li><strong>NBs with citations:</strong> {$nbs_with_citations}</li>";
echo "</ul>";
echo "</div>";

// Check nb_cache table
$company_id = $run->companyid;
$target_company_id = $run->targetcompanyid;

echo "<div class='section'>";
echo "<h2>NB Cache Status</h2>";

$cache_entries = $DB->get_records('local_ci_nb_cache', ['companyid' => $company_id], 'nbcode ASC');
echo "<p><strong>Cache entries for source company ({$company_id}):</strong> " . count($cache_entries) . "</p>";

if (!empty($cache_entries)) {
    echo "<table>";
    echo "<tr>";
    echo "<th>NB Code</th>";
    echo "<th>Citations Count</th>";
    echo "<th>Payload Size</th>";
    echo "<th>Created</th>";
    echo "</tr>";

    foreach ($cache_entries as $cache) {
        $cache_citations = 0;
        if (!empty($cache->citations)) {
            $citations = json_decode($cache->citations, true);
            if (is_array($citations)) {
                $cache_citations = count($citations);
            }
        }

        echo "<tr>";
        echo "<td><strong>{$cache->nbcode}</strong></td>";
        echo "<td>" . ($cache_citations > 0 ? "<strong style='color: #28a745;'>{$cache_citations}</strong>" : "<span style='color: #dc3545;'>0</span>") . "</td>";
        echo "<td>" . number_format(strlen($cache->jsonpayload ?? '')) . " bytes</td>";
        echo "<td>" . date('Y-m-d H:i:s', $cache->timecreated) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

if ($target_company_id) {
    $target_cache_entries = $DB->get_records('local_ci_nb_cache', ['companyid' => $target_company_id], 'nbcode ASC');
    echo "<p><strong>Cache entries for target company ({$target_company_id}):</strong> " . count($target_cache_entries) . "</p>";

    if (!empty($target_cache_entries)) {
        echo "<table>";
        echo "<tr>";
        echo "<th>NB Code</th>";
        echo "<th>Citations Count</th>";
        echo "<th>Payload Size</th>";
        echo "<th>Created</th>";
        echo "</tr>";

        foreach ($target_cache_entries as $cache) {
            $cache_citations = 0;
            if (!empty($cache->citations)) {
                $citations = json_decode($cache->citations, true);
                if (is_array($citations)) {
                    $cache_citations = count($citations);
                }
            }

            echo "<tr>";
            echo "<td><strong>{$cache->nbcode}</strong></td>";
            echo "<td>" . ($cache_citations > 0 ? "<strong style='color: #28a745;'>{$cache_citations}</strong>" : "<span style='color: #dc3545;'>0</span>") . "</td>";
            echo "<td>" . number_format(strlen($cache->jsonpayload ?? '')) . " bytes</td>";
            echo "<td>" . date('Y-m-d H:i:s', $cache->timecreated) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
}

echo "</div>";

// Diagnosis
$class = ($nbs_with_citations > 0) ? 'good' : 'bad';
echo "<div class='section {$class}'>";
echo "<h2>🔍 Diagnosis</h2>";

if ($cache_hits > 0 && $nbs_with_citations === 0) {
    echo "<p><strong style='color: #dc3545;'>❌ PROBLEM: NBs loaded from cache but cache has 0 citations!</strong></p>";
    echo "<p>This means the cache was created BEFORE citations were properly saved.</p>";
    echo "<p><strong>Solution:</strong> Clear the NB cache for these companies and re-run NBs (not just synthesis).</p>";
    echo "<p>The M1T5-M1T8 caching system is working correctly - it's just using OLD cached data from before Bug #9 was fixed.</p>";
} else if ($cache_hits === 0 && $nbs_with_citations > 0) {
    echo "<p><strong style='color: #28a745;'>✅ All NBs were fresh API calls with citations</strong></p>";
    echo "<p>The issue is NOT with NB execution - citations exist in the database.</p>";
    echo "<p>The problem is in how synthesis loads/processes them.</p>";
} else if ($cache_hits > 0 && $nbs_with_citations > 0) {
    echo "<p><strong style='color: #28a745;'>✅ Mix of cache hits and fresh calls, both have citations</strong></p>";
    echo "<p>NBs are properly stored with citations.</p>";
    echo "<p>If synthesis shows 0 citations, the issue is in raw_collector extraction logic.</p>";
} else {
    echo "<p><strong style='color: #ffc107;'>⚠️ All NBs executed but mixed results</strong></p>";
    echo "<p>Need to investigate further.</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
