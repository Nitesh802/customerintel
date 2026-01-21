<?php
/**
 * M1 Task 4: Production Testing Helper
 *
 * Allows testing refresh_config on real production runs
 *
 * @package    local_customerintel
 * @copyright  2025 Fused Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

$runid = optional_param('runid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

echo "<html><head><title>M1T4 Production Testing Helper</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
h3 { color: #555; margin-top: 20px; }
.section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #007bff; color: white; }
tr:nth-child(even) { background-color: #f9f9f9; }
.btn { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-primary { background-color: #007bff; color: white; }
.btn-success { background-color: #28a745; color: white; }
.btn-warning { background-color: #ffc107; color: black; }
.btn-danger { background-color: #dc3545; color: white; }
.btn:hover { opacity: 0.8; }
pre { background: #f8f9fa; padding: 10px; border: 1px solid #ddd; overflow-x: auto; border-radius: 4px; }
.config-option { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }
.config-option h4 { margin-top: 0; color: #007bff; }
</style></head><body>";

echo "<h2>🧪 M1 Task 4: Production Testing Helper</h2>";
echo "<p class='info'>This tool helps you test refresh_config on real production runs</p>";

// Handle config update
if ($action === 'update' && $runid > 0) {
    $config_type = optional_param('config_type', '', PARAM_ALPHA);

    $configs = [
        'default' => json_encode([
            'force_nb_refresh' => false,
            'force_synthesis_refresh' => false,
            'refresh_source' => false,
            'refresh_target' => false
        ]),
        'force_all' => json_encode([
            'force_nb_refresh' => true,
            'force_synthesis_refresh' => false,
            'refresh_source' => false,
            'refresh_target' => false
        ]),
        'force_synthesis' => json_encode([
            'force_nb_refresh' => false,
            'force_synthesis_refresh' => true,
            'refresh_source' => false,
            'refresh_target' => false
        ]),
        'refresh_source' => json_encode([
            'force_nb_refresh' => false,
            'force_synthesis_refresh' => false,
            'refresh_source' => true,
            'refresh_target' => false
        ]),
        'refresh_target' => json_encode([
            'force_nb_refresh' => false,
            'force_synthesis_refresh' => false,
            'refresh_source' => false,
            'refresh_target' => true
        ])
    ];

    if (isset($configs[$config_type])) {
        $DB->set_field('local_ci_run', 'refresh_config', $configs[$config_type], ['id' => $runid]);
        echo "<div class='section'>";
        echo "<p class='success'>✅ Updated Run {$runid} refresh_config to: {$config_type}</p>";
        echo "<pre>" . json_encode(json_decode($configs[$config_type]), JSON_PRETTY_PRINT) . "</pre>";
        echo "</div>";
    }
}

// Step 1: Select a run
echo "<div class='section'>";
echo "<h3>Step 1: Select a Run to Test</h3>";

if ($runid > 0) {
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', IGNORE_MISSING);

    if (!$run) {
        echo "<p class='error'>❌ Run {$runid} not found</p>";
        echo "<p><a href='?'>← Back to run selection</a></p>";
    } else {
        // Display run info
        echo "<h4>Selected Run: #{$run->id}</h4>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Status</td><td>{$run->status}</td></tr>";
        echo "<tr><td>Company ID</td><td>{$run->companyid}</td></tr>";
        echo "<tr><td>Target Company ID</td><td>{$run->targetcompanyid}</td></tr>";
        echo "<tr><td>Cache Strategy</td><td>" . ($run->cache_strategy ?? 'null') . "</td></tr>";
        echo "<tr><td>Created</td><td>" . date('Y-m-d H:i:s', $run->timecreated) . "</td></tr>";

        $current_config = $run->refresh_config ? json_decode($run->refresh_config, true) : null;
        echo "<tr><td>Current refresh_config</td><td>";
        if ($current_config) {
            echo "<pre>" . json_encode($current_config, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "<em>null (uses default behavior)</em>";
        }
        echo "</td></tr>";
        echo "</table>";

        echo "<p><a href='?' class='btn btn-primary'>← Select Different Run</a></p>";

        // Step 2: Choose config
        echo "<hr>";
        echo "<h3>Step 2: Set refresh_config for This Run</h3>";
        echo "<p>Click a scenario below to update the run's refresh_config:</p>";

        echo "<div class='config-option'>";
        echo "<h4>Scenario A: Default (UI-driven cache)</h4>";
        echo "<p>All flags false - uses normal cache_strategy field</p>";
        echo "<a href='?runid={$runid}&action=update&config_type=default' class='btn btn-primary'>Apply Default Config</a>";
        echo "</div>";

        echo "<div class='config-option'>";
        echo "<h4>Scenario B: Force All NBs Refresh</h4>";
        echo "<p>Regenerates all 15 NBs + synthesis (~3 min)</p>";
        echo "<a href='?runid={$runid}&action=update&config_type=force_all' class='btn btn-warning'>Apply Force All Config</a>";
        echo "</div>";

        echo "<div class='config-option'>";
        echo "<h4>Scenario C: Force Synthesis Only</h4>";
        echo "<p>Reuse NBs, regenerate synthesis only (~30 sec)</p>";
        echo "<a href='?runid={$runid}&action=update&config_type=force_synthesis' class='btn btn-success'>Apply Force Synthesis Config</a>";
        echo "</div>";

        echo "<div class='config-option'>";
        echo "<h4>Scenario D: Refresh Source NBs Only</h4>";
        echo "<p>Regenerate NB-1 to NB-7 + synthesis (~2 min)</p>";
        echo "<a href='?runid={$runid}&action=update&config_type=refresh_source' class='btn btn-warning'>Apply Refresh Source Config</a>";
        echo "</div>";

        echo "<div class='config-option'>";
        echo "<h4>Scenario E: Refresh Target NBs Only</h4>";
        echo "<p>Regenerate NB-8 to NB-15 + synthesis (~2 min)</p>";
        echo "<a href='?runid={$runid}&action=update&config_type=refresh_target' class='btn btn-warning'>Apply Refresh Target Config</a>";
        echo "</div>";

        // Step 3: Verification info
        echo "<hr>";
        echo "<h3>Step 3: Execute the Run & Verify Results</h3>";
        echo "<ol>";
        echo "<li>After setting the config above, execute the run via the dashboard UI</li>";
        echo "<li>Check the sections below to verify the refresh_config was applied</li>";
        echo "</ol>";

        // Show diagnostics for this run
        echo "<h4>Diagnostics for Run {$runid}</h4>";
        $diagnostics = $DB->get_records('local_ci_diagnostics',
            ['runid' => $runid],
            'timecreated DESC',
            '*',
            0,
            20
        );

        if ($diagnostics) {
            echo "<table>";
            echo "<tr><th>Time</th><th>Metric</th><th>Severity</th><th>Message</th></tr>";
            foreach ($diagnostics as $diag) {
                $severity_class = $diag->severity === 'error' ? 'error' : ($diag->severity === 'warning' ? 'warning' : 'info');
                echo "<tr>";
                echo "<td>" . date('Y-m-d H:i:s', $diag->timecreated) . "</td>";
                echo "<td>{$diag->metric}</td>";
                echo "<td class='{$severity_class}'>{$diag->severity}</td>";
                echo "<td>" . htmlspecialchars(substr($diag->message, 0, 100)) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='info'>No diagnostics yet for this run</p>";
        }

        // Show telemetry for this run
        echo "<h4>Telemetry for Run {$runid}</h4>";
        $telemetry = $DB->get_records('local_ci_telemetry',
            ['runid' => $runid],
            'timecreated DESC',
            '*',
            0,
            20
        );

        if ($telemetry) {
            echo "<table>";
            echo "<tr><th>Time</th><th>Metric Key</th><th>Metric Value</th></tr>";
            foreach ($telemetry as $telem) {
                echo "<tr>";
                echo "<td>" . date('Y-m-d H:i:s', $telem->timecreated) . "</td>";
                echo "<td>{$telem->metrickey}</td>";
                echo "<td>{$telem->metricvalue}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='info'>No telemetry yet for this run</p>";
        }

        // Refresh button
        echo "<p><a href='?runid={$runid}' class='btn btn-primary'>🔄 Refresh This Page</a></p>";
    }

} else {
    // Show list of recent runs
    echo "<p>Select a run to test refresh_config functionality:</p>";

    $recent_runs = $DB->get_records('local_ci_run', null, 'id DESC', '*', 0, 20);

    if ($recent_runs) {
        echo "<table>";
        echo "<tr><th>Run ID</th><th>Status</th><th>Company</th><th>Target</th><th>Created</th><th>Action</th></tr>";
        foreach ($recent_runs as $run) {
            echo "<tr>";
            echo "<td>#{$run->id}</td>";
            echo "<td>{$run->status}</td>";
            echo "<td>{$run->companyid}</td>";
            echo "<td>{$run->targetcompanyid}</td>";
            echo "<td>" . date('Y-m-d H:i:s', $run->timecreated) . "</td>";
            echo "<td><a href='?runid={$run->id}' class='btn btn-primary'>Select</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>No runs found in database</p>";
    }
}

echo "</div>";

echo "</body></html>";
