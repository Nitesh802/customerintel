<?php
/**
 * CustomerIntel library functions
 *
 * @package    local_customerintel
 * @copyright  2025 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the plugin's name
 *
 * @return string
 */
function local_customerintel_get_name() {
    return get_string('pluginname', 'local_customerintel');
}

/**
 * Extend navigation
 *
 * @param global_navigation $navigation
 */
function local_customerintel_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!has_capability('local/customerintel:view', context_system::instance())) {
        return;
    }

    $node = navigation_node::create(
        get_string('pluginname', 'local_customerintel'),
        new moodle_url('/local/customerintel/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'customerintel',
        new pix_icon('i/report', '')
    );

    $navigation->add_node($node);
}

/**
 * Add plugin to settings navigation
 *
 * @param settings_navigation $navigation
 * @param context $context
 */
function local_customerintel_extend_settings_navigation(settings_navigation $navigation, context $context) {
    global $PAGE;

    if (!has_capability('local/customerintel:manage', context_system::instance())) {
        return;
    }

    if ($settingsnode = $navigation->find('root', navigation_node::TYPE_SITE_ADMIN)) {
        $node = navigation_node::create(
            get_string('pluginname', 'local_customerintel'),
            new moodle_url('/local/customerintel/admin_settings.php'),
            navigation_node::TYPE_SETTING,
            null,
            'customerintel_settings',
            new pix_icon('i/settings', '')
        );
        
        $settingsnode->add_node($node);
    }
}

/**
 * Get list of capabilities
 *
 * @return array
 */
function local_customerintel_get_capabilities() {
    return array(
        'local/customerintel:view',
        'local/customerintel:manage',
        'local/customerintel:export'
    );
}