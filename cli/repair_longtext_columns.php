<?php
/**
 * Repair LONGTEXT Columns CLI Script
 * 
 * Verifies and repairs database schema to ensure all large text fields 
 * are properly set to LONGTEXT to support large AI payloads.
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Allow running from CLI or web browser for debugging
if (php_sapi_name() === 'cli') {
    define('CLI_SCRIPT', true);
    require(__DIR__ . '/../../../config.php');
    require_once($CFG->libdir . '/clilib.php');
} else {
    // Web browser access for debugging
    require(__DIR__ . '/../../../config.php');
    require_once($CFG->libdir . '/adminlib.php');
    
    // Require admin login for web access
    require_login();
    require_capability('moodle/site:config', context_system::instance());
    
    // Set content type for proper display
    header('Content-Type: text/plain; charset=utf-8');
}

// Load the shared repair function from upgrade.php
require_once(__DIR__ . '/../db/upgrade.php');

/**
 * Column repair utility class
 */
class longtext_column_repair {
    
    /** @var array Fields that should be LONGTEXT */
    const LONGTEXT_FIELDS = [
        'local_ci_nb_result' => ['jsonpayload', 'citations'],
        'local_ci_snapshot' => ['snapshotjson'],
        'local_ci_diff' => ['diffjson'],
        'local_ci_comparison' => ['comparisonjson'],
        'local_ci_log' => ['message']
    ];
    
    /** @var object Database manager */
    private $dbman;
    
    /** @var object Database connection */
    private $db;
    
    /** @var array Results of repair operations */
    private $results = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
        $this->dbman = $DB->get_manager();
    }
    
    /**
     * Main repair function
     * 
     * @param bool $dry_run If true, only check without making changes
     * @param bool $quick_repair If true, use shared upgrade logic
     * @return array Results of the repair operation
     */
    public function repair_columns($dry_run = false, $quick_repair = false) {
        $this->print_header();
        
        echo "CustomerIntel LONGTEXT Column Repair Tool\n";
        echo "==========================================\n\n";
        
        if ($dry_run) {
            echo "DRY RUN MODE - No changes will be made\n\n";
        }
        
        if ($quick_repair && !$dry_run) {
            echo "QUICK REPAIR MODE - Using shared upgrade logic\n\n";
            return $this->quick_repair();
        }
        
        // Print table header
        printf("%-25s %-20s %-15s %-15s %-10s\n", 
            "Table", "Column", "Current Type", "Required Type", "Status");
        echo str_repeat("-", 85) . "\n";
        
        $total_repairs = 0;
        $total_errors = 0;
        
        foreach (self::LONGTEXT_FIELDS as $table_name => $columns) {
            foreach ($columns as $column_name) {
                $result = $this->check_and_repair_column($table_name, $column_name, $dry_run);
                $this->results[] = $result;
                
                // Print result row
                printf("%-25s %-20s %-15s %-15s %-10s\n",
                    $table_name,
                    $column_name,
                    $result['current_type'],
                    'LONGTEXT',
                    $result['status']
                );
                
                if ($result['repaired']) {
                    $total_repairs++;
                }
                if ($result['error']) {
                    $total_errors++;
                }
            }
        }
        
        echo str_repeat("-", 85) . "\n";
        echo "\nSummary:\n";
        echo "- Total columns checked: " . count($this->results) . "\n";
        echo "- Columns repaired: {$total_repairs}\n";
        echo "- Errors encountered: {$total_errors}\n\n";
        
        // Log results to database
        $this->log_results($dry_run);
        
        if (!$dry_run && $total_repairs > 0) {
            echo "✓ Database schema successfully updated!\n";
            echo "✓ CustomerIntel can now store large AI payloads without errors.\n\n";
        } else if ($dry_run) {
            echo "Re-run without --dry-run to apply changes.\n\n";
        } else {
            echo "✓ All columns are already properly configured.\n\n";
        }
        
        return $this->results;
    }
    
    /**
     * Quick repair using shared upgrade logic
     * 
     * @return array Results of the repair operation
     */
    public function quick_repair() {
        echo "Executing quick repair using shared upgrade logic...\n\n";
        
        $start_time = microtime(true);
        
        // Use the shared repair function from upgrade.php
        $results = xmldb_local_customerintel_repair_longtext_columns($this->dbman);
        
        $duration = round((microtime(true) - $start_time) * 1000, 2);
        
        // Print results
        printf("%-25s %-20s %-10s\n", "Table", "Column", "Status");
        echo str_repeat("-", 55) . "\n";
        
        $repaired_count = 0;
        $error_count = 0;
        
        foreach ($results as $result) {
            $status = 'OK';
            if ($result['error']) {
                $status = 'ERROR';
                $error_count++;
            } else if ($result['repaired']) {
                $status = 'REPAIRED';
                $repaired_count++;
            }
            
            printf("%-25s %-20s %-10s\n",
                $result['table'],
                $result['column'], 
                $status
            );
        }
        
        echo str_repeat("-", 55) . "\n";
        echo "\nQuick Repair Summary:\n";
        echo "- Execution time: {$duration}ms\n";
        echo "- Columns repaired: {$repaired_count}\n";
        echo "- Errors: {$error_count}\n\n";
        
        if ($repaired_count > 0) {
            echo "✓ Database schema successfully updated!\n";
            echo "✓ CustomerIntel can now store large AI payloads without errors.\n\n";
        } else if ($error_count > 0) {
            echo "⚠ Some errors occurred during repair. Check the detailed output above.\n\n";
        } else {
            echo "✓ All columns were already properly configured.\n\n";
        }
        
        // Log to database
        try {
            if (class_exists('\\local_customerintel\\services\\log_service')) {
                $message = "Quick repair completed: {$repaired_count} columns repaired, {$error_count} errors";
                \local_customerintel\services\log_service::info(null, $message);
            }
        } catch (Exception $e) {
            debugging('Failed to log quick repair results: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        return $results;
    }
    
    /**
     * Check and repair a single column
     * 
     * @param string $table_name Table name
     * @param string $column_name Column name  
     * @param bool $dry_run If true, only check without making changes
     * @return array Result of the operation
     */
    private function check_and_repair_column($table_name, $column_name, $dry_run = false) {
        $result = [
            'table' => $table_name,
            'column' => $column_name,
            'current_type' => 'UNKNOWN',
            'needs_repair' => false,
            'repaired' => false,
            'error' => false,
            'error_message' => '',
            'status' => 'ERROR'
        ];
        
        try {
            // Check if table exists
            $table = new xmldb_table($table_name);
            if (!$this->dbman->table_exists($table)) {
                $result['error'] = true;
                $result['error_message'] = 'Table does not exist';
                $result['status'] = 'TABLE_MISSING';
                return $result;
            }
            
            // Check if field exists
            $field = new xmldb_field($column_name);
            if (!$this->dbman->field_exists($table, $field)) {
                $result['error'] = true;
                $result['error_message'] = 'Column does not exist';
                $result['status'] = 'COLUMN_MISSING';
                return $result;
            }
            
            // Get current column type from database
            $current_type = $this->get_column_type($table_name, $column_name);
            $result['current_type'] = $current_type;
            
            // Check if repair is needed
            if (!$this->is_longtext($current_type)) {
                $result['needs_repair'] = true;
                $result['status'] = 'NEEDS_REPAIR';
                
                if (!$dry_run) {
                    // Perform the repair
                    $this->alter_column_to_longtext($table_name, $column_name);
                    $result['repaired'] = true;
                    $result['status'] = 'REPAIRED';
                }
            } else {
                $result['status'] = 'OK';
            }
            
        } catch (Exception $e) {
            $result['error'] = true;
            $result['error_message'] = $e->getMessage();
            $result['status'] = 'ERROR';
        }
        
        return $result;
    }
    
    /**
     * Get the actual column type from the database
     * 
     * @param string $table_name Table name
     * @param string $column_name Column name
     * @return string Column type
     */
    private function get_column_type($table_name, $column_name) {
        global $CFG;
        
        try {
            // Get database type specific query
            $dbtype = $this->db->get_dbfamily();
            
            switch ($dbtype) {
                case 'mysql':
                    $sql = "SHOW COLUMNS FROM {" . $table_name . "} WHERE Field = ?";
                    $result = $this->db->get_record_sql($sql, [$column_name]);
                    return $result ? strtoupper($result->type) : 'UNKNOWN';
                    
                case 'postgres':
                    $sql = "SELECT data_type, character_maximum_length 
                           FROM information_schema.columns 
                           WHERE table_name = ? AND column_name = ?";
                    $result = $this->db->get_record_sql($sql, [$table_name, $column_name]);
                    if ($result) {
                        return strtoupper($result->data_type) . 
                               ($result->character_maximum_length ? "({$result->character_maximum_length})" : '');
                    }
                    return 'UNKNOWN';
                    
                default:
                    // For other databases, use a generic approach
                    return 'TEXT'; // Assume it needs repair
            }
        } catch (Exception $e) {
            return 'ERROR: ' . $e->getMessage();
        }
    }
    
    /**
     * Check if a column type is equivalent to LONGTEXT
     * 
     * @param string $type Column type
     * @return bool True if type supports large text
     */
    private function is_longtext($type) {
        $type = strtoupper($type);
        
        // MySQL LONGTEXT variations
        if (strpos($type, 'LONGTEXT') !== false) {
            return true;
        }
        
        // PostgreSQL text (unlimited length)
        if ($type === 'TEXT') {
            return true;
        }
        
        // PostgreSQL character varying without length limit
        if (strpos($type, 'CHARACTER VARYING') !== false && strpos($type, '(') === false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Alter a column to LONGTEXT using Moodle XMLDB
     * 
     * @param string $table_name Table name
     * @param string $column_name Column name
     * @throws Exception If alteration fails
     */
    private function alter_column_to_longtext($table_name, $column_name) {
        $table = new xmldb_table($table_name);
        
        // Define the field as LONGTEXT
        $field = new xmldb_field($column_name, XMLDB_TYPE_TEXT, 'long', null, null, null, null);
        
        // Set the field position (required for some operations)
        if ($table_name === 'local_ci_nb_result') {
            if ($column_name === 'jsonpayload') {
                $field->setPrevious(new xmldb_field('nbcode'));
            } else if ($column_name === 'citations') {
                $field->setPrevious(new xmldb_field('jsonpayload'));
            }
        }
        
        // Perform the alteration
        $this->dbman->change_field_precision($table, $field);
    }
    
    /**
     * Log repair results to the database
     * 
     * @param bool $dry_run Whether this was a dry run
     */
    private function log_results($dry_run) {
        try {
            $summary = [
                'operation' => $dry_run ? 'column_check' : 'column_repair',
                'timestamp' => date('Y-m-d H:i:s'),
                'total_columns' => count($this->results),
                'columns_repaired' => count(array_filter($this->results, function($r) { return $r['repaired']; })),
                'errors' => count(array_filter($this->results, function($r) { return $r['error']; })),
                'details' => $this->results
            ];
            
            // Use log_service if available, otherwise fallback to debugging
            if (class_exists('\\local_customerintel\\services\\log_service')) {
                $message = ($dry_run ? 'Column check' : 'Column repair') . ' completed: ' . 
                          $summary['columns_repaired'] . ' columns repaired, ' . 
                          $summary['errors'] . ' errors';
                \local_customerintel\services\log_service::info(null, $message);
            } else {
                debugging('CustomerIntel column repair: ' . json_encode($summary), DEBUG_DEVELOPER);
            }
            
        } catch (Exception $e) {
            debugging('Failed to log repair results: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Print script header
     */
    private function print_header() {
        echo "\n";
        echo "CustomerIntel Database Schema Repair\n";
        echo "====================================\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        echo "Moodle Version: " . get_config('version') . "\n";
        echo "Plugin Version: " . get_config('local_customerintel', 'version') . "\n\n";
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    // CLI execution
    list($options, $unrecognized) = cli_get_params([
        'help' => false,
        'dry-run' => false,
        'quick' => false
    ], [
        'h' => 'help',
        'd' => 'dry-run',
        'q' => 'quick'
    ]);
    
    if ($options['help']) {
        echo "CustomerIntel LONGTEXT Column Repair Tool\n\n";
        echo "Usage: php repair_longtext_columns.php [options]\n\n";
        echo "Options:\n";
        echo "  -h, --help     Show this help message\n";
        echo "  -d, --dry-run  Check columns without making changes\n";
        echo "  -q, --quick    Use fast repair mode (shared upgrade logic)\n\n";
        echo "Examples:\n";
        echo "  php repair_longtext_columns.php --dry-run    # Check only\n";
        echo "  php repair_longtext_columns.php --quick     # Fast repair\n";
        echo "  php repair_longtext_columns.php             # Full repair\n\n";
        echo "This script verifies and repairs database columns to support large AI payloads.\n";
        exit(0);
    }
    
    $repair = new longtext_column_repair();
    $results = $repair->repair_columns($options['dry-run'], $options['quick']);
    
} else {
    // Web browser execution
    echo "Accessing via web browser...\n\n";
    
    // Check if quick parameter is provided via GET
    $quick_mode = isset($_GET['quick']) && $_GET['quick'] == '1';
    
    $repair = new longtext_column_repair();
    $results = $repair->repair_columns(false, $quick_mode); // Always perform repairs via web
    
    echo "\n";
    echo "Usage via web browser:\n";
    echo "- Full repair: " . $_SERVER['REQUEST_URI'] . "\n";
    echo "- Quick repair: " . $_SERVER['REQUEST_URI'] . "?quick=1\n\n";
    echo "Note: For production use, run this script via CLI:\n";
    echo "php " . __DIR__ . "/repair_longtext_columns.php --quick\n\n";
}