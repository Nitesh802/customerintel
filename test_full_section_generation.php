<?php
/**
 * Test if all 9 sections are being generated or if exceptions are occurring
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 190;

echo "<h1>Full Section Generation Test</h1>";
echo "<p>Testing why only 3 sections are generated instead of 9</p>";

// Enable debugging to see exceptions
set_debugging(DEBUG_DEVELOPER, true);

require_once(__DIR__ . '/classes/services/raw_collector.php');
require_once(__DIR__ . '/classes/services/canonical_builder.php');
require_once(__DIR__ . '/classes/services/analysis_engine.php');

$collector = new \local_customerintel\services\raw_collector();
$raw_inputs = $collector->get_normalized_inputs($runid);

$builder = new \local_customerintel\services\canonical_builder();
$canonical_nbkeys = array_keys($raw_inputs['nb']);
$canonical = $builder->build_canonical_nb_dataset($raw_inputs, $canonical_nbkeys, $runid);

$analyzer = new \local_customerintel\services\analysis_engine($runid, $canonical);

echo "<h2>Generating Synthesis</h2>";
$start = microtime(true);
$result = $analyzer->generate_synthesis($raw_inputs);
$duration = microtime(true) - $start;

echo "<p>Duration: <strong>" . round($duration, 3) . "s</strong></p>";
echo "<p>Sections count: <strong>" . count($result['sections'] ?? []) . "</strong></p>";

if (isset($result['sections'])) {
    echo "<h3>Sections Generated</h3>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Section Code</th><th>Has Content</th><th>Content Length</th></tr>";

    $expected_sections = [
        'executive_insight',
        'customer_fundamentals',
        'financial_trajectory',
        'margin_pressures',
        'strategic_priorities',
        'growth_levers',
        'buying_behavior',
        'current_initiatives',
        'risk_signals'
    ];

    foreach ($expected_sections as $code) {
        if (isset($result['sections'][$code])) {
            $section = $result['sections'][$code];
            $has_content = isset($section['text']) && !empty($section['text']);
            $length = isset($section['text']) ? strlen($section['text']) : 0;

            echo "<tr>";
            echo "<td><strong>{$code}</strong></td>";
            echo "<td>" . ($has_content ? "✅ YES" : "❌ NO") . "</td>";
            echo "<td>{$length} chars</td>";
            echo "</tr>";
        } else {
            echo "<tr style='background: #ffcccc;'>";
            echo "<td><strong>{$code}</strong></td>";
            echo "<td colspan='2'>❌ MISSING - Not generated</td>";
            echo "</tr>";
        }
    }

    echo "</table>";

    echo "<h3>Sample Content (First Section)</h3>";
    if (!empty($result['sections'])) {
        $first_section = reset($result['sections']);
        echo "<pre style='white-space: pre-wrap; background: #f5f5f5; padding: 15px;'>";
        echo htmlspecialchars(substr($first_section['text'] ?? 'No text', 0, 500));
        echo "</pre>";
    }
}

echo "<h3>Patterns Detected</h3>";
echo "<p>Count: " . count($result['patterns'] ?? []) . "</p>";
if (!empty($result['patterns'])) {
    echo "<ul>";
    foreach (array_keys($result['patterns']) as $key) {
        echo "<li>{$key}</li>";
    }
    echo "</ul>";
}

echo "<h2>Debug Info</h2>";
echo "<p>Check the Moodle debug output above for any errors or exceptions caught during section generation.</p>";

?>

<style>
table { border-collapse: collapse; }
th { background: #e9ecef; text-align: left; }
td { border: 1px solid #dee2e6; }
</style>
