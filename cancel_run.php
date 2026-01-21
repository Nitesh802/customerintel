<?php
// Cancel a stuck run
require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/customerintel:manage', context_system::instance());

global $DB;

header('Content-Type: text/plain');

$run_id = 276;

$run = $DB->get_record('local_ci_run', ['id' => $run_id]);

if ($run) {
    echo "Run {$run_id} current status: {$run->status}\n";

    if ($run->status === 'processing' || $run->status === 'pending') {
        $DB->update_record('local_ci_run', (object)[
            'id' => $run_id,
            'status' => 'failed',
            'timemodified' => time()
        ]);
        echo "Run {$run_id} marked as FAILED\n";
    } else {
        echo "Run is already {$run->status} - no action needed\n";
    }
} else {
    echo "Run {$run_id} not found\n";
}
?>
