<?php
/**
 * Simple run executor for testing
 */

require_once('../../config.php');
require_once(__DIR__ . '/classes/task/execute_run_task.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

$runid = optional_param('runid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo "<html><head><title>Execute Run</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-primary { background-color: #007bff; color: white; }
.btn-danger { background-color: #dc3545; color: white; }
.btn-secondary { background-color: #6c757d; color: white; }
.section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
</style></head><body>";

echo "<h2>🚀 Execute Run</h2>";

if (!$runid) {
    // Show run selection
    $recent_runs = $DB->get_records('local_ci_run', null, 'id DESC', '*', 0, 10);

    echo "<div class='section'>";
    echo "<h3>Select a run to execute:</h3>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Run ID</th><th>Status</th><th>Company</th><th>Target</th><th>Action</th></tr>";
    foreach ($recent_runs as $run) {
        echo "<tr>";
        echo "<td>#{$run->id}</td>";
        echo "<td>{$run->status}</td>";
        echo "<td>{$run->companyid}</td>";
        echo "<td>{$run->targetcompanyid}</td>";
        echo "<td><a href='?runid={$run->id}' class='btn btn-primary'>Execute</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

} elseif (!$confirm) {
    // Show confirmation
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);

    if (!$run) {
        echo "<p style='color:red;'>Run not found!</p>";
        echo "<p><a href='?' class='btn btn-secondary'>← Back</a></p>";
    } else {
        echo "<div class='section'>";
        echo "<h3>⚠️ Confirm Run Execution</h3>";
        echo "<p><strong>Run ID:</strong> {$run->id}</p>";
        echo "<p><strong>Company:</strong> {$run->companyid}</p>";
        echo "<p><strong>Target:</strong> {$run->targetcompanyid}</p>";
        echo "<p><strong>Current Status:</strong> {$run->status}</p>";

        $config = $run->refresh_config ? json_decode($run->refresh_config, true) : null;
        echo "<p><strong>Refresh Config:</strong></p>";
        if ($config) {
            echo "<pre>" . json_encode($config, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<p><em>null (default behavior)</em></p>";
        }

        echo "<p><strong>This will:</strong></p>";
        echo "<ol>";
        echo "<li>Reset the run status to 'pending'</li>";
        echo "<li>Execute the run via the background task</li>";
        echo "<li>Generate NBs and synthesis according to refresh_config</li>";
        echo "</ol>";

        echo "<p><a href='?runid={$runid}&confirm=1' class='btn btn-danger'>✅ Confirm & Execute</a> ";
        echo "<a href='?' class='btn btn-secondary'>Cancel</a></p>";
        echo "</div>";
    }

} else {
    // Execute the run
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);

    if (!$run) {
        echo "<p style='color:red;'>Run not found!</p>";
        echo "<p><a href='?' class='btn btn-secondary'>← Back</a></p>";
    } else {
        echo "<div class='section'>";
        echo "<h3>🚀 Executing Run {$runid}</h3>";

        try {
            // Reset run status
            $DB->set_field('local_ci_run', 'status', 'pending', ['id' => $runid]);
            $DB->set_field('local_ci_run', 'timestarted', null, ['id' => $runid]);

            // Create and execute the task
            $task = new \local_customerintel\task\execute_run_task();
            $task->set_custom_data(['runid' => $runid]);

            echo "<p>⏳ Executing run (this may take a few minutes)...</p>";
            echo "<pre>";

            // Execute synchronously so we can see output
            ob_start();
            $task->execute();
            $output = ob_get_clean();

            echo htmlspecialchars($output);
            echo "</pre>";

            // Reload run to check status
            $run = $DB->get_record('local_ci_run', ['id' => $runid]);

            echo "<p><strong>✅ Execution completed!</strong></p>";
            echo "<p><strong>Final Status:</strong> {$run->status}</p>";
            echo "<p><a href='test_m1t4_production.php?runid={$runid}' class='btn btn-primary'>View Diagnostics & Telemetry</a></p>";
            echo "<p><a href='?' class='btn btn-secondary'>← Back to Run List</a></p>";

        } catch (Exception $e) {
            echo "<p style='color:red;'><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><a href='?' class='btn btn-secondary'>← Back</a></p>";
        }

        echo "</div>";
    }
}

echo "</body></html>";
