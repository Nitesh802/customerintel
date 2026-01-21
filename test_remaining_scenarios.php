<?php
/**
 * Test remaining M1T4 scenarios (3, 4, 5)
 */

require_once('../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

$scenario = optional_param('scenario', 0, PARAM_INT);
$runid = optional_param('runid', 122, PARAM_INT);

echo "<html><head><title>M1T4 Remaining Scenarios Test</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
.scenario { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.btn { padding: 12px 24px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; color: white; font-weight: bold; }
.btn-warning { background-color: #ffc107; color: black; }
.btn-success { background-color: #28a745; }
.btn-primary { background-color: #007bff; }
.result { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }
.success { color: green; font-weight: bold; }
pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
</style></head><body>";

echo "<h2>🧪 M1T4: Test Remaining Scenarios</h2>";

if ($scenario == 0) {
    // Show scenario selection
    echo "<p>Select a scenario to test with Run {$runid}:</p>";

    echo "<div class='scenario'>";
    echo "<h3>Scenario 3: Force All NB Refresh</h3>";
    echo "<p><strong>Config:</strong> force_nb_refresh = true</p>";
    echo "<p><strong>Expected:</strong> Regenerates all 15 NBs + synthesis (~3 min)</p>";
    echo "<p><strong>Expected Diagnostics:</strong></p>";
    echo "<ul>";
    echo "<li>Force regenerate ALL NBs (force_nb_refresh=true)</li>";
    echo "<li>Regenerate synthesis because all NBs were refreshed</li>";
    echo "</ul>";
    echo "<p><a href='?scenario=3&runid={$runid}' class='btn btn-warning'>▶ Test Scenario 3</a></p>";
    echo "</div>";

    echo "<div class='scenario'>";
    echo "<h3>Scenario 4: Refresh Source NBs Only</h3>";
    echo "<p><strong>Config:</strong> refresh_source = true</p>";
    echo "<p><strong>Expected:</strong> Regenerates NB-1 to NB-7 + synthesis (~2 min)</p>";
    echo "<p><strong>Expected Diagnostics:</strong></p>";
    echo "<ul>";
    echo "<li>Force regenerate SOURCE NBs (refresh_source=true)</li>";
    echo "<li>Regenerate synthesis because NBs were refreshed (refresh_source=true)</li>";
    echo "</ul>";
    echo "<p><a href='?scenario=4&runid={$runid}' class='btn btn-warning'>▶ Test Scenario 4</a></p>";
    echo "</div>";

    echo "<div class='scenario'>";
    echo "<h3>Scenario 5: Refresh Target NBs Only</h3>";
    echo "<p><strong>Config:</strong> refresh_target = true</p>";
    echo "<p><strong>Expected:</strong> Regenerates NB-8 to NB-15 + synthesis (~2 min)</p>";
    echo "<p><strong>Expected Diagnostics:</strong></p>";
    echo "<ul>";
    echo "<li>Force regenerate TARGET NBs (refresh_target=true)</li>";
    echo "<li>Regenerate synthesis because NBs were refreshed (refresh_target=true)</li>";
    echo "</ul>";
    echo "<p><a href='?scenario=5&runid={$runid}' class='btn btn-warning'>▶ Test Scenario 5</a></p>";
    echo "</div>";

} else {
    // Apply the selected scenario config
    $configs = [
        3 => [
            'name' => 'Force All NB Refresh',
            'config' => json_encode([
                'force_nb_refresh' => true,
                'force_synthesis_refresh' => false,
                'refresh_source' => false,
                'refresh_target' => false
            ]),
            'expected_nb' => 'Force regenerate ALL NBs (force_nb_refresh=true)',
            'expected_synthesis' => 'Regenerate synthesis because all NBs were refreshed'
        ],
        4 => [
            'name' => 'Refresh Source NBs Only',
            'config' => json_encode([
                'force_nb_refresh' => false,
                'force_synthesis_refresh' => false,
                'refresh_source' => true,
                'refresh_target' => false
            ]),
            'expected_nb' => 'Force regenerate SOURCE NBs (refresh_source=true)',
            'expected_synthesis' => 'Regenerate synthesis because NBs were refreshed (refresh_source=true'
        ],
        5 => [
            'name' => 'Refresh Target NBs Only',
            'config' => json_encode([
                'force_nb_refresh' => false,
                'force_synthesis_refresh' => false,
                'refresh_source' => false,
                'refresh_target' => true
            ]),
            'expected_nb' => 'Force regenerate TARGET NBs (refresh_target=true)',
            'expected_synthesis' => 'Regenerate synthesis because NBs were refreshed (refresh_target=true'
        ]
    ];

    if (!isset($configs[$scenario])) {
        echo "<p class='error'>Invalid scenario!</p>";
        echo "<p><a href='?' class='btn btn-primary'>← Back</a></p>";
    } else {
        $config = $configs[$scenario];

        echo "<div class='scenario'>";
        echo "<h3>Scenario {$scenario}: {$config['name']}</h3>";

        // Apply config
        $DB->set_field('local_ci_run', 'refresh_config', $config['config'], ['id' => $runid]);

        echo "<div class='result'>";
        echo "<p class='success'>✅ Config applied to Run {$runid}!</p>";
        echo "<pre>" . json_encode(json_decode($config['config']), JSON_PRETTY_PRINT) . "</pre>";
        echo "</div>";

        echo "<h4>Expected Diagnostics After Execution:</h4>";
        echo "<ul>";
        echo "<li><strong>NB Decision:</strong> {$config['expected_nb']}</li>";
        echo "<li><strong>Synthesis Decision:</strong> {$config['expected_synthesis']}</li>";
        echo "</ul>";

        echo "<h4>Next Steps:</h4>";
        echo "<ol>";
        echo "<li>Click 'Execute Run {$runid}' below</li>";
        echo "<li>Wait for execution to complete (~2-3 minutes)</li>";
        echo "<li>Click 'View Diagnostics' to verify the results</li>";
        echo "</ol>";

        echo "<p>";
        echo "<a href='execute_run.php?runid={$runid}' class='btn btn-success'>🚀 Execute Run {$runid}</a> ";
        echo "<a href='test_m1t4_production.php?runid={$runid}' class='btn btn-primary'>📊 View Diagnostics</a> ";
        echo "<a href='?' class='btn btn-primary'>← Back to Scenario List</a>";
        echo "</p>";

        echo "</div>";
    }
}

echo "</body></html>";
