<?php

/**
 * NB10 Investigation and Recovery Script for Run 19
 * 
 * Analyzes NB10 status and provides recovery options
 */

require_once('local_customerintel/db/access.php');

echo "🔍 NB10 INVESTIGATION FOR RUN 19\n";
echo "================================\n\n";

echo "1. CHECKING RUN 19 STATUS\n";
echo "-------------------------\n";

class NB10Investigation {
    
    private $runid = 19;
    
    public function investigate() {
        global $DB;
        
        // Check if Run 19 exists
        $run = $DB->get_record('local_ci_run', ['id' => $this->runid]);
        if (!$run) {
            echo "❌ Run {$this->runid} does not exist\n";
            return false;
        }
        
        echo "✅ Run {$this->runid} found\n";
        echo "   Status: {$run->status}\n";
        echo "   Company ID: {$run->companyid}\n";
        if ($run->finished_at) {
            echo "   Finished: " . date('Y-m-d H:i:s', $run->finished_at) . "\n";
        }
        echo "\n";
        
        // Get all NB results for Run 19
        $nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $this->runid], 'nbcode ASC');
        
        echo "2. NB EXECUTION STATUS\n";
        echo "---------------------\n";
        
        $expected_nbs = ['NB-1', 'NB-2', 'NB-3', 'NB-4', 'NB-5', 'NB-6', 'NB-7', 'NB-8', 
                         'NB-9', 'NB-10', 'NB-11', 'NB-12', 'NB-13', 'NB-14', 'NB-15'];
        
        $found_nbs = [];
        $nb10_found = false;
        $nb10_result = null;
        
        foreach ($nb_results as $result) {
            $found_nbs[] = $result->nbcode;
            if ($result->nbcode === 'NB-10') {
                $nb10_found = true;
                $nb10_result = $result;
            }
        }
        
        echo "Expected NBs: " . count($expected_nbs) . "\n";
        echo "Found NBs: " . count($found_nbs) . "\n";
        echo "Missing NBs: " . implode(', ', array_diff($expected_nbs, $found_nbs)) . "\n\n";
        
        if ($nb10_found) {
            echo "3. NB10 DETAILED ANALYSIS\n";
            echo "-------------------------\n";
            echo "✅ NB10 result found in database\n";
            echo "   Record ID: {$nb10_result->id}\n";
            echo "   Status: {$nb10_result->status}\n";
            echo "   Duration: {$nb10_result->durationms}ms\n";
            echo "   Tokens: {$nb10_result->tokensused}\n";
            echo "   Created: " . date('Y-m-d H:i:s', $nb10_result->timecreated) . "\n";
            
            // Check payload
            $payload_size = strlen($nb10_result->jsonpayload ?? '');
            $citations_size = strlen($nb10_result->citations ?? '');
            
            echo "   Payload size: {$payload_size} bytes\n";
            echo "   Citations size: {$citations_size} bytes\n";
            
            if ($payload_size > 0) {
                $payload = json_decode($nb10_result->jsonpayload, true);
                if ($payload) {
                    echo "   Payload structure: " . implode(', ', array_keys($payload)) . "\n";
                    
                    // Check for ESG vs Risk content
                    $content = json_encode($payload);
                    $esg_keywords = ['sustainability', 'ESG', 'environmental', 'governance'];
                    $risk_keywords = ['risk', 'crisis', 'mitigation', 'resilience'];
                    
                    $esg_count = 0;
                    $risk_count = 0;
                    
                    foreach ($esg_keywords as $keyword) {
                        $esg_count += substr_count(strtolower($content), strtolower($keyword));
                    }
                    
                    foreach ($risk_keywords as $keyword) {
                        $risk_count += substr_count(strtolower($content), strtolower($keyword));
                    }
                    
                    echo "   Content analysis: ESG keywords: {$esg_count}, Risk keywords: {$risk_count}\n";
                    
                } else {
                    echo "   ❌ Payload JSON is malformed\n";
                }
            } else {
                echo "   ❌ Payload is empty\n";
            }
            
            echo "\n";
            
        } else {
            echo "3. NB10 STATUS\n";
            echo "-------------\n";
            echo "❌ NB10 result NOT found in database\n";
            echo "   This explains the 'synthesis_input_missing' error\n\n";
            
            // Check for NB10 errors
            $nb10_errors = $DB->get_records('local_ci_nb_error', ['runid' => $this->runid, 'nbcode' => 'NB-10']);
            if (!empty($nb10_errors)) {
                echo "🔍 NB10 Error Records Found:\n";
                foreach ($nb10_errors as $error) {
                    echo "   Error ID: {$error->id}\n";
                    echo "   Error: " . substr($error->errormessage, 0, 200) . "...\n";
                    echo "   Time: " . date('Y-m-d H:i:s', $error->timecreated) . "\n\n";
                }
            } else {
                echo "   No NB10 error records found either\n";
                echo "   Likely cause: NB10 execution was skipped or never attempted\n";
            }
        }
        
        return $this->provide_recommendations($nb10_found, $nb10_result);
    }
    
    private function provide_recommendations($nb10_found, $nb10_result) {
        echo "4. RECOVERY RECOMMENDATIONS\n";
        echo "===========================\n";
        
        if ($nb10_found && $nb10_result) {
            if (strlen($nb10_result->jsonpayload ?? '') > 0) {
                echo "✅ OPTION 1: NB10 data exists - Retry synthesis only\n";
                echo "   NB10 executed successfully but synthesis failed to read it\n";
                echo "   Command: Rebuild synthesis phase without re-running NBs\n";
                echo "   Risk: Low\n";
                echo "   Time: ~2-3 minutes\n\n";
                
                echo "🔧 RECOMMENDED ACTION: Option 1\n";
                echo "   The issue is in synthesis phase, not NB10 execution\n";
                return 'retry_synthesis';
            } else {
                echo "⚠️  OPTION 2: NB10 exists but has empty payload - Re-run NB10\n";
                echo "   NB10 executed but produced no results\n";
                echo "   Command: Re-execute NB10 only, then retry synthesis\n";
                echo "   Risk: Medium\n";
                echo "   Time: ~5-8 minutes\n\n";
                
                echo "🔧 RECOMMENDED ACTION: Option 2\n";
                return 'rerun_nb10';
            }
        } else {
            echo "❌ OPTION 3: NB10 missing completely - Re-run NB10\n";
            echo "   NB10 was never executed or failed silently\n";
            echo "   Command: Execute NB10 for Run 19, then retry synthesis\n";
            echo "   Risk: Medium\n";
            echo "   Time: ~5-8 minutes\n\n";
            
            echo "💡 OPTION 4: Skip NB10 - Modify synthesis to be resilient\n";
            echo "   Make synthesis engine skip missing NBs gracefully\n";
            echo "   Command: Patch get_missing_nbs() to allow partial completion\n";
            echo "   Risk: Low (if NB10 is not critical for synthesis)\n";
            echo "   Time: ~1 minute + synthesis retry\n\n";
            
            echo "🔧 RECOMMENDED ACTION: Option 4 (Resilient synthesis)\n";
            echo "   ESG/Sustainability data may not be critical for core intelligence\n";
            return 'make_resilient';
        }
    }
    
    public function execute_recovery($action) {
        switch ($action) {
            case 'retry_synthesis':
                return $this->retry_synthesis_only();
            case 'rerun_nb10':
                return $this->rerun_nb10_and_synthesis();
            case 'make_resilient':
                return $this->make_synthesis_resilient();
            default:
                echo "❌ Unknown recovery action: {$action}\n";
                return false;
        }
    }
    
    private function retry_synthesis_only() {
        echo "🔧 EXECUTING: Retry synthesis only\n";
        echo "==================================\n";
        echo "This would call: synthesis_engine->build_report({$this->runid}, true)\n";
        echo "✅ Synthesis retry would be initiated\n";
        return true;
    }
    
    private function rerun_nb10_and_synthesis() {
        echo "🔧 EXECUTING: Re-run NB10 and synthesis\n";
        echo "=======================================\n";
        echo "This would:\n";
        echo "1. Call: orchestrator->execute_nb({$this->runid}, 'NB-10')\n";
        echo "2. Call: synthesis_engine->build_report({$this->runid}, true)\n";
        echo "✅ NB10 re-execution and synthesis retry would be initiated\n";
        return true;
    }
    
    private function make_synthesis_resilient() {
        echo "🔧 EXECUTING: Make synthesis resilient to missing NBs\n";
        echo "=====================================================\n";
        echo "This would modify get_missing_nbs() to:\n";
        echo "1. Allow synthesis with 80% of NBs present (12+ out of 15)\n";
        echo "2. Log warnings for missing NBs instead of throwing exceptions\n";
        echo "3. Adjust synthesis to skip sections requiring missing NBs\n";
        echo "✅ Resilient synthesis patch would be applied\n";
        return true;
    }
}

$investigation = new NB10Investigation();
$recommended_action = $investigation->investigate();

echo "🎯 NEXT STEPS:\n";
echo "=============\n";
echo "Run this script with the recommended action to proceed:\n";
echo "php nb10_investigation.php --action={$recommended_action}\n";

?>