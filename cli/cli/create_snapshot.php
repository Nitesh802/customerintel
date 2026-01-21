#!/usr/bin/env php
<?php
/**
 * CLI script to manually create a snapshot for a run
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Bootstrap Moodle
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/versioning_service.php');

use local_customerintel\services\versioning_service;

// CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'runid' => null,
        'companyid' => null,
        'force' => false,
        'verbose' => false
    ],
    ['h' => 'help', 'r' => 'runid', 'c' => 'companyid', 'f' => 'force', 'v' => 'verbose']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Create Snapshot CLI Utility

This script creates a snapshot for a completed run and optionally computes diffs.

Usage:
  php create_snapshot.php --runid=ID [OPTIONS]
  php create_snapshot.php --companyid=ID [OPTIONS]

Options:
  -h, --help          Show this help message
  -r, --runid=ID      Create snapshot for specific run ID
  -c, --companyid=ID  Create snapshot for latest run of company
  -f, --force         Force creation even if snapshot exists
  -v, --verbose       Show detailed output

Examples:
  # Create snapshot for run 123
  php create_snapshot.php --runid=123

  # Create snapshot for latest run of company 456
  php create_snapshot.php --companyid=456

  # Force recreation with verbose output
  php create_snapshot.php --runid=123 --force --verbose

";
    exit(0);
}

// Validate input
if (empty($options['runid']) && empty($options['companyid'])) {
    cli_error("Either --runid or --companyid must be specified. Use --help for usage information.");
}

try {
    $versioningservice = new versioning_service();
    
    // Determine run ID
    $runid = null;
    
    if (!empty($options['runid'])) {
        $runid = intval($options['runid']);
        
        // Verify run exists
        if (!$DB->record_exists('local_ci_run', ['id' => $runid])) {
            cli_error("Run ID $runid not found.");
        }
        
    } else if (!empty($options['companyid'])) {
        $companyid = intval($options['companyid']);
        
        // Get latest completed run for company
        $sql = "SELECT * FROM {local_ci_run} 
                WHERE companyid = :companyid 
                AND status = 'completed'
                ORDER BY timecompleted DESC 
                LIMIT 1";
        
        $run = $DB->get_record_sql($sql, ['companyid' => $companyid]);
        
        if (!$run) {
            cli_error("No completed runs found for company ID $companyid.");
        }
        
        $runid = $run->id;
        
        if ($options['verbose']) {
            cli_writeln("Found latest run: $runid (completed " . userdate($run->timecompleted) . ")");
        }
    }
    
    // Check if snapshot already exists
    if (!$options['force']) {
        $existing = $DB->get_record('local_ci_snapshot', ['runid' => $runid]);
        if ($existing) {
            cli_writeln("Snapshot already exists for run $runid (ID: {$existing->id})");
            cli_writeln("Use --force to recreate snapshot.");
            exit(0);
        }
    }
    
    // Get run details for display
    $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
    $company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
    
    cli_writeln("=== Creating Snapshot ===");
    cli_writeln("Run ID: $runid");
    cli_writeln("Company: {$company->name} (ID: {$company->id})");
    cli_writeln("Run Status: {$run->status}");
    
    if ($run->status !== 'completed') {
        cli_writeln("WARNING: Run is not completed (status: {$run->status})");
        
        if (!$options['force']) {
            cli_writeln("Use --force to create snapshot anyway.");
            exit(1);
        }
    }
    
    // Create snapshot
    $starttime = microtime(true);
    
    if ($options['verbose']) {
        cli_writeln("\nBuilding snapshot data...");
    }
    
    $snapshotid = $versioningservice->create_snapshot($runid);
    
    $duration = round((microtime(true) - $starttime) * 1000, 2);
    
    cli_writeln("\n✓ Snapshot created successfully!");
    cli_writeln("  Snapshot ID: $snapshotid");
    cli_writeln("  Duration: {$duration}ms");
    
    // Get snapshot details
    $snapshot = $DB->get_record('local_ci_snapshot', ['id' => $snapshotid]);
    $size = strlen($snapshot->snapshotjson) / 1024;
    
    cli_writeln("  Size: " . round($size, 2) . " KB");
    
    // Check for diff creation
    $diff = $DB->get_record_select('local_ci_diff', 
        'tosnapshotid = :toid', 
        ['toid' => $snapshotid],
        '*',
        IGNORE_MULTIPLE
    );
    
    if ($diff) {
        cli_writeln("\n✓ Diff computed with previous snapshot");
        cli_writeln("  From Snapshot: {$diff->fromsnapshotid}");
        cli_writeln("  To Snapshot: {$diff->tosnapshotid}");
        
        if ($options['verbose']) {
            // Display diff summary
            $diffdata = json_decode($diff->diffjson, true);
            $changecount = 0;
            
            foreach ($diffdata['nb_diffs'] ?? [] as $nbdiff) {
                $changecount += count($nbdiff['added'] ?? []);
                $changecount += count($nbdiff['changed'] ?? []);
                $changecount += count($nbdiff['removed'] ?? []);
            }
            
            cli_writeln("  Total Changes: $changecount");
            cli_writeln("  Affected NBs: " . count($diffdata['nb_diffs'] ?? []));
        }
    } else {
        cli_writeln("\n(No previous snapshot found for diff computation)");
    }
    
    // Display telemetry if verbose
    if ($options['verbose']) {
        cli_writeln("\n=== Telemetry ===");
        
        $telemetry = $DB->get_records('local_ci_telemetry', [
            'runid' => $runid,
            'metrickey' => 'snapshot_creation_duration_ms'
        ], 'timecreated DESC', '*', 0, 1);
        
        if ($telemetry) {
            $record = reset($telemetry);
            cli_writeln("Creation Duration: {$record->metricvaluenum}ms");
        }
        
        $telemetry = $DB->get_records('local_ci_telemetry', [
            'runid' => $runid,
            'metrickey' => 'snapshot_size_kb'
        ], 'timecreated DESC', '*', 0, 1);
        
        if ($telemetry) {
            $record = reset($telemetry);
            cli_writeln("Snapshot Size: {$record->metricvaluenum} KB");
        }
    }
    
    cli_writeln("\n✓ Operation completed successfully!");
    exit(0);
    
} catch (Exception $e) {
    cli_error("Error creating snapshot: " . $e->getMessage());
}