<?php
/**
 * Rebuild XMLDB schema using Moodle's XMLDB PHP API
 * This script generates the install.xml file programmatically
 * 
 * @package    local_customerintel
 * @copyright  2025 CustomerIntel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Bootstrap Moodle environment if available
$moodle_root = dirname(dirname(dirname(dirname(__FILE__))));
if (file_exists($moodle_root . '/config.php')) {
    require_once($moodle_root . '/config.php');
} else {
    // Minimal standalone mode for schema generation
    require_once($moodle_root . '/lib/xmldb/classes/XMLDBStructure.class.php');
    require_once($moodle_root . '/lib/xmldb/classes/XMLDBTable.class.php');
    require_once($moodle_root . '/lib/xmldb/classes/XMLDBField.class.php');
    require_once($moodle_root . '/lib/xmldb/classes/XMLDBKey.class.php');
    require_once($moodle_root . '/lib/xmldb/classes/XMLDBIndex.class.php');
}

require_once($CFG->libdir . '/xmldb/xmldb_object.php');
require_once($CFG->libdir . '/xmldb/xmldb_file.php');
require_once($CFG->libdir . '/xmldb/xmldb_structure.php');
require_once($CFG->libdir . '/xmldb/xmldb_table.php');
require_once($CFG->libdir . '/xmldb/xmldb_field.php');
require_once($CFG->libdir . '/xmldb/xmldb_key.php');
require_once($CFG->libdir . '/xmldb/xmldb_index.php');

// Create XMLDB structure
$structure = new xmldb_structure('local_customerintel');
$structure->setVersion('20250121');
$structure->setComment('XMLDB file for Customer Intelligence Dashboard with Citation Enhancement');

// ============================================================================
// TABLE: local_ci_company
// ============================================================================
$table_company = new xmldb_table('local_ci_company');
$table_company->setComment('Company repository for Customer and Target companies');

// Fields
$table_company->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_company->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
$table_company->add_field('ticker', XMLDB_TYPE_CHAR, '10');
$table_company->add_field('type', XMLDB_TYPE_CHAR, '20', null, null, null, 'unknown');
$table_company->add_field('website', XMLDB_TYPE_CHAR, '255');
$table_company->add_field('sector', XMLDB_TYPE_CHAR, '100');
$table_company->add_field('metadata', XMLDB_TYPE_TEXT);
$table_company->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
$table_company->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

// Keys
$table_company->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

// Indexes
$table_company->add_index('name_idx', XMLDB_INDEX_NOTUNIQUE, ['name']);
$table_company->add_index('type_idx', XMLDB_INDEX_NOTUNIQUE, ['type']);
$table_company->add_index('ticker_idx', XMLDB_INDEX_NOTUNIQUE, ['ticker']);

$structure->add_table($table_company);

// ============================================================================
// TABLE: local_ci_source
// ============================================================================
$table_source = new xmldb_table('local_ci_source');
$table_source->setComment('Sources for company intelligence');

// Fields
$table_source->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_source->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_source->add_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
$table_source->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
$table_source->add_field('url', XMLDB_TYPE_TEXT);
$table_source->add_field('uploadedfilename', XMLDB_TYPE_CHAR, '255');
$table_source->add_field('fileid', XMLDB_TYPE_INTEGER, '10');
$table_source->add_field('publishedat', XMLDB_TYPE_INTEGER, '10');
$table_source->add_field('addedbyuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_source->add_field('approved', XMLDB_TYPE_INTEGER, '1', null, null, null, '1');
$table_source->add_field('rejected', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
$table_source->add_field('hash', XMLDB_TYPE_CHAR, '64');
$table_source->add_field('domain', XMLDB_TYPE_CHAR, '255');  // NEW: For citation enhancement
$table_source->add_field('confidence', XMLDB_TYPE_NUMBER, '5, 2');  // NEW: Citation confidence score
$table_source->add_field('source_type', XMLDB_TYPE_CHAR, '20');  // NEW: regulatory|news|analyst|company|industry
$table_source->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_source->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_source->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_source->add_key('companyid', XMLDB_KEY_FOREIGN, ['companyid'], 'local_ci_company', ['id']);
$table_source->add_key('addedbyuserid', XMLDB_KEY_FOREIGN, ['addedbyuserid'], 'user', ['id']);
$table_source->add_key('fileid', XMLDB_KEY_FOREIGN, ['fileid'], 'files', ['id']);

// Indexes
$table_source->add_index('hash_idx', XMLDB_INDEX_NOTUNIQUE, ['hash']);
$table_source->add_index('company_hash_idx', XMLDB_INDEX_NOTUNIQUE, ['companyid', 'hash']);
$table_source->add_index('type_idx', XMLDB_INDEX_NOTUNIQUE, ['type']);
$table_source->add_index('domain_idx', XMLDB_INDEX_NOTUNIQUE, ['domain']);  // NEW
$table_source->add_index('confidence_idx', XMLDB_INDEX_NOTUNIQUE, ['confidence']);  // NEW

$structure->add_table($table_source);

// ============================================================================
// TABLE: local_ci_source_chunk
// ============================================================================
$table_chunk = new xmldb_table('local_ci_source_chunk');
$table_chunk->setComment('Text chunks for source retrieval');

// Fields
$table_chunk->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_chunk->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_chunk->add_field('chunkindex', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL);
$table_chunk->add_field('chunktext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
$table_chunk->add_field('hash', XMLDB_TYPE_CHAR, '64');
$table_chunk->add_field('tokens', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_chunk->add_field('metadata', XMLDB_TYPE_TEXT);
$table_chunk->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_chunk->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_chunk->add_key('sourceid', XMLDB_KEY_FOREIGN, ['sourceid'], 'local_ci_source', ['id']);

// Indexes
$table_chunk->add_index('source_idx', XMLDB_INDEX_NOTUNIQUE, ['sourceid']);
$table_chunk->add_index('source_chunk_idx', XMLDB_INDEX_NOTUNIQUE, ['sourceid', 'chunkindex']);

$structure->add_table($table_chunk);

// ============================================================================
// TABLE: local_ci_run
// ============================================================================
$table_run = new xmldb_table('local_ci_run');
$table_run->setComment('Intelligence run tracking');

// Fields
$table_run->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_run->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_run->add_field('targetcompanyid', XMLDB_TYPE_INTEGER, '10');
$table_run->add_field('initiatedbyuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_run->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_run->add_field('mode', XMLDB_TYPE_CHAR, '20', null, null, null, 'full');
$table_run->add_field('reusedfromrunid', XMLDB_TYPE_INTEGER, '10');
$table_run->add_field('esttokens', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_run->add_field('estcost', XMLDB_TYPE_NUMBER, '12, 4', null, null, null, '0');
$table_run->add_field('actualtokens', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_run->add_field('actualcost', XMLDB_TYPE_NUMBER, '12, 4', null, null, null, '0');
$table_run->add_field('timestarted', XMLDB_TYPE_INTEGER, '10');
$table_run->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10');
$table_run->add_field('status', XMLDB_TYPE_CHAR, '20', null, null, null, 'queued');
$table_run->add_field('error', XMLDB_TYPE_TEXT);
$table_run->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_run->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_run->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_run->add_key('companyid', XMLDB_KEY_FOREIGN, ['companyid'], 'local_ci_company', ['id']);
$table_run->add_key('targetcompanyid', XMLDB_KEY_FOREIGN, ['targetcompanyid'], 'local_ci_company', ['id']);
$table_run->add_key('initiatedbyuserid', XMLDB_KEY_FOREIGN, ['initiatedbyuserid'], 'user', ['id']);
$table_run->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
$table_run->add_key('reusedfromrunid', XMLDB_KEY_FOREIGN, ['reusedfromrunid'], 'local_ci_run', ['id']);

// Indexes
$table_run->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
$table_run->add_index('company_status_idx', XMLDB_INDEX_NOTUNIQUE, ['companyid', 'status']);
$table_run->add_index('timestarted_idx', XMLDB_INDEX_NOTUNIQUE, ['timestarted']);
$table_run->add_index('timecompleted_idx', XMLDB_INDEX_NOTUNIQUE, ['timecompleted']);
$table_run->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

$structure->add_table($table_run);

// ============================================================================
// TABLE: local_ci_nb_result
// ============================================================================
$table_nb = new xmldb_table('local_ci_nb_result');
$table_nb->setComment('Individual NB results');

// Fields
$table_nb->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_nb->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_nb->add_field('nbcode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
$table_nb->add_field('jsonpayload', XMLDB_TYPE_TEXT, 'big');
$table_nb->add_field('citations', XMLDB_TYPE_TEXT, 'big');
$table_nb->add_field('durationms', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_nb->add_field('tokensused', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_nb->add_field('status', XMLDB_TYPE_CHAR, '20', null, null, null, 'pending');
$table_nb->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_nb->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_nb->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_nb->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);

// Indexes
$table_nb->add_index('nbcode_idx', XMLDB_INDEX_NOTUNIQUE, ['nbcode']);
$table_nb->add_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, ['runid']);
$table_nb->add_index('runid_nbcode_idx', XMLDB_INDEX_UNIQUE, ['runid', 'nbcode']);

$structure->add_table($table_nb);

// ============================================================================
// TABLE: local_ci_telemetry
// ============================================================================
$table_telemetry = new xmldb_table('local_ci_telemetry');
$table_telemetry->setComment('Run telemetry and metrics');

// Fields
$table_telemetry->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_telemetry->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_telemetry->add_field('metrickey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
$table_telemetry->add_field('metricvaluenum', XMLDB_TYPE_NUMBER, '20, 4');
$table_telemetry->add_field('payload', XMLDB_TYPE_TEXT);
$table_telemetry->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_telemetry->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_telemetry->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);

// Indexes
$table_telemetry->add_index('metrickey_idx', XMLDB_INDEX_NOTUNIQUE, ['metrickey']);
$table_telemetry->add_index('runid_metric_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'metrickey']);

$structure->add_table($table_telemetry);

// ============================================================================
// TABLE: local_ci_synthesis
// ============================================================================
$table_synthesis = new xmldb_table('local_ci_synthesis');
$table_synthesis->setComment('Intelligence Playbook synthesis results');

// Fields
$table_synthesis->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_synthesis->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_synthesis->add_field('htmlcontent', XMLDB_TYPE_TEXT, 'big');
$table_synthesis->add_field('jsoncontent', XMLDB_TYPE_TEXT, 'big');
$table_synthesis->add_field('voice_report', XMLDB_TYPE_TEXT, 'big');
$table_synthesis->add_field('selfcheck_report', XMLDB_TYPE_TEXT, 'big');
$table_synthesis->add_field('createdat', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
$table_synthesis->add_field('updatedat', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_synthesis->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_synthesis->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);

// Indexes
$table_synthesis->add_index('runid_idx', XMLDB_INDEX_UNIQUE, ['runid']);
$table_synthesis->add_index('createdat_idx', XMLDB_INDEX_NOTUNIQUE, ['createdat']);

$structure->add_table($table_synthesis);

// ============================================================================
// TABLE: local_ci_citation (NEW for Slice 4)
// ============================================================================
$table_citation = new xmldb_table('local_ci_citation');
$table_citation->setComment('Enhanced citation tracking with confidence and diversity metrics');

// Fields
$table_citation->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_citation->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_citation->add_field('sourceid', XMLDB_TYPE_INTEGER, '10');
$table_citation->add_field('section', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
$table_citation->add_field('marker', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
$table_citation->add_field('position', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL);
$table_citation->add_field('url', XMLDB_TYPE_TEXT);
$table_citation->add_field('title', XMLDB_TYPE_CHAR, '255');
$table_citation->add_field('domain', XMLDB_TYPE_CHAR, '255');
$table_citation->add_field('publishedat', XMLDB_TYPE_INTEGER, '10');
$table_citation->add_field('confidence', XMLDB_TYPE_NUMBER, '5, 2');
$table_citation->add_field('relevance', XMLDB_TYPE_NUMBER, '5, 2');
$table_citation->add_field('source_type', XMLDB_TYPE_CHAR, '20');
$table_citation->add_field('snippet', XMLDB_TYPE_TEXT);
$table_citation->add_field('provenance', XMLDB_TYPE_TEXT);  // JSON
$table_citation->add_field('diversity_tags', XMLDB_TYPE_TEXT);  // JSON array
$table_citation->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_citation->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_citation->add_key('runid', XMLDB_KEY_FOREIGN, ['runid'], 'local_ci_run', ['id']);
$table_citation->add_key('sourceid', XMLDB_KEY_FOREIGN, ['sourceid'], 'local_ci_source', ['id']);

// Indexes
$table_citation->add_index('runid_section_idx', XMLDB_INDEX_NOTUNIQUE, ['runid', 'section']);
$table_citation->add_index('marker_idx', XMLDB_INDEX_NOTUNIQUE, ['marker']);
$table_citation->add_index('confidence_idx', XMLDB_INDEX_NOTUNIQUE, ['confidence']);
$table_citation->add_index('source_type_idx', XMLDB_INDEX_NOTUNIQUE, ['source_type']);

$structure->add_table($table_citation);

// ============================================================================
// TABLE: local_ci_citation_metrics (NEW for Slice 4)
// ============================================================================
$table_metrics = new xmldb_table('local_ci_citation_metrics');
$table_metrics->setComment('Aggregated citation metrics per run');

// Fields
$table_metrics->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
$table_metrics->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
$table_metrics->add_field('total_citations', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
$table_metrics->add_field('unique_domains', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
$table_metrics->add_field('confidence_avg', XMLDB_TYPE_NUMBER, '5, 2');
$table_metrics->add_field('confidence_min', XMLDB_TYPE_NUMBER, '5, 2');
$table_metrics->add_field('confidence_max', XMLDB_TYPE_NUMBER, '5, 2');
$table_metrics->add_field('diversity_score', XMLDB_TYPE_NUMBER, '5, 2');
$table_metrics->add_field('source_distribution', XMLDB_TYPE_TEXT);  // JSON
$table_metrics->add_field('recency_mix', XMLDB_TYPE_TEXT);  // JSON
$table_metrics->add_field('section_coverage', XMLDB_TYPE_TEXT);  // JSON
$table_metrics->add_field('low_confidence_count', XMLDB_TYPE_INTEGER, '5', null, null, null, '0');
$table_metrics->add_field('trace_gaps', XMLDB_TYPE_INTEGER, '5', null, null, null, '0');
$table_metrics->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

// Keys
$table_metrics->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
$table_metrics->add_key('runid', XMLDB_KEY_FOREIGN_UNIQUE, ['runid'], 'local_ci_run', ['id']);

// Indexes
$table_metrics->add_index('confidence_avg_idx', XMLDB_INDEX_NOTUNIQUE, ['confidence_avg']);
$table_metrics->add_index('diversity_score_idx', XMLDB_INDEX_NOTUNIQUE, ['diversity_score']);

$structure->add_table($table_metrics);

// ============================================================================
// Generate and save the XML
// ============================================================================

// Create xmldb_file instance
$xmldb_file = new xmldb_file(__DIR__ . '/install.xml');
$xmldb_file->setStructure($structure);

// Generate the XML content
$xml_content = $xmldb_file->getStructure()->xmlOutput();

// Save to file
$output_file = __DIR__ . '/install.xml';
if (file_put_contents($output_file, $xml_content)) {
    echo "✅ Successfully generated install.xml using XMLDB API\n";
    echo "   File saved to: {$output_file}\n\n";
    
    // Display summary
    echo "Database Schema Summary:\n";
    echo str_repeat('-', 50) . "\n";
    echo "Tables: 10 total (2 new for citation enhancement)\n";
    echo "  - local_ci_company\n";
    echo "  - local_ci_source (enhanced with citation fields)\n";
    echo "  - local_ci_source_chunk\n";
    echo "  - local_ci_run\n";
    echo "  - local_ci_nb_result\n";
    echo "  - local_ci_telemetry\n";
    echo "  - local_ci_synthesis\n";
    echo "  - local_ci_citation (NEW)\n";
    echo "  - local_ci_citation_metrics (NEW)\n";
    echo "\nNew fields added to local_ci_source:\n";
    echo "  - domain (CHAR 255)\n";
    echo "  - confidence (NUMBER 5,2)\n";
    echo "  - source_type (CHAR 20)\n";
    echo "\nTo apply these changes:\n";
    echo "  1. Navigate to Site administration > Development > XMLDB editor\n";
    echo "  2. Load the local_customerintel plugin\n";
    echo "  3. Compare with database and apply changes\n";
} else {
    echo "❌ Failed to save install.xml\n";
    exit(1);
}

// Also create an upgrade script
$upgrade_content = '<?php
/**
 * Upgrade script for Citation Enhancement (Slice 4)
 * 
 * @package    local_customerintel
 * @copyright  2025 CustomerIntel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined(\'MOODLE_INTERNAL\') || die();

function xmldb_local_customerintel_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025012100) {
        // Add new fields to local_ci_source
        $table = new xmldb_table(\'local_ci_source\');
        
        $field = new xmldb_field(\'domain\', XMLDB_TYPE_CHAR, \'255\');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field(\'confidence\', XMLDB_TYPE_NUMBER, \'5, 2\');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field(\'source_type\', XMLDB_TYPE_CHAR, \'20\');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add indexes
        $index = new xmldb_index(\'domain_idx\', XMLDB_INDEX_NOTUNIQUE, [\'domain\']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        $index = new xmldb_index(\'confidence_idx\', XMLDB_INDEX_NOTUNIQUE, [\'confidence\']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        // Create local_ci_citation table
        $table = new xmldb_table(\'local_ci_citation\');
        if (!$dbman->table_exists($table)) {
            $table->add_field(\'id\', XMLDB_TYPE_INTEGER, \'10\', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field(\'runid\', XMLDB_TYPE_INTEGER, \'10\', null, XMLDB_NOTNULL);
            $table->add_field(\'sourceid\', XMLDB_TYPE_INTEGER, \'10\');
            $table->add_field(\'section\', XMLDB_TYPE_CHAR, \'50\', null, XMLDB_NOTNULL);
            $table->add_field(\'marker\', XMLDB_TYPE_CHAR, \'10\', null, XMLDB_NOTNULL);
            $table->add_field(\'position\', XMLDB_TYPE_INTEGER, \'5\', null, XMLDB_NOTNULL);
            $table->add_field(\'url\', XMLDB_TYPE_TEXT);
            $table->add_field(\'title\', XMLDB_TYPE_CHAR, \'255\');
            $table->add_field(\'domain\', XMLDB_TYPE_CHAR, \'255\');
            $table->add_field(\'publishedat\', XMLDB_TYPE_INTEGER, \'10\');
            $table->add_field(\'confidence\', XMLDB_TYPE_NUMBER, \'5, 2\');
            $table->add_field(\'relevance\', XMLDB_TYPE_NUMBER, \'5, 2\');
            $table->add_field(\'source_type\', XMLDB_TYPE_CHAR, \'20\');
            $table->add_field(\'snippet\', XMLDB_TYPE_TEXT);
            $table->add_field(\'provenance\', XMLDB_TYPE_TEXT);
            $table->add_field(\'diversity_tags\', XMLDB_TYPE_TEXT);
            $table->add_field(\'timecreated\', XMLDB_TYPE_INTEGER, \'10\', null, null, null, \'0\');
            
            $table->add_key(\'primary\', XMLDB_KEY_PRIMARY, [\'id\']);
            $table->add_key(\'runid\', XMLDB_KEY_FOREIGN, [\'runid\'], \'local_ci_run\', [\'id\']);
            $table->add_key(\'sourceid\', XMLDB_KEY_FOREIGN, [\'sourceid\'], \'local_ci_source\', [\'id\']);
            
            $table->add_index(\'runid_section_idx\', XMLDB_INDEX_NOTUNIQUE, [\'runid\', \'section\']);
            $table->add_index(\'marker_idx\', XMLDB_INDEX_NOTUNIQUE, [\'marker\']);
            $table->add_index(\'confidence_idx\', XMLDB_INDEX_NOTUNIQUE, [\'confidence\']);
            $table->add_index(\'source_type_idx\', XMLDB_INDEX_NOTUNIQUE, [\'source_type\']);
            
            $dbman->create_table($table);
        }
        
        // Create local_ci_citation_metrics table
        $table = new xmldb_table(\'local_ci_citation_metrics\');
        if (!$dbman->table_exists($table)) {
            $table->add_field(\'id\', XMLDB_TYPE_INTEGER, \'10\', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field(\'runid\', XMLDB_TYPE_INTEGER, \'10\', null, XMLDB_NOTNULL);
            $table->add_field(\'total_citations\', XMLDB_TYPE_INTEGER, \'5\', null, XMLDB_NOTNULL, null, \'0\');
            $table->add_field(\'unique_domains\', XMLDB_TYPE_INTEGER, \'5\', null, XMLDB_NOTNULL, null, \'0\');
            $table->add_field(\'confidence_avg\', XMLDB_TYPE_NUMBER, \'5, 2\');
            $table->add_field(\'confidence_min\', XMLDB_TYPE_NUMBER, \'5, 2\');
            $table->add_field(\'confidence_max\', XMLDB_TYPE_NUMBER, \'5, 2\');
            $table->add_field(\'diversity_score\', XMLDB_TYPE_NUMBER, \'5, 2\');
            $table->add_field(\'source_distribution\', XMLDB_TYPE_TEXT);
            $table->add_field(\'recency_mix\', XMLDB_TYPE_TEXT);
            $table->add_field(\'section_coverage\', XMLDB_TYPE_TEXT);
            $table->add_field(\'low_confidence_count\', XMLDB_TYPE_INTEGER, \'5\', null, null, null, \'0\');
            $table->add_field(\'trace_gaps\', XMLDB_TYPE_INTEGER, \'5\', null, null, null, \'0\');
            $table->add_field(\'timecreated\', XMLDB_TYPE_INTEGER, \'10\', null, null, null, \'0\');
            
            $table->add_key(\'primary\', XMLDB_KEY_PRIMARY, [\'id\']);
            $table->add_key(\'runid\', XMLDB_KEY_FOREIGN_UNIQUE, [\'runid\'], \'local_ci_run\', [\'id\']);
            
            $table->add_index(\'confidence_avg_idx\', XMLDB_INDEX_NOTUNIQUE, [\'confidence_avg\']);
            $table->add_index(\'diversity_score_idx\', XMLDB_INDEX_NOTUNIQUE, [\'diversity_score\']);
            
            $dbman->create_table($table);
        }
        
        // Update plugin version
        upgrade_plugin_savepoint(true, 2025012100, \'local\', \'customerintel\');
    }
    
    return true;
}
';

$upgrade_file = dirname(__DIR__) . '/db/upgrade.php';
if (file_put_contents($upgrade_file, $upgrade_content)) {
    echo "\n✅ Also created upgrade.php for database migration\n";
    echo "   File saved to: {$upgrade_file}\n";
}