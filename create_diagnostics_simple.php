<?php
/**
 * Simple script to create local_ci_diagnostics table (no foreign key)
 * Run this via browser: /local/customerintel/create_diagnostics_simple.php
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customerintel/create_diagnostics_simple.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Create Diagnostics Table');

echo $OUTPUT->header();
echo $OUTPUT->heading('Create local_ci_diagnostics Table (Simple Version)');

$dbman = $DB->get_manager();
$table = new xmldb_table('local_ci_diagnostics');

if ($dbman->table_exists($table)) {
    echo $OUTPUT->notification('Table local_ci_diagnostics already exists!', 'notifysuccess');
} else {
    echo html_writer::tag('p', 'Creating table local_ci_diagnostics (without foreign key)...');

    try {
        // Define fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('metric', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('severity', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'info');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Define only primary key (skip foreign key to avoid conflicts)
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Define indexes
        $table->add_index('idx_runid', XMLDB_INDEX_NOTUNIQUE, ['runid']);
        $table->add_index('idx_severity', XMLDB_INDEX_NOTUNIQUE, ['severity']);

        // Create table
        $dbman->create_table($table);

        echo $OUTPUT->notification('Table local_ci_diagnostics created successfully!', 'notifysuccess');
        echo html_writer::tag('p', 'Foreign key constraint skipped - table will work fine without it.');
        echo html_writer::tag('p', 'You can now delete this file: create_diagnostics_simple.php');

    } catch (Exception $e) {
        echo $OUTPUT->notification('Error: ' . $e->getMessage(), 'notifyproblem');
    }
}

echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/customerintel/cache_decision.php', ['companyid' => 1]),
    'Test cache_decision.php'
));

echo $OUTPUT->footer();
