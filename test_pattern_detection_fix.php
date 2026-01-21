<?php
/**
 * Test if NB key normalization fix resolves pattern detection
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 190;

echo "<h1>Pattern Detection Fix Verification</h1>";
echo "<p>Testing if NB key normalization fix allows pattern detection to work</p>";

set_debugging(DEBUG_DEVELOPER, true);

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/analysis_engine.php');

$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

echo "<h2>NB Keys After Fix</h2>";
$nb_keys = array_keys($raw_inputs['nb']);
echo "<p>Count: " . count($nb_keys) . "</p>";
echo "<pre>";
print_r($nb_keys);
echo "</pre>";

echo "<h3>Check Expected Keys</h3>";
$checks = ['NB1', 'NB3', 'NB4', 'NB8', 'NB11', 'NB13'];
foreach ($checks as $key) {
    echo "<p>{$key}: " . (isset($raw_inputs['nb'][$key]) ? "✅ Found" : "❌ Missing") . "</p>";
}

echo "<h2>Pattern Detection Test</h2>";

$analyzer = new \local_customerintel\services\analysis_engine($runid, []);

$start = microtime(true);
$result = $analyzer->generate_synthesis($raw_inputs);
$duration = microtime(true) - $start;

echo "<p>Duration: <strong>" . round($duration, 3) . "s</strong></p>";

echo "<h3>Patterns Detected</h3>";
if (isset($result['patterns'])) {
    foreach ($result['patterns'] as $key => $items) {
        echo "<p><strong>{$key}:</strong> " . count($items) . " items</p>";
    }
}

echo "<h3>Sections Generated</h3>";
echo "<p>Count: <strong>" . count($result['sections'] ?? []) . "</strong></p>";

if (!empty($result['sections'])) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Section</th><th>Has Content</th><th>Length</th></tr>";

    foreach ($result['sections'] as $code => $section) {
        $has_text = isset($section['text']) && !empty($section['text']);
        $length = $has_text ? strlen($section['text']) : 0;

        echo "<tr>";
        echo "<td><strong>{$code}</strong></td>";
        echo "<td>" . ($has_text ? "✅" : "❌") . "</td>";
        echo "<td>{$length} chars</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<h3>Sample Section Content</h3>";
    $first = reset($result['sections']);
    if (isset($first['text'])) {
        echo "<pre style='background: #f5f5f5; padding: 15px; white-space: pre-wrap;'>";
        echo htmlspecialchars(substr($first['text'], 0, 500));
        if (strlen($first['text']) > 500) echo "\n... (truncated)";
        echo "</pre>";
    }
}

echo "<h2>Verdict</h2>";

$patterns_count = 0;
if (isset($result['patterns'])) {
    foreach ($result['patterns'] as $items) {
        $patterns_count += count($items);
    }
}

if ($patterns_count > 0 && count($result['sections'] ?? []) >= 9) {
    echo "<p style='color: #28a745; font-size: 20px; font-weight: bold;'>✅ FIX SUCCESSFUL!</p>";
    echo "<p>Pattern detection now works and all 9 sections are generated!</p>";
} else if ($patterns_count > 0) {
    echo "<p style='color: #ffc107; font-size: 20px; font-weight: bold;'>⚠️ PARTIAL FIX</p>";
    echo "<p>Patterns detected: {$patterns_count}, but only " . count($result['sections'] ?? []) . " sections generated</p>";
} else {
    echo "<p style='color: #dc3545; font-size: 20px; font-weight: bold;'>❌ FIX INCOMPLETE</p>";
    echo "<p>Still no patterns detected - may need additional fixes</p>";
}

?>
