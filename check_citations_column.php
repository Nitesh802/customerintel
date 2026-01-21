<?php
/**
 * Check if citations are actually in the citations column of local_ci_nb_result
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_citations_column.php'));
$PAGE->set_title("Check Citations Column - Run 192");

echo $OUTPUT->header();

?>
<style>
.diag { font-family: monospace; max-width: 1200px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
</style>

<div class="diag">

<h1>🔍 Citations Column Analysis - Run <?= $runid ?></h1>

<?php

// Get all NB results for this run
$nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');

echo "<div class='section'>";
echo "<h2>Database NB Results</h2>";
echo "<p><strong>Total NBs found:</strong> " . count($nb_results) . "</p>";
echo "</div>";

if (empty($nb_results)) {
    echo "<div class='section bad'>";
    echo "<h3>❌ No NB results found for Run {$runid}</h3>";
    echo "</div>";
} else {
    echo "<div class='section'>";
    echo "<h2>Citations Column Contents</h2>";
    echo "<table>";
    echo "<tr>";
    echo "<th>NB Code</th>";
    echo "<th>Citations Column</th>";
    echo "<th>Citations Count</th>";
    echo "<th>Payload Has Citations?</th>";
    echo "<th>Payload Citations Count</th>";
    echo "</tr>";

    $total_citations_column = 0;
    $total_citations_payload = 0;
    $nbs_with_citations_column = 0;
    $nbs_with_citations_payload = 0;

    foreach ($nb_results as $nb) {
        $citations_column = null;
        $citations_count_column = 0;
        $citations_column_empty = empty($nb->citations);

        if (!empty($nb->citations)) {
            $citations_column = json_decode($nb->citations, true);
            if (is_array($citations_column)) {
                $citations_count_column = count($citations_column);
                $total_citations_column += $citations_count_column;
                if ($citations_count_column > 0) {
                    $nbs_with_citations_column++;
                }
            }
        }

        $payload = null;
        $payload_has_citations = false;
        $citations_count_payload = 0;

        if (!empty($nb->jsonpayload)) {
            $payload = json_decode($nb->jsonpayload, true);
            if ($payload && isset($payload['citations'])) {
                $payload_has_citations = true;
                if (is_array($payload['citations'])) {
                    $citations_count_payload = count($payload['citations']);
                    $total_citations_payload += $citations_count_payload;
                    if ($citations_count_payload > 0) {
                        $nbs_with_citations_payload++;
                    }
                }
            }
        }

        echo "<tr>";
        echo "<td><strong>{$nb->nbcode}</strong></td>";
        echo "<td>" . ($citations_column_empty ? "<span style='color: #dc3545;'>EMPTY</span>" : "Has data") . "</td>";
        echo "<td>" . ($citations_count_column > 0 ? "<strong style='color: #28a745;'>{$citations_count_column}</strong>" : "<span style='color: #dc3545;'>0</span>") . "</td>";
        echo "<td>" . ($payload_has_citations ? "✅ Yes" : "❌ No") . "</td>";
        echo "<td>" . ($citations_count_payload > 0 ? "<strong style='color: #28a745;'>{$citations_count_payload}</strong>" : "<span style='color: #dc3545;'>0</span>") . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";

    // Summary
    $class = ($total_citations_column > 0) ? 'good' : 'bad';
    echo "<div class='section {$class}'>";
    echo "<h2>Summary</h2>";
    echo "<ul>";
    echo "<li><strong>Total NBs:</strong> " . count($nb_results) . "</li>";
    echo "<li><strong>NBs with citations in 'citations' column:</strong> {$nbs_with_citations_column}</li>";
    echo "<li><strong>Total citations in 'citations' column:</strong> {$total_citations_column}</li>";
    echo "<li><strong>NBs with citations in payload:</strong> {$nbs_with_citations_payload}</li>";
    echo "<li><strong>Total citations in payload:</strong> {$total_citations_payload}</li>";
    echo "</ul>";
    echo "</div>";

    // Show sample citation from each location if they exist
    if ($total_citations_column > 0) {
        echo "<div class='section good'>";
        echo "<h2>✅ Sample Citation from 'citations' Column</h2>";
        foreach ($nb_results as $nb) {
            if (!empty($nb->citations)) {
                $citations = json_decode($nb->citations, true);
                if (is_array($citations) && count($citations) > 0) {
                    echo "<p><strong>NB {$nb->nbcode} - First citation:</strong></p>";
                    echo "<pre>" . json_encode($citations[0], JSON_PRETTY_PRINT) . "</pre>";
                    break;
                }
            }
        }
        echo "</div>";
    }

    if ($total_citations_payload > 0) {
        echo "<div class='section good'>";
        echo "<h2>✅ Sample Citation from Payload</h2>";
        foreach ($nb_results as $nb) {
            if (!empty($nb->jsonpayload)) {
                $payload = json_decode($nb->jsonpayload, true);
                if ($payload && isset($payload['citations']) && is_array($payload['citations']) && count($payload['citations']) > 0) {
                    echo "<p><strong>NB {$nb->nbcode} - First citation:</strong></p>";
                    echo "<pre>" . json_encode($payload['citations'][0], JSON_PRETTY_PRINT) . "</pre>";
                    break;
                }
            }
        }
        echo "</div>";
    }

    // Diagnosis
    echo "<div class='section warning'>";
    echo "<h2>🔍 Diagnosis</h2>";

    if ($total_citations_column === 0 && $total_citations_payload === 0) {
        echo "<p><strong style='color: #dc3545;'>❌ PROBLEM: No citations found in EITHER location!</strong></p>";
        echo "<p>Neither the 'citations' column nor the jsonpayload has citations.</p>";
        echo "<p>This means the NBs were never properly saved with citations in the first place.</p>";
    } else if ($total_citations_column > 0 && $total_citations_payload > 0) {
        echo "<p><strong style='color: #28a745;'>✅ Citations exist in BOTH locations</strong></p>";
        echo "<p>The database has citations properly stored.</p>";
        echo "<p>If raw_collector is still showing 0 citations, the issue is in how it's extracting them.</p>";
    } else if ($total_citations_column > 0 && $total_citations_payload === 0) {
        echo "<p><strong style='color: #ffc107;'>⚠️ Citations ONLY in 'citations' column</strong></p>";
        echo "<p>Citations are in the separate column but NOT embedded in payload.</p>";
        echo "<p>The fallback code in raw_collector (line 402-408) should handle this.</p>";
    } else if ($total_citations_column === 0 && $total_citations_payload > 0) {
        echo "<p><strong style='color: #ffc107;'>⚠️ Citations ONLY in payload</strong></p>";
        echo "<p>Citations are embedded in payload but NOT in separate column.</p>";
        echo "<p>The primary code path (line 386-401) should handle this.</p>";
    }

    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
