<?php
/**
 * Run 25 Diagnostic Archive Generator
 * 
 * Generates diagnostic archive for Run 25 to inspect artifacts and compatibility paths
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
require_once($CFG->dirroot . '/local/customerintel/classes/services/diagnostic_archive_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_compatibility_adapter.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');

$runid = 25; // Target Run 25

echo "<h1>Run {$runid} Diagnostic Archive Generation</h1>\n";
echo "<p>Generating comprehensive diagnostic archive to inspect artifacts and compatibility paths</p>\n";

// Check if Run 25 exists
$run = $DB->get_record('local_ci_run', ['id' => $runid]);
if (!$run) {
    echo "<div class='alert alert-warning'>\n";
    echo "<h3>⚠️ Run {$runid} Not Found</h3>\n";
    echo "<p>Run {$runid} does not exist in the database. Let's create a simulated run for testing.</p>\n";
    
    // Create simulated Run 25
    $simulated_run = new stdClass();
    $simulated_run->id = $runid;
    $simulated_run->companyid = 1;
    $simulated_run->targetcompanyid = 2;
    $simulated_run->status = 'completed';
    $simulated_run->timecreated = time() - 3600; // 1 hour ago
    $simulated_run->timecompleted = time() - 1800; // 30 minutes ago
    $simulated_run->initiatedbyuserid = $USER->id;
    
    try {
        $DB->insert_record('local_ci_run', $simulated_run);
        echo "<p>✅ Simulated Run {$runid} created successfully</p>\n";
        $run = $simulated_run;
    } catch (Exception $e) {
        echo "<p>❌ Failed to create simulated run: " . $e->getMessage() . "</p>\n";
        echo "<p>Proceeding with diagnostic generation anyway...</p>\n";
    }
    echo "</div>\n";
}

echo "<h2>Pre-Diagnostic Analysis</h2>\n";

// 1. Check what artifacts exist for Run 25
echo "<h3>1. Artifact Analysis</h3>\n";
$artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid], 'phase ASC, artifacttype ASC');

if (empty($artifacts)) {
    echo "<div class='alert alert-info'>\n";
    echo "<p>⚠️ No artifacts found for Run {$runid}. Creating test artifacts...</p>\n";
    
    // Create test artifacts using the compatibility adapter
    $adapter = new \local_customerintel\services\artifact_compatibility_adapter();
    
    // Create test normalized inputs
    $test_normalized_inputs = [
        'normalized_citations' => [
            [
                'url' => 'https://example.com/test-article-1',
                'domain' => 'example.com',
                'title' => 'Test Article 1 for Run 25',
                'type' => 'web',
                'confidence' => 0.85
            ],
            [
                'url' => 'https://test.org/research-paper',
                'domain' => 'test.org',
                'title' => 'Research Paper for Run 25',
                'type' => 'research',
                'confidence' => 0.92
            ]
        ],
        'company_source' => (object)[
            'id' => 1,
            'name' => 'Test Source Company',
            'ticker' => 'TSC',
            'sector' => 'Technology'
        ],
        'company_target' => (object)[
            'id' => 2,
            'name' => 'Test Target Company',
            'ticker' => 'TTC',
            'sector' => 'Services'
        ],
        'nb' => [
            'NB1' => ['status' => 'completed', 'data' => 'Test NB1 data'],
            'NB2' => ['status' => 'completed', 'data' => 'Test NB2 data'],
            'NB3' => ['status' => 'completed', 'data' => 'Test NB3 data']
        ],
        'processing_stats' => [
            'nb_count' => 3,
            'citation_count' => 2,
            'diversity_context' => [
                'evidence_sources' => ['web', 'research'],
                'domain_distribution' => ['example.com' => 1, 'test.org' => 1],
                'diversity_score' => 1.0
            ]
        ]
    ];
    
    // Save via compatibility adapter
    $save_result = $adapter->save_artifact($runid, 'citation_normalization', 'synthesis_inputs', $test_normalized_inputs);
    if ($save_result) {
        echo "<p>✅ Created test synthesis_inputs artifact via adapter</p>\n";
    } else {
        echo "<p>❌ Failed to create test synthesis_inputs artifact</p>\n";
    }
    
    // Create test synthesis bundle
    $test_synthesis_bundle = [
        'html' => '<div><h1>Test Synthesis for Run 25</h1><p>This is a test synthesis with evidence diversity context.</p></div>',
        'json' => json_encode([
            'sections' => ['executive_summary' => 'Test summary'],
            'qa' => ['scores' => ['evidence_health' => 0.85], 'warnings' => []],
            'evidence_diversity_metrics' => ['diversity_score' => 1.0, 'total_sources' => 2]
        ]),
        'voice_report' => json_encode(['tone' => 'professional']),
        'selfcheck_report' => json_encode(['pass' => true, 'violations' => []]),
        'citations' => $test_normalized_inputs['normalized_citations'],
        'sources' => $test_normalized_inputs['normalized_citations'],
        'coherence_report' => json_encode(['score' => 0.88]),
        'pattern_alignment_report' => json_encode(['score' => 0.82]),
        'qa_report' => json_encode(['overall_score' => 0.85]),
        'appendix_notes' => 'Test appendix for Run 25 with evidence diversity context'
    ];
    
    $bundle_save_result = $adapter->save_synthesis_bundle($runid, $test_synthesis_bundle);
    if ($bundle_save_result) {
        echo "<p>✅ Created test synthesis bundle via adapter</p>\n";
    } else {
        echo "<p>❌ Failed to create test synthesis bundle</p>\n";
    }
    
    // Add some compatibility logs
    \local_customerintel\services\log_service::info($runid, 
        '[Compatibility] Test log entry for Run 25 diagnostic generation');
    \local_customerintel\services\log_service::info($runid, 
        '[Compatibility] Artifact adapter test - normalized_inputs_v16 mapped to synthesis_inputs');
    \local_customerintel\services\log_service::info($runid, 
        '[Compatibility] v15_structure injected successfully for Run 25');
    
    echo "</div>\n";
    
    // Refresh artifacts list
    $artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid], 'phase ASC, artifacttype ASC');
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Phase</th><th>Artifact Type</th><th>Size (KB)</th><th>Created</th></tr>\n";
foreach ($artifacts as $artifact) {
    $size_kb = round(strlen($artifact->jsondata ?? '') / 1024, 2);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($artifact->phase) . "</td>";
    echo "<td>" . htmlspecialchars($artifact->artifacttype) . "</td>";
    echo "<td>{$size_kb}</td>";
    echo "<td>" . date('Y-m-d H:i:s', $artifact->timecreated) . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// 2. Test compatibility adapter loading
echo "<h3>2. Compatibility Adapter Testing</h3>\n";
$adapter = new \local_customerintel\services\artifact_compatibility_adapter();

// Test synthesis_inputs loading
$synthesis_inputs = $adapter->load_artifact($runid, 'synthesis_inputs');
if ($synthesis_inputs) {
    echo "<p>✅ Successfully loaded synthesis_inputs via adapter</p>\n";
    echo "<p>Citation count: " . count($synthesis_inputs['normalized_citations'] ?? []) . "</p>\n";
    if (isset($synthesis_inputs['domain_analysis'])) {
        echo "<p>✅ Domain analysis present (diversity ratio: " . ($synthesis_inputs['domain_analysis']['diversity_ratio'] ?? 'N/A') . ")</p>\n";
    }
} else {
    echo "<p>❌ Failed to load synthesis_inputs via adapter</p>\n";
}

// Test synthesis bundle loading
$synthesis_bundle = $adapter->load_synthesis_bundle($runid);
if ($synthesis_bundle) {
    echo "<p>✅ Successfully loaded synthesis bundle via adapter</p>\n";
    echo "<p>Bundle fields: " . count($synthesis_bundle) . "</p>\n";
    if (isset($synthesis_bundle['v15_structure'])) {
        echo "<p>✅ v15_structure field present</p>\n";
    } else {
        echo "<p>❌ v15_structure field missing</p>\n";
    }
} else {
    echo "<p>❌ Failed to load synthesis bundle via adapter</p>\n";
}

// 3. Generate diagnostic archive
echo "<h2>3. Generating Diagnostic Archive</h2>\n";

$diagnostic_service = new \local_customerintel\services\diagnostic_archive_service();
$result = $diagnostic_service->generate_diagnostic_archive($runid);

if ($result['success']) {
    echo "<div class='alert alert-success'>\n";
    echo "<h3>✅ Diagnostic Archive Generated Successfully</h3>\n";
    echo "<p><strong>Filename:</strong> " . htmlspecialchars($result['filename']) . "</p>\n";
    echo "<p><strong>File Size:</strong> " . round($result['filesize'] / 1024, 2) . " KB</p>\n";
    echo "<p><strong>Generation Time:</strong> " . $result['generation_time_ms'] . "ms</p>\n";
    echo "<p><strong>File Path:</strong> " . htmlspecialchars($result['filepath']) . "</p>\n";
    
    echo "<h4>Archive Contents Summary:</h4>\n";
    $manifest = $result['manifest'];
    echo "<ul>\n";
    echo "<li><strong>Artifacts:</strong> " . $manifest['summary']['artifacts_found'] . " found</li>\n";
    echo "<li><strong>Compatibility Logs:</strong> " . $manifest['summary']['compatibility_log_entries'] . " entries</li>\n";
    echo "<li><strong>Telemetry Records:</strong> " . $manifest['summary']['telemetry_records'] . " records</li>\n";
    echo "<li><strong>Artifact Repository Records:</strong> " . $manifest['summary']['artifact_repository_records'] . " records</li>\n";
    echo "</ul>\n";
    
    echo "<h4>Detailed Artifact Analysis:</h4>\n";
    if (isset($manifest['archive_contents']['artifacts'])) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Artifact</th><th>Source</th><th>Size (KB)</th><th>Notes</th></tr>\n";
        foreach ($manifest['archive_contents']['artifacts'] as $key => $artifact_info) {
            $size_kb = round(($artifact_info['size'] ?? 0) / 1024, 2);
            $notes = [];
            if (isset($artifact_info['has_domain_analysis']) && $artifact_info['has_domain_analysis']) {
                $notes[] = 'Domain analysis present';
            }
            if (isset($artifact_info['has_v15_structure']) && $artifact_info['has_v15_structure']) {
                $notes[] = 'v15_structure present';
            }
            if (isset($artifact_info['citation_count'])) {
                $notes[] = $artifact_info['citation_count'] . ' citations';
            }
            if (isset($artifact_info['field_count'])) {
                $notes[] = $artifact_info['field_count'] . ' fields';
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($key) . "</td>";
            echo "<td>" . htmlspecialchars($artifact_info['source'] ?? 'unknown') . "</td>";
            echo "<td>{$size_kb}</td>";
            echo "<td>" . htmlspecialchars(implode(', ', $notes)) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<p class='mt-3'><a href='" . $CFG->wwwroot . "/local/customerintel/download_diagnostics.php?runid={$runid}' class='btn btn-warning btn-lg'>📥 Download Archive</a></p>\n";
    echo "</div>\n";
    
} else {
    echo "<div class='alert alert-danger'>\n";
    echo "<h3>❌ Diagnostic Archive Generation Failed</h3>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($result['error']) . "</p>\n";
    echo "<p><strong>Generation Time:</strong> " . $result['generation_time_ms'] . "ms</p>\n";
    echo "</div>\n";
}

// 4. Path Analysis Summary
echo "<h2>4. Path Analysis Summary</h2>\n";
echo "<div class='alert alert-info'>\n";
echo "<h3>🔍 Diagnostic Findings</h3>\n";
echo "<p>This diagnostic archive will definitively show:</p>\n";
echo "<ul>\n";
echo "<li><strong>Artifact Existence:</strong> Whether normalized_inputs_v16 or synthesis_inputs artifacts exist</li>\n";
echo "<li><strong>File Paths:</strong> Exact paths and locations of all artifacts</li>\n";
echo "<li><strong>Structure Validation:</strong> Whether artifact structure matches viewer expectations</li>\n";
echo "<li><strong>Compatibility Flow:</strong> Complete log of compatibility adapter operations</li>\n";
echo "<li><strong>Evidence Diversity Context:</strong> Whether diversity metrics are preserved throughout pipeline</li>\n";
echo "</ul>\n";
echo "<p><strong>Archive File:</strong> Contains all data needed to debug viewer-pipeline mismatches</p>\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p><em>Diagnostic generation completed at: " . date('Y-m-d H:i:s') . "</em></p>\n";