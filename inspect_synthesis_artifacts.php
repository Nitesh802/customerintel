<?php
/**
 * Inspect synthesis artifacts to understand what was generated
 * but not persisted to database
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = optional_param('runid', 0, PARAM_INT);

if (!$runid) {
    echo "<h1>Synthesis Artifact Inspector</h1>";
    echo "<p>Usage: inspect_synthesis_artifacts.php?runid=X</p>";

    $recent = $DB->get_records_sql(
        "SELECT id, status, timecreated
         FROM {local_ci_run}
         ORDER BY timecreated DESC
         LIMIT 10"
    );

    echo "<h2>Recent Runs</h2>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Run ID</th><th>Status</th><th>Action</th></tr>";

    foreach ($recent as $r) {
        echo "<tr>";
        echo "<td>{$r->id}</td>";
        echo "<td>{$r->status}</td>";
        echo "<td><a href='?runid={$r->id}'>Inspect</a></td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/inspect_synthesis_artifacts.php', ['runid' => $runid]));
$PAGE->set_title("Synthesis Artifacts - Run {$runid}");

echo $OUTPUT->header();

?>
<style>
.inspect { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; }
.artifact { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.good { background: #d4edda; border-left-color: #28a745; }
.bad { background: #f8d7da; border-left-color: #dc3545; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
pre { background: white; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 500px; overflow-y: auto; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 10px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; }
</style>

<div class="inspect">

<h1>🔍 Synthesis Artifact Inspector - Run <?php echo $runid; ?></h1>

<?php

// Get all synthesis-related artifacts
$artifacts = $DB->get_records_sql(
    "SELECT * FROM {local_ci_artifact}
     WHERE runid = ?
     AND (phase = 'synthesis' OR artifacttype LIKE '%synthesis%' OR artifacttype LIKE '%section%')
     ORDER BY timecreated",
    [$runid]
);

echo "<h2>Synthesis Artifacts Found</h2>";
echo "<p>Total: <strong>" . count($artifacts) . "</strong></p>";

if (empty($artifacts)) {
    echo "<div class='bad'>";
    echo "<p>❌ No synthesis artifacts found for this run!</p>";
    echo "<p>This means synthesis generation never started or failed early.</p>";
    echo "</div>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

echo "<table>";
echo "<tr><th>Phase</th><th>Type</th><th>Size</th><th>Created</th></tr>";

foreach ($artifacts as $art) {
    $size = strlen($art->jsondata ?? '');
    echo "<tr>";
    echo "<td>{$art->phase}</td>";
    echo "<td>{$art->artifacttype}</td>";
    echo "<td>" . number_format($size) . " bytes</td>";
    echo "<td>" . date('Y-m-d H:i:s', $art->timecreated) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Inspect each artifact
foreach ($artifacts as $art) {
    echo "<div class='artifact'>";
    echo "<h3>{$art->phase} → {$art->artifacttype}</h3>";

    $data = json_decode($art->jsondata, true);

    if ($art->artifacttype === 'final_bundle') {
        echo "<div class='good'>";
        echo "<h4>✨ Final Bundle Contents</h4>";

        if (isset($data['sections'])) {
            $section_count = count($data['sections']);
            echo "<p>Sections: <strong>{$section_count}</strong></p>";

            echo "<table>";
            echo "<tr><th>Section Code</th><th>Has HTML</th><th>Has JSON</th><th>HTML Size</th></tr>";

            foreach ($data['sections'] as $code => $section) {
                $has_html = isset($section['htmlcontent']) && !empty($section['htmlcontent']);
                $has_json = isset($section['jsoncontent']) && !empty($section['jsoncontent']);
                $html_size = $has_html ? strlen($section['htmlcontent']) : 0;

                echo "<tr>";
                echo "<td><strong>{$code}</strong></td>";
                echo "<td>" . ($has_html ? "✅" : "❌") . "</td>";
                echo "<td>" . ($has_json ? "✅" : "❌") . "</td>";
                echo "<td>" . number_format($html_size) . " bytes</td>";
                echo "</tr>";
            }

            echo "</table>";

            // Show sample section
            $first_section = reset($data['sections']);
            if ($first_section && isset($first_section['htmlcontent'])) {
                echo "<h4>Sample Section Content (first section):</h4>";
                echo "<pre style='max-height: 300px;'>";
                echo htmlspecialchars(substr($first_section['htmlcontent'], 0, 1000));
                if (strlen($first_section['htmlcontent']) > 1000) {
                    echo "\n\n... (truncated, total " . strlen($first_section['htmlcontent']) . " bytes)";
                }
                echo "</pre>";
            }
        }

        if (isset($data['metadata'])) {
            echo "<h4>Metadata</h4>";
            echo "<pre>";
            print_r($data['metadata']);
            echo "</pre>";
        }

        if (isset($data['synthesis'])) {
            echo "<h4>Synthesis Object</h4>";

            $synth = $data['synthesis'];

            if (isset($synth['htmlcontent'])) {
                $html_size = strlen($synth['htmlcontent']);
                echo "<p>HTML Content Size: <strong>" . number_format($html_size) . "</strong> bytes</p>";
            }

            if (isset($synth['jsoncontent'])) {
                $json_size = strlen($synth['jsoncontent']);
                echo "<p>JSON Content Size: <strong>" . number_format($json_size) . "</strong> bytes</p>";
            }

            echo "<h5>Synthesis Fields:</h5>";
            echo "<pre>";
            foreach ($synth as $key => $value) {
                if ($key === 'htmlcontent' || $key === 'jsoncontent') {
                    echo "{$key}: " . strlen($value) . " bytes\n";
                } else {
                    echo "{$key}: " . (is_scalar($value) ? $value : gettype($value)) . "\n";
                }
            }
            echo "</pre>";
        }

        echo "</div>";

    } else if ($art->artifacttype === 'drafted_sections') {
        echo "<h4>Drafted Sections</h4>";

        if (isset($data['sections'])) {
            echo "<p>Section count: <strong>" . count($data['sections']) . "</strong></p>";
            echo "<pre>";
            print_r(array_keys($data['sections']));
            echo "</pre>";
        }

    } else if ($art->artifacttype === 'canonical_nb_dataset') {
        echo "<h4>Canonical NB Dataset</h4>";

        if (isset($data['nb'])) {
            echo "<p>NB count: <strong>" . count($data['nb']) . "</strong></p>";
            echo "<p>NB keys: " . implode(', ', array_keys($data['nb'])) . "</p>";
        }

        if (isset($data['citations'])) {
            echo "<p>Total citations: <strong>" . count($data['citations']) . "</strong></p>";
        }

    } else {
        echo "<h4>Raw Data</h4>";
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }

    echo "</div>";
}

// Check database for synthesis record
echo "<h2>Database Status</h2>";

$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

if ($synthesis) {
    echo "<div class='good'>";
    echo "<p>✅ Synthesis record found in database (ID: {$synthesis->id})</p>";
    echo "</div>";
} else {
    echo "<div class='bad'>";
    echo "<p>❌ No synthesis record in database</p>";
    echo "<p><strong>This is the problem!</strong> Synthesis was generated (as shown in artifacts) but not saved to the database.</p>";

    echo "<h3>Why This Happens</h3>";
    echo "<ul>";
    echo "<li>synthesis_engine generated the content successfully</li>";
    echo "<li>But the database insert/update failed or was skipped</li>";
    echo "<li>Artifacts were saved (so trace mode is working)</li>";
    echo "<li>But synthesis table was not updated</li>";
    echo "</ul>";

    echo "<h3>Likely Causes</h3>";
    echo "<ul>";
    echo "<li>Exception during synthesis persistence</li>";
    echo "<li>Database transaction rollback</li>";
    echo "<li>synthesis_engine not calling persist_synthesis()</li>";
    echo "<li>Permissions issue on synthesis table</li>";
    echo "</ul>";

    echo "</div>";
}

?>

</div>

<?php

echo $OUTPUT->footer();

?>
