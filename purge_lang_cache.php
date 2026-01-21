<?php
/**
 * Purge Moodle language cache
 * Run this via browser: /local/customerintel/purge_lang_cache.php
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customerintel/purge_lang_cache.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Purge Language Cache');

echo $OUTPUT->header();
echo $OUTPUT->heading('Purge Language String Cache');

try {
    // Purge all caches
    purge_all_caches();

    echo $OUTPUT->notification('All caches purged successfully!', 'notifysuccess');
    echo html_writer::tag('p', 'Language strings should now reload from the file.');
    echo html_writer::tag('p', 'You can delete this file: purge_lang_cache.php');

} catch (Exception $e) {
    echo $OUTPUT->notification('Error: ' . $e->getMessage(), 'notifyproblem');
}

echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/customerintel/cache_decision.php', ['companyid' => 1]),
    'Test cache_decision.php again'
));

echo $OUTPUT->footer();
