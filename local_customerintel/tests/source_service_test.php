<?php
/**
 * Source Service CRUD Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/source_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');

use local_customerintel\services\source_service;
use local_customerintel\services\company_service;

/**
 * Test class for Source Service CRUD operations
 * 
 * @group local_customerintel
 * @group customerintel_source
 */
class source_service_test extends \advanced_testcase {
    
    /** @var source_service Service instance */
    private $source_service;
    
    /** @var company_service Company service */
    private $company_service;
    
    /** @var int Test company ID */
    private $test_company_id;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->source_service = new source_service();
        $this->company_service = new company_service();
        
        // Create test company
        $this->test_company_id = $this->company_service->create_company(
            'Test Company for Sources',
            'SRCT',
            'customer'
        );
    }
    
    /**
     * Test URL source creation
     * 
     * @covers \local_customerintel\services\source_service::add_url_source
     */
    public function test_add_url_source() {
        global $DB;
        
        $sourcedata = [
            'url' => 'https://example.com/investor-relations',
            'title' => 'Investor Relations Page',
            'description' => 'Company investor relations information'
        ];
        
        // Add URL source
        $sourceid = $this->source_service->add_url_source(
            $this->test_company_id,
            $sourcedata['url'],
            $sourcedata['title'],
            $sourcedata['description']
        );
        
        // Verify creation
        $this->assertIsInt($sourceid);
        $this->assertGreaterThan(0, $sourceid);
        
        // Verify stored data
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertNotFalse($source);
        $this->assertEquals($this->test_company_id, $source->companyid);
        $this->assertEquals('url', $source->sourcetype);
        $this->assertEquals($sourcedata['url'], $source->url);
        $this->assertEquals($sourcedata['title'], $source->title);
        $this->assertEquals($sourcedata['description'], $source->description);
        $this->assertEquals(1, $source->approved); // Should be auto-approved
        
        // Verify hash was generated
        $this->assertNotEmpty($source->hash);
    }
    
    /**
     * Test file source upload
     * 
     * @covers \local_customerintel\services\source_service::upload_file_source
     */
    public function test_upload_file_source() {
        global $DB;
        
        // Create mock file upload
        $filedata = [
            'filename' => 'annual_report_2023.pdf',
            'content' => 'Mock PDF content for testing',
            'mimetype' => 'application/pdf',
            'title' => 'Annual Report 2023'
        ];
        
        // Mock file upload
        $sourceid = $this->source_service->upload_file_source(
            $this->test_company_id,
            $filedata['filename'],
            $filedata['content'],
            $filedata['title']
        );
        
        // Verify creation
        $this->assertIsInt($sourceid);
        
        // Verify stored data
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals('file', $source->sourcetype);
        $this->assertEquals($filedata['filename'], $source->uploadedfilename);
        $this->assertEquals($filedata['title'], $source->title);
        $this->assertNotEmpty($source->hash);
    }
    
    /**
     * Test text source creation
     * 
     * @covers \local_customerintel\services\source_service::add_text_source
     */
    public function test_add_text_source() {
        global $DB;
        
        $textdata = [
            'title' => 'Executive Summary',
            'content' => 'This is the executive summary of the company strategy for 2024.',
            'description' => 'Key strategic initiatives'
        ];
        
        // Add text source
        $sourceid = $this->source_service->add_text_source(
            $this->test_company_id,
            $textdata['title'],
            $textdata['content'],
            $textdata['description']
        );
        
        // Verify creation
        $this->assertIsInt($sourceid);
        
        // Verify stored data
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals('text', $source->sourcetype);
        $this->assertEquals($textdata['title'], $source->title);
        $this->assertEquals($textdata['content'], $source->extractedtext);
        $this->assertEquals($textdata['description'], $source->description);
    }
    
    /**
     * Test duplicate source detection
     * 
     * @covers \local_customerintel\services\source_service::check_duplicate
     */
    public function test_duplicate_source_detection() {
        // Add first source
        $url = 'https://example.com/duplicate-test';
        $sourceid1 = $this->source_service->add_url_source(
            $this->test_company_id,
            $url,
            'First Source'
        );
        
        // Attempt to add duplicate
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('duplicate source');
        
        $this->source_service->add_url_source(
            $this->test_company_id,
            $url,
            'Duplicate Source'
        );
    }
    
    /**
     * Test source approval workflow
     * 
     * @covers \local_customerintel\services\source_service::approve_source
     * @covers \local_customerintel\services\source_service::reject_source
     */
    public function test_source_approval_workflow() {
        global $DB;
        
        // Create unapproved source
        $sourceid = $DB->insert_record('local_ci_source', [
            'companyid' => $this->test_company_id,
            'sourcetype' => 'url',
            'url' => 'https://example.com/unapproved',
            'title' => 'Unapproved Source',
            'approved' => 0,
            'timecreated' => time()
        ]);
        
        // Verify initially unapproved
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals(0, $source->approved);
        
        // Approve source
        $success = $this->source_service->approve_source($sourceid);
        $this->assertTrue($success);
        
        // Verify approved
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals(1, $source->approved);
        
        // Reject source
        $success = $this->source_service->reject_source($sourceid, 'Not relevant');
        $this->assertTrue($success);
        
        // Verify rejected
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals(-1, $source->approved);
    }
    
    /**
     * Test getting sources by company
     * 
     * @covers \local_customerintel\services\source_service::get_company_sources
     */
    public function test_get_company_sources() {
        // Add multiple sources
        $this->source_service->add_url_source(
            $this->test_company_id,
            'https://example.com/page1',
            'Page 1'
        );
        
        $this->source_service->add_url_source(
            $this->test_company_id,
            'https://example.com/page2',
            'Page 2'
        );
        
        $this->source_service->add_text_source(
            $this->test_company_id,
            'Text Source',
            'Some content'
        );
        
        // Get all sources
        $sources = $this->source_service->get_company_sources($this->test_company_id);
        $this->assertCount(3, $sources);
        
        // Get only URL sources
        $urlsources = $this->source_service->get_company_sources(
            $this->test_company_id,
            'url'
        );
        $this->assertCount(2, $urlsources);
        
        // Get only approved sources
        $approved = $this->source_service->get_company_sources(
            $this->test_company_id,
            null,
            true
        );
        $this->assertCount(3, $approved); // All should be auto-approved
    }
    
    /**
     * Test source deletion
     * 
     * @covers \local_customerintel\services\source_service::delete_source
     */
    public function test_delete_source() {
        global $DB;
        
        // Create source
        $sourceid = $this->source_service->add_url_source(
            $this->test_company_id,
            'https://example.com/to-delete',
            'To Delete'
        );
        
        // Verify exists
        $exists = $DB->record_exists('local_ci_source', ['id' => $sourceid]);
        $this->assertTrue($exists);
        
        // Delete source
        $success = $this->source_service->delete_source($sourceid);
        $this->assertTrue($success);
        
        // Verify deleted
        $exists = $DB->record_exists('local_ci_source', ['id' => $sourceid]);
        $this->assertFalse($exists);
    }
    
    /**
     * Test text extraction from sources
     * 
     * @covers \local_customerintel\services\source_service::extract_text
     */
    public function test_text_extraction() {
        // Add URL source
        $sourceid = $this->source_service->add_url_source(
            $this->test_company_id,
            'https://example.com/extract-test',
            'Extract Test'
        );
        
        // Mock extracted text
        $extractedtext = "This is the extracted text from the webpage.\nIt contains multiple lines.\nAnd important information.";
        
        // Update source with extracted text
        $success = $this->source_service->update_extracted_text($sourceid, $extractedtext);
        $this->assertTrue($success);
        
        // Verify text extraction
        $source = $this->source_service->get_source($sourceid);
        $this->assertEquals($extractedtext, $source->extractedtext);
        
        // Test word count
        $wordcount = str_word_count($extractedtext);
        $this->assertEquals(13, $wordcount);
    }
    
    /**
     * Test source discovery
     * 
     * @covers \local_customerintel\services\source_service::discover_sources
     */
    public function test_source_discovery() {
        // Mock discovered sources
        $discovered = $this->source_service->discover_sources($this->test_company_id);
        
        // Should return array of potential sources
        $this->assertIsArray($discovered);
        
        // Each discovered source should have required fields
        if (!empty($discovered)) {
            $first = $discovered[0];
            $this->assertObjectHasAttribute('type', $first);
            $this->assertObjectHasAttribute('url', $first);
            $this->assertObjectHasAttribute('title', $first);
            $this->assertObjectHasAttribute('confidence', $first);
        }
    }
    
    /**
     * Test source metadata
     * 
     * @covers \local_customerintel\services\source_service::update_metadata
     */
    public function test_source_metadata() {
        global $DB;
        
        // Create source
        $sourceid = $this->source_service->add_url_source(
            $this->test_company_id,
            'https://example.com/metadata-test',
            'Metadata Test'
        );
        
        // Add metadata
        $metadata = [
            'last_crawled' => time(),
            'content_type' => 'investor_presentation',
            'language' => 'en',
            'page_count' => 42,
            'tags' => ['quarterly', 'earnings', 'guidance']
        ];
        
        $success = $this->source_service->update_metadata($sourceid, $metadata);
        $this->assertTrue($success);
        
        // Verify metadata
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $stored_metadata = json_decode($source->metadata, true);
        
        $this->assertEquals($metadata['content_type'], $stored_metadata['content_type']);
        $this->assertEquals($metadata['language'], $stored_metadata['language']);
        $this->assertEquals($metadata['page_count'], $stored_metadata['page_count']);
        $this->assertCount(3, $stored_metadata['tags']);
    }
    
    /**
     * Test source validation
     */
    public function test_source_validation() {
        // Test invalid URL
        $this->expectException(\invalid_parameter_exception::class);
        $this->source_service->add_url_source(
            $this->test_company_id,
            'not-a-valid-url',
            'Invalid URL'
        );
    }
    
    /**
     * Test bulk source operations
     * 
     * @covers \local_customerintel\services\source_service::bulk_approve
     * @covers \local_customerintel\services\source_service::bulk_delete
     */
    public function test_bulk_source_operations() {
        global $DB;
        
        // Create multiple sources
        $sourceids = [];
        for ($i = 1; $i <= 5; $i++) {
            $sourceids[] = $DB->insert_record('local_ci_source', [
                'companyid' => $this->test_company_id,
                'sourcetype' => 'url',
                'url' => "https://example.com/bulk-$i",
                'title' => "Bulk Source $i",
                'approved' => 0,
                'timecreated' => time()
            ]);
        }
        
        // Bulk approve first 3
        $to_approve = array_slice($sourceids, 0, 3);
        $approved_count = $this->source_service->bulk_approve($to_approve);
        $this->assertEquals(3, $approved_count);
        
        // Verify approval
        foreach ($to_approve as $id) {
            $source = $DB->get_record('local_ci_source', ['id' => $id]);
            $this->assertEquals(1, $source->approved);
        }
        
        // Bulk delete last 2
        $to_delete = array_slice($sourceids, 3, 2);
        $deleted_count = $this->source_service->bulk_delete($to_delete);
        $this->assertEquals(2, $deleted_count);
        
        // Verify deletion
        foreach ($to_delete as $id) {
            $exists = $DB->record_exists('local_ci_source', ['id' => $id]);
            $this->assertFalse($exists);
        }
    }
    
    /**
     * Test source freshness
     * 
     * @covers \local_customerintel\services\source_service::check_source_freshness
     */
    public function test_source_freshness() {
        global $DB;
        
        // Create source with old timestamp
        $old_time = time() - (60 * 24 * 60 * 60); // 60 days ago
        
        $sourceid = $DB->insert_record('local_ci_source', [
            'companyid' => $this->test_company_id,
            'sourcetype' => 'url',
            'url' => 'https://example.com/old-source',
            'title' => 'Old Source',
            'approved' => 1,
            'timecreated' => $old_time,
            'timemodified' => $old_time
        ]);
        
        // Check freshness
        $is_fresh = $this->source_service->check_source_freshness($sourceid, 30);
        $this->assertFalse($is_fresh);
        
        // Update source
        $DB->set_field('local_ci_source', 'timemodified', time(), ['id' => $sourceid]);
        
        // Check freshness again
        $is_fresh = $this->source_service->check_source_freshness($sourceid, 30);
        $this->assertTrue($is_fresh);
    }
}