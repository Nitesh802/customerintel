<?php
/**
 * Version Check Script
 * 
 * Checks current installed version vs. file version to see if upgrade is needed
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "Customer Intelligence Version Check\n";
echo "==================================\n\n";

// Get version from version.php file
require(__DIR__ . '/version.php');
$file_version = $plugin->version;
$file_release = $plugin->release;

echo "📄 File Version: {$file_version} ({$file_release})\n";

// Get installed version from database
$installed_version = $DB->get_field('config_plugins', 'value', [
    'plugin' => 'local_customerintel',
    'name' => 'version'
]);

if ($installed_version) {
    echo "💾 Installed Version: {$installed_version}\n";
    
    if ($file_version > $installed_version) {
        echo "🔄 UPGRADE NEEDED: File version is newer than installed version\n";
        echo "💡 Visit /admin/index.php to trigger the upgrade\n";
        echo "📝 This will create the local_ci_artifact table and enable transparent pipeline tracing\n";
    } elseif ($file_version == $installed_version) {
        echo "✅ UP TO DATE: File and installed versions match\n";
    } else {
        echo "⚠️  WARNING: Installed version is newer than file version\n";
    }
} else {
    echo "❌ Plugin not installed or version not found in database\n";
}

echo "\n";

// Check if artifact table exists
$dbman = $DB->get_manager();
$table = new xmldb_table('local_ci_artifact');

if ($dbman->table_exists($table)) {
    echo "✅ Artifact table exists\n";
    $count = $DB->count_records('local_ci_artifact');
    echo "📊 Current artifacts: {$count}\n";
} else {
    echo "❌ Artifact table does NOT exist\n";
    echo "🔄 Database upgrade needed\n";
}

echo "\n";

// Check trace mode setting
$trace_mode = get_config('local_customerintel', 'enable_trace_mode');
echo "🔍 Trace Mode: " . ($trace_mode ?: 'not configured') . "\n";

if ($trace_mode === false) {
    echo "⚠️  Trace mode setting not found - upgrade needed\n";
} elseif ($trace_mode === '1') {
    echo "✅ Trace mode is ENABLED\n";
} else {
    echo "⚠️  Trace mode is DISABLED\n";
}

echo "\n📋 Summary:\n";
echo "----------\n";

if ($file_version > $installed_version) {
    echo "🚨 ACTION REQUIRED:\n";
    echo "1. Visit /admin/index.php\n";
    echo "2. Run the database upgrade\n";
    echo "3. Enable trace mode in settings if desired\n";
} else {
    echo "✅ System is ready for transparent pipeline tracing\n";
    echo "💡 Enable trace mode in Customer Intelligence settings to start collecting artifacts\n";
}
?>