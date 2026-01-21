<?php
/**
 * CustomerIntel company view page
 *
 * @package    local_customerintel
 * @copyright  2025 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/customerintel/lib.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/customerintel:view', $context);

$PAGE->set_url('/local/customerintel/view.php', array('id' => $id));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_customerintel'));
$PAGE->set_heading(get_string('pluginname', 'local_customerintel'));

echo $OUTPUT->header();

// Main content would go here
echo html_writer::tag('h2', 'Company View');
echo html_writer::tag('p', 'Company details and analysis results would be displayed here.');

echo $OUTPUT->footer();