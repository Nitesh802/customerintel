<?php
/**
 * Diagnose Canonical Builder - Run 192
 *
 * This script traces citation flow from M1T5 (raw_collector) to M1T6 (canonical_builder)
 * to identify exactly where citations are being lost.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/diagnose_canonical_builder_192.php'));
$PAGE->set_title("Diagnose Canonical Builder - Run 192");

echo $OUTPUT->header();

?>
<style>
.diagnose { font-family: 'Segoe UI', Arial, sans-serif; max-width: 1400px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 5px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.danger { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.info { background: #e7f3ff; border-left-color: #17a2b8; }
.code { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 12px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
.highlight { background: #ffeb3b; padding: 2px 5px; }
h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
h2 { color: #555; margin-top: 0; }
.stat { font-size: 24px; font-weight: bold; margin: 10px 0; }
.good { color: #28a745; }
.bad { color: #dc3545; }
</style>

<div class="diagnose">

<h1>🔍 Canonical Builder Citation Flow Diagnosis - Run <?= $runid ?></h1>

<?php

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/canonical_builder.php');
require_once(__DIR__ . '/classes/services/artifact_repository.php');

$artifact_repo = new \local_customerintel\services\artifact_repository();
$raw_collector = new \local_customerintel\services\raw_collector();
$canonical_builder = new \local_customerintel\services\canonical_builder();

// ============================================================================
// STAGE 1: M1T5 - Load normalized_inputs_v16 Artifact
// ============================================================================

echo "<div class='section info'>";
echo "<h2>📦 Stage 1: M1T5 Artifact (normalized_inputs_v16)</h2>";

$m1t5_artifact = $artifact_repo->get_artifact($runid, 'citation_normalization', 'normalized_inputs_v16');

if (!$m1t5_artifact) {
    echo "<p class='bad'>❌ M1T5 artifact not found!</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

$m1t5_data = json_decode($m1t5_artifact->jsondata, true);

echo "<p>✅ Artifact found</p>";
echo "<p><strong>Size:</strong> " . number_format(strlen($m1t5_artifact->jsondata)) . " bytes</p>";
echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $m1t5_artifact->timecreated) . "</p>";

// Analyze structure
$m1t5_nb_count = count($m1t5_data['nb'] ?? []);
$m1t5_total_citations = 0;

echo "<p><strong>NBs in artifact:</strong> {$m1t5_nb_count}</p>";

echo "<h3>Citation Count per NB (from artifact):</h3>";
echo "<table>";
echo "<tr><th>NB Code</th><th>Has 'citations' Key?</th><th>Citation Count</th></tr>";

foreach ($m1t5_data['nb'] ?? [] as $nbcode => $nb_data) {
    $has_citations_key = isset($nb_data['citations']);
    $citation_count = $has_citations_key ? count($nb_data['citations']) : 0;
    $m1t5_total_citations += $citation_count;

    $class = $citation_count > 0 ? '' : 'style="background: #f8d7da;"';
    echo "<tr {$class}>";
    echo "<td><strong>{$nbcode}</strong></td>";
    echo "<td>" . ($has_citations_key ? "✅ Yes" : "❌ No") . "</td>";
    echo "<td>" . ($citation_count > 0 ? "<span class='good'>{$citation_count}</span>" : "<span class='bad'>0</span>") . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p class='stat'><strong>Total M1T5 Citations:</strong> <span class='" . ($m1t5_total_citations > 0 ? "good" : "bad") . "'>{$m1t5_total_citations}</span></p>";

// Show sample NB structure
$first_nb = array_values($m1t5_data['nb'] ?? [])[0] ?? null;
if ($first_nb) {
    echo "<h3>Sample NB Structure (from artifact):</h3>";
    echo "<div class='code'>";
    echo "Keys: " . implode(', ', array_keys($first_nb)) . "\n";
    if (isset($first_nb['citations'])) {
        echo "\nCitations present: " . count($first_nb['citations']) . " citations\n";
        if (count($first_nb['citations']) > 0) {
            echo "First citation sample: " . substr(json_encode($first_nb['citations'][0], JSON_PRETTY_PRINT), 0, 200) . "...\n";
        }
    } else {
        echo "\n❌ NO 'citations' KEY!\n";
    }
    echo "</div>";
}

echo "</div>";

// ============================================================================
// STAGE 2: M1T6 - Load canonical_nb_dataset Artifact
// ============================================================================

echo "<div class='section info'>";
echo "<h2>📦 Stage 2: M1T6 Artifact (canonical_nb_dataset)</h2>";

$m1t6_artifact = $artifact_repo->get_artifact($runid, 'synthesis', 'canonical_nb_dataset');

if (!$m1t6_artifact) {
    echo "<p class='bad'>❌ M1T6 artifact not found!</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

$m1t6_data = json_decode($m1t6_artifact->jsondata, true);

echo "<p>✅ Artifact found</p>";
echo "<p><strong>Size:</strong> " . number_format(strlen($m1t6_artifact->jsondata)) . " bytes</p>";
echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $m1t6_artifact->timecreated) . "</p>";

// Analyze structure
$m1t6_nb_count = count($m1t6_data['nb_data'] ?? []);
$m1t6_total_citations = 0;

// Check different possible locations for citations
if (isset($m1t6_data['aggregated_citations'])) {
    $m1t6_total_citations = count($m1t6_data['aggregated_citations']);
    echo "<p><strong>Aggregated citations:</strong> {$m1t6_total_citations}</p>";
} else if (isset($m1t6_data['citations'])) {
    $m1t6_total_citations = count($m1t6_data['citations']);
    echo "<p><strong>Citations array:</strong> {$m1t6_total_citations}</p>";
} else if (isset($m1t6_data['processing_stats']['total_citations'])) {
    $m1t6_total_citations = $m1t6_data['processing_stats']['total_citations'];
    echo "<p><strong>Total from stats:</strong> {$m1t6_total_citations}</p>";
}

echo "<p><strong>NBs in artifact:</strong> {$m1t6_nb_count}</p>";

echo "<h3>Citation Count per NB (from artifact):</h3>";
echo "<table>";
echo "<tr><th>NB Code</th><th>Has 'citations' Key?</th><th>Citation Count</th></tr>";

$m1t6_per_nb_total = 0;
foreach ($m1t6_data['nb_data'] ?? [] as $nbcode => $nb_data) {
    $has_citations_key = isset($nb_data['citations']);
    $citation_count = $has_citations_key ? count($nb_data['citations']) : 0;
    $m1t6_per_nb_total += $citation_count;

    $class = $citation_count > 0 ? '' : 'style="background: #f8d7da;"';
    echo "<tr {$class}>";
    echo "<td><strong>{$nbcode}</strong></td>";
    echo "<td>" . ($has_citations_key ? "✅ Yes" : "❌ No") . "</td>";
    echo "<td>" . ($citation_count > 0 ? "<span class='good'>{$citation_count}</span>" : "<span class='bad'>0</span>") . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p class='stat'><strong>Total M1T6 Citations (per-NB):</strong> <span class='" . ($m1t6_per_nb_total > 0 ? "good" : "bad") . "'>{$m1t6_per_nb_total}</span></p>";

// Show sample NB structure
$first_m1t6_nb = array_values($m1t6_data['nb_data'] ?? [])[0] ?? null;
if ($first_m1t6_nb) {
    echo "<h3>Sample NB Structure (from artifact):</h3>";
    echo "<div class='code'>";
    echo "Keys: " . implode(', ', array_keys($first_m1t6_nb)) . "\n";
    if (isset($first_m1t6_nb['citations'])) {
        echo "\nCitations present: " . count($first_m1t6_nb['citations']) . " citations\n";
    } else {
        echo "\n❌ NO 'citations' KEY!\n";
    }
    echo "</div>";
}

echo "</div>";

// ============================================================================
// STAGE 3: Direct Code Path Test
// ============================================================================

echo "<div class='section warning'>";
echo "<h2>🧪 Stage 3: Direct Code Path Test</h2>";
echo "<p>Calling raw_collector and canonical_builder with CURRENT code...</p>";

try {
    // Call raw_collector
    $fresh_inputs = $raw_collector->get_normalized_inputs($runid);

    $fresh_nb_count = count($fresh_inputs['nb'] ?? []);
    $fresh_total_citations = 0;

    foreach ($fresh_inputs['nb'] ?? [] as $nbcode => $nb_data) {
        $fresh_total_citations += count($nb_data['citations'] ?? []);
    }

    echo "<p>✅ raw_collector executed</p>";
    echo "<p><strong>NBs returned:</strong> {$fresh_nb_count}</p>";
    echo "<p class='stat'><strong>Citations from raw_collector:</strong> <span class='" . ($fresh_total_citations > 0 ? "good" : "bad") . "'>{$fresh_total_citations}</span></p>";

    // Call canonical_builder
    $canonical_nbkeys = array_keys($fresh_inputs['nb']);
    $fresh_canonical = $canonical_builder->build_canonical_nb_dataset($fresh_inputs, $canonical_nbkeys, $runid);

    $fresh_canonical_citations = 0;
    foreach ($fresh_canonical['nb_data'] ?? [] as $nbcode => $nb_data) {
        $fresh_canonical_citations += count($nb_data['citations'] ?? []);
    }

    echo "<p>✅ canonical_builder executed</p>";
    echo "<p class='stat'><strong>Citations from canonical_builder:</strong> <span class='" . ($fresh_canonical_citations > 0 ? "good" : "bad") . "'>{$fresh_canonical_citations}</span></p>";

    // Show if there's a difference
    if ($fresh_total_citations != $fresh_canonical_citations) {
        echo "<div class='section danger'>";
        echo "<p><strong>⚠️ Citation Loss Detected!</strong></p>";
        echo "<p>raw_collector: {$fresh_total_citations} → canonical_builder: {$fresh_canonical_citations}</p>";
        echo "<p>Lost: " . ($fresh_total_citations - $fresh_canonical_citations) . " citations</p>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<p class='bad'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";

// ============================================================================
// STAGE 4: Database Direct Check
// ============================================================================

echo "<div class='section info'>";
echo "<h2>💾 Stage 4: Database Direct Check</h2>";

$db_nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');
$db_total_citations = 0;

echo "<p><strong>NBs in database:</strong> " . count($db_nbs) . "</p>";

echo "<h3>Citation Count per NB (from database):</h3>";
echo "<table>";
echo "<tr><th>NB Code</th><th>Citations in Column</th><th>Citations in Payload</th></tr>";

foreach ($db_nbs as $nb) {
    $citations_in_column = 0;
    if (!empty($nb->citations)) {
        $citations_data = json_decode($nb->citations, true);
        if (is_array($citations_data)) {
            $citations_in_column = count($citations_data);
            $db_total_citations += $citations_in_column;
        }
    }

    $citations_in_payload = 0;
    if (!empty($nb->jsonpayload)) {
        $payload = json_decode($nb->jsonpayload, true);
        if (isset($payload['citations']) && is_array($payload['citations'])) {
            $citations_in_payload = count($payload['citations']);
        }
    }

    $class = ($citations_in_column > 0 || $citations_in_payload > 0) ? '' : 'style="background: #f8d7da;"';
    echo "<tr {$class}>";
    echo "<td><strong>{$nb->nbcode}</strong></td>";
    echo "<td>" . ($citations_in_column > 0 ? "<span class='good'>{$citations_in_column}</span>" : "<span class='bad'>0</span>") . "</td>";
    echo "<td>" . ($citations_in_payload > 0 ? "<span class='good'>{$citations_in_payload}</span>" : "<span class='bad'>0</span>") . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p class='stat'><strong>Total Database Citations:</strong> <span class='" . ($db_total_citations > 0 ? "good" : "bad") . "'>{$db_total_citations}</span></p>";

echo "</div>";

// ============================================================================
// STAGE 5: Summary & Diagnosis
// ============================================================================

echo "<div class='section'>";
echo "<h2>🎯 Summary & Diagnosis</h2>";

echo "<table>";
echo "<tr>";
echo "<th>Stage</th><th>Citations</th><th>Status</th>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>Database (raw data)</strong></td>";
echo "<td class='stat'><span class='" . ($db_total_citations > 0 ? "good" : "bad") . "'>{$db_total_citations}</span></td>";
echo "<td>" . ($db_total_citations > 0 ? "✅ Citations exist" : "❌ No citations") . "</td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>M1T5 Artifact</strong></td>";
echo "<td class='stat'><span class='" . ($m1t5_total_citations > 0 ? "good" : "bad") . "'>{$m1t5_total_citations}</span></td>";
echo "<td>" . ($m1t5_total_citations > 0 ? "✅ Citations preserved" : "❌ Citations lost") . "</td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>M1T6 Artifact</strong></td>";
echo "<td class='stat'><span class='" . ($m1t6_per_nb_total > 0 ? "good" : "bad") . "'>{$m1t6_per_nb_total}</span></td>";
echo "<td>" . ($m1t6_per_nb_total > 0 ? "✅ Citations preserved" : "❌ Citations lost") . "</td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>raw_collector (fresh call)</strong></td>";
echo "<td class='stat'><span class='" . ($fresh_total_citations > 0 ? "good" : "bad") . "'>{$fresh_total_citations}</span></td>";
echo "<td>" . ($fresh_total_citations > 0 ? "✅ Code working" : "❌ Code broken") . "</td>";
echo "</tr>";

echo "<tr>";
echo "<td><strong>canonical_builder (fresh call)</strong></td>";
echo "<td class='stat'><span class='" . ($fresh_canonical_citations > 0 ? "good" : "bad") . "'>{$fresh_canonical_citations}</span></td>";
echo "<td>" . ($fresh_canonical_citations > 0 ? "✅ Code working" : "❌ Code broken") . "</td>";
echo "</tr>";

echo "</table>";

echo "</div>";

// Final Diagnosis
$diagnosis_class = 'success';
$diagnosis_message = '';

if ($db_total_citations > 0 && $fresh_total_citations > 0 && $fresh_canonical_citations > 0) {
    if ($m1t5_total_citations == 0 || $m1t6_per_nb_total == 0) {
        $diagnosis_class = 'warning';
        $diagnosis_message = "✅ CODE IS FIXED! Fresh calls work correctly ({$fresh_canonical_citations} citations).\n\n";
        $diagnosis_message .= "⚠️ OLD ARTIFACTS are stale (created before fix):\n";
        $diagnosis_message .= "- M1T5 artifact: {$m1t5_total_citations} citations\n";
        $diagnosis_message .= "- M1T6 artifact: {$m1t6_per_nb_total} citations\n\n";
        $diagnosis_message .= "🔧 SOLUTION: Delete artifacts and regenerate to get fresh data with {$fresh_canonical_citations} citations.";
    } else {
        $diagnosis_class = 'success';
        $diagnosis_message = "✅ EVERYTHING WORKING!\n\n";
        $diagnosis_message .= "Citations flow correctly through entire pipeline:\n";
        $diagnosis_message .= "Database ({$db_total_citations}) → M1T5 ({$m1t5_total_citations}) → M1T6 ({$m1t6_per_nb_total}) → Code ({$fresh_canonical_citations})";
    }
} else if ($db_total_citations == 0) {
    $diagnosis_class = 'danger';
    $diagnosis_message = "❌ PROBLEM: Database has 0 citations!\n\n";
    $diagnosis_message .= "NBs themselves were generated without citations or from before Bug #9 fix.\n\n";
    $diagnosis_message .= "🔧 SOLUTION: Regenerate NBs (not just synthesis) with force_refresh flag.";
} else if ($fresh_total_citations == 0) {
    $diagnosis_class = 'danger';
    $diagnosis_message = "❌ PROBLEM: raw_collector returns 0 citations!\n\n";
    $diagnosis_message .= "The citation extraction fix in raw_collector.php (line 416) may not be applied.\n\n";
    $diagnosis_message .= "🔧 SOLUTION: Verify raw_collector.php line 416 has: 'citations' => \$enhanced_citations";
} else if ($fresh_canonical_citations == 0) {
    $diagnosis_class = 'danger';
    $diagnosis_message = "❌ PROBLEM: canonical_builder loses citations!\n\n";
    $diagnosis_message .= "The NB code normalization fix may not be applied correctly.\n\n";
    $diagnosis_message .= "🔧 SOLUTION: Verify canonical_builder.php has normalize_nbcode() method and uses it in comparison.";
} else {
    $diagnosis_class = 'success';
    $diagnosis_message = "✅ Citations flowing correctly!";
}

echo "<div class='section {$diagnosis_class}'>";
echo "<h2>🔬 Final Diagnosis</h2>";
echo "<pre style='background: transparent; color: inherit; font-size: 14px; line-height: 1.6;'>{$diagnosis_message}</pre>";
echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
