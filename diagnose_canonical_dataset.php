<?php
/**
 * Diagnostic: Check canonical dataset structure
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 190;

echo "<h1>Canonical Dataset Diagnostic</h1>";
echo "<p>Building and analyzing canonical dataset for Run {$runid}</p>";

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/canonical_builder.php');

$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

$builder = new \local_customerintel\services\canonical_builder();
$canonical_nbkeys = array_keys($raw_inputs['nb']);

echo "<h2>Canonical NB Keys</h2>";
echo "<p>Count: " . count($canonical_nbkeys) . "</p>";
echo "<pre>";
print_r($canonical_nbkeys);
echo "</pre>";

$canonical = $builder->build_canonical_nb_dataset($raw_inputs, $canonical_nbkeys, $runid);

echo "<h2>Canonical Dataset Structure</h2>";
echo "<h3>Top-Level Keys</h3>";
echo "<pre>";
print_r(array_keys($canonical));
echo "</pre>";

echo "<h3>Metadata</h3>";
echo "<pre>";
print_r($canonical['metadata']);
echo "</pre>";

echo "<h3>Processing Stats</h3>";
echo "<pre>";
print_r($canonical['processing_stats']);
echo "</pre>";

echo "<h3>NB Data Sample (NB-1)</h3>";
if (isset($canonical['nb_data']['NB-1'])) {
    $nb1 = $canonical['nb_data']['NB-1'];
    echo "<p>Keys in NB-1:</p>";
    echo "<pre>";
    print_r(array_keys($nb1));
    echo "</pre>";

    echo "<p>Citations in NB-1: <strong>" . count($nb1['citations'] ?? []) . "</strong></p>";
    if (!empty($nb1['citations'])) {
        echo "<pre>";
        print_r(array_slice($nb1['citations'], 0, 3));
        echo "</pre>";
    }

    echo "<p>Data keys:</p>";
    echo "<pre>";
    print_r(array_keys($nb1['data'] ?? []));
    echo "</pre>";
} else {
    echo "<p><strong>⚠️ NB-1 not found in nb_data!</strong></p>";
    echo "<p>Available keys:</p>";
    echo "<pre>";
    print_r(array_keys($canonical['nb_data']));
    echo "</pre>";
}

echo "<h3>Top-Level Citations Array</h3>";
echo "<p>Count: " . count($canonical['citations'] ?? []) . "</p>";
if (!empty($canonical['citations'])) {
    echo "<pre>";
    print_r(array_slice($canonical['citations'], 0, 3));
    echo "</pre>";
} else {
    echo "<p><em>Top-level citations array is empty (citations are per-NB instead)</em></p>";
}

echo "<h2>Citation Count Per NB</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>NB Code</th><th>Status</th><th>Citations</th><th>Tokens</th></tr>";

foreach ($canonical['nb_data'] as $nbcode => $nbdata) {
    echo "<tr>";
    echo "<td>{$nbcode}</td>";
    echo "<td>{$nbdata['status']}</td>";
    echo "<td>" . count($nbdata['citations'] ?? []) . "</td>";
    echo "<td>{$nbdata['tokens_used']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Summary</h2>";
echo "<ul>";
echo "<li><strong>Total NBs:</strong> " . count($canonical['nb_data']) . "</li>";
echo "<li><strong>Total Citations (from stats):</strong> " . ($canonical['processing_stats']['total_citations'] ?? 0) . "</li>";
echo "<li><strong>Avg Tokens per NB:</strong> " . ($canonical['processing_stats']['avg_tokens_per_nb'] ?? 0) . "</li>";
echo "</ul>";

?>
