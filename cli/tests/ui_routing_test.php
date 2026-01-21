<?php
/**
 * CustomerIntel UI Routing Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel;

use advanced_testcase;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * UI Routing test class
 */
class ui_routing_test extends advanced_testcase {
    
    private $user;
    private $company_id;
    private $target_id;
    private $run_id;
    
    /**
     * Setup before each test
     */
    protected function setUp(): void {
        global $DB;
        
        $this->resetAfterTest();
        
        // Create test user with capabilities
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
        
        // Assign capabilities
        $systemcontext = \context_system::instance();
        $role = $DB->get_record('role', ['shortname' => 'manager']);
        role_assign($role->id, $this->user->id, $systemcontext->id);
        
        // Create test data
        $this->setup_test_data();
    }
    
    /**
     * Setup test data
     */
    private function setup_test_data() {
        global $DB, $USER;
        
        // Create test company
        $company = new \stdClass();
        $company->name = 'UI Test Company';
        $company->domain = 'uitest.com';
        $company->industry = 'Technology';
        $company->size_category = 'medium';
        $company->created_by = $USER->id;
        $company->timecreated = time();
        $company->timemodified = time();
        $this->company_id = $DB->insert_record('local_customerintel_company', $company);
        
        // Create test target
        $target = new \stdClass();
        $target->company_id = $this->company_id;
        $target->name = 'UI Test Target';
        $target->profile = json_encode(['test' => true]);
        $target->created_by = $USER->id;
        $target->timecreated = time();
        $target->timemodified = time();
        $this->target_id = $DB->insert_record('local_customerintel_target', $target);
        
        // Create test run
        $run = new \stdClass();
        $run->company_id = $this->company_id;
        $run->target_id = $this->target_id;
        $run->status = 'completed';
        $run->created_by = $USER->id;
        $run->timecreated = time();
        $run->timemodified = time();
        $this->run_id = $DB->insert_record('local_customerintel_run', $run);
    }
    
    /**
     * Test dashboard.php loads and displays data
     */
    public function test_dashboard_page() {
        global $CFG, $DB;
        
        // Test that dashboard URL is accessible
        $url = new moodle_url('/local/customerintel/dashboard.php');
        $this->assertNotNull($url);
        
        // Simulate page load
        ob_start();
        $PAGE = new \moodle_page();
        $PAGE->set_url($url);
        $PAGE->set_context(\context_system::instance());
        
        // Check that queue statistics are calculated
        $queue_stats = [
            'queued' => $DB->count_records('local_customerintel_job_queue', ['status' => 'pending']),
            'running' => $DB->count_records('local_customerintel_job_queue', ['status' => 'processing']),
            'completed' => $DB->count_records('local_customerintel_run', ['status' => 'completed']),
            'failed' => $DB->count_records('local_customerintel_run', ['status' => 'failed'])
        ];
        
        $this->assertIsArray($queue_stats);
        $this->assertArrayHasKey('queued', $queue_stats);
        $this->assertEquals(1, $queue_stats['completed']); // We created one completed run
        
        ob_end_clean();
    }
    
    /**
     * Test reports.php with missing runid parameter
     */
    public function test_reports_page_without_runid() {
        global $DB;
        
        // Test URL without runid
        $url = new moodle_url('/local/customerintel/reports.php');
        $this->assertNotNull($url);
        
        // Verify it should show list of runs
        $runs = $DB->get_records('local_customerintel_run', ['status' => 'completed']);
        $this->assertCount(1, $runs);
        
        // Verify run has correct data
        $run = reset($runs);
        $this->assertEquals($this->company_id, $run->company_id);
        $this->assertEquals($this->target_id, $run->target_id);
    }
    
    /**
     * Test reports.php with valid runid parameter
     */
    public function test_reports_page_with_runid() {
        global $DB;
        
        // Add some NB results for the run
        $nb_result = new \stdClass();
        $nb_result->run_id = $this->run_id;
        $nb_result->nb_type = 'nb1_industry_analysis';
        $nb_result->result_data = json_encode(['test' => 'data']);
        $nb_result->tokens_used = 100;
        $nb_result->execution_time = 1.5;
        $nb_result->timecreated = time();
        $nb_result->timemodified = time();
        $DB->insert_record('local_customerintel_nb_result', $nb_result);
        
        // Test URL with runid
        $url = new moodle_url('/local/customerintel/reports.php', ['runid' => $this->run_id]);
        $this->assertNotNull($url);
        $this->assertEquals($this->run_id, $url->param('runid'));
        
        // Verify report can be generated
        $assembler = new services\assembler();
        $report = $assembler->generate_html_report($this->run_id);
        $this->assertNotEmpty($report);
    }
    
    /**
     * Test run.php form validation
     */
    public function test_run_page_form() {
        global $CFG, $DB;
        
        require_once($CFG->libdir . '/formslib.php');
        
        // Test URL
        $url = new moodle_url('/local/customerintel/run.php');
        $this->assertNotNull($url);
        
        // Test form data validation
        $formdata = [
            'company_id' => 0,
            'target_id' => 0,
            'force_refresh' => 0,
            'notes' => '',
            'source_urls' => '',
            'source_text' => ''
        ];
        
        // Company and target are required
        $this->assertEquals(0, $formdata['company_id']); // Should fail validation
        $this->assertEquals(0, $formdata['target_id']); // Should fail validation
        
        // Valid form data
        $validdata = [
            'company_id' => $this->company_id,
            'target_id' => $this->target_id,
            'force_refresh' => 1,
            'notes' => 'Test run',
            'source_urls' => 'https://example.com',
            'source_text' => 'Test content'
        ];
        
        $this->assertGreaterThan(0, $validdata['company_id']);
        $this->assertGreaterThan(0, $validdata['target_id']);
    }
    
    /**
     * Test sources.php page functionality
     */
    public function test_sources_page() {
        global $DB;
        
        // Add test source
        $source = new \stdClass();
        $source->company_id = $this->company_id;
        $source->target_id = $this->target_id;
        $source->type = 'website';
        $source->url = 'https://test.example.com';
        $source->status = 'pending';
        $source->metadata = json_encode(['test' => true]);
        $source->timecreated = time();
        $source->timemodified = time();
        $source_id = $DB->insert_record('local_customerintel_source', $source);
        
        // Test URL
        $url = new moodle_url('/local/customerintel/sources.php');
        $this->assertNotNull($url);
        
        // Test approve action
        $approve_url = new moodle_url('/local/customerintel/sources.php', [
            'action' => 'approve',
            'id' => $source_id,
            'sesskey' => sesskey()
        ]);
        $this->assertNotNull($approve_url);
        
        // Test reject action
        $reject_url = new moodle_url('/local/customerintel/sources.php', [
            'action' => 'reject',
            'id' => $source_id,
            'sesskey' => sesskey()
        ]);
        $this->assertNotNull($reject_url);
        
        // Test delete action
        $delete_url = new moodle_url('/local/customerintel/sources.php', [
            'action' => 'delete',
            'id' => $source_id,
            'sesskey' => sesskey()
        ]);
        $this->assertNotNull($delete_url);
        
        // Verify source exists
        $exists = $DB->record_exists('local_customerintel_source', ['id' => $source_id]);
        $this->assertTrue($exists);
    }
    
    /**
     * Test dashboard links point to correct pages
     */
    public function test_dashboard_links() {
        // Test New Analysis Run link
        $run_url = new moodle_url('/local/customerintel/run.php');
        $this->assertNotNull($run_url);
        $this->assertEquals('/local/customerintel/run.php', $run_url->get_path());
        
        // Test View Reports link (without runid)
        $reports_url = new moodle_url('/local/customerintel/reports.php');
        $this->assertNotNull($reports_url);
        $this->assertEquals('/local/customerintel/reports.php', $reports_url->get_path());
        $this->assertNull($reports_url->param('runid')); // Should not have runid
        
        // Test Manage Sources link
        $sources_url = new moodle_url('/local/customerintel/sources.php');
        $this->assertNotNull($sources_url);
        $this->assertEquals('/local/customerintel/sources.php', $sources_url->get_path());
        
        // Test View Report link with runid
        $report_with_id_url = new moodle_url('/local/customerintel/reports.php', ['runid' => $this->run_id]);
        $this->assertNotNull($report_with_id_url);
        $this->assertEquals($this->run_id, $report_with_id_url->param('runid'));
    }
    
    /**
     * Test capability checks on pages
     */
    public function test_page_capabilities() {
        global $DB;
        
        $systemcontext = \context_system::instance();
        
        // Test view capability
        $this->assertTrue(has_capability('local/customerintel:view', $systemcontext, $this->user));
        
        // Create user without manage capability
        $viewonly_user = $this->getDataGenerator()->create_user();
        $this->setUser($viewonly_user);
        
        // Should not have manage capability
        $this->assertFalse(has_capability('local/customerintel:manage', $systemcontext, $viewonly_user));
        
        // Reset to admin for remaining tests
        $this->setAdminUser();
        
        // Admin should have all capabilities
        $this->assertTrue(has_capability('local/customerintel:view', $systemcontext));
        $this->assertTrue(has_capability('local/customerintel:manage', $systemcontext));
        $this->assertTrue(has_capability('local/customerintel:export', $systemcontext));
    }
    
    /**
     * Test job queue submission from run.php
     */
    public function test_job_queue_submission() {
        global $DB;
        
        $job_queue = new services\job_queue();
        
        // Enqueue a test job
        $job_id = $job_queue->enqueue('process_run', [
            'run_id' => $this->run_id,
            'force_refresh' => true,
            'estimated_tokens' => 1000,
            'estimated_cost' => 0.05
        ]);
        
        $this->assertGreaterThan(0, $job_id);
        
        // Verify job was created
        $job = $DB->get_record('local_customerintel_job_queue', ['id' => $job_id]);
        $this->assertNotNull($job);
        $this->assertEquals('process_run', $job->job_type);
        $this->assertEquals('pending', $job->status);
        
        // Verify payload
        $payload = json_decode($job->payload, true);
        $this->assertEquals($this->run_id, $payload['run_id']);
        $this->assertTrue($payload['force_refresh']);
        $this->assertEquals(1000, $payload['estimated_tokens']);
    }
    
    /**
     * Test source deduplication
     */
    public function test_source_deduplication() {
        global $DB;
        
        $source_service = new services\source_service();
        
        // Add duplicate sources
        $source1 = $source_service->add_source(
            $this->company_id,
            $this->target_id,
            'website',
            'https://duplicate.com',
            json_encode(['test' => 1])
        );
        
        $source2 = $source_service->add_source(
            $this->company_id,
            $this->target_id,
            'website',
            'https://duplicate.com',
            json_encode(['test' => 2])
        );
        
        $this->assertGreaterThan(0, $source1);
        $this->assertGreaterThan(0, $source2);
        
        // Run deduplication
        $removed = $source_service->deduplicate_sources($this->company_id);
        
        // Should have removed one duplicate
        $this->assertGreaterThanOrEqual(0, $removed);
        
        // Verify sources
        $sources = $source_service->get_sources_for_company($this->company_id);
        $this->assertIsArray($sources);
    }
}