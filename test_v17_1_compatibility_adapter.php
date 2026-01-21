<?php
/**
 * v17.1 Unified Artifact Compatibility Test
 * 
 * Simulates Run 25 to validate the compatibility adapter system
 * Tests artifact aliasing, schema normalization, and Evidence Diversity Context
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Load Moodle config
require_once(__DIR__ . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();
require_capability('local/customerintel:manage', context_system::instance());

// Load services
require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_compatibility_adapter.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');

// Set up test environment
$runid = 25; // Simulated Run 25
$test_start_time = microtime(true);

echo "<h1>v17.1 Unified Artifact Compatibility Test - Run {$runid}</h1>\n";
echo "<p>Testing compatibility adapter system with simulated Evidence Diversity Context</p>\n";

// Initialize services
$adapter = new \local_customerintel\services\artifact_compatibility_adapter();
$synthesis_engine = new \local_customerintel\services\synthesis_engine();

echo "<h2>1. Compatibility Adapter Info</h2>\n";
$compat_info = \local_customerintel\services\artifact_compatibility_adapter::get_compatibility_info();
echo "<pre>\n";
echo "Version: " . $compat_info['version'] . "\n";
echo "Description: " . $compat_info['description'] . "\n";
echo "Artifact Aliases:\n";
foreach ($compat_info['artifact_aliases'] as $physical => $logical) {
    echo "  {$physical} → {$logical}\n";
}
echo "Schema Transformations: " . implode(', ', $compat_info['schema_transformations']) . "\n";
echo "</pre>\n";

// Test 1: Create test synthesis inputs with Evidence Diversity Context
echo "<h2>2. Creating Test Synthesis Inputs (Simulated Run {$runid})</h2>\n";

// Create simulated normalized inputs with Evidence Diversity Context
$test_synthesis_inputs = [
    'normalized_citations' => [
        [
            'url' => 'https://www.gartner.com/en/insights/AI-automation-trends-2024',
            'domain' => 'gartner.com',
            'title' => 'AI Automation Trends 2024: Enterprise Implementation',
            'type' => 'research',
            'confidence' => 0.95,
            'diversity_marker' => 'industry_analysis'
        ],
        [
            'url' => 'https://techcrunch.com/2024/08/15/ai-workforce-transformation',
            'domain' => 'techcrunch.com', 
            'title' => 'AI Workforce Transformation Accelerates in 2024',
            'type' => 'news',
            'confidence' => 0.87,
            'diversity_marker' => 'technology_news'
        ],
        [
            'url' => 'https://www.mckinsey.com/capabilities/digital/our-insights/economic-impact-ai',
            'domain' => 'mckinsey.com',
            'title' => 'The Economic Impact of AI: New Analysis',
            'type' => 'consulting',
            'confidence' => 0.93,
            'diversity_marker' => 'economic_analysis'
        ],
        [
            'url' => 'https://arxiv.org/abs/2408.12345',
            'domain' => 'arxiv.org',
            'title' => 'Large Language Models in Enterprise: A Systematic Review',
            'type' => 'academic',
            'confidence' => 0.91,
            'diversity_marker' => 'academic_research'
        ]
    ],
    'company_source' => (object)[
        'id' => 1,
        'name' => 'Test Corp Inc',
        'ticker' => 'TCORP',
        'sector' => 'Technology',
        'website' => 'https://testcorp.com'
    ],
    'company_target' => (object)[
        'id' => 2,
        'name' => 'Target Solutions Ltd',
        'ticker' => 'TSOL',
        'sector' => 'Professional Services',
        'website' => 'https://targetsolutions.com'
    ],
    'nb' => [
        'NB1' => ['status' => 'completed', 'data' => 'Technology trends analysis'],
        'NB2' => ['status' => 'completed', 'data' => 'Market positioning research'],
        'NB3' => ['status' => 'completed', 'data' => 'Competitive landscape'],
        'NB4' => ['status' => 'completed', 'data' => 'Innovation pipeline'],
        'NB5' => ['status' => 'completed', 'data' => 'Partnership opportunities']
    ],
    'processing_stats' => [
        'nb_count' => 5,
        'citation_count' => 4,
        'diversity_context' => [
            'evidence_sources' => ['industry_analysis', 'technology_news', 'economic_analysis', 'academic_research'],
            'domain_distribution' => [
                'gartner.com' => 1,
                'techcrunch.com' => 1,
                'mckinsey.com' => 1,
                'arxiv.org' => 1
            ],
            'diversity_score' => 1.0, // Perfect diversity
            'evidence_coverage' => 'comprehensive'
        ]
    ]
];

// Test 2: Save artifact via adapter (this tests aliasing)
echo "<h3>2.1 Testing Artifact Save (normalized_inputs_v16 → synthesis_inputs)</h3>\n";
$save_result = $adapter->save_artifact($runid, 'citation_normalization', 'synthesis_inputs', $test_synthesis_inputs);
echo "<p>Save result: " . ($save_result ? "✅ SUCCESS" : "❌ FAILED") . "</p>\n";

// Test 3: Load artifact via adapter (this tests loading and schema normalization)
echo "<h3>2.2 Testing Artifact Load (synthesis_inputs)</h3>\n";
$loaded_inputs = $adapter->load_artifact($runid, 'synthesis_inputs');
if ($loaded_inputs) {
    echo "<p>✅ Successfully loaded synthesis_inputs via adapter</p>\n";
    echo "<p>Citations found: " . count($loaded_inputs['normalized_citations']) . "</p>\n";
    if (isset($loaded_inputs['domain_analysis'])) {
        echo "<p>✅ Domain analysis injected by adapter</p>\n";
        echo "<p>Domain diversity ratio: " . $loaded_inputs['domain_analysis']['diversity_ratio'] . "</p>\n";
    }
    if (isset($loaded_inputs['processing_stats']['diversity_context'])) {
        echo "<p>✅ Evidence Diversity Context preserved</p>\n";
        echo "<p>Evidence sources: " . implode(', ', $loaded_inputs['processing_stats']['diversity_context']['evidence_sources']) . "</p>\n";
        echo "<p>Diversity score: " . $loaded_inputs['processing_stats']['diversity_context']['diversity_score'] . "</p>\n";
    }
} else {
    echo "<p>❌ Failed to load synthesis_inputs via adapter</p>\n";
}

// Test 4: Create test synthesis bundle with v15_structure
echo "<h2>3. Testing Synthesis Bundle with v15_structure</h2>\n";

$test_synthesis_bundle = [
    'html' => '<div class="playbook"><h1>Test Intelligence Playbook</h1><p>Evidence Diversity Context: Comprehensive analysis across industry, technology, economic, and academic sources.</p></div>',
    'json' => json_encode([
        'sections' => [
            'executive_summary' => 'Test executive summary with diverse evidence',
            'market_analysis' => 'Market analysis leveraging multiple source types',
            'evidence_diversity' => [
                'source_variety' => 'high',
                'domain_coverage' => 'comprehensive',
                'perspective_balance' => 'strong'
            ]
        ],
        'qa' => [
            'scores' => [
                'relevance_density' => 0.87,
                'pov_strength' => 0.82,
                'evidence_health' => 0.94,
                'precision' => 0.89,
                'target_awareness' => 0.85,
                'coherence' => 0.91,
                'pattern_alignment' => 0.88
            ],
            'warnings' => []
        ],
        'evidence_diversity_metrics' => [
            'total_sources' => 4,
            'unique_domains' => 4,
            'source_type_distribution' => [
                'research' => 1,
                'news' => 1,
                'consulting' => 1,
                'academic' => 1
            ],
            'diversity_score' => 1.0
        ]
    ]),
    'voice_report' => json_encode(['tone' => 'professional', 'clarity' => 'high']),
    'selfcheck_report' => json_encode(['pass' => true, 'violations' => []]),
    'coherence_report' => json_encode(['score' => 0.91, 'details' => 'Strong coherence across sections']),
    'pattern_alignment_report' => json_encode(['score' => 0.88, 'diagnostics' => 'Good pattern alignment']),
    'citations' => $test_synthesis_inputs['normalized_citations'],
    'sources' => $test_synthesis_inputs['normalized_citations'],
    'qa_report' => json_encode(['overall_score' => 0.87, 'status' => 'passed']),
    'appendix_notes' => 'Evidence Diversity Context: This report demonstrates comprehensive source diversity across industry analysis, technology news, economic research, and academic literature.'
];

// Test 5: Save synthesis bundle via adapter
echo "<h3>3.1 Testing Synthesis Bundle Save</h3>\n";
$bundle_save_result = $adapter->save_synthesis_bundle($runid, $test_synthesis_bundle);
echo "<p>Bundle save result: " . ($bundle_save_result ? "✅ SUCCESS" : "❌ FAILED") . "</p>\n";

// Test 6: Load synthesis bundle via adapter (this tests v15_structure injection)
echo "<h3>3.2 Testing Synthesis Bundle Load</h3>\n";
$loaded_bundle = $adapter->load_synthesis_bundle($runid);
if ($loaded_bundle) {
    echo "<p>✅ Successfully loaded synthesis bundle via adapter</p>\n";
    echo "<p>Fields in bundle: " . count($loaded_bundle) . "</p>\n";
    echo "<ul>\n";
    foreach (array_keys($loaded_bundle) as $field) {
        echo "<li>{$field}</li>\n";
    }
    echo "</ul>\n";
    
    // Check for v15_structure specifically
    if (isset($loaded_bundle['v15_structure'])) {
        echo "<p>✅ v15_structure field present</p>\n";
        if (isset($loaded_bundle['v15_structure']['qa']['scores'])) {
            echo "<p>✅ QA scores available in v15_structure</p>\n";
            $scores = $loaded_bundle['v15_structure']['qa']['scores'];
            echo "<p>Evidence health score: " . ($scores['evidence_health'] ?? 'N/A') . "</p>\n";
        }
        if (isset($loaded_bundle['v15_structure']['evidence_diversity_metrics'])) {
            echo "<p>✅ Evidence Diversity Context preserved in v15_structure</p>\n";
            $diversity = $loaded_bundle['v15_structure']['evidence_diversity_metrics'];
            echo "<p>Diversity score: " . ($diversity['diversity_score'] ?? 'N/A') . "</p>\n";
        }
    } else {
        echo "<p>❌ v15_structure field missing</p>\n";
    }
    
    // Check for complete field coverage
    $expected_fields = ['html', 'json', 'voice_report', 'selfcheck_report', 'citations', 'sources', 
                       'coherence_report', 'pattern_alignment_report', 'appendix_notes', 'v15_structure'];
    $missing_fields = array_diff($expected_fields, array_keys($loaded_bundle));
    if (empty($missing_fields)) {
        echo "<p>✅ All expected fields present</p>\n";
    } else {
        echo "<p>⚠️  Missing fields: " . implode(', ', $missing_fields) . "</p>\n";
    }
} else {
    echo "<p>❌ Failed to load synthesis bundle via adapter</p>\n";
}

// Test 7: Test viewer compatibility
echo "<h2>4. Testing Viewer Compatibility (Simulated view_report.php)</h2>\n";

echo "<h3>4.1 Testing Evidence Diversity Context in Viewer</h3>\n";
if ($loaded_bundle && isset($loaded_bundle['v15_structure']['evidence_diversity_metrics'])) {
    $diversity_metrics = $loaded_bundle['v15_structure']['evidence_diversity_metrics'];
    
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; margin: 10px 0;'>\n";
    echo "<h4>Evidence Diversity Dashboard</h4>\n";
    echo "<p><strong>Total Sources:</strong> " . $diversity_metrics['total_sources'] . "</p>\n";
    echo "<p><strong>Unique Domains:</strong> " . $diversity_metrics['unique_domains'] . "</p>\n";
    echo "<p><strong>Diversity Score:</strong> " . $diversity_metrics['diversity_score'] . "</p>\n";
    echo "<p><strong>Source Distribution:</strong></p>\n";
    echo "<ul>\n";
    foreach ($diversity_metrics['source_type_distribution'] as $type => $count) {
        echo "<li>" . ucfirst($type) . ": {$count}</li>\n";
    }
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<p>✅ Evidence Diversity Context successfully rendered in viewer format</p>\n";
} else {
    echo "<p>❌ Evidence Diversity Context not available for viewer</p>\n";
}

// Test 8: Check HTML content for Evidence Diversity
echo "<h3>4.2 Testing HTML Content for Evidence Diversity References</h3>\n";
if ($loaded_bundle && isset($loaded_bundle['html'])) {
    $html_content = $loaded_bundle['html'];
    if (strpos($html_content, 'Evidence Diversity') !== false) {
        echo "<p>✅ Evidence Diversity Context found in HTML content</p>\n";
    } else {
        echo "<p>⚠️  Evidence Diversity Context not explicitly mentioned in HTML</p>\n";
    }
} else {
    echo "<p>❌ HTML content not available</p>\n";
}

// Test 9: Performance and logging summary
echo "<h2>5. Performance and Logging Summary</h2>\n";

$test_duration = (microtime(true) - $test_start_time) * 1000;
echo "<p><strong>Total test duration:</strong> " . round($test_duration, 2) . "ms</p>\n";

// Check logs for compatibility messages
echo "<h3>5.1 Compatibility Log Messages</h3>\n";
echo "<p>Check the application logs for messages starting with '[Compatibility]'</p>\n";
echo "<p>Expected log entries:</p>\n";
echo "<ul>\n";
echo "<li>[Compatibility] Loading artifact: synthesis_inputs → normalized_inputs_v16</li>\n";
echo "<li>[Compatibility] Artifact loaded and normalized: synthesis_inputs</li>\n";
echo "<li>[Compatibility] Synthesis bundle cached with v17.1 compatibility structure</li>\n";
echo "<li>[Compatibility] Built complete synthesis bundle with v15_structure field</li>\n";
echo "</ul>\n";

// Final validation
echo "<h2>6. Final Validation</h2>\n";

$validation_results = [
    'Compatibility adapter created' => class_exists('\local_customerintel\services\artifact_compatibility_adapter'),
    'Artifact aliasing works' => $save_result && $loaded_inputs,
    'Schema normalization works' => $loaded_inputs && isset($loaded_inputs['domain_analysis']),
    'Synthesis bundle caching works' => $bundle_save_result && $loaded_bundle,
    'v15_structure injection works' => $loaded_bundle && isset($loaded_bundle['v15_structure']),
    'Evidence Diversity Context preserved' => $loaded_bundle && isset($loaded_bundle['v15_structure']['evidence_diversity_metrics']),
    'Viewer compatibility maintained' => $loaded_bundle && count($loaded_bundle) >= 10
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Validation Check</th><th>Status</th></tr>\n";
foreach ($validation_results as $check => $passed) {
    $status = $passed ? "✅ PASS" : "❌ FAIL";
    echo "<tr><td>{$check}</td><td>{$status}</td></tr>\n";
}
echo "</table>\n";

$total_passed = array_sum($validation_results);
$total_checks = count($validation_results);

if ($total_passed === $total_checks) {
    echo "<h3 style='color: green;'>🎉 All validation checks PASSED!</h3>\n";
    echo "<p><strong>v17.1 Unified Artifact Compatibility system is ready for production</strong></p>\n";
} else {
    echo "<h3 style='color: red;'>⚠️  {$total_passed}/{$total_checks} validation checks passed</h3>\n";
    echo "<p>Please review and fix failed checks before proceeding</p>\n";
}

echo "<hr>\n";
echo "<p><em>Test completed at: " . date('Y-m-d H:i:s') . "</em></p>\n";
echo "<p><em>Simulated Run ID: {$runid} | Test Duration: " . round($test_duration, 2) . "ms</em></p>\n";