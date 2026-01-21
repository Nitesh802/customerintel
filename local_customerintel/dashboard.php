<?php
/**
 * Customer Intelligence Dashboard - Main Dashboard Page
 *
 * @package    local_customerintel
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:view', $context);

// Get URL parameters for search and sorting
$search = optional_param('intel', '', PARAM_TEXT);
$sort = optional_param('sort', 'started', PARAM_ALPHA);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);

// Validate sort column
$valid_sorts = ['id', 'customer', 'target', 'status', 'started', 'completed'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'started';
}

// Validate direction
$dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

// Set up page
$PAGE->set_url('/local/customerintel/dashboard.php', ['intel' => $search, 'sort' => $sort, 'dir' => $dir]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard', 'local_customerintel'));
$PAGE->set_heading(get_string('pluginname', 'local_customerintel'));
$PAGE->set_pagelayout('admin');

// Output header
echo $OUTPUT->header();

// Get queue statistics from run table - handle if table doesn't exist
try {
    $queue_stats = [
        'queued' => $DB->count_records('local_ci_run', ['status' => 'pending']),
        'running' => $DB->count_records('local_ci_run', ['status' => 'processing']),
        'completed' => $DB->count_records('local_ci_run', ['status' => 'completed']),
        'failed' => $DB->count_records('local_ci_run', ['status' => 'failed'])
    ];
} catch (Exception $e) {
    $queue_stats = [
        'queued' => 0,
        'running' => 0,
        'completed' => 0,
        'failed' => 0
    ];
}

// Get recent runs with search and sorting
$recent_runs = [];
try {
    $params = [];
    $where_clauses = [];

    // Build search conditions if search term provided
    if (!empty($search)) {
        $search_lower = strtolower(trim($search));

        // Search by run ID (exact or partial match)
        $where_clauses[] = $DB->sql_like('CAST(r.id AS CHAR)', ':search_id', false);
        $params['search_id'] = '%' . $DB->sql_like_escape($search_lower) . '%';

        // Search by customer company name
        $where_clauses[] = $DB->sql_like('LOWER(c.name)', ':search_customer', false);
        $params['search_customer'] = '%' . $DB->sql_like_escape($search_lower) . '%';

        // Search by target company name
        $where_clauses[] = $DB->sql_like('LOWER(t.name)', ':search_target', false);
        $params['search_target'] = '%' . $DB->sql_like_escape($search_lower) . '%';
    }

    // Build WHERE clause
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE (' . implode(' OR ', $where_clauses) . ')';
    }

    // Map sort column to SQL field
    $sort_map = [
        'id' => 'r.id',
        'customer' => 'c.name',
        'target' => 't.name',
        'status' => 'r.status',
        'started' => 'r.timecreated',
        'completed' => 'r.timecompleted'
    ];
    $order_field = $sort_map[$sort] ?? 'r.timecreated';
    $order_sql = "ORDER BY {$order_field} {$dir}";

    // Handle NULL values for completed column (put NULLs at end for desc, beginning for asc)
    if ($sort === 'completed') {
        if ($dir === 'desc') {
            $order_sql = "ORDER BY CASE WHEN r.timecompleted IS NULL THEN 1 ELSE 0 END, r.timecompleted {$dir}";
        } else {
            $order_sql = "ORDER BY CASE WHEN r.timecompleted IS NULL THEN 1 ELSE 0 END DESC, r.timecompleted {$dir}";
        }
    }

    $sql = "SELECT r.*, c.name AS customer_name, t.name AS target_name
            FROM {local_ci_run} r
            LEFT JOIN {local_ci_company} c ON r.companyid = c.id
            LEFT JOIN {local_ci_company} t ON r.targetcompanyid = t.id
            {$where_sql}
            {$order_sql}";

    // Limit results: show all matching when searching, otherwise show recent 5
    $limit = empty($search) ? 5 : 0;
    $recent_runs = $DB->get_records_sql($sql, $params, 0, $limit);

    // Set default names for missing companies
    foreach ($recent_runs as $run) {
        if (empty($run->customer_name)) {
            $run->customer_name = 'Unknown';
        }
        if (empty($run->target_name)) {
            $run->target_name = '-';
        }
    }
} catch (Exception $e) {
    $recent_runs = [];
}

// Prepare template data
$templatedata = new stdClass();
$templatedata->runurl = new moodle_url('/local/customerintel/run.php');
$templatedata->reportsurl = new moodle_url('/local/customerintel/reports.php');
$templatedata->sourcesurl = new moodle_url('/local/customerintel/sources.php');
$templatedata->queued = $queue_stats['queued'];
$templatedata->running = $queue_stats['running'];
$templatedata->completed = $queue_stats['completed'];
$templatedata->failed = $queue_stats['failed'];

// Add logs URL if user has manage capability
if (has_capability('local/customerintel:manage', $context)) {
    $templatedata->logsurl = new moodle_url('/local/customerintel/logs.php');
    $templatedata->canviewlogs = true;
}

// Search and sort parameters for template (used by custom frontend)
$templatedata->search = $search;
$templatedata->sort = $sort;
$templatedata->dir = $dir;

// Generate sort URLs for each column
$opposite_dir = ($dir === 'asc') ? 'desc' : 'asc';
$sort_columns = ['id', 'customer', 'target', 'status', 'started', 'completed'];
foreach ($sort_columns as $col) {
    // If clicking current sort column, toggle direction; otherwise use desc as default
    $col_dir = ($sort === $col) ? $opposite_dir : 'desc';
    $templatedata->{'sort_' . $col . '_url'} = new moodle_url('/local/customerintel/dashboard.php', [
        'intel' => $search,
        'sort' => $col,
        'dir' => $col_dir
    ]);
    // Mark if this column is currently sorted and in which direction
    $templatedata->{'sort_' . $col . '_active'} = ($sort === $col);
    $templatedata->{'sort_' . $col . '_asc'} = ($sort === $col && $dir === 'asc');
    $templatedata->{'sort_' . $col . '_desc'} = ($sort === $col && $dir === 'desc');
}

// Status icon configuration
$status_config = [
    'completed' => ['icon' => 'fa-circle-check', 'class' => 'completed'],
    'processing' => ['icon' => 'fa-circle-notch fa-spin', 'class' => 'processing'],
    'running' => ['icon' => 'fa-circle-notch fa-spin', 'class' => 'running'],
    'pending' => ['icon' => 'fa-circle-notch', 'class' => 'pending'],
    'failed' => ['icon' => 'fa-circle-exclamation', 'class' => 'failed'],
];

// Status display mapping - show user-friendly terms
$status_display_map = [
    'pending' => 'Queued',
    'processing' => 'In Progress',
    'running' => 'In Progress',
    'completed' => 'Completed',
    'failed' => 'Failed'
];

// Format runs for template
$templatedata->runs = [];
foreach ($recent_runs as $run) {
    $rundata = new stdClass();
    $rundata->id = $run->id;
    $rundata->customername = isset($run->customer_name) ? $run->customer_name : 'Unknown';
    $rundata->targetname = isset($run->target_name) ? $run->target_name : '-';

    // Status icon
    $status = $run->status;
    if (isset($status_config[$status])) {
        $config = $status_config[$status];
        $rundata->icon = sprintf(
            '<i class="fa-solid %s %s" title="%s"></i>',
            $config['icon'],
            $config['class'],
            $status
        );
    } else {
        $rundata->icon = '';
    }

    // Normalize status display
    $rundata->status = $status_display_map[$run->status] ?? $run->status;
    $rundata->status_raw = $run->status; // Keep raw value for logic checks

    $rundata->timestarted_h = userdate($run->timecreated, get_string('strftimedatetime'));
    $rundata->timecompleted_h = ($run->status == 'completed' && !empty($run->timecompleted))
        ? userdate($run->timecompleted, get_string('strftimedatetime'))
        : '-';

    // M2 Task 0.2: Add view report button for pending, processing, and completed runs
    $rundata->can_view_report = ($run->status === 'completed' || $run->status === 'processing' || $run->status === 'pending');
    $rundata->is_processing = ($run->status === 'processing' || $run->status === 'pending');
    $rundata->report_url = new moodle_url('/local/customerintel/view_report.php', ['runid' => $run->id]);

    $templatedata->runs[] = $rundata;
}

// Render using template
echo $OUTPUT->render_from_template('local_customerintel/dashboard', $templatedata);

// Output footer
echo $OUTPUT->footer();
