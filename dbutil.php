<?php
/**
 * Database utility class for CustomerIntel
 *
 * @package    local_customerintel
 * @copyright  2024 Your Company
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Database utility helper
 */
class dbutil {
    
    /**
     * Get properly formatted table name
     * 
     * @param string $short Short table name without prefix
     * @return string Full table name with brackets
     */
    public static function table($short) {
        return '{local_ci_' . $short . '}';
    }
    
    /**
     * Get raw table name without brackets
     * 
     * @param string $short Short table name without prefix
     * @return string Full table name without brackets
     */
    public static function raw_table($short) {
        return 'local_ci_' . $short;
    }
}