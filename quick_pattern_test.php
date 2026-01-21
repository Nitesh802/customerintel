<?php
require_once(__DIR__ . '/../../config.php');
require_login();
global $DB;

$runid = 190;

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/analysis_engine.php');

$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

// Check if NB1 now has 'data' key
echo "NB1 has 'data' key: " . (isset($raw_inputs['nb']['NB1']['data']) ? "YES" : "NO") . "\n";

$analyzer = new \local_customerintel\services\analysis_engine($runid, []);
$result = $analyzer->generate_synthesis($raw_inputs);

echo "\nPattern Counts:\n";
foreach ($result['patterns'] ?? [] as $key => $items) {
    echo "- {$key}: " . count($items) . "\n";
}

$total_patterns = 0;
foreach ($result['patterns'] ?? [] as $items) {
    $total_patterns += count($items);
}

echo "\nTotal patterns: {$total_patterns}\n";
echo "Sections: " . count($result['sections'] ?? []) . "\n";

if ($total_patterns > 0) {
    echo "\n✅ PATTERN DETECTION WORKING!\n";
} else {
    echo "\n❌ Still not working\n";
}
?>
