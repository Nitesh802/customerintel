<?php
/**
 * Clear PHP OPcache via Web
 * This script attempts to clear PHP opcache without SSH access
 *
 * Usage: https://sales.multi.rubi.digital/local/customerintel/clear_opcache.php
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Clear PHP OPcache</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
.success { border-left-color: #4CAF50; background: #d4edda; }
.error { border-left-color: #f44336; background: #ffebee; }
.warning { border-left-color: #ff9800; background: #fff3cd; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; white-space: pre-wrap; }
</style></head><body>";

echo "<h1>🗑️ Clear PHP OPcache</h1>";
echo "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Check if opcache is available
if (!function_exists('opcache_reset')) {
    echo "<div class='box error'>";
    echo "<h2>❌ OPcache Not Available</h2>";
    echo "<p>The opcache_reset() function is not available. This could mean:</p>";
    echo "<ul>";
    echo "<li>OPcache is not installed</li>";
    echo "<li>OPcache is disabled</li>";
    echo "<li>OPcache reset is restricted in php.ini</li>";
    echo "</ul>";
    echo "</div>";
} else {
    // Get status before clearing
    $status_before = null;
    if (function_exists('opcache_get_status')) {
        $status_before = opcache_get_status();
    }

    echo "<div class='box'>";
    echo "<h2>📊 OPcache Status Before Clearing</h2>";
    if ($status_before) {
        echo "<pre>";
        echo "Enabled: " . ($status_before['opcache_enabled'] ? 'Yes' : 'No') . "\n";
        echo "Cached scripts: " . $status_before['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "Memory used: " . round($status_before['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "Hits: " . number_format($status_before['opcache_statistics']['hits']) . "\n";
        echo "Misses: " . number_format($status_before['opcache_statistics']['misses']) . "\n";
        echo "</pre>";
    } else {
        echo "<p>OPcache status not available</p>";
    }
    echo "</div>";

    // Try to reset opcache
    try {
        $reset_result = opcache_reset();

        if ($reset_result) {
            echo "<div class='box success'>";
            echo "<h2>✅ OPcache Cleared Successfully</h2>";
            echo "<p>opcache_reset() returned TRUE - the cache has been cleared.</p>";
            echo "</div>";

            // Get status after clearing
            sleep(1); // Give it a moment
            $status_after = opcache_get_status();

            echo "<div class='box'>";
            echo "<h2>📊 OPcache Status After Clearing</h2>";
            if ($status_after) {
                echo "<pre>";
                echo "Enabled: " . ($status_after['opcache_enabled'] ? 'Yes' : 'No') . "\n";
                echo "Cached scripts: " . $status_after['opcache_statistics']['num_cached_scripts'] . "\n";
                echo "Memory used: " . round($status_after['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
                echo "</pre>";
            }
            echo "</div>";

        } else {
            echo "<div class='box error'>";
            echo "<h2>❌ OPcache Clear Failed</h2>";
            echo "<p>opcache_reset() returned FALSE. This could mean:</p>";
            echo "<ul>";
            echo "<li>OPcache reset is restricted in php.ini (opcache.restrict_api)</li>";
            echo "<li>Insufficient permissions</li>";
            echo "</ul>";
            echo "</div>";
        }

    } catch (Exception $e) {
        echo "<div class='box error'>";
        echo "<h2>❌ Error Clearing OPcache</h2>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
}

echo "<div class='box'>";
echo "<h2>📄 Verify File Timestamps</h2>";
echo "<p>Check that synthesis_composer.php shows recent modification:</p>";

$composer_file = $CFG->dirroot . '/local/customerintel/classes/services/synthesis_composer.php';
if (file_exists($composer_file)) {
    $mtime = filemtime($composer_file);
    $mod_time = date('Y-m-d H:i:s', $mtime);
    $age_minutes = round((time() - $mtime) / 60, 1);

    $color = ($age_minutes < 30) ? '#d4edda' : '#fff3cd';

    echo "<div style='background:{$color}; padding:15px; border-radius:5px; margin:10px 0;'>";
    echo "<strong>synthesis_composer.php</strong><br>";
    echo "Last Modified: {$mod_time}<br>";
    echo "Age: {$age_minutes} minutes ago";
    echo "</div>";
} else {
    echo "<p style='color:#f44336;'>❌ synthesis_composer.php not found</p>";
}
echo "</div>";

echo "<div class='box success'>";
echo "<h2>✅ Next Steps</h2>";
echo "<ol>";
echo "<li>If opcache was cleared successfully, proceed to create Run 239</li>";
echo "<li>If opcache clear failed, you'll need to contact your hosting provider or system administrator</li>";
echo "<li>Alternative: Wait 5-10 minutes - opcache may auto-refresh based on file modification time</li>";
echo "<li>Create Run 239 with 'Full Refresh' cache setting</li>";
echo "<li>Check logs for [M3.5] entries</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
