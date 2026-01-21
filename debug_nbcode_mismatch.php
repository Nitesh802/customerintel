<?php
/**
 * Debug NB code mismatch between inputs and database
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/debug_nbcode_mismatch.php'));
$PAGE->set_title("Debug NB Code Mismatch");

echo $OUTPUT->header();

?>
<style>
.debug { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: bold; }
</style>

<div class="debug">

<h1>🔍 NB Code Mismatch Debug - Run <?= $runid ?></h1>

<?php

require_once(__DIR__ . '/classes/services/raw_collector.php');

$raw_collector = new \local_customerintel\services\raw_collector();

// Get inputs from raw_collector
$inputs = $raw_collector->get_normalized_inputs($runid);

echo "<div class='section'>";
echo "<h2>NB Codes in raw_collector Output</h2>";
echo "<pre>";
print_r(array_keys($inputs['nb']));
echo "</pre>";
echo "</div>";

// Get NBs from database
$nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);

echo "<div class='section'>";
echo "<h2>NB Codes in Database</h2>";
$db_codes = [];
foreach ($nb_results as $result) {
    $db_codes[] = $result->nbcode;
}
echo "<pre>";
print_r($db_codes);
echo "</pre>";
echo "</div>";

// Show comparison
echo "<div class='section warning'>";
echo "<h2>🔍 Code Matching Test</h2>";
echo "<table>";
echo "<tr>";
echo "<th>Input Code<br>(from raw_collector)</th>";
echo "<th>Matches DB?</th>";
echo "<th>Citations in Input</th>";
echo "<th>DB Code</th>";
echo "<th>Citations in DB</th>";
echo "</tr>";

foreach ($inputs['nb'] as $nbcode => $nb_data) {
    $citations_in_input = count($nb_data['citations'] ?? []);

    // Try to find matching DB record
    $found = false;
    $db_nbcode = '';
    $citations_in_db = 0;

    foreach ($nb_results as $result) {
        if ($result->nbcode === $nbcode) {
            $found = true;
            $db_nbcode = $result->nbcode;

            if (!empty($result->citations)) {
                $citations = json_decode($result->citations, true);
                if (is_array($citations)) {
                    $citations_in_db = count($citations);
                }
            }
            break;
        }
    }

    $match_class = $found ? 'good' : 'bad';

    echo "<tr class='{$match_class}'>";
    echo "<td><strong>{$nbcode}</strong></td>";
    echo "<td>" . ($found ? '✅ YES' : '❌ NO') . "</td>";
    echo "<td>{$citations_in_input}</td>";
    echo "<td>" . ($found ? $db_nbcode : '<em>not found</em>') . "</td>";
    echo "<td>" . ($found ? $citations_in_db : '-') . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// Show what canonical_builder is looking for
echo "<div class='section'>";
echo "<h2>What canonical_builder Does</h2>";
echo "<ol>";
echo "<li>Receives <code>\$inputs['nb']</code> with keys: <strong>" . implode(', ', array_keys($inputs['nb'])) . "</strong></li>";
echo "<li>Creates <code>\$canonical_nbkeys = array_keys(\$inputs['nb'])</code></li>";
echo "<li>Loads NBs from database with codes: <strong>" . implode(', ', $db_codes) . "</strong></li>";
echo "<li>Loops through canonical_nbkeys and tries to find matching <code>\$result->nbcode</code></li>";
echo "<li>If <code>\$result->nbcode === \$nbcode</code>, extracts citations from database</li>";
echo "</ol>";
echo "</div>";

// Diagnosis
$all_match = true;
foreach ($inputs['nb'] as $nbcode => $nb_data) {
    $found = false;
    foreach ($nb_results as $result) {
        if ($result->nbcode === $nbcode) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $all_match = false;
        break;
    }
}

$class = $all_match ? 'good' : 'bad';
echo "<div class='section {$class}'>";
echo "<h2>🔬 Diagnosis</h2>";

if ($all_match) {
    echo "<p><strong style='color: #28a745;'>✅ All NB codes match!</strong></p>";
    echo "<p>The codes from raw_collector match the database codes.</p>";
    echo "<p>If canonical_builder shows 0 citations, there's another issue in the code logic.</p>";
} else {
    echo "<p><strong style='color: #dc3545;'>❌ NB code MISMATCH!</strong></p>";
    echo "<p>raw_collector uses normalized codes (e.g., NB1) but database has hyphenated codes (e.g., NB-1).</p>";
    echo "<p>canonical_builder can't find matching records because it's doing strict equality comparison.</p>";
    echo "<p><strong>Solution:</strong> canonical_builder needs to normalize NB codes before comparison.</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
