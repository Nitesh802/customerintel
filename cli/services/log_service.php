<?php
/**
 * Log Service for Customer Intelligence Dashboard
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Log service class for persistent logging of task executions
 */
class log_service {
    
    /**
     * Log an info message
     * 
     * @param int|null $runid The run ID associated with this log entry
     * @param string $message The message to log
     * @return void
     */
    public static function info($runid, $message) {
        self::write($runid, 'info', $message);
    }
    
    /**
     * Log a warning message
     * 
     * @param int|null $runid The run ID associated with this log entry
     * @param string $message The message to log
     * @return void
     */
    public static function warning($runid, $message) {
        self::write($runid, 'warning', $message);
    }
    
    /**
     * Log an error message
     * 
     * @param int|null $runid The run ID associated with this log entry
     * @param string $message The message to log
     * @return void
     */
    public static function error($runid, $message) {
        self::write($runid, 'error', $message);
    }
    
    /**
     * Log a debug message
     * 
     * @param int|null $runid The run ID associated with this log entry
     * @param string $message The message to log
     * @return void
     */
    public static function debug($runid, $message) {
        self::write($runid, 'debug', $message);
    }
    
    /**
     * Write a log entry to the database
     * 
     * @param int|null $runid The run ID associated with this log entry
     * @param string $level The log level (info, warning, error, debug)
     * @param string $message The message to log
     * @return void
     */
    private static function write($runid, $level, $message) {
        global $DB;
        
        try {
            $record = new \stdClass();
            $record->runid = $runid;
            $record->level = $level;
            $record->message = substr($message, 0, 65535); // Limit message length
            $record->timecreated = time();
            
            $DB->insert_record('local_ci_log', $record);
            
            // Also output to mtrace for real-time monitoring
            mtrace("[{$level}] Run {$runid}: {$message}");
        } catch (\Exception $e) {
            // If logging fails, at least output to mtrace
            mtrace("Failed to write log: " . $e->getMessage());
        }
    }
    
    /**
     * Get logs for a specific run
     * 
     * @param int $runid The run ID
     * @param string|null $level Optional level filter
     * @param int $limit Maximum number of logs to return
     * @return array Array of log records
     */
    public static function get_logs($runid, $level = null, $limit = 100) {
        global $DB;
        
        $conditions = ['runid' => $runid];
        if ($level !== null) {
            $conditions['level'] = $level;
        }
        
        return $DB->get_records('local_ci_log', $conditions, 'timecreated DESC', '*', 0, $limit);
    }
    
    /**
     * Get all recent logs
     * 
     * @param int $limit Maximum number of logs to return
     * @param string|null $level Optional level filter
     * @return array Array of log records
     */
    public static function get_recent_logs($limit = 100, $level = null) {
        global $DB;
        
        $conditions = [];
        if ($level !== null) {
            $conditions['level'] = $level;
        }
        
        return $DB->get_records('local_ci_log', $conditions, 'timecreated DESC', '*', 0, $limit);
    }
    
    /**
     * Clean old logs (optional maintenance function)
     * 
     * @param int $daystokeep Number of days to keep logs
     * @return int Number of deleted records
     */
    public static function clean_old_logs($daystokeep = 30) {
        global $DB;
        
        $cutoff = time() - ($daystokeep * 86400);
        return $DB->delete_records_select('local_ci_log', 'timecreated < ?', [$cutoff]);
    }
}