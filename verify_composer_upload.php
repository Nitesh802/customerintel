<?php
/**
 * Verify synthesis_composer.php Has Latest Changes
 * Checks if NB normalization code is present
 *
 * Usage: https://sales.multi.rubi.digital/local/customerintel/verify_composer_upload.php
 */

require_once('../../config.php');
require_login();

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Verify Composer Upload</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
.success { border-left-color: #4CAF50; background: #d4edda; }
.error { border-left-color: #f44336; background: #ffebee; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; white-space: pre-wrap; overflow-x: auto; }
</style></head><body>";

echo "<h1>🔍 Verify synthesis_composer.php Upload</h1>";
echo "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

$composer_file = $CFG->dirroot . '/local/customerintel/classes/services/synthesis_composer.php';

if (!file_exists($composer_file)) {
    echo "<div class='box error'>";
    echo "<h2>❌ File Not Found</h2>";
    echo "</div>";
} else {
    $content = file_get_contents($composer_file);

    echo "<div class='box'>";
    echo "<h2>📄 File Info</h2>";
    echo "<pre>";
    echo "File: {$composer_file}\n";
    echo "Size: " . number_format(strlen($content)) . " bytes\n";
    echo "Last Modified: " . date('Y-m-d H:i:s', filemtime($composer_file)) . "\n";
    $age_minutes = round((time() - filemtime($composer_file)) / 60, 1);
    echo "Age: {$age_minutes} minutes ago\n";
    echo "</pre>";
    echo "</div>";

    // Check for critical M3.5 code
    $checks = [
        'NB normalization code' => 'M3.5 Fix: Try both NB1 and NB-1 formats',
        'str_replace for hyphen' => 'str_replace(\'NB\', \'NB-\', $nb_code)',
        'NB mapping uses NB1' => '\'NB1\' => \'Company Overview\'',
        'CSS validation fix' => '\'subsection-header\', \'perf-gap\', \'timeline\', \'accountability\'',
        'HTML quoting fix' => 'class=\"subsection-header perf-gap\"'
    ];

    $all_present = true;
    $results = [];

    foreach ($checks as $check_name => $search_string) {
        $found = strpos($content, $search_string) !== false;
        $results[$check_name] = $found;
        if (!$found) {
            $all_present = false;
        }
    }

    $box_class = $all_present ? 'box success' : 'box error';
    echo "<div class='{$box_class}'>";
    echo "<h2>" . ($all_present ? '✅' : '❌') . " M3.5 Code Verification</h2>";
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f0f0f0;'>";
    echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Check</th>";
    echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Status</th>";
    echo "</tr>";

    foreach ($results as $check_name => $found) {
        $status = $found ? '✅ Found' : '❌ MISSING';
        $color = $found ? '' : "style='background:#ffebee;'";
        echo "<tr {$color}>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>{$check_name}</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    if (!$all_present) {
        echo "<div class='box error'>";
        echo "<h2>❌ ACTION REQUIRED</h2>";
        echo "<p>The file is missing critical M3.5 code. You need to:</p>";
        echo "<ol>";
        echo "<li>Re-upload synthesis_composer.php from your local machine</li>";
        echo "<li>Run <a href='clear_opcache.php'>clear_opcache.php</a></li>";
        echo "<li>Verify again using this page</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div class='box success'>";
        echo "<h2>✅ Next Steps</h2>";
        echo "<p>All M3.5 code is present! Now:</p>";
        echo "<ol>";
        echo "<li>Run <a href='clear_opcache.php'>clear_opcache.php</a> to clear PHP opcache</li>";
        echo "<li>Wait 1-2 minutes for opcache to fully clear</li>";
        echo "<li>Create Run 243 with Full Refresh</li>";
        echo "<li>M3.5 formatting should work!</li>";
        echo "</ol>";
        echo "</div>";
    }
}

echo "</body></html>";
