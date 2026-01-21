<?php
/**
 * Test script for synthesis_engine->build_target_bridge() implementation
 */

require_once(__DIR__ . '/config.php');
require_login();
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');

use local_customerintel\services\synthesis_engine;

echo "<h2>Target-Relevance Bridge Test</h2>";

// Get completed runs with target companies
$sql = "SELECT r.*, 
               sc.name as source_name, sc.sector as source_sector,
               tc.name as target_name, tc.sector as target_sector
        FROM {local_ci_run} r
        JOIN {local_ci_company} sc ON r.companyid = sc.id
        LEFT JOIN {local_ci_company} tc ON r.targetcompanyid = tc.id
        WHERE r.status = 'completed'
        ORDER BY r.id DESC
        LIMIT 10";

$runs = $DB->get_records_sql($sql);

if (empty($runs)) {
    echo "<p style='color: red;'>No completed runs found. Please run at least one intelligence analysis first.</p>";
    echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";
    exit;
}

echo "<h3>Available Completed Runs:</h3>";
echo "<table class='table table-striped'>";
echo "<thead><tr><th>Run ID</th><th>Source Company</th><th>Target Company</th><th>Mode</th><th>Actions</th></tr></thead>";
echo "<tbody>";

foreach ($runs as $run) {
    echo "<tr>";
    echo "<td><strong>{$run->id}</strong></td>";
    echo "<td>{$run->source_name}";
    if ($run->source_sector) echo " <em>({$run->source_sector})</em>";
    echo "</td>";
    echo "<td>";
    if ($run->target_name) {
        echo $run->target_name;
        if ($run->target_sector) echo " <em>({$run->target_sector})</em>";
    } else {
        echo "<em>Single-company analysis</em>";
    }
    echo "</td>";
    echo "<td>" . ucfirst($run->mode) . "</td>";
    echo "<td><a href='?test_runid={$run->id}' class='btn btn-sm btn-primary'>Test Bridge</a></td>";
    echo "</tr>";
}

echo "</tbody></table>";

// Test specific run if requested
$test_runid = optional_param('test_runid', 0, PARAM_INT);

if ($test_runid) {
    echo "<hr>";
    echo "<h3>Testing Target Bridge for Run ID: {$test_runid}</h3>";
    
    try {
        $engine = new synthesis_engine();
        $start_time = microtime(true);
        
        // Step 1: Get normalized inputs
        echo "<h4>Step 1: Getting Normalized Inputs</h4>";
        $inputs = $engine->get_normalized_inputs($test_runid);
        echo "<p style='color: green;'>✓ Loaded {$inputs['processing_stats']['nb_count']} NBs</p>";
        
        // Step 2: Detect patterns
        echo "<h4>Step 2: Detecting Patterns</h4>";
        $patterns = $engine->detect_patterns($inputs);
        echo "<p style='color: green;'>✓ Detected " . count($patterns['pressures']) . " pressure themes, " . 
             count($patterns['levers']) . " capability levers</p>";
        
        // Step 3: Build target bridge
        echo "<h4>Step 3: Building Target-Relevance Bridge</h4>";
        
        // Prepare source patterns for bridge analysis
        $source_patterns = $patterns;
        $source_patterns['nb'] = $inputs['nb']; // Add NB data for context
        
        $bridge = $engine->build_target_bridge($source_patterns, $inputs);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "<p style='color: green;'>✓ Bridge analysis completed in {$processing_time}ms</p>";
        
        // Display results
        echo "<h4>Target Bridge Results:</h4>";
        
        // Bridge rationale
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h5>🧠 Bridge Analysis Rationale</h5>";
        if (!empty($bridge['rationale'])) {
            echo "<div style='background: #e9ecef; padding: 10px; border-radius: 5px;'>";
            foreach ($bridge['rationale'] as $i => $reason) {
                echo "<p><strong>" . ($i + 1) . ".</strong> " . htmlspecialchars($reason) . "</p>";
            }
            echo "</div>";
        }
        echo "</div>";
        
        // Bridge items
        echo "<div style='margin-bottom: 20px;'>";
        echo "<h5>🌉 Top Bridge Items (by Relevance Score)</h5>";
        
        if (!empty($bridge['items'])) {
            echo "<p><strong>Found " . count($bridge['items']) . " relevant bridge items:</strong></p>";
            
            foreach ($bridge['items'] as $i => $item) {
                $color = $item['type'] === 'pressure' ? '#fff3cd' : '#d4edda';
                $icon = $item['type'] === 'pressure' ? '🔥' : '⚙️';
                
                echo "<div style='background: {$color}; margin-bottom: 15px; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff;'>";
                echo "<h6>{$icon} " . ucfirst($item['type']) . " Theme (Score: {$item['relevance_score']})</h6>";
                echo "<p><strong>Theme:</strong> " . htmlspecialchars($item['theme']) . "</p>";
                echo "<p><strong>Source:</strong> {$item['source_nb']} → {$item['source_field']}</p>";
                
                echo "<div style='margin: 10px 0;'>";
                echo "<p><strong>Why it matters to target:</strong><br>";
                echo "<em>" . htmlspecialchars($item['why_it_matters_to_target']) . "</em></p>";
                echo "</div>";
                
                if ($item['timing_sync'] !== "No specific timing alignment detected") {
                    echo "<div style='margin: 10px 0;'>";
                    echo "<p><strong>Timing synchrony:</strong><br>";
                    echo "<em>" . htmlspecialchars($item['timing_sync']) . "</em></p>";
                    echo "</div>";
                }
                
                echo "<div style='margin: 10px 0;'>";
                echo "<p><strong>Local consequence if ignored:</strong><br>";
                echo "<em>" . htmlspecialchars($item['local_consequence_if_ignored']) . "</em></p>";
                echo "</div>";
                
                if (!empty($item['supporting_evidence'])) {
                    echo "<div style='margin: 10px 0;'>";
                    echo "<p><strong>Supporting evidence:</strong></p>";
                    echo "<ul>";
                    foreach ($item['supporting_evidence'] as $evidence) {
                        echo "<li><em>" . htmlspecialchars($evidence) . "</em></li>";
                    }
                    echo "</ul>";
                    echo "</div>";
                }
                
                echo "</div>";
            }
        } else {
            if ($inputs['company_target']) {
                echo "<p><em>No relevant bridge items found between source and target patterns.</em></p>";
                echo "<p>This could indicate:</p>";
                echo "<ul>";
                echo "<li>Different sectors with limited overlap</li>";
                echo "<li>Different regulatory environments</li>";
                echo "<li>Non-overlapping stakeholder ecosystems</li>";
                echo "<li>Misaligned timing windows</li>";
                echo "</ul>";
            } else {
                echo "<p><em>Single-company analysis: no target bridge required.</em></p>";
            }
        }
        echo "</div>";
        
        // Detailed scoring breakdown
        if (!empty($bridge['items'])) {
            echo "<h5>📊 Detailed Scoring Breakdown</h5>";
            echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
            echo "<p><strong>Scoring System:</strong></p>";
            echo "<ul>";
            echo "<li><strong>Sector Overlap:</strong> 2 points per matching keyword (max 8)</li>";
            echo "<li><strong>Regulatory Overlap:</strong> 3 points per shared regulatory framework</li>";
            echo "<li><strong>Ecosystem Overlap:</strong> 4 points per shared stakeholder/partner</li>";
            echo "<li><strong>Timing Overlap:</strong> 3 points per aligned timing signal</li>";
            echo "</ul>";
            
            echo "<table class='table table-sm'>";
            echo "<thead><tr><th>Theme</th><th>Type</th><th>Sector</th><th>Regulatory</th><th>Ecosystem</th><th>Timing</th><th>Total</th></tr></thead>";
            echo "<tbody>";
            
            // For display purposes, we'll show a summary of the scoring
            foreach ($bridge['items'] as $item) {
                echo "<tr>";
                echo "<td>" . substr(htmlspecialchars($item['theme']), 0, 30) . "...</td>";
                echo "<td>" . ucfirst($item['type']) . "</td>";
                echo "<td>?</td>"; // Would need to expose scoring details
                echo "<td>?</td>";
                echo "<td>?</td>";
                echo "<td>?</td>";
                echo "<td><strong>{$item['relevance_score']}</strong></td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            
            echo "<p><em>Note: Individual component scores not exposed in current bridge item structure.</em></p>";
            echo "</div>";
        }
        
        // Analysis Summary
        echo "<h4>Analysis Summary:</h4>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
        echo "<ul>";
        echo "<li><strong>Processing Time:</strong> {$processing_time}ms</li>";
        echo "<li><strong>Source Patterns:</strong> " . count($patterns['pressures']) . " pressures + " . count($patterns['levers']) . " levers</li>";
        echo "<li><strong>Bridge Items Generated:</strong> " . count($bridge['items']) . "</li>";
        
        if ($inputs['company_target']) {
            echo "<li><strong>Target Company:</strong> {$inputs['company_target']->name}";
            if ($inputs['target_hints']['sector']) {
                echo " ({$inputs['target_hints']['sector']})";
            }
            echo "</li>";
            
            echo "<li><strong>Cross-Company Analysis:</strong> ✓ Enabled</li>";
            echo "<li><strong>Relevance Factors:</strong> ";
            $factors = [];
            if (!empty($inputs['target_hints']['sector'])) $factors[] = "Sector";
            if (!empty($inputs['target_hints']['name'])) $factors[] = "Regulatory";
            $factors[] = "Ecosystem";
            $factors[] = "Timing";
            echo implode(", ", $factors) . "</li>";
        } else {
            echo "<li><strong>Target Company:</strong> None (single-company analysis)</li>";
            echo "<li><strong>Cross-Company Analysis:</strong> ✗ Disabled</li>";
        }
        echo "</ul>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error testing target bridge for run {$test_runid}: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

echo "<hr>";
echo "<h3>Implementation Status</h3>";
echo "<p>✅ <strong>build_target_bridge()</strong> implementation complete with:</p>";
echo "<ul>";
echo "<li>✅ <strong>Relevance Scoring:</strong> Multi-factor scoring (sector + regulatory + ecosystem + timing)</li>";
echo "<li>✅ <strong>Sector Overlap:</strong> Keyword matching with industry-specific expansion</li>";
echo "<li>✅ <strong>Regulatory Overlap:</strong> Common frameworks (FDA, SEC, GDPR, etc.)</li>";
echo "<li>✅ <strong>Ecosystem Links:</strong> Stakeholder/partner intersection analysis</li>";
echo "<li>✅ <strong>Timing Synchrony:</strong> Direct matching + sector-based heuristics</li>";
echo "<li>✅ <strong>Target Profile:</strong> Comprehensive profile extraction from hints + NB data</li>";
echo "<li>✅ <strong>Bridge Items:</strong> Complete bridge item generation with consequences</li>";
echo "<li>✅ <strong>Top-5 Selection:</strong> Ranked by relevance score</li>";
echo "<li>✅ <strong>Rationale Generation:</strong> Detailed logic explanation</li>";
echo "<li>✅ <strong>Single-Company Handling:</strong> Graceful fallback for no-target scenarios</li>";
echo "</ul>";

echo "<p><strong>Relevance Scoring Components:</strong></p>";
echo "<ul>";
echo "<li>🏢 <strong>Sector Overlap:</strong> 2 pts/keyword (max 8) - Healthcare, Financial, Technology, etc.</li>";
echo "<li>📋 <strong>Regulatory Overlap:</strong> 3 pts/framework - FDA, SEC, GDPR, HIPAA, etc.</li>";
echo "<li>🌐 <strong>Ecosystem Links:</strong> 4 pts/overlap - Shared stakeholders, partners</li>";
echo "<li>⏰ <strong>Timing Synchrony:</strong> 3 pts/overlap - Budget cycles, academic calendars, clinical timelines</li>";
echo "</ul>";

echo "<p><strong>Heuristic Timing Overlaps:</strong></p>";
echo "<ul>";
echo "<li>📅 <strong>Budget Cycles:</strong> Government, education, healthcare sectors</li>";
echo "<li>🎓 <strong>Academic Calendars:</strong> Education, research institutions</li>";
echo "<li>🏥 <strong>Clinical Timelines:</strong> Healthcare, pharmaceutical companies</li>";
echo "<li>📊 <strong>Quarter Alignment:</strong> Direct Q1-Q4 matching across companies</li>";
echo "</ul>";

echo "<p><strong>Ready for next steps:</strong></p>";
echo "<ol>";
echo "<li>Implement draft_sections() to generate Intelligence Playbook content</li>";
echo "<li>Apply voice enforcement with Operator Voice rules</li>";
echo "<li>Run self-check validation on generated content</li>";
echo "<li>Enrich citations and persist final synthesis</li>";
echo "</ol>";

echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";