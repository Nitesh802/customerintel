<?php
/**
 * Anomaly detected event for Customer Intelligence Dashboard (Slice 11)
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Anomaly detected event class
 * 
 * Triggered when the predictive engine detects an anomaly in metric data
 */
class anomaly_detected extends \core\event\base {
    
    /**
     * Initialize the event
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_ci_telemetry';
    }
    
    /**
     * Returns the event name
     * 
     * @return string
     */
    public static function get_name() {
        return get_string('event_anomaly_detected', 'local_customerintel');
    }
    
    /**
     * Returns the event description
     * 
     * @return string
     */
    public function get_description() {
        $metric = $this->other['metric'] ?? 'unknown';
        $severity = $this->other['severity'] ?? 'unknown';
        $z_score = $this->other['z_score'] ?? 0;
        
        return "Anomaly detected in metric '{$metric}' with severity '{$severity}' (z-score: {$z_score}) for run {$this->objectid}";
    }
    
    /**
     * Returns the relevant URL
     * 
     * @return \moodle_url
     */
    public function get_url() {
        if ($this->objectid) {
            return new \moodle_url('/local/customerintel/view_report.php', ['runid' => $this->objectid]);
        }
        return new \moodle_url('/local/customerintel/analytics.php', ['tab' => 'predictive']);
    }
    
    /**
     * Custom validation of this event
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        
        if (!isset($this->other['metric'])) {
            throw new \coding_exception('The \'metric\' value must be set in other.');
        }
        
        if (!isset($this->other['z_score'])) {
            throw new \coding_exception('The \'z_score\' value must be set in other.');
        }
    }
    
    /**
     * Create instance of event
     *
     * @param int $runid Run ID where anomaly was detected (0 for system-wide)
     * @param string $metric Metric key where anomaly was detected
     * @param float $z_score Z-score of the anomaly
     * @param string $severity Severity level of the anomaly
     * @param array $additional_data Additional anomaly data
     * @return anomaly_detected
     */
    public static function create_from_anomaly($runid, $metric, $z_score, $severity, $additional_data = []) {
        global $USER;
        
        $event_data = [
            'objectid' => $runid,
            'context' => \context_system::instance(),
            'userid' => $USER->id ?? 0,
            'other' => array_merge([
                'metric' => $metric,
                'z_score' => $z_score,
                'severity' => $severity,
                'detection_time' => time()
            ], $additional_data)
        ];
        
        return self::create($event_data);
    }
}