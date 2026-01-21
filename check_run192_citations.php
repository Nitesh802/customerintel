<?php
/**
 * Check Run 192 NB citations to diagnose why canonical builder gets 0 citations
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_run192_citations.php'));
$PAGE->set_title("Run 192 Citation Check");

echo $OUTPUT->header();

?>
<style>
.check { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
th, td { padding: 8px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; }
pre { background: white; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 300px; font-size: 11px; }
.code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>

<div class="check">

<h1>🔍 Run 192 - Citation Diagnostic</h1>

<?php

// Get all NBs for Run 192
echo "<div class='section'>";
echo "<h2>NB Records from Database</h2>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');

echo "<p>Found " . count($nbs) . " NB records for Run {$runid}:</p>";

echo "<table>";
echo "<tr>";
echo "<th>NB Code</th>";
echo "<th>Status</th>";
echo "<th>Citations Field</th>";
echo "<th>Citation Count</th>";
echo "<th>Payload Size</th>";
echo "<th>Tokens</th>";
echo "</tr>";

$total_citations = 0;
$nbs_with_citations = 0;
$nbs_with_empty_citations = 0;
$nbs_with_null_citations = 0;

foreach ($nbs as $nb) {
    $citations = null;
    $citation_count = 0;
    $citation_status = '';

    if ($nb->citations === null) {
        $citation_status = '❌ NULL';
        $nbs_with_null_citations++;
    } else if (empty($nb->citations)) {
        $citation_status = '⚠️ Empty String';
        $nbs_with_empty_citations++;
    } else {
        $citations_data = json_decode($nb->citations, true);
        if (is_array($citations_data)) {
            $citation_count = count($citations_data);
            $total_citations += $citation_count;

            if ($citation_count > 0) {
                $citation_status = "✅ {$citation_count} citations";
                $nbs_with_citations++;
            } else {
                $citation_status = '⚠️ Empty Array';
                $nbs_with_empty_citations++;
            }
        } else {
            $citation_status = '❌ Invalid JSON';
            $nbs_with_empty_citations++;
        }
    }

    $payload_size = $nb->jsonpayload ? strlen($nb->jsonpayload) : 0;

    $row_class = '';
    if ($citation_count > 0) {
        $row_class = 'success';
    } else if ($nb->citations === null) {
        $row_class = 'fail';
    } else {
        $row_class = 'warning';
    }

    echo "<tr class='{$row_class}'>";
    echo "<td><strong>{$nb->nbcode}</strong></td>";
    echo "<td>{$nb->status}</td>";
    echo "<td>{$citation_status}</td>";
    echo "<td>{$citation_count}</td>";
    echo "<td>" . number_format($payload_size) . " B</td>";
    echo "<td>{$nb->tokensused}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Summary:</h3>";
echo "<ul>";
echo "<li><strong>Total NBs:</strong> " . count($nbs) . "</li>";
echo "<li><strong>✅ NBs with citations:</strong> {$nbs_with_citations}</li>";
echo "<li><strong>⚠️ NBs with empty/invalid citations:</strong> {$nbs_with_empty_citations}</li>";
echo "<li><strong>❌ NBs with NULL citations:</strong> {$nbs_with_null_citations}</li>";
echo "<li><strong>📊 Total citations found:</strong> {$total_citations}</li>";
echo "</ul>";

if ($total_citations == 0) {
    echo "<div class='fail'>";
    echo "<h3>🔴 ROOT CAUSE FOUND</h3>";
    echo "<p><strong>The NBs in the database have NO citation data!</strong></p>";
    echo "<p>This explains why canonical_builder outputs 0 citations.</p>";
    echo "<p><strong>Next question:</strong> Why weren't citations saved when the NBs were generated?</p>";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<p><strong>✅ Citations exist in database!</strong></p>";
    echo "<p>The issue must be elsewhere in the pipeline.</p>";
    echo "</div>";
}

echo "</div>";

// Sample one NB to show its structure
echo "<div class='section'>";
echo "<h2>Sample NB Structure</h2>";

$sample_nb = reset($nbs);
if ($sample_nb) {
    echo "<h3>NB: {$sample_nb->nbcode}</h3>";

    echo "<h4>Citations Field:</h4>";
    if ($sample_nb->citations === null) {
        echo "<pre>NULL</pre>";
    } else {
        $citations_data = json_decode($sample_nb->citations, true);
        echo "<pre>" . htmlspecialchars(json_encode($citations_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
    }

    echo "<h4>Payload Structure (first 2000 chars):</h4>";
    if ($sample_nb->jsonpayload) {
        $payload_preview = substr($sample_nb->jsonpayload, 0, 2000);
        $payload_data = json_decode($sample_nb->jsonpayload, true);

        if ($payload_data) {
            echo "<p><strong>Top-level keys:</strong> " . implode(', ', array_keys($payload_data)) . "</p>";

            // Check if citations are embedded in payload
            $has_citations_in_payload = false;
            if (isset($payload_data['citations'])) {
                $has_citations_in_payload = true;
                echo "<p class='success'>✅ Payload contains 'citations' field with " . count($payload_data['citations']) . " items</p>";
            }

            echo "<details>";
            echo "<summary>Full payload structure (click to expand)</summary>";
            echo "<pre>" . htmlspecialchars(json_encode($payload_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
            echo "</details>";
        } else {
            echo "<pre>" . htmlspecialchars($payload_preview) . "...</pre>";
        }
    } else {
        echo "<p>No payload</p>";
    }
}

echo "</div>";

// Check if this is a cache hit
echo "<div class='section'>";
echo "<h2>Cache Status Check</h2>";

$telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid], 'timecreated ASC');

$cache_hit = false;
$cached_from_runid = null;

foreach ($telemetry as $t) {
    if ($t->metrickey === 'CACHED_FROM_RUNID') {
        $data = json_decode($t->metricvaluejson, true);
        $cached_from_runid = $data['from_runid'] ?? null;
        $cache_hit = true;
        break;
    }
}

if ($cache_hit) {
    echo "<p class='warning'>⚠️ <strong>Run 192 was a CACHE HIT from Run {$cached_from_runid}</strong></p>";
    echo "<p>The NBs were copied from Run {$cached_from_runid}, not freshly generated.</p>";
    echo "<p>This means citation data depends on what was saved in Run {$cached_from_runid}.</p>";

    echo "<h3>Check Source Run:</h3>";
    echo "<p><a href='check_run192_citations.php?runid={$cached_from_runid}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Check Run {$cached_from_runid} Citations</a></p>";
} else {
    echo "<p class='success'>✅ Run 192 was NOT a cache hit - NBs were freshly generated</p>";
}

echo "</div>";

// Diagnosis
echo "<div class='section'>";
echo "<h2>🎯 Diagnosis</h2>";

if ($total_citations == 0 && $nbs_with_null_citations == count($nbs)) {
    echo "<div class='fail'>";
    echo "<h3>❌ Issue: Citations Column is NULL</h3>";
    echo "<p><strong>Problem:</strong> All NBs have NULL in the citations field.</p>";
    echo "<p><strong>Cause:</strong> NB generation or caching is not populating the citations column.</p>";
    echo "<p><strong>Impact:</strong> Canonical builder reads NULL → outputs 0 citations → pattern detection fails.</p>";
    echo "</div>";

    echo "<div class='section warning'>";
    echo "<h3>🔧 Potential Fixes:</h3>";
    echo "<ol>";
    echo "<li><strong>Check NB orchestrator:</strong> Ensure it saves citations when generating NBs</li>";
    echo "<li><strong>Check cache copy logic:</strong> Ensure it copies citations when reusing NBs</li>";
    echo "<li><strong>Check if citations are in payload:</strong> They might be in jsonpayload instead of citations field</li>";
    echo "</ol>";
    echo "</div>";

} else if ($total_citations > 0) {
    echo "<div class='success'>";
    echo "<p>✅ Citations exist in database ({$total_citations} total)</p>";
    echo "<p>The issue must be in how canonical_builder reads or aggregates them.</p>";
    echo "</div>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
