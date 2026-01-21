<?php
/**
 * Quick check of Run 122 config
 */

require_once('../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Admin access required');
}

$run = $DB->get_record('local_ci_run', ['id' => 122], '*');

echo "<html><head><title>Run 122 Config Check</title>";
echo "<style>body { font-family: monospace; padding: 20px; }</style>";
echo "</head><body>";

echo "<h2>Run 122 Current Configuration</h2>";

if ($run) {
    echo "<p><strong>Status:</strong> {$run->status}</p>";
    echo "<p><strong>Refresh Config (raw):</strong></p>";
    echo "<pre>" . htmlspecialchars($run->refresh_config) . "</pre>";

    if ($run->refresh_config) {
        $config = json_decode($run->refresh_config, true);
        echo "<p><strong>Parsed Config:</strong></p>";
        echo "<pre>" . json_encode($config, JSON_PRETTY_PRINT) . "</pre>";
    }
} else {
    echo "<p>Run 122 not found!</p>";
}

echo "</body></html>";
