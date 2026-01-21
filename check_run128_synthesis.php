<?php
/**
 * Check how Run 128 synthesis was actually generated
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_run128_synthesis.php'));
$PAGE->set_title("Check Run 128 Synthesis");

echo $OUTPUT->header();

?>
<style>
.check { font-family: monospace; max-width: 1400px; margin: 20px auto; font-size: 12px; }
.section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 10px; max-height: 400px; }
</style>

<div class="check">

<h1>🔍 Check Run 128 Synthesis Generation</h1>

<?php

// Get Run 128 synthesis record
$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => 128]);

echo "<div class='section'>";
echo "<h2>Run 128 Synthesis Record</h2>";

if ($synthesis) {
    echo "<p>✅ Synthesis record found</p>";
    echo "<p><strong>HTML Size:</strong> " . number_format(strlen($synthesis->html)) . " bytes</p>";
    echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $synthesis->timecreated) . "</p>";

    // Count sections in HTML
    $html = $synthesis->html;
    preg_match_all('/<h2[^>]*>/', $html, $matches);
    $section_count = count($matches[0]);
    echo "<p><strong>Sections (h2 tags):</strong> {$section_count}</p>";

    // Check if HTML has content
    $text_content = strip_tags($html);
    $content_length = strlen(trim($text_content));
    echo "<p><strong>Text content length:</strong> " . number_format($content_length) . " characters</p>";

    if ($section_count > 5 && $content_length > 1000) {
        echo "<p class='good'>✅ Run 128 has a FULL report with content</p>";
    } else {
        echo "<p class='bad'>❌ Run 128 also has minimal content</p>";
    }
} else {
    echo "<p class='bad'>❌ No synthesis record found for Run 128</p>";
}

echo "</div>";

// Check artifacts
require_once(__DIR__ . '/classes/services/artifact_repository.php');
$artifact_repo = new \local_customerintel\services\artifact_repository();

echo "<div class='section'>";
echo "<h2>Run 128 Artifacts</h2>";

$artifacts = $DB->get_records('local_ci_artifact', ['runid' => 128], 'timecreated ASC');

if (empty($artifacts)) {
    echo "<p class='warning'>⚠️ No artifacts found for Run 128</p>";
    echo "<p>This means Run 128 was created BEFORE the artifact system was implemented!</p>";
    echo "<p>Run 128 likely used the OLD synthesis_engine code (pre-M1T5-M1T8).</p>";
} else {
    echo "<p>Artifacts found: " . count($artifacts) . "</p>";
    echo "<table style='width: 100%; border-collapse: collapse; font-size: 11px;'>";
    echo "<tr>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Phase</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Type</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Size</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Created</th>";
    echo "</tr>";

    foreach ($artifacts as $artifact) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$artifact->phase}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$artifact->artifacttype}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . number_format(strlen($artifact->jsondata)) . " bytes</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . date('Y-m-d H:i:s', $artifact->timecreated) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

echo "</div>";

// Check Run 192 vs 128 timestamps
$run128 = $DB->get_record('local_ci_run', ['id' => 128]);
$run192 = $DB->get_record('local_ci_run', ['id' => 192]);

echo "<div class='section warning'>";
echo "<h2>Timeline Analysis</h2>";

if ($run128 && $run192) {
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Run</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Created</th>";
    echo "<th style='padding: 8px; border: 1px solid #ddd;'>Status</th>";
    echo "</tr>";

    echo "<tr>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>Run 128</td>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . date('Y-m-d H:i:s', $run128->timecreated) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$run128->status}</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>Run 192</td>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . date('Y-m-d H:i:s', $run192->timecreated) . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$run192->status}</td>";
    echo "</tr>";

    echo "</table>";

    $time_diff = $run192->timecreated - $run128->timecreated;
    $days_diff = floor($time_diff / 86400);

    echo "<p><strong>Time difference:</strong> {$days_diff} days</p>";

    if (empty($artifacts)) {
        echo "<p class='warning'><strong>CRITICAL INSIGHT:</strong></p>";
        echo "<p>Run 128 has NO artifacts, which means it was created BEFORE M1T5-M1T8 was implemented!</p>";
        echo "<p>Run 128 used the OLD synthesis_engine code that generated reports differently.</p>";
        echo "<p>Run 192 is using the NEW M1T5-M1T8 pipeline which expects a different NB schema.</p>";
    }
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
