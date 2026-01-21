<?php
/**
 * Emergency: Clear Synthesis Cache for ViiV → J&J
 * This deletes cached synthesis results that have NB1 format
 *
 * Usage: https://sales.multi.rubi.digital/local/customerintel/clear_synthesis_cache.php
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB;

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Clear Synthesis Cache</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196F3; }
.warning { border-left-color: #ff9800; background: #fff3cd; }
.success { border-left-color: #4CAF50; background: #d4edda; }
.error { border-left-color: #f44336; background: #ffebee; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; }
button { background: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
button:hover { background: #d32f2f; }
</style></head><body>";

echo "<h1>🗑️ Clear Synthesis Cache - Emergency Fix for NB Code Format Change</h1>";
echo "<hr>";

$confirmed = optional_param('confirm', 0, PARAM_INT);

if (!$confirmed) {
    echo "<div class='box error'>";
    echo "<h2>⚠️ EMERGENCY: Cache Format Mismatch</h2>";
    echo "<p><strong>Problem:</strong> Cached synthesis data uses old NB1 format, but code now expects NB-1</p>";
    echo "<p><strong>Impact:</strong> Run 241 generated 0 sections because cache lookup failed</p>";
    echo "<p><strong>Solution:</strong> Delete cached synthesis records for ViiV → J&J</p>";
    echo "</div>";

    echo "<div class='box warning'>";
    echo "<h2>⚠️ Warning: This will delete cached synthesis results</h2>";
    echo "<p>This action will:</p>";
    echo "<ul>";
    echo "<li>Delete synthesis cache records from <code>local_ci_synthesis</code> table</li>";
    echo "<li>Force next run to regenerate synthesis from scratch</li>";
    echo "<li>Take 5-10 minutes to rebuild synthesis</li>";
    echo "</ul>";
    echo "<p><strong>This cannot be undone!</strong></p>";
    echo "<form method='get'>";
    echo "<input type='hidden' name='confirm' value='1'>";
    echo "<button type='submit'>Yes, Clear Synthesis Cache for ViiV → J&J</button>";
    echo "</form>";
    echo "</div>";

    // Show what will be deleted
    $cache_count = $DB->count_records('local_ci_synthesis', [
        'sourcecompanyid' => 17, // ViiV
        'targetcompanyid' => 1   // J&J
    ]);

    echo "<div class='box'>";
    echo "<h2>📊 Current Cache Status</h2>";
    echo "<p>Synthesis cache records for ViiV → J&J: <strong>{$cache_count}</strong></p>";

    if ($cache_count > 0) {
        $records = $DB->get_records('local_ci_synthesis', [
            'sourcecompanyid' => 17,
            'targetcompanyid' => 1
        ], 'timecreated DESC', '*', 0, 10);

        echo "<table style='width:100%; border-collapse: collapse; margin-top:10px;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>ID</th>";
        echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Created</th>";
        echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Age</th>";
        echo "</tr>";

        foreach ($records as $record) {
            $age = round((time() - $record->timecreated) / 3600, 1);
            echo "<tr>";
            echo "<td style='padding:8px; border:1px solid #ddd;'>{$record->id}</td>";
            echo "<td style='padding:8px; border:1px solid #ddd;'>" . date('Y-m-d H:i:s', $record->timecreated) . "</td>";
            echo "<td style='padding:8px; border:1px solid #ddd;'>{$age} hours ago</td>";
            echo "</tr>";
        }
        echo "</table>";
        if ($cache_count > 10) {
            echo "<p><em>Showing 10 most recent of {$cache_count} total records</em></p>";
        }
    }
    echo "</div>";

} else {
    // Actually delete
    try {
        $deleted_count = $DB->count_records('local_ci_synthesis', [
            'sourcecompanyid' => 17,
            'targetcompanyid' => 1
        ]);

        $DB->delete_records('local_ci_synthesis', [
            'sourcecompanyid' => 17,
            'targetcompanyid' => 1
        ]);

        echo "<div class='box success'>";
        echo "<h2>✅ Synthesis Cache Cleared Successfully</h2>";
        echo "<p><strong>Deleted {$deleted_count} synthesis cache records</strong></p>";
        echo "<p>Next steps:</p>";
        echo "<ol>";
        echo "<li>Go to Dashboard</li>";
        echo "<li>Create Run 242 with 'Full Refresh'</li>";
        echo "<li>Wait for cron to process (5-10 minutes)</li>";
        echo "<li>System will regenerate synthesis from NBs with correct NB1 format</li>";
        echo "<li>M3.5 formatting should work correctly</li>";
        echo "</ol>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='box error'>";
        echo "<h2>❌ Error</h2>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
}

echo "</body></html>";
