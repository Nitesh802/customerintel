#!/usr/bin/env php
<?php
/**
 * CLI script to display formatted diff between two snapshots
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
        'from' => null,
        'to' => null,
        'companyid' => null,
        'latest' => false,
        'format' => 'text',
        'output' => null,
        'stats' => false
    ],
    ['h' => 'help', 'f' => 'from', 't' => 'to', 'c' => 'companyid', 'l' => 'latest', 'o' => 'output', 's' => 'stats']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Show Diff CLI Utility

This script displays formatted diff between two snapshots.

Usage:
  php show_diff.php --from=ID --to=ID [OPTIONS]
  php show_diff.php --companyid=ID --latest [OPTIONS]

Options:
  -h, --help          Show this help message
  -f, --from=ID       From snapshot ID
  -t, --to=ID         To snapshot ID
  -c, --companyid=ID  Company ID for latest diff
  -l, --latest        Show diff between two most recent snapshots
  --format=FORMAT     Output format: text, json, html (default: text)
  -o, --output=FILE   Write output to file
  -s, --stats         Show statistics only

Examples:
  # Show diff between snapshots 10 and 15
  php show_diff.php --from=10 --to=15

  # Show latest diff for company 5
  php show_diff.php --companyid=5 --latest

  # Export diff as JSON
  php show_diff.php --from=10 --to=15 --format=json --output=diff.json

  # Show diff statistics only
  php show_diff.php --from=10 --to=15 --stats

";
    exit(0);
}

try {
    $versioningservice = new versioning_service();
    
    // Determine snapshots to compare
    $fromsnapshot = null;
    $tosnapshot = null;
    
    if ($options['latest'] && !empty($options['companyid'])) {
        // Get two most recent snapshots for company
        $companyid = intval($options['companyid']);
        
        $sql = "SELECT * FROM {local_ci_snapshot} 
                WHERE companyid = :companyid 
                ORDER BY timecreated DESC 
                LIMIT 2";
        
        $snapshots = $DB->get_records_sql($sql, ['companyid' => $companyid]);
        
        if (count($snapshots) < 2) {
            cli_error("Company $companyid needs at least 2 snapshots for diff comparison.");
        }
        
        $snapshots = array_values($snapshots);
        $tosnapshot = $snapshots[0]->id;
        $fromsnapshot = $snapshots[1]->id;
        
        cli_writeln("Comparing latest snapshots for company $companyid:");
        cli_writeln("  From: Snapshot $fromsnapshot (" . userdate($snapshots[1]->timecreated) . ")");
        cli_writeln("  To:   Snapshot $tosnapshot (" . userdate($snapshots[0]->timecreated) . ")");
        
    } else if (!empty($options['from']) && !empty($options['to'])) {
        $fromsnapshot = intval($options['from']);
        $tosnapshot = intval($options['to']);
        
        // Verify snapshots exist
        if (!$DB->record_exists('local_ci_snapshot', ['id' => $fromsnapshot])) {
            cli_error("Snapshot $fromsnapshot not found.");
        }
        if (!$DB->record_exists('local_ci_snapshot', ['id' => $tosnapshot])) {
            cli_error("Snapshot $tosnapshot not found.");
        }
        
    } else {
        cli_error("Must specify either --from and --to, or --companyid with --latest. Use --help for usage.");
    }
    
    // Check for existing diff
    $diff = $DB->get_record('local_ci_diff', [
        'fromsnapshotid' => $fromsnapshot,
        'tosnapshotid' => $tosnapshot
    ]);
    
    if (!$diff) {
        cli_writeln("\nNo existing diff found. Computing diff...");
        
        // Compute diff
        $diffdata = $versioningservice->compute_diff($fromsnapshot, $tosnapshot);
        
        // Reload diff record
        $diff = $DB->get_record('local_ci_diff', [
            'fromsnapshotid' => $fromsnapshot,
            'tosnapshotid' => $tosnapshot
        ]);
    } else {
        cli_writeln("\nUsing cached diff (created " . userdate($diff->timecreated) . ")");
    }
    
    // Parse diff data
    $diffdata = json_decode($diff->diffjson, true);
    
    // Show statistics if requested
    if ($options['stats']) {
        display_diff_stats($diffdata);
        exit(0);
    }
    
    // Format output based on format option
    $output = '';
    
    switch ($options['format']) {
        case 'json':
            $output = json_encode($diffdata, JSON_PRETTY_PRINT);
            break;
            
        case 'html':
            $output = format_diff_html($diffdata);
            break;
            
        case 'text':
        default:
            $output = $versioningservice->format_diff_display($diffdata);
            break;
    }
    
    // Output to file or console
    if (!empty($options['output'])) {
        $filepath = $options['output'];
        
        // Ensure directory exists
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($filepath, $output);
        cli_writeln("\n✓ Diff written to: $filepath");
        
    } else {
        echo $output;
    }
    
    cli_writeln("\n✓ Operation completed successfully!");
    exit(0);
    
} catch (Exception $e) {
    cli_error("Error showing diff: " . $e->getMessage());
}

/**
 * Display diff statistics
 * 
 * @param array $diffdata Diff data
 */
function display_diff_stats($diffdata) {
    cli_writeln("\n=== DIFF STATISTICS ===");
    cli_writeln("From Snapshot: {$diffdata['from_snapshot_id']}");
    cli_writeln("To Snapshot: {$diffdata['to_snapshot_id']}");
    cli_writeln("Timestamp: " . date('Y-m-d H:i:s', $diffdata['timestamp']));
    cli_writeln("");
    
    $totalnbs = count($diffdata['nb_diffs'] ?? []);
    $totaladded = 0;
    $totalchanged = 0;
    $totalremoved = 0;
    $totalcitationsadded = 0;
    $totalcitationsremoved = 0;
    
    $nbstats = [];
    
    foreach ($diffdata['nb_diffs'] ?? [] as $nbdiff) {
        $added = count($nbdiff['added'] ?? []);
        $changed = count($nbdiff['changed'] ?? []);
        $removed = count($nbdiff['removed'] ?? []);
        $citationsadded = count($nbdiff['citations']['added'] ?? []);
        $citationsremoved = count($nbdiff['citations']['removed'] ?? []);
        
        $totaladded += $added;
        $totalchanged += $changed;
        $totalremoved += $removed;
        $totalcitationsadded += $citationsadded;
        $totalcitationsremoved += $citationsremoved;
        
        if ($added + $changed + $removed + $citationsadded + $citationsremoved > 0) {
            $nbstats[] = sprintf(
                "  %s: +%d ~%d -%d (citations: +%d -%d)",
                $nbdiff['nb_code'],
                $added,
                $changed,
                $removed,
                $citationsadded,
                $citationsremoved
            );
        }
    }
    
    cli_writeln("Summary:");
    cli_writeln("  NBs with changes: $totalnbs");
    cli_writeln("  Total fields added: $totaladded");
    cli_writeln("  Total fields changed: $totalchanged");
    cli_writeln("  Total fields removed: $totalremoved");
    cli_writeln("  Total citations added: $totalcitationsadded");
    cli_writeln("  Total citations removed: $totalcitationsremoved");
    cli_writeln("");
    cli_writeln("Per-NB Changes:");
    
    foreach ($nbstats as $stat) {
        cli_writeln($stat);
    }
}

/**
 * Format diff as HTML
 * 
 * @param array $diffdata Diff data
 * @return string HTML output
 */
function format_diff_html($diffdata) {
    $html = '<html><head><title>Snapshot Diff</title>';
    $html .= '<style>
        body { font-family: monospace; margin: 20px; }
        .header { background: #f0f0f0; padding: 10px; margin-bottom: 20px; }
        .nb-section { margin: 20px 0; border: 1px solid #ddd; padding: 10px; }
        .nb-title { font-weight: bold; font-size: 1.2em; margin-bottom: 10px; }
        .added { background: #d4edda; padding: 5px; margin: 5px 0; }
        .changed { background: #cce5ff; padding: 5px; margin: 5px 0; }
        .removed { background: #f8d7da; padding: 5px; margin: 5px 0; text-decoration: line-through; }
        .from { color: #721c24; }
        .to { color: #004085; }
        .citations { margin-top: 10px; padding: 10px; background: #f9f9f9; }
    </style></head><body>';
    
    $html .= '<div class="header">';
    $html .= '<h1>Snapshot Diff</h1>';
    $html .= '<p>From: ' . $diffdata['from_snapshot_id'] . '</p>';
    $html .= '<p>To: ' . $diffdata['to_snapshot_id'] . '</p>';
    $html .= '<p>Generated: ' . date('Y-m-d H:i:s', $diffdata['timestamp']) . '</p>';
    $html .= '</div>';
    
    foreach ($diffdata['nb_diffs'] ?? [] as $nbdiff) {
        $html .= '<div class="nb-section">';
        $html .= '<div class="nb-title">' . $nbdiff['nb_code'] . '</div>';
        
        if (!empty($nbdiff['added'])) {
            $html .= '<div class="added"><strong>ADDED:</strong><br>';
            foreach ($nbdiff['added'] as $field => $value) {
                $html .= htmlspecialchars($field) . ': ' . htmlspecialchars(json_encode($value)) . '<br>';
            }
            $html .= '</div>';
        }
        
        if (!empty($nbdiff['changed'])) {
            $html .= '<div class="changed"><strong>CHANGED:</strong><br>';
            foreach ($nbdiff['changed'] as $field => $change) {
                $html .= htmlspecialchars($field) . ':<br>';
                $html .= '<span class="from">FROM: ' . htmlspecialchars(json_encode($change['from'])) . '</span><br>';
                $html .= '<span class="to">TO: ' . htmlspecialchars(json_encode($change['to'])) . '</span><br>';
            }
            $html .= '</div>';
        }
        
        if (!empty($nbdiff['removed'])) {
            $html .= '<div class="removed"><strong>REMOVED:</strong><br>';
            foreach ($nbdiff['removed'] as $field => $value) {
                $html .= htmlspecialchars($field) . ': ' . htmlspecialchars(json_encode($value)) . '<br>';
            }
            $html .= '</div>';
        }
        
        if (!empty($nbdiff['citations'])) {
            $html .= '<div class="citations"><strong>CITATION CHANGES:</strong><br>';
            if (!empty($nbdiff['citations']['added'])) {
                $html .= 'Added: ' . implode(', ', $nbdiff['citations']['added']) . '<br>';
            }
            if (!empty($nbdiff['citations']['removed'])) {
                $html .= 'Removed: ' . implode(', ', $nbdiff['citations']['removed']) . '<br>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</body></html>';
    
    return $html;
}