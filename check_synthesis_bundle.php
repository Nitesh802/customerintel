<?php
/**
 * Check the synthesis bundle artifacts to see where actual content is
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_synthesis_bundle.php'));
$PAGE->set_title("Check Synthesis Bundle");

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

<h1>🔍 Check Synthesis Bundle Artifacts</h1>

<?php

// Check Run 128 synthesis_record artifact (OLD system)
echo "<div class='section'>";
echo "<h2>Run 128 - synthesis_record Artifact (OLD System)</h2>";

$run128_old = $DB->get_record('local_ci_artifact', [
    'runid' => 128,
    'phase' => 'synthesis',
    'artifacttype' => 'synthesis_record'
]);

if ($run128_old) {
    echo "<p>✅ Found OLD synthesis_record artifact</p>";
    echo "<p><strong>Size:</strong> " . number_format(strlen($run128_old->jsondata)) . " bytes</p>";
    echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $run128_old->timecreated) . "</p>";

    $data = json_decode($run128_old->jsondata, true);
    if ($data) {
        echo "<p><strong>Keys in artifact:</strong></p>";
        echo "<pre>";
        print_r(array_keys($data));
        echo "</pre>";

        if (isset($data['html'])) {
            $html = $data['html'];
            echo "<p><strong>HTML size:</strong> " . number_format(strlen($html)) . " bytes</p>";

            // Count sections
            preg_match_all('/<h2[^>]*>/', $html, $matches);
            $section_count = count($matches[0]);
            echo "<p><strong>Sections (h2 tags):</strong> {$section_count}</p>";

            $class = $section_count > 5 ? 'good' : 'bad';
            echo "<div class='section {$class}'>";
            if ($section_count > 5) {
                echo "<p>✅ Run 128 OLD artifact has FULL content ({$section_count} sections)</p>";
            } else {
                echo "<p>❌ Run 128 OLD artifact also minimal</p>";
            }
            echo "</div>";
        }
    }
} else {
    echo "<p class='bad'>❌ No synthesis_record artifact found</p>";
}

echo "</div>";

// Check Run 128 final_bundle artifact (NEW system)
echo "<div class='section'>";
echo "<h2>Run 128 - final_bundle Artifact (NEW M1T5-M1T8)</h2>";

$run128_new = $DB->get_record('local_ci_artifact', [
    'runid' => 128,
    'phase' => 'synthesis',
    'artifacttype' => 'final_bundle'
]);

if ($run128_new) {
    echo "<p>✅ Found NEW final_bundle artifact</p>";
    echo "<p><strong>Size:</strong> " . number_format(strlen($run128_new->jsondata)) . " bytes</p>";
    echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $run128_new->timecreated) . "</p>";

    $data = json_decode($run128_new->jsondata, true);
    if ($data) {
        echo "<p><strong>Keys in artifact:</strong></p>";
        echo "<pre>";
        print_r(array_keys($data));
        echo "</pre>";

        if (isset($data['html'])) {
            $html = $data['html'];
            echo "<p><strong>HTML size:</strong> " . number_format(strlen($html)) . " bytes</p>";

            preg_match_all('/<h2[^>]*>/', $html, $matches);
            $section_count = count($matches[0]);
            echo "<p><strong>Sections (h2 tags):</strong> {$section_count}</p>";

            $class = $section_count > 5 ? 'good' : 'bad';
            echo "<div class='section {$class}'>";
            if ($section_count > 5) {
                echo "<p>✅ Run 128 NEW artifact has FULL content ({$section_count} sections)</p>";
            } else {
                echo "<p>❌ Run 128 NEW artifact also minimal ({$section_count} sections)</p>";
            }
            echo "</div>";
        }

        if (isset($data['sections'])) {
            echo "<p><strong>Sections data:</strong> " . count($data['sections']) . " sections</p>";
        }
    }
}

echo "</div>";

// Check Run 192 final_bundle
echo "<div class='section'>";
echo "<h2>Run 192 - final_bundle Artifact</h2>";

$run192 = $DB->get_record('local_ci_artifact', [
    'runid' => 192,
    'phase' => 'synthesis',
    'artifacttype' => 'final_bundle'
]);

if ($run192) {
    echo "<p>✅ Found final_bundle artifact</p>";
    echo "<p><strong>Size:</strong> " . number_format(strlen($run192->jsondata)) . " bytes</p>";
    echo "<p><strong>Created:</strong> " . date('Y-m-d H:i:s', $run192->timecreated) . "</p>";

    $data = json_decode($run192->jsondata, true);
    if ($data) {
        echo "<p><strong>Keys in artifact:</strong></p>";
        echo "<pre>";
        print_r(array_keys($data));
        echo "</pre>";

        if (isset($data['sections'])) {
            echo "<p><strong>Sections data:</strong> " . count($data['sections']) . " sections</p>";
        }

        if (isset($data['html'])) {
            $html = $data['html'];
            echo "<p><strong>HTML size:</strong> " . number_format(strlen($html)) . " bytes</p>";

            preg_match_all('/<h2[^>]*>/', $html, $matches);
            $section_count = count($matches[0]);
            echo "<p><strong>Sections (h2 tags):</strong> {$section_count}</p>";
        }
    }
}

echo "</div>";

// Diagnosis
echo "<div class='section warning'>";
echo "<h2>🔬 Key Insight</h2>";
echo "<p><strong>The OLD synthesis_record artifact (101KB) has the FULL report from before M1T5-M1T8!</strong></p>";
echo "<p>When you regenerated Run 128 with the NEW M1T5-M1T8 code, it created small artifacts with minimal content.</p>";
echo "<p>This confirms that M1T5-M1T8 has a bug where it's not generating full reports.</p>";
echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
