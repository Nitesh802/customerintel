<?php
require_once(__DIR__ . '/../../../config.php');
require_login();
global $DB;
echo "Running CustomerIntel DB Smoke Test...\n";
$tables = ['local_ci_company','local_ci_source','local_ci_run','local_ci_nb_result','local_ci_snapshot','local_ci_diff','local_ci_comparison','local_ci_telemetry'];
foreach ($tables as $table) {
    if (!$DB->get_manager()->table_exists($table)) {
        echo "Missing table: $table\n";
        exit(1);
    }
}
echo "All tables exist.\n";
$record = $DB->get_record_sql('SELECT * FROM {local_ci_run} ORDER BY id DESC', null, IGNORE_MULTIPLE);
if ($record) {
    echo "Sample run record found (ID {$record->id})\n";
} else {
    echo "No runs found but table accessible.\n";
}
echo "DB OK\n";
exit(0);