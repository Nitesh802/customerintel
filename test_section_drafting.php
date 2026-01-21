<?php
/**
 * Test script for synthesis_engine->draft_sections() implementation
 */

require_once(__DIR__ . '/config.php');
require_login();
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');

use local_customerintel\services\synthesis_engine;

echo "<h2>Intelligence Playbook Section Drafting Test</h2>";

// Get completed runs
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
echo "<thead><tr><th>Run ID</th><th>Source Company</th><th>Target Company</th><th>Actions</th></tr></thead>";
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
    echo "<td><a href='?test_runid={$run->id}' class='btn btn-sm btn-primary'>Test Drafting</a></td>";
    echo "</tr>";
}

echo "</tbody></table>";

// Test specific run if requested
$test_runid = optional_param('test_runid', 0, PARAM_INT);

if ($test_runid) {
    echo "<hr>";
    echo "<h3>Testing Section Drafting for Run ID: {$test_runid}</h3>";
    
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
        echo "<p style='color: green;'>✓ Detected " . count($patterns['pressures']) . " pressures, " . 
             count($patterns['levers']) . " levers, " . count($patterns['timing']) . " timing signals</p>";
        
        // Step 3: Build target bridge
        echo "<h4>Step 3: Building Target Bridge</h4>";
        $source_patterns = $patterns;
        $source_patterns['nb'] = $inputs['nb'];
        $bridge = $engine->build_target_bridge($source_patterns, $inputs);
        echo "<p style='color: green;'>✓ Generated " . count($bridge['items']) . " bridge items</p>";
        
        // Step 4: Draft sections
        echo "<h4>Step 4: Drafting Intelligence Playbook Sections</h4>";
        $sections = $engine->draft_sections($patterns, $bridge);
        
        $end_time = microtime(true);
        $processing_time = round(($end_time - $start_time) * 1000, 2);
        
        echo "<p style='color: green;'>✓ Section drafting completed in {$processing_time}ms</p>";
        
        // Display results
        echo "<h4>Intelligence Playbook Sections:</h4>";
        
        // Executive Summary
        echo "<div style='margin-bottom: 25px;'>";
        echo "<h5 style='color: #d63384;'>📋 Executive Summary</h5>";
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #d63384;'>";
        echo "<p><strong>Word Count:</strong> " . $sections['word_counts']['executive_summary'] . "/140</p>";
        echo "<div style='font-size: 14px; line-height: 1.6;'>";
        echo htmlspecialchars($sections['executive_summary']);
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        // What's Often Overlooked
        echo "<div style='margin-bottom: 25px;'>";
        echo "<h5 style='color: #fd7e14;'>🔍 What's Often Overlooked</h5>";
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #fd7e14;'>";
        echo "<p><strong>Insights Count:</strong> " . count($sections['overlooked']) . " bullets</p>";
        echo "<ul>";
        foreach ($sections['overlooked'] as $i => $insight) {
            echo "<li style='margin-bottom: 8px; font-size: 14px;'>" . htmlspecialchars($insight) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
        echo "</div>";
        
        // Opportunity Blueprints
        echo "<div style='margin-bottom: 25px;'>";
        echo "<h5 style='color: #20c997;'>⚡ Opportunity Blueprints</h5>";
        echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 8px; border-left: 4px solid #20c997;'>";
        echo "<p><strong>Blueprints Count:</strong> " . count($sections['opportunities']) . "</p>";
        
        foreach ($sections['opportunities'] as $i => $blueprint) {
            $word_count = $sections['word_counts']['opportunities'][$i] ?? str_word_count($blueprint['body']);
            echo "<div style='background: white; margin: 10px 0; padding: 12px; border-radius: 5px; border: 1px solid #bee5eb;'>";
            echo "<h6 style='color: #20c997; margin-bottom: 8px;'>" . htmlspecialchars($blueprint['title']) . "</h6>";
            echo "<p style='font-size: 12px; color: #6c757d; margin-bottom: 8px;'><strong>Word Count:</strong> {$word_count}/120</p>";
            echo "<div style='font-size: 14px; line-height: 1.5;'>" . htmlspecialchars($blueprint['body']) . "</div>";
            echo "</div>";
        }
        echo "</div>";
        echo "</div>";
        
        // Convergence Insight
        echo "<div style='margin-bottom: 25px;'>";
        echo "<h5 style='color: #6f42c1;'>🌀 Convergence Insight</h5>";
        echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;'>";
        echo "<p><strong>Word Count:</strong> " . $sections['word_counts']['convergence'] . "/140</p>";
        echo "<div style='font-size: 14px; line-height: 1.6;'>";
        echo htmlspecialchars($sections['convergence']);
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        // Citations Used
        echo "<div style='margin-bottom: 25px;'>";
        echo "<h5 style='color: #6c757d;'>📚 Citations Used</h5>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #6c757d;'>";
        echo "<p><strong>Citation Count:</strong> " . count($sections['citations_used']) . "</p>";
        
        if (!empty($sections['citations_used'])) {
            echo "<ol>";
            foreach ($sections['citations_used'] as $i => $citation) {
                echo "<li style='margin-bottom: 8px; font-size: 14px;'>";
                echo "<strong>Source:</strong> {$citation['source']} | ";
                echo "<strong>Field:</strong> {$citation['field']} | ";
                echo "<strong>Value:</strong> {$citation['value']}<br>";
                if (!empty($citation['context'])) {
                    echo "<em>Context:</em> " . htmlspecialchars(substr($citation['context'], 0, 100)) . "...";
                }
                echo "</li>";
            }
            echo "</ol>";
        } else {
            echo "<p><em>No citations referenced in this analysis.</em></p>";
        }
        echo "</div>";
        echo "</div>";
        
        // Composition Rules Validation
        echo "<h5>✅ Composition Rules Validation</h5>";
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px;'>";
        
        // Executive Summary validation
        $exec_summary = $sections['executive_summary'];
        $has_number = preg_match('/\d+[%$£€¥]?|\b(Q[1-4]|January|February|March|April|May|June|July|August|September|October|November|December|\d{4})\b/i', $exec_summary);
        $has_exec = preg_match('/\b(CEO|CFO|CTO|president|director|manager|executive|leadership)\b/i', $exec_summary);
        $has_why_now = strpos(strtolower($exec_summary), 'timing') !== false || strpos(strtolower($exec_summary), 'now') !== false || strpos(strtolower($exec_summary), 'window') !== false;
        $has_target = empty($bridge['items']) || preg_match('/\b\w+Corp|\w+Inc|\w+Ltd|target|company\b/i', $exec_summary);
        
        echo "<h6>Executive Summary (≤140 words):</h6>";
        echo "<ul>";
        echo "<li>" . ($sections['word_counts']['executive_summary'] <= 140 ? "✅" : "❌") . " Word limit: {$sections['word_counts']['executive_summary']}/140</li>";
        echo "<li>" . ($has_number ? "✅" : "❌") . " Contains number/date</li>";
        echo "<li>" . ($has_exec ? "✅" : "❌") . " Names accountable executive</li>";
        echo "<li>" . ($has_why_now ? "✅" : "❌") . " Includes 'why now' reasoning</li>";
        echo "<li>" . ($has_target ? "✅" : "❌") . " Mentions target (if applicable)</li>";
        echo "</ul>";
        
        // What's Often Overlooked validation
        echo "<h6>What's Often Overlooked:</h6>";
        echo "<ul>";
        echo "<li>" . (count($sections['overlooked']) >= 3 && count($sections['overlooked']) <= 5 ? "✅" : "❌") . " Bullet count: " . count($sections['overlooked']) . " (3-5 required)</li>";
        
        $has_contrast = false;
        foreach ($sections['overlooked'] as $insight) {
            if (strpos($insight, 'see') !== false && strpos($insight, 'actually') !== false) {
                $has_contrast = true;
                break;
            }
        }
        echo "<li>" . ($has_contrast ? "✅" : "❌") . " Contains contrast structure ('see X, but actually Y')</li>";
        echo "</ul>";
        
        // Opportunity Blueprints validation
        echo "<h6>Opportunity Blueprints:</h6>";
        echo "<ul>";
        echo "<li>" . (count($sections['opportunities']) >= 2 && count($sections['opportunities']) <= 3 ? "✅" : "❌") . " Blueprint count: " . count($sections['opportunities']) . " (2-3 required)</li>";
        
        $all_blueprints_valid = true;
        foreach ($sections['opportunities'] as $i => $blueprint) {
            $word_count = $sections['word_counts']['opportunities'][$i] ?? str_word_count($blueprint['body']);
            $title_words = str_word_count($blueprint['title']);
            $has_citation = preg_match('/\[\d+\]/', $blueprint['body']);
            $has_numeric = preg_match('/\d+[%$£€¥]?/', $blueprint['body']);
            
            if ($word_count > 120 || $title_words < 3 || $title_words > 6 || !$has_citation || !$has_numeric) {
                $all_blueprints_valid = false;
            }
        }
        echo "<li>" . ($all_blueprints_valid ? "✅" : "❌") . " All blueprints meet requirements (≤120 words, 3-6 word titles, numeric proof, citation)</li>";
        echo "</ul>";
        
        // Convergence Insight validation
        echo "<h6>Convergence Insight (≤140 words):</h6>";
        echo "<ul>";
        echo "<li>" . ($sections['word_counts']['convergence'] <= 140 ? "✅" : "❌") . " Word limit: {$sections['word_counts']['convergence']}/140</li>";
        echo "<li>" . (strpos(strtolower($sections['convergence']), 'window') !== false && strpos(strtolower($sections['convergence']), 'close') !== false ? "✅" : "❌") . " Explains window closure trigger</li>";
        echo "</ul>";
        
        echo "</div>";
        
        // Analysis Summary
        echo "<h4>Analysis Summary:</h4>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
        echo "<ul>";
        echo "<li><strong>Processing Time:</strong> {$processing_time}ms</li>";
        echo "<li><strong>Input Patterns:</strong> " . count($patterns['pressures']) . " pressures + " . count($patterns['levers']) . " levers</li>";
        echo "<li><strong>Bridge Items:</strong> " . count($bridge['items']) . " relevance mappings</li>";
        echo "<li><strong>Total Citations:</strong> " . count($sections['citations_used']) . " referenced</li>";
        echo "<li><strong>Content Generation:</strong> 4 sections with " . 
             ($sections['word_counts']['executive_summary'] + $sections['word_counts']['convergence'] + array_sum($sections['word_counts']['opportunities'])) . " total words</li>";
        
        // Target analysis indicator
        if (!empty($bridge['items'])) {
            $target_name = preg_match('/This matters to ([^\\s]+)/', $bridge['items'][0]['why_it_matters_to_target'], $matches) ? $matches[1] : 'Target';
            echo "<li><strong>Target Analysis:</strong> ✓ Dual-company playbook for {$target_name}</li>";
        } else {
            echo "<li><strong>Target Analysis:</strong> ✗ Single-company analysis</li>";
        }
        echo "</ul>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error testing section drafting for run {$test_runid}: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

echo "<hr>";
echo "<h3>Implementation Status</h3>";
echo "<p>✅ <strong>draft_sections()</strong> implementation complete with:</p>";
echo "<ul>";
echo "<li>✅ <strong>Executive Summary:</strong> ≤140 words with number/date, accountable exec, 'why now', target mention</li>";
echo "<li>✅ <strong>What's Often Overlooked:</strong> 3-5 bullets with 'teams see X, but actually Y' contrast structure</li>";
echo "<li>✅ <strong>Opportunity Blueprints:</strong> 2-3 blueprints with 3-6 word titles, ≤120 words, Source→Target→Timing→Risk flow</li>";
echo "<li>✅ <strong>Convergence Insight:</strong> ≤140 words explaining window closure triggers</li>";
echo "<li>✅ <strong>Citation Tracking:</strong> Automatic [n] reference assignment and deduplication</li>";
echo "<li>✅ <strong>Word Limits:</strong> Enforced with sentence-boundary trimming</li>";
echo "<li>✅ <strong>Content Flow:</strong> Source capability → Target need → Timing cue → Risk consequence</li>";
echo "<li>✅ <strong>Target Awareness:</strong> Dual-company vs single-company content adaptation</li>";
echo "</ul>";

echo "<p><strong>Content Generation Features:</strong></p>";
echo "<ul>";
echo "<li>🎯 <strong>Required Elements:</strong> Numbers, dates, executives, timing, target mentions</li>";
echo "<li>📝 <strong>Contrast Structure:</strong> 'Teams see X, but what's actually driving it is Y'</li>";
echo "<li>🔗 <strong>Source→Target Flow:</strong> Capability mapping to target relevance</li>";
echo "<li>⏰ <strong>Timing Integration:</strong> Windows, deadlines, budget cycles, regulatory gates</li>";
echo "<li>📊 <strong>Numeric Proofs:</strong> Percentages, currency, headcounts with citations</li>";
echo "<li>🎭 <strong>Dynamic Titles:</strong> Context-aware blueprint naming (3-6 words)</li>";
echo "<li>📚 <strong>Citation Management:</strong> Automatic deduplication and reference tracking</li>";
echo "<li>✂️ <strong>Word Limiting:</strong> Intelligent trimming at sentence boundaries</li>";
echo "</ul>";

echo "<p><strong>Section Templates:</strong></p>";
echo "<ul>";
echo "<li>📋 <strong>Executive Summary:</strong> Pressure + Performance gap [citation] + Executive accountability + Why now + Target relevance + Window risk</li>";
echo "<li>🔍 <strong>Overlooked Insights:</strong> Surface vs. underlying drivers with operational/financial/competitive/regulatory patterns</li>";
echo "<li>⚡ <strong>Opportunity Blueprints:</strong> Title + Source capability + Target relevance + Timing advantage + Risk mitigation</li>";
echo "<li>🌀 <strong>Convergence Insight:</strong> Pressure convergence + Window closure trigger + Timeline consequence</li>";
echo "</ul>";

echo "<p><strong>Ready for next steps:</strong></p>";
echo "<ol>";
echo "<li>Implement apply_voice() for Operator Voice enforcement (casual asides, ellipses, ban consultant-speak)</li>";
echo "<li>Implement run_selfcheck() for quality validation (execution leakage, speculative claims)</li>";
echo "<li>Implement enrich_citations() for source metadata enhancement</li>";
echo "<li>Complete end-to-end synthesis pipeline integration</li>";
echo "</ol>";

echo "<p><a href='/local/customerintel/dashboard.php'>← Back to Dashboard</a></p>";