<?php
/**
 * Synthesis Phase Exception for Customer Intelligence Dashboard
 * 
 * Thrown when synthesis phases encounter critical errors
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\exceptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Exception thrown during synthesis phase failures
 * 
 * Provides structured error information for debugging and recovery
 */
class SynthesisPhaseException extends \moodle_exception {
    
    /**
     * @var string The synthesis phase where the error occurred
     */
    private $phase;
    
    /**
     * @var int The run ID associated with this error
     */
    private $runid;
    
    /**
     * @var array Additional context data
     */
    private $context_data;
    
    /**
     * Constructor
     * 
     * @param string $phase The synthesis phase
     * @param int $runid The run ID
     * @param string $message Error message
     * @param array $context_data Additional context
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $phase, int $runid, string $message, array $context_data = [], \Throwable $previous = null) {
        $this->phase = $phase;
        $this->runid = $runid;
        $this->context_data = $context_data;
        
        $errorcode = 'synthesis_phase_failed';
        $module = 'local_customerintel';
        
        $a = [
            'phase' => $phase,
            'runid' => $runid,
            'message' => $message,
            'context' => json_encode($context_data)
        ];
        
        parent::__construct($errorcode, $module, '', $a, $message, $previous);
    }
    
    /**
     * Get the synthesis phase where error occurred
     * 
     * @return string
     */
    public function get_phase(): string {
        return $this->phase;
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
            'error_type' => 'synthesis_phase_failure',
            'phase' => $this->phase,
            'runid' => $this->runid,
            'message' => $this->getMessage(),
            'context' => $this->context_data,
            'trace_excerpt' => substr($this->getTraceAsString(), 0, 500),
            'timestamp' => time()
        ];
    }
}