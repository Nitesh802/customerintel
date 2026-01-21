<?php
/**
 * Company Service CRUD Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');

use local_customerintel\services\company_service;

/**
 * Test class for Company Service CRUD operations
 * 
 * @group local_customerintel
 * @group customerintel_company
 */
class company_service_test extends \advanced_testcase {
    
    /** @var company_service Service instance */
    private $service;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->service = new company_service();
    }
    
    /**
     * Test company creation
     * 
     * @covers \local_customerintel\services\company_service::create_company
     */
    public function test_create_company() {
        global $DB;
        
        // Test data
        $companydata = [
            'name' => 'Acme Corporation',
            'ticker' => 'ACME',
            'type' => 'customer',
            'metadata' => [
                'industry' => 'Technology',
                'employees' => 5000,
                'headquarters' => 'San Francisco, CA',
                'website' => 'https://acme.example.com'
            ]
        ];
        
        // Create company
        $companyid = $this->service->create_company(
            $companydata['name'],
            $companydata['ticker'],
            $companydata['type'],
            $companydata['metadata']
        );
        
        // Verify creation
        $this->assertIsInt($companyid);
        $this->assertGreaterThan(0, $companyid);
        
        // Verify stored data
        $company = $DB->get_record('local_ci_company', ['id' => $companyid]);
        $this->assertNotFalse($company);
        $this->assertEquals($companydata['name'], $company->name);
        $this->assertEquals($companydata['ticker'], $company->ticker);
        $this->assertEquals($companydata['type'], $company->type);
        
        // Verify metadata
        $metadata = json_decode($company->metadata, true);
        $this->assertEquals($companydata['metadata']['industry'], $metadata['industry']);
        $this->assertEquals($companydata['metadata']['employees'], $metadata['employees']);
    }
    
    /**
     * Test duplicate company prevention
     * 
     * @covers \local_customerintel\services\company_service::create_company
     */
    public function test_duplicate_company_prevention() {
        // Create first company
        $companyid1 = $this->service->create_company(
            'Duplicate Corp',
            'DUP',
            'customer'
        );
        
        // Attempt to create duplicate
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Company with ticker DUP already exists');
        
        $this->service->create_company(
            'Duplicate Corp Again',
            'DUP',
            'customer'
        );
    }
    
    /**
     * Test company update
     * 
     * @covers \local_customerintel\services\company_service::update_company
     */
    public function test_update_company() {
        global $DB;
        
        // Create company
        $companyid = $this->service->create_company(
            'Original Name',
            'ORIG',
            'customer',
            ['status' => 'active']
        );
        
        // Update company
        $updates = [
            'name' => 'Updated Name',
            'type' => 'target',
            'metadata' => [
                'status' => 'inactive',
                'updated_by' => 'test_user'
            ]
        ];
        
        $success = $this->service->update_company($companyid, $updates);
        $this->assertTrue($success);
        
        // Verify updates
        $company = $DB->get_record('local_ci_company', ['id' => $companyid]);
        $this->assertEquals('Updated Name', $company->name);
        $this->assertEquals('target', $company->type);
        
        $metadata = json_decode($company->metadata, true);
        $this->assertEquals('inactive', $metadata['status']);
        $this->assertEquals('test_user', $metadata['updated_by']);
        
        // Verify ticker unchanged
        $this->assertEquals('ORIG', $company->ticker);
    }
    
    /**
     * Test company retrieval
     * 
     * @covers \local_customerintel\services\company_service::get_company
     */
    public function test_get_company() {
        // Create test company
        $companyid = $this->service->create_company(
            'Test Company',
            'TEST',
            'customer',
            ['founded' => 1999]
        );
        
        // Get company
        $company = $this->service->get_company($companyid);
        
        // Verify retrieval
        $this->assertIsObject($company);
        $this->assertEquals($companyid, $company->id);
        $this->assertEquals('Test Company', $company->name);
        $this->assertEquals('TEST', $company->ticker);
        
        // Verify metadata parsing
        $this->assertIsArray($company->metadata);
        $this->assertEquals(1999, $company->metadata['founded']);
    }
    
    /**
     * Test getting non-existent company
     * 
     * @covers \local_customerintel\services\company_service::get_company
     */
    public function test_get_nonexistent_company() {
        $company = $this->service->get_company(999999);
        $this->assertNull($company);
    }
    
    /**
     * Test company deletion
     * 
     * @covers \local_customerintel\services\company_service::delete_company
     */
    public function test_delete_company() {
        global $DB;
        
        // Create company
        $companyid = $this->service->create_company(
            'To Delete',
            'DEL',
            'customer'
        );
        
        // Verify exists
        $exists = $DB->record_exists('local_ci_company', ['id' => $companyid]);
        $this->assertTrue($exists);
        
        // Delete company
        $success = $this->service->delete_company($companyid);
        $this->assertTrue($success);
        
        // Verify deleted
        $exists = $DB->record_exists('local_ci_company', ['id' => $companyid]);
        $this->assertFalse($exists);
    }
    
    /**
     * Test company search
     * 
     * @covers \local_customerintel\services\company_service::search_companies
     */
    public function test_search_companies() {
        // Create test companies
        $this->service->create_company('Apple Inc.', 'AAPL', 'customer');
        $this->service->create_company('Microsoft Corporation', 'MSFT', 'target');
        $this->service->create_company('Amazon.com Inc.', 'AMZN', 'customer');
        $this->service->create_company('Alphabet Inc.', 'GOOGL', 'both');
        
        // Test search by name
        $results = $this->service->search_companies('Inc');
        $this->assertCount(3, $results);
        
        // Test search by type
        $results = $this->service->search_companies(null, 'customer');
        $this->assertCount(2, $results);
        
        // Test search by ticker
        $results = $this->service->search_companies('MSFT');
        $this->assertCount(1, $results);
        $this->assertEquals('Microsoft Corporation', $results[0]->name);
        
        // Test combined search
        $results = $this->service->search_companies('Inc', 'customer');
        $this->assertCount(2, $results);
    }
    
    /**
     * Test getting recent companies
     * 
     * @covers \local_customerintel\services\company_service::get_recent_companies
     */
    public function test_get_recent_companies() {
        // Create companies with delays
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $this->service->create_company(
                "Company $i",
                "CO$i",
                'customer'
            );
            sleep(1); // Ensure different timestamps
        }
        
        // Get recent companies
        $recent = $this->service->get_recent_companies(3);
        
        // Verify count and order
        $this->assertCount(3, $recent);
        $this->assertEquals('Company 5', $recent[0]->name);
        $this->assertEquals('Company 4', $recent[1]->name);
        $this->assertEquals('Company 3', $recent[2]->name);
    }
    
    /**
     * Test company metadata operations
     * 
     * @covers \local_customerintel\services\company_service::update_metadata
     */
    public function test_company_metadata_operations() {
        // Create company with metadata
        $companyid = $this->service->create_company(
            'Meta Company',
            'META',
            'customer',
            [
                'industry' => 'Tech',
                'size' => 'Large',
                'tags' => ['innovation', 'growth']
            ]
        );
        
        // Update metadata
        $success = $this->service->update_metadata($companyid, [
            'industry' => 'Technology', // Update existing
            'region' => 'North America', // Add new
            'tags' => ['innovation', 'growth', 'AI'] // Update array
        ]);
        
        $this->assertTrue($success);
        
        // Verify metadata updates
        $company = $this->service->get_company($companyid);
        $this->assertEquals('Technology', $company->metadata['industry']);
        $this->assertEquals('Large', $company->metadata['size']); // Unchanged
        $this->assertEquals('North America', $company->metadata['region']); // New
        $this->assertCount(3, $company->metadata['tags']);
        $this->assertContains('AI', $company->metadata['tags']);
    }
    
    /**
     * Data provider for company validation tests
     */
    public function invalid_company_data_provider(): array {
        return [
            'empty_name' => ['', 'TICK', 'customer', 'Company name is required'],
            'empty_ticker' => ['Company', '', 'customer', 'Ticker symbol is required'],
            'invalid_type' => ['Company', 'TICK', 'invalid', 'Invalid company type'],
            'long_ticker' => ['Company', 'VERYLONGTICKER', 'customer', 'Ticker symbol too long'],
            'invalid_chars_ticker' => ['Company', 'TI-CK', 'customer', 'Invalid ticker symbol format']
        ];
    }
    
    /**
     * Test company validation
     * 
     * @dataProvider invalid_company_data_provider
     * @covers \local_customerintel\services\company_service::create_company
     */
    public function test_company_validation($name, $ticker, $type, $expected_error) {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage($expected_error);
        
        $this->service->create_company($name, $ticker, $type);
    }
    
    /**
     * Test company with sources relationship
     * 
     * @covers \local_customerintel\services\company_service::get_company_with_sources
     */
    public function test_company_with_sources() {
        global $DB;
        
        // Create company
        $companyid = $this->service->create_company('Source Company', 'SRC', 'customer');
        
        // Add sources
        $source1 = new \stdClass();
        $source1->companyid = $companyid;
        $source1->sourcetype = 'url';
        $source1->title = 'Company Website';
        $source1->url = 'https://example.com';
        $source1->approved = 1;
        $source1->timecreated = time();
        $DB->insert_record('local_ci_source', $source1);
        
        $source2 = new \stdClass();
        $source2->companyid = $companyid;
        $source2->sourcetype = 'file';
        $source2->title = 'Annual Report';
        $source2->uploadedfilename = 'report.pdf';
        $source2->approved = 1;
        $source2->timecreated = time();
        $DB->insert_record('local_ci_source', $source2);
        
        // Get company with sources
        $company = $this->service->get_company_with_sources($companyid);
        
        $this->assertIsObject($company);
        $this->assertObjectHasAttribute('sources', $company);
        $this->assertCount(2, $company->sources);
        
        // Verify source details
        $sources = array_values($company->sources);
        $this->assertEquals('Company Website', $sources[0]->title);
        $this->assertEquals('Annual Report', $sources[1]->title);
    }
    
    /**
     * Test company freshness check
     * 
     * @covers \local_customerintel\services\company_service::check_data_freshness
     */
    public function test_check_data_freshness() {
        global $DB;
        
        // Create company
        $companyid = $this->service->create_company('Fresh Company', 'FRSH', 'customer');
        
        // No runs - data is stale
        $freshness = $this->service->check_data_freshness($companyid);
        $this->assertFalse($freshness['is_fresh']);
        $this->assertNull($freshness['last_run_date']);
        
        // Add a recent run (within 30 days)
        $run = new \stdClass();
        $run->companyid = $companyid;
        $run->initiatedbyuserid = 2;
        $run->status = 'completed';
        $run->mode = 'full';
        $run->timecompleted = time() - (7 * 24 * 60 * 60); // 7 days ago
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Check freshness - should be fresh
        $freshness = $this->service->check_data_freshness($companyid);
        $this->assertTrue($freshness['is_fresh']);
        $this->assertNotNull($freshness['last_run_date']);
        $this->assertEquals(7, $freshness['days_old']);
        
        // Update run to be old (more than 30 days)
        $DB->set_field('local_ci_run', 'timecompleted', 
            time() - (35 * 24 * 60 * 60), ['id' => $runid]);
        
        // Check freshness - should be stale
        $freshness = $this->service->check_data_freshness($companyid, 30);
        $this->assertFalse($freshness['is_fresh']);
        $this->assertEquals(35, $freshness['days_old']);
    }
}