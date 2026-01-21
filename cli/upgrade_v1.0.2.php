<?php
/**
 * CustomerIntel Database Upgrade Script v1.0.2
 * 
 * Handles database schema upgrades for synthesis features
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for CustomerIntel v1.0.2
 */
function xmldb_local_customerintel_upgrade_v102($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    echo "Starting CustomerIntel v1.0.2 database upgrade...\n";
    
    // Create synthesis table if it doesn't exist
    if ($oldversion < 2024011505) {
        $table = new xmldb_table('local_ci_synthesis');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('jsonpayload', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, ['runid']);
            
            $dbman->create_table($table);
            echo "✅ Created local_ci_synthesis table\n";
        }
        
        upgrade_plugin_savepoint(true, 2024011505, 'local', 'customerintel');
    }
    
    // Add telemetry table
    if ($oldversion < 2024011506) {
        $table = new xmldb_table('local_ci_telemetry');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('event_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('event_type_idx', XMLDB_INDEX_NOTUNIQUE, ['event_type']);
            $table->add_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, ['runid']);
            $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            
            $dbman->create_table($table);
            echo "✅ Created local_ci_telemetry table\n";
        }
        
        upgrade_plugin_savepoint(true, 2024011506, 'local', 'customerintel');
    }
    
    // Add orchestrator state table
    if ($oldversion < 2024011507) {
        $table = new xmldb_table('local_ci_orchestrator_state');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('current_nb', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('state_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('runid_unique', XMLDB_KEY_UNIQUE, ['runid']);
            
            $dbman->create_table($table);
            echo "✅ Created local_ci_orchestrator_state table\n";
        }
        
        upgrade_plugin_savepoint(true, 2024011507, 'local', 'customerintel');
    }
    
    // Ensure all required columns exist in nb_result table
    if ($oldversion < 2024011508) {
        $table = new xmldb_table('local_ci_nb_result');
        
        if ($dbman->table_exists($table)) {
            // Add validation_status if missing
            $field = new xmldb_field('validation_status', XMLDB_TYPE_CHAR, '20', null, null, null, 'pending');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                echo "✅ Added validation_status to local_ci_nb_result\n";
            }
            
            // Add citations field if missing  
            $field = new xmldb_field('citations', XMLDB_TYPE_TEXT, null, null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                echo "✅ Added citations to local_ci_nb_result\n";
            }
        }
        
        upgrade_plugin_savepoint(true, 2024011508, 'local', 'customerintel');
    }
    
    // Update cost tracking table structure
    if ($oldversion < 2024011509) {
        $table = new xmldb_table('local_ci_cost_tracking');
        
        if ($dbman->table_exists($table)) {
            // Ensure cost_usd is decimal with proper precision
            $field = new xmldb_field('cost_usd', XMLDB_TYPE_NUMBER, '10,4', null, XMLDB_NOTNULL, null, '0');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
                echo "✅ Updated cost_usd field precision\n";
            }
        }
        
        upgrade_plugin_savepoint(true, 2024011509, 'local', 'customerintel');
    }
    
    // Ensure validation table has proper structure
    if ($oldversion < 2024011510) {
        $table = new xmldb_table('local_ci_validation');
        
        if ($dbman->table_exists($table)) {
            // Add violation_count if missing
            $field = new xmldb_field('violation_count', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
                echo "✅ Added violation_count to local_ci_validation\n";
            }
        }
        
        upgrade_plugin_savepoint(true, 2024011510, 'local', 'customerintel');
    }
    
    echo "✅ CustomerIntel v1.0.2 database upgrade completed successfully!\n";
    return true;
}

/**
 * Check if upgrade is needed
 */
function customerintel_needs_upgrade() {
    global $DB;
    
    $current_version = get_config('local_customerintel', 'version');
    $target_version = 2024011510;
    
    return $current_version < $target_version;
}

/**
 * Validate database schema after upgrade
 */
function customerintel_validate_schema() {
    global $DB;
    $dbman = $DB->get_manager();
    
    $required_tables = [
        'local_ci_company',
        'local_ci_source', 
        'local_ci_run',
        'local_ci_run_sources',
        'local_ci_nb_result',
        'local_ci_synthesis',
        'local_ci_job_queue',
        'local_ci_cost_tracking',
        'local_ci_validation',
        'local_ci_snapshots',
        'local_ci_citations',
        'local_ci_logs',
        'local_ci_settings',
        'local_ci_telemetry',
        'local_ci_orchestrator_state'
    ];
    
    $missing = [];
    foreach ($required_tables as $table_name) {
        $table = new xmldb_table($table_name);
        if (!$dbman->table_exists($table)) {
            $missing[] = $table_name;
        }
    }
    
    if (empty($missing)) {
        echo "✅ All 15 required tables present\n";
        return true;
    } else {
        echo "❌ Missing tables: " . implode(', ', $missing) . "\n";
        return false;
    }
}