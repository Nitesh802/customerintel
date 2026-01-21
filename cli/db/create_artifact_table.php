<?php
/**
 * Manual Database Table Creation Script
 * 
 * Creates the local_ci_artifact table manually if upgrade fails
 */

require_once(__DIR__ . '/../../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "Manual Table Creation for CustomerIntel Artifacts\n";
echo "===============================================\n\n";

// Check if table already exists
$dbman = $DB->get_manager();
$table = new xmldb_table('local_ci_artifact');

if ($dbman->table_exists($table)) {
    echo "❌ Table 'local_ci_artifact' already exists!\n";
    echo "Current record count: " . $DB->count_records('local_ci_artifact') . "\n";
    echo "No action needed.\n";
    exit;
}

echo "🔨 Creating local_ci_artifact table manually...\n\n";

try {
    // Define table structure
    $table = new xmldb_table('local_ci_artifact');
    
    // Add fields
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
    $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
    $table->add_field('phase', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
    $table->add_field('artifacttype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
    $table->add_field('jsondata', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
    
    echo "✅ Fields defined\n";
    
    // Add keys
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);
    
    echo "✅ Keys defined\n";
    
    // Add indexes
    $table->add_index('runid_phase_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'phase']);
    $table->add_index('phase_idx', XMLDB_INDEX_NOTUNIQUE, ['phase']);
    $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
    $table->add_index('runid_phase_type_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'phase', 'artifacttype']);
    
    echo "✅ Indexes defined\n";
    
    // Create the table
    $dbman->create_table($table);
    
    echo "✅ Table created successfully!\n\n";
    
    // Verify creation
    if ($dbman->table_exists($table)) {
        echo "🎉 SUCCESS: local_ci_artifact table is now available\n";
        
        // Initialize configuration if needed
        $trace_setting = get_config('local_customerintel', 'enable_trace_mode');
        if ($trace_setting === false) {
            set_config('enable_trace_mode', '0', 'local_customerintel');
            echo "✅ Initialized enable_trace_mode setting\n";
        }
        
        echo "\nNext steps:\n";
        echo "1. Enable trace mode in Customer Intelligence settings\n";
        echo "2. Run an intelligence report to test artifact collection\n";
        echo "3. Check the Data Trace tab in reports\n";
        
    } else {
        echo "❌ FAILED: Table creation did not succeed\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>