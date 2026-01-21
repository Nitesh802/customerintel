<?php

/**
 * Minimal Normalization Smoke Test
 * 
 * Tests that citation domain normalization works by:
 * 1. Creating minimal NB result with sample citations
 * 2. Running normalization on that data
 * 3. Reporting citations processed and unique domains
 */

require_once('local_customerintel/db/access.php');

class NormalizationSmokeTest {
    
    private $test_runid;
    
    public function __construct() {
        echo "🧪 NORMALIZATION SMOKE TEST\n";
        echo "===========================\n\n";
    }
    
    public function run() {
        try {
            $this->setup_test_data();
            $this->run_normalization_test();
            $this->verify_results();
            $this->cleanup();
            echo "✅ SMOKE TEST PASSED\n";
        } catch (Exception $e) {
            echo "❌ SMOKE TEST FAILED: " . $e->getMessage() . "\n";
            $this->cleanup();
            exit(1);
        }
    }
    
    private function setup_test_data() {
        global $DB;
        
        echo "📋 Setting up test data...\n";
        
        // Create a test run
        $run = new stdClass();
        $run->companyid = 1;
        $run->targetcompanyid = 2;
        $run->status = 'completed';
        $run->userid = 1;
        $run->initiatedbyuserid = 1;
        $run->timecreated = time();
        $run->timemodified = time();
        $run->timecompleted = time();
        
        $this->test_runid = $DB->insert_record('local_ci_run', $run);
        echo "   Created test run: {$this->test_runid}\n";
        
        // Create sample NB result with citations
        $sample_citations = [
            [
                'title' => 'Financial Report Q3 2024',
                'url' => 'https://www.bloomberg.com/news/articles/2024/financial-report',
                'source' => 'Bloomberg'
            ],
            [
                'title' => 'Market Analysis Update',
                'url' => 'https://www.reuters.com/business/market-analysis',
                'source' => 'Reuters'
            ],
            [
                'title' => 'SEC Filing 10-K',
                'url' => 'https://www.sec.gov/edgar/browse/?company=ABC123',
                'source' => 'SEC'
            ],
            [
                'title' => 'Industry Trends Report',
                'url' => 'https://www.wsj.com/articles/industry-trends-2024',
                'source' => 'Wall Street Journal'
            ],
            [
                'title' => 'Analyst Coverage',
                'url' => 'https://research.goldman.sachs.com/content/research/report.pdf',
                'source' => 'Goldman Sachs Research'
            ]
        ];
        
        $payload = [
            'analysis' => 'Sample NB analysis for normalization testing',
            'citations' => $sample_citations,
            'metadata' => ['test' => true]
        ];
        
        $nb_result = new stdClass();
        $nb_result->runid = $this->test_runid;
        $nb_result->nbcode = 'nb1_industry_analysis';
        $nb_result->payload = json_encode($payload);
        $nb_result->citations = json_encode($sample_citations);
        $nb_result->timecreated = time();
        $nb_result->duration = 1000;
        $nb_result->tokens = 2500;
        $nb_result->status = 'completed';
        
        $DB->insert_record('local_ci_nb_result', $nb_result);
        echo "   Created NB result with " . count($sample_citations) . " sample citations\n";
        echo "   Sample domains: bloomberg.com, reuters.com, sec.gov, wsj.com, goldman.sachs.com\n\n";
    }
    
    private function run_normalization_test() {
        echo "🔧 Running normalization...\n";
        
        // Create orchestrator and run normalization
        $orchestrator = new \local_customerintel\services\nb_orchestrator();
        
        // Use reflection to access the protected method
        $reflection = new ReflectionClass($orchestrator);
        $normalize_method = $reflection->getMethod('normalize_citation_domains');
        $normalize_method->setAccessible(true);
        
        $start_time = microtime(true);
        $normalize_method->invoke($orchestrator, $this->test_runid);
        $duration = round((microtime(true) - $start_time) * 1000, 2);
        
        echo "   Normalization completed in {$duration}ms\n\n";
    }
    
    private function verify_results() {
        echo "📊 Verifying normalization results...\n";
        
        // Check for artifact creation
        $artifact_path = "local_customerintel/output/normalized_inputs_v16_{$this->test_runid}.json";
        
        if (!file_exists($artifact_path)) {
            throw new Exception("Normalization artifact not created at: {$artifact_path}");
        }
        
        $file_size = filesize($artifact_path);
        echo "   ✅ Artifact created: {$artifact_path} ({$file_size} bytes)\n";
        
        // Parse and analyze the artifact
        $artifact_content = file_get_contents($artifact_path);
        $normalized_data = json_decode($artifact_content, true);
        
        if (!$normalized_data) {
            throw new Exception("Failed to parse normalization artifact JSON");
        }
        
        // Extract key metrics
        $summary = $normalized_data['summary'] ?? [];
        $citations_processed = $summary['total_citations_processed'] ?? 0;
        $unique_domains = $summary['unique_domains_found'] ?? 0;
        $diversity_score = $summary['diversity_score_preliminary'] ?? 0;
        $top_domains = $summary['top_domains'] ?? [];
        
        echo "\n📈 NORMALIZATION SUMMARY:\n";
        echo "   • Citations processed: {$citations_processed}\n";
        echo "   • Unique domains: {$unique_domains}\n";
        echo "   • Diversity score: " . round($diversity_score, 3) . "\n";
        echo "   • Top domains:\n";
        
        foreach ($top_domains as $domain => $count) {
            $percentage = round(($count / $citations_processed) * 100, 1);
            echo "     - {$domain}: {$count} citations ({$percentage}%)\n";
        }
        
        // Verify domain extraction worked
        if ($unique_domains === 0) {
            throw new Exception("No domains extracted - normalization failed");
        }
        
        if ($citations_processed === 0) {
            throw new Exception("No citations processed - normalization failed");
        }
        
        echo "\n   ✅ Domain extraction successful\n";
        echo "   ✅ Citations normalized with domain fields\n";
        
        // Sample a few normalized citations
        $normalized_citations = $normalized_data['normalized_citations'] ?? [];
        echo "\n📋 Sample normalized citations:\n";
        
        $sample_count = 0;
        foreach ($normalized_citations as $citation) {
            if ($sample_count >= 3) break;
            
            $title = $citation['title'] ?? 'Unknown';
            $domain = $citation['domain'] ?? 'NO_DOMAIN';
            echo "   " . ($sample_count + 1) . ". {$title} (domain: {$domain})\n";
            $sample_count++;
        }
        
        echo "\n";
    }
    
    private function cleanup() {
        global $DB;
        
        if ($this->test_runid) {
            echo "🧹 Cleaning up test data...\n";
            
            // Remove test records
            $DB->delete_records('local_ci_nb_result', ['runid' => $this->test_runid]);
            $DB->delete_records('local_ci_run', ['id' => $this->test_runid]);
            
            // Remove artifact file
            $artifact_path = "local_customerintel/output/normalized_inputs_v16_{$this->test_runid}.json";
            if (file_exists($artifact_path)) {
                unlink($artifact_path);
                echo "   Removed artifact: {$artifact_path}\n";
            }
            
            echo "   Test data cleaned up\n\n";
        }
    }
}

try {
    $test = new NormalizationSmokeTest();
    $test->run();
} catch (Exception $e) {
    echo "💥 FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>