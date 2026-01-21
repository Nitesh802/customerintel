<?php
/**
 * Database Schema Inspector for CustomerIntel
 * 
 * Inspects and displays the actual database schema for CustomerIntel tables
 * to help diagnose schema-related issues.
 * 
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Bootstrap Moodle
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require admin login and capability
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set content type for proper display
header('Content-Type: text/plain; charset=utf-8');

/**
 * Schema inspector class
 */
class table_schema_inspector {
    
    /** @var array Tables to inspect */
    const TABLES_TO_INSPECT = [
        'local_ci_nb_result',
        'local_ci_snapshot', 
        'local_ci_diff',
        'local_ci_comparison',
        'local_ci_log'
    ];
    
    /** @var object Database manager */
    private $dbman;
    
    /** @var object Database connection */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
        $this->dbman = $DB->get_manager();
    }
    
    /**
     * Inspect all CustomerIntel tables
     */
    public function inspect_all_tables() {
        $this->print_header();
        
        foreach (self::TABLES_TO_INSPECT as $table_name) {
            $this->inspect_table($table_name);
            echo "\n" . str_repeat("=", 80) . "\n\n";
        }
        
        echo "DONE\n";
    }
    
    /**
     * Inspect a specific table
     * 
     * @param string $table_name Table name to inspect
     */
    private function inspect_table($table_name) {
        echo "TABLE {$table_name}\n";
        echo str_repeat("-", strlen("TABLE {$table_name}")) . "\n";
        
        // Check if table exists
        $table = new xmldb_table($table_name);
        if (!$this->dbman->table_exists($table)) {
            echo "ERROR: Table does not exist!\n";
            return;
        }
        
        // Get columns using correct Moodle API
        $columns = $this->db->get_columns($table_name);
        
        if (empty($columns)) {
            echo "ERROR: Could not retrieve column information!\n";
            return;
        }
        
        // Print column information
        echo "COLUMNS:\n";
        printf("%-20s %-20s %-10s %-10s %-15s %-10s\n",
            "Name", "Type", "Length", "Nullable", "Default", "Auto Inc");
        echo str_repeat("-", 90) . "\n";
        
        foreach ($columns as $column_name => $column_info) {
            $type = $this->format_column_type($column_info);
            $length = $this->format_column_length($column_info);
            $nullable = $this->format_nullable($column_info);
            $default = $this->format_default_value($column_info);
            $auto_inc = $this->format_auto_increment($column_info);
            
            printf("%-20s %-20s %-10s %-10s %-15s %-10s\n",
                $column_name, $type, $length, $nullable, $default, $auto_inc);
        }
        
        // Get raw column information for debugging
        echo "\nRAW MOODLE COLUMN OBJECTS:\n";
        foreach ($columns as $col_name => $col_obj) {
            echo "  {$col_name}: " . json_encode($col_obj) . "\n";
        }
        
        echo "\nRAW DATABASE COLUMN INFO:\n";
        $raw_columns = $this->get_raw_column_info($table_name);
        foreach ($raw_columns as $col) {
            echo "  " . json_encode($col) . "\n";
        }
        
        // Print indexes
        echo "\nINDEXES:\n";
        $indexes = $this->get_table_indexes($table_name);
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                echo "  " . $index . "\n";
            }
        } else {
            echo "  No indexes found or unable to retrieve index information.\n";
        }
        
        // Print foreign keys
        echo "\nFOREIGN KEYS:\n";
        $foreign_keys = $this->get_table_foreign_keys($table_name);
        if (!empty($foreign_keys)) {
            foreach ($foreign_keys as $fk) {
                echo "  " . $fk . "\n";
            }
        } else {
            echo "  No foreign keys found or unable to retrieve foreign key information.\n";
        }
        
        // Print sample data (first 3 records)
        echo "\nSAMPLE DATA (first 3 records):\n";
        try {
            $records = $this->db->get_records($table_name, null, 'id ASC', '*', 0, 3);
            if (!empty($records)) {
                $count = 0;
                foreach ($records as $record) {
                    $count++;
                    echo "  Record {$count}: " . json_encode($record) . "\n";
                }
            } else {
                echo "  No records found in table.\n";
            }
        } catch (Exception $e) {
            echo "  Error retrieving sample data: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Format column type for display
     * 
     * @param object $column_info Column information from $DB->get_columns()
     * @return string Formatted type
     */
    private function format_column_type($column_info) {
        if (isset($column_info->meta_type)) {
            return strtoupper($column_info->meta_type);
        }
        return 'UNKNOWN';
    }
    
    /**
     * Format column length for display
     * 
     * @param object $column_info Column information from $DB->get_columns()
     * @return string Formatted length
     */
    private function format_column_length($column_info) {
        if (isset($column_info->max_length) && $column_info->max_length > 0) {
            return $column_info->max_length;
        }
        return 'N/A';
    }
    
    /**
     * Format nullable status for display
     * 
     * @param object $column_info Column information from $DB->get_columns()
     * @return string Formatted nullable status
     */
    private function format_nullable($column_info) {
        if (isset($column_info->not_null)) {
            return $column_info->not_null ? 'NOT NULL' : 'NULL';
        }
        return 'UNKNOWN';
    }
    
    /**
     * Format auto increment status for display
     * 
     * @param object $column_info Column information from $DB->get_columns()
     * @return string Formatted auto increment status
     */
    private function format_auto_increment($column_info) {
        if (isset($column_info->auto_increment) && $column_info->auto_increment) {
            return 'YES';
        } else if (isset($column_info->primary_key) && $column_info->primary_key) {
            return 'PK';
        }
        return 'NO';
    }
    
    /**
     * Format default value for display
     * 
     * @param object $column_info Column information from $DB->get_columns()
     * @return string Formatted default value
     */
    private function format_default_value($column_info) {
        if (isset($column_info->default_value)) {
            if ($column_info->default_value === null) {
                return 'NULL';
            } else if ($column_info->default_value === '') {
                return "''";
            } else {
                return "'" . $column_info->default_value . "'";
            }
        }
        return 'N/A';
    }
    
    /**
     * Get raw column information directly from database
     * 
     * @param string $table_name Table name
     * @return array Raw column information
     */
    private function get_raw_column_info($table_name) {
        global $CFG;
        
        try {
            $dbtype = $this->db->get_dbfamily();
            
            switch ($dbtype) {
                case 'mysql':
                    $sql = "SHOW COLUMNS FROM {" . $table_name . "}";
                    return $this->db->get_records_sql($sql);
                    
                case 'postgres':
                    $sql = "SELECT column_name, data_type, character_maximum_length, 
                                  is_nullable, column_default
                           FROM information_schema.columns 
                           WHERE table_name = ? 
                           ORDER BY ordinal_position";
                    return $this->db->get_records_sql($sql, [$table_name]);
                    
                default:
                    return ['Database type not supported for raw column info'];
            }
        } catch (Exception $e) {
            return ['Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get table indexes
     * 
     * @param string $table_name Table name
     * @return array Index information
     */
    private function get_table_indexes($table_name) {
        try {
            $dbtype = $this->db->get_dbfamily();
            
            switch ($dbtype) {
                case 'mysql':
                    $sql = "SHOW INDEXES FROM {" . $table_name . "}";
                    $records = $this->db->get_records_sql($sql);
                    $indexes = [];
                    foreach ($records as $record) {
                        $unique = $record->non_unique == 0 ? 'UNIQUE' : '';
                        $indexes[] = "{$unique} {$record->key_name} ({$record->column_name})";
                    }
                    return $indexes;
                    
                case 'postgres':
                    $sql = "SELECT indexname, indexdef 
                           FROM pg_indexes 
                           WHERE tablename = ?";
                    $records = $this->db->get_records_sql($sql, [$table_name]);
                    $indexes = [];
                    foreach ($records as $record) {
                        $indexes[] = "{$record->indexname}: {$record->indexdef}";
                    }
                    return $indexes;
                    
                default:
                    return ['Database type not supported for index info'];
            }
        } catch (Exception $e) {
            return ['Error retrieving indexes: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get table foreign keys
     * 
     * @param string $table_name Table name
     * @return array Foreign key information
     */
    private function get_table_foreign_keys($table_name) {
        try {
            $dbtype = $this->db->get_dbfamily();
            
            switch ($dbtype) {
                case 'mysql':
                    $sql = "SELECT CONSTRAINT_NAME, COLUMN_NAME, 
                                  REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                           FROM information_schema.KEY_COLUMN_USAGE 
                           WHERE TABLE_NAME = ? 
                           AND REFERENCED_TABLE_NAME IS NOT NULL";
                    $records = $this->db->get_records_sql($sql, [$table_name]);
                    $fks = [];
                    foreach ($records as $record) {
                        $fks[] = "{$record->constraint_name}: {$record->column_name} -> {$record->referenced_table_name}.{$record->referenced_column_name}";
                    }
                    return $fks;
                    
                case 'postgres':
                    $sql = "SELECT conname, 
                                  pg_get_constraintdef(oid) as definition
                           FROM pg_constraint 
                           WHERE conrelid = ?::regclass 
                           AND contype = 'f'";
                    $records = $this->db->get_records_sql($sql, [$table_name]);
                    $fks = [];
                    foreach ($records as $record) {
                        $fks[] = "{$record->conname}: {$record->definition}";
                    }
                    return $fks;
                    
                default:
                    return ['Database type not supported for foreign key info'];
            }
        } catch (Exception $e) {
            return ['Error retrieving foreign keys: ' . $e->getMessage()];
        }
    }
    
    /**
     * Print header information
     */
    private function print_header() {
        global $CFG;
        
        echo "CustomerIntel Database Schema Inspector\n";
        echo "======================================\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        echo "Moodle Version: " . $CFG->version . "\n";
        echo "Database Type: " . $this->db->get_dbfamily() . "\n";
        echo "Plugin Version: " . get_config('local_customerintel', 'version') . "\n";
        echo "\n" . str_repeat("=", 80) . "\n\n";
    }
}

// Execute the inspection
echo "Starting CustomerIntel database schema inspection...\n\n";

$inspector = new table_schema_inspector();
$inspector->inspect_all_tables();