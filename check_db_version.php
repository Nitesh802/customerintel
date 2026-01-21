<?php
/**
 * Database Version Check Script
 * 
 * Checks the current version stored in mdl_config_plugins
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "Database Version Check for CustomerIntel Plugin\n";
echo "=============================================\n\n";

// Check current version in database
$db_version = $DB->get_field('config_plugins', 'value', [
    'plugin' => 'local_customerintel',
    'name' => 'version'
]);

if ($db_version) {
    echo "📀 Database Version: {$db_version}\n";
} else {
    echo "❌ No version found in database for local_customerintel\n";
}

// Get file version
require(__DIR__ . '/version.php');
$file_version = $plugin->version;
echo "📄 File Version: {$file_version}\n\n";

// Compare versions
if ($db_version && $file_version) {
    if ($file_version > $db_version) {
        echo "🔄 UPGRADE NEEDED\n";
        echo "   Database: {$db_version}\n";
        echo "   File:     {$file_version}\n";
        echo "   Difference: " . ($file_version - $db_version) . "\n";
    } elseif ($file_version == $db_version) {
        echo "✅ VERSIONS MATCH - No upgrade needed\n";
    } else {
        echo "⚠️  File version is OLDER than database version\n";
    }
}

echo "\n";

// Check if table exists
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists(new xmldb_table('local_ci_artifact'));
echo "🗃️  Table local_ci_artifact exists: " . ($table_exists ? "YES" : "NO") . "\n";

// Show all CustomerIntel tables
echo "\n📊 All CustomerIntel tables:\n";
$all_tables = $DB->get_tables();
foreach ($all_tables as $table_name) {
    if (strpos($table_name, 'local_ci_') === 0) {
        $count = $DB->count_records($table_name);
        echo "   • {$table_name} ({$count} records)\n";
    }
}

echo "\n";
?>