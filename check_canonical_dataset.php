<?php
/**
 * Check the canonical dataset artifact for Run 192
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_canonical_dataset.php'));
$PAGE->set_title("Canonical Dataset Check - Run $runid");

echo $OUTPUT->header();

?>
<style>
.check { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
pre { background: white; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 400px; font-size: 11px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
th, td { padding: 8px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; }
</style>

<div class="check">

<h1>🔍 Canonical Dataset Check - Run <?php echo $runid; ?></h1>

<?php

// Get the most recent canonical dataset artifact
$artifact = $DB->get_record_sql(
    "SELECT * FROM {local_ci_artifact}
     WHERE runid = ?
     AND phase = ?
     AND artifacttype = ?
     ORDER BY timecreated DESC
     LIMIT 1",
    [$runid, 'synthesis', 'canonical_nb_dataset']
);

echo "<div class='section'>";
echo "<h2>Artifact Status</h2>";

if (!$artifact) {
    echo "<p class='fail'>❌ No canonical dataset artifact found for Run {$runid}</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<p>✅ Found artifact ID: {$artifact->id}</p>";
echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $artifact->timecreated) . "</p>";
echo "<p><strong>Payload size:</strong> " . number_format(strlen($artifact->jsondata)) . " bytes</p>";
echo "</div>";

// Decode the payload
$data = json_decode($artifact->jsondata, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<div class='fail'>";
    echo "<h2>❌ JSON Decode Error</h2>";
    echo "<p>" . json_last_error_msg() . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// Show metadata
echo "<div class='section'>";
echo "<h2>Canonical Dataset Metadata</h2>";

if (isset($data['metadata'])) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    foreach ($data['metadata'] as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        echo "<tr><td>{$key}</td><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No metadata found</p>";
}

echo "</div>";

// Show NB data
echo "<div class='section'>";
echo "<h2>NB Data in Canonical Dataset</h2>";

if (!isset($data['nb_data']) || empty($data['nb_data'])) {
    echo "<p class='fail'>❌ No NB data found in canonical dataset!</p>";
    echo "<p>This explains why pattern detection found 0 patterns.</p>";
} else {
    echo "<p>Found " . count($data['nb_data']) . " NBs in canonical dataset:</p>";

    echo "<table>";
    echo "<tr>";
    echo "<th>NB Code</th>";
    echo "<th>Status</th>";
    echo "<th>Citations</th>";
    echo "<th>Tokens Used</th>";
    echo "<th>Data Keys</th>";
    echo "</tr>";

    $total_citations = 0;

    foreach ($data['nb_data'] as $nbcode => $nb) {
        $citation_count = isset($nb['citations']) && is_array($nb['citations']) ? count($nb['citations']) : 0;
        $total_citations += $citation_count;

        $data_keys = isset($nb['data']) && is_array($nb['data']) ? implode(', ', array_keys($nb['data'])) : 'N/A';

        $row_class = $citation_count > 0 ? 'success' : 'fail';

        echo "<tr class='{$row_class}'>";
        echo "<td><strong>{$nbcode}</strong></td>";
        echo "<td>" . ($nb['status'] ?? 'unknown') . "</td>";
        echo "<td>{$citation_count}</td>";
        echo "<td>" . ($nb['tokens_used'] ?? 0) . "</td>";
        echo "<td>" . htmlspecialchars(substr($data_keys, 0, 50)) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<h3>Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Total NBs:</strong> " . count($data['nb_data']) . "</li>";
    echo "<li><strong>Total Citations:</strong> {$total_citations}</li>";
    echo "<li><strong>Average Citations per NB:</strong> " . (count($data['nb_data']) > 0 ? round($total_citations / count($data['nb_data']), 1) : 0) . "</li>";
    echo "</ul>";

    if ($total_citations == 0) {
        echo "<div class='fail'>";
        echo "<h3>🔴 Root Cause Confirmed</h3>";
        echo "<p><strong>The canonical dataset has 0 citations!</strong></p>";
        echo "<p>This is why pattern detection finds 0 patterns.</p>";
        echo "</div>";
    }
}

echo "</div>";

// Show a sample NB structure
if (isset($data['nb_data']) && count($data['nb_data']) > 0) {
    echo "<div class='section'>";
    echo "<h2>Sample NB Structure</h2>";

    $sample_nb = reset($data['nb_data']);
    $sample_code = key($data['nb_data']);
    reset($data['nb_data']);

    echo "<h3>NB: {$sample_code}</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($sample_nb, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
    echo "</div>";
}

// Check what NBs exist in database
echo "<div class='section'>";
echo "<h2>NBs in Database (for comparison)</h2>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');

echo "<p>Found " . count($nbs) . " NB records in database:</p>";

echo "<table>";
echo "<tr>";
echo "<th>NB Code</th>";
echo "<th>Citations in DB</th>";
echo "<th>Payload Size</th>";
echo "</tr>";

$db_total_citations = 0;

foreach ($nbs as $nb) {
    $citations = [];
    if (!empty($nb->citations)) {
        $citations = json_decode($nb->citations, true);
        if (!is_array($citations)) {
            $citations = [];
        }
    }

    $db_total_citations += count($citations);

    echo "<tr>";
    echo "<td><strong>{$nb->nbcode}</strong></td>";
    echo "<td>" . count($citations) . "</td>";
    echo "<td>" . number_format(strlen($nb->jsonpayload)) . " B</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><strong>Total citations in database:</strong> {$db_total_citations}</p>";

echo "</div>";

// Diagnosis
echo "<div class='section'>";
echo "<h2>🎯 Diagnosis</h2>";

$canonical_nbs = isset($data['nb_data']) ? array_keys($data['nb_data']) : [];
$db_nbs = array_column($nbs, 'nbcode');

echo "<h3>NB Code Comparison:</h3>";
echo "<p><strong>Canonical dataset NBs:</strong> " . implode(', ', $canonical_nbs) . "</p>";
echo "<p><strong>Database NBs:</strong> " . implode(', ', $db_nbs) . "</p>";

// Check if formats match
$format_mismatch = [];
foreach ($db_nbs as $db_code) {
    if (!in_array($db_code, $canonical_nbs)) {
        $format_mismatch[] = $db_code;
    }
}

if (count($format_mismatch) > 0) {
    echo "<div class='fail'>";
    echo "<h3>🔴 Format Mismatch Detected!</h3>";
    echo "<p><strong>These NBs exist in database but NOT in canonical dataset:</strong></p>";
    echo "<p>" . implode(', ', $format_mismatch) . "</p>";
    echo "<p>This confirms the NB key filtering issue is still present!</p>";
    echo "</div>";
} else {
    echo "<div class='success'>";
    echo "<p>✅ All database NBs are present in canonical dataset</p>";
    echo "</div>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
