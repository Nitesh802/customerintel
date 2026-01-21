<?php
/**
 * Citation Resolver Exception for Customer Intelligence Dashboard
 * 
 * Thrown when citation lookup or resolution encounters errors
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\exceptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Exception thrown during citation resolution failures
 * 
 * Provides structured error information for citation debugging
 */
class CitationResolverException extends \moodle_exception {
    
    /**
     * @var string The citation URL that failed
     */
    private $citation_url;
    
    /**
     * @var int The run ID associated with this error
     */
    private $runid;
    
    /**
     * @var string The resolution step where error occurred
     */
    private $resolution_step;
    
    /**
     * @var array Additional context data
     */
    private $context_data;
    
    /**
     * Constructor
     * 
     * @param string $citation_url The problematic citation URL
     * @param int $runid The run ID
     * @param string $resolution_step The step where error occurred
     * @param string $message Error message
     * @param array $context_data Additional context
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $citation_url, int $runid, string $resolution_step, string $message, array $context_data = [], \Throwable $previous = null) {
        $this->citation_url = $citation_url;
        $this->runid = $runid;
        $this->resolution_step = $resolution_step;
        $this->context_data = $context_data;
        
        $errorcode = 'citation_resolver_failed';
        $module = 'local_customerintel';
        
        $a = [
            'url' => $citation_url,
            'runid' => $runid,
            'step' => $resolution_step,
            'message' => $message,
            'context' => json_encode($context_data)
        ];
        
        parent::__construct($errorcode, $module, '', $a, $message, $previous);
    }
    
    /**
     * Get the citation URL that failed
     * 
     * @return string
     */
    public function get_citation_url(): string {
        return $this->citation_url;
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
     * Get the resolution step where error occurred
     * 
     * @return string
     */
    public function get_resolution_step(): string {
        return $this->resolution_step;
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
            'error_type' => 'citation_resolver_failure',
            'citation_url' => $this->citation_url,
            'runid' => $this->runid,
            'resolution_step' => $this->resolution_step,
            'message' => $this->getMessage(),
            'context' => $this->context_data,
            'trace_excerpt' => substr($this->getTraceAsString(), 0, 500),
            'timestamp' => time()
        ];
    }
}