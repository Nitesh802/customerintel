<?php
/**
 * Check Run 235 Cache Setting
 * Usage: https://sales.multi.rubi.digital/local/customerintel/check_run235_cache.php
 */

require_once('../../config.php');
require_login();

global $DB;

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Run 235 Cache Check</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
.error { border-left: 4px solid #f44336; }
.success { border-left: 4px solid #4CAF50; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; }
</style></head><body>";

echo "<h1>🔍 Run 235 Cache Setting Check</h1>";
echo "<p><strong>Checked at:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

try {
    $run = $DB->get_record('local_ci_run', ['id' => 235], '*');

    if (!$run) {
        echo "<div class='box error'>";
        echo "<h2>❌ Run 235 Not Found</h2>";
        echo "</div>";
    } else {
        echo "<div class='box'>";
        echo "<h2>📊 Run 235 Database Record</h2>";
        echo "<pre>";
        echo "ID:                    {$run->id}\n";
        echo "Source Company ID:     {$run->companyid}\n";
        echo "Target Company ID:     {$run->targetcompanyid}\n";
        echo "Status:                {$run->status}\n";
        echo "Reused From Run ID:    " . ($run->reusedfromrunid ? $run->reusedfromrunid : 'NULL') . "\n";
        echo "Time Created:          " . date('Y-m-d H:i:s', $run->timecreated) . "\n";
        echo "Time Modified:         " . date('Y-m-d H:i:s', $run->timemodified) . "\n";
        echo "</pre>";
        echo "</div>";

        // Check if cache was actually used
        if ($run->reusedfromrunid) {
            echo "<div class='box error'>";
            echo "<h2>❌ BUG DETECTED: Cache Used Despite 'Full Refresh'</h2>";
            echo "<p><strong>You selected 'Full Refresh' but reusedfromrunid = {$run->reusedfromrunid}</strong></p>";
            echo "<p>This means the system cached NBs from Run {$run->reusedfromrunid} instead of generating fresh ones.</p>";
            echo "<p><strong>Root Cause:</strong> The cache decision logic is not respecting the 'Full Refresh' selection.</p>";
            echo "</div>";
        } else {
            echo "<div class='box success'>";
            echo "<h2>✅ Cache Setting Correct</h2>";
            echo "<p>reusedfromrunid is NULL, which means 'Full Refresh' was properly set.</p>";
            echo "<p>If NBs are still cached, the issue is in the NB orchestrator logic, not the run creation.</p>";
            echo "</div>";
        }

        // Check NB results
        $nb_count = $DB->count_records('local_ci_nb_result', ['runid' => 235]);
        echo "<div class='box'>";
        echo "<h2>📦 NB Results</h2>";
        echo "<p>Total NB results stored: <strong>{$nb_count}</strong></p>";

        if ($nb_count > 0) {
            echo "<p>Checking if NBs were freshly generated or loaded from cache...</p>";
            $nbs = $DB->get_records('local_ci_nb_result', ['runid' => 235], 'nbcode ASC', 'nbcode, status, timecreated');
            echo "<table style='width:100%; border-collapse: collapse; margin-top:10px;'>";
            echo "<tr style='background:#f0f0f0;'>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>NB Code</th>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Status</th>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Created</th>";
            echo "</tr>";
            foreach ($nbs as $nb) {
                echo "<tr>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>{$nb->nbcode}</td>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>{$nb->status}</td>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>" . date('Y-m-d H:i:s', $nb->timecreated) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='box error'>";
    echo "<h2>💥 Error</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
