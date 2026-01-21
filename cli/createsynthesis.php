<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
global $DB;

$table = new xmldb_table('local_ci_synthesis');

if (!$DB->get_manager()->table_exists($table)) {
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('jsonpayload', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('runid_idx', XMLDB_INDEX_NOTUNIQUE, ['runid']);

    $DB->get_manager()->create_table($table);
    echo "✅ Table local_ci_synthesis created successfully.";
} else {
    echo "ℹ️ Table local_ci_synthesis already exists.";
}