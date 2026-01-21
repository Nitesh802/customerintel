<?php
/**
 * Check synthesis history to understand when reports stopped working
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customerintel/check_synthesis_history.php'));
$PAGE->set_title("Synthesis History Analysis");

echo $OUTPUT->header();

?>
<style>
.history { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; }
.section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.success { background: #d4edda; border-left-color: #28a745; }
.warning { background: #fff3cd; border-left-color: #ffc107; }
.fail { background: #f8d7da; border-left-color: #dc3545; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
th, td { padding: 8px; text-align: left; border: 1px solid #dee2e6; }
th { background: #e9ecef; font-weight: bold; }
.large { background: #d4edda; }
.small { background: #fff3cd; }
.tiny { background: #f8d7da; }
</style>

<div class="history">

<h1>📊 Synthesis History Analysis</h1>

<p>Investigating when reports stopped generating full content...</p>

<?php

// Get all synthesis records with size details
echo "<div class='section'>";
echo "<h2>All Synthesis Records (by date)</h2>";

// Check if we should look at ALL records or just recent
$show_all = optional_param('all', 0, PARAM_INT);

$limit_clause = $show_all ? '' : 'LIMIT 50';

$syntheses = $DB->get_records_sql(
    "SELECT s.id, s.runid, s.createdat,
            LENGTH(s.htmlcontent) as html_size,
            LENGTH(s.jsoncontent) as json_size,
            s.source_company_id, s.target_company_id
     FROM {local_ci_synthesis} s
     ORDER BY s.createdat DESC
     {$limit_clause}"
);

if (!$show_all) {
    $total_count = $DB->count_records('local_ci_synthesis');
    echo "<p>Showing most recent 50 of {$total_count} total records. <a href='?all=1'>Show all</a></p>";
}

if (empty($syntheses)) {
    echo "<p>No synthesis records found!</p>";
} else {
    echo "<table>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Run</th>";
    echo "<th>Created</th>";
    echo "<th>HTML Size</th>";
    echo "<th>JSON Size</th>";
    echo "<th>Source ID</th>";
    echo "<th>Target ID</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    foreach ($syntheses as $s) {
        $html_class = '';
        $status = '';

        if ($s->html_size > 50000) {
            $html_class = 'large';
            $status = '✅ Full Content';
        } else if ($s->html_size > 5000) {
            $html_class = 'small';
            $status = '⚠️ Partial';
        } else {
            $html_class = 'tiny';
            $status = '❌ Minimal';
        }

        echo "<tr class='{$html_class}'>";
        echo "<td>{$s->id}</td>";
        echo "<td><a href='verify_new_run.php?runid={$s->runid}'>{$s->runid}</a></td>";
        echo "<td>" . date('Y-m-d H:i:s', $s->createdat) . "</td>";
        echo "<td>" . number_format($s->html_size) . " B</td>";
        echo "<td>" . number_format($s->json_size) . " B</td>";
        echo "<td>" . ($s->source_company_id ?? 'NULL') . "</td>";
        echo "<td>" . ($s->target_company_id ?? 'NULL') . "</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Summary stats
    $total = count($syntheses);
    $large = 0;
    $small = 0;
    $tiny = 0;

    foreach ($syntheses as $s) {
        if ($s->html_size > 50000) $large++;
        else if ($s->html_size > 5000) $small++;
        else $tiny++;
    }

    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>Total: {$total} synthesis records</li>";
    echo "<li>✅ Full Content (>50KB): {$large}</li>";
    echo "<li>⚠️ Partial (5-50KB): {$small}</li>";
    echo "<li>❌ Minimal (<5KB): {$tiny}</li>";
    echo "</ul>";
}

echo "</div>";

// Get detailed section counts for each synthesis
echo "<div class='section'>";
echo "<h2>Section Counts per Synthesis</h2>";

echo "<table>";
echo "<tr>";
echo "<th>Synthesis ID</th>";
echo "<th>Run ID</th>";
echo "<th>Sections</th>";
echo "<th>HTML Size</th>";
echo "<th>Details</th>";
echo "</tr>";

foreach ($syntheses as $s) {
    $section_count = 0;
    $sections_info = '';

    if (!empty($s->jsoncontent)) {
        $json_data = json_decode($DB->get_field('local_ci_synthesis', 'jsoncontent', ['id' => $s->id]), true);

        if (isset($json_data['synthesis_cache']['v15_structure']['sections'])) {
            $sections = $json_data['synthesis_cache']['v15_structure']['sections'];
            $section_count = count($sections);

            // Get section names
            $section_names = array_column($sections, 'section_name');
            $sections_info = implode(', ', array_slice($section_names, 0, 3));
            if (count($section_names) > 3) {
                $sections_info .= '...';
            }
        }
    }

    $class = $section_count >= 9 ? 'large' : ($section_count > 3 ? 'small' : 'tiny');

    echo "<tr class='{$class}'>";
    echo "<td>{$s->id}</td>";
    echo "<td><a href='verify_new_run.php?runid={$s->runid}'>{$s->runid}</a></td>";
    echo "<td>{$section_count}/9</td>";
    echo "<td>" . number_format($s->html_size) . " B</td>";
    echo "<td>{$sections_info}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// Check recent runs to see which have synthesis
echo "<div class='section'>";
echo "<h2>Recent Completed Runs</h2>";

$runs = $DB->get_records_sql(
    "SELECT r.id, r.status, r.timecreated, r.timeupdated,
            r.companyid, r.targetcompanyid,
            (SELECT COUNT(*) FROM {local_ci_nb} WHERE runid = r.id) as nb_count,
            s.id as synthesis_id,
            LENGTH(s.htmlcontent) as html_size
     FROM {local_ci_run} r
     LEFT JOIN {local_ci_synthesis} s ON s.runid = r.id
     WHERE r.status = 'completed'
     ORDER BY r.timecreated DESC
     LIMIT 20"
);

echo "<table>";
echo "<tr>";
echo "<th>Run ID</th>";
echo "<th>Created</th>";
echo "<th>NBs</th>";
echo "<th>Source</th>";
echo "<th>Target</th>";
echo "<th>Synthesis?</th>";
echo "<th>HTML Size</th>";
echo "</tr>";

foreach ($runs as $r) {
    $synth_status = $r->synthesis_id ? "✅ Yes (ID: {$r->synthesis_id})" : "❌ No";
    $html_info = $r->html_size ? number_format($r->html_size) . ' B' : '-';

    echo "<tr>";
    echo "<td><a href='verify_new_run.php?runid={$r->id}'>{$r->id}</a></td>";
    echo "<td>" . date('Y-m-d H:i', $r->timecreated) . "</td>";
    echo "<td>{$r->nb_count}</td>";
    echo "<td>{$r->companyid}</td>";
    echo "<td>" . ($r->targetcompanyid ?? '-') . "</td>";
    echo "<td>{$synth_status}</td>";
    echo "<td>{$html_info}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// Timeline analysis
echo "<div class='section'>";
echo "<h2>📅 Timeline Analysis</h2>";

// Find when M1T5-M1T8 was implemented (look for first run with minimal content)
$first_minimal = null;
$last_full = null;

$all_syntheses = $DB->get_records_sql(
    "SELECT s.id, s.runid, s.createdat, LENGTH(s.htmlcontent) as html_size
     FROM {local_ci_synthesis} s
     ORDER BY s.createdat ASC"
);

foreach ($all_syntheses as $s) {
    if ($s->html_size < 5000 && !$first_minimal) {
        $first_minimal = $s;
    }
    if ($s->html_size > 50000) {
        $last_full = $s;
    }
}

if ($last_full) {
    echo "<p>✅ <strong>Last Full Content Report:</strong> Run {$last_full->runid} on " . date('Y-m-d H:i:s', $last_full->createdat) . " (" . number_format($last_full->html_size) . " bytes)</p>";
}

if ($first_minimal) {
    echo "<p>❌ <strong>First Minimal Content Report:</strong> Run {$first_minimal->runid} on " . date('Y-m-d H:i:s', $first_minimal->createdat) . " (" . number_format($first_minimal->html_size) . " bytes)</p>";
}

if ($last_full && $first_minimal) {
    $time_diff = $first_minimal->createdat - $last_full->createdat;
    $hours = round($time_diff / 3600, 1);

    if ($first_minimal->createdat > $last_full->createdat) {
        echo "<p><strong>⚠️ Content degradation occurred approximately {$hours} hours after last full report</strong></p>";
    }
}

echo "</div>";

?>

<div class="section">
<h2>🔍 Next Steps</h2>
<p>Based on the data above:</p>
<ul>
<li>Identify the last run with full content (>50KB HTML)</li>
<li>Compare that run's NBs to Run 192's NBs to see schema differences</li>
<li>Determine when/why the NB schema changed from field-centric to company-centric</li>
<li>Check if M1T5-M1T8 implementation coincided with content degradation</li>
</ul>

<p><strong>Key Question:</strong> Did full content reports exist before, or is this the first time we're seeing minimal content?</p>
</div>

</div>

<?php

echo $OUTPUT->footer();

?>
