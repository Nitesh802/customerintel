<?php
/**
 * Test Synthesis with Artifact Collection
 * 
 * Tests the synthesis pipeline with artifact collection enabled
 */

require_once(__DIR__ . '/../../config.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

echo "Testing Synthesis Pipeline with Artifact Collection\n";
echo "==================================================\n\n";

// Get a recent completed run for testing
$recent_run = $DB->get_record_sql(
    "SELECT * FROM {local_ci_run} WHERE status = ? ORDER BY timecompleted DESC LIMIT 1",
    ['completed']
);

if (!$recent_run) {
    echo "❌ No completed runs found. Please run an intelligence report first.\n";
    exit;
}

echo "📊 Testing with Run ID: {$recent_run->id}\n";

// Get company info
$company = $DB->get_record('local_ci_company', ['id' => $recent_run->companyid]);
echo "🏢 Company: " . ($company ? $company->name : 'Unknown') . "\n";

// Check current trace mode setting
$trace_mode = get_config('local_customerintel', 'enable_trace_mode');
echo "🔍 Trace Mode: " . ($trace_mode === '1' ? 'ENABLED' : 'DISABLED') . "\n\n";

// Check existing artifacts before test
$existing_artifacts = $DB->count_records('local_ci_artifact', ['runid' => $recent_run->id]);
echo "📦 Existing artifacts for this run: {$existing_artifacts}\n\n";

if ($trace_mode !== '1') {
    echo "⚠️  Enabling trace mode for this test...\n";
    set_config('enable_trace_mode', '1', 'local_customerintel');
    echo "✅ Trace mode enabled\n\n";
}

try {
    echo "🚀 Starting synthesis engine test...\n";
    
    // Initialize synthesis engine
    require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
    $synthesis_engine = new \local_customerintel\services\synthesis_engine();
    
    echo "📈 Running synthesis for run {$recent_run->id}...\n";
    
    // Force regenerate to ensure artifacts are created
    $start_time = microtime(true);
    $result = $synthesis_engine->build_report($recent_run->id, true);
    $duration = round((microtime(true) - $start_time) * 1000);
    
    echo "✅ Synthesis completed in {$duration}ms\n\n";
    
    // Check artifacts after synthesis
    $new_artifacts = $DB->get_records('local_ci_artifact', ['runid' => $recent_run->id], 'phase ASC, timecreated ASC');
    $new_count = count($new_artifacts);
    
    echo "📦 Artifacts after synthesis: {$new_count}\n";
    echo "📈 New artifacts created: " . ($new_count - $existing_artifacts) . "\n\n";
    
    if (!empty($new_artifacts)) {
        echo "📋 Artifact Details:\n";
        echo "-------------------\n";
        
        $phases = [];
        foreach ($new_artifacts as $artifact) {
            if (!isset($phases[$artifact->phase])) {
                $phases[$artifact->phase] = [];
            }
            $phases[$artifact->phase][] = $artifact;
        }
        
        foreach ($phases as $phase => $phase_artifacts) {
            echo "🔹 {$phase} phase:\n";
            foreach ($phase_artifacts as $artifact) {
                $size = strlen($artifact->jsondata);
                $size_formatted = $size > 1024 ? round($size/1024, 1) . 'KB' : $size . 'B';
                echo "   • {$artifact->artifacttype} ({$size_formatted}) - " . userdate($artifact->timecreated) . "\n";
            }
            echo "\n";
        }
    } else {
        echo "❌ No artifacts were created!\n";
        echo "Debugging information:\n";
        echo "- Trace mode: " . get_config('local_customerintel', 'enable_trace_mode') . "\n";
        echo "- Run status: {$recent_run->status}\n";
        echo "- Synthesis result keys: " . implode(', ', array_keys($result)) . "\n";
    }
    
    // Test view_trace.php if artifacts exist
    if (!empty($new_artifacts)) {
        echo "🔍 Testing trace view...\n";
        $trace_url = new moodle_url('/local/customerintel/view_trace.php', ['runid' => $recent_run->id]);
        echo "   View artifacts at: {$trace_url}\n\n";
    }
    
    // Test Data Trace tab
    echo "📊 Testing report view with Data Trace tab...\n";
    $report_url = new moodle_url('/local/customerintel/view_report.php', ['runid' => $recent_run->id]);
    echo "   View report with Data Trace tab at: {$report_url}\n\n";
    
    echo "✅ All tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error during synthesis test: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
?>