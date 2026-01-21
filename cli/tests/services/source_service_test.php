<?php
/**
 * Source Service Unit Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests\services;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/source_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');

use local_customerintel\services\source_service;
use local_customerintel\services\company_service;

/**
 * Source Service test cases
 * 
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_customerintel\services\source_service
 */
class source_service_test extends \advanced_testcase {
    
    /** @var source_service Service instance */
    protected $sourceservice;
    
    /** @var company_service Company service */
    protected $companyservice;
    
    /** @var int Test company ID */
    protected $companyid;
    
    /** @var int Test user ID */
    protected $userid;
    
    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->sourceservice = new source_service();
        $this->companyservice = new company_service();
        
        // Create test company
        $this->companyid = $this->companyservice->create_company('Test Company', 'customer');
        
        // Create test user
        $this->userid = $this->getDataGenerator()->create_user()->id;
    }
    
    /**
     * Test add URL source
     * 
     * @covers ::add_url_source
     */
    public function test_add_url_source() {
        global $DB;
        
        $url = 'https://example.com/article';
        $sourceid = $this->sourceservice->add_url_source(
            $this->companyid,
            $url,
            $this->userid,
            'Test Article'
        );
        
        $this->assertIsInt($sourceid);
        $this->assertGreaterThan(0, $sourceid);
        
        // Verify in database
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals($this->companyid, $source->companyid);
        $this->assertEquals('url', $source->type);
        $this->assertEquals('Test Article', $source->title);
        $this->assertEquals($url, $source->url);
        $this->assertEquals($this->userid, $source->addedbyuserid);
        $this->assertEquals(1, $source->approved);
        
        // Test duplicate URL
        $duplicateid = $this->sourceservice->add_url_source(
            $this->companyid,
            $url,
            $this->userid
        );
        $this->assertEquals($sourceid, $duplicateid);
    }
    
    /**
     * Test invalid URL
     * 
     * @covers ::add_url_source
     */
    public function test_add_invalid_url() {
        $this->expectException(\invalid_parameter_exception::class);
        $this->sourceservice->add_url_source(
            $this->companyid,
            'not-a-valid-url',
            $this->userid
        );
    }
    
    /**
     * Test domain filtering
     * 
     * @covers ::add_url_source
     * @covers ::is_domain_allowed
     */
    public function test_domain_filtering() {
        // Set up deny list
        set_config('domains_deny', "badsite.com\nspam.org", 'local_customerintel');
        
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Domain not allowed');
        
        $this->sourceservice->add_url_source(
            $this->companyid,
            'https://badsite.com/article',
            $this->userid
        );
    }
    
    /**
     * Test add manual source
     * 
     * @covers ::add_manual_source
     */
    public function test_add_manual_source() {
        global $DB;
        
        $text = 'This is manual content for testing.';
        $sourceid = $this->sourceservice->add_manual_source(
            $this->companyid,
            $text,
            'Manual Entry',
            $this->userid
        );
        
        $this->assertIsInt($sourceid);
        
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals('manual_text', $source->type);
        $this->assertEquals('Manual Entry', $source->title);
        $this->assertEquals(sha1($text), $source->hash);
        
        // Test duplicate detection
        $duplicateid = $this->sourceservice->add_manual_source(
            $this->companyid,
            $text,
            'Different Title',
            $this->userid
        );
        $this->assertEquals($sourceid, $duplicateid);
    }
    
    /**
     * Test get company sources
     * 
     * @covers ::get_company_sources
     */
    public function test_get_company_sources() {
        // Add multiple sources
        $url1 = $this->sourceservice->add_url_source(
            $this->companyid,
            'https://example1.com',
            $this->userid
        );
        
        $manual1 = $this->sourceservice->add_manual_source(
            $this->companyid,
            'Manual content',
            'Manual 1',
            $this->userid
        );
        
        $url2 = $this->sourceservice->add_url_source(
            $this->companyid,
            'https://example2.com',
            $this->userid
        );
        
        // Reject one source
        $this->sourceservice->update_approval($url2, false);
        
        // Get all sources
        $sources = $this->sourceservice->get_company_sources($this->companyid);
        $this->assertCount(3, $sources);
        
        // Get approved only
        $approved = $this->sourceservice->get_company_sources($this->companyid, true);
        $this->assertCount(2, $approved);
        
        // Verify user data is joined
        $source = reset($sources);
        $this->assertObjectHasAttribute('firstname', $source);
        $this->assertObjectHasAttribute('lastname', $source);
        $this->assertObjectHasAttribute('email', $source);
    }
    
    /**
     * Test source approval/rejection
     * 
     * @covers ::update_approval
     */
    public function test_update_approval() {
        global $DB;
        
        $sourceid = $this->sourceservice->add_url_source(
            $this->companyid,
            'https://example.com',
            $this->userid
        );
        
        // Initially approved
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals(1, $source->approved);
        $this->assertEquals(0, $source->rejected);
        
        // Reject
        $this->assertTrue($this->sourceservice->update_approval($sourceid, false));
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals(0, $source->approved);
        $this->assertEquals(1, $source->rejected);
        
        // Re-approve
        $this->assertTrue($this->sourceservice->update_approval($sourceid, true));
        $source = $DB->get_record('local_ci_source', ['id' => $sourceid]);
        $this->assertEquals(1, $source->approved);
        $this->assertEquals(0, $source->rejected);
    }
    
    /**
     * Test bulk approval
     * 
     * @covers ::bulk_update_approval
     */
    public function test_bulk_update_approval() {
        $sourceids = [];
        
        // Create multiple sources
        for ($i = 1; $i <= 5; $i++) {
            $sourceids[] = $this->sourceservice->add_url_source(
                $this->companyid,
                "https://example{$i}.com",
                $this->userid
            );
        }
        
        // Bulk reject
        $updated = $this->sourceservice->bulk_update_approval($sourceids, false);
        $this->assertEquals(5, $updated);
        
        // Verify all rejected
        $sources = $this->sourceservice->get_company_sources($this->companyid, true);
        $this->assertCount(0, $sources);
        
        // Bulk approve subset
        $subset = array_slice($sourceids, 0, 3);
        $updated = $this->sourceservice->bulk_update_approval($subset, true);
        $this->assertEquals(3, $updated);
        
        $sources = $this->sourceservice->get_company_sources($this->companyid, true);
        $this->assertCount(3, $sources);
    }
    
    /**
     * Test delete source
     * 
     * @covers ::delete_source
     */
    public function test_delete_source() {
        global $DB;
        
        $sourceid = $this->sourceservice->add_url_source(
            $this->companyid,
            'https://example.com',
            $this->userid
        );
        
        $this->assertTrue($DB->record_exists('local_ci_source', ['id' => $sourceid]));
        
        $this->assertTrue($this->sourceservice->delete_source($sourceid));
        
        $this->assertFalse($DB->record_exists('local_ci_source', ['id' => $sourceid]));
    }
    
    /**
     * Test file source (stub)
     * 
     * @covers ::add_file_source
     */
    public function test_add_file_source() {
        $this->markTestIncomplete('File upload testing requires Moodle file API mocking');
        
        // Would test:
        // - PDF text extraction
        // - DOCX text extraction
        // - Hash computation for deduplication
        // - File storage via Moodle Files API
    }
    
    /**
     * Test source discovery (stub)
     * 
     * @covers ::discover_sources
     */
    public function test_discover_sources() {
        $this->markTestIncomplete('Source discovery requires Perplexity API mocking');
        
        // Would test:
        // - Perplexity API integration
        // - Domain filtering on discovered sources
        // - Automatic source creation
    }
    
    /**
     * Test text extraction (stub)
     * 
     * @covers ::extract_text
     */
    public function test_extract_text() {
        $this->markTestIncomplete('Text extraction requires file processing libraries');
        
        // Would test:
        // - PDF text extraction
        // - DOCX text extraction
        // - Text sanitization
    }
    
    /**
     * Test text chunking (stub)
     * 
     * @covers ::chunk_text
     */
    public function test_chunk_text() {
        $this->markTestIncomplete('Text chunking implementation needed');
        
        // Would test:
        // - Chunk size limits
        // - Overlap between chunks
        // - Paragraph boundary preservation
    }
    
    /**
     * Test chunk retrieval (stub)
     * 
     * @covers ::retrieve_chunks
     */
    public function test_retrieve_chunks() {
        $this->markTestIncomplete('Chunk retrieval requires full implementation');
        
        // Would test:
        // - Relevance ranking
        // - K-best selection
        // - Citation tracking
    }
}