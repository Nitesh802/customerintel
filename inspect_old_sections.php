<?php
/**
 * Inspect the OLD synthesis_record sections to see how they were structured
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/inspect_old_sections.php'));
$PAGE->set_title("Inspect OLD Sections");

echo $OUTPUT->header();

?>
<style>
.inspect { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 10px; max-height: 400px; }
</style>

<div class="inspect">

<h1>🔍 Inspect OLD Synthesis Sections</h1>

<?php

// Get OLD synthesis_record
$old_artifact = $DB->get_record('local_ci_artifact', [
    'runid' => 128,
    'phase' => 'synthesis',
    'artifacttype' => 'synthesis_record'
]);

if (!$old_artifact) {
    echo "<div class='section bad'>";
    echo "<p>❌ OLD synthesis_record not found</p>";
    echo "</div></div>";
    echo $OUTPUT->footer();
    exit;
}

$data = json_decode($old_artifact->jsondata, true);

echo "<div class='section good'>";
echo "<h2>OLD Synthesis Record Structure</h2>";
echo "<p><strong>Top-level keys:</strong></p>";
echo "<pre>";
print_r(array_keys($data));
echo "</pre>";
echo "</div>";

// Inspect sections
if (isset($data['sections'])) {
    echo "<div class='section'>";
    echo "<h2>Sections Data</h2>";
    echo "<p><strong>Number of sections:</strong> " . count($data['sections']) . "</p>";

    $total_content = 0;
    foreach ($data['sections'] as $section_name => $section_data) {
        if (is_array($section_data) && isset($section_data['content'])) {
            $total_content += strlen($section_data['content']);
        }
    }

    echo "<p><strong>Total content across all sections:</strong> " . number_format($total_content) . " characters</p>";

    echo "<h3>Section List:</h3>";
    echo "<table style='width: 100%; border-collapse: collapse; font-size: 11px;'>";
    echo "<tr>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Section Name</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Has Content?</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Content Length</th>";
    echo "</tr>";

    foreach ($data['sections'] as $section_name => $section_data) {
        $has_content = false;
        $content_length = 0;

        if (is_array($section_data)) {
            if (isset($section_data['content'])) {
                $has_content = true;
                $content_length = strlen($section_data['content']);
            }
        } else if (is_string($section_data)) {
            $has_content = true;
            $content_length = strlen($section_data);
        }

        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'><strong>{$section_name}</strong></td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($has_content ? '✅ Yes' : '❌ No') . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($content_length) . " chars</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Show sample section
    $first_section_name = array_keys($data['sections'])[0] ?? null;
    if ($first_section_name) {
        $first_section = $data['sections'][$first_section_name];

        echo "<h3>Sample Section: {$first_section_name}</h3>";
        echo "<pre>";
        if (is_array($first_section)) {
            echo "Keys: " . implode(', ', array_keys($first_section)) . "\n\n";
            if (isset($first_section['content'])) {
                echo "Content (first 500 chars):\n";
                echo htmlspecialchars(substr($first_section['content'], 0, 500));
                echo "\n...";
            }
        } else {
            echo htmlspecialchars(substr($first_section, 0, 500));
        }
        echo "</pre>";
    }

    echo "</div>";
}

// Compare with NEW final_bundle
$new_artifact = $DB->get_record('local_ci_artifact', [
    'runid' => 192,
    'phase' => 'synthesis',
    'artifacttype' => 'final_bundle'
]);

if ($new_artifact) {
    $new_data = json_decode($new_artifact->jsondata, true);

    echo "<div class='section warning'>";
    echo "<h2>NEW Final Bundle (Run 192) for Comparison</h2>";

    if (isset($new_data['json']['sections'])) {
        echo "<p><strong>Number of sections:</strong> " . count($new_data['json']['sections']) . "</p>";

        echo "<h3>Section List:</h3>";
        echo "<table style='width: 100%; border-collapse: collapse; font-size: 11px;'>";
        echo "<tr>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>Section Name</th>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>Has Content?</th>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>Content Length</th>";
        echo "</tr>";

        foreach ($new_data['json']['sections'] as $section_name => $section_data) {
            $has_content = false;
            $content_length = 0;

            if (is_array($section_data) && isset($section_data['content'])) {
                $has_content = true;
                $content_length = strlen($section_data['content']);
            } else if (is_string($section_data)) {
                $has_content = true;
                $content_length = strlen($section_data);
            }

            echo "<tr>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'><strong>{$section_name}</strong></td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($has_content ? '✅ Yes' : '❌ No') . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($content_length) . " chars</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p class='bad'>❌ No sections found in NEW bundle</p>";
    }

    echo "</div>";
}

// Key insight
echo "<div class='section bad'>";
echo "<h2>🎯 THE PROBLEM</h2>";
echo "<p><strong>The OLD system generated sections with actual content.</strong></p>";
echo "<p><strong>The NEW M1T5-M1T8 system is generating empty or minimal sections.</strong></p>";
echo "<p>This is because pattern_detection is returning 0 patterns due to the NB schema mismatch!</p>";
echo "<p>Without patterns, draft_sections has nothing to work with, so it generates empty sections.</p>";
echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
