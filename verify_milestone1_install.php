<?php
/**
 * Milestone 1 Installation Verification Script
 *
 * Run this script to verify the per-company NB caching system is installed correctly.
 *
 * Usage: php verify_milestone1_install.php
 * Or via browser: http://your-moodle-site/local/customerintel/verify_milestone1_install.php
 */

// Detect if running from CLI or web
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    // Web access - require Moodle config and admin privileges
    require_once(__DIR__ . '/../../config.php');
    require_login();
    require_capability('moodle/site:config', context_system::instance());

    echo '<html><head><title>Milestone 1 Verification</title>';
    echo '<style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: white; padding: 10px; border: 1px solid #ccc; }
        h2 { border-bottom: 2px solid #333; }
    </style></head><body>';
    echo '<h1>Milestone 1: Per-Company NB Caching - Installation Verification</h1>';
} else {
    // CLI access - require Moodle config
    define('CLI_SCRIPT', true);
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->libdir . '/clilib.php');

    echo "\n";
    echo "=============================================================\n";
    echo "Milestone 1: Per-Company NB Caching - Installation Verification\n";
    echo "=============================================================\n\n";
}

function output($message, $type = 'info') {
    global $is_cli;

    if ($is_cli) {
        $prefix = '';
        switch ($type) {
            case 'success': $prefix = '✅ '; break;
            case 'error': $prefix = '❌ '; break;
            case 'warning': $prefix = '⚠️  '; break;
            case 'info': $prefix = 'ℹ️  '; break;
        }
        echo $prefix . $message . "\n";
    } else {
        echo "<div class='{$type}'>{$message}</div>";
    }
}

function section($title) {
    global $is_cli;

    if ($is_cli) {
        echo "\n--- {$title} ---\n";
    } else {
        echo "<h2>{$title}</h2>";
    }
}

global $DB;

$errors = 0;
$warnings = 0;

// ============================================================
// 1. Check Plugin Version
// ============================================================
section('1. Plugin Version Check');

try {
    $version = get_config('local_customerintel', 'version');

    if ($version == 2025203023) {
        output("Plugin version: {$version} ✓", 'success');
    } else {
        output("Plugin version: {$version} (expected: 2025203023)", 'error');
        $errors++;
    }
} catch (Exception $e) {
    output("Error checking version: " . $e->getMessage(), 'error');
    $errors++;
}

// ============================================================
// 2. Check Table Exists
// ============================================================
section('2. Database Table Check');

try {
    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_ci_nb_cache');

    if ($dbman->table_exists($table)) {
        output("Table 'mdl_local_ci_nb_cache' exists ✓", 'success');

        // ============================================================
        // 3. Check Table Structure
        // ============================================================
        section('3. Table Structure Check');

        $expected_fields = [
            'id' => 'ID field',
            'company_id' => 'Company ID field',
            'nbcode' => 'NB code field',
            'jsonpayload' => 'JSON payload field',
            'citations' => 'Citations field',
            'version' => 'Version field',
            'timecreated' => 'Time created field'
        ];

        foreach ($expected_fields as $field_name => $description) {
            $field = new xmldb_field($field_name);
            if ($dbman->field_exists($table, $field)) {
                output("{$description} exists ✓", 'success');
            } else {
                output("{$description} MISSING", 'error');
                $errors++;
            }
        }

        // ============================================================
        // 4. Check Indexes
        // ============================================================
        section('4. Index Check');

        $index1 = new xmldb_index('company_nb_version', XMLDB_INDEX_UNIQUE, ['company_id', 'nbcode', 'version']);
        if ($dbman->index_exists($table, $index1)) {
            output("Unique index 'company_nb_version' exists ✓", 'success');
        } else {
            output("Unique index 'company_nb_version' MISSING", 'warning');
            $warnings++;
        }

        $index2 = new xmldb_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if ($dbman->index_exists($table, $index2)) {
            output("Index 'timecreated_idx' exists ✓", 'success');
        } else {
            output("Index 'timecreated_idx' MISSING", 'warning');
            $warnings++;
        }

    } else {
        output("Table 'mdl_local_ci_nb_cache' DOES NOT EXIST", 'error');
        output("Please run: php admin/cli/upgrade.php", 'info');
        $errors++;
    }
} catch (Exception $e) {
    output("Error checking table: " . $e->getMessage(), 'error');
    $errors++;
}

// ============================================================
// 5. Check Cache Service Class
// ============================================================
section('5. Cache Service Class Check');

$cache_service_path = __DIR__ . '/classes/services/nb_cache_service.php';
if (file_exists($cache_service_path)) {
    output("Cache service file exists ✓", 'success');

    // Check if class is loadable
    try {
        require_once($cache_service_path);

        if (class_exists('local_customerintel\services\nb_cache_service')) {
            output("Cache service class loaded ✓", 'success');

            // Check methods exist
            $methods = [
                'get_cached_nb',
                'store_nb',
                'invalidate_company_cache',
                'get_cache_stats',
                'has_cached_nbs',
                'get_cached_nbcodes'
            ];

            foreach ($methods as $method) {
                if (method_exists('local_customerintel\services\nb_cache_service', $method)) {
                    output("Method '{$method}' exists ✓", 'success');
                } else {
                    output("Method '{$method}' MISSING", 'error');
                    $errors++;
                }
            }
        } else {
            output("Cache service class NOT FOUND", 'error');
            $errors++;
        }
    } catch (Exception $e) {
        output("Error loading cache service: " . $e->getMessage(), 'error');
        $errors++;
    }
} else {
    output("Cache service file DOES NOT EXIST at: {$cache_service_path}", 'error');
    $errors++;
}

// ============================================================
// 6. Check NB Orchestrator Integration
// ============================================================
section('6. NB Orchestrator Integration Check');

$orchestrator_path = __DIR__ . '/classes/services/nb_orchestrator.php';
if (file_exists($orchestrator_path)) {
    output("NB orchestrator file exists ✓", 'success');

    // Check if cache service is required
    $content = file_get_contents($orchestrator_path);

    if (strpos($content, 'nb_cache_service.php') !== false) {
        output("Cache service required in orchestrator ✓", 'success');
    } else {
        output("Cache service NOT required in orchestrator", 'error');
        $errors++;
    }

    if (strpos($content, 'nb_cache_service::get_cached_nb') !== false) {
        output("Cache check integrated ✓", 'success');
    } else {
        output("Cache check NOT integrated", 'error');
        $errors++;
    }

    if (strpos($content, 'nb_cache_service::store_nb') !== false) {
        output("Cache store integrated ✓", 'success');
    } else {
        output("Cache store NOT integrated", 'error');
        $errors++;
    }
} else {
    output("NB orchestrator file DOES NOT EXIST", 'error');
    $errors++;
}

// ============================================================
// 7. Check Cache Statistics (if table exists)
// ============================================================
if ($dbman->table_exists(new xmldb_table('local_ci_nb_cache'))) {
    section('7. Cache Statistics');

    try {
        $cache_count = $DB->count_records('local_ci_nb_cache');
        output("Total cached NBs: {$cache_count}", 'info');

        if ($cache_count > 0) {
            $sql = "SELECT COUNT(DISTINCT company_id) as companies,
                           COUNT(DISTINCT nbcode) as nbcodes,
                           MIN(timecreated) as oldest,
                           MAX(timecreated) as newest
                    FROM {local_ci_nb_cache}";
            $stats = $DB->get_record_sql($sql);

            output("Unique companies cached: {$stats->companies}", 'info');
            output("Unique NB types cached: {$stats->nbcodes}", 'info');
            output("Oldest cache: " . date('Y-m-d H:i:s', $stats->oldest), 'info');
            output("Newest cache: " . date('Y-m-d H:i:s', $stats->newest), 'info');
        }
    } catch (Exception $e) {
        output("Error getting cache stats: " . $e->getMessage(), 'warning');
        $warnings++;
    }
}

// ============================================================
// 8. Check Diagnostics Logging
// ============================================================
section('8. Diagnostics Logging Check');

try {
    $sql = "SELECT COUNT(*) as count
            FROM {local_ci_diagnostics}
            WHERE metric LIKE 'nb_cache%'";
    $diag_count = $DB->get_record_sql($sql);

    if ($diag_count && $diag_count->count > 0) {
        output("Cache diagnostics entries: {$diag_count->count} ✓", 'success');

        // Get breakdown
        $sql = "SELECT metric, COUNT(*) as count
                FROM {local_ci_diagnostics}
                WHERE metric LIKE 'nb_cache%'
                GROUP BY metric";
        $metrics = $DB->get_records_sql($sql);

        foreach ($metrics as $metric) {
            output("  - {$metric->metric}: {$metric->count}", 'info');
        }
    } else {
        output("No cache diagnostics entries yet (this is normal if no runs have been executed)", 'info');
    }
} catch (Exception $e) {
    output("Error checking diagnostics: " . $e->getMessage(), 'warning');
    $warnings++;
}

// ============================================================
// 9. Backward Compatibility Check
// ============================================================
section('9. Backward Compatibility Check');

try {
    // Check if synthesis table still exists (M0)
    $synthesis_table = new xmldb_table('local_ci_synthesis');
    if ($dbman->table_exists($synthesis_table)) {
        output("M0 synthesis table exists ✓", 'success');

        $synthesis_count = $DB->count_records('local_ci_synthesis');
        output("Existing synthesis records: {$synthesis_count}", 'info');
    } else {
        output("M0 synthesis table missing (this may be expected)", 'warning');
        $warnings++;
    }
} catch (Exception $e) {
    output("Error checking backward compatibility: " . $e->getMessage(), 'warning');
    $warnings++;
}

// ============================================================
// Summary
// ============================================================
section('Verification Summary');

if ($errors === 0 && $warnings === 0) {
    output("✅ ALL CHECKS PASSED! Milestone 1 is correctly installed.", 'success');
} else if ($errors === 0) {
    output("✅ Installation verified with {$warnings} warning(s).", 'success');
} else {
    output("❌ Installation INCOMPLETE: {$errors} error(s), {$warnings} warning(s)", 'error');
    output("Please review errors above and run: php admin/cli/upgrade.php", 'info');
}

output("\nVerification complete at " . date('Y-m-d H:i:s'), 'info');

if (!$is_cli) {
    echo '</body></html>';
} else {
    echo "\n";
}

// Return exit code for CLI
if ($is_cli) {
    exit($errors > 0 ? 1 : 0);
}
?>
