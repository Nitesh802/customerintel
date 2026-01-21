<?php
// Comprehensive script to find and analyze Run 277 citations
// Access via browser

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('local/customerintel:manage', context_system::instance());

global $DB;

header('Content-Type: text/plain');

$run_id = 280;

echo "===========================================\n";
echo "Run 280 Citation Analysis - Comprehensive\n";
echo "===========================================\n\n";

// Method 1: Check main run table
echo "CHECKING STORAGE LOCATIONS...\n\n";

$run = $DB->get_record('local_ci_run', ['id' => $run_id]);
if ($run && !empty($run->report)) {
    echo "Found in local_ci_run.report field\n";
    analyze_citations($run->report, "Run table report");
}

// Check all artifact types
$artifact_types = [
    'synthesis_final_bundle',
    'synthesis_report',
    'final_report',
    'synthesis_cache',
    'report_html'
];

foreach ($artifact_types as $type) {
    $artifact = $DB->get_record('local_ci_artifact', [
        'runid' => $run_id,
        'artifacttype' => $type
    ]);

    if ($artifact) {
        echo "Found artifact type: {$type} (" . strlen($artifact->content) . " bytes)\n";

        // Try as raw HTML
        if (strpos($artifact->content, '[') !== false) {
            analyze_citations($artifact->content, "Artifact: {$type}");
        }

        // Try as JSON
        $json = json_decode($artifact->content, true);
        if ($json) {
            $content = '';
            if (isset($json['final_report'])) $content .= $json['final_report'];
            if (isset($json['executive_summary'])) $content .= $json['executive_summary'];
            if (isset($json['sections'])) {
                foreach ($json['sections'] as $section) {
                    if (is_array($section)) {
                        $content .= ' ' . ($section['content'] ?? '');
                        $content .= ' ' . ($section['formatted_html'] ?? '');
                    } else {
                        $content .= ' ' . $section;
                    }
                }
            }
            if (isset($json['report'])) $content .= $json['report'];

            if ($content) {
                analyze_citations($content, "JSON from {$type}");
            }
        }
    }
}

// Also check the synthesis table if it exists
$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $run_id]);
if ($synthesis) {
    echo "\nFound in local_ci_synthesis table\n";

    // Show all fields and their sizes
    echo "Fields in synthesis record:\n";
    foreach ($synthesis as $field => $value) {
        if (is_string($value)) {
            $size = strlen($value);
            echo "  - {$field}: {$size} bytes\n";
            if ($size > 0 && $size < 500) {
                echo "    Preview: " . substr($value, 0, 100) . "\n";
            }
        }
    }

    // Check the actual fields
    if (!empty($synthesis->htmlcontent)) {
        analyze_citations($synthesis->htmlcontent, "Synthesis: htmlcontent");
    }
    if (!empty($synthesis->jsoncontent)) {
        // Try to extract content from JSON
        $json = json_decode($synthesis->jsoncontent, true);
        if ($json) {
            $content = '';
            if (isset($json['final_report'])) $content .= $json['final_report'];
            if (isset($json['executive_summary'])) $content .= ' ' . $json['executive_summary'];
            if (isset($json['sections'])) {
                foreach ($json['sections'] as $section) {
                    if (is_array($section)) {
                        $content .= ' ' . ($section['content'] ?? '');
                        $content .= ' ' . ($section['formatted_html'] ?? '');
                    }
                }
            }
            if ($content) {
                analyze_citations($content, "Synthesis: jsoncontent (parsed)");
            } else {
                // Fallback: analyze raw JSON
                analyze_citations($synthesis->jsoncontent, "Synthesis: jsoncontent (raw)");
            }
        } else {
            analyze_citations($synthesis->jsoncontent, "Synthesis: jsoncontent (raw)");
        }
    }
} else {
    echo "\nNo record in local_ci_synthesis table\n";
}

// Check run status
echo "\n\nRUN STATUS:\n";
$run = $DB->get_record('local_ci_run', ['id' => $run_id]);
if ($run) {
    echo "- Status: " . $run->status . "\n";
    echo "- Created: " . date('Y-m-d H:i:s', $run->timecreated) . "\n";
    if (isset($run->timemodified)) {
        echo "- Modified: " . date('Y-m-d H:i:s', $run->timemodified) . "\n";
    }
}

function analyze_citations($content, $source) {
    echo "\n--- Analyzing: {$source} ---\n";
    echo "Content length: " . strlen($content) . " bytes\n";

    // Find all citations [123]
    preg_match_all('/\[(\d+)\]/', $content, $matches);

    if (count($matches[0]) == 0) {
        echo "No citations found in this source\n";
        return;
    }

    $all_citations = $matches[1];
    $unique_citations = array_unique($all_citations);
    sort($unique_citations, SORT_NUMERIC);

    // Separate valid vs phantom
    $valid = array_filter($unique_citations, function($c) {
        return intval($c) <= 203;
    });

    $phantom = array_filter($unique_citations, function($c) {
        return intval($c) > 203;
    });

    // Find unused valid citations
    $all_valid_nums = range(1, 203);
    $unused = array_diff($all_valid_nums, $valid);

    echo "\nRESULTS:\n";
    echo "- Total citation uses: " . count($all_citations) . "\n";
    echo "- Unique citations: " . count($unique_citations) . "\n";
    echo "- Valid citations used: " . count($valid) . "/203 (" . round(count($valid)/203*100, 1) . "%)\n";
    echo "- Phantom citations: " . count($phantom) . "\n";
    echo "- Unused valid citations: " . count($unused) . "\n";

    if (count($phantom) > 0) {
        sort($phantom, SORT_NUMERIC);
        echo "\nPHANTOM CITATIONS: " . implode(', ', $phantom) . "\n";
        echo "Highest phantom: " . max($phantom) . "\n";
    } else {
        echo "\n✅ NO PHANTOM CITATIONS!\n";
    }
}

echo "\n\n===========================================\n";
echo "Analysis Complete\n";
echo "===========================================\n";
?>
