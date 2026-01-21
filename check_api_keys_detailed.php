<?php
/**
 * Detailed API Key Check
 *
 * Verify API keys are configured AND test if they actually work
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/customerintel/check_api_keys_detailed.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('API Key Detailed Check');

echo $OUTPUT->header();

?>
<style>
.check-container { max-width: 1000px; margin: 20px auto; }
.check-section { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 5px; }
.success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; }
.fail { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; }
.warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 4px; }
.info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
.code-output { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; overflow-x: auto; }
</style>

<div class="check-container">

<h1>🔑 API Key Detailed Check</h1>

<?php

// Check all API keys - CORRECT config key names
$api_configs = [
    'llm_key' => ['name' => 'OpenAI/Anthropic', 'prefix' => 'sk-'],
    'perplexityapikey' => ['name' => 'Perplexity', 'prefix' => 'pplx-']
];

echo "<div class='check-section'>";
echo "<h2>Step 1: Check Configuration Storage</h2>";

$configured_keys = [];

foreach ($api_configs as $config_key => $info) {
    $value = get_config('local_customerintel', $config_key);

    echo "<h3>{$info['name']} API Key</h3>";

    if (empty($value)) {
        echo "<div class='fail'>";
        echo "❌ NOT configured (empty value)";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "✅ Value exists in config";
        echo "</div>";

        // Show masked value
        $masked = substr($value, 0, 12) . '...' . substr($value, -6);
        echo "<div class='info'>";
        echo "<strong>Stored value:</strong> $masked<br>";
        echo "<strong>Length:</strong> " . strlen($value) . " characters<br>";
        echo "<strong>Starts with expected prefix?</strong> ";

        if (strpos($value, $info['prefix']) === 0) {
            echo "✅ Yes ({$info['prefix']}...)";
            $configured_keys[$config_key] = $value;
        } else {
            echo "⚠️ No (expected {$info['prefix']}..., got " . substr($value, 0, 8) . "...)";
        }
        echo "</div>";
    }
}

echo "</div>";

// Check if nb_orchestrator can ACCESS the keys
echo "<div class='check-section'>";
echo "<h2>Step 2: Check nb_orchestrator Can Access Keys</h2>";

try {
    require_once(__DIR__ . '/classes/services/nb_orchestrator.php');

    // Create a test run
    $test_runid = 999999;

    // Try to instantiate nb_orchestrator
    $nb_orch = new \local_customerintel\services\nb_orchestrator($test_runid);

    echo "<div class='success'>";
    echo "✅ nb_orchestrator instantiated successfully";
    echo "</div>";

    // Check if it has methods to retrieve API keys
    $reflection = new ReflectionClass($nb_orch);
    $methods = $reflection->getMethods();

    echo "<div class='info'>";
    echo "<strong>Available methods:</strong><br>";
    echo "<ul style='column-count: 3; font-size: 12px;'>";
    foreach ($methods as $method) {
        echo "<li>{$method->getName()}</li>";
    }
    echo "</ul>";
    echo "</div>";

    // Try to access API keys through nb_orchestrator
    echo "<h3>Testing API Key Retrieval</h3>";

    // Check if there's a method to get API keys
    $key_methods = ['get_api_key', 'get_openai_key', 'get_anthropic_key', 'load_config'];
    $found_key_method = false;

    foreach ($key_methods as $method_name) {
        if ($reflection->hasMethod($method_name)) {
            echo "<div class='success'>";
            echo "✅ Found method: $method_name()";
            echo "</div>";
            $found_key_method = true;
        }
    }

    if (!$found_key_method) {
        echo "<div class='warning'>";
        echo "⚠️ No obvious API key retrieval method found<br>";
        echo "Keys may be retrieved directly via get_config() in execute_nb()";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='fail'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

// Check actual NB records to see what happened
echo "<div class='check-section'>";
echo "<h2>Step 3: Inspect Recent NB Records</h2>";

try {
    // Get 5 most recent NB records
    $recent_nbs = $DB->get_records('local_ci_nb_result', null, 'timecreated DESC', '*', 0, 5);

    if (empty($recent_nbs)) {
        echo "<div class='warning'>";
        echo "⚠️ No NB records found in database";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>Found " . count($recent_nbs) . " recent NB records:</strong>";
        echo "</div>";

        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='background: #007bff; color: white;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Run ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>NB ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Status</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Data Size</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Created</th>";
        echo "</tr>";

        foreach ($recent_nbs as $nb) {
            $data_size = !empty($nb->jsondata) ? strlen($nb->jsondata) : 0;
            $has_data = $data_size > 100;

            $row_color = $has_data ? '#d4edda' : '#f8d7da';

            echo "<tr style='background: $row_color;'>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$nb->id}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$nb->runid}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>NB{$nb->nbid}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($nb->status ?? 'NULL') . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>$data_size bytes</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . date('Y-m-d H:i:s', $nb->timecreated) . "</td>";
            echo "</tr>";

            // If there's data, show a sample
            if ($has_data) {
                echo "<tr style='background: $row_color;'>";
                echo "<td colspan='6' style='border: 1px solid #ddd; padding: 8px;'>";
                echo "<strong>Data Sample:</strong><br>";
                echo "<div class='code-output' style='max-height: 150px; overflow-y: auto;'>";
                echo htmlspecialchars(substr($nb->jsondata, 0, 500));
                if ($data_size > 500) {
                    echo "\n\n... (" . ($data_size - 500) . " more bytes)";
                }
                echo "</div>";
                echo "</td>";
                echo "</tr>";
            }
        }

        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div class='fail'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

// Check if there are error records
echo "<div class='check-section'>";
echo "<h2>Step 4: Check for NB Errors</h2>";

try {
    // Check if there's an error table
    if ($DB->get_manager()->table_exists('local_ci_nb_error')) {
        $errors = $DB->get_records('local_ci_nb_error', null, 'timecreated DESC', '*', 0, 10);

        if (empty($errors)) {
            echo "<div class='success'>";
            echo "✅ No errors logged in local_ci_nb_error table";
            echo "</div>";
        } else {
            echo "<div class='fail'>";
            echo "❌ Found " . count($errors) . " error records:";
            echo "</div>";

            foreach ($errors as $error) {
                echo "<div class='warning'>";
                echo "<strong>Run {$error->runid}, NB{$error->nbid}:</strong><br>";
                echo "Error: " . ($error->error_message ?? 'No message') . "<br>";
                echo "Time: " . date('Y-m-d H:i:s', $error->timecreated);
                echo "</div>";
            }
        }
    } else {
        echo "<div class='info'>";
        echo "ℹ️ No error tracking table (local_ci_nb_error) found";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='info'>";
    echo "ℹ️ Could not check error table: " . $e->getMessage();
    echo "</div>";
}

echo "</div>";

// Recommendation
echo "<div class='check-section'>";
echo "<h2>🎯 Diagnosis</h2>";

if (count($configured_keys) === 0) {
    echo "<div class='fail'>";
    echo "<h3>❌ NO VALID API KEYS CONFIGURED</h3>";
    echo "<p>Even though you said keys are configured, the system shows:</p>";
    echo "<ul>";
    echo "<li>All config values are empty or invalid</li>";
    echo "<li>Keys may have been deleted or corrupted</li>";
    echo "<li>Config storage may be broken</li>";
    echo "</ul>";
    echo "<p><strong>Action:</strong> Re-configure API keys at /local/customerintel/admin_settings.php</p>";
    echo "</div>";

} else if (count($configured_keys) > 0) {
    echo "<div class='success'>";
    echo "<h3>✅ API KEYS ARE CONFIGURED</h3>";
    echo "<p>Found " . count($configured_keys) . " configured API key(s):</p>";
    echo "<ul>";
    foreach ($configured_keys as $key => $value) {
        echo "<li>" . str_replace('_api_key', '', $key) . ": " . substr($value, 0, 12) . "...</li>";
    }
    echo "</ul>";
    echo "</div>";

    echo "<div class='warning'>";
    echo "<h3>⚠️ BUT NBs ARE STILL EMPTY</h3>";
    echo "<p>Possible reasons:</p>";
    echo "<ol>";
    echo "<li><strong>execute_nb() is never being called</strong> - NBs created but not populated</li>";
    echo "<li><strong>API calls are failing</strong> - Keys may be invalid or network issue</li>";
    echo "<li><strong>execute_nb() has a bug</strong> - Exception thrown during execution</li>";
    echo "<li><strong>Different config being used</strong> - Code looking in wrong place for keys</li>";
    echo "</ol>";
    echo "<p><strong>Next Step:</strong> Run /local/customerintel/test_nb_generation.php to test if execute_nb() works</p>";
    echo "</div>";
}

echo "</div>";

?>

<div class="check-section">
    <h2>📝 Next Steps</h2>
    <ol>
        <li>
            <strong>If keys ARE configured:</strong> Run the manual NB test<br>
            <a href="/local/customerintel/test_nb_generation.php" target="_blank" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;">
                ▶️ Test NB Generation
            </a>
        </li>
        <li>
            <strong>If keys are NOT configured:</strong> Configure them<br>
            <a href="/local/customerintel/admin_settings.php" target="_blank" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;">
                ⚙️ Admin Settings
            </a>
        </li>
        <li>
            <strong>Check error logs manually:</strong><br>
            <code>/var/www/html/moodledata/error.log</code><br>
            Search for: "execute_nb", "API", "nb_orchestrator"
        </li>
    </ol>
</div>

</div>

<?php
echo $OUTPUT->footer();
?>
