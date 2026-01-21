<?php
/**
 * Diagnose NB data structure to understand pattern detection failure
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 190;

echo "<h1>NB Data Structure Diagnostic</h1>";
echo "<p>Understanding why pattern detection finds 0 items</p>";

require_once(__DIR__ . '/classes/services/raw_collector.php');

$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

echo "<h2>NB1 Structure (Used for Financial Pressures)</h2>";

if (isset($raw_inputs['nb']['NB1'])) {
    $nb1 = $raw_inputs['nb']['NB1'];

    echo "<h3>Top-level keys in NB1:</h3>";
    echo "<pre>";
    print_r(array_keys($nb1));
    echo "</pre>";

    if (isset($nb1['data'])) {
        echo "<h3>Keys in NB1['data']:</h3>";
        echo "<pre>";
        print_r(array_keys($nb1['data']));
        echo "</pre>";

        echo "<h3>NB1['data'] content (first 1000 chars):</h3>";
        echo "<pre style='background: #f5f5f5; padding: 15px; white-space: pre-wrap;'>";
        echo htmlspecialchars(substr(print_r($nb1['data'], true), 0, 1000));
        echo "</pre>";
    } else {
        echo "<p><strong>❌ NB1 has NO 'data' key!</strong></p>";
    }

    if (isset($nb1['payload'])) {
        echo "<h3>Keys in NB1['payload']:</h3>";
        echo "<pre>";
        print_r(array_keys($nb1['payload']));
        echo "</pre>";

        echo "<h3>NB1['payload'] content (first 1000 chars):</h3>";
        echo "<pre style='background: #f5f5f5; padding: 15px; white-space: pre-wrap;'>";
        echo htmlspecialchars(substr(print_r($nb1['payload'], true), 0, 1000));
        echo "</pre>";
    }

    echo "<h3>Full NB1 Structure:</h3>";
    echo "<pre>";
    print_r($nb1);
    echo "</pre>";
} else {
    echo "<p><strong>❌ NB1 not found!</strong></p>";
}

echo "<h2>What Pattern Detection Expects</h2>";
echo "<p>The <code>collect_pressure_themes()</code> method looks for:</p>";
echo "<pre>
\$nb1_data = \$this->get_or(\$nb_data, 'NB1', []);
\$financial_pressures = \$this->extract_field(
    \$this->get_or(\$nb1_data, 'data', []),
    ['financial_pressures', 'pressures', 'challenges']
);
</pre>";

echo "<p>So it's looking for:</p>";
echo "<ul>";
echo "<li><code>\$nb_data['NB1']['data']['financial_pressures']</code></li>";
echo "<li>OR <code>\$nb_data['NB1']['data']['pressures']</code></li>";
echo "<li>OR <code>\$nb_data['NB1']['data']['challenges']</code></li>";
echo "</ul>";

echo "<h2>Diagnosis</h2>";
echo "<p>The NB structure from the artifact uses:</p>";
echo "<ul>";
echo "<li><code>\$nb['payload']</code> - Contains the actual company data</li>";
echo "<li><code>\$nb['metadata']</code> - Contains tokens, duration, status</li>";
echo "</ul>";

echo "<p>But pattern detection expects:</p>";
echo "<ul>";
echo "<li><code>\$nb['data']</code> - Should contain normalized field structure</li>";
echo "</ul>";

echo "<p><strong>Solution:</strong> The artifact loader needs to map 'payload' to 'data' OR analysis_engine needs to look in 'payload' instead of 'data'.</p>";

?>
