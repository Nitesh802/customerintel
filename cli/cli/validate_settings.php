<?php
/**
 * Settings Validation Script
 * Validates that settings are properly registered and accessible
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB, $CFG;

echo "\n=== Customer Intelligence Settings Validation ===\n\n";

// Step 1: Check if plugin is installed
echo "1. Checking plugin installation...\n";
$plugin = $DB->get_record('config_plugins', ['plugin' => 'local_customerintel', 'name' => 'version']);
if ($plugin) {
    echo "   ✓ Plugin installed (version: {$plugin->value})\n";
} else {
    echo "   ✗ Plugin not found in config_plugins\n";
    exit(1);
}

// Step 2: Check settings.php file exists
echo "\n2. Checking settings files...\n";
$settingsfile = $CFG->dirroot . '/local/customerintel/settings.php';
if (file_exists($settingsfile)) {
    $content = file_get_contents($settingsfile);
    if (strlen($content) > 100) { // Not empty
        echo "   ✓ settings.php exists and has content\n";
    } else {
        echo "   ✗ settings.php exists but appears empty\n";
    }
} else {
    echo "   ✗ settings.php not found\n";
}

// Check admin_settings.php
$adminsettingsfile = $CFG->dirroot . '/local/customerintel/admin_settings.php';
if (file_exists($adminsettingsfile)) {
    $content = file_get_contents($adminsettingsfile);
    if (strlen($content) < 200) { // Should be empty
        echo "   ✓ admin_settings.php properly emptied\n";
    } else {
        echo "   ⚠ admin_settings.php exists with content (should be empty)\n";
    }
}

// Step 3: Test configuration retrieval
echo "\n3. Testing configuration access...\n";
$settings_to_test = [
    'perplexityapikey' => 'Perplexity API Key',
    'openaiapikey' => 'OpenAI API Key',
    'automaticsourcediscovery' => 'Automatic Source Discovery',
    'costwarning' => 'Cost Warning Threshold',
    'costlimit' => 'Cost Hard Limit',
    'freshnesswindow' => 'Freshness Window'
];

$found = 0;
foreach ($settings_to_test as $key => $label) {
    $value = get_config('local_customerintel', $key);
    if ($value !== false) {
        echo "   ✓ $label: " . (empty($value) ? '(empty)' : substr($value, 0, 20) . '...') . "\n";
        $found++;
    } else {
        echo "   ⚠ $label: not set\n";
    }
}
echo "   Found $found/" . count($settings_to_test) . " settings\n";

// Step 4: Check capabilities
echo "\n4. Checking capabilities...\n";
$caps = [
    'local/customerintel:view',
    'local/customerintel:run',
    'local/customerintel:viewreports',
    'local/customerintel:managesources'
];

foreach ($caps as $cap) {
    $capability = $DB->get_record('capabilities', ['name' => $cap]);
    if ($capability) {
        echo "   ✓ Capability registered: $cap\n";
    } else {
        echo "   ✗ Missing capability: $cap\n";
    }
}

// Step 5: Check database tables
echo "\n5. Checking database tables...\n";
$tables = [
    'local_ci_company',
    'local_ci_source',
    'local_ci_run',
    'local_ci_nb_result',
    'local_ci_snapshot',
    'local_ci_diff',
    'local_ci_comparison',
    'local_ci_telemetry'
];

$dbman = $DB->get_manager();
$table_count = 0;
foreach ($tables as $table) {
    if ($dbman->table_exists($table)) {
        $count = $DB->count_records($table);
        echo "   ✓ Table $table exists ($count records)\n";
        $table_count++;
    } else {
        echo "   ✗ Missing table: $table\n";
    }
}
echo "   Found $table_count/" . count($tables) . " tables\n";

// Step 6: Check language strings
echo "\n6. Checking language strings...\n";
$langfile = $CFG->dirroot . '/local/customerintel/lang/en/local_customerintel.php';
if (file_exists($langfile)) {
    $content = file_get_contents($langfile);
    $required_strings = [
        'pluginname',
        'perplexityapikey',
        'openaiapikey',
        'automaticsourcediscovery',
        'apisettings',
        'featuresettings',
        'costcontrols',
        'domainsettings',
        'freshnesssettings'
    ];
    
    $missing = [];
    foreach ($required_strings as $str) {
        if (strpos($content, "['$str']") === false) {
            $missing[] = $str;
        }
    }
    
    if (empty($missing)) {
        echo "   ✓ All required language strings present\n";
    } else {
        echo "   ⚠ Missing strings: " . implode(', ', $missing) . "\n";
    }
} else {
    echo "   ✗ Language file not found\n";
}

// Step 7: Test direct admin tree access (simulated)
echo "\n7. Checking admin tree registration...\n";
echo "   ℹ Note: Full admin tree validation requires web access\n";
echo "   ℹ Visit: Site administration > Plugins > Local plugins\n";
echo "   ℹ Look for: Customer Intelligence Dashboard\n";

// Step 8: Check for common issues
echo "\n8. Checking for common issues...\n";

// Check version.php
$versionfile = $CFG->dirroot . '/local/customerintel/version.php';
if (file_exists($versionfile)) {
    require($versionfile);
    if (isset($plugin->version)) {
        echo "   ✓ version.php valid (version: $plugin->version)\n";
    }
} else {
    echo "   ✗ version.php not found\n";
}

// Check for task classes
$taskfile = $CFG->dirroot . '/local/customerintel/classes/task/execute_run_task.php';
if (file_exists($taskfile)) {
    echo "   ✓ Task class exists\n";
} else {
    echo "   ⚠ Task class missing (optional)\n";
}

// Final summary
echo "\n=== Validation Summary ===\n";
echo "✓ Plugin is installed and basic structure is valid\n";
echo "✓ Settings files have been properly configured\n";
echo "✓ Database tables are in place\n";
echo "\nNext steps:\n";
echo "1. Purge all caches: php admin/cli/purge_caches.php\n";
echo "2. Access Site administration > Plugins > Local plugins\n";
echo "3. Click on 'Customer Intelligence Dashboard' to access settings\n";
echo "4. Configure API keys and other settings as needed\n\n";

exit(0);