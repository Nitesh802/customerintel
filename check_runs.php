<?php
/**
 * Quick database check for runs
 */

require_once('../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

echo "<html><head><title>Run Status Check</title>";
echo "<style>
body { font-family: monospace; padding: 20px; }
table { border-collapse: collapse; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #007bff; color: white; }
.test-run { background-color: #fff3cd; }
.production-run { background-color: #d4edda; }
</style></head><body>";

echo "<h2>Run Status Check</h2>";

// Get all runs from 122 onwards
try {
    $runs = $DB->get_records_sql("
        SELECT *
        FROM {local_ci_run}
        WHERE id >= 122
        ORDER BY id ASC
    ");
} catch (Exception $e) {
    echo "<div style='background:#f8d7da; padding:15px; border-left:4px solid #dc3545; margin:20px 0;'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>This might be because:</p>";
    echo "<ul>";
    echo "<li>The local_ci_run table doesn't exist</li>";
    echo "<li>Database connection issue</li>";
    echo "<li>Insufficient permissions</li>";
    echo "</ul>";
    echo "</div>";
    echo "</body></html>";
    die();
}

echo "<h3>Runs from 122 onwards:</h3>";
echo "<table>";
echo "<tr><th>ID</th><th>Status</th><th>Company</th><th>Target</th><th>Cache Strategy</th><th>Refresh Config</th><th>Created</th><th>Completed</th><th>Type</th></tr>";

foreach ($runs as $run) {
    // Determine if it's a test run (status=pending, never completed, created recently)
    $timecompleted = isset($run->timecompleted) ? $run->timecompleted : null;
    $is_test = ($run->status === 'pending' && empty($timecompleted) && (time() - $run->timecreated) < 3600);
    $row_class = $is_test ? 'test-run' : 'production-run';

    echo "<tr class='{$row_class}'>";
    echo "<td>{$run->id}</td>";
    echo "<td>{$run->status}</td>";
    echo "<td>{$run->companyid}</td>";
    echo "<td>{$run->targetcompanyid}</td>";
    echo "<td>" . (isset($run->cache_strategy) && $run->cache_strategy ? $run->cache_strategy : 'null') . "</td>";
    echo "<td>" . (isset($run->refresh_config) && $run->refresh_config ? substr($run->refresh_config, 0, 30) . '...' : 'null') . "</td>";
    echo "<td>" . date('Y-m-d H:i:s', $run->timecreated) . "</td>";
    echo "<td>" . ($timecompleted ? date('Y-m-d H:i:s', $timecompleted) : 'null') . "</td>";
    echo "<td>" . ($is_test ? 'TEST (orphaned?)' : 'Production') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Summary:</h3>";
echo "<ul>";
echo "<li><span style='background:#d4edda; padding:2px 8px;'>Green</span> = Production runs (completed or in progress)</li>";
echo "<li><span style='background:#fff3cd; padding:2px 8px;'>Yellow</span> = Test runs (pending, created recently, likely orphaned from failed cleanup)</li>";
echo "</ul>";

// Count orphaned test runs
try {
    $orphaned = $DB->count_records_sql("
        SELECT COUNT(*)
        FROM {local_ci_run}
        WHERE status = 'pending'
        AND timecreated > ?
        AND id > 122
    ", [time() - 3600]);
} catch (Exception $e) {
    $orphaned = 0;
}

if ($orphaned > 0) {
    echo "<h3>⚠️ Found {$orphaned} orphaned test run(s)</h3>";
    echo "<p>These are likely from test_m1t4.php runs where cleanup failed.</p>";
    echo "<p><strong>Safe to delete?</strong> Only if they're recent (< 1 hour old) and status=pending</p>";

    echo "<h4>Cleanup Options:</h4>";
    echo "<p><a href='cleanup_test_runs.php' style='padding:8px 16px; background:#dc3545; color:white; text-decoration:none; border-radius:4px;'>🗑️ Delete Orphaned Test Runs</a></p>";
}

echo "</body></html>";
