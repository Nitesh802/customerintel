<?php
/**
 * Deep NB Schema Inspector for Run 192
 *
 * Inspects the ACTUAL structure of NB data to understand
 * what fields exist for pattern detection.
 *
 * This will show us the REAL schema, not what we think it should be.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $PAGE, $OUTPUT;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/inspect_nb_schema_192.php'));
$PAGE->set_title("NB Schema Inspector - Run $runid");

echo $OUTPUT->header();

?>
<style>
.inspector { font-family: 'Consolas', 'Monaco', 'Courier New', monospace; max-width: 1600px; margin: 20px auto; font-size: 13px; }
.section { background: #fff; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 5px solid #007bff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.info { background: #e7f3ff; border-left-color: #17a2b8; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.success { background: #d4edda; border-left-color: #28a745; }
.tree { font-family: 'Consolas', monospace; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; white-space: pre; overflow-x: auto; line-height: 1.6; }
.json-view { background: #2d2d2d; color: #f8f8f2; padding: 20px; border-radius: 5px; overflow-x: auto; max-height: 600px; overflow-y: auto; font-size: 12px; }
.json-key { color: #f92672; }
.json-string { color: #e6db74; }
.json-number { color: #ae81ff; }
.json-bool { color: #66d9ef; }
.json-null { color: #66d9ef; }
h1 { color: #333; }
h2 { color: #555; margin-top: 0; font-size: 20px; }
h3 { color: #666; font-size: 16px; margin: 15px 0 10px 0; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
th { background: #e9ecef; font-weight: 600; }
.key-badge { background: #007bff; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-right: 5px; }
.type-badge { background: #6c757d; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; }
</style>

<div class="inspector">

<h1>🔬 Deep NB Schema Inspector - Run <?= $runid ?></h1>

<div class="section info">
<h2>📋 Purpose</h2>
<p>This script inspects the ACTUAL structure of NB data to understand what fields exist for pattern detection.</p>
<p><strong>We need to see the real schema, not assumptions!</strong></p>
</div>

<?php

// ============================================================================
// STEP 1: Load Canonical Dataset
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 1: Loading Canonical NB Dataset</h2>";

require_once(__DIR__ . '/classes/services/artifact_compatibility_adapter.php');

$adapter = new \local_customerintel\services\artifact_compatibility_adapter();

// Try to load canonical_nb_dataset
$canonical_artifact = null;
$artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid]);

foreach ($artifacts as $artifact) {
    if ($artifact->artifacttype === 'canonical_nb_dataset' ||
        ($artifact->phase === 'synthesis_core' && strpos($artifact->artifacttype, 'canonical') !== false)) {
        $canonical_artifact = $artifact;
        break;
    }
}

if (!$canonical_artifact) {
    echo "<p style='color: #dc3545;'>❌ No canonical_nb_dataset artifact found!</p>";
    echo "<p>Available artifacts:</p>";
    echo "<ul>";
    foreach ($artifacts as $artifact) {
        echo "<li>{$artifact->phase} / {$artifact->artifacttype}</li>";
    }
    echo "</ul>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

$canonical_data = json_decode($canonical_artifact->jsondata, true);

if (!$canonical_data) {
    echo "<p style='color: #dc3545;'>❌ Failed to decode canonical dataset JSON</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<p>✅ Loaded canonical_nb_dataset artifact (ID: {$canonical_artifact->id})</p>";
echo "<p><strong>Size:</strong> " . number_format(strlen($canonical_artifact->jsondata)) . " bytes</p>";
echo "<p><strong>Top-level keys:</strong> " . implode(', ', array_keys($canonical_data)) . "</p>";

echo "</div>";

// ============================================================================
// STEP 2: Extract NB Data
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 2: Extracting NB Data</h2>";

// The canonical dataset might have different structures
$nb_data = null;

if (isset($canonical_data['nb_data'])) {
    $nb_data = $canonical_data['nb_data'];
    echo "<p>✅ Found NB data under key: <span class='key-badge'>nb_data</span></p>";
} elseif (isset($canonical_data['nbs'])) {
    $nb_data = $canonical_data['nbs'];
    echo "<p>✅ Found NB data under key: <span class='key-badge'>nbs</span></p>";
} elseif (isset($canonical_data['normalized_nbs'])) {
    $nb_data = $canonical_data['normalized_nbs'];
    echo "<p>✅ Found NB data under key: <span class='key-badge'>normalized_nbs</span></p>";
} else {
    // Maybe the whole thing is NB data
    $nb_data = $canonical_data;
    echo "<p>⚠️ Using entire canonical dataset as NB data (no explicit nb_data key)</p>";
}

if (!$nb_data || !is_array($nb_data)) {
    echo "<p style='color: #dc3545;'>❌ Could not extract NB data</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

$nb_keys = array_keys($nb_data);
echo "<p><strong>NB keys found:</strong> " . implode(', ', $nb_keys) . "</p>";
echo "<p><strong>Total NBs:</strong> " . count($nb_keys) . "</p>";

echo "</div>";

// ============================================================================
// STEP 3: Deep Inspection of NB1
// ============================================================================

echo "<div class='section success'>";
echo "<h2>Step 3: Deep Inspection of NB1</h2>";

$nb1 = null;
foreach (['NB1', 'NB-1', 'nb1', 'nb-1'] as $key) {
    if (isset($nb_data[$key])) {
        $nb1 = $nb_data[$key];
        echo "<p>✅ Found NB1 under key: <span class='key-badge'>{$key}</span></p>";
        break;
    }
}

if (!$nb1) {
    echo "<p style='color: #dc3545;'>❌ NB1 not found in NB data</p>";
    echo "<p>Available keys: " . implode(', ', array_keys($nb_data)) . "</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

// Show NB1 top-level structure
echo "<h3>NB1 Top-Level Structure:</h3>";
echo "<div class='tree'>";
echo "NB1:\n";

$nb1_keys = is_array($nb1) ? array_keys($nb1) : [];
foreach ($nb1_keys as $idx => $key) {
    $is_last = ($idx === count($nb1_keys) - 1);
    $prefix = $is_last ? '└── ' : '├── ';

    $value = $nb1[$key];
    $type = gettype($value);

    if (is_array($value)) {
        $count = count($value);
        echo "{$prefix}{$key}: <span class='type-badge'>array[{$count}]</span>\n";
    } elseif (is_string($value)) {
        $preview = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
        echo "{$prefix}{$key}: <span class='type-badge'>string</span> \"{$preview}\"\n";
    } elseif (is_numeric($value)) {
        echo "{$prefix}{$key}: <span class='type-badge'>number</span> {$value}\n";
    } elseif (is_bool($value)) {
        $bool_str = $value ? 'true' : 'false';
        echo "{$prefix}{$key}: <span class='type-badge'>boolean</span> {$bool_str}\n";
    } elseif (is_null($value)) {
        echo "{$prefix}{$key}: <span class='type-badge'>null</span>\n";
    } else {
        echo "{$prefix}{$key}: <span class='type-badge'>{$type}</span>\n";
    }
}

echo "</div>";

echo "</div>";

// ============================================================================
// STEP 4: Inspect NB1 'data' Field
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 4: NB1 'data' Field Deep Dive</h2>";

if (!isset($nb1['data'])) {
    echo "<p style='color: #ffc107;'>⚠️ No 'data' field found in NB1</p>";
    echo "<p>NB1 keys: " . implode(', ', array_keys($nb1)) . "</p>";
} else {
    $nb1_data = $nb1['data'];

    echo "<h3>NB1 Data Field Type: <span class='type-badge'>" . gettype($nb1_data) . "</span></h3>";

    if (is_array($nb1_data)) {
        $data_keys = array_keys($nb1_data);
        echo "<p><strong>Keys in 'data' field:</strong></p>";
        echo "<table>";
        echo "<tr><th>Key</th><th>Type</th><th>Preview</th></tr>";

        foreach ($data_keys as $key) {
            $value = $nb1_data[$key];
            $type = gettype($value);

            $preview = '';
            if (is_array($value)) {
                $preview = "Array with " . count($value) . " items";
                if (count($value) > 0) {
                    $first_item = reset($value);
                    if (is_array($first_item)) {
                        $preview .= " (first item has " . count($first_item) . " fields)";
                    }
                }
            } elseif (is_string($value)) {
                $preview = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
            } elseif (is_numeric($value)) {
                $preview = $value;
            } elseif (is_bool($value)) {
                $preview = $value ? 'true' : 'false';
            }

            echo "<tr>";
            echo "<td><span class='key-badge'>{$key}</span></td>";
            echo "<td><span class='type-badge'>{$type}</span></td>";
            echo "<td>" . htmlspecialchars($preview) . "</td>";
            echo "</tr>";
        }

        echo "</table>";

        // Show tree structure
        echo "<h3>NB1 Data Tree Structure:</h3>";
        echo "<div class='tree'>";
        echo "NB1['data']:\n";

        foreach ($data_keys as $idx => $key) {
            $is_last = ($idx === count($data_keys) - 1);
            $prefix = $is_last ? '└── ' : '├── ';

            $value = $nb1_data[$key];

            if (is_array($value) && count($value) > 0) {
                echo "{$prefix}{$key}: array[" . count($value) . "]\n";

                // Show first item structure if it's an array
                $first_item = reset($value);
                if (is_array($first_item)) {
                    $sub_keys = array_keys($first_item);
                    $connector = $is_last ? '    ' : '│   ';
                    echo "{$connector}└── [0]: {\n";
                    foreach ($sub_keys as $sub_idx => $sub_key) {
                        $is_sub_last = ($sub_idx === count($sub_keys) - 1);
                        $sub_prefix = $is_sub_last ? '└── ' : '├── ';
                        echo "{$connector}    {$sub_prefix}{$sub_key}\n";
                    }
                    echo "{$connector}    }\n";
                }
            } else {
                $type = gettype($value);
                echo "{$prefix}{$key}: {$type}\n";
            }
        }

        echo "</div>";

    } else {
        echo "<p>Data field is not an array: " . gettype($nb1_data) . "</p>";
    }
}

echo "</div>";

// ============================================================================
// STEP 5: Check for Expected Schema Fields
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 5: Schema Field Validation</h2>";

$expected_fields = [
    'pressure_factors' => 'Expected in NB1 for pressure themes',
    'key_metrics' => 'Expected in NB1 for numeric proofs',
    'commitments' => 'Expected in NB1',
    'profitability' => 'Expected in NB3 for operational issues',
    'competitive_threats' => 'Expected in NB4/NB8 for competitive pressures',
    'leadership_assessment' => 'Expected in NB11',
    'leadership_team' => 'Expected in NB11'
];

echo "<h3>Looking for Expected Fields in NB1 'data':</h3>";
echo "<table>";
echo "<tr><th>Field</th><th>Found?</th><th>Notes</th></tr>";

foreach ($expected_fields as $field => $note) {
    $found = false;
    $location = '';

    if (isset($nb1_data[$field])) {
        $found = true;
        $location = "Top-level in data";
    } elseif (isset($nb1[$field])) {
        $found = true;
        $location = "Top-level in NB1";
    }

    $status = $found ? '✅ YES' : '❌ NO';
    $color = $found ? '#28a745' : '#dc3545';

    echo "<tr>";
    echo "<td><span class='key-badge'>{$field}</span></td>";
    echo "<td style='color: {$color}; font-weight: bold;'>{$status}</td>";
    echo "<td>{$note}" . ($location ? " | Found: {$location}" : "") . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "</div>";

// ============================================================================
// STEP 6: Full JSON View of NB1
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 6: Complete NB1 JSON Structure</h2>";

echo "<p>This shows the EXACT JSON structure of NB1:</p>";

// Pretty-print JSON
$nb1_json = json_encode($nb1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Syntax highlight
$nb1_json_highlighted = htmlspecialchars($nb1_json);
$nb1_json_highlighted = preg_replace('/"([^"]+)"(:)/', '<span class="json-key">"$1"</span>$2', $nb1_json_highlighted);
$nb1_json_highlighted = preg_replace('/(: )"([^"]*)"/', '$1<span class="json-string">"$2"</span>', $nb1_json_highlighted);
$nb1_json_highlighted = preg_replace('/(: )(\d+)/', '$1<span class="json-number">$2</span>', $nb1_json_highlighted);
$nb1_json_highlighted = preg_replace('/(: )(true|false)/', '$1<span class="json-bool">$2</span>', $nb1_json_highlighted);
$nb1_json_highlighted = preg_replace('/(: )(null)/', '$1<span class="json-null">$2</span>', $nb1_json_highlighted);

echo "<div class='json-view'>";
echo $nb1_json_highlighted;
echo "</div>";

echo "<p style='margin-top: 15px;'><strong>JSON Size:</strong> " . number_format(strlen($nb1_json)) . " bytes</p>";

echo "</div>";

// ============================================================================
// STEP 7: Citations Analysis
// ============================================================================

echo "<div class='section'>";
echo "<h2>Step 7: Citations Analysis</h2>";

if (isset($nb1['citations'])) {
    $citations = $nb1['citations'];
    echo "<p>✅ Found 'citations' field in NB1</p>";
    echo "<p><strong>Type:</strong> " . gettype($citations) . "</p>";

    if (is_array($citations)) {
        echo "<p><strong>Count:</strong> " . count($citations) . " citations</p>";

        if (count($citations) > 0) {
            echo "<h3>First Citation Structure:</h3>";
            $first_citation = reset($citations);
            echo "<pre>" . htmlspecialchars(json_encode($first_citation, JSON_PRETTY_PRINT)) . "</pre>";
        }
    }
} else {
    echo "<p style='color: #ffc107;'>⚠️ No 'citations' field found in NB1</p>";
    echo "<p>Available top-level keys: " . implode(', ', array_keys($nb1)) . "</p>";
}

echo "</div>";

// ============================================================================
// STEP 8: Recommendations
// ============================================================================

echo "<div class='section info'>";
echo "<h2>📝 Analysis & Recommendations</h2>";

echo "<h3>What We Found:</h3>";
echo "<ul>";

if (isset($nb1_data)) {
    $actual_keys = array_keys($nb1_data);
    echo "<li><strong>Actual 'data' field keys:</strong> " . implode(', ', $actual_keys) . "</li>";

    $has_pressure_factors = isset($nb1_data['pressure_factors']);
    $has_key_metrics = isset($nb1_data['key_metrics']);

    if ($has_pressure_factors) {
        echo "<li>✅ <strong>pressure_factors</strong> field exists - pattern detection should work!</li>";
    } else {
        echo "<li>❌ <strong>pressure_factors</strong> field NOT found - pattern detection will fail</li>";
        echo "<li>💡 Pattern detection is looking for 'pressure_factors' but NB has: " . implode(', ', $actual_keys) . "</li>";
    }

    if ($has_key_metrics) {
        echo "<li>✅ <strong>key_metrics</strong> field exists - numeric proofs should work!</li>";
    } else {
        echo "<li>❌ <strong>key_metrics</strong> field NOT found - numeric proofs will fail</li>";
    }
}

echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li>Review the JSON structure above to see EXACTLY what fields exist</li>";
echo "<li>Compare actual fields with what pattern detection expects</li>";
echo "<li>Update pattern detection code to use ACTUAL field names</li>";
echo "<li>If fields don't match schema, NBs may need regeneration</li>";
echo "</ul>";

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
