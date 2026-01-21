<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
global $CFG, $DB;
$plugin = $DB->get_record('config_plugins', ['plugin' => 'local_customerintel']);
echo "Customer Intelligence Dashboard Manual Check\n";
echo "Moodle path: $CFG->dirroot\n";
echo "Plugin version: v1.0.1\n";
echo "Site URL: " . $CFG->wwwroot . "\n";
echo "Checking core tables...\n";
$required = ['local_ci_company','local_ci_source','local_ci_run','local_ci_nb_result','local_ci_snapshot','local_ci_diff'];
foreach ($required as $t) {
    if ($DB->get_manager()->table_exists($t)) {
        echo "OK: $t\n";
    } else {
        echo "ERROR: $t missing\n";
    }
}
echo "Manual check complete.\n";
exit(0);