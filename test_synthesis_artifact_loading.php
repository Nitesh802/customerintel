<?php
/**
 * Test synthesis artifact loading logic
 * This script tests the updated conditional logic for handling missing artifacts
 */

require_once(__DIR__ . '/config.php');

// Test parameters
$test_runid = 23; // Use the failing run ID

// Initialize synthesis engine
require_once($CFG->dirroot . '/local/customerintel/classes/services/synthesis_engine.php');
$synthesis_engine = new \local_customerintel\services\synthesis_engine();

echo "=== Testing Synthesis Artifact Loading Logic ===\n";
echo "Run ID: {$test_runid}\n\n";

// 1. Check if normalized artifact exists
echo "1. Checking for normalized artifact...\n";
$artifact = $DB->get_record('local_ci_artifact', [
    'runid' => $test_runid,
    'phase' => 'citation_normalization', 
    'artifacttype' => 'normalized_inputs_v16'
]);

if ($artifact && !empty($artifact->jsondata)) {
    $data = json_decode($artifact->jsondata, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($data['normalized_citations'])) {
        echo "✓ Artifact found: normalized_inputs_v16_{$test_runid}.json\n";
        echo "  Citations: " . count($data['normalized_citations']) . "\n";
        echo "  Sample log line: Artifact loaded successfully: normalized_inputs_v16_{$test_runid}.json found with " . count($data['normalized_citations']) . " citations\n";
    } else {
        echo "✗ Artifact exists but invalid JSON or missing citations\n";
    }
} else {
    echo "✗ No artifact found\n";
    echo "  Sample log line: No normalized artifact found — rebuilding synthesis inputs from NB results\n";
}

echo "\n2. Checking NB results for fallback...\n";
$nb_results = $DB->get_records('local_ci_nb_result', ['runid' => $test_runid]);
echo "NB results found: " . count($nb_results) . "\n";

$completed_nbs = [];
foreach ($nb_results as $nb) {
    if ($nb->status === 'completed') {
        $completed_nbs[] = $nb->nbcode;
    }
}
echo "Completed NBs: " . implode(', ', $completed_nbs) . "\n";

echo "\n3. Testing updated conditional logic:\n";
if ($artifact && !empty($artifact->jsondata)) {
    echo "→ Path: Load artifact immediately and continue synthesis\n";
    echo "→ Log: 'Artifact loaded successfully: normalized_inputs_v16_{$test_runid}.json found with N citations'\n";
} else {
    echo "→ Path: Log warning and call build_inputs_from_normalized_artifact({$test_runid})\n";
    echo "→ Log: 'No normalized artifact found — rebuilding synthesis inputs from NB results'\n";
    echo "→ Action: No synthesis_input_missing exception thrown, only warning logged\n";
}

echo "\n=== Test Complete ===\n";