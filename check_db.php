<?php
/**
 * Database Check Script
 * 
 * Simple script to check database table and configuration
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "Customer Intelligence Database Check\n";
echo "===================================\n\n";

// Check database manager
$dbman = $DB->get_manager();

// Check table existence
$table = new xmldb_table('local_ci_artifact');
if ($dbman->table_exists($table)) {
    echo "✅ Table 'local_ci_artifact' exists\n";
    
    // Check columns
    $columns = $DB->get_columns('local_ci_artifact');
    echo "📋 Columns: " . implode(', ', array_keys($columns)) . "\n";
    
    // Check record count
    $count = $DB->count_records('local_ci_artifact');
    echo "📊 Record count: {$count}\n";
    
    // Show recent records if any
    if ($count > 0) {
        echo "\n📦 Recent artifacts:\n";
        $recent = $DB->get_records('local_ci_artifact', null, 'timecreated DESC', 'id, runid, phase, artifacttype, timecreated', 0, 5);
        foreach ($recent as $record) {
            echo "   • ID {$record->id}: Run {$record->runid}, {$record->phase}/{$record->artifacttype} at " . userdate($record->timecreated) . "\n";
        }
    }
    
} else {
    echo "❌ Table 'local_ci_artifact' does NOT exist\n";
    echo "💡 Run database upgrade by visiting /admin/index.php\n";
}

echo "\n";

// Check configuration
$trace_mode = get_config('local_customerintel', 'enable_trace_mode');
echo "🔍 Trace Mode Setting: " . ($trace_mode ?: 'not set') . "\n";

if ($trace_mode === '1') {
    echo "✅ Trace mode is ENABLED\n";
} else {
    echo "⚠️  Trace mode is DISABLED\n";
    echo "💡 Enable at /admin/settings.php?section=local_customerintel_settings\n";
}

echo "\n";

// Check recent runs
$recent_runs = $DB->get_records('local_ci_run', ['status' => 'completed'], 'timecompleted DESC', '*', 0, 3);
echo "📈 Recent completed runs:\n";
if (!empty($recent_runs)) {
    foreach ($recent_runs as $run) {
        $company = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
        $artifact_count = $DB->count_records('local_ci_artifact', ['runid' => $run->id]);
        echo "   • Run {$run->id}: " . ($company ? $company->name : 'Unknown') . " ({$artifact_count} artifacts)\n";
    }
} else {
    echo "   No completed runs found\n";
}

echo "\n✅ Check complete\n";
?>