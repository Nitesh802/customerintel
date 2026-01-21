<?php
/**
 * Quick force synthesis config setter
 */

require_once('../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

$runid = optional_param('runid', 122, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

echo "<html><head><title>Set Force Synthesis Config</title>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; color: white; }
.btn-success { background-color: #28a745; }
.btn-secondary { background-color: #6c757d; }
</style></head><body>";

echo "<h2>Set Force Synthesis Config for Run {$runid}</h2>";

if (!$confirm) {
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);

    echo "<p><strong>Current Config:</strong></p>";
    echo "<pre>" . htmlspecialchars($run->refresh_config) . "</pre>";

    echo "<p><strong>Will set to:</strong></p>";
    $new_config = json_encode([
        'force_nb_refresh' => false,
        'force_synthesis_refresh' => true,
        'refresh_source' => false,
        'refresh_target' => false
    ]);
    echo "<pre>" . htmlspecialchars($new_config) . "</pre>";

    echo "<p><a href='?runid={$runid}&confirm=1' class='btn btn-success'>✅ Apply Config</a> ";
    echo "<a href='test_m1t4_production.php?runid={$runid}' class='btn btn-secondary'>Cancel</a></p>";

} else {
    $new_config = json_encode([
        'force_nb_refresh' => false,
        'force_synthesis_refresh' => true,
        'refresh_source' => false,
        'refresh_target' => false
    ]);

    $DB->set_field('local_ci_run', 'refresh_config', $new_config, ['id' => $runid]);

    echo "<p style='color:green;'>✅ Successfully updated Run {$runid}!</p>";
    echo "<p><strong>New Config:</strong></p>";
    echo "<pre>" . htmlspecialchars($new_config) . "</pre>";

    echo "<p><a href='quick_check_config.php' class='btn btn-success'>Verify Config</a> ";
    echo "<a href='execute_run.php?runid={$runid}' class='btn btn-success'>Execute Run {$runid}</a></p>";
}

echo "</body></html>";
