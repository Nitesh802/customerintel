<?php
/**
 * Customer Intelligence Dashboard - Logs Viewer
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/log_service.php');

use local_customerintel\services\log_service;

// Security
require_login();
$context = context_system::instance();
require_capability('local/customerintel:manage', $context);

// Get parameters
$runid = optional_param('runid', 0, PARAM_INT);
$level = optional_param('level', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url('/local/customerintel/logs.php', ['runid' => $runid, 'level' => $level, 'page' => $page]);
$PAGE->set_title(get_string('viewlogs', 'local_customerintel'));
$PAGE->set_heading(get_string('viewlogs', 'local_customerintel'));
$PAGE->set_pagelayout('admin');

// Add CSS
$PAGE->requires->css('/local/customerintel/styles/customerintel.css');

// Output header
echo $OUTPUT->header();

// Navigation
echo html_writer::start_div('mb-3');
echo html_writer::link(
    new moodle_url('/local/customerintel/dashboard.php'),
    get_string('backtodashboard', 'local_customerintel'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();

// Filter form
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url, 'class' => 'form-inline mb-3']);
echo html_writer::start_div('form-group mr-2');
echo html_writer::label(get_string('filterbyrun', 'local_customerintel'), 'runid', true, ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'runid',
    'id' => 'runid',
    'value' => $runid,
    'class' => 'form-control',
    'placeholder' => get_string('allruns', 'local_customerintel')
]);
echo html_writer::end_div();

echo html_writer::start_div('form-group mr-2');
echo html_writer::label(get_string('filterbylevel', 'local_customerintel'), 'level', true, ['class' => 'mr-2']);
echo html_writer::select([
    '' => get_string('alllevels', 'local_customerintel'),
    'info' => get_string('info', 'local_customerintel'),
    'warning' => get_string('warning', 'local_customerintel'),
    'error' => get_string('error', 'local_customerintel'),
    'debug' => get_string('debug', 'local_customerintel')
], 'level', $level, false, ['class' => 'form-control']);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('filter', 'local_customerintel'),
    'class' => 'btn btn-primary mr-2'
]);

echo html_writer::link(
    new moodle_url('/local/customerintel/logs.php'),
    get_string('clearfilters', 'local_customerintel'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_tag('form');

// Get logs
$perpage = 100;
$offset = $page * $perpage;

if ($runid > 0) {
    $logs = log_service::get_logs($runid, $level ?: null, $perpage);
    $totalcount = $DB->count_records('local_ci_log', array_filter(['runid' => $runid, 'level' => $level ?: null]));
} else {
    $conditions = [];
    if ($level) {
        $conditions['level'] = $level;
    }
    $logs = $DB->get_records('local_ci_log', $conditions, 'timecreated DESC', '*', $offset, $perpage);
    $totalcount = $DB->count_records('local_ci_log', $conditions);
}

// Display logs table
if ($logs) {
    $table = new html_table();
    $table->head = [
        get_string('logid', 'local_customerintel'),
        get_string('runid', 'local_customerintel'),
        get_string('level', 'local_customerintel'),
        get_string('message', 'local_customerintel'),
        get_string('timestamp', 'local_customerintel')
    ];
    $table->attributes['class'] = 'generaltable';
    $table->colclasses = ['text-center', 'text-center', 'text-center', '', 'text-nowrap'];
    
    foreach ($logs as $log) {
        $levelclass = '';
        switch($log->level) {
            case 'error':
                $levelclass = 'badge badge-danger';
                break;
            case 'warning':
                $levelclass = 'badge badge-warning';
                break;
            case 'info':
                $levelclass = 'badge badge-info';
                break;
            case 'debug':
                $levelclass = 'badge badge-secondary';
                break;
            default:
                $levelclass = 'badge badge-light';
        }
        
        $runlink = $log->runid ? html_writer::link(
            new moodle_url('/local/customerintel/logs.php', ['runid' => $log->runid]),
            $log->runid
        ) : '-';
        
        $table->data[] = [
            $log->id,
            $runlink,
            html_writer::tag('span', $log->level, ['class' => $levelclass]),
            html_writer::tag('pre', s($log->message), ['class' => 'log-message', 'style' => 'white-space: pre-wrap; word-wrap: break-word; max-width: 600px;']),
            userdate($log->timecreated, '%Y-%m-%d %H:%M:%S')
        ];
    }
    
    echo html_writer::table($table);
    
    // Pagination
    if ($totalcount > $perpage) {
        echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);
    }
    
    // Summary
    echo html_writer::start_div('alert alert-info mt-3');
    echo html_writer::tag('strong', get_string('logsummary', 'local_customerintel') . ': ');
    echo get_string('showingxofy', 'local_customerintel', [
        'showing' => min($perpage, count($logs)),
        'total' => $totalcount
    ]);
    echo html_writer::end_div();
    
} else {
    echo html_writer::div(
        get_string('nologsfound', 'local_customerintel'),
        'alert alert-warning'
    );
}

// Add styles
echo html_writer::tag('style', '
    .log-message {
        font-family: monospace;
        font-size: 0.9em;
        margin: 0;
        padding: 4px;
        background: #f5f5f5;
        border-radius: 3px;
    }
    .generaltable td {
        vertical-align: top;
    }
');

// Output footer
echo $OUTPUT->footer();