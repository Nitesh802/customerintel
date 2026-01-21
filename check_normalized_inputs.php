<?php
/**
 * Check the normalized inputs (M1T5 output) for Run 192
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_normalized_inputs.php'));
$PAGE->set_title("Normalized Inputs Check - Run $runid");

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

<h1>🔍 M1T5 Raw Collector Output - Run <?php echo $runid; ?></h1>

<?php

// Get the normalized_inputs artifact from nb_orchestration phase
$artifact = $DB->get_record_sql(
    "SELECT * FROM {local_ci_artifact}
     WHERE runid = ?
     AND phase = ?
     AND artifacttype = ?
     ORDER BY timecreated DESC
     LIMIT 1",
    [$runid, 'nb_orchestration', 'normalized_inputs']
);

echo "<div class='section'>";
echo "<h2>M1T5 Output Artifact</h2>";

if (!$artifact) {
    echo "<p class='fail'>❌ No normalized_inputs artifact found</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<p>✅ Found artifact ID: {$artifact->id}</p>";
echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $artifact->timecreated) . "</p>";
echo "<p><strong>Payload size:</strong> " . number_format(strlen($artifact->jsondata)) . " bytes</p>";
echo "</div>";

// Decode payload
$data = json_decode($artifact->jsondata, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<div class='fail'>";
    echo "<h2>❌ JSON Decode Error</h2>";
    echo "<p>" . json_last_error_msg() . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// Show NB keys
echo "<div class='section'>";
echo "<h2>NB Keys from M1T5 (Raw Collector)</h2>";

if (!isset($data['nb']) || empty($data['nb'])) {
    echo "<p class='fail'>❌ No NB data in normalized inputs!</p>";
} else {
    $nb_keys = array_keys($data['nb']);
    echo "<p><strong>Number of NBs:</strong> " . count($nb_keys) . "</p>";
    echo "<p><strong>NB Keys:</strong> " . implode(', ', $nb_keys) . "</p>";

    echo "<h3>Regex Test:</h3>";
    echo "<table>";
    echo "<tr>";
    echo "<th>NB Key</th>";
    echo "<th>Matches /^NB\d+$/</th>";
    echo "<th>Matches /^NB-?\d+$/</th>";
    echo "<th>Citations</th>";
    echo "</tr>";

    $total_citations = 0;

    foreach ($nb_keys as $key) {
        $old_regex_match = preg_match('/^NB\d+$/', $key) ? '✅ Yes' : '❌ No';
        $new_regex_match = preg_match('/^NB-?\d+$/', $key) ? '✅ Yes' : '❌ No';

        $citations = [];
        if (isset($data['nb'][$key]['citations']) && is_array($data['nb'][$key]['citations'])) {
            $citations = $data['nb'][$key]['citations'];
        }

        $total_citations += count($citations);

        $row_class = preg_match('/^NB\d+$/', $key) ? 'success' : 'warning';

        echo "<tr class='{$row_class}'>";
        echo "<td><strong>{$key}</strong></td>";
        echo "<td>{$old_regex_match}</td>";
        echo "<td>{$new_regex_match}</td>";
        echo "<td>" . count($citations) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<p><strong>Total citations in M1T5 output:</strong> {$total_citations}</p>";
}

echo "</div>";

// Show database NBs for comparison
echo "<div class='section'>";
echo "<h2>Database NB Codes (for comparison)</h2>";

$nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode ASC');

echo "<p><strong>Number of NBs in database:</strong> " . count($nbs) . "</p>";
echo "<p><strong>NB Codes:</strong> " . implode(', ', array_column($nbs, 'nbcode')) . "</p>";

echo "</div>";

// Diagnosis
echo "<div class='section'>";
echo "<h2>🎯 Diagnosis</h2>";

if (isset($data['nb'])) {
    $m1t5_keys = array_keys($data['nb']);
    $db_keys = array_column($nbs, 'nbcode');

    echo "<h3>Key Comparison:</h3>";
    echo "<p><strong>M1T5 outputs:</strong> " . implode(', ', $m1t5_keys) . "</p>";
    echo "<p><strong>Database has:</strong> " . implode(', ', $db_keys) . "</p>";

    if ($m1t5_keys != $db_keys) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Key Format Mismatch!</h3>";
        echo "<p>M1T5 (raw_collector) is changing the NB key format when loading from database!</p>";
        echo "<p>This means the issue is in the raw_collector, not synthesis_engine.</p>";
        echo "</div>";
    }

    // Test old regex
    $old_regex_passes = 0;
    foreach ($m1t5_keys as $key) {
        if (preg_match('/^NB\d+$/', $key)) {
            $old_regex_passes++;
        }
    }

    echo "<h3>Filter Test Results:</h3>";
    echo "<p><strong>Old regex (/^NB\d+$/) would match:</strong> {$old_regex_passes} out of " . count($m1t5_keys) . " NBs</p>";
    echo "<p><strong>New regex (/^NB-?\d+$/) would match:</strong> " . count($m1t5_keys) . " out of " . count($m1t5_keys) . " NBs</p>";

    if ($old_regex_passes == count($m1t5_keys)) {
        echo "<div class='success'>";
        echo "<p>✅ All M1T5 keys match the old regex - regex fix not needed for current data!</p>";
        echo "<p>The issue must be elsewhere in the pipeline.</p>";
        echo "</div>";
    }
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
