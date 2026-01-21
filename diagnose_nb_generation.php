<?php
/**
 * NB Generation Diagnostic Script
 *
 * Comprehensive diagnostics to determine why NBs aren't being generated.
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/customerintel/diagnose_nb_generation.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('NB Generation Diagnostics');

echo $OUTPUT->header();

?>
<style>
.diagnostic-container { max-width: 1200px; margin: 20px auto; }
.check-section { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 5px; }
.check-item { padding: 10px; margin: 5px 0; border-left: 4px solid #ddd; background: white; }
.check-item.pass { border-left-color: #28a745; background: #d4edda; }
.check-item.fail { border-left-color: #dc3545; background: #f8d7da; }
.check-item.warning { border-left-color: #ffc107; background: #fff3cd; }
.check-title { font-weight: bold; font-size: 16px; margin-bottom: 5px; }
.check-detail { font-size: 14px; color: #666; margin-left: 20px; }
.status-icon { font-size: 20px; margin-right: 10px; }
.code-block { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; overflow-x: auto; margin: 10px 0; }
.summary { background: white; border: 2px solid #007bff; border-radius: 8px; padding: 20px; margin: 20px 0; }
.summary.critical { border-color: #dc3545; background: #f8d7da; }
.summary.good { border-color: #28a745; background: #d4edda; }
</style>

<div class="diagnostic-container">

<h1>🔍 NB Generation Diagnostics</h1>

<p><strong>Purpose:</strong> Identify why Notebook (NB) generation is not working.</p>

<?php

$diagnostics = [];
$critical_issues = [];
$warnings = [];

// ============================================================================
// CHECK 1: Database Table Exists
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 1: Database Tables</h2>";

$tables_to_check = [
    'local_ci_nb_result' => 'Stores NB results',
    'local_ci_run' => 'Stores run records',
    'local_ci_company' => 'Stores company data'
];

foreach ($tables_to_check as $table => $purpose) {
    try {
        $exists = $DB->get_manager()->table_exists($table);

        if ($exists) {
            echo "<div class='check-item pass'>";
            echo "<span class='status-icon'>✅</span>";
            echo "<span class='check-title'>Table '$table' exists</span>";
            echo "<div class='check-detail'>$purpose</div>";
            echo "</div>";

            // Count records
            $count = $DB->count_records($table);
            echo "<div class='check-detail'>Records in table: <strong>$count</strong></div>";

        } else {
            echo "<div class='check-item fail'>";
            echo "<span class='status-icon'>❌</span>";
            echo "<span class='check-title'>Table '$table' MISSING</span>";
            echo "<div class='check-detail'>$purpose - This is CRITICAL!</div>";
            echo "</div>";
            $critical_issues[] = "Table '$table' does not exist";
        }
    } catch (Exception $e) {
        echo "<div class='check-item fail'>";
        echo "<span class='status-icon'>❌</span>";
        echo "<span class='check-title'>Error checking table '$table'</span>";
        echo "<div class='check-detail'>" . $e->getMessage() . "</div>";
        echo "</div>";
        $critical_issues[] = "Cannot check table '$table': " . $e->getMessage();
    }
}

echo "</div>";

// ============================================================================
// CHECK 2: Any Runs with NB Data?
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 2: Historical NB Data</h2>";

try {
    $all_nbs = $DB->get_records('local_ci_nb_result');
    $nb_count = count($all_nbs);

    $nbs_with_data = 0;
    foreach ($all_nbs as $nb) {
        if (!empty($nb->jsondata) && strlen($nb->jsondata) > 100) {
            $nbs_with_data++;
        }
    }

    if ($nb_count === 0) {
        echo "<div class='check-item fail'>";
        echo "<span class='status-icon'>❌</span>";
        echo "<span class='check-title'>NO NB records found in database</span>";
        echo "<div class='check-detail'>NB generation has NEVER worked on this system</div>";
        echo "</div>";
        $critical_issues[] = "No NB records exist in database";

    } else if ($nbs_with_data === 0) {
        echo "<div class='check-item warning'>";
        echo "<span class='status-icon'>⚠️</span>";
        echo "<span class='check-title'>$nb_count NB records found, but NONE have data</span>";
        echo "<div class='check-detail'>NBs are being created but not populated</div>";
        echo "</div>";
        $warnings[] = "NB records exist but have no data";

    } else {
        echo "<div class='check-item pass'>";
        echo "<span class='status-icon'>✅</span>";
        echo "<span class='check-title'>$nbs_with_data out of $nb_count NB records have data</span>";
        $percent = round(($nbs_with_data / $nb_count) * 100);
        echo "<div class='check-detail'>$percent% data coverage - NB generation HAS worked before</div>";
        echo "</div>";

        // Find most recent NB with data
        $recent_nb_with_data = null;
        foreach ($all_nbs as $nb) {
            if (!empty($nb->jsondata) && strlen($nb->jsondata) > 100) {
                if (!$recent_nb_with_data || $nb->timecreated > $recent_nb_with_data->timecreated) {
                    $recent_nb_with_data = $nb;
                }
            }
        }

        if ($recent_nb_with_data) {
            echo "<div class='check-detail'>";
            echo "Most recent NB with data: Run {$recent_nb_with_data->runid}, ";
            echo "NB{$recent_nb_with_data->nbid}, ";
            echo "Created: " . date('Y-m-d H:i:s', $recent_nb_with_data->timecreated);
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='check-item fail'>";
    echo "<span class='status-icon'>❌</span>";
    echo "<span class='check-title'>Error checking NB records</span>";
    echo "<div class='check-detail'>" . $e->getMessage() . "</div>";
    echo "</div>";
}

echo "</div>";

// ============================================================================
// CHECK 3: API Key Configuration
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 3: API Key Configuration</h2>";

$api_configs = [
    'openai_api_key' => 'OpenAI API Key',
    'anthropic_api_key' => 'Anthropic API Key',
    'perplexity_api_key' => 'Perplexity API Key'
];

$has_api_keys = false;

foreach ($api_configs as $config_name => $display_name) {
    $value = get_config('local_customerintel', $config_name);

    if (!empty($value)) {
        echo "<div class='check-item pass'>";
        echo "<span class='status-icon'>✅</span>";
        echo "<span class='check-title'>$display_name is configured</span>";
        // Show masked key
        $masked = substr($value, 0, 8) . '...' . substr($value, -4);
        echo "<div class='check-detail'>Value: $masked</div>";
        echo "</div>";
        $has_api_keys = true;
    } else {
        echo "<div class='check-item fail'>";
        echo "<span class='status-icon'>❌</span>";
        echo "<span class='check-title'>$display_name is NOT configured</span>";
        echo "<div class='check-detail'>NBs cannot be generated without API keys!</div>";
        echo "</div>";
        $critical_issues[] = "$display_name is missing";
    }
}

if (!$has_api_keys) {
    echo "<div class='check-item fail' style='background: #f8d7da; border: 2px solid #dc3545; margin-top: 10px;'>";
    echo "<span class='status-icon'>❌</span>";
    echo "<span class='check-title' style='color: #721c24;'>CRITICAL: NO API KEYS CONFIGURED</span>";
    echo "<div class='check-detail' style='color: #721c24;'>";
    echo "Configure API keys at: <a href='/local/customerintel/admin_settings.php'>/local/customerintel/admin_settings.php</a>";
    echo "</div>";
    echo "</div>";
}

echo "</div>";

// ============================================================================
// CHECK 4: NB Orchestrator File Exists
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 4: NB Orchestrator Service</h2>";

$nb_orchestrator_path = __DIR__ . '/classes/services/nb_orchestrator.php';

if (file_exists($nb_orchestrator_path)) {
    echo "<div class='check-item pass'>";
    echo "<span class='status-icon'>✅</span>";
    echo "<span class='check-title'>nb_orchestrator.php file exists</span>";
    echo "<div class='check-detail'>Path: $nb_orchestrator_path</div>";
    echo "</div>";

    // Try to load the class
    try {
        require_once($nb_orchestrator_path);

        if (class_exists('\\local_customerintel\\services\\nb_orchestrator')) {
            echo "<div class='check-item pass'>";
            echo "<span class='status-icon'>✅</span>";
            echo "<span class='check-title'>nb_orchestrator class can be loaded</span>";
            echo "</div>";

            // Check for key methods
            $reflection = new ReflectionClass('\\local_customerintel\\services\\nb_orchestrator');
            $methods = $reflection->getMethods();

            $key_methods = ['execute_nb', 'execute_all_nbs', 'get_nb_config'];
            $found_methods = [];

            foreach ($methods as $method) {
                if (in_array($method->getName(), $key_methods)) {
                    $found_methods[] = $method->getName();
                }
            }

            echo "<div class='check-detail'>";
            echo "Found methods: " . implode(', ', array_map(function($m) { return $m; }, array_column($methods, 'name')));
            echo "</div>";

            foreach ($key_methods as $method_name) {
                if (in_array($method_name, $found_methods)) {
                    echo "<div class='check-item pass'>";
                    echo "<span class='status-icon'>✅</span>";
                    echo "<span class='check-title'>Method '$method_name()' exists</span>";
                    echo "</div>";
                } else {
                    echo "<div class='check-item fail'>";
                    echo "<span class='status-icon'>❌</span>";
                    echo "<span class='check-title'>Method '$method_name()' MISSING</span>";
                    echo "</div>";
                    $critical_issues[] = "nb_orchestrator missing method '$method_name()'";
                }
            }

        } else {
            echo "<div class='check-item fail'>";
            echo "<span class='status-icon'>❌</span>";
            echo "<span class='check-title'>nb_orchestrator class NOT found in file</span>";
            echo "</div>";
            $critical_issues[] = "nb_orchestrator class does not exist";
        }

    } catch (Exception $e) {
        echo "<div class='check-item fail'>";
        echo "<span class='status-icon'>❌</span>";
        echo "<span class='check-title'>Error loading nb_orchestrator class</span>";
        echo "<div class='check-detail'>" . $e->getMessage() . "</div>";
        echo "</div>";
        $critical_issues[] = "Cannot load nb_orchestrator: " . $e->getMessage();
    }

} else {
    echo "<div class='check-item fail'>";
    echo "<span class='status-icon'>❌</span>";
    echo "<span class='check-title'>nb_orchestrator.php file NOT FOUND</span>";
    echo "<div class='check-detail'>Expected at: $nb_orchestrator_path</div>";
    echo "</div>";
    $critical_issues[] = "nb_orchestrator.php file does not exist";
}

echo "</div>";

// ============================================================================
// CHECK 5: Scheduled Tasks / Cron Jobs
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 5: Scheduled Tasks</h2>";

try {
    $tasks = $DB->get_records('task_scheduled', ['component' => 'local_customerintel']);

    if (empty($tasks)) {
        echo "<div class='check-item warning'>";
        echo "<span class='status-icon'>⚠️</span>";
        echo "<span class='check-title'>No scheduled tasks found for local_customerintel</span>";
        echo "<div class='check-detail'>NBs may need manual triggering</div>";
        echo "</div>";
        $warnings[] = "No scheduled tasks configured";
    } else {
        echo "<div class='check-item pass'>";
        echo "<span class='status-icon'>✅</span>";
        echo "<span class='check-title'>" . count($tasks) . " scheduled task(s) found</span>";
        echo "</div>";

        foreach ($tasks as $task) {
            $disabled = $task->disabled ? '❌ DISABLED' : '✅ ENABLED';
            echo "<div class='check-detail'>";
            echo "Task: {$task->classname} - $disabled";
            echo "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='check-item warning'>";
    echo "<span class='status-icon'>⚠️</span>";
    echo "<span class='check-title'>Could not check scheduled tasks</span>";
    echo "<div class='check-detail'>" . $e->getMessage() . "</div>";
    echo "</div>";
}

echo "</div>";

// ============================================================================
// CHECK 6: Database Write Permissions
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 6: Database Write Permissions</h2>";

try {
    // Try to insert a test record
    $test_record = new stdClass();
    $test_record->runid = 99999; // Fake run ID
    $test_record->nbid = 0;
    $test_record->type = 'test';
    $test_record->status = 'test';
    $test_record->jsondata = '{"test": true}';
    $test_record->timecreated = time();
    $test_record->timemodified = time();

    $inserted_id = $DB->insert_record('local_ci_nb_result', $test_record);

    if ($inserted_id) {
        echo "<div class='check-item pass'>";
        echo "<span class='status-icon'>✅</span>";
        echo "<span class='check-title'>Database write permissions OK</span>";
        echo "<div class='check-detail'>Successfully inserted test record (ID: $inserted_id)</div>";
        echo "</div>";

        // Clean up test record
        $DB->delete_records('local_ci_nb_result', ['id' => $inserted_id]);
        echo "<div class='check-detail'>Test record cleaned up</div>";
    }

} catch (Exception $e) {
    echo "<div class='check-item fail'>";
    echo "<span class='status-icon'>❌</span>";
    echo "<span class='check-title'>Database write FAILED</span>";
    echo "<div class='check-detail'>" . $e->getMessage() . "</div>";
    echo "</div>";
    $critical_issues[] = "Cannot write to local_ci_nb_result table: " . $e->getMessage();
}

echo "</div>";

// ============================================================================
// CHECK 7: Recent Error Logs
// ============================================================================

echo "<div class='check-section'>";
echo "<h2>Check 7: Recent Error Logs</h2>";

echo "<div class='check-item warning'>";
echo "<span class='status-icon'>ℹ️</span>";
echo "<span class='check-title'>Check server error logs manually</span>";
echo "<div class='check-detail'>";
echo "Look for NB-related errors in:<br>";
echo "- /var/www/html/moodledata/error.log<br>";
echo "- Apache/Nginx error logs<br>";
echo "- PHP error logs<br>";
echo "<br>";
echo "Search for: 'nb_orchestrator', 'execute_nb', 'NB generation'";
echo "</div>";
echo "</div>";

echo "</div>";

// ============================================================================
// FINAL SUMMARY
// ============================================================================

echo "<h2>📋 Diagnostic Summary</h2>";

if (count($critical_issues) > 0) {
    echo "<div class='summary critical'>";
    echo "<h3>❌ CRITICAL ISSUES FOUND (" . count($critical_issues) . ")</h3>";
    echo "<ol>";
    foreach ($critical_issues as $issue) {
        echo "<li><strong>$issue</strong></li>";
    }
    echo "</ol>";
    echo "</div>";
}

if (count($warnings) > 0) {
    echo "<div class='summary' style='border-color: #ffc107; background: #fff3cd;'>";
    echo "<h3>⚠️ WARNINGS (" . count($warnings) . ")</h3>";
    echo "<ul>";
    foreach ($warnings as $warning) {
        echo "<li>$warning</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (count($critical_issues) === 0 && count($warnings) === 0) {
    echo "<div class='summary good'>";
    echo "<h3>✅ No Critical Issues Found</h3>";
    echo "<p>All systems appear operational. If NBs still aren't generating, check:</p>";
    echo "<ul>";
    echo "<li>NB triggering mechanism in run.php</li>";
    echo "<li>Background job queue</li>";
    echo "<li>API connectivity</li>";
    echo "</ul>";
    echo "</div>";
}

// ============================================================================
// RECOMMENDED ACTIONS
// ============================================================================

echo "<h2>🔧 Recommended Actions</h2>";

echo "<div class='check-section'>";

if (in_array("NO API KEYS CONFIGURED", array_map(function($i) { return strpos($i, 'API') !== false ? 'NO API KEYS CONFIGURED' : ''; }, $critical_issues))) {
    echo "<div class='check-item fail' style='font-size: 16px;'>";
    echo "<span class='status-icon'>1️⃣</span>";
    echo "<span class='check-title'>IMMEDIATE: Configure API Keys</span>";
    echo "<div class='check-detail'>";
    echo "Go to: <a href='/local/customerintel/admin_settings.php' target='_blank'>/local/customerintel/admin_settings.php</a><br>";
    echo "Configure at least one of: OpenAI, Anthropic, or Perplexity API keys";
    echo "</div>";
    echo "</div>";
}

echo "<div class='check-item warning' style='font-size: 16px;'>";
echo "<span class='status-icon'>2️⃣</span>";
echo "<span class='check-title'>Test NB Generation Manually</span>";
echo "<div class='check-detail'>";
echo "Run: <a href='/local/customerintel/test_nb_generation.php' target='_blank'>/local/customerintel/test_nb_generation.php</a><br>";
echo "This will test if execute_nb() works when called directly";
echo "</div>";
echo "</div>";

echo "<div class='check-item warning' style='font-size: 16px;'>";
echo "<span class='status-icon'>3️⃣</span>";
echo "<span class='check-title'>Check Run Creation Flow</span>";
echo "<div class='check-detail'>";
echo "Verify that run.php triggers NB generation when creating a new run<br>";
echo "Look for calls to nb_orchestrator->execute_all_nbs()";
echo "</div>";
echo "</div>";

echo "<div class='check-item warning' style='font-size: 16px;'>";
echo "<span class='status-icon'>4️⃣</span>";
echo "<span class='check-title'>Test API Connectivity</span>";
echo "<div class='check-detail'>";
echo "Run: <a href='/local/customerintel/cli/test_api_keys.php' target='_blank'>/local/customerintel/cli/test_api_keys.php</a><br>";
echo "Verify API keys are valid and network connectivity works";
echo "</div>";
echo "</div>";

echo "</div>";

?>

</div>

<?php
echo $OUTPUT->footer();
?>
