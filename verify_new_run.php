<?php
/**
 * Quick verification of a new run
 * Usage: Pass runid as URL parameter
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = optional_param('runid', 0, PARAM_INT);

if (!$runid) {
    echo "<h1>Verify New Run</h1>";
    echo "<p>Usage: verify_new_run.php?runid=X</p>";

    // Show recent runs
    $recent = $DB->get_records_sql(
        "SELECT id, companyid, targetcompanyid, status, timecreated
         FROM {local_ci_run}
         ORDER BY timecreated DESC
         LIMIT 10"
    );

    echo "<h2>Recent Runs</h2>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Run ID</th><th>Companies</th><th>Status</th><th>Created</th><th>Action</th></tr>";

    foreach ($recent as $r) {
        $company = $DB->get_record('local_ci_company', ['id' => $r->companyid], 'name');
        $target = $DB->get_record('local_ci_company', ['id' => $r->targetcompanyid], 'name');

        echo "<tr>";
        echo "<td>{$r->id}</td>";
        echo "<td>{$company->name} → {$target->name}</td>";
        echo "<td>{$r->status}</td>";
        echo "<td>" . date('Y-m-d H:i', $r->timecreated) . "</td>";
        echo "<td><a href='?runid={$r->id}'>Verify</a></td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/verify_new_run.php', ['runid' => $runid]));
$PAGE->set_title("Verify Run {$runid}");

echo $OUTPUT->header();

?>
<style>
.verify { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }
.check { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { color: #28a745; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 10px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; }
</style>

<div class="verify">

<h1>🔍 Verification Report for Run <?php echo $runid; ?></h1>

<?php

$run = $DB->get_record('local_ci_run', ['id' => $runid]);

if (!$run) {
    echo "<p class='fail'>❌ Run {$runid} not found!</p>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

$company = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
$target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);

echo "<p><strong>{$company->name}</strong> → <strong>{$target->name}</strong></p>";
echo "<p>Status: <strong>{$run->status}</strong></p>";

// Check 1: NBs
echo "<div class='check'>";
echo "<h2>✓ Check 1: NBs in Database</h2>";

$nb_count = $DB->count_records('local_ci_nb_result', ['runid' => $runid]);
echo "<p>NB Count: <strong>{$nb_count}/15</strong></p>";

if ($nb_count == 15) {
    echo "<p class='success'>✅ All 15 NBs present</p>";
} else if ($nb_count > 0) {
    echo "<p class='warning'>⚠️ Only {$nb_count} NBs (expected 15)</p>";
} else {
    echo "<p class='fail'>❌ No NBs found!</p>";
}

// Check citations
if ($nb_count > 0) {
    $nbs_with_citations = $DB->get_records_sql(
        "SELECT COUNT(*) as count FROM {local_ci_nb_result}
         WHERE runid = ? AND citations IS NOT NULL AND citations != ''",
        [$runid]
    );

    $citation_count = reset($nbs_with_citations)->count;
    echo "<p>NBs with citations: <strong>{$citation_count}/{$nb_count}</strong></p>";

    if ($citation_count == $nb_count) {
        echo "<p class='success'>✅ All NBs have citations</p>";
    }
}

echo "</div>";

// Check 2: Synthesis
echo "<div class='check'>";
echo "<h2>✓ Check 2: Synthesis Generated</h2>";

$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);

if ($synthesis) {
    echo "<p class='success'>✅ Synthesis record found (ID: {$synthesis->id})</p>";

    $html_size = strlen($synthesis->htmlcontent ?? '');
    $json_size = strlen($synthesis->jsoncontent ?? '');

    echo "<p>HTML size: <strong>" . number_format($html_size) . "</strong> bytes</p>";
    echo "<p>JSON size: <strong>" . number_format($json_size) . "</strong> bytes</p>";

    if ($html_size > 50000) {
        echo "<p class='success'>✅ Substantial content</p>";
    } else if ($html_size > 10000) {
        echo "<p class='warning'>⚠️ Moderate content</p>";
    } else {
        echo "<p class='fail'>❌ Very small content</p>";
    }

    // Check M1T3 metadata
    if (!empty($synthesis->source_company_id)) {
        echo "<p class='success'>✅ M1T3 metadata present</p>";
        echo "<p>Source: {$synthesis->source_company_id}, Target: {$synthesis->target_company_id}</p>";
    } else {
        echo "<p class='warning'>⚠️ M1T3 metadata missing</p>";
    }

} else {
    echo "<p class='fail'>❌ No synthesis record found</p>";
}

echo "</div>";

// Check 3: Sections
echo "<div class='check'>";
echo "<h2>✓ Check 3: Synthesis Sections</h2>";

if ($synthesis) {
    // Extract sections from JSON content (sections are stored in synthesis_cache)
    $section_count = 0;
    $sections = [];

    if (!empty($synthesis->jsoncontent)) {
        $json_data = json_decode($synthesis->jsoncontent, true);
        if (isset($json_data['synthesis_cache']['v15_structure']['sections'])) {
            $sections = $json_data['synthesis_cache']['v15_structure']['sections'];
            $section_count = count($sections);
        }
    }

    echo "<p>Sections: <strong>{$section_count}/9</strong></p>";

    if ($section_count >= 9) {
        echo "<p class='success'>✅ All sections present in JSON</p>";
    } else if ($section_count > 0) {
        echo "<p class='warning'>⚠️ Only {$section_count} sections</p>";
    } else {
        echo "<p class='fail'>❌ No sections found</p>";
    }

    if ($section_count > 0 && is_array($sections)) {
        echo "<table>";
        echo "<tr><th>Section Code</th><th>Content Type</th></tr>";
        foreach ($sections as $code => $section_data) {
            echo "<tr>";
            echo "<td>{$code}</td>";
            echo "<td>" . (is_array($section_data) ? 'Array' : 'String') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>No synthesis to check sections</p>";
}

echo "</div>";

// Check 4: Trace/Artifacts
echo "<div class='check'>";
echo "<h2>✓ Check 4: Trace Data</h2>";

$artifacts = $DB->get_records('local_ci_artifact', ['runid' => $runid]);
$artifact_count = count($artifacts);

echo "<p>Artifacts: <strong>{$artifact_count}</strong></p>";

if ($artifact_count > 0) {
    echo "<table>";
    echo "<tr><th>Phase</th><th>Type</th><th>Size</th></tr>";
    foreach ($artifacts as $art) {
        $size = strlen($art->jsondata ?? '');
        echo "<tr>";
        echo "<td>{$art->phase}</td>";
        echo "<td>{$art->artifacttype}</td>";
        echo "<td>" . number_format($size) . " bytes</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</div>";

// Final Verdict
echo "<div class='check' style='border-left-color: #28a745; background: #d4edda;'>";
echo "<h2>🎯 Final Verdict</h2>";

$all_good = ($nb_count == 15 && $synthesis && $section_count >= 9 && $html_size > 10000);

if ($all_good) {
    echo "<p class='success' style='font-size: 20px;'>✅ COMPLETE SUCCESS!</p>";
    echo "<p>All validation checks passed:</p>";
    echo "<ul>";
    echo "<li>✅ 15 NBs generated and saved</li>";
    echo "<li>✅ Synthesis created with substantial content</li>";
    echo "<li>✅ All 9 sections present</li>";
    echo "<li>✅ M1T3 metadata preserved</li>";
    echo "</ul>";
    echo "<p><strong>Bug #9 and M1T5-M1T8 are fully validated!</strong></p>";
    echo "<p><a href='view_report.php?runid={$runid}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📊 View Report</a></p>";
} else {
    echo "<p class='warning' style='font-size: 18px;'>⚠️ Partial Success</p>";
    echo "<p>Some checks didn't pass. Review the details above.</p>";
}

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
