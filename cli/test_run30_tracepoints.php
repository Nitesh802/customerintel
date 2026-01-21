<?php
/**
 * Diagnostic CLI script to trace synthesis phase execution for runid 30
 * This script helps identify where the synthesis process is freezing
 * 
 * Usage: php cli/test_run30_tracepoints.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/local_customerintel_logger.php');

// CLI setup
cli_heading('CustomerIntel Synthesis Diagnostic - Run 30');

// Initialize logging
$logger = new \local_customerintel\services\local_customerintel_logger();

function trace_log($message, $data = []) {
    global $logger;
    $timestamp = date('Y-m-d H:i:s');
    $trace_msg = "[TRACEPOINT] {$message}";
    
    // Output to CLI
    echo "{$timestamp} - {$trace_msg}\n";
    if (!empty($data)) {
        echo "  Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
    
    // Log to Moodle logs
    $logger->log($trace_msg, $data);
    
    // Force output
    flush();
}

try {
    $runid = 30;
    
    trace_log("diagnostic_start", ["runid" => $runid, "script" => "test_run30_tracepoints.php"]);
    
    // Verify run exists
    $run_record = $DB->get_record('local_customerintel_runs', ['id' => $runid]);
    if (!$run_record) {
        trace_log("error_no_run", ["runid" => $runid]);
        echo "ERROR: Run ID {$runid} not found in database\n";
        exit(1);
    }
    
    trace_log("run_found", [
        "runid" => $runid, 
        "company" => $run_record->company_source,
        "status" => $run_record->status
    ]);
    
    // Initialize synthesis engine
    trace_log("pre_engine_init");
    $synthesis_engine = new \local_customerintel\services\synthesis_engine();
    trace_log("post_engine_init");
    
    // Start synthesis with force regeneration
    trace_log("pre_build_report", ["force_regenerate" => true]);
    
    $result = $synthesis_engine->build_report($runid, true);
    
    trace_log("post_build_report", [
        "result_keys" => array_keys($result),
        "html_length" => isset($result['html']) ? strlen($result['html']) : 0,
        "json_length" => isset($result['json']) ? strlen($result['json']) : 0
    ]);
    
    trace_log("diagnostic_complete", ["success" => true, "runid" => $runid]);
    echo "\nSynthesis diagnostic completed successfully!\n";
    
} catch (Exception $e) {
    trace_log("error_exception", [
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString()
    ]);
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Throwable $t) {
    trace_log("error_throwable", [
        "message" => $t->getMessage(),
        "file" => $t->getFile(),
        "line" => $t->getLine()
    ]);
    echo "FATAL: " . $t->getMessage() . "\n";
    exit(1);
}
?>