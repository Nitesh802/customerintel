<?php
/**
 * Check ALL runs and their artifacts to find successful synthesis
 *
 * This checks the artifact table to find runs that generated synthesis,
 * even if they don't have records in local_ci_synthesis table.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_all_runs_artifacts.php'));
$PAGE->set_title("All Runs - Artifact Analysis");

echo $OUTPUT->header();

?>
<style>
.analysis { font-family: Arial, sans-serif; max-width: 1400px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
th, td { padding: 8px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; font-weight: bold; }
.has-synthesis { background: #d4edda; }
.no-synthesis { background: #f8d7da; }
.code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>

<div class="analysis">

<h1>📊 All Runs - Synthesis Artifact Analysis</h1>

<p>Checking artifacts to find ALL runs that generated synthesis, including older successful runs...</p>

<?php

// Get all completed runs with their artifact information
echo "<div class='section'>";
echo "<h2>Completed Runs with Synthesis Artifacts</h2>";

$runs_with_synthesis = $DB->get_records_sql(
    "SELECT r.id as runid,
            r.timecreated,
            r.companyid,
            r.targetcompanyid,
            COUNT(DISTINCT nb.id) as nb_count,
            COUNT(DISTINCT CASE WHEN a.phase = 'synthesis' AND a.artifacttype = 'final_bundle' THEN a.id END) as has_final_bundle,
            MAX(s.id) as synthesis_db_id,
            MAX(LENGTH(s.htmlcontent)) as html_size
     FROM {local_ci_run} r
     LEFT JOIN {local_ci_nb_result} nb ON nb.runid = r.id
     LEFT JOIN {local_ci_artifact} a ON a.runid = r.id
     LEFT JOIN {local_ci_synthesis} s ON s.runid = r.id
     WHERE r.status = 'completed'
     GROUP BY r.id, r.timecreated, r.companyid, r.targetcompanyid
     ORDER BY r.timecreated DESC
     LIMIT 100"
);

echo "<p>Found " . count($runs_with_synthesis) . " completed runs:</p>";

echo "<table>";
echo "<tr>";
echo "<th>Run ID</th>";
echo "<th>Date</th>";
echo "<th>NBs</th>";
echo "<th>Has Artifacts?</th>";
echo "<th>In DB?</th>";
echo "<th>HTML Size</th>";
echo "<th>Status</th>";
echo "<th>Actions</th>";
echo "</tr>";

$successful_with_artifacts = [];
$missing_from_db = [];

foreach ($runs_with_synthesis as $run) {
    $has_artifacts = $run->has_final_bundle > 0;
    $in_db = $run->synthesis_db_id ? true : false;

    $status_class = '';
    $status_text = '';

    if ($has_artifacts && $in_db) {
        $status_class = 'has-synthesis';
        $status_text = '✅ Complete';
    } else if ($has_artifacts && !$in_db) {
        $status_class = 'warning';
        $status_text = '⚠️ Needs Backfill';
        $missing_from_db[] = $run->runid;
    } else {
        $status_class = 'no-synthesis';
        $status_text = '❌ No Synthesis';
    }

    if ($has_artifacts) {
        $successful_with_artifacts[] = $run->runid;
    }

    echo "<tr class='{$status_class}'>";
    echo "<td><strong>{$run->runid}</strong></td>";
    echo "<td>" . date('Y-m-d H:i', $run->timecreated) . "</td>";
    echo "<td>{$run->nb_count}</td>";
    echo "<td>" . ($has_artifacts ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>" . ($in_db ? "✅ Yes (ID: {$run->synthesis_db_id})" : '❌ No') . "</td>";
    echo "<td>" . ($run->html_size ? number_format($run->html_size) . ' B' : '-') . "</td>";
    echo "<td>{$status_text}</td>";
    echo "<td>";

    if ($has_artifacts && !$in_db) {
        echo "<a href='backfill_synthesis_from_artifacts.php?runid={$run->runid}'>Backfill</a> | ";
    }

    echo "<a href='inspect_synthesis_artifacts.php?runid={$run->runid}'>Inspect</a>";

    if ($in_db) {
        echo " | <a href='verify_new_run.php?runid={$run->runid}'>Verify</a>";
    }

    echo "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Summary:</h3>";
echo "<ul>";
echo "<li><strong>Total completed runs:</strong> " . count($runs_with_synthesis) . "</li>";
echo "<li><strong>✅ Runs with synthesis artifacts:</strong> " . count($successful_with_artifacts) . "</li>";
echo "<li><strong>⚠️ Missing from database (need backfill):</strong> " . count($missing_from_db) . "</li>";
echo "</ul>";

if (count($missing_from_db) > 0) {
    echo "<p class='warning'><strong>⚠️ Found " . count($missing_from_db) . " runs that generated synthesis but aren't in database!</strong></p>";
    echo "<p>These runs completed successfully but synthesis records weren't saved due to the bug we just fixed.</p>";
    echo "<p>Run IDs needing backfill: " . implode(', ', array_slice($missing_from_db, 0, 10));
    if (count($missing_from_db) > 10) {
        echo " ... and " . (count($missing_from_db) - 10) . " more";
    }
    echo "</p>";
}

echo "</div>";

// Now check specific runs the user mentioned (128, 122, etc.)
echo "<div class='section'>";
echo "<h2>🔍 Checking Specific Runs (128, 122, etc.)</h2>";

$specific_runs = [128, 122, 120, 115, 110, 100];

echo "<table>";
echo "<tr>";
echo "<th>Run ID</th>";
echo "<th>Status</th>";
echo "<th>Artifacts?</th>";
echo "<th>Action</th>";
echo "</tr>";

foreach ($specific_runs as $runid) {
    $run = $DB->get_record('local_ci_run', ['id' => $runid]);

    if (!$run) {
        echo "<tr>";
        echo "<td>{$runid}</td>";
        echo "<td colspan='3'>❌ Run not found</td>";
        echo "</tr>";
        continue;
    }

    $has_artifacts = $DB->count_records('local_ci_artifact', [
        'runid' => $runid,
        'phase' => 'synthesis',
        'artifacttype' => 'final_bundle'
    ]) > 0;

    $has_db_record = $DB->record_exists('local_ci_synthesis', ['runid' => $runid]);

    echo "<tr class='" . ($has_artifacts ? 'success' : 'fail') . "'>";
    echo "<td><strong>{$runid}</strong></td>";
    echo "<td>{$run->status}</td>";
    echo "<td>" . ($has_artifacts ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>";

    if ($has_artifacts && !$has_db_record) {
        echo "<a href='backfill_synthesis_from_artifacts.php?runid={$runid}'>📥 Backfill to DB</a> | ";
    }

    if ($has_artifacts) {
        echo "<a href='inspect_synthesis_artifacts.php?runid={$runid}'>🔍 Inspect</a>";
        echo " | <a href='compare_nb_schemas.php?run1={$runid}&run2=192'>📊 Compare with 192</a>";
    }

    echo "</td>";
    echo "</tr>";
}

echo "</table>";

echo "</div>";

// Timeline analysis
if (count($successful_with_artifacts) > 0) {
    echo "<div class='section'>";
    echo "<h2>📅 Timeline Analysis</h2>";

    // Get first and last successful run
    $first_success = max($successful_with_artifacts);
    $last_success = min($successful_with_artifacts);

    $first_run = $DB->get_record('local_ci_run', ['id' => $first_success]);
    $last_run = $DB->get_record('local_ci_run', ['id' => $last_success]);

    if ($first_run && $last_run) {
        echo "<p><strong>First successful run:</strong> Run {$first_success} on " . date('Y-m-d H:i:s', $first_run->timecreated) . "</p>";
        echo "<p><strong>Most recent run with synthesis:</strong> Run {$last_success} on " . date('Y-m-d H:i:s', $last_run->timecreated) . "</p>";

        $time_span_days = ($last_run->timecreated - $first_run->timecreated) / 86400;
        echo "<p><strong>Synthesis working period:</strong> " . round($time_span_days, 1) . " days</p>";
    }

    // Check if there are runs AFTER the last successful one
    $failed_runs = $DB->get_records_sql(
        "SELECT r.id, r.timecreated
         FROM {local_ci_run} r
         WHERE r.status = 'completed'
         AND r.timecreated > ?
         AND NOT EXISTS (
             SELECT 1 FROM {local_ci_artifact} a
             WHERE a.runid = r.id
             AND a.phase = 'synthesis'
             AND a.artifacttype = 'final_bundle'
         )
         ORDER BY r.timecreated ASC
         LIMIT 10",
        [$last_run->timecreated]
    );

    if (count($failed_runs) > 0) {
        echo "<p class='fail'><strong>⚠️ Found " . count($failed_runs) . " runs AFTER last successful synthesis that have NO artifacts!</strong></p>";
        echo "<p>This suggests something changed around " . date('Y-m-d', $last_run->timecreated) . " that broke synthesis generation.</p>";

        echo "<p>Failed runs: ";
        $failed_ids = array_column($failed_runs, 'id');
        echo implode(', ', array_slice($failed_ids, 0, 10));
        echo "</p>";
    }

    echo "</div>";
}

// Recommendations
echo "<div class='section'>";
echo "<h2>💡 Next Steps</h2>";

if (count($missing_from_db) > 0) {
    echo "<h3>1. Backfill Successful Runs</h3>";
    echo "<p>You have " . count($missing_from_db) . " runs that generated synthesis but aren't in the database.</p>";
    echo "<p>To restore them, click 'Backfill' for each run above, or run them in order:</p>";
    echo "<ol>";
    foreach (array_slice($missing_from_db, 0, 5) as $runid) {
        echo "<li><a href='backfill_synthesis_from_artifacts.php?runid={$runid}'>Backfill Run {$runid}</a></li>";
    }
    echo "</ol>";
}

if (count($successful_with_artifacts) > 0 && in_array(128, $successful_with_artifacts)) {
    echo "<h3>2. Compare Successful vs Failed Schemas</h3>";
    echo "<p>Run 128 has synthesis artifacts. Compare it with Run 192 to see what changed:</p>";
    echo "<p><a href='compare_nb_schemas.php?run1=128&run2=192' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📊 Compare Run 128 vs 192</a></p>";
}

echo "<h3>3. Inspect Artifact Contents</h3>";
echo "<p>Look at the actual synthesis data from successful runs:</p>";
echo "<ul>";
foreach (array_slice($successful_with_artifacts, 0, 5) as $runid) {
    echo "<li><a href='inspect_synthesis_artifacts.php?runid={$runid}'>Inspect Run {$runid}</a></li>";
}
echo "</ul>";

echo "</div>";

?>

</div>

<?php

echo $OUTPUT->footer();

?>
