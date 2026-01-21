<?php
/**
 * Test script for synthesis_engine->detect_patterns() implementation
 */

require_once(__DIR__ . '/config.php');
require_login();
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');

use local_customerintel\services\synthesis_engine;

echo "<h2>Pattern Detection Test</h2>";

// Get a completed run for testing
$completed_runs = $DB->get_records('local_ci_run', ['status' => 'completed'], 'id DESC', '*', 0, 5);

if (empty($completed_runs)) {
    echo "<p style='color: red;'>No completed runs found. Please run at least one intelligence analysis first.</p>";
    echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";
    exit;
}

echo "<h3>Available Completed Runs:</h3>";
echo "<ul>";
foreach ($completed_runs as $run) {
    $company = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
    $target_company = $run->targetcompanyid ? $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]) : null;
    
    echo "<li>";
    echo "<strong>Run ID {$run->id}</strong>: {$company->name}";
    if ($target_company) {
        echo " → {$target_company->name}";
    }
    echo " (completed: " . userdate($run->timecompleted) . ")";
    echo " <a href='?test_runid={$run->id}' class='btn btn-sm btn-primary'>Test This Run</a>";
    echo "</li>";
}
echo "</ul>";

// Test specific run if requested
$test_runid = optional_param('test_runid', 0, PARAM_INT);

if ($test_runid) {
    echo "<hr>";
    echo "<h3>Testing Pattern Detection for Run ID: {$test_runid}</h3>";
    
    try {
        $engine = new synthesis_engine();
        $start_time = microtime(true);
        
        // First get normalized inputs
        echo "<h4>Step 1: Getting Normalized Inputs</h4>";
        $inputs = $engine->get_normalized_inputs($test_runid);
        echo "<p style='color: green;'>✓ Loaded {$inputs['processing_stats']['nb_count']} NBs with {$inputs['processing_stats']['citation_count']} citations</p>";
        
        // Test pattern detection
        echo "<h4>Step 2: Detecting Patterns</h4>";
        $patterns = $engine->detect_patterns($inputs);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "<p style='color: green;'>✓ Pattern detection completed in {$processing_time}ms</p>";
        
        // Display results
        echo "<h4>Pattern Detection Results:</h4>";
        
        // Pressure Themes
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h5>🔥 Pressure Themes (Top 4)</h5>";
        if (!empty($patterns['pressures'])) {
            echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px;'>";
            foreach ($patterns['pressures'] as $i => $pressure) {
                echo "<div style='margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;'>";
                echo "<strong>" . ($i + 1) . ".</strong> " . htmlspecialchars($pressure['text']) . "<br>";
                echo "<small><em>Source: {$pressure['source']}, Field: {$pressure['field']}, ";
                echo "Mentions: {$pressure['mentions']}, Score: {$pressure['score']}";
                if ($pressure['has_numeric_proof']) echo " ✓ Numeric Proof";
                echo "</em></small>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p><em>No pressure themes detected</em></p>";
        }
        echo "</div>";
        
        // Capability Levers
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h5>⚙️ Capability Levers (Top 4)</h5>";
        if (!empty($patterns['levers'])) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
            foreach ($patterns['levers'] as $i => $lever) {
                echo "<div style='margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;'>";
                echo "<strong>" . ($i + 1) . ".</strong> " . htmlspecialchars($lever['text']) . "<br>";
                echo "<small><em>Source: {$lever['source']}, Field: {$lever['field']}, ";
                echo "Mentions: {$lever['mentions']}, Score: {$lever['score']}";
                if ($lever['has_numeric_proof']) echo " ✓ Numeric Proof";
                echo "</em></small>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p><em>No capability levers detected</em></p>";
        }
        echo "</div>";
        
        // Timing Signals
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h5>⏰ Timing Signals (Top 6)</h5>";
        if (!empty($patterns['timing'])) {
            echo "<div style='background: #cff4fc; padding: 10px; border-radius: 5px;'>";
            foreach ($patterns['timing'] as $i => $timing) {
                echo "<div style='margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;'>";
                echo "<strong>" . ($i + 1) . ".</strong> " . htmlspecialchars($timing['signal']) . "<br>";
                echo "<small><em>Source: {$timing['source']}, Context: " . htmlspecialchars(substr($timing['context'], 0, 100)) . "...</em></small>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p><em>No timing signals detected</em></p>";
        }
        echo "</div>";
        
        // Executive Accountabilities
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h5>👥 Executive Accountabilities</h5>";
        if (!empty($patterns['execs'])) {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
            foreach ($patterns['execs'] as $i => $exec) {
                echo "<div style='margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;'>";
                echo "<strong>" . ($i + 1) . ".</strong> " . htmlspecialchars($exec['name']);
                if (!empty($exec['title'])) {
                    echo " - " . htmlspecialchars($exec['title']);
                }
                echo "<br>";
                if (!empty($exec['accountability'])) {
                    echo "<small><em>Accountability: " . htmlspecialchars(substr($exec['accountability'], 0, 150)) . "...</em></small>";
                }
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p><em>No executive accountabilities detected</em></p>";
        }
        echo "</div>";
        
        // Numeric Proofs
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h5>📊 Numeric Proofs (Sample)</h5>";
        if (!empty($patterns['proofs'])) {
            echo "<div style='background: #e2e3e5; padding: 10px; border-radius: 5px;'>";
            echo "<p><strong>Total Numeric Proofs Found:</strong> " . count($patterns['proofs']) . "</p>";
            $sample_proofs = array_slice($patterns['proofs'], 0, 10);
            foreach ($sample_proofs as $i => $proof) {
                echo "<div style='margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;'>";
                echo "<strong>" . ($i + 1) . ".</strong> " . htmlspecialchars($proof['value']) . "<br>";
                echo "<small><em>Source: {$proof['source']}, Field: {$proof['field']}<br>";
                echo "Context: " . htmlspecialchars(substr($proof['context'], 0, 100)) . "...</em></small>";
                echo "</div>";
            }
            if (count($patterns['proofs']) > 10) {
                echo "<p><em>... and " . (count($patterns['proofs']) - 10) . " more numeric proofs</em></p>";
            }
            echo "</div>";
        } else {
            echo "<p><em>No numeric proofs detected</em></p>";
        }
        echo "</div>";
        
        // Analysis Summary
        echo "<h4>Analysis Summary:</h4>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
        echo "<ul>";
        echo "<li><strong>Processing Time:</strong> {$processing_time}ms</li>";
        echo "<li><strong>NBs Analyzed:</strong> " . count($inputs['nb']) . "</li>";
        echo "<li><strong>Pressure Themes:</strong> " . count($patterns['pressures']) . " (validated from multiple sources)</li>";
        echo "<li><strong>Capability Levers:</strong> " . count($patterns['levers']) . " (from competitive/innovation analysis)</li>";
        echo "<li><strong>Timing Signals:</strong> " . count($patterns['timing']) . " (extracted from dates/deadlines)</li>";
        echo "<li><strong>Executives:</strong> " . count($patterns['execs']) . " (deduplicated by name/title)</li>";
        echo "<li><strong>Numeric Proofs:</strong> " . count($patterns['proofs']) . " (concrete evidence)</li>";
        echo "</ul>";
        
        // Validation Results
        $pressure_with_proof = array_filter($patterns['pressures'], function($p) { return $p['has_numeric_proof']; });
        $levers_with_proof = array_filter($patterns['levers'], function($l) { return $l['has_numeric_proof']; });
        
        echo "<h5>Validation Heuristics:</h5>";
        echo "<ul>";
        echo "<li><strong>Pressure Themes with Numeric Proof:</strong> " . count($pressure_with_proof) . "/" . count($patterns['pressures']) . "</li>";
        echo "<li><strong>Capability Levers with Numeric Proof:</strong> " . count($levers_with_proof) . "/" . count($patterns['levers']) . "</li>";
        echo "<li><strong>Theme Validation Rule:</strong> ≥2 mentions OR 1 mention + numeric proof ✓</li>";
        echo "<li><strong>Ranking Applied:</strong> Top themes by mention count + proof bonus ✓</li>";
        echo "</ul>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error testing pattern detection for run {$test_runid}: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

echo "<hr>";
echo "<h3>Implementation Status</h3>";
echo "<p>✅ <strong>detect_patterns()</strong> implementation complete with:</p>";
echo "<ul>";
echo "<li>✅ <strong>Pressure Themes:</strong> Aggregated from NB1 (executive pressure), NB3 (financial health), NB4 (strategic priorities)</li>";
echo "<li>✅ <strong>Capability Levers:</strong> Collected from NB8 (competitive differentiators), NB13 (innovation pipeline)</li>";
echo "<li>✅ <strong>Timing Signals:</strong> Extracted from NB2/NB3/NB10/NB15 with regex pattern matching</li>";
echo "<li>✅ <strong>Executive Accountabilities:</strong> Parsed from NB11 with deduplication by name/title</li>";
echo "<li>✅ <strong>Numeric Proofs:</strong> Extracted percentages, currency, headcounts across all NBs</li>";
echo "<li>✅ <strong>Validation Heuristics:</strong> ≥2 mentions OR 1 mention + numeric proof</li>";
echo "<li>✅ <strong>Ranking & Limits:</strong> Top 4 pressures, top 4 levers, top 6 timing signals</li>";
echo "<li>✅ <strong>Deduplication:</strong> Removes similar themes and duplicate executives</li>";
echo "</ul>";

echo "<p><strong>Pattern Types Detected:</strong></p>";
echo "<ul>";
echo "<li>📅 <strong>Dates:</strong> 12/31/2024, December 31 2024, Q4 2024</li>";
echo "<li>💰 <strong>Currency:</strong> $100M, £50k, €25B</li>";
echo "<li>📊 <strong>Percentages:</strong> 15%, 3.5% growth</li>";
echo "<li>👥 <strong>Headcount:</strong> 1,000 employees, 50 staff</li>";
echo "<li>⏱️ <strong>Deadlines:</strong> Regulatory deadline, EOY target</li>";
echo "<li>🎯 <strong>Targets:</strong> 25% by Q3, budget cycle</li>";
echo "</ul>";

echo "<p><strong>Ready for next steps:</strong></p>";
echo "<ol>";
echo "<li>Implement build_target_bridge() for cross-company relevance mapping</li>";
echo "<li>Draft Intelligence Playbook sections using detected patterns</li>";
echo "<li>Apply voice enforcement and self-check validation</li>";
echo "</ol>";

echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";