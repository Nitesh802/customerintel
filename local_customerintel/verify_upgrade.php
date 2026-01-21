<?php
/**
 * Upgrade Verification Script
 * 
 * Verifies that the database upgrade was successful
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "CustomerIntel Database Upgrade Verification\n";
echo "==========================================\n\n";

// 1. Check version numbers
$db_version = $DB->get_field('config_plugins', 'value', [
    'plugin' => 'local_customerintel',
    'name' => 'version'
]);

require(__DIR__ . '/version.php');
$file_version = $plugin->version;

echo "📄 File Version: {$file_version}\n";
echo "📀 Database Version: " . ($db_version ?: 'NOT FOUND') . "\n";

if ($db_version == $file_version) {
    echo "✅ Versions match - upgrade completed\n";
} elseif ($db_version < $file_version) {
    echo "⚠️  Database version is behind - upgrade needed\n";
    echo "   👉 Visit /admin/index.php to run upgrade\n";
} else {
    echo "❓ Database version is ahead of file version\n";
}

echo "\n";

// 2. Check table existence
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists(new xmldb_table('local_ci_artifact'));

echo "🗃️  Table 'local_ci_artifact' exists: " . ($table_exists ? "✅ YES" : "❌ NO") . "\n";

if ($table_exists) {
    // Check table structure
    $columns = $DB->get_columns('local_ci_artifact');
    $expected_columns = ['id', 'runid', 'phase', 'artifacttype', 'jsondata', 'timecreated', 'timemodified'];
    $actual_columns = array_keys($columns);
    $missing_columns = array_diff($expected_columns, $actual_columns);
    
    if (empty($missing_columns)) {
        echo "✅ All expected columns present: " . implode(', ', $actual_columns) . "\n";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missing_columns) . "\n";
    }
    
    // Check record count
    $record_count = $DB->count_records('local_ci_artifact');
    echo "📊 Current record count: {$record_count}\n";
    
} else {
    echo "❌ Table creation failed or was not attempted\n";
    echo "🔧 You can manually create it by running:\n";
    echo "   /local/customerintel/db/create_artifact_table.php\n";
}

echo "\n";

// 3. Check configuration
$trace_mode = get_config('local_customerintel', 'enable_trace_mode');
echo "🔍 Trace Mode Setting: " . ($trace_mode !== false ? $trace_mode : 'NOT SET') . "\n";

if ($trace_mode === false) {
    echo "⚠️  Configuration not initialized\n";
} elseif ($trace_mode === '1') {
    echo "✅ Trace mode is ENABLED\n";
} else {
    echo "💡 Trace mode is DISABLED (default)\n";
}

echo "\n";

// 4. Summary
echo "📋 SUMMARY:\n";
echo "----------\n";

if ($table_exists && $db_version == $file_version && $trace_mode !== false) {
    echo "🎉 SUCCESS: Database upgrade completed successfully!\n\n";
    echo "Next steps:\n";
    echo "1. Enable trace mode in Customer Intelligence settings if desired\n";
    echo "2. Run an intelligence report to test artifact collection\n";
    echo "3. View the Data Trace tab in reports\n";
} else {
    echo "⚠️  Issues detected:\n";
    if (!$table_exists) {
        echo "   • Table 'local_ci_artifact' missing\n";
    }
    if ($db_version != $file_version) {
        echo "   • Version mismatch (DB: {$db_version}, File: {$file_version})\n";
    }
    if ($trace_mode === false) {
        echo "   • Configuration not initialized\n";
    }
    echo "\n";
    echo "🔧 Solutions:\n";
    echo "   • Visit /admin/index.php to run upgrade\n";
    echo "   • Or manually create table: /local/customerintel/db/create_artifact_table.php\n";
}
?>