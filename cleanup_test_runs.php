<?php
/**
 * Cleanup orphaned test runs
 */

require_once('../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

$confirm = optional_param('confirm', 0, PARAM_INT);

echo "<html><head><title>Cleanup Test Runs</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
.success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
.btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-danger { background-color: #dc3545; color: white; }
.btn-secondary { background-color: #6c757d; color: white; }
table { border-collapse: collapse; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #007bff; color: white; }
</style></head><body>";

echo "<h2>🗑️ Cleanup Orphaned Test Runs</h2>";

if (!$confirm) {
    // Show what will be deleted
    $orphaned_runs = $DB->get_records_sql("
        SELECT id, companyid, targetcompanyid, status, timecreated, refresh_config
        FROM {local_ci_run}
        WHERE status = 'pending'
        AND timecompleted IS NULL
        AND timecreated > ?
        AND id > 122
        ORDER BY id ASC
    ", [time() - 3600]);

    if (empty($orphaned_runs)) {
        echo "<div class='success'>";
        echo "<p>✅ No orphaned test runs found!</p>";
        echo "<p>All test runs have been cleaned up properly.</p>";
        echo "</div>";
        echo "<p><a href='check_runs.php' class='btn btn-secondary'>← Back to Run Status</a></p>";
    } else {
        echo "<div class='warning'>";
        echo "<p>⚠️ Found " . count($orphaned_runs) . " orphaned test run(s) that will be deleted:</p>";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>Run ID</th><th>Status</th><th>Company</th><th>Target</th><th>Created</th><th>Refresh Config</th></tr>";
        foreach ($orphaned_runs as $run) {
            echo "<tr>";
            echo "<td>#{$run->id}</td>";
            echo "<td>{$run->status}</td>";
            echo "<td>{$run->companyid}</td>";
            echo "<td>{$run->targetcompanyid}</td>";
            echo "<td>" . date('Y-m-d H:i:s', $run->timecreated) . "</td>";
            echo "<td>" . ($run->refresh_config ? 'Has config' : 'null') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<p><strong>These runs will be permanently deleted from the database.</strong></p>";
        echo "<p><a href='?confirm=1' class='btn btn-danger'>🗑️ Confirm Delete</a> ";
        echo "<a href='check_runs.php' class='btn btn-secondary'>Cancel</a></p>";
    }

} else {
    // Perform deletion
    $deleted_count = $DB->execute("
        DELETE FROM {local_ci_run}
        WHERE status = 'pending'
        AND timecompleted IS NULL
        AND timecreated > ?
        AND id > 122
    ", [time() - 3600]);

    echo "<div class='success'>";
    echo "<p>✅ Successfully deleted orphaned test runs!</p>";
    echo "<p>Deleted runs: Check database for confirmation</p>";
    echo "</div>";

    echo "<p><a href='check_runs.php' class='btn btn-secondary'>← Back to Run Status</a></p>";
}

echo "</body></html>";
