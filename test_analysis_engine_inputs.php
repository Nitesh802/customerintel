<?php
/**
 * Test what analysis_engine expects vs what we're giving it
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 190;

echo "<h1>Analysis Engine Input Test</h1>";

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/canonical_builder.php');
require_once(__DIR__ . '/classes/services/analysis_engine.php');

// Get both datasets
$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

$builder = new \local_customerintel\services\canonical_builder();
$canonical_nbkeys = array_keys($raw_inputs['nb']);
$canonical = $builder->build_canonical_nb_dataset($raw_inputs, $canonical_nbkeys, $runid);

echo "<h2>Raw Inputs Structure</h2>";
echo "<h3>Top-level keys:</h3>";
echo "<pre>";
print_r(array_keys($raw_inputs));
echo "</pre>";

echo "<h3>Has company_source?</h3>";
echo "<p>" . (isset($raw_inputs['company_source']) ? "✅ YES" : "❌ NO") . "</p>";

echo "<h3>Has company_target?</h3>";
echo "<p>" . (isset($raw_inputs['company_target']) ? "✅ YES" : "❌ NO") . "</p>";

echo "<h3>Has 'nb' key?</h3>";
echo "<p>" . (isset($raw_inputs['nb']) ? "✅ YES (count: " . count($raw_inputs['nb']) . ")" : "❌ NO") . "</p>";

echo "<hr>";

echo "<h2>Canonical Dataset Structure</h2>";
echo "<h3>Top-level keys:</h3>";
echo "<pre>";
print_r(array_keys($canonical));
echo "</pre>";

echo "<h3>Has company_source?</h3>";
echo "<p>" . (isset($canonical['company_source']) ? "✅ YES" : "❌ NO (check metadata)") . "</p>";
if (isset($canonical['metadata']['source_company'])) {
    echo "<p>Found in metadata['source_company']: " . $canonical['metadata']['source_company']['name'] . "</p>";
}

echo "<h3>Has company_target?</h3>";
echo "<p>" . (isset($canonical['company_target']) ? "✅ YES" : "❌ NO (check metadata)") . "</p>";
if (isset($canonical['metadata']['target_company'])) {
    echo "<p>Found in metadata['target_company']: " . $canonical['metadata']['target_company']['name'] . "</p>";
}

echo "<h3>Has 'nb' key?</h3>";
echo "<p>" . (isset($canonical['nb']) ? "✅ YES" : "❌ NO") . "</p>";

echo "<h3>Has 'nb_data' key?</h3>";
echo "<p>" . (isset($canonical['nb_data']) ? "✅ YES (count: " . count($canonical['nb_data']) . ")" : "❌ NO") . "</p>";

echo "<hr>";

echo "<h2>Test Analysis Engine</h2>";

echo "<h3>Test 1: Pass RAW inputs to generate_synthesis()</h3>";
try {
    $analyzer1 = new \local_customerintel\services\analysis_engine($runid, $raw_inputs);
    $start = microtime(true);
    $result1 = $analyzer1->generate_synthesis($raw_inputs);
    $duration1 = microtime(true) - $start;

    echo "<p class='success'>✅ Success with raw inputs!</p>";
    echo "<p>Duration: " . round($duration1, 2) . "s</p>";
    echo "<p>Sections generated: " . count($result1['sections'] ?? []) . "</p>";
    echo "<p>Patterns detected: " . count($result1['patterns'] ?? []) . "</p>";
} catch (Exception $e) {
    echo "<p class='fail'>❌ Failed with raw inputs: " . $e->getMessage() . "</p>";
}

echo "<h3>Test 2: Pass CANONICAL dataset to generate_synthesis()</h3>";
try {
    $analyzer2 = new \local_customerintel\services\analysis_engine($runid, $canonical);
    $start = microtime(true);
    $result2 = $analyzer2->generate_synthesis($canonical);
    $duration2 = microtime(true) - $start;

    echo "<p class='success'>✅ Success with canonical!</p>";
    echo "<p>Duration: " . round($duration2, 2) . "s</p>";
    echo "<p>Sections generated: " . count($result2['sections'] ?? []) . "</p>";
    echo "<p>Patterns detected: " . count($result2['patterns'] ?? []) . "</p>";
} catch (Exception $e) {
    echo "<p class='fail'>❌ Failed with canonical: " . $e->getMessage() . "</p>";
}

echo "<h2>Conclusion</h2>";
echo "<p>The analysis_engine needs to receive the <strong>correct input structure</strong>.</p>";
echo "<p>Check which format (raw vs canonical) produces actual synthesis.</p>";

?>

<style>
.success { color: #28a745; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
</style>
