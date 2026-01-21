<?php
/**
 * CLI script to rebuild the local_ci_log table if it doesn't exist.
 * Can be run via CLI or web browser.
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Check if running via web browser
$is_web = !CLI_SCRIPT || isset($_SERVER['HTTP_HOST']);

if ($is_web) {
    echo "<html><body><pre>\n";
}

try {
    echo "Checking for table local_ci_log...\n";
    
    $dbman = $DB->get_manager();
    $table_name = 'local_ci_log';
    
    // Check if table exists
    if ($dbman->table_exists($table_name)) {
        echo "Table already exists.\n";
    } else {
        echo "Creating table local_ci_log...\n";
        
        // Define table structure
        $table = new xmldb_table($table_name);
        
        // Add fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('level', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'info');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        
        // Add primary key
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        // Create the table
        $dbman->create_table($table);
        
        echo "Table created successfully.\n";
    }
    
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}

echo "Done.\n";

if ($is_web) {
    echo "</pre></body></html>\n";
}

exit(0);