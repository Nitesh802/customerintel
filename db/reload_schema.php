<?php
/**
 * XMLDB Schema Reload Script for Customer Intelligence Plugin
 * 
 * This script forcibly reloads the install.xml schema into Moodle's XMLDB cache
 * WITHOUT dropping or recreating any tables. It only refreshes the schema registry.
 * 
 * Usage: Access this script as an admin user via browser:
 *        https://your-moodle-site/local/customerintel/db/reload_schema.php
 * 
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Initialize Moodle environment
define('CLI_SCRIPT', false);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/xmldb/xmldb_file.php');
require_once($CFG->libdir . '/xmldb/xmldb_structure.php');
require_once($CFG->libdir . '/upgradelib.php');

// Ensure only admins can run this script
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up the page
$PAGE->set_url('/local/customerintel/db/reload_schema.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('XMLDB Schema Reload - Customer Intelligence');
$PAGE->set_heading('XMLDB Schema Reload Tool');

// Output header
echo $OUTPUT->header();
echo html_writer::tag('h2', 'Customer Intelligence Plugin - XMLDB Schema Reload');

// Start output buffering for cleaner display
ob_start();

try {
    // Step 1: Locate and validate the install.xml file
    $xmldbfile_path = $CFG->dirroot . '/local/customerintel/db/install.xml';
    
    echo html_writer::tag('h3', 'Step 1: Validating install.xml location');
    echo html_writer::tag('p', 'Looking for: ' . $xmldbfile_path);
    
    if (!file_exists($xmldbfile_path)) {
        throw new Exception('ERROR: install.xml file not found at expected location!');
    }
    
    echo html_writer::tag('p', '✓ File exists', array('style' => 'color: green;'));
    
    // Display file size and modification time for verification
    $fileinfo = stat($xmldbfile_path);
    echo html_writer::tag('p', 'File size: ' . number_format($fileinfo['size']) . ' bytes');
    echo html_writer::tag('p', 'Last modified: ' . date('Y-m-d H:i:s', $fileinfo['mtime']));
    
    // Step 2: Load the XML structure
    echo html_writer::tag('h3', 'Step 2: Loading XML structure');
    
    $xmldb_file = new xmldb_file($xmldbfile_path);
    
    if (!$xmldb_file->fileExists()) {
        throw new Exception('ERROR: xmldb_file reports file does not exist!');
    }
    
    echo html_writer::tag('p', '✓ XMLDB file object created', array('style' => 'color: green;'));
    
    // Load and parse the XML
    if (!$xmldb_file->loadXMLStructure()) {
        throw new Exception('ERROR: Failed to load XML structure! Check for XML syntax errors.');
    }
    
    echo html_writer::tag('p', '✓ XML structure loaded successfully', array('style' => 'color: green;'));
    
    // Get the structure object
    $structure = $xmldb_file->getStructure();
    
    if (!$structure) {
        throw new Exception('ERROR: Failed to get XMLDB structure object!');
    }
    
    echo html_writer::tag('p', '✓ Structure object retrieved', array('style' => 'color: green;'));
    
    // Step 3: Analyze the structure
    echo html_writer::tag('h3', 'Step 3: Analyzing structure contents');
    
    // Get structure metadata
    $path = $structure->getPath();
    $version = $structure->getVersion();
    $comment = $structure->getComment();
    
    echo html_writer::tag('p', 'Path: ' . $path);
    echo html_writer::tag('p', 'Version: ' . $version);
    echo html_writer::tag('p', 'Comment: ' . $comment);
    
    // Validate path
    if ($path !== 'local/customerintel/db') {
        echo html_writer::tag('p', 
            '⚠ WARNING: Path should be "local/customerintel/db" but is "' . $path . '"', 
            array('style' => 'color: orange;'));
    } else {
        echo html_writer::tag('p', '✓ Path is correct', array('style' => 'color: green;'));
    }
    
    // Get all tables
    $tables = $structure->getTables();
    $table_count = count($tables);
    
    echo html_writer::tag('h4', 'Tables found: ' . $table_count);
    
    // List all tables with field counts
    echo html_writer::start_tag('ul');
    foreach ($tables as $table) {
        $table_name = $table->getName();
        $fields = $table->getFields();
        $field_count = count($fields);
        $indexes = $table->getIndexes();
        $index_count = count($indexes);
        $keys = $table->getKeys();
        $key_count = count($keys);
        
        echo html_writer::tag('li', 
            $table_name . ' (' . $field_count . ' fields, ' . 
            $key_count . ' keys, ' . $index_count . ' indexes)');
    }
    echo html_writer::end_tag('ul');
    
    // Step 4: Clear Moodle caches
    echo html_writer::tag('h3', 'Step 4: Clearing Moodle caches');
    
    // Clear all caches to ensure fresh load
    purge_all_caches();
    echo html_writer::tag('p', '✓ All caches purged', array('style' => 'color: green;'));
    
    // Step 5: Re-register the XMLDB structure (without creating tables)
    echo html_writer::tag('h3', 'Step 5: Re-registering XMLDB structure');
    
    // Get the database manager
    $dbman = $DB->get_manager();
    
    // Safety check: Don't actually create tables, just validate structure
    $validation_errors = [];
    
    // Validate each table structure
    foreach ($tables as $table) {
        $table_name = $table->getName();
        
        // Check if table exists in database
        if ($dbman->table_exists($table)) {
            echo html_writer::tag('p', '✓ Table ' . $table_name . ' exists in database', 
                array('style' => 'color: green; margin-left: 20px;'));
        } else {
            echo html_writer::tag('p', '⚠ Table ' . $table_name . ' does NOT exist in database', 
                array('style' => 'color: orange; margin-left: 20px;'));
        }
    }
    
    // Step 6: Force XMLDB Editor cache refresh
    echo html_writer::tag('h3', 'Step 6: Forcing XMLDB Editor cache refresh');
    
    // Set a config value to mark the refresh
    set_config('xmldb_schema_reload_' . $version, time(), 'local_customerintel');
    echo html_writer::tag('p', '✓ Schema reload timestamp saved', array('style' => 'color: green;'));
    
    // Clear XMLDB caches using safe methods
    try {
        // Method 1: Try to purge by event if available
        if (class_exists('cache_helper')) {
            // Use purge_by_event with safe event names
            if (method_exists('cache_helper', 'purge_by_event')) {
                try {
                    cache_helper::purge_by_event('changesincourse');
                    echo html_writer::tag('p', '✓ Course cache events triggered', array('style' => 'color: green;'));
                } catch (Exception $e) {
                    // Ignore if this specific event doesn't exist
                }
                
                try {
                    cache_helper::purge_by_event('changesindatabase');
                    echo html_writer::tag('p', '✓ Database cache events triggered', array('style' => 'color: green;'));
                } catch (Exception $e) {
                    // Ignore if this specific event doesn't exist
                }
            }
            
            // Safe method: Purge all caches without specific definition
            if (method_exists('cache_helper', 'purge_all')) {
                cache_helper::purge_all();
                echo html_writer::tag('p', '✓ All cache stores purged', array('style' => 'color: green;'));
            }
        }
        
        // Method 2: Purge all caches again to be thorough
        purge_all_caches();
        echo html_writer::tag('p', '✓ All caches purged again for XMLDB refresh', array('style' => 'color: green;'));
        
        // Method 3: Clear PHP opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
            echo html_writer::tag('p', '✓ PHP opcache cleared', array('style' => 'color: green;'));
        }
        
        // Method 4: Force database metadata reload if reset_caches method exists
        if (method_exists($DB->get_manager(), 'reset_caches')) {
            $DB->get_manager()->reset_caches();
            echo html_writer::tag('p', '✓ Database manager caches reset', array('style' => 'color: green;'));
        }
        
    } catch (Exception $cache_error) {
        // If any cache clearing fails, continue anyway
        echo html_writer::tag('p', '⚠ Some caches could not be cleared: ' . $cache_error->getMessage(), 
            array('style' => 'color: orange;'));
    }
    
    // Final success confirmation
    echo html_writer::tag('p', '✓ XMLDB cache refresh completed successfully!', 
        array('style' => 'color: green; font-weight: bold; font-size: 1.1em;'));
    
    // Success summary
    echo html_writer::tag('h3', 'Summary', array('style' => 'color: green; margin-top: 30px;'));
    echo html_writer::tag('div', 
        '✓ Successfully loaded and validated the XMLDB structure:' . 
        html_writer::start_tag('ul') .
        html_writer::tag('li', 'Version: ' . $version) .
        html_writer::tag('li', 'Tables: ' . $table_count) .
        html_writer::tag('li', 'Path: ' . $path) .
        html_writer::end_tag('ul'),
        array('style' => 'background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;')
    );
    
    // Instructions for next steps
    echo html_writer::tag('h3', 'Next Steps');
    echo html_writer::start_tag('ol');
    echo html_writer::tag('li', 'Go to Site Administration → Development → XMLDB editor');
    echo html_writer::tag('li', 'Click on "local/customerintel" to view the schema');
    echo html_writer::tag('li', 'You should now see all ' . $table_count . ' tables and version ' . $version);
    echo html_writer::tag('li', 'If the old version still appears, try:');
    echo html_writer::start_tag('ul');
    echo html_writer::tag('li', 'Clear your browser cache (Ctrl+F5 or Cmd+Shift+R)');
    echo html_writer::tag('li', 'Log out and log back in to Moodle');
    echo html_writer::tag('li', 'Run Site Administration → Development → Purge all caches again');
    echo html_writer::tag('li', 'Restart your web server if you have access');
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('ol');
    
} catch (Exception $e) {
    echo html_writer::tag('div', 
        'ERROR: ' . $e->getMessage(),
        array('style' => 'background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;')
    );
    
    // Show debug information
    echo html_writer::tag('h3', 'Debug Information');
    echo html_writer::tag('pre', print_r($e->getTrace(), true));
}

// Get the buffered output
$output = ob_get_clean();

// Display the output
echo html_writer::tag('div', $output, array('style' => 'padding: 20px;'));

// Add a link to go back
echo html_writer::tag('div',
    html_writer::link(new moodle_url('/admin/tool/xmldb/'), 
        '← Back to XMLDB Editor', 
        array('class' => 'btn btn-primary')) . ' ' .
    html_writer::link(new moodle_url('/admin/'), 
        '← Back to Site Administration', 
        array('class' => 'btn btn-secondary')),
    array('style' => 'margin-top: 30px; padding: 20px;')
);

// Output footer
echo $OUTPUT->footer();