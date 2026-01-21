<?php
/**
 * Check M3 Code Deployment Status
 * Usage: https://sales.multi.rubi.digital/local/customerintel/check_m3_deployment.php
 */

require_once('../../config.php');
require_login();

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>M3 Deployment Check</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
.success { border-left-color: #4CAF50; }
.error { border-left-color: #f44336; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; white-space: pre-wrap; }
</style></head><body>";

echo "<h1>🔍 M3/M3.5 Code Deployment Check</h1>";
echo "<hr>";

$composer_file = $CFG->dirroot . '/local/customerintel/classes/services/synthesis_composer.php';

if (!file_exists($composer_file)) {
    echo "<div class='box error'>";
    echo "<h2>❌ File Not Found</h2>";
    echo "<p>synthesis_composer.php does not exist at expected location</p>";
    echo "</div>";
} else {
    $content = file_get_contents($composer_file);

    echo "<div class='box'>";
    echo "<h2>📄 File Info</h2>";
    echo "<pre>";
    echo "File: {$composer_file}\n";
    echo "Size: " . number_format(strlen($content)) . " bytes\n";
    echo "Last Modified: " . date('Y-m-d H:i:s', filemtime($composer_file)) . "\n";
    echo "</pre>";
    echo "</div>";

    // Check for M3 code markers
    $checks = [
        'M3 formatting call' => 'generate_formatted_section(',
        'M3 log trace' => '[M3] Attempting to format section',
        'M3.5 log trace' => '[M3.5] Starting CVS-style section formatting',
        'M3.5 CSS classes' => 'class=subsection-header perf-gap',
        'CVS prompt' => 'CVS-style strategic opportunity brief',
    ];

    $results = [];
    foreach ($checks as $check_name => $search_string) {
        $found = strpos($content, $search_string) !== false;
        $results[$check_name] = $found;
    }

    $all_present = !in_array(false, $results, true);

    $box_class = $all_present ? 'box success' : 'box error';
    echo "<div class='{$box_class}'>";
    echo "<h2>" . ($all_present ? '✅' : '❌') . " M3/M3.5 Code Status</h2>";
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f0f0f0;'>";
    echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Check</th>";
    echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Status</th>";
    echo "</tr>";

    foreach ($results as $check_name => $found) {
        $status = $found ? '✅ Found' : '❌ Missing';
        echo "<tr>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>{$check_name}</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Show method signature
    if (preg_match('/private function generate_formatted_section\([^)]+\)\s*\{/', $content, $matches)) {
        echo "<div class='box success'>";
        echo "<h2>✅ generate_formatted_section() Method Found</h2>";
        echo "<pre>" . htmlspecialchars($matches[0]) . "</pre>";
        echo "</div>";
    } else {
        echo "<div class='box error'>";
        echo "<h2>❌ generate_formatted_section() Method Missing</h2>";
        echo "<p>The M3 formatting method was not found in the file</p>";
        echo "</div>";
    }

    // Check if method is being called
    $call_count = substr_count($content, 'generate_formatted_section(');
    echo "<div class='box'>";
    echo "<h2>📊 Method Call Analysis</h2>";
    echo "<p><strong>Total calls to generate_formatted_section():</strong> {$call_count}</p>";
    if ($call_count === 0) {
        echo "<p style='color: #f44336;'>⚠️ Method is never called in this file!</p>";
    } elseif ($call_count === 1) {
        echo "<p style='color: #ff9800;'>⚠️ Method is only called once (may be definition only)</p>";
    } else {
        echo "<p style='color: #4CAF50;'>✅ Method is called {$call_count} times</p>";
    }
    echo "</div>";
}

echo "</body></html>";
