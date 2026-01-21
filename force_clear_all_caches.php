<?php
/**
 * Force Clear ALL Caches - M3.5 Deployment
 * This clears Moodle caches and confirms PHP opcache status
 *
 * Usage: https://sales.multi.rubi.digital/local/customerintel/force_clear_all_caches.php
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Force Clear All Caches</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
.success { border-left-color: #4CAF50; background: #d4edda; }
.warning { border-left-color: #ff9800; background: #fff3cd; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; }
</style></head><body>";

echo "<h1>🗑️ Force Clear ALL Caches</h1>";
echo "<hr>";

$results = [];

// 1. Clear Moodle theme cache
try {
    theme_reset_all_caches();
    $results[] = ['✅ Theme cache', 'Cleared successfully'];
} catch (Exception $e) {
    $results[] = ['❌ Theme cache', 'Error: ' . $e->getMessage()];
}

// 2. Purge all caches
try {
    purge_all_caches();
    $results[] = ['✅ All Moodle caches', 'Purged successfully'];
} catch (Exception $e) {
    $results[] = ['❌ All Moodle caches', 'Error: ' . $e->getMessage()];
}

// 3. Clear cache store
try {
    cache_helper::purge_all();
    $results[] = ['✅ Cache store', 'Purged successfully'];
} catch (Exception $e) {
    $results[] = ['❌ Cache store', 'Error: ' . $e->getMessage()];
}

// 4. Check PHP opcache status
$opcache_info = '';
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        $opcache_info = "Enabled: " . ($status['opcache_enabled'] ? 'Yes' : 'No') . "\n";
        $opcache_info .= "Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        $opcache_info .= "Memory usage: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        $results[] = ['ℹ️ PHP opcache', 'Active - ' . $status['opcache_statistics']['num_cached_scripts'] . ' scripts cached'];
    } else {
        $results[] = ['ℹ️ PHP opcache', 'Disabled or not available'];
    }
} else {
    $results[] = ['ℹ️ PHP opcache', 'Not available (function not found)'];
}

// 5. Check file timestamps
$composer_file = $CFG->dirroot . '/local/customerintel/classes/services/synthesis_composer.php';
$engine_file = $CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php';
$css_file = $CFG->dirroot . '/local/customerintel/styles/report.css';

$files = [
    'synthesis_composer.php' => $composer_file,
    'synthesis_engine.php' => $engine_file,
    'report.css' => $css_file
];

echo "<div class='box success'>";
echo "<h2>✅ Cache Clearing Complete</h2>";
echo "<table style='width:100%; border-collapse: collapse;'>";
echo "<tr style='background:#f0f0f0;'>";
echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Operation</th>";
echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Result</th>";
echo "</tr>";

foreach ($results as list($operation, $result)) {
    echo "<tr>";
    echo "<td style='padding:8px; border:1px solid #ddd;'>{$operation}</td>";
    echo "<td style='padding:8px; border:1px solid #ddd;'>{$result}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

echo "<div class='box'>";
echo "<h2>📄 File Modification Times</h2>";
echo "<p><strong>Check if your recent uploads are reflected:</strong></p>";
echo "<table style='width:100%; border-collapse: collapse;'>";
echo "<tr style='background:#f0f0f0;'>";
echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>File</th>";
echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Last Modified</th>";
echo "</tr>";

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        $mtime = filemtime($path);
        $mod_time = date('Y-m-d H:i:s', $mtime);
        $age_minutes = round((time() - $mtime) / 60, 1);

        $color = ($age_minutes < 5) ? '#d4edda' : (($age_minutes < 60) ? '#fff3cd' : '#ffebee');

        echo "<tr style='background:{$color};'>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>{$name}</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>{$mod_time} ({$age_minutes} min ago)</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>{$name}</td>";
        echo "<td style='padding:8px; border:1px solid #ddd;'>❌ File not found</td>";
        echo "</tr>";
    }
}
echo "</table>";
echo "</div>";

if ($opcache_info) {
    echo "<div class='box warning'>";
    echo "<h2>⚠️ PHP OPcache Status</h2>";
    echo "<pre>{$opcache_info}</pre>";
    echo "<p><strong>Note:</strong> PHP opcache may still have old code cached.</p>";
    echo "<p>To clear opcache, run on server:</p>";
    echo "<pre>sudo service php8.1-fpm reload</pre>";
    echo "<p>Or:</p>";
    echo "<pre>sudo systemctl reload php8.1-fpm</pre>";
    echo "</div>";
}

echo "<div class='box success'>";
echo "<h2>✅ Next Steps</h2>";
echo "<ol>";
echo "<li>Verify file modification times above show recent timestamps</li>";
echo "<li>If opcache is active, run: <code>sudo service php8.1-fpm reload</code> on server</li>";
echo "<li>Create a new test run (Run 239) with 'Full Refresh'</li>";
echo "<li>Check logs for <code>[M3.5]</code> entries</li>";
echo "<li>Verify no 'HTML validation failed' errors</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
