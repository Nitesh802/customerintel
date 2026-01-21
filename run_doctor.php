<?php

/**
 * Run Doctor - One-Pass Pipeline Diagnostic Tool
 * 
 * Analyzes the latest run to check:
 * - NB citation extraction
 * - Domain normalization 
 * - Diversity rebalancing
 * - Evidence validation
 * - Synthesis execution
 */

require_once('local_customerintel/db/access.php');

class RunDoctor {
    
    private $runid;
    private $run_data;
    
    public function __construct() {
        global $DB;
        
        // Get the latest run
        $this->run_data = $DB->get_record_sql('SELECT * FROM {local_customerintel_run} ORDER BY id DESC LIMIT 1');
        
        if (!$this->run_data) {
            throw new Exception("No runs found in database");
        }
        
        $this->runid = $this->run_data->id;
        
        echo "🩺 RUN DOCTOR - Latest Run Analysis\n";
        echo "===================================\n";
        echo "Run ID: {$this->runid}\n";
        echo "Status: {$this->run_data->status}\n";
        echo "Company ID: {$this->run_data->company_id}\n";
        echo "Mode: {$this->run_data->mode}\n";
        echo "Started: " . date('Y-m-d H:i:s', $this->run_data->started_at) . "\n";
        if ($this->run_data->finished_at) {
            echo "Finished: " . date('Y-m-d H:i:s', $this->run_data->finished_at) . "\n";
        }
        echo "\n";
    }
    
    public function diagnose() {
        echo "📋 PIPELINE CHECKLIST\n";
        echo "====================\n\n";
        
        $this->check_nb_extraction();
        $this->check_normalization();
        $this->check_rebalancing();
        $this->check_validation();
        $this->check_synthesis();
        
        echo "\n🏁 DIAGNOSIS COMPLETE\n";
    }
    
    private function check_nb_extraction() {
        global $DB;
        
        echo "1. NB EXTRACTION\n";
        echo "----------------\n";
        
        // Check for extracted citations
        $nb_results = $DB->get_records('local_customerintel_nb_result', ['runid' => $this->runid]);
        $citation_count = 0;
        
        if (!empty($nb_results)) {
            foreach ($nb_results as $result) {
                if (!empty($result->citations)) {
                    $citations_data = json_decode($result->citations, true);
                    if (is_array($citations_data)) {
                        $citation_count += count($citations_data);
                    }
                }
            }
            
            echo "• NB extracted citations? YES\n";
            echo "• Count: {$citation_count}\n";
            echo "• NB results found: " . count($nb_results) . "/15\n";
        } else {
            echo "• NB extracted citations? NO\n";
            echo "• Count: 0\n";
            echo "❌ ISSUE: No NB results found\n";
            echo "   Function: nb_orchestrator.php execute_protocol() or execute_full_protocol()\n";
            echo "   Missing: NB execution for run {$this->runid}\n";
        }
        echo "\n";
    }
    
    private function check_normalization() {
        echo "2. NORMALIZATION\n";
        echo "---------------\n";
        
        // Check for normalized citation artifacts
        $artifact_path = "local_customerintel/output/normalized_inputs_v16_{$this->runid}.json";
        
        if (file_exists($artifact_path)) {
            $file_size = filesize($artifact_path);
            $file_content = file_get_contents($artifact_path);
            $normalized_data = json_decode($file_content, true);
            
            echo "• Normalization executed? YES\n";
            echo "• File created? YES\n";
            echo "• Size: " . number_format($file_size) . " bytes\n";
            
            // Sample 3 citations with domain fields
            echo "• Sample citations with domains:\n";
            $sample_count = 0;
            if (isset($normalized_data['inputs']) && is_array($normalized_data['inputs'])) {
                foreach ($normalized_data['inputs'] as $nb_key => $nb_data) {
                    if (isset($nb_data['citations']) && is_array($nb_data['citations'])) {
                        foreach ($nb_data['citations'] as $citation) {
                            if (isset($citation['domain']) && $sample_count < 3) {
                                echo "  - {$citation['title']} (domain: {$citation['domain']})\n";
                                $sample_count++;
                            }
                        }
                    }
                    if ($sample_count >= 3) break;
                }
            }
            
            if ($sample_count === 0) {
                echo "  ❌ No citations with domain fields found\n";
                echo "     Issue: Citations normalized but missing domain fields\n";
            }
            
        } else {
            echo "• Normalization executed? NO\n";
            echo "• File created? NO\n";
            echo "• Size: 0 bytes\n";
            echo "❌ ISSUE: Normalization artifact missing\n";
            echo "   Function: nb_orchestrator.php normalize_citation_domains()\n";
            echo "   Missing: normalized_inputs_v16_{$this->runid}.json\n";
            echo "   Cause: normalize_citation_domains() not called in execution path\n";
        }
        echo "\n";
    }
    
    private function check_rebalancing() {
        echo "3. REBALANCING\n";
        echo "-------------\n";
        
        // Check for diversity artifacts or evidence
        $diversity_path = "local_customerintel/output/diversity_analysis_{$this->runid}.json";
        $rebalance_path = "local_customerintel/output/rebalanced_sources_{$this->runid}.json";
        
        $diversity_score = 0;
        $unique_domains = 0;
        
        if (file_exists($diversity_path)) {
            $diversity_data = json_decode(file_get_contents($diversity_path), true);
            $diversity_score = isset($diversity_data['diversity_score']) ? $diversity_data['diversity_score'] : 0;
            $unique_domains = isset($diversity_data['unique_domains']) ? count($diversity_data['unique_domains']) : 0;
            
            echo "• Rebalancing executed? YES\n";
            echo "• Diversity score: {$diversity_score}\n";
            echo "• Unique domains: {$unique_domains}\n";
        } else {
            echo "• Rebalancing executed? NO\n";
            echo "• Diversity score: 0\n";
            echo "• Unique domains: 0\n";
            echo "❌ ISSUE: Diversity analysis not performed\n";
            echo "   Function: synthesis_engine.php analyze_citation_diversity()\n";
            echo "   Missing: Domain-enhanced citations for diversity calculation\n";
        }
        echo "\n";
    }
    
    private function check_validation() {
        echo "4. VALIDATION\n";
        echo "------------\n";
        
        // Check for validation artifacts
        $validation_path = "local_customerintel/output/diversity_validation_{$this->runid}.json";
        
        if (file_exists($validation_path)) {
            $validation_data = json_decode(file_get_contents($validation_path), true);
            $status = isset($validation_data['validation_result']['status']) ? $validation_data['validation_result']['status'] : 'UNKNOWN';
            
            echo "• Validation executed? YES\n";
            echo "• Status: {$status}\n";
        } else {
            echo "• Validation executed? NO\n";
            echo "• Status: NOT_RUN\n";
            echo "❌ ISSUE: Evidence diversity validation missing\n";
            echo "   Function: cli/validate_evidence_diversity.php\n";
            echo "   Missing: Diversity metrics validation step\n";
        }
        echo "\n";
    }
    
    private function check_synthesis() {
        echo "5. SYNTHESIS\n";
        echo "-----------\n";
        
        // Check for synthesis artifacts
        $synthesis_path = "local_customerintel/output/synthesis_{$this->runid}.json";
        $report_path = "local_customerintel/output/report_{$this->runid}.html";
        
        $blueprint_used = "UNKNOWN";
        $evidence_diversity_context = "";
        
        if (file_exists($synthesis_path)) {
            $synthesis_data = json_decode(file_get_contents($synthesis_path), true);
            $blueprint_used = isset($synthesis_data['blueprint_version']) ? $synthesis_data['blueprint_version'] : 'v16 (default)';
            
            // Look for Evidence Diversity Context in synthesis
            if (isset($synthesis_data['sections'])) {
                foreach ($synthesis_data['sections'] as $section) {
                    if (isset($section['content']) && strpos($section['content'], 'Evidence Diversity Context') !== false) {
                        // Extract first 2 lines
                        $lines = explode("\n", $section['content']);
                        $context_started = false;
                        $line_count = 0;
                        foreach ($lines as $line) {
                            if (strpos($line, 'Evidence Diversity Context') !== false) {
                                $context_started = true;
                                continue;
                            }
                            if ($context_started && trim($line) !== '' && $line_count < 2) {
                                $evidence_diversity_context .= trim($line) . "\n";
                                $line_count++;
                            }
                            if ($line_count >= 2) break;
                        }
                        break;
                    }
                }
            }
            
            echo "• Synthesis executed? YES\n";
            echo "• Blueprint used: {$blueprint_used}\n";
            echo "• First 2 lines of Evidence Diversity Context:\n";
            if (!empty($evidence_diversity_context)) {
                $lines = explode("\n", trim($evidence_diversity_context));
                foreach ($lines as $i => $line) {
                    if ($i < 2 && !empty(trim($line))) {
                        echo "  " . ($i + 1) . ". " . trim($line) . "\n";
                    }
                }
            } else {
                echo "  ❌ No Evidence Diversity Context found\n";
                echo "     Issue: Synthesis executed but diversity context missing/empty\n";
            }
            
        } else {
            echo "• Synthesis executed? NO\n";
            echo "• Blueprint used: NONE\n";
            echo "• Evidence Diversity Context: MISSING\n";
            echo "❌ ISSUE: Synthesis not executed\n";
            echo "   Function: synthesis_engine.php generate_synthesis()\n";
            echo "   Missing: Synthesis execution or diversity context injection\n";
        }
        echo "\n";
    }
}

try {
    $doctor = new RunDoctor();
    $doctor->diagnose();
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>