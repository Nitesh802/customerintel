<?php
/**
 * Quick Run Status Checker
 * Usage: https://sales.multi.rubi.digital/local/customerintel/check_run_status.php?runid=233
 */

require_once('../../config.php');
require_login();

global $DB;

$runid = optional_param('runid', 0, PARAM_INT);

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Run Status Checker</title>";
echo "<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
.status-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4CAF50; }
.error { border-left-color: #f44336; }
.warning { border-left-color: #ff9800; }
.success { border-left-color: #4CAF50; }
pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
h2 { margin-top: 0; }
</style></head><body>";

if ($runid <= 0) {
    echo "<div class='status-box error'>";
    echo "<h2>❌ No Run ID Specified</h2>";
    echo "<p>Usage: check_run_status.php?runid=233</p>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

echo "<h1>🔍 Run Status Checker - Run #{$runid}</h1>";
echo "<p><strong>Checked at:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

try {
    // Get run record
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);

    if (!$run) {
        echo "<div class='status-box error'>";
        echo "<h2>❌ Run Not Found</h2>";
        echo "<p>Run #{$runid} does not exist in the database.</p>";
        echo "</div>";
    } else {
        // Display run status
        $status_class = 'status-box ';
        if ($run->status === 'completed') {
            $status_class .= 'success';
        } elseif ($run->status === 'failed') {
            $status_class .= 'error';
        } else {
            $status_class .= 'warning';
        }

        echo "<div class='{$status_class}'>";
        echo "<h2>📊 Run #{$runid} - Status: " . strtoupper($run->status) . "</h2>";
        echo "<pre>";
        echo "Run ID:           {$run->id}\n";
        echo "Status:           {$run->status}\n";
        echo "Source Company:   {$run->sourcecompanyid}\n";
        echo "Target Company:   {$run->targetcompanyid}\n";
        echo "Time Created:     " . ($run->timecreated ? date('Y-m-d H:i:s', $run->timecreated) : 'N/A') . "\n";
        echo "Time Completed:   " . ($run->timecompleted ? date('Y-m-d H:i:s', $run->timecompleted) : 'N/A') . "\n";
        echo "Time Modified:    " . ($run->timemodified ? date('Y-m-d H:i:s', $run->timemodified) : 'N/A') . "\n";
        if (!empty($run->error)) {
            echo "\n🚨 ERROR MESSAGE:\n";
            echo $run->error . "\n";
        }
        echo "</pre>";
        echo "</div>";

        // Check for artifacts
        $artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid]);

        echo "<div class='status-box'>";
        echo "<h2>📦 Artifacts (" . count($artifacts) . " found)</h2>";

        if (empty($artifacts)) {
            echo "<p>⚠️ No artifacts found for this run.</p>";
        } else {
            echo "<table style='width:100%; border-collapse: collapse;'>";
            echo "<tr style='background:#f0f0f0;'>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Artifact Name</th>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Size (bytes)</th>";
            echo "<th style='padding:8px; text-align:left; border:1px solid #ddd;'>Created</th>";
            echo "</tr>";

            foreach ($artifacts as $artifact) {
                $size = strlen($artifact->artifact_data ?? '');
                $created = date('Y-m-d H:i:s', $artifact->timecreated);
                echo "<tr>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>{$artifact->artifact_name}</td>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>" . number_format($size) . "</td>";
                echo "<td style='padding:8px; border:1px solid #ddd;'>{$created}</td>";
                echo "</tr>";
            }
            echo "</table>";

            // Check specifically for synthesis_final_bundle
            $has_report = false;
            foreach ($artifacts as $artifact) {
                if ($artifact->artifact_name === 'synthesis_final_bundle') {
                    $has_report = true;
                    break;
                }
            }

            if ($has_report) {
                echo "<div style='margin-top:15px; padding:10px; background:#e8f5e9; border-radius:4px;'>";
                echo "✅ <strong>Report exists!</strong> (synthesis_final_bundle found)";
                echo "</div>";

                if ($run->status !== 'completed') {
                    echo "<div style='margin-top:15px; padding:10px; background:#fff3cd; border-radius:4px;'>";
                    echo "⚠️ <strong>Status Mismatch:</strong> Report exists but status is '{$run->status}'<br>";
                    echo "The report may be viewable even though status shows as failed.";
                    echo "</div>";
                }
            }
        }
        echo "</div>";

        // Check adhoc task
        $task_sql = "SELECT * FROM {task_adhoc}
                     WHERE classname = '\\\\local_customerintel\\\\task\\\\execute_run_task'
                     AND customdata LIKE ?
                     ORDER BY id DESC LIMIT 1";
        $task_param = '%"runid":' . $runid . '%';
        $task = $DB->get_record_sql($task_sql, [$task_param]);

        echo "<div class='status-box'>";
        echo "<h2>⚙️ Adhoc Task Status</h2>";

        if (!$task) {
            echo "<p>ℹ️ No adhoc task found (may have been cleaned up after completion)</p>";
        } else {
            echo "<pre>";
            echo "Task ID:          {$task->id}\n";
            echo "Fail Delay:       {$task->faildelay}\n";
            echo "Time Created:     " . date('Y-m-d H:i:s', $task->timecreated) . "\n";
            echo "Time Started:     " . ($task->timestarted ? date('Y-m-d H:i:s', $task->timestarted) : 'Not started') . "\n";
            echo "Next Run Time:    " . ($task->nextruntime ? date('Y-m-d H:i:s', $task->nextruntime) : 'N/A') . "\n";
            echo "</pre>";
        }
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='status-box error'>";
    echo "<h2>💥 Error</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
