<?php
/**
 * CustomerIntel main entry point
 *
 * @package    local_customerintel
 * @copyright  2025 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/customerintel/lib.php');

// Redirect to dashboard
redirect(new moodle_url('/local/customerintel/dashboard.php'));