<?php
/**
 * Diagnostic: Check raw_collector output structure
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 190;

echo "<h1>Raw Collector Output Diagnostic</h1>";
echo "<p>Analyzing structure returned by raw_collector for Run {$runid}</p>";

require_once(__DIR__ . '/classes/services/raw_collector.php');

$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

echo "<h2>Top-Level Keys</h2>";
echo "<pre>";
print_r(array_keys($raw_inputs));
echo "</pre>";

echo "<h2>Top-Level Citations</h2>";
echo "<p>Count: " . count($raw_inputs['citations'] ?? []) . "</p>";
if (!empty($raw_inputs['citations'])) {
    echo "<pre>";
    print_r(array_slice($raw_inputs['citations'], 0, 3));
    echo "</pre>";
} else {
    echo "<p><strong>⚠️ No top-level citations array!</strong></p>";
}

echo "<h2>NB Structure Sample (NB1)</h2>";
if (isset($raw_inputs['nb']['NB1'])) {
    echo "<p>Keys in NB1:</p>";
    echo "<pre>";
    print_r(array_keys($raw_inputs['nb']['NB1']));
    echo "</pre>";

    // Check if citations are in payload
    if (isset($raw_inputs['nb']['NB1']['payload']['citations'])) {
        echo "<p><strong>✅ Found citations in NB1 payload!</strong></p>";
        echo "<p>Count: " . count($raw_inputs['nb']['NB1']['payload']['citations']) . "</p>";
        echo "<pre>";
        print_r(array_slice($raw_inputs['nb']['NB1']['payload']['citations'], 0, 2));
        echo "</pre>";
    }

    // Check if citations are in data
    if (isset($raw_inputs['nb']['NB1']['data'])) {
        echo "<p>NB1 has 'data' key with keys:</p>";
        echo "<pre>";
        print_r(array_keys($raw_inputs['nb']['NB1']['data']));
        echo "</pre>";
    }

    // Check if citations are separate
    if (isset($raw_inputs['nb']['NB1']['citations'])) {
        echo "<p><strong>✅ Found citations as separate field in NB1!</strong></p>";
        echo "<p>Count: " . count($raw_inputs['nb']['NB1']['citations']) . "</p>";
        echo "<pre>";
        print_r(array_slice($raw_inputs['nb']['NB1']['citations'], 0, 2));
        echo "</pre>";
    }
}

echo "<h2>Diversity Metadata</h2>";
if (isset($raw_inputs['diversity_metadata'])) {
    echo "<pre>";
    print_r($raw_inputs['diversity_metadata']);
    echo "</pre>";
} else {
    echo "<p>No diversity_metadata found</p>";
}

echo "<h2>Processing Stats</h2>";
if (isset($raw_inputs['processing_stats'])) {
    echo "<pre>";
    print_r($raw_inputs['processing_stats']);
    echo "</pre>";
} else {
    echo "<p>No processing_stats found</p>";
}

echo "<h2>Full Structure (First 50 lines)</h2>";
echo "<pre>";
$full_dump = print_r($raw_inputs, true);
$lines = explode("\n", $full_dump);
echo implode("\n", array_slice($lines, 0, 50));
echo "\n... (truncated)\n";
echo "</pre>";

?>
