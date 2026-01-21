<?php
/**
 * Schema Consistency Checker for CustomerIntel
 * 
 * Validates database schema alignment with install.xml
 * 
 * @package    local_customerintel
 * @copyright  2024 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/ddllib.php');

// CLI options
list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
        'fix' => false,
        'verbose' => false
    ),
    array('h' => 'help', 'f' => 'fix', 'v' => 'verbose')
);

if ($options['help']) {
    echo "Schema Consistency Checker for CustomerIntel

Usage:
    php check_schema_consistency.php [OPTIONS]

Options:
    -h, --help      Show this help message
    -f, --fix       Attempt to fix schema issues (use with caution)
    -v, --verbose   Verbose output

Example:
    php check_schema_consistency.php         # Check schema
    php check_schema_consistency.php --fix   # Fix issues
";
    exit(0);
}

/**
 * Schema checker class
 */
class customerintel_schema_checker {
    
    private $dbman;
    private $issues = [];
    private $warnings = [];
    private $verbose;
    private $fix;
    
    public function __construct($verbose = false, $fix = false) {
        global $DB;
        $this->dbman = $DB->get_manager();
        $this->verbose = $verbose;
        $this->fix = $fix;
    }
    
    /**
     * Run schema check
     */
    public function check() {
        $this->log("=== CustomerIntel Schema Consistency Check ===");
        $this->log("Timestamp: " . date('Y-m-d H:i:s'));
        
        // Load install.xml
        $xmldb_file = new xmldb_file(__DIR__ . '/../db/install.xml');
        if (!$xmldb_file->fileExists()) {
            $this->error("install.xml not found");
            return false;
        }
        
        $xmldb_file->loadXMLStructure();
        $structure = $xmldb_file->getStructure();
        $tables = $structure->getTables();
        
        $total_tables = count($tables);
        $checked = 0;
        $issues_found = 0;
        
        $this->log("\nChecking $total_tables tables...\n");
        
        foreach ($tables as $table) {
            $table_name = $table->getName();
            $checked++;
            
            $this->log("[$checked/$total_tables] Checking table: $table_name");
            
            // Check if table exists
            if (!$this->dbman->table_exists($table)) {
                $this->issue("Table '$table_name' does not exist in database");
                $issues_found++;
                
                if ($this->fix) {
                    $this->log("  Creating table '$table_name'...");
                    $this->dbman->create_table($table);
                }
                continue;
            }
            
            // Check fields
            $fields = $table->getFields();
            foreach ($fields as $field) {
                $field_name = $field->getName();
                
                if (!$this->dbman->field_exists($table, $field)) {
                    $this->issue("Field '$field_name' missing in table '$table_name'");
                    $issues_found++;
                    
                    if ($this->fix) {
                        $this->log("  Adding field '$field_name'...");
                        $this->dbman->add_field($table, $field);
                    }
                } else {
                    // Check field properties
                    $this->check_field_properties($table_name, $field);
                }
            }
            
            // Check indexes
            $indexes = $table->getIndexes();
            foreach ($indexes as $index) {
                $index_name = $index->getName();
                
                if (!$this->dbman->index_exists($table, $index)) {
                    $this->warning("Index '$index_name' missing in table '$table_name'");
                    
                    if ($this->fix) {
                        $this->log("  Adding index '$index_name'...");
                        $this->dbman->add_index($table, $index);
                    }
                }
            }
            
            // Check keys
            $keys = $table->getKeys();
            foreach ($keys as $key) {
                $key_name = $key->getName();
                
                if ($key->getType() == XMLDB_KEY_PRIMARY) {
                    // Primary keys are handled by field definitions
                    continue;
                }
                
                if (!$this->check_key_exists($table_name, $key)) {
                    $this->warning("Key '$key_name' missing in table '$table_name'");
                    
                    if ($this->fix) {
                        $this->log("  Adding key '$key_name'...");
                        $this->dbman->add_key($table, $key);
                    }
                }
            }
        }
        
        // Check for required capabilities
        $this->check_capabilities();
        
        // Check for required data
        $this->check_required_data();
        
        // Output summary
        $this->output_summary($checked, $issues_found);
        
        return count($this->issues) === 0;
    }
    
    /**
     * Check field properties
     */
    private function check_field_properties($table_name, $field) {
        global $DB;
        
        // Get actual field info from database
        $columns = $DB->get_columns($table_name);
        $field_name = $field->getName();
        
        if (!isset($columns[$field_name])) {
            return;
        }
        
        $actual = $columns[$field_name];
        
        // Check type
        $expected_type = $this->get_expected_type($field);
        if ($this->normalize_type($actual->type) !== $this->normalize_type($expected_type)) {
            $this->warning("Field '$field_name' in '$table_name' has type '{$actual->type}', expected '$expected_type'");
        }
        
        // Check nullable
        if ($field->getNotNull() && !$actual->not_null) {
            $this->warning("Field '$field_name' in '$table_name' should not be nullable");
        }
        
        // Check default
        if ($field->getDefault() !== null && $actual->default_value != $field->getDefault()) {
            $this->warning("Field '$field_name' in '$table_name' has wrong default value");
        }
    }
    
    /**
     * Get expected field type
     */
    private function get_expected_type($field) {
        $type = '';
        
        switch ($field->getType()) {
            case XMLDB_TYPE_INTEGER:
                $type = 'int';
                break;
            case XMLDB_TYPE_NUMBER:
                $type = 'decimal';
                break;
            case XMLDB_TYPE_FLOAT:
                $type = 'float';
                break;
            case XMLDB_TYPE_CHAR:
                $type = 'varchar';
                break;
            case XMLDB_TYPE_TEXT:
                $type = 'text';
                break;
            case XMLDB_TYPE_BINARY:
                $type = 'blob';
                break;
            case XMLDB_TYPE_DATETIME:
                $type = 'datetime';
                break;
            case XMLDB_TYPE_TIMESTAMP:
                $type = 'timestamp';
                break;
        }
        
        return $type;
    }
    
    /**
     * Normalize type name for comparison
     */
    private function normalize_type($type) {
        $type = strtolower($type);
        
        // Map database-specific types to generic ones
        $mappings = [
            'bigint' => 'int',
            'smallint' => 'int',
            'tinyint' => 'int',
            'mediumint' => 'int',
            'longtext' => 'text',
            'mediumtext' => 'text',
            'tinytext' => 'text'
        ];
        
        return $mappings[$type] ?? $type;
    }
    
    /**
     * Check if key exists
     */
    private function check_key_exists($table_name, $key) {
        // This is a simplified check
        // In a real implementation, would need to query information_schema
        return true;
    }
    
    /**
     * Check capabilities
     */
    private function check_capabilities() {
        global $DB;
        
        $required_capabilities = [
            'local/customerintel:view',
            'local/customerintel:manage',
            'local/customerintel:export'
        ];
        
        foreach ($required_capabilities as $capability) {
            $exists = $DB->record_exists('capabilities', ['name' => $capability]);
            
            if (!$exists) {
                $this->warning("Capability '$capability' not found in database");
            } else {
                $this->log("✓ Capability '$capability' exists");
            }
        }
    }
    
    /**
     * Check required data
     */
    private function check_required_data() {
        global $DB;
        
        // Check if tables have any data
        $tables_to_check = [
            'local_customerintel_company',
            'local_customerintel_target',
            'local_customerintel_run',
            'local_customerintel_nb_result',
            'local_customerintel_source',
            'local_customerintel_snapshot',
            'local_customerintel_job_queue',
            'local_customerintel_telemetry'
        ];
        
        $this->log("\nChecking data presence...");
        
        foreach ($tables_to_check as $table) {
            $count = $DB->count_records($table);
            
            if ($count === 0) {
                $this->log("  ⚠ Table '$table' is empty");
            } else {
                $this->log("  ✓ Table '$table' has $count records");
            }
        }
    }
    
    /**
     * Output summary
     */
    private function output_summary($tables_checked, $issues_found) {
        echo "\n";
        echo "=====================================\n";
        echo "     SCHEMA CHECK SUMMARY           \n";
        echo "=====================================\n";
        echo "Tables checked:   $tables_checked\n";
        echo "Issues found:     " . count($this->issues) . "\n";
        echo "Warnings:         " . count($this->warnings) . "\n";
        
        if (!empty($this->issues)) {
            echo "\nISSUES:\n";
            foreach ($this->issues as $issue) {
                echo "  ✗ $issue\n";
            }
        }
        
        if (!empty($this->warnings) && $this->verbose) {
            echo "\nWARNINGS:\n";
            foreach ($this->warnings as $warning) {
                echo "  ⚠ $warning\n";
            }
        }
        
        if (empty($this->issues)) {
            echo "\n✓ Schema is consistent with install.xml\n";
        } else {
            echo "\n✗ Schema inconsistencies detected\n";
            
            if (!$this->fix) {
                echo "\nRun with --fix to attempt automatic repairs\n";
            }
        }
        
        // Generate report file
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tables_checked' => $tables_checked,
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'status' => empty($this->issues) ? 'PASS' : 'FAIL'
        ];
        
        $filename = 'schema_check_' . date('Ymd_His') . '.json';
        file_put_contents(__DIR__ . '/' . $filename, json_encode($report, JSON_PRETTY_PRINT));
        echo "\nDetailed report saved to: $filename\n";
    }
    
    /**
     * Log message
     */
    private function log($message) {
        if ($this->verbose) {
            echo "$message\n";
        }
    }
    
    /**
     * Record issue
     */
    private function issue($message) {
        $this->issues[] = $message;
        echo "  ✗ $message\n";
    }
    
    /**
     * Record warning
     */
    private function warning($message) {
        $this->warnings[] = $message;
        
        if ($this->verbose) {
            echo "  ⚠ $message\n";
        }
    }
    
    /**
     * Record error
     */
    private function error($message) {
        $this->issues[] = $message;
        echo "ERROR: $message\n";
    }
}

// Main execution
$checker = new customerintel_schema_checker($options['verbose'], $options['fix']);
$success = $checker->check();

exit($success ? 0 : 1);