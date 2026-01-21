<?php
/**
 * Cache Rebuild CLI Tool for CustomerIntel Plugin
 * Purges caches, re-validates schema, and re-registers capabilities
 * 
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $CFG;

echo "========================================\n";
echo "CustomerIntel Cache & Capability Rebuild\n";
echo "========================================\n\n";

// Step 1: Purge All Caches
echo "[1/5] Purging Moodle caches...\n";
purge_all_caches();
echo "✓ All caches purged successfully\n\n";

// Step 2: Clear Plugin-Specific Caches
echo "[2/5] Clearing plugin-specific caches...\n";

// Clear any session data related to the plugin
if (isset($_SESSION['customerintel_cache'])) {
    unset($_SESSION['customerintel_cache']);
    echo "✓ Session cache cleared\n";
}

// Clear any config cache
$DB->delete_records_select('cache_config', "name LIKE 'customerintel_%'");
echo "✓ Config cache entries cleared\n\n";

// Step 3: Re-validate Database Schema
echo "[3/5] Validating database schema...\n";

$dbman = $DB->get_manager();
$tables_checked = 0;
$tables_ok = 0;

$expected_tables = [
    'local_ci_company',
    'local_ci_source',
    'local_ci_run',
    'local_ci_nb_result',
    'local_ci_snapshot',
    'local_ci_diff',
    'local_ci_comparison',
    'local_ci_telemetry'
];

foreach ($expected_tables as $table) {
    $tables_checked++;
    if ($dbman->table_exists($table)) {
        echo "  ✓ Table $table exists\n";
        $tables_ok++;
    } else {
        echo "  ✗ Table $table MISSING - run upgrade.php\n";
    }
}

echo "Schema validation: $tables_ok/$tables_checked tables OK\n\n";

// Step 4: Re-register Capabilities
echo "[4/5] Re-registering capabilities...\n";

$context = context_system::instance();

// Define capabilities
$capabilities = [
    'local/customerintel:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW
        ]
    ],
    'local/customerintel:run' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
    'local/customerintel:manage' => [
        'riskbitmask' => RISK_CONFIG | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ]
];

// Update capability definitions
$caps_updated = 0;
foreach ($capabilities as $capability => $config) {
    // Check if capability exists in database
    $exists = $DB->record_exists('capabilities', ['name' => $capability]);
    
    if (!$exists) {
        // Insert new capability
        $cap = new stdClass();
        $cap->name = $capability;
        $cap->captype = $config['captype'];
        $cap->contextlevel = $config['contextlevel'];
        $cap->component = 'local_customerintel';
        $cap->riskbitmask = $config['riskbitmask'];
        
        $DB->insert_record('capabilities', $cap);
        echo "  ✓ Created capability: $capability\n";
    } else {
        // Update existing capability
        $DB->set_field('capabilities', 'riskbitmask', $config['riskbitmask'], ['name' => $capability]);
        echo "  ✓ Updated capability: $capability\n";
    }
    
    // Assign to admin role
    $adminroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
    if ($adminroleid) {
        assign_capability($capability, CAP_ALLOW, $adminroleid, $context->id, true);
    }
    
    $caps_updated++;
}

echo "Capabilities updated: $caps_updated\n\n";

// Step 5: Rebuild Component Cache
echo "[5/5] Rebuilding component cache...\n";

// Force reload of plugin information
core_plugin_manager::reset_caches();
echo "✓ Plugin manager caches reset\n";

// Update module info
if (function_exists('rebuild_course_cache')) {
    rebuild_course_cache(0, true);
    echo "✓ Course cache rebuilt\n";
}

// Clear theme caches
theme_reset_all_caches();
echo "✓ Theme caches cleared\n\n";

// Final Summary
echo "========================================\n";
echo "REBUILD SUMMARY\n";
echo "========================================\n";
echo "✓ All caches purged\n";
echo "✓ Database schema validated ($tables_ok/$tables_checked tables)\n";
echo "✓ Capabilities re-registered ($caps_updated capabilities)\n";
echo "✓ Component caches rebuilt\n";
echo "\n";

// Version check
$versionfile = __DIR__ . '/../version.php';
if (file_exists($versionfile)) {
    $plugin = new stdClass();
    include($versionfile);
    echo "Plugin version: " . ($plugin->version ?? 'unknown') . "\n";
    echo "Plugin release: " . ($plugin->release ?? 'v1.0.1') . "\n";
}

echo "\n========================================\n";
echo "Rebuild complete.\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

exit(0);