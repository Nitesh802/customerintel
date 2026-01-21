<?php
/**
 * Company Service Unit Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests\services;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');

use local_customerintel\services\company_service;

/**
 * Company Service test cases
 * 
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_customerintel\services\company_service
 */
class company_service_test extends \advanced_testcase {
    
    /** @var company_service Service instance */
    protected $service;
    
    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->service = new company_service();
    }
    
    /**
     * Test company creation
     * 
     * @covers ::create_company
     */
    public function test_create_company() {
        global $DB;
        
        // Test basic company creation
        $companyid = $this->service->create_company('Test Company', 'customer', [
            'ticker' => 'TEST',
            'website' => 'https://test.com',
            'sector' => 'Technology'
        ]);
        
        $this->assertIsInt($companyid);
        $this->assertGreaterThan(0, $companyid);
        
        // Verify in database
        $company = $DB->get_record('local_ci_company', ['id' => $companyid]);
        $this->assertEquals('Test Company', $company->name);
        $this->assertEquals('customer', $company->type);
        $this->assertEquals('TEST', $company->ticker);
        $this->assertEquals('https://test.com', $company->website);
        $this->assertEquals('Technology', $company->sector);
        
        // Test duplicate handling
        $duplicateid = $this->service->create_company('Test Company', 'customer');
        $this->assertEquals($companyid, $duplicateid);
    }
    
    /**
     * Test invalid company type
     * 
     * @covers ::create_company
     */
    public function test_create_company_invalid_type() {
        $this->expectException(\invalid_parameter_exception::class);
        $this->service->create_company('Test Company', 'invalid_type');
    }
    
    /**
     * Test company search
     * 
     * @covers ::search_companies
     */
    public function test_search_companies() {
        // Create test companies
        $this->service->create_company('Apple Inc', 'customer', ['ticker' => 'AAPL']);
        $this->service->create_company('Microsoft Corp', 'target', ['ticker' => 'MSFT']);
        $this->service->create_company('Alphabet Inc', 'customer', ['ticker' => 'GOOGL']);
        
        // Test search by name
        $results = $this->service->search_companies('Apple');
        $this->assertCount(1, $results);
        $this->assertEquals('Apple Inc', reset($results)->name);
        
        // Test search by ticker
        $results = $this->service->search_companies('MSFT');
        $this->assertCount(1, $results);
        $this->assertEquals('Microsoft Corp', reset($results)->name);
        
        // Test type filter
        $results = $this->service->search_companies('', 'customer');
        $this->assertCount(2, $results);
    }
    
    /**
     * Test get company by ID
     * 
     * @covers ::get_company
     */
    public function test_get_company() {
        $companyid = $this->service->create_company('Test Company', 'target', [
            'revenue' => '1B',
            'employees' => 5000
        ]);
        
        $company = $this->service->get_company($companyid);
        
        $this->assertEquals('Test Company', $company->name);
        $this->assertEquals('target', $company->type);
        $this->assertIsArray($company->metadata);
        $this->assertEquals('1B', $company->metadata['revenue']);
        $this->assertEquals(5000, $company->metadata['employees']);
    }
    
    /**
     * Test get non-existent company
     * 
     * @covers ::get_company
     */
    public function test_get_company_not_found() {
        $this->expectException(\dml_missing_record_exception::class);
        $this->service->get_company(99999);
    }
    
    /**
     * Test metadata update
     * 
     * @covers ::update_metadata
     */
    public function test_update_metadata() {
        $companyid = $this->service->create_company('Test Company', 'customer', [
            'revenue' => '1B'
        ]);
        
        // Test merge mode
        $this->service->update_metadata($companyid, [
            'employees' => 5000,
            'founded' => 2000
        ]);
        
        $company = $this->service->get_company($companyid);
        $this->assertEquals('1B', $company->metadata['revenue']); // Original preserved
        $this->assertEquals(5000, $company->metadata['employees']); // New added
        $this->assertEquals(2000, $company->metadata['founded']); // New added
        
        // Test replace mode
        $this->service->update_metadata($companyid, [
            'new_field' => 'new_value'
        ], true);
        
        $company = $this->service->get_company($companyid);
        $this->assertArrayNotHasKey('revenue', $company->metadata);
        $this->assertEquals('new_value', $company->metadata['new_field']);
    }
    
    /**
     * Test freshness check
     * 
     * @covers ::is_fresh
     * @covers ::get_latest_snapshot
     */
    public function test_is_fresh() {
        global $DB;
        
        $companyid = $this->service->create_company('Test Company', 'customer');
        
        // No snapshot exists
        $this->assertFalse($this->service->is_fresh($companyid));
        
        // Create a run and snapshot
        $run = new \stdClass();
        $run->companyid = $companyid;
        $run->initiatedbyuserid = 2;
        $run->status = 'succeeded';
        $runid = $DB->insert_record('local_ci_run', $run);
        
        $snapshot = new \stdClass();
        $snapshot->companyid = $companyid;
        $snapshot->runid = $runid;
        $snapshot->snapshotjson = json_encode(['test' => 'data']);
        $snapshot->timecreated = time();
        $DB->insert_record('local_ci_snapshot', $snapshot);
        
        // Fresh snapshot
        $this->assertTrue($this->service->is_fresh($companyid));
        
        // Simulate old snapshot
        $DB->set_field('local_ci_snapshot', 'timecreated', time() - (40 * 86400), ['companyid' => $companyid]);
        $this->assertFalse($this->service->is_fresh($companyid));
    }
    
    /**
     * Test company deletion with cascade
     * 
     * @covers ::delete_company
     */
    public function test_delete_company() {
        global $DB;
        
        // Create company with related data
        $companyid = $this->service->create_company('Test Company', 'customer');
        
        // Add source
        $source = new \stdClass();
        $source->companyid = $companyid;
        $source->type = 'url';
        $source->title = 'Test Source';
        $source->addedbyuserid = 2;
        $source->approved = 1;
        $source->rejected = 0;
        $source->timecreated = time();
        $DB->insert_record('local_ci_source', $source);
        
        // Add run
        $run = new \stdClass();
        $run->companyid = $companyid;
        $run->initiatedbyuserid = 2;
        $run->status = 'succeeded';
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Add NB result
        $nbresult = new \stdClass();
        $nbresult->runid = $runid;
        $nbresult->nbcode = 'NB1';
        $nbresult->status = 'completed';
        $DB->insert_record('local_ci_nb_result', $nbresult);
        
        // Delete company
        $this->assertTrue($this->service->delete_company($companyid));
        
        // Verify cascade deletion
        $this->assertFalse($DB->record_exists('local_ci_company', ['id' => $companyid]));
        $this->assertFalse($DB->record_exists('local_ci_source', ['companyid' => $companyid]));
        $this->assertFalse($DB->record_exists('local_ci_run', ['id' => $runid]));
        $this->assertFalse($DB->record_exists('local_ci_nb_result', ['runid' => $runid]));
    }
    
    /**
     * Test transaction rollback on error
     * 
     * @covers ::create_company
     */
    public function test_transaction_rollback() {
        global $DB;
        
        // Mock a database error during insert
        // This would require more sophisticated mocking in real implementation
        $this->markTestIncomplete('Transaction rollback test requires DB mocking');
    }
}