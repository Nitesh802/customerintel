<?php
/**
 * Force Clear NB Cache for ViiV → J&J
 * This will delete all cached NB results so the next run generates fresh NBs
 *
 * Usage: https://sales.multi.rubi.digital/local/customerintel/force_clear_nb_cache_viiv_jnj.php
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB;

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Force Clear NB Cache</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
.warning { border-left-color: #ff9800; background: #fff3cd; }
.success { border-left-color: #4CAF50; background: #d4edda; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; }
button { background: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
button:hover { background: #d32f2f; }
</style></head><body>";

echo "<h1>🗑️ Force Clear NB Cache - ViiV Healthcare → Johnson & Johnson</h1>";
echo "<hr>";

$confirmed = optional_param('confirm', 0, PARAM_INT);

if (!$confirmed) {
    // Show confirmation form
    echo "<div class='box warning'>";
    echo "<h2>⚠️ Warning: This will delete ALL cached NB results</h2>";
    echo "<p>This action will:</p>";
    echo "<ul>";
    echo "<li>Delete all NB results from <code>local_ci_nb_result</code> table</li>";
    echo "<li>Force the next run to generate fresh NBs (no cache reuse)</li>";
    echo "<li>Take 10-15 minutes to regenerate all 15 NBs</li>";
    echo "</ul>";
    echo "<p><strong>This cannot be undone!</strong></p>";
    echo "<form method='get'>";
    echo "<input type='hidden' name='confirm' value='1'>";
    echo "<button type='submit'>Yes, Delete All Cached NBs</button>";
    echo "</form>";
    echo "</div>";

    // Show what will be deleted
    $nb_count = $DB->count_records('local_ci_nb_result');
    echo "<div class='box'>";
    echo "<h2>📊 Current Cache Status</h2>";
    echo "<p>Total NB results in cache: <strong>{$nb_count}</strong></p>";

    if ($nb_count > 0) {
        $runs = $DB->get_records_sql("
            SELECT DISTINCT runid, COUNT(*) as nb_count
            FROM {local_ci_nb_result}
            GROUP BY runid
            ORDER BY runid DESC
        ");

        echo "<table style='width:100%; border-collapse: collapse; margin-top:10px;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Run ID</th>";
        echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>NB Count</th>";
        echo "</tr>";

        foreach ($runs as $run) {
            echo "<tr>";
            echo "<td style='padding:8px; border:1px solid #ddd;'>Run {$run->runid}</td>";
            echo "<td style='padding:8px; border:1px solid #ddd;'>{$run->nb_count} NBs</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

} else {
    // Actually delete
    try {
        $deleted_count = $DB->count_records('local_ci_nb_result');

        $DB->delete_records('local_ci_nb_result');

        echo "<div class='box success'>";
        echo "<h2>✅ Cache Cleared Successfully</h2>";
        echo "<p><strong>Deleted {$deleted_count} NB results</strong></p>";
        echo "<p>Next steps:</p>";
        echo "<ol>";
        echo "<li>Go to Dashboard</li>";
        echo "<li>Create a new run (Run 236)</li>";
        echo "<li>Select 'Full Refresh' for cache strategy</li>";
        echo "<li>Wait for cron to process (10-15 minutes)</li>";
        echo "<li>New NBs will be generated from scratch with M3.5 synthesis</li>";
        echo "</ol>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='box' style='border-left-color: #f44336; background: #ffebee;'>";
        echo "<h2>❌ Error</h2>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
}

echo "</body></html>";
