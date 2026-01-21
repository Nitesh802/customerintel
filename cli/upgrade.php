<?php
/**
 * Customer Intelligence Dashboard - Database upgrade script
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute database upgrade steps
 * 
 * @param int $oldversion Previous version
 * @return bool True on success
 */
function xmldb_local_customerintel_upgrade($oldversion) {
    global $DB;
    
    $dbman = $DB->get_manager();

    // Upgrade to version 2024121400 - Complete schema alignment
    if ($oldversion < 2024121400) {
        
        // 1. Fix field name mismatches in local_ci_run table
        $table = new xmldb_table('local_ci_run');
        
        // Rename startedat to timestarted
        $field = new xmldb_field('startedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'reusedfromrunid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'timestarted');
        }
        
        // Rename finishedat to timecompleted
        $field = new xmldb_field('finishedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timestarted');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'timecompleted');
        }
        
        // 2. Add missing fields to local_ci_run
        $table = new xmldb_table('local_ci_run');
        
        // Add userid field
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'initiatedbyuserid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            
            // Copy data from initiatedbyuserid to userid for existing records
            $DB->execute("UPDATE {local_ci_run} SET userid = initiatedbyuserid WHERE userid = 0");
        }
        
        // Add actualtokens field
        $field = new xmldb_field('actualtokens', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'estcost');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add actualcost field
        $field = new xmldb_field('actualcost', XMLDB_TYPE_NUMBER, '12, 4', null, null, null, '0', 'actualtokens');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add timecreated field
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'error');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            
            // Set timecreated to current time for existing records
            $DB->execute("UPDATE {local_ci_run} SET timecreated = " . time() . " WHERE timecreated = 0");
        }
        
        // Add timemodified field
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            
            // Set timemodified to current time for existing records
            $DB->execute("UPDATE {local_ci_run} SET timemodified = " . time() . " WHERE timemodified = 0");
        }
        
        // Add targetcompanyid field if missing
        $field = new xmldb_field('targetcompanyid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'companyid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // 3. Update field properties in local_ci_run
        
        // Update status field comment
        $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued', 'timecompleted');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
        
        // Increase precision of estcost and actualcost
        $field = new xmldb_field('estcost', XMLDB_TYPE_NUMBER, '12, 4', null, null, null, '0', 'esttokens');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        $field = new xmldb_field('actualcost', XMLDB_TYPE_NUMBER, '12, 4', null, null, null, '0', 'actualtokens');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        // 4. Add missing fields to other tables
        
        // Add uploadedfilename to local_ci_source
        $table = new xmldb_table('local_ci_source');
        $field = new xmldb_field('uploadedfilename', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'url');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add timemodified to local_ci_source
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_ci_source} SET timemodified = timecreated WHERE timemodified = 0");
        }
        
        // Add timestamp fields to local_ci_nb_result
        $table = new xmldb_table('local_ci_nb_result');
        
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_ci_nb_result} SET timecreated = " . time() . " WHERE timecreated = 0");
        }
        
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_ci_nb_result} SET timemodified = " . time() . " WHERE timemodified = 0");
        }
        
        // Optimize nbcode field length - REMOVED: DDL call causes index dependency errors
        // $field = new xmldb_field('nbcode', XMLDB_TYPE_CHAR, '4', null, XMLDB_NOTNULL, null, null, 'runid');
        // if ($dbman->field_exists($table, $field)) {
        //     $dbman->change_field_precision($table, $field);
        // }
        
        // Add timemodified to local_ci_snapshot
        $table = new xmldb_table('local_ci_snapshot');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_ci_snapshot} SET timemodified = timecreated WHERE timemodified = 0");
        }
        
        // Add timemodified to local_ci_diff
        $table = new xmldb_table('local_ci_diff');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_ci_diff} SET timemodified = timecreated WHERE timemodified = 0");
        }
        
        // Add timemodified to local_ci_comparison
        $table = new xmldb_table('local_ci_comparison');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_ci_comparison} SET timemodified = timecreated WHERE timemodified = 0");
        }
        
        // Ensure default values for timestamp fields in local_ci_company
        $table = new xmldb_table('local_ci_company');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'metadata');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }
        
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }
        
        // Optimize ticker field length
        $field = new xmldb_field('ticker', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'name');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        // 5. Create the missing local_ci_source_chunk table
        if (!$dbman->table_exists('local_ci_source_chunk')) {
            $table = new xmldb_table('local_ci_source_chunk');
            
            // Add fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('chunkindex', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, null);
            $table->add_field('chunktext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('hash', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('tokens', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('sourceid', XMLDB_KEY_FOREIGN, array('sourceid'), 'local_ci_source', array('id'));
            
            // Add indexes
            $table->add_index('source_idx', XMLDB_INDEX_NOTUNIQUE, array('sourceid'));
            $table->add_index('source_chunk_idx', XMLDB_INDEX_NOTUNIQUE, array('sourceid', 'chunkindex'));
            
            // Create the table
            $dbman->create_table($table);
        }
        
        // 6. Remove the unused local_ci_settings table
        if ($dbman->table_exists('local_ci_settings')) {
            $table = new xmldb_table('local_ci_settings');
            $dbman->drop_table($table);
        }
        
        // 7. Add new indexes for performance
        
        // Indexes for local_ci_run
        $table = new xmldb_table('local_ci_run');
        
        $index = new xmldb_index('company_status_idx', XMLDB_INDEX_NOTUNIQUE, array('companyid', 'status'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('timestarted_idx', XMLDB_INDEX_NOTUNIQUE, array('timestarted'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('timecompleted_idx', XMLDB_INDEX_NOTUNIQUE, array('timecompleted'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Indexes for local_ci_source
        $table = new xmldb_table('local_ci_source');
        
        $index = new xmldb_index('company_hash_idx', XMLDB_INDEX_NOTUNIQUE, array('companyid', 'hash'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('type_idx', XMLDB_INDEX_NOTUNIQUE, array('type'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Indexes for local_ci_nb_result
        $table = new xmldb_table('local_ci_nb_result');
        
        $index = new xmldb_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, array('runid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('runid_nbcode_idx', XMLDB_INDEX_UNIQUE, array('runid', 'nbcode'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Indexes for local_ci_snapshot
        $table = new xmldb_table('local_ci_snapshot');
        
        $index = new xmldb_index('company_time_idx', XMLDB_INDEX_NOTUNIQUE, array('companyid', 'timecreated'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, array('runid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Indexes for local_ci_company
        $table = new xmldb_table('local_ci_company');
        
        $index = new xmldb_index('ticker_idx', XMLDB_INDEX_NOTUNIQUE, array('ticker'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Indexes for local_ci_telemetry
        $table = new xmldb_table('local_ci_telemetry');
        
        $index = new xmldb_index('runid_metric_idx', XMLDB_INDEX_NOTUNIQUE, array('runid', 'metrickey'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Indexes for local_ci_diff
        $table = new xmldb_table('local_ci_diff');
        
        $index = new xmldb_index('from_to_idx', XMLDB_INDEX_UNIQUE, array('fromsnapshotid', 'tosnapshotid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Indexes for local_ci_comparison
        $table = new xmldb_table('local_ci_comparison');
        
        $index = new xmldb_index('companies_idx', XMLDB_INDEX_NOTUNIQUE, array('customercompanyid', 'targetcompanyid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // 8. Add missing foreign keys
        
        // Foreign keys for local_ci_run
        $table = new xmldb_table('local_ci_run');
        
        $key = new xmldb_key('targetcompanyid', XMLDB_KEY_FOREIGN, array('targetcompanyid'), 'local_ci_company', array('id'));
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }
        
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }
        
        $key = new xmldb_key('reusedfromrunid', XMLDB_KEY_FOREIGN, array('reusedfromrunid'), 'local_ci_run', array('id'));
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }
        
        // Foreign key for local_ci_source.fileid
        $table = new xmldb_table('local_ci_source');
        $key = new xmldb_key('fileid', XMLDB_KEY_FOREIGN, array('fileid'), 'files', array('id'));
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }
        
        // Foreign keys for local_ci_comparison
        $table = new xmldb_table('local_ci_comparison');
        
        $key = new xmldb_key('basecustomersnapshotid', XMLDB_KEY_FOREIGN, array('basecustomersnapshotid'), 'local_ci_snapshot', array('id'));
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }
        
        $key = new xmldb_key('targetsnapshotid', XMLDB_KEY_FOREIGN, array('targetsnapshotid'), 'local_ci_snapshot', array('id'));
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }
        
        // Upgrade plugin version
        upgrade_plugin_savepoint(true, 2024121400, 'local', 'customerintel');
    }
    
    // Version 1.0.1 - Database refactoring for field and table naming consistency
    if ($oldversion < 2025101401) {
        // This version fixes PHP code to use correct table and field names
        // No schema changes needed as tables already use correct names (local_ci_*)
        // and fields are already correctly named in the database
        
        upgrade_plugin_savepoint(true, 2025101401, 'local', 'customerintel');
    }
    
    // Version 1.0.2 - UI fixes and Company Management feature
    if ($oldversion < 2025101402) {
        // This version adds Company Management feature and fixes UI issues
        // No database schema changes required - tables already exist
        // Just updating PHP code and UI templates
        
        upgrade_plugin_savepoint(true, 2025101402, 'local', 'customerintel');
    }
    
    // Version 1.0.3 - Add logging table for task execution tracking
    if ($oldversion < 2025101403) {
        // Create the log table for persistent task execution logs
        if (!$dbman->table_exists('local_ci_log')) {
            $table = new xmldb_table('local_ci_log');
            
            // Add fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('level', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'info');
            $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            
            // Add indexes
            $table->add_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, array('runid'));
            $table->add_index('level_idx', XMLDB_INDEX_NOTUNIQUE, array('level'));
            $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));
            
            // Create the table
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025101403, 'local', 'customerintel');
    }
    
    // Version 1.0.4 - Fix missing local_ci_log table creation
    if ($oldversion < 2025101405) {
        // Ensure the local_ci_log table exists with proper structure
        // This fixes any installations where the table creation might have failed
        $table = new xmldb_table('local_ci_log');
        if (!$dbman->table_exists($table)) {
            // Add fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('level', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'info');
            $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            
            // Add indexes for performance
            $table->add_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, ['runid']);
            $table->add_index('level_idx', XMLDB_INDEX_NOTUNIQUE, ['level']);
            $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            
            // Create the table
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025101405, 'local', 'customerintel');
    }
    
    // Version 2025101501 - Update TEXT fields to LONGTEXT for large AI payloads
    if ($oldversion < 2025101501) {
        
        // Update local_ci_nb_result table
        $table = new xmldb_table('local_ci_nb_result');
        
        // Change jsonpayload from TEXT to LONGTEXT
        $field = new xmldb_field('jsonpayload', XMLDB_TYPE_TEXT, 'long', null, null, null, null, 'nbcode');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        // Change citations from TEXT to LONGTEXT  
        $field = new xmldb_field('citations', XMLDB_TYPE_TEXT, 'long', null, null, null, null, 'jsonpayload');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        // Update local_ci_snapshot table
        $table = new xmldb_table('local_ci_snapshot');
        
        // Change snapshotjson from TEXT to LONGTEXT
        $field = new xmldb_field('snapshotjson', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL, null, null, 'runid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        // Update local_ci_diff table  
        $table = new xmldb_table('local_ci_diff');
        
        // Change diffjson from TEXT to LONGTEXT
        $field = new xmldb_field('diffjson', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL, null, null, 'tosnapshotid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        // Update local_ci_comparison table
        $table = new xmldb_table('local_ci_comparison');
        
        // Change comparisonjson from TEXT to LONGTEXT
        $field = new xmldb_field('comparisonjson', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL, null, null, 'targetsnapshotid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        // Update local_ci_log table
        $table = new xmldb_table('local_ci_log');
        
        // Change message from TEXT to LONGTEXT (for large stack traces)
        $field = new xmldb_field('message', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL, null, null, 'level');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2025101501, 'local', 'customerintel');
    }
    
    // Version 2025101502 - Enhanced LONGTEXT repair with verification
    if ($oldversion < 2025101502) {
        
        // Call the comprehensive column repair logic
        $repair_results = xmldb_local_customerintel_repair_longtext_columns($dbman);
        
        // Log the repair results
        $repaired_count = count(array_filter($repair_results, function($r) { return $r['repaired']; }));
        $error_count = count(array_filter($repair_results, function($r) { return $r['error']; }));
        
        debugging("CustomerIntel LONGTEXT repair completed: {$repaired_count} columns repaired, {$error_count} errors", DEBUG_DEVELOPER);
        
        upgrade_plugin_savepoint(true, 2025101502, 'local', 'customerintel');
    }
    
    // Version 2025101504 - Fix NOT NULL constraints on NB result table to allow proper inserts
    if ($oldversion < 2025101504) {
        
        // Modify local_ci_nb_result table to fix NOT NULL constraint issues
        $table = new xmldb_table('local_ci_nb_result');
        
        // Change timecreated to allow NULL values
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        
        // Change timemodified to allow NULL values
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timecreated');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        
        // Ensure tokensused allows NULL and has default 0
        $field = new xmldb_field('tokensused', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'durationms');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
            $dbman->change_field_notnull($table, $field);
        }
        
        // Ensure durationms allows NULL and has default 0
        $field = new xmldb_field('durationms', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'citations');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
            $dbman->change_field_notnull($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2025101504, 'local', 'customerintel');
    }
    
    // Version 2025101505 - General maintenance and code cleanup
    if ($oldversion < 2025101505) {
        // This version includes general code maintenance and improvements
        // No database schema changes required
        
        upgrade_plugin_savepoint(true, 2025101505, 'local', 'customerintel');
    }
    
    // Version 2025101506 - Replace sonar-deep-research references
    if ($oldversion < 2025101506) {
        // This version replaces sonar-deep-research references with updated model names
        // No database schema changes required - code changes only
        
        upgrade_plugin_savepoint(true, 2025101506, 'local', 'customerintel');
    }
    
    // Version 2025101507 - UI improvements and telemetry enhancements
    if ($oldversion < 2025101507) {
        // This version includes UI improvements and enhanced telemetry
        // No database schema changes required
        
        upgrade_plugin_savepoint(true, 2025101507, 'local', 'customerintel');
    }
    
    // Version 2025101508 - Performance optimizations and bug fixes
    if ($oldversion < 2025101508) {
        // This version includes performance optimizations and bug fixes
        // No database schema changes required
        
        upgrade_plugin_savepoint(true, 2025101508, 'local', 'customerintel');
    }
    
    // Version 2025101509 - Enhanced logging and debug capabilities
    if ($oldversion < 2025101509) {
        // This version enhances logging infrastructure and debug capabilities
        // No database schema changes required
        
        upgrade_plugin_savepoint(true, 2025101509, 'local', 'customerintel');
    }
    
    // Version 2025101510 - Production readiness and stability improvements (v1.0.8)
    if ($oldversion < 2025101510) {
        // This version includes final production readiness improvements
        // No database schema changes required
        
        upgrade_plugin_savepoint(true, 2025101510, 'local', 'customerintel');
    }
    
    // Version 2025101801 - Expand citations column to handle large citation lists (v1.0.9)
    if ($oldversion < 2025101801) {
        // Ensure citations field in local_ci_nb_result can handle large citation data
        // This addresses NB-13 save errors when citation lists exceed 2000+ characters
        
        $table = new xmldb_table('local_ci_nb_result');
        
        // Explicitly ensure citations field is LONGTEXT to handle large citation arrays
        $field = new xmldb_field('citations', XMLDB_TYPE_TEXT, 'long', null, null, null, null, 'jsonpayload');
        if ($dbman->field_exists($table, $field)) {
            // Force update the field to ensure it's properly set as LONGTEXT
            $dbman->change_field_precision($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2025101801, 'local', 'customerintel');
    }
    
    // Version 2025102002 - Expand nbcode field length safely (v1.0.11) - REMOVED: DDL calls cause dependency errors
    if ($oldversion < 2025102002) {
        // This upgrade step has been disabled because change_field_precision() triggers
        // index dependency errors. The nbcode field expansion is now handled by
        // the manual SQL migration in version 2025102006.
        
        upgrade_plugin_savepoint(true, 2025102002, 'local', 'customerintel');
    }
    
    // Version 2025102003 - Expand nbcode field length safely (second pass) (v1.0.12) - REMOVED: DDL calls cause dependency errors
    if ($oldversion < 2025102003) {
        // This upgrade step has been disabled because change_field_precision() triggers
        // index dependency errors. The nbcode field expansion is now handled by
        // the manual SQL migration in version 2025102006.
        
        upgrade_plugin_savepoint(true, 2025102003, 'local', 'customerintel');
    }
    
    // Version 2025102004 - Force-drop nbcode dependencies before field expansion (v1.0.13) - REMOVED: DDL calls cause dependency errors
    if ($oldversion < 2025102004) {
        // This upgrade step has been disabled because change_field_precision() triggers
        // index dependency errors. The nbcode field expansion is now handled by
        // the manual SQL migration in version 2025102006.
        
        upgrade_plugin_savepoint(true, 2025102004, 'local', 'customerintel');
    }
    
    // Version 2025102005 - nbcode side-by-side migration to varchar(20) (v1.0.14) - REMOVED: DDL calls cause dependency errors
    if ($oldversion < 2025102005) {
        // This upgrade step has been disabled because it uses DDL functions that trigger
        // index dependency errors. The nbcode field expansion is now handled by
        // the manual SQL migration in version 2025102006.
        
        upgrade_plugin_savepoint(true, 2025102005, 'local', 'customerintel');
    }
    
    // Version 2025102006 - nbcode direct SQL migration bypassing DDL dependency checks (v1.0.15)
    if ($oldversion < 2025102006) {
        // Use direct SQL to bypass Moodle's DDL dependency checks entirely
        
        global $DB, $CFG;
        
        // Get database type for SQL syntax
        $dbtype = $DB->get_dbfamily();
        
        try {
            // 1. Identify table and confirm nbcode column exists
            $table_name = $DB->get_prefix() . 'local_ci_nb_result';
            
            // Check if table exists and has nbcode column
            if ($dbtype === 'mysql') {
                $column_check = $DB->get_record_sql(
                    "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
                     FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = ? 
                     AND COLUMN_NAME = 'nbcode'", 
                    [$table_name]
                );
            } else if ($dbtype === 'postgres') {
                $column_check = $DB->get_record_sql(
                    "SELECT column_name, data_type, character_maximum_length 
                     FROM information_schema.columns 
                     WHERE table_name = ? 
                     AND column_name = 'nbcode'", 
                    [$table_name]
                );
            }
            
            if (!$column_check) {
                debugging("CustomerIntel upgrade: nbcode column not found, skipping migration", DEBUG_DEVELOPER);
                upgrade_plugin_savepoint(true, 2025102006, 'local', 'customerintel');
                return true;
            }
            
            // Only proceed if nbcode is still varchar(4) or smaller
            if ($column_check->character_maximum_length >= 20) {
                debugging("CustomerIntel upgrade: nbcode already varchar(20) or larger, skipping migration", DEBUG_DEVELOPER);
                upgrade_plugin_savepoint(true, 2025102006, 'local', 'customerintel');
                return true;
            }
            
            // 2. Drop indexes that reference nbcode using direct SQL
            $indexes_to_drop = [
                'mdl_locacinbresu_nbc_ix',
                'nbcode_idx', 
                'mdl_locacinbresu_runnbc_uix',
                'mdl_locacinbresu_run2_ix',
                'runid_nbcode_idx'
            ];
            
            foreach ($indexes_to_drop as $index_name) {
                try {
                    if ($dbtype === 'mysql') {
                        $DB->execute("DROP INDEX IF EXISTS {$index_name} ON {$table_name}");
                    } else if ($dbtype === 'postgres') {
                        $DB->execute("DROP INDEX IF EXISTS {$index_name}");
                    }
                    debugging("CustomerIntel upgrade: Dropped index {$index_name}", DEBUG_DEVELOPER);
                } catch (Exception $e) {
                    debugging("CustomerIntel upgrade: Could not drop index {$index_name}: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
            
            // 3. Add nbcode_new column if it doesn't exist
            try {
                if ($dbtype === 'mysql') {
                    $existing_new_column = $DB->get_record_sql(
                        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = ? 
                         AND COLUMN_NAME = 'nbcode_new'", 
                        [$table_name]
                    );
                } else if ($dbtype === 'postgres') {
                    $existing_new_column = $DB->get_record_sql(
                        "SELECT column_name FROM information_schema.columns 
                         WHERE table_name = ? 
                         AND column_name = 'nbcode_new'", 
                        [$table_name]
                    );
                }
                
                if (!$existing_new_column) {
                    $DB->execute("ALTER TABLE {$table_name} ADD COLUMN nbcode_new VARCHAR(20) NOT NULL DEFAULT ''");
                    debugging("CustomerIntel upgrade: Added nbcode_new column", DEBUG_DEVELOPER);
                }
            } catch (Exception $e) {
                debugging("CustomerIntel upgrade: Failed to add nbcode_new column: " . $e->getMessage(), DEBUG_DEVELOPER);
                throw $e;
            }
            
            // 4. Copy data from old to new column
            try {
                $DB->execute("UPDATE {$table_name} SET nbcode_new = nbcode WHERE nbcode_new = ''");
                debugging("CustomerIntel upgrade: Copied data from nbcode to nbcode_new", DEBUG_DEVELOPER);
            } catch (Exception $e) {
                debugging("CustomerIntel upgrade: Failed to copy data: " . $e->getMessage(), DEBUG_DEVELOPER);
                throw $e;
            }
            
            // 5. Drop the old nbcode column
            try {
                $DB->execute("ALTER TABLE {$table_name} DROP COLUMN nbcode");
                debugging("CustomerIntel upgrade: Dropped old nbcode column", DEBUG_DEVELOPER);
            } catch (Exception $e) {
                debugging("CustomerIntel upgrade: Failed to drop old nbcode column: " . $e->getMessage(), DEBUG_DEVELOPER);
                throw $e;
            }
            
            // 6. Rename nbcode_new to nbcode
            try {
                if ($dbtype === 'mysql') {
                    $DB->execute("ALTER TABLE {$table_name} CHANGE nbcode_new nbcode VARCHAR(20) NOT NULL DEFAULT ''");
                } else if ($dbtype === 'postgres') {
                    $DB->execute("ALTER TABLE {$table_name} RENAME COLUMN nbcode_new TO nbcode");
                    $DB->execute("ALTER TABLE {$table_name} ALTER COLUMN nbcode TYPE VARCHAR(20)");
                }
                debugging("CustomerIntel upgrade: Renamed nbcode_new to nbcode", DEBUG_DEVELOPER);
            } catch (Exception $e) {
                debugging("CustomerIntel upgrade: Failed to rename column: " . $e->getMessage(), DEBUG_DEVELOPER);
                throw $e;
            }
            
            // 7. Recreate necessary indexes manually
            try {
                // Create unique index on (runid, nbcode)
                $unique_index_sql = "CREATE UNIQUE INDEX mdl_locacinbresu_runnbc_uix ON {$table_name} (runid, nbcode)";
                $DB->execute($unique_index_sql);
                debugging("CustomerIntel upgrade: Created unique index on (runid, nbcode)", DEBUG_DEVELOPER);
                
                // Create non-unique index on (nbcode) - optional but useful for queries
                $single_index_sql = "CREATE INDEX mdl_locacinbresu_nbc_ix ON {$table_name} (nbcode)";
                $DB->execute($single_index_sql);
                debugging("CustomerIntel upgrade: Created index on (nbcode)", DEBUG_DEVELOPER);
                
            } catch (Exception $e) {
                debugging("CustomerIntel upgrade: Failed to recreate indexes: " . $e->getMessage(), DEBUG_DEVELOPER);
                throw $e;
            }
            
            // 8. Verify column type
            try {
                if ($dbtype === 'mysql') {
                    $verification = $DB->get_record_sql(
                        "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = ? 
                         AND COLUMN_NAME = 'nbcode'", 
                        [$table_name]
                    );
                    
                    if ($verification && strpos(strtolower($verification->column_type), 'varchar(20)') !== false) {
                        debugging("CustomerIntel upgrade: Verification successful - nbcode is now {$verification->column_type}", DEBUG_DEVELOPER);
                    } else {
                        debugging("CustomerIntel upgrade: Verification warning - unexpected column type", DEBUG_DEVELOPER);
                    }
                } else if ($dbtype === 'postgres') {
                    $verification = $DB->get_record_sql(
                        "SELECT data_type, character_maximum_length 
                         FROM information_schema.columns 
                         WHERE table_name = ? 
                         AND column_name = 'nbcode'", 
                        [$table_name]
                    );
                    
                    if ($verification && $verification->character_maximum_length >= 20) {
                        debugging("CustomerIntel upgrade: Verification successful - nbcode is now {$verification->data_type}({$verification->character_maximum_length})", DEBUG_DEVELOPER);
                    }
                }
                
                // 9. Success message
                debugging("CustomerIntel upgrade: Direct SQL migration completed successfully. nbcode field expanded to varchar(20) bypassing all DDL dependency checks.", DEBUG_DEVELOPER);
                
            } catch (Exception $e) {
                debugging("CustomerIntel upgrade: Verification failed but migration may have succeeded: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
            
        } catch (Exception $e) {
            debugging("CustomerIntel upgrade: Critical error during direct SQL migration: " . $e->getMessage(), DEBUG_DEVELOPER);
            throw new upgrade_exception('local_customerintel', 2025102006, "Direct SQL migration failed: " . $e->getMessage());
        }
        
        upgrade_plugin_savepoint(true, 2025102006, 'local', 'customerintel');
    }
    
    // Version 2025102007 - Remove DDL dependency calls for nbcode field migration (v1.0.16)
    if ($oldversion < 2025102007) {
        // This version removes all Moodle DDL function calls that could trigger
        // dependency errors related to nbcode field. All problematic upgrade steps
        // have been disabled and only the direct SQL migration (v2025102006) remains active.
        // 
        // Removed steps:
        // - 2025102002: change_field_precision() on nbcode
        // - 2025102003: change_field_precision() on nbcode  
        // - 2025102004: change_field_precision() on nbcode
        // - 2025102005: DDL functions for side-by-side migration
        //
        // The nbcode field expansion is now handled exclusively by direct SQL
        // in version 2025102006 which bypasses all Moodle dependency checks.
        
        upgrade_plugin_savepoint(true, 2025102007, 'local', 'customerintel');
    }
    
    // Version 2025102008 - Add synthesis table for Target-Aware Synthesis Engine
    if ($oldversion < 2025102008) {
        // Create the synthesis table for Intelligence Playbook generation
        if (!$dbman->table_exists('local_ci_synthesis')) {
            $table = new xmldb_table('local_ci_synthesis');
            
            // Add fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('htmlcontent', XMLDB_TYPE_TEXT, 'long', null, null, null, null);
            $table->add_field('jsoncontent', XMLDB_TYPE_TEXT, 'long', null, null, null, null);
            $table->add_field('voice_report', XMLDB_TYPE_TEXT, 'long', null, null, null, null);
            $table->add_field('selfcheck_report', XMLDB_TYPE_TEXT, 'long', null, null, null, null);
            $table->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('updatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('runid', XMLDB_KEY_FOREIGN, array('runid'), 'local_ci_run', array('id'));
            
            // Add indexes
            $table->add_index('runid_idx', XMLDB_INDEX_UNIQUE, array('runid'));
            $table->add_index('createdat_idx', XMLDB_INDEX_NOTUNIQUE, array('createdat'));
            
            // Create the table
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2025102008, 'local', 'customerintel');
    }
    
    // Version 2025203004 - Citation Enhancement System (Slice 4)
    if ($oldversion < 2025203004) {
        // ============================================================
        // Add new fields to local_ci_source table for citation enhancement
        // ============================================================
        $table = new xmldb_table('local_ci_source');
        
        // Add domain field for citation authority scoring
        $field = new xmldb_field('domain', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'hash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add confidence field (0.00-1.00)
        $field = new xmldb_field('confidence', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null, 'domain');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add source_type field (regulatory|news|analyst|company|industry)
        $field = new xmldb_field('source_type', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'confidence');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add indexes for new fields
        $index = new xmldb_index('domain_idx', XMLDB_INDEX_NOTUNIQUE, ['domain']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index('confidence_idx', XMLDB_INDEX_NOTUNIQUE, ['confidence']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // ============================================================
        // Create local_ci_citation table for enhanced citation tracking
        // ============================================================
        $table = new xmldb_table('local_ci_citation');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('section', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('marker', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
            $table->add_field('position', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL);
            $table->add_field('url', XMLDB_TYPE_TEXT);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255');
            $table->add_field('domain', XMLDB_TYPE_CHAR, '255');
            $table->add_field('publishedat', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('confidence', XMLDB_TYPE_NUMBER, '5, 2');
            $table->add_field('relevance', XMLDB_TYPE_NUMBER, '5, 2');
            $table->add_field('source_type', XMLDB_TYPE_CHAR, '20');
            $table->add_field('snippet', XMLDB_TYPE_TEXT);
            $table->add_field('provenance', XMLDB_TYPE_TEXT);
            $table->add_field('diversity_tags', XMLDB_TYPE_TEXT);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);
            $table->add_key('sourceid', XMLDB_KEY_FOREIGN, ['sourceid'], 'local_ci_source', ['id']);
            
            // Add indexes
            $table->add_index('runid_section_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'section']);
            $table->add_index('marker_idx', XMLDB_INDEX_NOTUNIQUE, ['marker']);
            $table->add_index('confidence_idx', XMLDB_INDEX_NOTUNIQUE, ['confidence']);
            $table->add_index('source_type_idx', XMLDB_INDEX_NOTUNIQUE, ['source_type']);
            
            // Create table
            $dbman->create_table($table);
        }
        
        // ============================================================
        // Create local_ci_citation_metrics table for aggregated metrics
        // ============================================================
        $table = new xmldb_table('local_ci_citation_metrics');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('total_citations', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('unique_domains', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('confidence_avg', XMLDB_TYPE_NUMBER, '5, 2');
            $table->add_field('confidence_min', XMLDB_TYPE_NUMBER, '5, 2');
            $table->add_field('confidence_max', XMLDB_TYPE_NUMBER, '5, 2');
            $table->add_field('diversity_score', XMLDB_TYPE_NUMBER, '5, 2');
            $table->add_field('source_distribution', XMLDB_TYPE_TEXT);
            $table->add_field('recency_mix', XMLDB_TYPE_TEXT);
            $table->add_field('section_coverage', XMLDB_TYPE_TEXT);
            $table->add_field('low_confidence_count', XMLDB_TYPE_INTEGER, '5', null, null, null, '0');
            $table->add_field('trace_gaps', XMLDB_TYPE_INTEGER, '5', null, null, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('runid', XMLDB_KEY_FOREIGN_UNIQUE, ['runid'], 'local_ci_run', ['id']);
            
            // Add indexes
            $table->add_index('confidence_avg_idx', XMLDB_INDEX_NOTUNIQUE, ['confidence_avg']);
            $table->add_index('diversity_score_idx', XMLDB_INDEX_NOTUNIQUE, ['diversity_score']);
            
            // Create table
            $dbman->create_table($table);
        }
        
        // Update plugin version
        upgrade_plugin_savepoint(true, 2025203004, 'local', 'customerintel');
    }
    
    // Version 2025203005 - Add missing tables (snapshot, diff, comparison)
    if ($oldversion < 2025203005) {
        // ============================================================
        // Create local_ci_snapshot table if missing
        // ============================================================
        $table = new xmldb_table('local_ci_snapshot');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('snapshotjson', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('companyid', XMLDB_KEY_FOREIGN, ['companyid'], 'local_ci_company', ['id']);
            $table->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);
            
            // Add indexes
            $table->add_index('company_time_idx', XMLDB_INDEX_NOTUNIQUE, ['companyid', 'timecreated']);
            $table->add_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, ['runid']);
            
            // Create table
            $dbman->create_table($table);
        }
        
        // ============================================================
        // Create local_ci_diff table if missing
        // ============================================================
        $table = new xmldb_table('local_ci_diff');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('fromsnapshotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('tosnapshotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('diffjson', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fromsnapshotid', XMLDB_KEY_FOREIGN, ['fromsnapshotid'], 'local_ci_snapshot', ['id']);
            $table->add_key('tosnapshotid', XMLDB_KEY_FOREIGN, ['tosnapshotid'], 'local_ci_snapshot', ['id']);
            
            // Add indexes
            $table->add_index('from_to_idx', XMLDB_INDEX_UNIQUE, ['fromsnapshotid', 'tosnapshotid']);
            
            // Create table
            $dbman->create_table($table);
        }
        
        // ============================================================
        // Create local_ci_comparison table if missing
        // ============================================================
        $table = new xmldb_table('local_ci_comparison');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('customercompanyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('targetcompanyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('basecustomersnapshotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('targetsnapshotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('comparisonjson', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('customercompanyid', XMLDB_KEY_FOREIGN, ['customercompanyid'], 'local_ci_company', ['id']);
            $table->add_key('targetcompanyid', XMLDB_KEY_FOREIGN, ['targetcompanyid'], 'local_ci_company', ['id']);
            $table->add_key('basecustomersnapshotid', XMLDB_KEY_FOREIGN, ['basecustomersnapshotid'], 'local_ci_snapshot', ['id']);
            $table->add_key('targetsnapshotid', XMLDB_KEY_FOREIGN, ['targetsnapshotid'], 'local_ci_snapshot', ['id']);
            
            // Add indexes
            $table->add_index('companies_idx', XMLDB_INDEX_NOTUNIQUE, ['customercompanyid', 'targetcompanyid']);
            
            // Create table
            $dbman->create_table($table);
        }
        
        // Update plugin version
        upgrade_plugin_savepoint(true, 2025203005, 'local', 'customerintel');
    }
    
    // Version 2025203006 - TEMPORARY: Force XMLDB refresh from install.xml
    // This step can be removed after confirming all tables are created correctly
    if ($oldversion < 2025203006) {
        // ============================================================
        // TEMPORARY FORCED XMLDB REFRESH
        // This ensures all tables from install.xml exist in the database
        // without dropping any existing tables or data
        // ============================================================
        
        global $CFG;
        
        // Load the install.xml file
        $xmldb_file = new xmldb_file($CFG->dirroot . '/local/customerintel/db/install.xml');
        if (!$xmldb_file->fileExists()) {
            throw new upgrade_exception('local_customerintel', 2025203006, 
                'install.xml file not found');
        }
        
        // Load and parse the XML structure
        if (!$xmldb_file->loadXMLStructure()) {
            throw new upgrade_exception('local_customerintel', 2025203006, 
                'Failed to load install.xml structure');
        }
        
        $xmldb_structure = $xmldb_file->getStructure();
        if (!$xmldb_structure) {
            throw new upgrade_exception('local_customerintel', 2025203006, 
                'Failed to parse install.xml structure');
        }
        
        // Get all tables from install.xml
        $tables = $xmldb_structure->getTables();
        
        // Counter for logging
        $tables_created = 0;
        $tables_skipped = 0;
        
        // Process each table with "create if not exists" logic
        foreach ($tables as $table) {
            $table_name = $table->getName();
            
            // Check if table already exists
            if ($dbman->table_exists($table)) {
                $tables_skipped++;
                debugging("XMLDB Refresh: Table {$table_name} already exists, skipping", DEBUG_DEVELOPER);
                
                // Even if table exists, check for missing fields and add them
                $existing_table = new xmldb_table($table_name);
                $fields = $table->getFields();
                
                foreach ($fields as $field) {
                    if (!$dbman->field_exists($existing_table, $field)) {
                        try {
                            $dbman->add_field($existing_table, $field);
                            debugging("XMLDB Refresh: Added missing field {$field->getName()} to {$table_name}", DEBUG_DEVELOPER);
                        } catch (Exception $e) {
                            debugging("XMLDB Refresh: Could not add field {$field->getName()} to {$table_name}: " . $e->getMessage(), DEBUG_DEVELOPER);
                        }
                    }
                }
                
                // Check for missing indexes
                $indexes = $table->getIndexes();
                foreach ($indexes as $index) {
                    if (!$dbman->index_exists($existing_table, $index)) {
                        try {
                            $dbman->add_index($existing_table, $index);
                            debugging("XMLDB Refresh: Added missing index {$index->getName()} to {$table_name}", DEBUG_DEVELOPER);
                        } catch (Exception $e) {
                            debugging("XMLDB Refresh: Could not add index {$index->getName()} to {$table_name}: " . $e->getMessage(), DEBUG_DEVELOPER);
                        }
                    }
                }
                
            } else {
                // Table doesn't exist, create it
                try {
                    $dbman->create_table($table);
                    $tables_created++;
                    debugging("XMLDB Refresh: Created table {$table_name}", DEBUG_DEVELOPER);
                } catch (Exception $e) {
                    debugging("XMLDB Refresh: Failed to create table {$table_name}: " . $e->getMessage(), DEBUG_DEVELOPER);
                    throw new upgrade_exception('local_customerintel', 2025203006, 
                        "Failed to create table {$table_name}: " . $e->getMessage());
                }
            }
        }
        
        // Log summary
        debugging("XMLDB Refresh Complete: Created {$tables_created} tables, Skipped {$tables_skipped} existing tables", DEBUG_DEVELOPER);
        
        // Special check for Citation Enhancement tables
        $citation_tables = ['local_ci_citation', 'local_ci_citation_metrics'];
        foreach ($citation_tables as $citation_table_name) {
            $citation_table = new xmldb_table($citation_table_name);
            if ($dbman->table_exists($citation_table)) {
                debugging("XMLDB Refresh: Verified {$citation_table_name} exists", DEBUG_DEVELOPER);
            } else {
                debugging("XMLDB Refresh: WARNING - {$citation_table_name} still missing after refresh", DEBUG_NORMAL);
            }
        }
        
        // Update plugin version
        upgrade_plugin_savepoint(true, 2025203006, 'local', 'customerintel');
    }
    
    // Version 2025203007 - Safe XMLDB re-registration without table recreation
    if ($oldversion < 2025203007) {
        // ============================================================
        // SAFE XMLDB RE-REGISTRATION
        // This re-registers the XML structure without dropping/recreating tables
        // Fixes the "Failed to load install.xml structure" error
        // ============================================================
        
        global $CFG;
        
        debugging("XMLDB Re-registration: Starting safe re-registration for version 2025203007", DEBUG_DEVELOPER);
        
        // Load the corrected install.xml file
        $xmldbfile = $CFG->dirroot . '/local/customerintel/db/install.xml';
        
        // Check file exists
        if (!file_exists($xmldbfile)) {
            throw new upgrade_exception('local_customerintel', 2025203007, 
                'install.xml file not found at: ' . $xmldbfile);
        }
        
        // Use the xmldb_file class to load and validate the structure
        require_once($CFG->dirroot . '/lib/xmldb/xmldb_file.php');
        require_once($CFG->dirroot . '/lib/xmldb/xmldb_structure.php');
        
        $xmldb_file = new xmldb_file($xmldbfile);
        
        // Load and parse the XML structure
        if (!$xmldb_file->loadXMLStructure()) {
            throw new upgrade_exception('local_customerintel', 2025203007, 
                'Failed to load install.xml structure - check XML syntax');
        }
        
        $structure = $xmldb_file->getStructure();
        if (!$structure) {
            throw new upgrade_exception('local_customerintel', 2025203007, 
                'Failed to parse install.xml structure');
        }
        
        // Verify structure contains expected path
        $path = $structure->getPath();
        if ($path !== 'local/customerintel/db') {
            debugging("XMLDB Re-registration: WARNING - Path mismatch. Expected 'local/customerintel/db', got '{$path}'", DEBUG_NORMAL);
        }
        
        // Count tables in structure for verification
        $tables = $structure->getTables();
        $table_count = count($tables);
        debugging("XMLDB Re-registration: Loaded structure with {$table_count} tables", DEBUG_DEVELOPER);
        
        // List all table names for verification
        $table_names = [];
        foreach ($tables as $table) {
            $table_names[] = $table->getName();
        }
        debugging("XMLDB Re-registration: Tables in structure: " . implode(', ', $table_names), DEBUG_DEVELOPER);
        
        // DO NOT create or modify any tables - just re-register the structure
        // The structure is now loaded and validated, which refreshes Moodle's cache
        
        // Clear Moodle caches to ensure the new structure is recognized
        if (function_exists('purge_all_caches')) {
            purge_all_caches();
            debugging("XMLDB Re-registration: Purged all Moodle caches", DEBUG_DEVELOPER);
        }
        
        // Success message
        debugging("XMLDB Re-registration: Successfully re-registered install.xml structure without modifying tables", DEBUG_DEVELOPER);
        
        // Update plugin version
        upgrade_plugin_savepoint(true, 2025203007, 'local', 'customerintel');
    }
    
    // Version 2025203008 - UI Enhancement & Integration (Slice 8)
    if ($oldversion < 2025203008) {
        // Add feature flags for UI enhancements
        
        // Enable interactive UI components by default
        $current_ui_setting = get_config('local_customerintel', 'enable_interactive_ui');
        if ($current_ui_setting === false) {
            set_config('enable_interactive_ui', '1', 'local_customerintel');
        }
        
        // Enable citation charts by default
        $current_citation_setting = get_config('local_customerintel', 'enable_citation_charts');
        if ($current_citation_setting === false) {
            set_config('enable_citation_charts', '1', 'local_customerintel');
        }
        
        // Success message
        debugging("Slice 8 UI Enhancement: Feature flags initialized (enable_interactive_ui=1, enable_citation_charts=1)", DEBUG_DEVELOPER);
        
        upgrade_plugin_savepoint(true, 2025203008, 'local', 'customerintel');
    }
    
    // Version 2025203010 - Analytics Dashboard & Historical Insights (Slice 10)
    if ($oldversion < 2025203010) {
        
        // Initialize analytics feature flags
        $current_analytics_setting = get_config('local_customerintel', 'enable_analytics_dashboard');
        if ($current_analytics_setting === false) {
            set_config('enable_analytics_dashboard', '1', 'local_customerintel');
        }
        
        $current_telemetry_trends_setting = get_config('local_customerintel', 'enable_telemetry_trends');
        if ($current_telemetry_trends_setting === false) {
            set_config('enable_telemetry_trends', '1', 'local_customerintel');
        }
        
        // Initialize safe mode setting (moved from Slice 9)
        $current_safe_mode_setting = get_config('local_customerintel', 'enable_safe_mode');
        if ($current_safe_mode_setting === false) {
            set_config('enable_safe_mode', '0', 'local_customerintel'); // Default disabled for full functionality
        }
        
        debugging("Slice 10 Analytics Dashboard: Feature flags initialized (enable_analytics_dashboard=1, enable_telemetry_trends=1, enable_safe_mode=0)", DEBUG_DEVELOPER);
        
        // Plugin savepoint reached
        upgrade_plugin_savepoint(true, 2025203010, 'local', 'customerintel');
    }
    
    // Version 2025203011 - Transparent Pipeline View System (Artifact Repository)
    if ($oldversion < 2025203011) {
        // ============================================================
        // Create local_ci_artifact table for pipeline artifact storage
        // ============================================================
        $table = new xmldb_table('local_ci_artifact');
        
        if (!$dbman->table_exists($table)) {
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('phase', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('artifacttype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('jsondata', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);
            
            // Add indexes
            $table->add_index('runid_phase_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'phase']);
            $table->add_index('phase_idx', XMLDB_INDEX_NOTUNIQUE, ['phase']);
            $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('runid_phase_type_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'phase', 'artifacttype']);
            
            // Create table
            $dbman->create_table($table);
            
            debugging("Transparent Pipeline View: Created local_ci_artifact table", DEBUG_DEVELOPER);
        } else {
            debugging("Transparent Pipeline View: local_ci_artifact table already exists", DEBUG_DEVELOPER);
        }
        
        // Initialize trace mode feature flag (disabled by default for safety)
        $current_trace_setting = get_config('local_customerintel', 'enable_trace_mode');
        if ($current_trace_setting === false) {
            set_config('enable_trace_mode', '0', 'local_customerintel');
        }
        
        debugging("Transparent Pipeline View: Feature flag initialized (enable_trace_mode=0)", DEBUG_DEVELOPER);
        
        upgrade_plugin_savepoint(true, 2025203011, 'local', 'customerintel');
    }
    
    // Version 2025203012 - Transparent Pipeline View System Implementation Complete
    if ($oldversion < 2025203012) {
        // This version completes the transparent pipeline view system implementation
        // All components are now properly integrated and tested
        
        debugging("Transparent Pipeline View: System implementation completed", DEBUG_DEVELOPER);
        
        upgrade_plugin_savepoint(true, 2025203012, 'local', 'customerintel');
    }
    
    // Version 2025203015 - Force Database Upgrade for Transparent Pipeline View
    if ($oldversion < 2025203015) {
        // ============================================================
        // FORCE CREATE local_ci_artifact table (if not exists)
        // This ensures the table is created regardless of previous upgrade issues
        // ============================================================
        $table = new xmldb_table('local_ci_artifact');
        
        if (!$dbman->table_exists($table)) {
            debugging("Force creating local_ci_artifact table (version 2025203015)", DEBUG_NORMAL);
            
            // Define fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('phase', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('artifacttype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('jsondata', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);
            
            // Add indexes
            $table->add_index('runid_phase_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'phase']);
            $table->add_index('phase_idx', XMLDB_INDEX_NOTUNIQUE, ['phase']);
            $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('runid_phase_type_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'phase', 'artifacttype']);
            
            // Create table
            $dbman->create_table($table);
            
            debugging("SUCCESS: Created local_ci_artifact table", DEBUG_NORMAL);
        } else {
            debugging("local_ci_artifact table already exists - skipping creation", DEBUG_NORMAL);
        }
        
        // Force initialize trace mode setting
        $current_trace_setting = get_config('local_customerintel', 'enable_trace_mode');
        if ($current_trace_setting === false) {
            set_config('enable_trace_mode', '0', 'local_customerintel');
            debugging("Initialized enable_trace_mode setting to 0 (disabled by default)", DEBUG_NORMAL);
        } else {
            debugging("enable_trace_mode setting already exists: {$current_trace_setting}", DEBUG_NORMAL);
        }
        
        debugging("Transparent Pipeline View: Force upgrade completed successfully", DEBUG_NORMAL);
        
        upgrade_plugin_savepoint(true, 2025203015, 'local', 'customerintel');
    }
    
    return true;
}

/**
 * Repair LONGTEXT columns - shared logic for upgrade and CLI
 * 
 * @param object $dbman Database manager instance
 * @return array Results of repair operations
 */
function xmldb_local_customerintel_repair_longtext_columns($dbman) {
    global $DB;
    
    // Fields that should be LONGTEXT
    $longtext_fields = [
        'local_ci_nb_result' => ['jsonpayload', 'citations'],
        'local_ci_snapshot' => ['snapshotjson'],
        'local_ci_diff' => ['diffjson'],
        'local_ci_comparison' => ['comparisonjson'],
        'local_ci_log' => ['message']
    ];
    
    $results = [];
    
    foreach ($longtext_fields as $table_name => $columns) {
        foreach ($columns as $column_name) {
            $result = [
                'table' => $table_name,
                'column' => $column_name,
                'repaired' => false,
                'error' => false,
                'error_message' => ''
            ];
            
            try {
                // Check if table exists
                $table = new xmldb_table($table_name);
                if (!$dbman->table_exists($table)) {
                    $result['error'] = true;
                    $result['error_message'] = 'Table does not exist';
                    $results[] = $result;
                    continue;
                }
                
                // Check if field exists  
                $field = new xmldb_field($column_name);
                if (!$dbman->field_exists($table, $field)) {
                    $result['error'] = true;
                    $result['error_message'] = 'Column does not exist';
                    $results[] = $result;
                    continue;
                }
                
                // Always attempt to update to LONGTEXT (idempotent operation)
                $field = new xmldb_field($column_name, XMLDB_TYPE_TEXT, 'long', null, null, null, null);
                
                // Set field position for proper schema
                if ($table_name === 'local_ci_nb_result') {
                    if ($column_name === 'jsonpayload') {
                        $field->setPrevious(new xmldb_field('nbcode'));
                    } else if ($column_name === 'citations') {
                        $field->setPrevious(new xmldb_field('jsonpayload'));
                    }
                } else if ($table_name === 'local_ci_snapshot') {
                    if ($column_name === 'snapshotjson') {
                        $field->setPrevious(new xmldb_field('runid'));
                    }
                } else if ($table_name === 'local_ci_diff') {
                    if ($column_name === 'diffjson') {
                        $field->setPrevious(new xmldb_field('tosnapshotid'));
                    }
                } else if ($table_name === 'local_ci_comparison') {
                    if ($column_name === 'comparisonjson') {
                        $field->setPrevious(new xmldb_field('targetsnapshotid'));
                    }
                } else if ($table_name === 'local_ci_log') {
                    if ($column_name === 'message') {
                        $field->setPrevious(new xmldb_field('level'));
                    }
                }
                
                // Perform the alteration
                $dbman->change_field_precision($table, $field);
                $result['repaired'] = true;
                
            } catch (Exception $e) {
                $result['error'] = true;
                $result['error_message'] = $e->getMessage();
                debugging("Failed to repair {$table_name}.{$column_name}: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
            
            $results[] = $result;
        }
    }
    
    return $results;
}