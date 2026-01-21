<?php
/**
 * Database Refactor Smoke Test
 * Tests that all DB operations work after table/field name refactoring
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/customerintel/lib.php');

use local_customerintel\services\dbutil;

/**
 * Smoke test for database refactoring
 */
class local_customerintel_db_refactor_smoke_test extends advanced_testcase {
    
    /**
     * Setup
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }
    
    /**
     * Test table names are correct
     */
    public function test_table_names() {
        global $DB;
        
        // Test all tables exist with correct names
        $tables = [
            'local_ci_company',
            'local_ci_source',
            'local_ci_source_chunk',
            'local_ci_run',
            'local_ci_nb_result',
            'local_ci_snapshot',
            'local_ci_diff',
            'local_ci_comparison',
            'local_ci_telemetry'
        ];
        
        foreach ($tables as $table) {
            $this->assertTrue($DB->get_manager()->table_exists($table), 
                "Table $table should exist");
        }
        
        // Test old table names don't exist
        $old_tables = [
            'local_customerintel_company',
            'local_customerintel_source',
            'local_customerintel_run',
            'local_customerintel_job_queue',
            'local_customerintel_target'
        ];
        
        foreach ($old_tables as $table) {
            $this->assertFalse($DB->get_manager()->table_exists($table), 
                "Old table $table should not exist");
        }
    }
    
    /**
     * Test company operations
     */
    public function test_company_operations() {
        global $DB;
        
        // Create customer company
        $company = new stdClass();
        $company->name = 'Test Customer Company';
        $company->domain = 'testcustomer.com';
        $company->type = 'customer';
        $company->timecreated = time();
        $company->timemodified = time();
        
        $companyid = $DB->insert_record('local_ci_company', $company);
        $this->assertNotEmpty($companyid);
        
        // Create target company
        $target = new stdClass();
        $target->name = 'Test Target Company';
        $target->domain = 'testtarget.com';
        $target->type = 'target';
        $target->timecreated = time();
        $target->timemodified = time();
        
        $targetid = $DB->insert_record('local_ci_company', $target);
        $this->assertNotEmpty($targetid);
        
        // Verify retrieval
        $customer = $DB->get_record('local_ci_company', ['id' => $companyid, 'type' => 'customer']);
        $this->assertEquals('Test Customer Company', $customer->name);
        
        $target = $DB->get_record('local_ci_company', ['id' => $targetid, 'type' => 'target']);
        $this->assertEquals('Test Target Company', $target->name);
    }
    
    /**
     * Test run operations with correct field names
     */
    public function test_run_operations() {
        global $DB, $USER;
        
        // Setup companies first
        $company = new stdClass();
        $company->name = 'Test Company';
        $company->type = 'customer';
        $company->timecreated = time();
        $company->timemodified = time();
        $companyid = $DB->insert_record('local_ci_company', $company);
        
        $target = new stdClass();
        $target->name = 'Test Target';
        $target->type = 'target';
        $target->timecreated = time();
        $target->timemodified = time();
        $targetid = $DB->insert_record('local_ci_company', $target);
        
        // Create run with correct field names
        $run = new stdClass();
        $run->companyid = $companyid;  // NOT company_id
        $run->targetcompanyid = $targetid;  // NOT target_id
        $run->initiatedbyuserid = $USER->id;  // NOT created_by
        $run->status = 'pending';
        $run->timecreated = time();
        $run->timemodified = time();
        
        $runid = $DB->insert_record('local_ci_run', $run);
        $this->assertNotEmpty($runid);
        
        // Verify retrieval
        $retrieved = $DB->get_record('local_ci_run', ['id' => $runid]);
        $this->assertEquals($companyid, $retrieved->companyid);
        $this->assertEquals($targetid, $retrieved->targetcompanyid);
        $this->assertEquals($USER->id, $retrieved->initiatedbyuserid);
    }
    
    /**
     * Test source operations with correct field names
     */
    public function test_source_operations() {
        global $DB, $USER;
        
        // Setup company
        $company = new stdClass();
        $company->name = 'Test Company';
        $company->type = 'customer';
        $company->timecreated = time();
        $company->timemodified = time();
        $companyid = $DB->insert_record('local_ci_company', $company);
        
        // Create source with correct field names
        $source = new stdClass();
        $source->companyid = $companyid;  // NOT company_id
        $source->targetcompanyid = null;  // NOT target_id
        $source->type = 'website';
        $source->url = 'https://example.com';
        $source->status = 'pending';
        $source->addedbyuserid = $USER->id;
        $source->timecreated = time();
        $source->timemodified = time();
        
        $sourceid = $DB->insert_record('local_ci_source', $source);
        $this->assertNotEmpty($sourceid);
        
        // Verify retrieval
        $retrieved = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals($companyid, $retrieved->companyid);
        $this->assertEquals($USER->id, $retrieved->addedbyuserid);
    }
    
    /**
     * Test NB result operations with correct field names
     */
    public function test_nb_result_operations() {
        global $DB;
        
        // Setup run first
        $company = new stdClass();
        $company->name = 'Test Company';
        $company->type = 'customer';
        $company->timecreated = time();
        $company->timemodified = time();
        $companyid = $DB->insert_record('local_ci_company', $company);
        
        $run = new stdClass();
        $run->companyid = $companyid;
        $run->status = 'running';
        $run->initiatedbyuserid = 1;
        $run->timecreated = time();
        $run->timemodified = time();
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Create NB result with correct field name
        $result = new stdClass();
        $result->runid = $runid;  // NOT run_id
        $result->nbcode = 'nb01';
        $result->status = 'completed';
        $result->result = json_encode(['test' => 'data']);
        $result->timecreated = time();
        $result->timemodified = time();
        
        $resultid = $DB->insert_record('local_ci_nb_result', $result);
        $this->assertNotEmpty($resultid);
        
        // Verify retrieval
        $retrieved = $DB->get_record('local_ci_nb_result', ['id' => $resultid]);
        $this->assertEquals($runid, $retrieved->runid);
    }
    
    /**
     * Test dbutil helper class
     */
    public function test_dbutil_helper() {
        // Test table name generation
        $this->assertEquals('{local_ci_company}', dbutil::table('company'));
        $this->assertEquals('{local_ci_run}', dbutil::table('run'));
        $this->assertEquals('{local_ci_source}', dbutil::table('source'));
        
        // Test raw table name generation
        $this->assertEquals('local_ci_company', dbutil::raw_table('company'));
        $this->assertEquals('local_ci_run', dbutil::raw_table('run'));
        $this->assertEquals('local_ci_source', dbutil::raw_table('source'));
    }
    
    /**
     * Test telemetry operations
     */
    public function test_telemetry_operations() {
        global $DB;
        
        // Create telemetry record with correct field names
        $telemetry = new stdClass();
        $telemetry->runid = 1;  // NOT run_id
        $telemetry->nbcode = 'nb01';
        $telemetry->provider = 'openai';
        $telemetry->model = 'gpt-4';
        $telemetry->prompttokens = 100;
        $telemetry->completiontokens = 50;
        $telemetry->totaltokens = 150;
        $telemetry->cost = 0.01;
        $telemetry->duration = 2.5;
        $telemetry->timecreated = time();
        
        $telemetryid = $DB->insert_record('local_ci_telemetry', $telemetry);
        $this->assertNotEmpty($telemetryid);
        
        // Verify retrieval
        $retrieved = $DB->get_record('local_ci_telemetry', ['id' => $telemetryid]);
        $this->assertEquals(1, $retrieved->runid);
        $this->assertEquals('nb01', $retrieved->nbcode);
    }
}