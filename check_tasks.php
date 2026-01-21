<?php
// Check adhoc task status for CustomerIntel
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB;

echo "<!DOCTYPE html><html><head><title>Task Status</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #4CAF50; color: white; }
tr:nth-child(even) { background-color: #f2f2f2; }
.pending { color: orange; font-weight: bold; }
.running { color: blue; font-weight: bold; }
.failed { color: red; font-weight: bold; }
.success { color: green; font-weight: bold; }
</style>";
echo "</head><body>";

echo "<h1>CustomerIntel - Adhoc Task Status</h1>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

try {
    // Get adhoc tasks for CustomerIntel
    $tasks = $DB->get_records_sql("
        SELECT id, customdata, faildelay, timestarted, timecreated, nextruntime
        FROM {task_adhoc}
        WHERE classname = ?
        ORDER BY id DESC
        LIMIT 10
    ", ['\\local_customerintel\\task\\execute_run_task']);
    
    if (empty($tasks)) {
        echo "<p style='color: orange;'>⚠️ <strong>No adhoc tasks found for CustomerIntel</strong></p>";
        echo "<p>This could mean:</p>";
        echo "<ul>";
        echo "<li>No runs have been queued recently</li>";
        echo "<li>All tasks have been completed and cleaned up</li>";
        echo "<li>Task queuing is not working</li>";
        echo "</ul>";
    } else {
        echo "<p>✅ Found <strong>" . count($tasks) . "</strong> adhoc task(s)</p>";
        
        echo "<table>";
        echo "<tr>";
        echo "<th>Task ID</th>";
        echo "<th>Run ID</th>";
        echo "<th>Status</th>";
        echo "<th>Created</th>";
        echo "<th>Started</th>";
        echo "<th>Next Run</th>";
        echo "<th>Fail Delay</th>";
        echo "</tr>";
        
        foreach ($tasks as $task) {
            $customdata = json_decode($task->customdata);
            $runid = isset($customdata->runid) ? $customdata->runid : 'N/A';
            
            // Determine status
            if ($task->timestarted > 0) {
                $status = "<span class='running'>RUNNING</span>";
            } elseif ($task->faildelay > 0) {
                $status = "<span class='failed'>FAILED (Retrying)</span>";
            } else {
                $status = "<span class='pending'>PENDING</span>";
            }
            
            echo "<tr>";
            echo "<td>" . $task->id . "</td>";
            echo "<td><strong>Run " . $runid . "</strong></td>";
            echo "<td>" . $status . "</td>";
            echo "<td>" . date('Y-m-d H:i:s', $task->timecreated) . "</td>";
            echo "<td>" . ($task->timestarted > 0 ? date('Y-m-d H:i:s', $task->timestarted) : 'Not started') . "</td>";
            echo "<td>" . ($task->nextruntime > 0 ? date('Y-m-d H:i:s', $task->nextruntime) : 'N/A') . "</td>";
            echo "<td>" . $task->faildelay . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Also check recent runs
    echo "<hr>";
    echo "<h2>Recent Runs (Last 10)</h2>";
    
    $runs = $DB->get_records_sql("
        SELECT id, company, status, timecreated, timemodified, timecompleted
        FROM {local_ci_run}
        ORDER BY id DESC
        LIMIT 10
    ");
    
    if (!empty($runs)) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Run ID</th>";
        echo "<th>Company</th>";
        echo "<th>Status</th>";
        echo "<th>Created</th>";
        echo "<th>Modified</th>";
        echo "<th>Completed</th>";
        echo "</tr>";
        
        foreach ($runs as $run) {
            $statusClass = '';
            if ($run->status == 'completed') $statusClass = 'success';
            elseif ($run->status == 'pending') $statusClass = 'pending';
            elseif ($run->status == 'running') $statusClass = 'running';
            elseif ($run->status == 'failed') $statusClass = 'failed';
            
            echo "<tr>";
            echo "<td><strong>" . $run->id . "</strong></td>";
            echo "<td>" . $run->company . "</td>";
            echo "<td><span class='" . $statusClass . "'>" . strtoupper($run->status) . "</span></td>";
            echo "<td>" . date('Y-m-d H:i:s', $run->timecreated) . "</td>";
            echo "<td>" . date('Y-m-d H:i:s', $run->timemodified) . "</td>";
            echo "<td>" . ($run->timecompleted > 0 ? date('Y-m-d H:i:s', $run->timecompleted) : 'Not completed') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ <strong>ERROR:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a> | ";
echo "<a href='check_tasks.php'>🔄 Refresh</a></p>";
echo "</body></html>";
?>