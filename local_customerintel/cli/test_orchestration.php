#!/usr/bin/env php
<?php
/**
 * Test NB Orchestration - CLI script for testing full orchestration flow
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Bootstrap Moodle
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/nb_orchestrator.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/source_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/company_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/clients/llm_client.php');

use local_customerintel\services\nb_orchestrator;
use local_customerintel\services\source_service;
use local_customerintel\services\company_service;
use local_customerintel\clients\llm_client;

// CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'mock' => false,
        'company' => null,
        'nbcode' => null,
        'full' => false,
        'verbose' => false
    ],
    ['h' => 'help', 'm' => 'mock', 'c' => 'company', 'n' => 'nbcode', 'f' => 'full', 'v' => 'verbose']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Test NB Orchestration Flow

This script tests the NB orchestration system with mock or real data.

Usage:
  php test_orchestration.php [OPTIONS]

Options:
  -h, --help          Show this help message
  -m, --mock          Use mock LLM responses (default: false)
  -c, --company=NAME  Company name to test with (default: creates test company)
  -n, --nbcode=CODE   Test specific NB code (e.g., NB1) instead of full protocol
  -f, --full          Run full NB1-NB15 protocol (default: false)
  -v, --verbose       Show detailed output

Examples:
  # Test single NB with mock data
  php test_orchestration.php --mock --nbcode=NB1

  # Test full protocol with mock data
  php test_orchestration.php --mock --full

  # Test with specific company
  php test_orchestration.php --mock --company=\"Acme Corp\" --full

";
    exit(0);
}

// Enable mock mode if requested
if ($options['mock']) {
    set_config('llm_mock_mode', true, 'local_customerintel');
    cli_writeln("✓ Mock mode enabled");
} else {
    set_config('llm_mock_mode', false, 'local_customerintel');
    cli_writeln("✓ Real LLM mode enabled");
}

// Ensure required configuration
if (!get_config('local_customerintel', 'llm_provider')) {
    set_config('llm_provider', 'openai-gpt4', 'local_customerintel');
    set_config('llm_temperature', 0.2, 'local_customerintel');
    cli_writeln("✓ Default LLM configuration set");
}

try {
    // Create or find company
    $companyservice = new company_service();
    $companyname = $options['company'] ?? 'Test Company ' . uniqid();
    
    cli_writeln("\n=== Setting up test company ===");
    
    // Check if company exists
    $company = $DB->get_record('local_ci_company', ['name' => $companyname]);
    
    if (!$company) {
        // Create new company
        $company = new stdClass();
        $company->name = $companyname;
        $company->ticker = 'TEST' . rand(100, 999);
        $company->type = 'customer';
        $company->website = 'https://example.com';
        $company->sector = 'Technology';
        $company->metadata = json_encode([
            'employees' => rand(1000, 50000),
            'revenue' => rand(10, 1000) . 'M',
            'founded' => rand(1990, 2020)
        ]);
        $company->timecreated = time();
        $company->timemodified = time();
        
        $company->id = $DB->insert_record('local_ci_company', $company);
        cli_writeln("✓ Created company: {$company->name} (ID: {$company->id})");
    } else {
        cli_writeln("✓ Using existing company: {$company->name} (ID: {$company->id})");
    }
    
    // Create test sources if none exist
    $sources = $DB->get_records('local_ci_source', ['companyid' => $company->id]);
    
    if (empty($sources)) {
        cli_writeln("\n=== Creating test sources ===");
        
        $sourceservice = new source_service();
        
        // Create 3 test sources
        for ($i = 1; $i <= 3; $i++) {
            $source = new stdClass();
            $source->companyid = $company->id;
            $source->type = 'url';
            $source->title = "{$company->name} Source $i";
            $source->url = "https://example.com/{$company->ticker}/source$i";
            $source->addedbyuserid = 2; // Admin user
            $source->approved = 1;
            $source->rejected = 0;
            $source->hash = sha1($source->url);
            $source->timecreated = time();
            $source->timemodified = time();
            
            $sourceid = $DB->insert_record('local_ci_source', $source);
            
            // Create chunks for each source
            $chunkdata = [
                "The company reported strong financial performance with revenue growth of 25% year-over-year.",
                "Executive leadership announced new strategic initiatives focused on digital transformation.",
                "Market analysis shows increasing competitive pressure from emerging players in the sector.",
                "Operating margins improved to 18% driven by cost optimization and efficiency programs.",
                "Customer satisfaction scores reached all-time high of 92% in latest quarterly survey."
            ];
            
            foreach ($chunkdata as $j => $text) {
                $chunk = new stdClass();
                $chunk->sourceid = $sourceid;
                $chunk->chunkindex = $j + 1;
                $chunk->chunktext = $text;
                $chunk->hash = sha1($text);
                $chunk->tokens = str_word_count($text) * 1.3; // Rough token estimate
                $chunk->metadata = json_encode([
                    'source_type' => 'url',
                    'extraction_date' => date('Y-m-d')
                ]);
                $chunk->timecreated = time();
                
                $DB->insert_record('local_ci_source_chunk', $chunk);
            }
            
            cli_writeln("✓ Created source $i with 5 chunks");
        }
    } else {
        cli_writeln("✓ Found " . count($sources) . " existing sources");
    }
    
    // Create a test run
    cli_writeln("\n=== Creating test run ===");
    
    $run = new stdClass();
    $run->companyid = $company->id;
    $run->targetcompanyid = null; // Single company analysis
    $run->initiatedbyuserid = 2; // Admin user
    $run->userid = 2;
    $run->mode = 'full';
    $run->esttokens = 50000;
    $run->estcost = 0.50;
    $run->status = 'queued';
    $run->timecreated = time();
    $run->timemodified = time();
    
    $runid = $DB->insert_record('local_ci_run', $run);
    cli_writeln("✓ Created run ID: $runid");
    
    // Initialize orchestrator
    $orchestrator = new nb_orchestrator();
    
    if ($options['nbcode']) {
        // Test single NB
        $nbcode = strtoupper($options['nbcode']);
        cli_writeln("\n=== Testing single NB: $nbcode ===");
        
        $starttime = microtime(true);
        
        try {
            $result = $orchestrator->execute_nb($runid, $nbcode);
            
            if ($options['verbose']) {
                cli_writeln("\nResult details:");
                cli_writeln("  Status: " . $result['status']);
                cli_writeln("  Duration: " . round($result['duration_ms']) . "ms");
                cli_writeln("  Tokens used: " . $result['tokens_used']);
                cli_writeln("  Attempts: " . $result['attempts']);
                cli_writeln("  Citations: " . count($result['citations']));
                
                if (!empty($result['payload'])) {
                    cli_writeln("\nPayload structure:");
                    foreach (array_keys($result['payload']) as $key) {
                        $value = $result['payload'][$key];
                        if (is_array($value)) {
                            cli_writeln("  - $key: " . count($value) . " items");
                        } else {
                            cli_writeln("  - $key: " . substr($value, 0, 50) . "...");
                        }
                    }
                }
            }
            
            cli_writeln("\n✓ $nbcode executed successfully");
            
        } catch (Exception $e) {
            cli_error("Failed to execute $nbcode: " . $e->getMessage());
        }
        
    } elseif ($options['full']) {
        // Test full protocol
        cli_writeln("\n=== Testing full NB1-NB15 protocol ===");
        
        $starttime = microtime(true);
        
        // Execute protocol
        $success = $orchestrator->execute_protocol($runid);
        
        $duration = microtime(true) - $starttime;
        
        if ($success) {
            cli_writeln("\n✓ Full protocol completed successfully");
            
            // Show summary
            $updatedrun = $DB->get_record('local_ci_run', ['id' => $runid]);
            $nbresults = $DB->get_records('local_ci_nb_result', ['runid' => $runid]);
            
            cli_writeln("\n=== Execution Summary ===");
            cli_writeln("  Total duration: " . round($duration, 2) . " seconds");
            cli_writeln("  Total tokens: " . ($updatedrun->actualtokens ?? 0));
            cli_writeln("  Total cost: $" . number_format($updatedrun->actualcost ?? 0, 4));
            cli_writeln("  NBs completed: " . count($nbresults) . "/15");
            
            if ($options['verbose']) {
                cli_writeln("\n=== NB Results ===");
                foreach ($nbresults as $nbresult) {
                    $status = $nbresult->status == 'completed' ? '✓' : '✗';
                    cli_writeln("  $status {$nbresult->nbcode}: " . 
                               "{$nbresult->tokensused} tokens, " .
                               "{$nbresult->durationms}ms");
                }
            }
            
            // Check telemetry
            $telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
            cli_writeln("\n  Telemetry records: " . count($telemetry));
            
        } else {
            cli_error("Protocol execution failed - check logs for details");
        }
        
    } else {
        // Just test setup
        cli_writeln("\n=== Test setup complete ===");
        cli_writeln("Run with --nbcode=NB1 to test single NB");
        cli_writeln("Run with --full to test full protocol");
    }
    
    // Show test data locations
    cli_writeln("\n=== Test Data Created ===");
    cli_writeln("  Company ID: {$company->id}");
    cli_writeln("  Run ID: $runid");
    cli_writeln("  Database tables populated:");
    cli_writeln("    - local_ci_company");
    cli_writeln("    - local_ci_source");
    cli_writeln("    - local_ci_source_chunk");
    cli_writeln("    - local_ci_run");
    
    if ($options['nbcode'] || $options['full']) {
        cli_writeln("    - local_ci_nb_result");
        cli_writeln("    - local_ci_telemetry");
    }
    
    cli_writeln("\n✓ Test complete!");
    
} catch (Exception $e) {
    cli_error("Test failed: " . $e->getMessage());
}

exit(0);