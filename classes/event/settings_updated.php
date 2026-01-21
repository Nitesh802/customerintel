<?php
/**
 * Settings Updated Event
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Settings updated event class
 * 
 * Triggered when admin settings are updated
 */
class settings_updated extends \core\event\base {
    
    /**
     * Init method
     */
    protected function init() {
        $this->data['crud'] = 'u'; // Update operation
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context = \context_system::instance();
    }
    
    /**
     * Return localised event name
     * 
     * @return string
     */
    public static function get_name() {
        return get_string('eventsettingsupdated', 'local_customerintel');
    }
    
    /**
     * Returns description of what happened
     * 
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' updated the Customer Intelligence plugin settings.";
    }
    
    /**
     * Get URL related to the action
     * 
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/customerintel/admin_settings.php');
    }
    
    /**
     * Custom validation
     * 
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        
        if ($this->context->contextlevel != CONTEXT_SYSTEM) {
            throw new \coding_exception('Context must be system context.');
        }
    }
}