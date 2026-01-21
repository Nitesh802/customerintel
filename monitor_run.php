<?php
/**
 * Monitor a running report in real-time
 * Auto-refreshes every 10 seconds until complete
 *
 * Usage: monitor_run.php?runid=X
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$runid = optional_param('runid', 0, PARAM_INT);
$autorefresh = optional_param('autorefresh', 1, PARAM_INT);

if (!$runid) {
    echo "<h1>Run Monitor</h1>";
    echo "<p>Usage: monitor_run.php?runid=X</p>";

    // Show recent runs
    $recent = $DB->get_records_sql(
        "SELECT id, companyid, targetcompanyid, status, timecreated
         FROM {local_ci_run}
         ORDER BY timecreated DESC
         LIMIT 10"
    );

    echo "<h2>Recent Runs</h2>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Run ID</th><th>Status</th><th>Created</th><th>Action</th></tr>";

    foreach ($recent as $r) {
        echo "<tr>";
        echo "<td>{$r->id}</td>";
        echo "<td><strong>{$r->status}</strong></td>";
        echo "<td>" . date('Y-m-d H:i:s', $r->timecreated) . "</td>";
        echo "<td><a href='?runid={$r->id}'>Monitor</a></td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/monitor_run.php', ['runid' => $runid]));
$PAGE->set_title("Monitor Run {$runid}");

echo $OUTPUT->header();

?>
<style>
.monitor { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; }
.status-box { padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.running { background: #fff3cd; border-left-color: #ffc107; }
.completed { background: #d4edda; border-left-color: #28a745; }
.failed { background: #f8d7da; border-left-color: #dc3545; }
.progress-bar { width: 100%; height: 30px; background: #e9ecef; border-radius: 5px; overflow: hidden; margin: 10px 0; }
.progress-fill { height: 100%; background: #007bff; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 10px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; }
.metric { display: inline-block; padding: 10px 20px; margin: 5px; background: #f8f9fa; border-radius: 5px; }
.metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
.metric-label { font-size: 12px; color: #6c757d; }
</style>

<?php if ($autorefresh): ?>
<meta http-equiv="refresh" content="10">
<?php endif; ?>

<div class="monitor">

<h1>📊 Run Monitor - <?php echo $runid; ?></h1>

<?php

$run = $DB->get_record('local_ci_run', ['id' => $runid]);

if (!$run) {
    echo "<p style='color: #dc3545;'>❌ Run {$runid} not found!</p>";
    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

$company = $DB->get_record('local_ci_company', ['id' => $run->companyid]);
$target = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);

$status_class = 'running';
if ($run->status === 'completed') {
    $status_class = 'completed';
} else if ($run->status === 'failed') {
    $status_class = 'failed';
}

echo "<div class='status-box {$status_class}'>";
echo "<p><strong>{$company->name}</strong> → <strong>{$target->name}</strong></p>";
echo "<p>Status: <strong style='font-size: 18px;'>{$run->status}</strong></p>";
echo "<p>Started: " . date('Y-m-d H:i:s', $run->timecreated) . "</p>";

$elapsed = time() - $run->timecreated;
$minutes = floor($elapsed / 60);
$seconds = $elapsed % 60;
echo "<p>Elapsed: <strong>{$minutes}m {$seconds}s</strong></p>";

if ($run->status !== 'running') {
    echo "<p><a href='?runid={$runid}&autorefresh=0'>⏸️ Stop Auto-Refresh</a></p>";
} else {
    if ($autorefresh) {
        echo "<p>🔄 Auto-refreshing every 10 seconds... <a href='?runid={$runid}&autorefresh=0'>⏸️ Stop</a></p>";
    } else {
        echo "<p><a href='?runid={$runid}&autorefresh=1'>▶️ Enable Auto-Refresh</a></p>";
    }
}

echo "</div>";

// Progress metrics
echo "<h2>Progress Metrics</h2>";

$nb_count = $DB->count_records('local_ci_nb_result', ['runid' => $runid]);
$nb_progress = ($nb_count / 15) * 100;

$synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
$section_count = 0;
if ($synthesis) {
    $section_count = $DB->count_records('local_ci_synthesis_section', ['synthesisid' => $synthesis->id]);
}
$section_progress = ($section_count / 9) * 100;

echo "<div class='metric'>";
echo "<div class='metric-value'>{$nb_count}/15</div>";
echo "<div class='metric-label'>NBs Generated</div>";
echo "</div>";

if ($synthesis) {
    $html_size = strlen($synthesis->htmlcontent ?? '');
    $size_kb = round($html_size / 1024, 1);

    echo "<div class='metric'>";
    echo "<div class='metric-value'>{$section_count}/9</div>";
    echo "<div class='metric-label'>Sections</div>";
    echo "</div>";

    echo "<div class='metric'>";
    echo "<div class='metric-value'>{$size_kb}KB</div>";
    echo "<div class='metric-label'>Content Size</div>";
    echo "</div>";
}

$artifact_count = $DB->count_records('local_ci_artifact', ['runid' => $runid]);
echo "<div class='metric'>";
echo "<div class='metric-value'>{$artifact_count}</div>";
echo "<div class='metric-label'>Artifacts</div>";
echo "</div>";

// Progress bars
echo "<h3>NB Generation Progress</h3>";
echo "<div class='progress-bar'>";
echo "<div class='progress-fill' style='width: {$nb_progress}%'>{$nb_count}/15</div>";
echo "</div>";

if ($synthesis) {
    echo "<h3>Section Generation Progress</h3>";
    echo "<div class='progress-bar'>";
    echo "<div class='progress-fill' style='width: {$section_progress}%'>{$section_count}/9</div>";
    echo "</div>";
}

// NB Details
echo "<h2>NB Details</h2>";

if ($nb_count > 0) {
    $nbs = $DB->get_records('local_ci_nb_result', ['runid' => $runid], 'nbcode');

    echo "<table>";
    echo "<tr><th>NB</th><th>Status</th><th>Citations</th><th>Tokens</th><th>Duration</th></tr>";

    for ($i = 1; $i <= 15; $i++) {
        $nbcode = "NB{$i}";
        $nb = null;

        // Try both formats
        foreach ($nbs as $n) {
            if ($n->nbcode === $nbcode || $n->nbcode === "NB-{$i}") {
                $nb = $n;
                break;
            }
        }

        echo "<tr>";
        echo "<td><strong>{$nbcode}</strong></td>";

        if ($nb) {
            $citation_count = 0;
            if (!empty($nb->citations)) {
                $citations = json_decode($nb->citations, true);
                $citation_count = is_array($citations) ? count($citations) : 0;
            }

            echo "<td>✅ {$nb->status}</td>";
            echo "<td>{$citation_count}</td>";
            echo "<td>" . number_format($nb->tokensused ?? 0) . "</td>";
            echo "<td>" . round(($nb->durationms ?? 0) / 1000, 2) . "s</td>";
        } else {
            echo "<td>⏳ Pending...</td>";
            echo "<td>-</td>";
            echo "<td>-</td>";
            echo "<td>-</td>";
        }

        echo "</tr>";
    }

    echo "</table>";

    // Summary
    $total_tokens = $DB->get_records_sql(
        "SELECT SUM(tokensused) as total FROM {local_ci_nb_result} WHERE runid = ?",
        [$runid]
    );
    $total = reset($total_tokens)->total ?? 0;

    echo "<p><strong>Total tokens used: " . number_format($total) . "</strong></p>";
}

// Synthesis Status
if ($synthesis) {
    echo "<h2>Synthesis Status</h2>";

    $html_size = strlen($synthesis->htmlcontent ?? '');
    $json_size = strlen($synthesis->jsoncontent ?? '');

    echo "<p>Synthesis ID: <strong>{$synthesis->id}</strong></p>";
    echo "<p>HTML size: <strong>" . number_format($html_size) . "</strong> bytes</p>";
    echo "<p>JSON size: <strong>" . number_format($json_size) . "</strong> bytes</p>";

    if (!empty($synthesis->source_company_id)) {
        echo "<p>M1T3 Metadata: ✅ Present (Source: {$synthesis->source_company_id}, Target: {$synthesis->target_company_id})</p>";
    }

    if ($section_count > 0) {
        $sections = $DB->get_records('local_ci_synthesis_section', ['synthesisid' => $synthesis->id], 'sectioncode');

        echo "<h3>Sections</h3>";
        echo "<table>";
        echo "<tr><th>Section</th><th>Size</th></tr>";

        foreach ($sections as $section) {
            $size = strlen($section->htmlcontent ?? '');
            echo "<tr>";
            echo "<td>{$section->sectioncode}</td>";
            echo "<td>" . number_format($size) . " bytes</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
}

// Next Actions
echo "<h2>Next Actions</h2>";

if ($run->status === 'completed') {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>";
    echo "<p><strong>✅ Run Completed!</strong></p>";
    echo "<p><a href='verify_new_run.php?runid={$runid}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;'>📋 Full Verification Report</a></p>";
    echo "<p><a href='check_nb_schema_compatibility.php?runid={$runid}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;'>🔬 Check NB Schema</a></p>";
    echo "<p><a href='view_report.php?runid={$runid}' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;'>📊 View Report</a></p>";
    echo "</div>";
} else if ($run->status === 'failed') {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;'>";
    echo "<p><strong>❌ Run Failed</strong></p>";
    echo "<p>Check Moodle logs for error details.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; border-left: 4px solid #0c5460;'>";
    echo "<p><strong>⏳ Run In Progress...</strong></p>";
    echo "<p>Current: {$nb_count}/15 NBs generated</p>";
    if ($nb_count === 15 && !$synthesis) {
        echo "<p>All NBs complete! Waiting for synthesis generation...</p>";
    }
    echo "<p>This page will auto-refresh every 10 seconds.</p>";
    echo "</div>";
}

?>

<p style='margin-top: 30px; text-align: center; color: #6c757d;'>
    Last updated: <?php echo date('Y-m-d H:i:s'); ?>
</p>

</div>

<?php

echo $OUTPUT->footer();

?>
