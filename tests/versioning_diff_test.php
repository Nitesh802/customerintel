<?php
/**
 * Versioning Diff Output Tests
 *
 * @package    local_customerintel
 * @category   test
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/classes/services/versioning_service.php');
require_once($CFG->dirroot . '/local/customerintel/tests/mocks/mock_llm_client.php');

use local_customerintel\services\versioning_service;
use local_customerintel\tests\mocks\mock_llm_client;

/**
 * Test class for versioning diff output
 * 
 * @group local_customerintel
 * @group customerintel_versioning
 */
class versioning_diff_test extends \advanced_testcase {
    
    /** @var versioning_service Versioning service instance */
    private $versioning_service;
    
    /** @var mock_llm_client Mock LLM client */
    private $mock_client;
    
    /** @var int Test company ID */
    private $test_company_id;
    
    /** @var int Test user ID */
    private $test_user_id;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        
        $this->versioning_service = new versioning_service();
        $this->mock_client = new mock_llm_client();
        
        // Create test user
        $user = $this->getDataGenerator()->create_user();
        $this->test_user_id = $user->id;
        $this->setUser($user);
        
        // Create test company
        $this->test_company_id = $this->create_test_company();
    }
    
    /**
     * Create test company
     */
    private function create_test_company(): int {
        global $DB;
        
        $company = new \stdClass();
        $company->name = 'Test Corp';
        $company->ticker = 'TEST';
        $company->description = 'Test company for versioning';
        $company->domain = 'testcorp.com';
        $company->metadata = json_encode(['industry' => 'Technology']);
        $company->createdbyuserid = $this->test_user_id;
        $company->timecreated = time();
        $company->timemodified = time();
        
        return $DB->insert_record('local_ci_company', $company);
    }
    
    /**
     * Create test run with NB results
     */
    private function create_test_run($nbdata = null): int {
        global $DB;
        
        // Create run
        $run = new \stdClass();
        $run->companyid = $this->test_company_id;
        $run->initiatedbyuserid = $this->test_user_id;
        $run->userid = $this->test_user_id;
        $run->mode = 'full';
        $run->status = 'completed';
        $run->timestarted = time() - 300;
        $run->timecompleted = time();
        $run->actualtokens = 25000;
        $run->actualcost = 2.5;
        $run->timecreated = time();
        $run->timemodified = time();
        
        $runid = $DB->insert_record('local_ci_run', $run);
        
        // Add NB results
        $nbcodes = ['NB1', 'NB2', 'NB3', 'NB4', 'NB5'];
        foreach ($nbcodes as $nbcode) {
            $result = new \stdClass();
            $result->runid = $runid;
            $result->nbcode = $nbcode;
            $result->status = 'completed';
            
            if ($nbdata && isset($nbdata[$nbcode])) {
                $result->jsonpayload = json_encode($nbdata[$nbcode]);
            } else {
                // Use mock client to get payload
                $response = $this->mock_client->execute_prompt("Test for $nbcode");
                $result->jsonpayload = json_encode($response['payload']);
            }
            
            $result->citations = json_encode([
                ['source_id' => 1, 'title' => "Source for $nbcode", 'page' => 10]
            ]);
            $result->durationms = 1500;
            $result->tokensused = 1000;
            $result->timecreated = time();
            $result->timemodified = time();
            
            $DB->insert_record('local_ci_nb_result', $result);
        }
        
        return $runid;
    }
    
    /**
     * Test snapshot creation
     * 
     * @covers \local_customerintel\services\versioning_service::create_snapshot
     */
    public function test_snapshot_creation() {
        global $DB;
        
        $runid = $this->create_test_run();
        
        // Create snapshot
        $snapshotid = $this->versioning_service->create_snapshot($runid);
        
        $this->assertGreaterThan(0, $snapshotid);
        
        // Verify snapshot was created
        $snapshot = $DB->get_record('local_ci_snapshot', ['id' => $snapshotid]);
        $this->assertNotFalse($snapshot);
        $this->assertEquals($this->test_company_id, $snapshot->companyid);
        $this->assertEquals($runid, $snapshot->runid);
        
        // Verify snapshot JSON structure
        $data = json_decode($snapshot->snapshotjson, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('run_id', $data);
        $this->assertArrayHasKey('company_id', $data);
        $this->assertArrayHasKey('nb_results', $data);
        $this->assertArrayHasKey('citations', $data);
        $this->assertArrayHasKey('metadata', $data);
        
        // Verify NB results are included
        $this->assertCount(5, $data['nb_results']);
        $this->assertArrayHasKey('NB1', $data['nb_results']);
        $this->assertArrayHasKey('NB5', $data['nb_results']);
    }
    
    /**
     * Test diff computation between snapshots
     * 
     * @covers \local_customerintel\services\versioning_service::compute_diff
     */
    public function test_diff_computation() {
        global $DB;
        
        // Create first run with initial data
        $nbdata1 = [
            'NB1' => [
                'board_expectations' => ['Increase revenue by 20%'],
                'investor_commitments' => ['IPO in 2025'],
                'executive_mandates' => ['Digital transformation'],
                'pressure_points' => ['Competition from startups']
            ],
            'NB2' => [
                'market_conditions' => ['Growing at 10% CAGR'],
                'competitive_landscape' => [
                    'market_position' => 'Leader',
                    'market_share' => '35%'
                ]
            ]
        ];
        
        $runid1 = $this->create_test_run($nbdata1);
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        // Create second run with modified data
        $nbdata2 = [
            'NB1' => [
                'board_expectations' => ['Increase revenue by 30%', 'Expand to Asia'],
                'investor_commitments' => ['IPO in 2025'],
                'executive_mandates' => ['AI integration'],
                'pressure_points' => ['Competition from startups', 'Regulatory changes']
            ],
            'NB2' => [
                'market_conditions' => ['Growing at 15% CAGR'],
                'competitive_landscape' => [
                    'market_position' => 'Leader',
                    'market_share' => '40%',
                    'key_competitors' => ['Competitor A', 'Competitor B']
                ]
            ]
        ];
        
        $runid2 = $this->create_test_run($nbdata2);
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Compute diff
        $diff = $this->versioning_service->compute_diff($snapshotid1, $snapshotid2);
        
        // Verify diff structure (per PRD 24.2)
        $this->assertIsArray($diff);
        $this->assertArrayHasKey('from_snapshot_id', $diff);
        $this->assertArrayHasKey('to_snapshot_id', $diff);
        $this->assertArrayHasKey('timestamp', $diff);
        $this->assertArrayHasKey('nb_diffs', $diff);
        
        $this->assertEquals($snapshotid1, $diff['from_snapshot_id']);
        $this->assertEquals($snapshotid2, $diff['to_snapshot_id']);
        
        // Verify NB diffs
        $this->assertNotEmpty($diff['nb_diffs']);
        
        // Find NB1 diff
        $nb1diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB1') {
                $nb1diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb1diff);
        $this->assertArrayHasKey('changed', $nb1diff);
        $this->assertArrayHasKey('added', $nb1diff);
        $this->assertArrayHasKey('removed', $nb1diff);
        
        // Verify changed fields
        $this->assertArrayHasKey('board_expectations', $nb1diff['changed']);
        $this->assertArrayHasKey('executive_mandates', $nb1diff['changed']);
        
        // Find NB2 diff
        $nb2diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB2') {
                $nb2diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb2diff);
        
        // Verify new field was added
        $this->assertArrayHasKey('competitive_landscape', $nb2diff['added']);
        $this->assertArrayHasKey('key_competitors', $nb2diff['added']['competitive_landscape']);
    }
    
    /**
     * Test diff with citation changes
     * 
     * @covers \local_customerintel\services\versioning_service::compare_nb_results
     */
    public function test_diff_with_citation_changes() {
        global $DB;
        
        // Create first run
        $runid1 = $this->create_test_run();
        
        // Update citations for NB1
        $DB->set_field('local_ci_nb_result', 'citations', json_encode([
            ['source_id' => 1, 'title' => 'Source 1'],
            ['source_id' => 2, 'title' => 'Source 2']
        ]), ['runid' => $runid1, 'nbcode' => 'NB1']);
        
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        // Create second run
        $runid2 = $this->create_test_run();
        
        // Update citations for NB1 with different sources
        $DB->set_field('local_ci_nb_result', 'citations', json_encode([
            ['source_id' => 2, 'title' => 'Source 2'],
            ['source_id' => 3, 'title' => 'Source 3'],
            ['source_id' => 4, 'title' => 'Source 4']
        ]), ['runid' => $runid2, 'nbcode' => 'NB1']);
        
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Get diff
        $diff = $this->versioning_service->get_diff($snapshotid2, $snapshotid1);
        
        // Find NB1 diff
        $nb1diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB1') {
                $nb1diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb1diff);
        $this->assertArrayHasKey('citations', $nb1diff);
        $this->assertArrayHasKey('added', $nb1diff['citations']);
        $this->assertArrayHasKey('removed', $nb1diff['citations']);
        
        // Verify citation changes
        $this->assertContains(3, $nb1diff['citations']['added']);
        $this->assertContains(4, $nb1diff['citations']['added']);
        $this->assertContains(1, $nb1diff['citations']['removed']);
    }
    
    /**
     * Test diff with completely new NB
     */
    public function test_diff_with_new_nb() {
        global $DB;
        
        // Create first run with only 3 NBs
        $run1 = new \stdClass();
        $run1->companyid = $this->test_company_id;
        $run1->initiatedbyuserid = $this->test_user_id;
        $run1->userid = $this->test_user_id;
        $run1->mode = 'partial';
        $run1->status = 'completed';
        $run1->timestarted = time() - 300;
        $run1->timecompleted = time();
        $run1->actualtokens = 15000;
        $run1->actualcost = 1.5;
        $run1->timecreated = time();
        
        $runid1 = $DB->insert_record('local_ci_run', $run1);
        
        // Add only NB1, NB2, NB3
        foreach (['NB1', 'NB2', 'NB3'] as $nbcode) {
            $result = new \stdClass();
            $result->runid = $runid1;
            $result->nbcode = $nbcode;
            $result->status = 'completed';
            $response = $this->mock_client->execute_prompt("Test for $nbcode");
            $result->jsonpayload = json_encode($response['payload']);
            $result->citations = '[]';
            $result->durationms = 1000;
            $result->tokensused = 800;
            $result->timecreated = time();
            
            $DB->insert_record('local_ci_nb_result', $result);
        }
        
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        // Create second run with all 5 NBs
        $runid2 = $this->create_test_run();
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Get diff
        $diff = $this->versioning_service->compute_diff($snapshotid1, $snapshotid2);
        
        // Find NB4 diff (should be completely new)
        $nb4diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB4') {
                $nb4diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb4diff);
        $this->assertNotEmpty($nb4diff['added']);
        $this->assertEmpty($nb4diff['changed']);
        $this->assertEmpty($nb4diff['removed']);
    }
    
    /**
     * Test diff with removed NB
     */
    public function test_diff_with_removed_nb() {
        // Create first run with all NBs
        $runid1 = $this->create_test_run();
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        // Create second run missing NB5
        global $DB;
        $run2 = new \stdClass();
        $run2->companyid = $this->test_company_id;
        $run2->initiatedbyuserid = $this->test_user_id;
        $run2->userid = $this->test_user_id;
        $run2->mode = 'partial';
        $run2->status = 'completed';
        $run2->timestarted = time() - 300;
        $run2->timecompleted = time();
        $run2->actualtokens = 20000;
        $run2->actualcost = 2.0;
        $run2->timecreated = time();
        
        $runid2 = $DB->insert_record('local_ci_run', $run2);
        
        // Add all except NB5
        foreach (['NB1', 'NB2', 'NB3', 'NB4'] as $nbcode) {
            $result = new \stdClass();
            $result->runid = $runid2;
            $result->nbcode = $nbcode;
            $result->status = 'completed';
            $response = $this->mock_client->execute_prompt("Test for $nbcode");
            $result->jsonpayload = json_encode($response['payload']);
            $result->citations = '[]';
            $result->durationms = 1000;
            $result->tokensused = 800;
            $result->timecreated = time();
            
            $DB->insert_record('local_ci_nb_result', $result);
        }
        
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Get diff
        $diff = $this->versioning_service->compute_diff($snapshotid1, $snapshotid2);
        
        // Find NB5 diff (should be completely removed)
        $nb5diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB5') {
                $nb5diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb5diff);
        $this->assertNotEmpty($nb5diff['removed']);
        $this->assertEmpty($nb5diff['added']);
        $this->assertEmpty($nb5diff['changed']);
    }
    
    /**
     * Test deep nested object comparison
     */
    public function test_deep_nested_diff() {
        global $DB;
        
        // Create complex nested data
        $nbdata1 = [
            'NB6' => [
                'technology_stack' => [
                    'cloud_adoption' => [
                        'level' => 'Advanced',
                        'providers' => ['AWS', 'Azure'],
                        'services' => [
                            'compute' => ['EC2', 'Lambda'],
                            'storage' => ['S3', 'EBS']
                        ]
                    ],
                    'databases' => [
                        'primary' => 'PostgreSQL',
                        'cache' => 'Redis'
                    ]
                ],
                'digital_maturity' => [
                    'current_stage' => 'Optimizing',
                    'score' => 75
                ]
            ]
        ];
        
        $runid1 = $this->create_test_run($nbdata1);
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        // Modify nested data
        $nbdata2 = [
            'NB6' => [
                'technology_stack' => [
                    'cloud_adoption' => [
                        'level' => 'Expert',  // Changed
                        'providers' => ['AWS', 'Azure', 'GCP'],  // Added GCP
                        'services' => [
                            'compute' => ['EC2', 'Lambda', 'Kubernetes'],  // Added K8s
                            'storage' => ['S3'],  // Removed EBS
                            'ai_ml' => ['SageMaker', 'Vertex AI']  // New category
                        ]
                    ],
                    'databases' => [
                        'primary' => 'PostgreSQL',
                        'cache' => 'Redis',
                        'analytics' => 'Snowflake'  // Added
                    ]
                ],
                'digital_maturity' => [
                    'current_stage' => 'Leading',  // Changed
                    'score' => 90,  // Changed
                    'next_milestone' => 'Industry benchmark'  // Added
                ]
            ]
        ];
        
        $runid2 = $this->create_test_run($nbdata2);
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Get diff
        $diff = $this->versioning_service->compute_diff($snapshotid1, $snapshotid2);
        
        // Find NB6 diff
        $nb6diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB6') {
                $nb6diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb6diff);
        
        // Verify nested changes are detected
        $this->assertArrayHasKey('technology_stack', $nb6diff['changed']);
        $this->assertArrayHasKey('cloud_adoption', $nb6diff['changed']['technology_stack']);
        $this->assertArrayHasKey('level', $nb6diff['changed']['technology_stack']['cloud_adoption']);
        
        // Verify deep additions
        $this->assertArrayHasKey('technology_stack', $nb6diff['added']);
        $this->assertArrayHasKey('cloud_adoption', $nb6diff['added']['technology_stack']);
        $this->assertArrayHasKey('services', $nb6diff['added']['technology_stack']['cloud_adoption']);
        $this->assertArrayHasKey('ai_ml', $nb6diff['added']['technology_stack']['cloud_adoption']['services']);
    }
    
    /**
     * Test get_history functionality
     * 
     * @covers \local_customerintel\services\versioning_service::get_history
     */
    public function test_get_version_history() {
        // Create multiple runs
        $runid1 = $this->create_test_run();
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        sleep(1); // Ensure different timestamps
        
        $runid2 = $this->create_test_run();
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        sleep(1);
        
        $runid3 = $this->create_test_run();
        $snapshotid3 = $this->versioning_service->create_snapshot($runid3);
        
        // Get history
        $history = $this->versioning_service->get_history($this->test_company_id);
        
        $this->assertIsArray($history);
        $this->assertCount(3, $history);
        
        // Verify ordering (most recent first)
        $this->assertEquals($snapshotid3, $history[0]['snapshot_id']);
        $this->assertEquals($snapshotid2, $history[1]['snapshot_id']);
        $this->assertEquals($snapshotid1, $history[2]['snapshot_id']);
        
        // Verify history entry structure
        $entry = $history[0];
        $this->assertArrayHasKey('snapshot_id', $entry);
        $this->assertArrayHasKey('run_id', $entry);
        $this->assertArrayHasKey('mode', $entry);
        $this->assertArrayHasKey('status', $entry);
        $this->assertArrayHasKey('created', $entry);
        $this->assertArrayHasKey('created_formatted', $entry);
        $this->assertArrayHasKey('run_by', $entry);
        $this->assertArrayHasKey('duration', $entry);
    }
    
    /**
     * Test diff storage and retrieval
     * 
     * @covers \local_customerintel\services\versioning_service::store_diff
     * @covers \local_customerintel\services\versioning_service::get_diff
     */
    public function test_diff_storage() {
        global $DB;
        
        // Create two snapshots
        $runid1 = $this->create_test_run();
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        $runid2 = $this->create_test_run();
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Diff should have been auto-computed and stored
        $stored_diff = $DB->get_record('local_ci_diff', [
            'fromsnapshotid' => $snapshotid1,
            'tosnapshotid' => $snapshotid2
        ]);
        
        $this->assertNotFalse($stored_diff);
        
        // Retrieve diff
        $diff = $this->versioning_service->get_diff($snapshotid2, $snapshotid1);
        
        $this->assertIsArray($diff);
        $this->assertEquals($snapshotid1, $diff['from_snapshot_id']);
        $this->assertEquals($snapshotid2, $diff['to_snapshot_id']);
    }
    
    /**
     * Test array value changes in diff
     */
    public function test_array_value_changes() {
        global $DB;
        
        // Create data with array values
        $nbdata1 = [
            'NB4' => [
                'strategic_priorities' => [
                    'Expand to Europe',
                    'Launch mobile app',
                    'Improve margins'
                ],
                'key_initiatives' => [
                    'Digital transformation',
                    'Cost reduction'
                ]
            ]
        ];
        
        $runid1 = $this->create_test_run($nbdata1);
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        // Modify arrays
        $nbdata2 = [
            'NB4' => [
                'strategic_priorities' => [
                    'Expand to Asia',  // Changed
                    'Launch mobile app',  // Same
                    'Improve margins',  // Same
                    'Acquire competitor'  // Added
                ],
                'key_initiatives' => [
                    'AI integration'  // Completely different
                ]
            ]
        ];
        
        $runid2 = $this->create_test_run($nbdata2);
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Get diff
        $diff = $this->versioning_service->compute_diff($snapshotid1, $snapshotid2);
        
        // Find NB4 diff
        $nb4diff = null;
        foreach ($diff['nb_diffs'] as $nbdiff) {
            if ($nbdiff['nb_code'] === 'NB4') {
                $nb4diff = $nbdiff;
                break;
            }
        }
        
        $this->assertNotNull($nb4diff);
        
        // Arrays should show as changed
        $this->assertArrayHasKey('strategic_priorities', $nb4diff['changed']);
        $this->assertArrayHasKey('key_initiatives', $nb4diff['changed']);
        
        // Verify the from/to values
        $priorities_change = $nb4diff['changed']['strategic_priorities'];
        $this->assertArrayHasKey('from', $priorities_change);
        $this->assertArrayHasKey('to', $priorities_change);
        $this->assertCount(3, $priorities_change['from']);
        $this->assertCount(4, $priorities_change['to']);
    }
    
    /**
     * Test empty diff when no changes
     */
    public function test_empty_diff_no_changes() {
        // Create identical runs
        $nbdata = [
            'NB1' => [
                'board_expectations' => ['Growth target'],
                'investor_commitments' => ['IPO 2025'],
                'executive_mandates' => ['Innovation'],
                'pressure_points' => ['Competition']
            ]
        ];
        
        $runid1 = $this->create_test_run($nbdata);
        $snapshotid1 = $this->versioning_service->create_snapshot($runid1);
        
        $runid2 = $this->create_test_run($nbdata);
        $snapshotid2 = $this->versioning_service->create_snapshot($runid2);
        
        // Get diff
        $diff = $this->versioning_service->compute_diff($snapshotid1, $snapshotid2);
        
        // Should have basic structure but minimal diffs
        $this->assertIsArray($diff);
        $this->assertArrayHasKey('nb_diffs', $diff);
        
        // Each NB diff should have empty changed/added/removed
        foreach ($diff['nb_diffs'] as $nbdiff) {
            $has_changes = !empty($nbdiff['changed']) || 
                          !empty($nbdiff['added']) || 
                          !empty($nbdiff['removed']);
            
            // Only NBs with actual changes should be in the diff
            if (!$has_changes) {
                $this->fail("NB diff included with no changes: " . $nbdiff['nb_code']);
            }
        }
    }
}