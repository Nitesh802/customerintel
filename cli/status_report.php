<?php
/**
 * Status Report CLI Tool for CustomerIntel Plugin
 * Displays operational summary including version, record counts, and health metrics
 * 
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_login();
global $DB, $CFG;

// Header
echo "========================================\n";
echo "Customer Intelligence Dashboard - Status Report\n";
echo "========================================\n\n";

// System Information
echo "SYSTEM INFORMATION\n";
echo "------------------\n";
echo "Moodle path: $CFG->dirroot\n";
echo "Site URL: $CFG->wwwroot\n";

// Plugin Version
$versionfile = __DIR__ . '/../version.php';
if (file_exists($versionfile)) {
    $plugin = new stdClass();
    include($versionfile);
    echo "Plugin version: " . ($plugin->version ?? 'unknown') . "\n";
    echo "Plugin release: " . ($plugin->release ?? 'not specified') . "\n";
} else {
    echo "Plugin version: unable to read version.php\n";
}
echo "\n";

// Database Statistics
echo "DATABASE STATISTICS\n";
echo "------------------\n";
$tables = [
    'local_ci_company' => 'Companies',
    'local_ci_source' => 'Sources',
    'local_ci_run' => 'Runs',
    'local_ci_nb_result' => 'NB Results',
    'local_ci_snapshot' => 'Snapshots',
    'local_ci_diff' => 'Diffs',
    'local_ci_comparison' => 'Comparisons',
    'local_ci_telemetry' => 'Telemetry'
];

$total_records = 0;
foreach ($tables as $table => $label) {
    if ($DB->get_manager()->table_exists($table)) {
        $count = $DB->count_records($table);
        $total_records += $count;
        echo sprintf("%-25s %6d records\n", $label . ':', $count);
    } else {
        echo sprintf("%-25s TABLE MISSING!\n", $label . ':');
    }
}
echo sprintf("%-25s %6d records\n", 'TOTAL:', $total_records);
echo "\n";

// Latest Run Information
echo "LATEST RUN INFORMATION\n";
echo "---------------------\n";
$latest = $DB->get_record_sql(
    "SELECT * FROM {local_ci_run} ORDER BY timecreated DESC",
    null,
    IGNORE_MULTIPLE
);

if ($latest) {
    echo "Run ID: " . $latest->id . "\n";
    echo "Status: " . $latest->status . "\n";
    echo "Company ID: " . $latest->companyid . "\n";
    echo "Created: " . date('Y-m-d H:i:s', $latest->timecreated) . "\n";
    
    if ($latest->timecompleted) {
        echo "Completed: " . date('Y-m-d H:i:s', $latest->timecompleted) . "\n";
        $duration = $latest->timecompleted - $latest->timecreated;
        echo "Duration: " . gmdate('H:i:s', $duration) . "\n";
    } else {
        echo "Completed: Not yet completed\n";
    }
    
    // Check for recent results
    $result_count = $DB->count_records('local_ci_nb_result', ['runid' => $latest->id]);
    echo "Results generated: $result_count\n";
} else {
    echo "No runs found in database.\n";
}
echo "\n";

// Telemetry Summary
echo "TELEMETRY SUMMARY\n";
echo "----------------\n";
$metrics = $DB->count_records('local_ci_telemetry');
echo "Total telemetry entries: $metrics\n";

if ($metrics > 0) {
    $recent = $DB->get_record_sql(
        "SELECT * FROM {local_ci_telemetry} ORDER BY timecreated DESC",
        null,
        IGNORE_MULTIPLE
    );
    
    if ($recent) {
        echo "Most recent entry: " . date('Y-m-d H:i:s', $recent->timecreated) . "\n";
        echo "Event type: " . $recent->event_type . "\n";
        
        // Cost tracking
        $total_cost = $DB->get_field_sql(
            "SELECT SUM(CAST(event_data AS DECIMAL(10,4))) 
             FROM {local_ci_telemetry} 
             WHERE event_type = 'cost'"
        );
        
        if ($total_cost) {
            echo "Total tracked costs: $" . number_format($total_cost, 4) . "\n";
        }
    }
} else {
    echo "No telemetry data collected yet.\n";
}
echo "\n";

// Capabilities Check
echo "CAPABILITIES CHECK\n";
echo "-----------------\n";
$capabilities = [
    'local/customerintel:view' => 'View dashboard',
    'local/customerintel:run' => 'Execute runs',
    'local/customerintel:manage' => 'Manage settings'
];

$accessfile = __DIR__ . '/../db/access.php';
if (file_exists($accessfile)) {
    echo "Access file exists: ✓\n";
    $capabilities_defined = [];
    include($accessfile);
    
    foreach ($capabilities as $cap => $desc) {
        if (isset($capabilities[$cap])) {
            echo "$cap: ✓ ($desc)\n";
        } else {
            echo "$cap: ✗ NOT DEFINED\n";
        }
    }
} else {
    echo "Access file missing: ✗\n";
    echo "Capabilities cannot be verified.\n";
}
echo "\n";

// Health Check Summary
echo "HEALTH CHECK SUMMARY\n";
echo "-------------------\n";
$issues = 0;

// Check for missing tables
foreach ($tables as $table => $label) {
    if (!$DB->get_manager()->table_exists($table)) {
        echo "✗ Missing table: $table\n";
        $issues++;
    }
}

// Check for stalled runs
$stalled = $DB->count_records_select('local_ci_run', 
    "status = 'running' AND timecreated < ?", 
    [time() - 3600]
);
if ($stalled > 0) {
    echo "✗ Found $stalled stalled runs (running > 1 hour)\n";
    $issues++;
}

// Check recent activity
$recent_runs = $DB->count_records_select('local_ci_run',
    'timecreated > ?',
    [time() - 86400 * 30]
);
if ($recent_runs == 0) {
    echo "⚠ No runs in the last 30 days\n";
}

if ($issues == 0) {
    echo "✓ All health checks passed\n";
} else {
    echo "✗ Found $issues issues requiring attention\n";
}

echo "\n========================================\n";
echo "Status report complete.\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

exit(0);