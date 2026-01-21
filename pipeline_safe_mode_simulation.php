<?php
/**
 * Pipeline Safe Mode Simulation Script
 * 
 * Simulates the enhanced error handling and retry logic
 * that has been implemented for robust pipeline execution.
 * 
 * Shows expected log output for various failure scenarios.
 */

echo "=== PIPELINE SAFE MODE SIMULATION ===\n\n";

// Simulate NB Orchestrator execution with retry logic
echo "1. NB ORCHESTRATOR - ENHANCED RETRY LOGIC\n";
echo "=========================================\n";

simulate_nb_orchestrator_with_retries();

echo "\n\n";

// Simulate NB execution with placeholder creation
echo "2. NB EXECUTION - PLACEHOLDER CREATION\n";
echo "======================================\n";

simulate_nb_execution_with_placeholders();

echo "\n\n";

// Simulate synthesis with graceful handling
echo "3. SYNTHESIS ENGINE - GRACEFUL HANDLING\n";
echo "=======================================\n";

simulate_synthesis_with_graceful_handling();

echo "\n\n";

// Simulate final report with appendix
echo "4. FINAL REPORT - APPENDIX NOTES\n";
echo "=================================\n";

simulate_final_report_with_appendix();

echo "\n\nSimulation completed successfully!\n";

function simulate_nb_orchestrator_with_retries() {
    $runid = 17;
    
    echo "[INFO] Perplexity initial - HTTP: 0, errno: 28, error: Timeout, elapsed: 30000ms\n";
    echo "[WARN] Retrying Perplexity after network error (errno: 28) in 2s...\n";
    echo "[INFO] Perplexity retry #1 - HTTP: 0, errno: 28, error: Timeout, elapsed: 30000ms\n";
    echo "[WARN] Retrying Perplexity after network error (errno: 28) in 4s...\n";
    echo "[INFO] Perplexity retry #2 - HTTP: 200, errno: 0, error: None, elapsed: 1247ms\n";
    echo "[INFO] Perplexity API succeeded after 2 retries\n";
    echo "[INFO] NB execution completed successfully with enhanced retry logic\n";
}

function simulate_nb_execution_with_placeholders() {
    $runid = 17;
    $nbcode = 'NB10';
    
    echo "[INFO] Starting NB execution for {$nbcode}\n";
    echo "[INFO] Exception on attempt 1: API rate limit exceeded\n";
    echo "[INFO] Exception on attempt 2: Network timeout\n";
    echo "[INFO] Exception on attempt 3: JSON validation failed\n";
    echo "[INFO] Creating placeholder result: NB execution failed with exception: JSON validation failed\n";
    echo "[INFO] Placeholder result created for {$nbcode} with fallback risk assessment data\n";
    echo "[INFO] NB execution completed with placeholder - pipeline continues\n";
}

function simulate_synthesis_with_graceful_handling() {
    $runid = 17;
    
    echo "[INFO] Synthesis starting with 13 NBs available, 2 placeholder NBs detected\n";
    echo "[INFO] Processing NB1 (Financial) - placeholder detected, using fallback: 'Financial performance optimization (data unavailable)'\n";
    echo "[INFO] Processing NB3 (Operational) - data available, extracting operational issues\n";
    echo "[INFO] Processing NB4 (Competitive) - data available, extracting competitive threats\n";
    echo "[INFO] Processing NB10 (Risk) - placeholder detected, using fallback: 'Risk management framework (data unavailable)'\n";
    echo "[INFO] Executive Insight generation completed with graceful fallback integration\n";
    echo "[INFO] All sections drafted successfully with placeholder-aware processing\n";
}

function simulate_final_report_with_appendix() {
    $placeholder_nbs = ['NB1', 'NB10'];
    
    echo "[INFO] Collecting placeholder NB information for appendix\n";
    echo "[INFO] Found 2 placeholder NBs: " . implode(', ', $placeholder_nbs) . "\n";
    echo "[INFO] Adding appendix note: 'Data Processing Notes'\n";
    echo "[INFO] Appendix content: 'The following analysis modules encountered processing issues and used fallback data: NB1, NB10. This may affect the depth of insights in certain sections but does not compromise the overall strategic recommendations.'\n";
    echo "[INFO] Final report bundle prepared with transparency notes\n";
    echo "[INFO] Synthesis completed successfully with full transparency\n";
}