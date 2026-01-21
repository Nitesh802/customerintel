<?php
/**
 * Regenerate Run 192 synthesis with the NB key fix
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = 192;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/regenerate_run192.php'));
$PAGE->set_title("Regenerate Run 192");

echo $OUTPUT->header();

?>
<style>
.regen { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
pre { background: white; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 400px; font-size: 11px; }
</style>

<div class="regen">

<h1>🔄 Regenerate Run 192 Synthesis</h1>

<div class="section">
<h2>Fix Applied</h2>
<p>Updated <code>synthesis_engine.php</code> line 802:</p>
<pre>// OLD: preg_match('/^NB\d+$/', $key)  // Only matches NB1, NB2, NB3
// NEW: preg_match('/^NB-?\d+$/', $key) // Matches NB1 AND NB-1, NB-2, NB-3</pre>

<p><strong>Impact:</strong> The canonical builder will now receive all 15 NBs (instead of 0), including their 254 citations!</p>
</div>

<?php

echo "<div class='section'>";
echo "<h2>Regenerating Synthesis</h2>";

try {
    require_once(__DIR__ . '/classes/services/synthesis_engine.php');
    require_once(__DIR__ . '/classes/services/log_service.php');
    require_once(__DIR__ . '/classes/services/artifact_repository.php');

    $engine = new \local_customerintel\services\synthesis_engine();

    echo "<p>🔄 Starting synthesis regeneration for Run {$runid}...</p>";
    flush();

    $result = $engine->build_report($runid, true); // force regenerate

    if ($result && isset($result['html'])) {
        $html_size = strlen($result['html']);
        $section_count = 0;

        if (isset($result['json'])) {
            $json_data = json_decode($result['json'], true);
            if (isset($json_data['synthesis_cache']['v15_structure']['sections'])) {
                $section_count = count($json_data['synthesis_cache']['v15_structure']['sections']);
            }
        }

        echo "<div class='success'>";
        echo "<h3>✅ Synthesis Generated Successfully!</h3>";
        echo "<ul>";
        echo "<li><strong>HTML Size:</strong> " . number_format($html_size) . " bytes</li>";
        echo "<li><strong>Section Count:</strong> {$section_count}</li>";
        echo "</ul>";
        echo "</div>";

        // Check citation count from canonical dataset artifact
        $artifact_repo = new \local_customerintel\services\artifact_repository();
        $canonical_artifact = $artifact_repo->get_artifact($runid, 'synthesis', 'canonical_nb_dataset');

        if ($canonical_artifact) {
            $canonical = json_decode($canonical_artifact->jsondata, true);
            $citation_count = 0;

            if (isset($canonical['nb_data'])) {
                foreach ($canonical['nb_data'] as $nb) {
                    if (isset($nb['citations']) && is_array($nb['citations'])) {
                        $citation_count += count($nb['citations']);
                    }
                }
            }

            echo "<div class='section'>";
            echo "<h3>📊 Citation Analysis</h3>";
            echo "<p><strong>NBs in canonical dataset:</strong> " . count($canonical['nb_data'] ?? []) . "</p>";
            echo "<p><strong>Citations in canonical dataset:</strong> {$citation_count}</p>";

            if ($citation_count > 0) {
                echo "<p class='success'>✅ Citations successfully passed through canonical builder!</p>";
            } else {
                echo "<p class='fail'>❌ Still 0 citations in canonical dataset</p>";
            }
            echo "</div>";
        } else {
            echo "<div class='warning'>";
            echo "<h3>⚠️ Canonical Dataset Artifact Not Found</h3>";
            echo "<p>The canonical dataset artifact wasn't saved (trace mode may be disabled)</p>";
            echo "</div>";
        }

    } else {
        echo "<div class='fail'>";
        echo "<h3>❌ Synthesis Generation Failed</h3>";
        echo "<p>Check Moodle logs for details.</p>";
        echo "</div>";
    }

} catch (\Exception $e) {
    echo "<div class='fail'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div>";

echo "<div class='section success'>";
echo "<h2>✅ Next Steps</h2>";
echo "<p><a href='view_report.php?runid={$runid}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📊 View Updated Report</a></p>";
echo "<p><a href='verify_new_run.php?runid={$runid}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>📋 Verify Run</a></p>";
echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
