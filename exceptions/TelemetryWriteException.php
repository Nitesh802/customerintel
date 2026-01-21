<?php
/**
 * Telemetry Write Exception for Customer Intelligence Dashboard
 * 
 * Thrown when telemetry insert operations fail
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\exceptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Exception thrown during telemetry write failures
 * 
 * Provides structured error information for telemetry debugging
 */
class TelemetryWriteException extends \moodle_exception {
    
    /**
     * @var string The metric key that failed to write
     */
    private $metric_key;
    
    /**
     * @var int The run ID associated with this error
     */
    private $runid;
    
    /**
     * @var mixed The metric value that failed
     */
    private $metric_value;
    
    /**
     * @var array Additional context data
     */
    private $context_data;
    
    /**
     * Constructor
     * 
     * @param string $metric_key The metric key that failed
     * @param int $runid The run ID
     * @param mixed $metric_value The value that failed to write
     * @param string $message Error message
     * @param array $context_data Additional context
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $metric_key, int $runid, $metric_value, string $message, array $context_data = [], \Throwable $previous = null) {
        $this->metric_key = $metric_key;
        $this->runid = $runid;
        $this->metric_value = $metric_value;
        $this->context_data = $context_data;
        
        $errorcode = 'telemetry_write_failed';
        $module = 'local_customerintel';
        
        $a = [
            'metric_key' => $metric_key,
            'runid' => $runid,
            'metric_value' => is_scalar($metric_value) ? $metric_value : json_encode($metric_value),
            'message' => $message,
            'context' => json_encode($context_data)
        ];
        
        parent::__construct($errorcode, $module, '', $a, $message, $previous);
    }
    
    /**
     * Get the metric key that failed
     * 
     * @return string
     */
    public function get_metric_key(): string {
        return $this->metric_key;
    }
    
    /**
     * Get the run ID
     * 
     * @return int
     */
    public function get_runid(): int {
        return $this->runid;
    }
    
    /**
     * Get the metric value that failed
     * 
     * @return mixed
     */
    public function get_metric_value() {
        return $this->metric_value;
    }
    
    /**
     * Get additional context data
     * 
     * @return array
     */
    public function get_context_data(): array {
        return $this->context_data;
    }
    
    /**
     * Get structured error data for logging
     * 
     * @return array
     */
    public function get_structured_data(): array {
        return [
            'error_type' => 'telemetry_write_failure',
            'metric_key' => $this->metric_key,
            'runid' => $this->runid,
            'metric_value' => $this->metric_value,
            'message' => $this->getMessage(),
            'context' => $this->context_data,
            'trace_excerpt' => substr($this->getTraceAsString(), 0, 500),
            'timestamp' => time()
        ];
    }
}