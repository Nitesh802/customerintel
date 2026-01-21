<?php
/**
 * CLI script to test job queue functionality
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/job_queue.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/cost_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');

// Get CLI options
list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'mode' => 'queue',
    'company' => null,
    'target' => null,
    'runid' => null,
    'simulate-failure' => false,
    'force-refresh' => false,
    'show-progress' => false,
    'cleanup' => false
], [
    'h' => 'help',
    'm' => 'mode',
    'c' => 'company',
    't' => 'target',
    'r' => 'runid',
    'f' => 'simulate-failure',
    'x' => 'force-refresh',
    'p' => 'show-progress',
    'l' => 'cleanup'
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "
Test Job Queue functionality for Customer Intel

Usage:
    php test_job_queue.php [OPTIONS]

Options:
    -h, --help              Print this help
    -m, --mode=MODE         Test mode: queue|execute|status|cancel|stats|cleanup (default: queue)
    -c, --company=ID        Company ID for queue/execute
    -t, --target=ID         Target company ID for comparison
    -r, --runid=ID          Run ID for execute/status/cancel
    -f, --simulate-failure  Simulate a failure for retry testing
    -x, --force-refresh     Force fresh data collection (no reuse)
    -p, --show-progress     Show progress updates
    -l, --cleanup           Clean up old runs (with cleanup mode)

Examples:
    # Queue a new run
    php test_job_queue.php --mode=queue --company=1
    
    # Queue a comparison run
    php test_job_queue.php --mode=queue --company=1 --target=2
    
    # Execute a specific run
    php test_job_queue.php --mode=execute --runid=5
    
    # Check run status
    php test_job_queue.php --mode=status --runid=5
    
    # Show queue statistics
    php test_job_queue.php --mode=stats
    
    # Cancel a queued run
    php test_job_queue.php --mode=cancel --runid=5
    
    # Test failure and retry
    php test_job_queue.php --mode=queue --company=1 --simulate-failure
    
    # Clean up old runs
    php test_job_queue.php --mode=cleanup
    
";
    echo $help;
    exit(0);
}

// Initialize services
$jobqueue = new \local_customerintel\services\job_queue();
$costservice = new \local_customerintel\services\cost_service();
$companyservice = new \local_customerintel\services\company_service();

// Execute based on mode
switch ($options['mode']) {
    case 'queue':
        test_queue_run($options);
        break;
        
    case 'execute':
        test_execute_run($options);
        break;
        
    case 'status':
        test_run_status($options);
        break;
        
    case 'cancel':
        test_cancel_run($options);
        break;
        
    case 'stats':
        show_queue_stats();
        break;
        
    case 'cleanup':
        test_cleanup();
        break;
        
    default:
        cli_error("Unknown mode: {$options['mode']}");
}

/**
 * Test queuing a new run
 */
function test_queue_run($options) {
    global $DB, $jobqueue, $costservice, $companyservice;
    
    if (!$options['company']) {
        // Create a test company if none specified
        $company = $DB->get_record_sql("SELECT * FROM {local_ci_company} LIMIT 1");
        if (!$company) {
            cli_heading("Creating test company");
            $companyid = $companyservice->create_company([
                'name' => 'Test Company ' . uniqid(),
                'ticker' => 'TEST',
                'type' => 'customer',
                'website' => 'https://test.example.com',
                'sector' => 'Technology'
            ]);
            echo "Created company ID: $companyid\n";
        } else {
            $companyid = $company->id;
            echo "Using existing company: {$company->name} (ID: $companyid)\n";
        }
    } else {
        $companyid = $options['company'];
        $company = $DB->get_record('local_ci_company', ['id' => $companyid]);
        if (!$company) {
            cli_error("Company ID $companyid not found");
        }
        echo "Using company: {$company->name}\n";
    }
    
    $targetid = $options['target'] ?: null;
    if ($targetid) {
        $target = $DB->get_record('local_ci_company', ['id' => $targetid]);
        if (!$target) {
            cli_error("Target company ID $targetid not found");
        }
        echo "Target company: {$target->name}\n";
    }
    
    cli_heading("Estimating cost");
    
    // Get cost estimate
    $estimate = $costservice->estimate_cost($companyid, $targetid, $options['force-refresh']);
    
    echo "Estimated cost: $" . number_format($estimate['total_cost'], 2) . "\n";
    echo "Estimated tokens: " . number_format($estimate['total_tokens']) . "\n";
    echo "Provider: {$estimate['provider']}\n";
    
    if (!empty($estimate['reuse_savings'])) {
        echo "Reuse savings: $" . number_format($estimate['reuse_savings'], 2) . "\n";
    }
    
    if (!empty($estimate['warnings'])) {
        cli_heading("Warnings");
        foreach ($estimate['warnings'] as $warning) {
            echo "- [{$warning['type']}] {$warning['message']}\n";
        }
    }
    
    if (!$estimate['can_proceed']) {
        cli_error("Cannot proceed: Cost exceeds hard limit");
    }
    
    cli_heading("Queueing run");
    
    try {
        // Queue the run
        $runid = $jobqueue->queue_run(
            $companyid,
            $targetid,
            get_admin()->id,
            [
                'mode' => $targetid ? 'comparison' : 'full',
                'force_refresh' => $options['force-refresh']
            ]
        );
        
        echo "Successfully queued run ID: $runid\n";
        
        // Show initial status
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        echo "Status: {$run->status}\n";
        echo "Mode: {$run->mode}\n";
        
        if ($options['simulate-failure']) {
            echo "\n";
            cli_heading("Simulating failure");
            echo "To test retry behavior, the run will be marked for failure simulation.\n";
            $DB->set_field('local_ci_run', 'error', json_encode(['simulate_failure' => true]), ['id' => $runid]);
        }
        
        echo "\n";
        echo "To execute this run, use:\n";
        echo "  php test_job_queue.php --mode=execute --runid=$runid\n";
        echo "\n";
        echo "To check status, use:\n";
        echo "  php test_job_queue.php --mode=status --runid=$runid\n";
        
    } catch (Exception $e) {
        cli_error("Failed to queue run: " . $e->getMessage());
    }
}

/**
 * Test executing a run
 */
function test_execute_run($options) {
    global $DB, $jobqueue;
    
    if (!$options['runid']) {
        cli_error("Run ID required for execute mode");
    }
    
    $runid = $options['runid'];
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);
    
    if (!$run) {
        cli_error("Run ID $runid not found");
    }
    
    cli_heading("Executing Run $runid");
    echo "Company ID: {$run->companyid}\n";
    echo "Mode: {$run->mode}\n";
    echo "Status: {$run->status}\n";
    echo "Estimated cost: $" . number_format($run->estcost, 2) . "\n";
    echo "\n";
    
    // Check for failure simulation
    if ($run->error) {
        $error = json_decode($run->error, true);
        if (!empty($error['simulate_failure'])) {
            echo "Note: This run is marked for failure simulation\n\n";
        }
    }
    
    try {
        if ($options['show-progress']) {
            // Start progress monitoring in background
            $pid = pcntl_fork();
            if ($pid == 0) {
                // Child process - monitor progress
                monitor_progress($runid);
                exit(0);
            }
        }
        
        // Execute the run
        echo "Starting execution...\n";
        $starttime = microtime(true);
        
        $success = $jobqueue->execute_run($runid);
        
        $duration = microtime(true) - $starttime;
        
        if ($options['show-progress'] && isset($pid) && $pid > 0) {
            // Stop progress monitoring
            posix_kill($pid, SIGTERM);
            pcntl_wait($status);
        }
        
        echo "\n";
        if ($success) {
            cli_heading("Run completed successfully");
        } else {
            cli_heading("Run failed");
        }
        
        echo "Duration: " . round($duration, 2) . " seconds\n";
        
        // Show final metrics
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        echo "Actual cost: $" . number_format($run->actualcost, 2) . "\n";
        echo "Actual tokens: " . number_format($run->actualtokens) . "\n";
        
        $variance = $costservice->calculate_variance($run->estcost, $run->actualcost);
        echo "Cost variance: " . sprintf('%+.1f%%', $variance) . "\n";
        
    } catch (Exception $e) {
        cli_error("Execution failed: " . $e->getMessage());
    }
}

/**
 * Monitor progress
 */
function monitor_progress($runid) {
    global $jobqueue;
    
    while (true) {
        $progress = $jobqueue->get_run_progress($runid);
        
        $bar = str_repeat('=', floor($progress['percentage'] / 2));
        $space = str_repeat(' ', 50 - strlen($bar));
        
        echo "\r[{$bar}{$space}] {$progress['percentage']}% ";
        echo "({$progress['completed_nbs']}/{$progress['total_nbs']} NBs)";
        
        if ($progress['current_nb']) {
            echo " - Current: {$progress['current_nb']}";
        }
        
        if ($progress['status'] !== 'running') {
            echo "\n";
            break;
        }
        
        sleep(2);
    }
}

/**
 * Test run status
 */
function test_run_status($options) {
    global $DB, $jobqueue;
    
    if (!$options['runid']) {
        cli_error("Run ID required for status mode");
    }
    
    $runid = $options['runid'];
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);
    
    if (!$run) {
        cli_error("Run ID $runid not found");
    }
    
    cli_heading("Run Status");
    
    echo "Run ID: {$run->id}\n";
    echo "Company ID: {$run->companyid}\n";
    echo "Status: {$run->status}\n";
    echo "Mode: {$run->mode}\n";
    echo "Created: " . userdate($run->timecreated) . "\n";
    
    if ($run->timestarted) {
        echo "Started: " . userdate($run->timestarted) . "\n";
    }
    
    if ($run->timecompleted) {
        echo "Completed: " . userdate($run->timecompleted) . "\n";
        $duration = $run->timecompleted - $run->timestarted;
        echo "Duration: " . gmdate('H:i:s', $duration) . "\n";
    }
    
    echo "\n";
    cli_heading("Cost Information");
    echo "Estimated cost: $" . number_format($run->estcost, 2) . "\n";
    echo "Estimated tokens: " . number_format($run->esttokens) . "\n";
    
    if ($run->actualcost) {
        echo "Actual cost: $" . number_format($run->actualcost, 2) . "\n";
        echo "Actual tokens: " . number_format($run->actualtokens) . "\n";
        
        $variance = $costservice->calculate_variance($run->estcost, $run->actualcost);
        echo "Cost variance: " . sprintf('%+.1f%%', $variance) . "\n";
    }
    
    if ($run->status === 'running') {
        echo "\n";
        cli_heading("Progress");
        $progress = $jobqueue->get_run_progress($runid);
        
        echo "Completed NBs: {$progress['completed_nbs']}/{$progress['total_nbs']}\n";
        echo "Progress: {$progress['percentage']}%\n";
        
        if ($progress['current_nb']) {
            echo "Current NB: {$progress['current_nb']}\n";
        }
        
        if ($progress['eta']) {
            echo "ETA: " . userdate($progress['eta']) . "\n";
        }
    }
    
    if ($run->error) {
        echo "\n";
        cli_heading("Error Details");
        $error = json_decode($run->error, true);
        echo "Message: " . ($error['message'] ?? 'Unknown error') . "\n";
        if (!empty($error['timestamp'])) {
            echo "Time: " . userdate($error['timestamp']) . "\n";
        }
    }
    
    // Show NB results
    $nbresults = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode');
    if ($nbresults) {
        echo "\n";
        cli_heading("NB Results");
        foreach ($nbresults as $result) {
            $status = $result->status === 'completed' ? '✓' : 
                     ($result->status === 'failed' ? '✗' : '○');
            echo "{$status} {$result->nbcode}: {$result->status}";
            if ($result->tokensused) {
                echo " ({$result->tokensused} tokens, {$result->durationms}ms)";
            }
            echo "\n";
        }
    }
}

/**
 * Test cancel run
 */
function test_cancel_run($options) {
    global $jobqueue;
    
    if (!$options['runid']) {
        cli_error("Run ID required for cancel mode");
    }
    
    $runid = $options['runid'];
    
    cli_heading("Cancelling Run $runid");
    
    if ($jobqueue->cancel_run($runid)) {
        echo "Run successfully cancelled\n";
    } else {
        echo "Run could not be cancelled (may already be running or completed)\n";
    }
}

/**
 * Show queue statistics
 */
function show_queue_stats() {
    global $jobqueue;
    
    cli_heading("Queue Statistics");
    
    $stats = $jobqueue->get_queue_stats();
    
    echo "Queued: " . $stats['queued'] . "\n";
    echo "Running: " . $stats['running'] . "\n";
    echo "Retrying: " . $stats['retrying'] . "\n";
    echo "Completed: " . $stats['completed'] . "\n";
    echo "Failed: " . $stats['failed'] . "\n";
    echo "Cancelled: " . $stats['cancelled'] . "\n";
    echo "\n";
    echo "Average wait time: " . gmdate('H:i:s', $stats['avg_wait_time']) . "\n";
    echo "Average execution time: " . gmdate('H:i:s', $stats['avg_execution_time']) . "\n";
}

/**
 * Test cleanup
 */
function test_cleanup() {
    global $jobqueue;
    
    cli_heading("Cleaning up old runs");
    
    $age = 30; // Clean runs older than 30 days
    echo "Cleaning runs older than $age days...\n";
    
    $cleaned = $jobqueue->cleanup_old_runs($age);
    
    echo "Cleaned $cleaned old runs\n";
}

exit(0);