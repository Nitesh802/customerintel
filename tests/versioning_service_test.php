<?php
/**
 * VersioningService unit tests
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

use advanced_testcase;
use local_customerintel\services\versioning_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/versioning_service.php');

/**
 * VersioningService test class
 * 
 * @group local_customerintel
 * @covers \local_customerintel\services\versioning_service
 */
class versioning_service_test extends advanced_testcase {

    /** @var versioning_service VersioningService instance */
    protected $versioningservice;

    /** @var int Test company ID */
    protected $companyid;

    /** @var array Test run IDs */
    protected $runids = [];

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->versioningservice = new versioning_service();
        
        // Create test data
        $this->create_test_data();
    }

    /**
     * Test snapshot creation
     */
    public function test_create_snapshot() {
        global $DB;
        
        $runid = $this->runids[0];
        
        // Create snapshot
        $snapshotid = $this->versioningservice->create_snapshot($runid);
        
        $this->assertIsInt($snapshotid);
        $this->assertGreaterThan(0, $snapshotid);
        
        // Verify snapshot exists
        $snapshot = $DB->get_record('local_ci_snapshot', ['id' => $snapshotid]);
        $this->assertNotFalse($snapshot);
        $this->assertEquals($runid, $snapshot->runid);
        $this->assertEquals($this->companyid, $snapshot->companyid);
        
        // Verify snapshot JSON structure
        $snapshotdata = json_decode($snapshot->snapshotjson, true);
        $this->assertIsArray($snapshotdata);
        $this->assertArrayHasKey('run_id', $snapshotdata);
        $this->assertArrayHasKey('company_id', $snapshotdata);
        $this->assertArrayHasKey('timestamp', $snapshotdata);
        $this->assertArrayHasKey('nb_results', $snapshotdata);
        $this->assertArrayHasKey('citations', $snapshotdata);
        $this->assertArrayHasKey('sources', $snapshotdata);
        $this->assertArrayHasKey('metadata', $snapshotdata);
        
        // Verify NB results included
        $this->assertCount(15, $snapshotdata['nb_results']);
        
        // Verify telemetry recorded
        $telemetry = $DB->get_records('local_ci_telemetry', [
            'runid' => $runid,
            'metrickey' => 'snapshot_creation_duration_ms'
        ]);
        $this->assertNotEmpty($telemetry);
    }

    /**
     * Test diff computation
     */
    public function test_compute_diff() {
        global $DB;
        
        // Create two snapshots with different data
        $snapshot1id = $this->versioningservice->create_snapshot($this->runids[0]);
        
        // Modify some NB results for second run
        $this->modify_nb_results($this->runids[1]);
        
        $snapshot2id = $this->versioningservice->create_snapshot($this->runids[1]);
        
        // Compute diff
        $diff = $this->versioningservice->compute_diff($snapshot1id, $snapshot2id);
        
        $this->assertIsArray($diff);
        $this->assertArrayHasKey('from_snapshot_id', $diff);
        $this->assertArrayHasKey('to_snapshot_id', $diff);
        $this->assertArrayHasKey('timestamp', $diff);
        $this->assertArrayHasKey('nb_diffs', $diff);
        
        $this->assertEquals($snapshot1id, $diff['from_snapshot_id']);
        $this->assertEquals($snapshot2id, $diff['to_snapshot_id']);
        
        // Verify diff stored in database
        $diffrec = $DB->get_record('local_ci_diff', [
            'fromsnapshotid' => $snapshot1id,
            'tosnapshotid' => $snapshot2id
        ]);
        $this->assertNotFalse($diffrec);
        
        // Verify telemetry recorded
        $telemetry = $DB->get_records('local_ci_telemetry', [
            'runid' => $this->runids[1],
            'metrickey' => 'diff_field_changes'
        ]);
        $this->assertNotEmpty($telemetry);
    }

    /**
     * Test diff accuracy for additions
     */
    public function test_diff_additions() {
        // Create first snapshot with minimal data
        $this->create_minimal_nb_results($this->runids[0]);
        $snapshot1id = $this->versioningservice->create_snapshot($this->runids[0]);
        
        // Create second snapshot with additional fields
        $this->create_expanded_nb_results($this->runids[1]);
        $snapshot2id = $this->versioningservice->create_snapshot($this->runids[1]);
        
        $diff = $this->versioningservice->compute_diff($snapshot1id, $snapshot2id);
        
        // Check for additions
        $this->assertNotEmpty($diff['nb_diffs']);
        
        $nb1diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB1') {
                $nb1diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb1diff);
        $this->assertNotEmpty($nb1diff['added']);
        $this->assertArrayHasKey('new_field', $nb1diff['added']);
    }

    /**
     * Test diff accuracy for removals
     */
    public function test_diff_removals() {
        // Create first snapshot with full data
        $this->create_expanded_nb_results($this->runids[0]);
        $snapshot1id = $this->versioningservice->create_snapshot($this->runids[0]);
        
        // Create second snapshot with minimal data
        $this->create_minimal_nb_results($this->runids[1]);
        $snapshot2id = $this->versioningservice->create_snapshot($this->runids[1]);
        
        $diff = $this->versioningservice->compute_diff($snapshot1id, $snapshot2id);
        
        // Check for removals
        $this->assertNotEmpty($diff['nb_diffs']);
        
        $nb1diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB1') {
                $nb1diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb1diff);
        $this->assertNotEmpty($nb1diff['removed']);
    }

    /**
     * Test diff accuracy for changes
     */
    public function test_diff_changes() {
        global $DB;
        
        // Create first snapshot
        $this->create_minimal_nb_results($this->runids[0]);
        $snapshot1id = $this->versioningservice->create_snapshot($this->runids[0]);
        
        // Modify values for second snapshot
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $this->runids[1],
            'nbcode' => 'NB1'
        ]);
        
        $payload = json_decode($nbresult->jsonpayload, true);
        $payload['summary'] = 'Modified summary';
        $nbresult->jsonpayload = json_encode($payload);
        $DB->update_record('local_ci_nb_result', $nbresult);
        
        $snapshot2id = $this->versioningservice->create_snapshot($this->runids[1]);
        
        $diff = $this->versioningservice->compute_diff($snapshot1id, $snapshot2id);
        
        // Check for changes
        $nb1diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB1') {
                $nb1diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb1diff);
        $this->assertNotEmpty($nb1diff['changed']);
        $this->assertArrayHasKey('summary', $nb1diff['changed']);
        $this->assertEquals('Test summary', $nb1diff['changed']['summary']['from']);
        $this->assertEquals('Modified summary', $nb1diff['changed']['summary']['to']);
    }

    /**
     * Test nested object comparison
     */
    public function test_nested_object_diff() {
        global $DB;
        
        // Create snapshot with nested structure
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $this->runids[0],
            'nbcode' => 'NB1'
        ]);
        
        $payload = [
            'metrics' => [
                'revenue' => ['value' => 100, 'unit' => 'M'],
                'growth' => ['value' => 10, 'unit' => '%']
            ]
        ];
        $nbresult->jsonpayload = json_encode($payload);
        $DB->update_record('local_ci_nb_result', $nbresult);
        
        $snapshot1id = $this->versioningservice->create_snapshot($this->runids[0]);
        
        // Modify nested values
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $this->runids[1],
            'nbcode' => 'NB1'
        ]);
        
        $payload['metrics']['revenue']['value'] = 120;
        $payload['metrics']['efficiency'] = ['value' => 85, 'unit' => '%'];
        unset($payload['metrics']['growth']);
        
        $nbresult->jsonpayload = json_encode($payload);
        $DB->update_record('local_ci_nb_result', $nbresult);
        
        $snapshot2id = $this->versioningservice->create_snapshot($this->runids[1]);
        
        $diff = $this->versioningservice->compute_diff($snapshot1id, $snapshot2id);
        
        // Verify nested changes detected
        $nb1diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB1') {
                $nb1diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb1diff);
        
        // Check nested field changes
        $this->assertArrayHasKey('metrics', $nb1diff['changed']);
        $this->assertArrayHasKey('revenue', $nb1diff['changed']['metrics']);
        
        // Check nested additions
        $this->assertArrayHasKey('metrics', $nb1diff['added']);
        $this->assertArrayHasKey('efficiency', $nb1diff['added']['metrics']);
        
        // Check nested removals
        $this->assertArrayHasKey('metrics', $nb1diff['removed']);
        $this->assertArrayHasKey('growth', $nb1diff['removed']['metrics']);
    }

    /**
     * Test citation tracking in diff
     */
    public function test_citation_diff() {
        global $DB;
        
        // Create snapshot with citations
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $this->runids[0],
            'nbcode' => 'NB1'
        ]);
        
        $citations = [
            ['source_id' => 1, 'quote' => 'Quote 1'],
            ['source_id' => 2, 'quote' => 'Quote 2']
        ];
        $nbresult->citations = json_encode($citations);
        $DB->update_record('local_ci_nb_result', $nbresult);
        
        $snapshot1id = $this->versioningservice->create_snapshot($this->runids[0]);
        
        // Modify citations
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $this->runids[1],
            'nbcode' => 'NB1'
        ]);
        
        $citations = [
            ['source_id' => 2, 'quote' => 'Quote 2'],
            ['source_id' => 3, 'quote' => 'Quote 3']
        ];
        $nbresult->citations = json_encode($citations);
        $DB->update_record('local_ci_nb_result', $nbresult);
        
        $snapshot2id = $this->versioningservice->create_snapshot($this->runids[1]);
        
        $diff = $this->versioningservice->compute_diff($snapshot1id, $snapshot2id);
        
        // Check citation changes
        $nb1diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB1') {
                $nb1diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb1diff);
        $this->assertArrayHasKey('citations', $nb1diff);
        $this->assertContains(3, $nb1diff['citations']['added']);
        $this->assertContains(1, $nb1diff['citations']['removed']);
    }

    /**
     * Test get_history method
     */
    public function test_get_history() {
        // Create multiple snapshots
        $this->versioningservice->create_snapshot($this->runids[0]);
        sleep(1); // Ensure different timestamps
        $this->versioningservice->create_snapshot($this->runids[1]);
        
        $history = $this->versioningservice->get_history($this->companyid);
        
        $this->assertIsArray($history);
        $this->assertCount(2, $history);
        
        // Verify history structure
        foreach ($history as $entry) {
            $this->assertArrayHasKey('snapshot_id', $entry);
            $this->assertArrayHasKey('run_id', $entry);
            $this->assertArrayHasKey('mode', $entry);
            $this->assertArrayHasKey('status', $entry);
            $this->assertArrayHasKey('created', $entry);
            $this->assertArrayHasKey('created_formatted', $entry);
        }
        
        // Verify ordering (newest first)
        $this->assertGreaterThan($history[1]['created'], $history[0]['created']);
    }

    /**
     * Test get_reusable_snapshot
     */
    public function test_get_reusable_snapshot() {
        // Create recent snapshot
        $snapshotid = $this->versioningservice->create_snapshot($this->runids[0]);
        
        // Test within freshness window
        $reusable = $this->versioningservice->get_reusable_snapshot($this->companyid, 3600);
        $this->assertEquals($snapshotid, $reusable);
        
        // Test outside freshness window
        $reusable = $this->versioningservice->get_reusable_snapshot($this->companyid, 0);
        $this->assertNull($reusable);
    }

    /**
     * Test format_diff_display
     */
    public function test_format_diff_display() {
        $diff = [
            'from_snapshot_id' => 1,
            'to_snapshot_id' => 2,
            'timestamp' => time(),
            'nb_diffs' => [
                [
                    'nb_code' => 'NB1',
                    'added' => ['new_field' => 'new_value'],
                    'changed' => ['field1' => ['from' => 'old', 'to' => 'new']],
                    'removed' => ['old_field' => 'old_value'],
                    'citations' => [
                        'added' => [3],
                        'removed' => [1]
                    ]
                ]
            ]
        ];
        
        $formatted = $this->versioningservice->format_diff_display($diff);
        
        $this->assertIsString($formatted);
        $this->assertStringContainsString('SNAPSHOT DIFF', $formatted);
        $this->assertStringContainsString('NB1', $formatted);
        $this->assertStringContainsString('ADDED', $formatted);
        $this->assertStringContainsString('CHANGED', $formatted);
        $this->assertStringContainsString('REMOVED', $formatted);
        $this->assertStringContainsString('CITATIONS', $formatted);
    }

    /**
     * Test get_or_create_diff
     */
    public function test_get_or_create_diff() {
        global $DB;
        
        $snapshot1id = $this->versioningservice->create_snapshot($this->runids[0]);
        $snapshot2id = $this->versioningservice->create_snapshot($this->runids[1]);
        
        // First call should create diff
        $diff1 = $this->versioningservice->get_or_create_diff($snapshot1id, $snapshot2id);
        $this->assertNotNull($diff1);
        
        // Second call should return existing diff
        $diff2 = $this->versioningservice->get_or_create_diff($snapshot1id, $snapshot2id);
        $this->assertNotNull($diff2);
        $this->assertEquals($diff1->id, $diff2->id);
        
        // Verify only one diff record exists
        $count = $DB->count_records('local_ci_diff', [
            'fromsnapshotid' => $snapshot1id,
            'tosnapshotid' => $snapshot2id
        ]);
        $this->assertEquals(1, $count);
    }

    /**
     * Create test data
     */
    protected function create_test_data() {
        global $DB, $USER;
        
        // Create test company
        $company = new \stdClass();
        $company->name = 'Test Company';
        $company->ticker = 'TEST';
        $company->type = 'customer';
        $company->website = 'https://test.com';
        $company->sector = 'Technology';
        $company->metadata = json_encode(['test' => true]);
        $company->timecreated = time();
        $company->timemodified = time();
        $this->companyid = $DB->insert_record('local_ci_company', $company);
        
        // Create test sources
        for ($i = 1; $i <= 3; $i++) {
            $source = new \stdClass();
            $source->companyid = $this->companyid;
            $source->type = 'url';
            $source->title = "Test Source $i";
            $source->url = "https://example.com/source$i";
            $source->addedbyuserid = $USER->id;
            $source->approved = 1;
            $source->rejected = 0;
            $source->hash = sha1("source$i");
            $source->timecreated = time();
            $source->timemodified = time();
            
            $DB->insert_record('local_ci_source', $source);
        }
        
        // Create two test runs
        for ($r = 0; $r < 2; $r++) {
            $run = new \stdClass();
            $run->companyid = $this->companyid;
            $run->initiatedbyuserid = $USER->id;
            $run->userid = $USER->id;
            $run->mode = 'full';
            $run->status = 'completed';
            $run->timestarted = time() - 3600;
            $run->timecompleted = time();
            $run->actualtokens = 50000;
            $run->actualcost = 0.50;
            $run->timecreated = time();
            $run->timemodified = time();
            $runid = $DB->insert_record('local_ci_run', $run);
            $this->runids[] = $runid;
            
            // Create NB results for all 15 NBs
            for ($i = 1; $i <= 15; $i++) {
                $nbresult = new \stdClass();
                $nbresult->runid = $runid;
                $nbresult->nbcode = 'NB' . $i;
                $nbresult->jsonpayload = json_encode([
                    'summary' => "Test summary",
                    'key_points' => ["Point 1", "Point 2"]
                ]);
                $nbresult->citations = json_encode([]);
                $nbresult->durationms = rand(1000, 5000);
                $nbresult->tokensused = rand(100, 1000);
                $nbresult->status = 'completed';
                $nbresult->timecreated = time();
                $nbresult->timemodified = time();
                
                $DB->insert_record('local_ci_nb_result', $nbresult);
            }
        }
    }

    /**
     * Modify NB results for testing diffs
     */
    protected function modify_nb_results($runid) {
        global $DB;
        
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $runid,
            'nbcode' => 'NB1'
        ]);
        
        $payload = json_decode($nbresult->jsonpayload, true);
        $payload['summary'] = "Modified summary for testing";
        $payload['new_field'] = "New value";
        $nbresult->jsonpayload = json_encode($payload);
        
        $DB->update_record('local_ci_nb_result', $nbresult);
    }

    /**
     * Create minimal NB results
     */
    protected function create_minimal_nb_results($runid) {
        global $DB;
        
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $runid,
            'nbcode' => 'NB1'
        ]);
        
        $payload = [
            'summary' => 'Test summary',
            'basic_field' => 'basic value'
        ];
        $nbresult->jsonpayload = json_encode($payload);
        
        $DB->update_record('local_ci_nb_result', $nbresult);
    }

    /**
     * Create expanded NB results
     */
    protected function create_expanded_nb_results($runid) {
        global $DB;
        
        $nbresult = $DB->get_record('local_ci_nb_result', [
            'runid' => $runid,
            'nbcode' => 'NB1'
        ]);
        
        $payload = [
            'summary' => 'Test summary',
            'basic_field' => 'basic value',
            'new_field' => 'additional value',
            'extra_data' => ['item1', 'item2']
        ];
        $nbresult->jsonpayload = json_encode($payload);
        
        $DB->update_record('local_ci_nb_result', $nbresult);
    }
}