<?php
/**
 * Test NB Generation
 *
 * Manually test if nb_orchestrator->execute_nb() works
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url('/local/customerintel/test_nb_generation.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Test NB Generation');

echo $OUTPUT->header();

?>
<style>
.test-container { max-width: 1000px; margin: 20px auto; }
.test-section { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 15px 0; border-radius: 5px; }
.success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; }
.fail { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; }
.warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 4px; }
.info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
.code-output { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; overflow-x: auto; margin: 10px 0; }
</style>

<div class="test-container">

<h1>🧪 Test NB Generation</h1>

<div class="warning">
    <strong>⚠️ WARNING:</strong> This test will:
    <ul>
        <li>Create a test run in the database</li>
        <li>Attempt to generate NB-1 (Company Overview)</li>
        <li>Make a real API call (costs money!)</li>
        <li>Save the result to database</li>
    </ul>
</div>

<?php

$run_test = optional_param('run_test', '', PARAM_TEXT);

if ($run_test !== 'yes') {
    ?>
    <div class="test-section">
        <h2>Ready to Test NB Generation?</h2>
        <p>This will test if the nb_orchestrator can generate NBs when called directly.</p>
        <form method="get" action="">
            <input type="hidden" name="run_test" value="yes">
            <button type="submit" style="background: #28a745; color: white; padding: 15px 30px; font-size: 18px; border: none; border-radius: 5px; cursor: pointer;">
                ▶️ Run NB Generation Test
            </button>
        </form>
    </div>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// ============================================================================
// TEST EXECUTION
// ============================================================================

echo "<h2>🔬 Test Execution</h2>";

// Step 1: Check prerequisites
echo "<div class='test-section'>";
echo "<h3>Step 1: Prerequisites Check</h3>";

$prereqs_ok = true;

// Check API key - CORRECT config key names
$api_key = get_config('local_customerintel', 'llm_key'); // OpenAI/Anthropic key
if (empty($api_key)) {
    $api_key = get_config('local_customerintel', 'perplexityapikey'); // Perplexity key
}

if (empty($api_key)) {
    echo "<p class='fail'>❌ No API keys configured! Cannot generate NBs.</p>";
    echo "<p>Configure API keys at: <a href='/local/customerintel/admin_settings.php'>/local/customerintel/admin_settings.php</a></p>";
    $prereqs_ok = false;
} else {
    echo "<p class='success'>✅ API key found</p>";
}

// Check nb_orchestrator exists
$nb_orch_path = __DIR__ . '/classes/services/nb_orchestrator.php';
if (!file_exists($nb_orch_path)) {
    echo "<p class='fail'>❌ nb_orchestrator.php not found at: $nb_orch_path</p>";
    $prereqs_ok = false;
} else {
    echo "<p class='success'>✅ nb_orchestrator.php file exists</p>";

    require_once($nb_orch_path);

    if (!class_exists('\\local_customerintel\\services\\nb_orchestrator')) {
        echo "<p class='fail'>❌ nb_orchestrator class not found in file</p>";
        $prereqs_ok = false;
    } else {
        echo "<p class='success'>✅ nb_orchestrator class loaded</p>";
    }
}

if (!$prereqs_ok) {
    echo "<p class='fail'><strong>Cannot continue - fix prerequisites first</strong></p>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// Step 2: Create test run
echo "<div class='test-section'>";
echo "<h3>Step 2: Create Test Run</h3>";

try {
    // Get first two companies
    $companies = $DB->get_records('local_ci_company', null, 'id ASC', '*', 0, 2);

    if (count($companies) < 2) {
        echo "<p class='fail'>❌ Need at least 2 companies. Found: " . count($companies) . "</p>";
        echo "</div>";
        echo $OUTPUT->footer();
        exit;
    }

    $companies_array = array_values($companies);
    $source = $companies_array[0];
    $target = $companies_array[1];

    echo "<p>Source Company: {$source->name} (ID: {$source->id})</p>";
    echo "<p>Target Company: {$target->name} (ID: {$target->id})</p>";

    // Create test run
    $run = new stdClass();
    $run->companyid = $source->id;
    $run->targetid = $target->id;
    $run->userid = $USER->id;
    $run->initiatedbyuserid = $USER->id;
    $run->status = 'pending'; // NB generation expects pending
    $run->timecreated = time();
    $run->timemodified = time();

    $runid = $DB->insert_record('local_ci_run', $run);

    echo "<p class='success'>✅ Created test run ID: <strong>$runid</strong></p>";

} catch (Exception $e) {
    echo "<p class='fail'>❌ Failed to create test run: " . $e->getMessage() . "</p>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

echo "</div>";

// Step 3: Test execute_nb()
echo "<div class='test-section'>";
echo "<h3>Step 3: Execute NB-1 (Company Overview)</h3>";

echo "<p class='info'>Calling nb_orchestrator->execute_nb() for NB-1...</p>";

flush();
ob_flush();

try {
    // Instantiate nb_orchestrator
    $nb_orchestrator = new \local_customerintel\services\nb_orchestrator($runid);

    echo "<p class='success'>✅ nb_orchestrator instantiated</p>";

    // Execute NB-1
    $start_time = microtime(true);

    // CORRECTED: execute_nb() requires (int $runid, string $nbcode)
    // NB codes use format 'NB-1' (with hyphen), not 'NB1'
    $result = $nb_orchestrator->execute_nb($runid, 'NB-1'); // NB-1 = Customer Fundamentals

    $end_time = microtime(true);
    $duration = $end_time - $start_time;

    echo "<p class='success'>✅ execute_nb($runid, 'NB-1') completed in " . number_format($duration, 2) . " seconds</p>";

    // Check result
    if ($result === null) {
        echo "<p class='fail'>❌ execute_nb() returned NULL</p>";
    } else if ($result === false) {
        echo "<p class='fail'>❌ execute_nb() returned FALSE (failed)</p>";
    } else {
        echo "<p class='success'>✅ execute_nb() returned a result</p>";

        // Show result details
        echo "<h4>Result Details:</h4>";
        echo "<div class='code-output'>";
        echo "Result type: " . gettype($result) . "\n\n";

        if (is_object($result)) {
            echo "Object class: " . get_class($result) . "\n";
            echo "Properties: " . implode(', ', array_keys(get_object_vars($result))) . "\n";
        } else if (is_array($result)) {
            echo "Array keys: " . implode(', ', array_keys($result)) . "\n";
        }

        echo "\nFull result:\n";
        echo json_encode($result, JSON_PRETTY_PRINT);
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ execute_nb() threw exception: " . $e->getMessage() . "</p>";
    echo "<div class='code-output'>";
    echo "Exception trace:\n";
    echo $e->getTraceAsString();
    echo "</div>";
}

echo "</div>";

// Step 4: Check if result was saved
echo "<div class='test-section'>";
echo "<h3>Step 4: Check Database Save</h3>";

try {
    $nb_result = $DB->get_record('local_ci_nb_result', ['runid' => $runid, 'nbcode' => 'NB-1']);

    if ($nb_result) {
        echo "<p class='success'>✅ NB result found in database!</p>";

        $data_size = !empty($nb_result->jsonpayload) ? strlen($nb_result->jsonpayload) : 0;

        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr><th style='border: 1px solid #ddd; padding: 8px;'>Field</th><th style='border: 1px solid #ddd; padding: 8px;'>Value</th></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>ID</td><td style='border: 1px solid #ddd; padding: 8px;'>{$nb_result->id}</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>Run ID</td><td style='border: 1px solid #ddd; padding: 8px;'>{$nb_result->runid}</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>NB Code</td><td style='border: 1px solid #ddd; padding: 8px;'>{$nb_result->nbcode}</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>Status</td><td style='border: 1px solid #ddd; padding: 8px;'>" . ($nb_result->status ?? 'NULL') . "</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>Duration</td><td style='border: 1px solid #ddd; padding: 8px;'>" . ($nb_result->durationms ?? 0) . " ms</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>Tokens Used</td><td style='border: 1px solid #ddd; padding: 8px;'>" . ($nb_result->tokensused ?? 0) . "</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>Data Size</td><td style='border: 1px solid #ddd; padding: 8px;'>$data_size bytes</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>Created</td><td style='border: 1px solid #ddd; padding: 8px;'>" . date('Y-m-d H:i:s', $nb_result->timecreated) . "</td></tr>";
        echo "</table>";

        if ($data_size > 100) {
            echo "<p class='success'>✅ NB has data ($data_size bytes) - Generation SUCCESSFUL!</p>";

            // Show sample of data
            echo "<h4>Data Sample (first 500 chars):</h4>";
            echo "<div class='code-output'>";
            echo htmlspecialchars(substr($nb_result->jsonpayload, 0, 500));
            if ($data_size > 500) {
                echo "\n\n... (" . ($data_size - 500) . " more bytes)";
            }
            echo "</div>";

        } else {
            echo "<p class='fail'>❌ NB record exists but has no data (only $data_size bytes)</p>";
        }

    } else {
        echo "<p class='fail'>❌ No NB result found in database for Run $runid, NB-1</p>";
        echo "<p>execute_nb() ran but did not save the result</p>";
    }

} catch (Exception $e) {
    echo "<p class='fail'>❌ Error checking database: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Final Verdict
echo "<div class='test-section'>";
echo "<h2>🎯 Test Verdict</h2>";

if (isset($nb_result) && $data_size > 100) {
    echo "<div class='success' style='font-size: 18px; padding: 20px;'>";
    echo "<strong>✅ SUCCESS!</strong><br><br>";
    echo "NB generation is WORKING when called directly.<br><br>";
    echo "<strong>This means:</strong><br>";
    echo "- nb_orchestrator->execute_nb() works correctly ✅<br>";
    echo "- API calls are being made successfully ✅<br>";
    echo "- Results are being saved to database ✅<br><br>";
    echo "<strong>The problem is likely:</strong><br>";
    echo "- NBs are not being triggered during run creation<br>";
    echo "- Check run.php to ensure it calls nb_orchestrator->execute_all_nbs()<br>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h4>Next Steps:</h4>";
    echo "<ol>";
    echo "<li>Review /local/customerintel/run.php for NB triggering code</li>";
    echo "<li>Check if execute_all_nbs() is called after run creation</li>";
    echo "<li>Verify background job queue is processing</li>";
    echo "</ol>";
    echo "</div>";

} else if (isset($result) && $result !== null && $result !== false) {
    echo "<div class='warning' style='font-size: 18px; padding: 20px;'>";
    echo "<strong>⚠️ PARTIAL SUCCESS</strong><br><br>";
    echo "execute_nb() returned a result, but it wasn't saved to database.<br><br>";
    echo "<strong>The problem is:</strong><br>";
    echo "- Database save logic in execute_nb() is broken<br>";
    echo "- Check nb_orchestrator.php for $DB->insert_record() calls<br>";
    echo "</div>";

} else {
    echo "<div class='fail' style='font-size: 18px; padding: 20px;'>";
    echo "<strong>❌ FAILURE</strong><br><br>";
    echo "execute_nb() failed to generate NB data.<br><br>";
    echo "<strong>Possible causes:</strong><br>";
    echo "- API call failed (check API keys)<br>";
    echo "- Network connectivity issue<br>";
    echo "- execute_nb() logic is broken<br><br>";
    echo "<strong>Check:</strong><br>";
    echo "- Error logs for API errors<br>";
    echo "- Test API keys: /local/customerintel/cli/test_api_keys.php<br>";
    echo "- Review nb_orchestrator->execute_nb() implementation<br>";
    echo "</div>";
}

echo "</div>";

// Cleanup option
echo "<div class='test-section'>";
echo "<h3>🧹 Cleanup</h3>";

echo "<p>Test run created: <strong>Run ID $runid</strong></p>";
echo "<p>You may want to delete this test run manually if it's not needed.</p>";

echo "<form method='post' action='' style='display: inline;'>";
echo "<input type='hidden' name='cleanup_run' value='$runid'>";
echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>";
echo "🗑️ Delete Test Run $runid";
echo "</button>";
echo "</form>";

if (isset($_POST['cleanup_run'])) {
    $cleanup_runid = intval($_POST['cleanup_run']);
    try {
        $DB->delete_records('local_ci_nb_result', ['runid' => $cleanup_runid]);
        $DB->delete_records('local_ci_run', ['id' => $cleanup_runid]);
        echo "<p class='success'>✅ Test run $cleanup_runid deleted</p>";
    } catch (Exception $e) {
        echo "<p class='fail'>❌ Failed to delete: " . $e->getMessage() . "</p>";
    }
}

echo "</div>";

?>

</div>

<?php
echo $OUTPUT->footer();
?>
