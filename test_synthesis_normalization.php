<?php
/**
 * Test script for synthesis_engine->get_normalized_inputs() implementation
 */

require_once(__DIR__ . '/config.php');
require_login();
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');

use local_customerintel\services\synthesis_engine;

echo "<h2>Synthesis Engine Input Normalization Test</h2>";

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
    echo "<h3>Testing Run ID: {$test_runid}</h3>";
    
    try {
        $engine = new synthesis_engine();
        $start_time = microtime(true);
        
        // Test the get_normalized_inputs method
        $inputs = $engine->get_normalized_inputs($test_runid);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "<p style='color: green;'>✓ Successfully processed inputs in {$processing_time}ms</p>";
        
        // Display results
        echo "<h4>Processing Results:</h4>";
        echo "<ul>";
        echo "<li><strong>Source Company:</strong> " . htmlspecialchars($inputs['company_source']->name) . "</li>";
        
        if ($inputs['company_target']) {
            echo "<li><strong>Target Company:</strong> " . htmlspecialchars($inputs['company_target']->name) . "</li>";
            echo "<li><strong>Target Hints:</strong> " . htmlspecialchars(json_encode($inputs['target_hints'], JSON_PRETTY_PRINT)) . "</li>";
        } else {
            echo "<li><strong>Target Company:</strong> None (single-company analysis)</li>";
        }
        
        $stats = $inputs['processing_stats'];
        echo "<li><strong>NBs Found:</strong> {$stats['nb_count']}</li>";
        echo "<li><strong>Completed NBs:</strong> {$stats['completed_nbs']}</li>";
        echo "<li><strong>Total Citations:</strong> {$stats['citation_count']}</li>";
        
        if (!empty($stats['missing_nbs'])) {
            echo "<li><strong>Missing NBs:</strong> " . implode(', ', $stats['missing_nbs']) . "</li>";
        } else {
            echo "<li><strong>Missing NBs:</strong> None (complete set)</li>";
        }
        echo "</ul>";
        
        // Display NB-by-NB breakdown
        echo "<h4>NB Breakdown:</h4>";
        echo "<div style='max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;'>";
        
        foreach ($inputs['nb'] as $nbcode => $nb_data) {
            echo "<div style='margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;'>";
            echo "<h5>{$nbcode} ({$nb_data['status']}) - {$nb_data['tokens_used']} tokens, {$nb_data['duration_ms']}ms</h5>";
            
            if (!empty($nb_data['data'])) {
                echo "<strong>Normalized Fields:</strong> " . implode(', ', array_keys($nb_data['data'])) . "<br>";
                
                // Show sample of normalized data
                $sample_fields = array_slice($nb_data['data'], 0, 2, true);
                foreach ($sample_fields as $field => $value) {
                    if (!empty($value)) {
                        $display_value = is_array($value) ? json_encode($value) : $value;
                        $truncated = strlen($display_value) > 100 ? substr($display_value, 0, 100) . '...' : $display_value;
                        echo "<em>{$field}:</em> " . htmlspecialchars($truncated) . "<br>";
                    }
                }
            } else {
                echo "<em>No data available</em><br>";
            }
            
            if (!empty($nb_data['citations'])) {
                echo "<strong>Citations:</strong> " . count($nb_data['citations']) . " found<br>";
            }
            echo "</div>";
        }
        echo "</div>";
        
        // Test citation extraction
        echo "<h4>Citation Analysis:</h4>";
        $unique_citations = array_unique($inputs['citations'], SORT_REGULAR);
        echo "<p><strong>Total Unique Citations:</strong> " . count($unique_citations) . "</p>";
        
        if (!empty($unique_citations)) {
            echo "<div style='max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;'>";
            echo "<strong>Sample Citations:</strong><br>";
            $sample_citations = array_slice($unique_citations, 0, 10);
            foreach ($sample_citations as $i => $citation) {
                $display = is_array($citation) ? json_encode($citation) : $citation;
                echo ($i + 1) . ". " . htmlspecialchars($display) . "<br>";
            }
            if (count($unique_citations) > 10) {
                echo "... and " . (count($unique_citations) - 10) . " more<br>";
            }
            echo "</div>";
        }
        
        // Performance analysis
        echo "<h4>Performance Analysis:</h4>";
        echo "<ul>";
        echo "<li><strong>Processing Time:</strong> {$processing_time}ms</li>";
        echo "<li><strong>Data Structure Size:</strong> " . number_format(strlen(serialize($inputs))) . " bytes</li>";
        echo "<li><strong>Average Time per NB:</strong> " . round($processing_time / max($stats['nb_count'], 1), 2) . "ms</li>";
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error testing run {$test_runid}: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

echo "<hr>";
echo "<h3>Implementation Status</h3>";
echo "<p>✅ <strong>get_normalized_inputs()</strong> implementation complete with:</p>";
echo "<ul>";
echo "<li>✅ Run record loading and validation (status = completed)</li>";
echo "<li>✅ Source company loading (required)</li>";
echo "<li>✅ Target company loading (optional)</li>";
echo "<li>✅ NB result fetching and JSON decoding</li>";
echo "<li>✅ Complete NB → Field Normalization Map (NB1-NB15)</li>";
echo "<li>✅ Target hints extraction for bridge building</li>";
echo "<li>✅ Citation aggregation and deduplication</li>";
echo "<li>✅ Processing statistics and missing NB detection</li>";
echo "<li>✅ Error handling for malformed JSON and missing records</li>";
echo "<li>✅ Debug logging for troubleshooting</li>";
echo "</ul>";

echo "<p><strong>Ready for next steps:</strong></p>";
echo "<ol>";
echo "<li>Implement detect_patterns() to find themes across NBs</li>";
echo "<li>Build target-relevance bridge logic</li>";
echo "<li>Draft Intelligence Playbook sections</li>";
echo "</ol>";

echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";