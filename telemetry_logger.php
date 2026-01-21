<?php
/**
 * Telemetry Logger for Observability & Logging Enhancement (Slice 7)
 * 
 * Provides comprehensive metrics logging for synthesis pipeline observability
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Telemetry Logger - Metrics and Phase Tracking
 * 
 * Logs metrics, phase transitions, and performance data to local_ci_telemetry
 */
class telemetry_logger {
    
    /**
     * @var bool Feature flag for detailed telemetry
     */
    private $detailed_telemetry_enabled;
    
    /**
     * @var \moodle_database Database connection
     */
    private $db;
    
    /**
     * @var array Active phase tracking
     */
    private $active_phases = [];
    
    /**
     * @var int Maximum payload size in characters
     */
    const MAX_PAYLOAD_SIZE = 2000;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
        
        // Check feature flag
        $this->detailed_telemetry_enabled = get_config('local_customerintel', 'enable_detailed_telemetry');
    }
    
    /**
     * Log a metric to the telemetry table
     * 
     * @param int $runid Run identifier
     * @param string $metrickey Metric key/name
     * @param float|null $metricvaluenum Numeric value (optional)
     * @param mixed|null $payload Additional data (will be JSON encoded)
     * @return bool Success status
     */
    public function log_metric($runid, $metrickey, $metricvaluenum = null, $payload = null) {
        try {
            // Skip if detailed telemetry is explicitly disabled
            if ($this->detailed_telemetry_enabled === '0') {
                return true;
            }
            
            $record = new \stdClass();
            $record->runid = $runid;
            $record->metrickey = $metrickey;
            $record->timecreated = time();
            
            // Handle numeric value
            if ($metricvaluenum !== null) {
                $record->metricvaluenum = (float)$metricvaluenum;
            }
            
            // Handle payload
            if ($payload !== null) {
                $json_payload = $this->prepare_payload($payload);
                if ($json_payload !== null) {
                    $record->payload = $json_payload;
                }
            }
            
            // Insert into database
            $this->db->insert_record('local_ci_telemetry', $record);
            
            // Debug logging
            debugging("Telemetry logged: {$metrickey} = " . 
                     ($metricvaluenum ?? 'null') . " for runid {$runid}", DEBUG_DEVELOPER);
            
            return true;
            
        } catch (\Exception $e) {
            // Fail silently with warning
            debugging("Telemetry logging failed: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Log the start of a phase
     * 
     * @param int $runid Run identifier
     * @param string $phase Phase name
     * @return bool Success status
     */
    public function log_phase_start($runid, $phase) {
        try {
            // Store start time for duration calculation
            $this->active_phases[$runid][$phase] = microtime(true) * 1000; // Convert to milliseconds
            
            // Log the phase start event
            return $this->log_metric(
                $runid,
                "phase_start_{$phase}",
                null,
                ['timestamp' => time(), 'phase' => $phase]
            );
            
        } catch (\Exception $e) {
            debugging("Phase start logging failed: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Log the end of a phase with duration
     * 
     * @param int $runid Run identifier
     * @param string $phase Phase name
     * @param float|null $duration_ms Duration in milliseconds (auto-calculated if null)
     * @return bool Success status
     */
    public function log_phase_end($runid, $phase, $duration_ms = null) {
        try {
            // Calculate duration if not provided
            if ($duration_ms === null && isset($this->active_phases[$runid][$phase])) {
                $start_time = $this->active_phases[$runid][$phase];
                $duration_ms = (microtime(true) * 1000) - $start_time;
                
                // Clean up tracking
                unset($this->active_phases[$runid][$phase]);
            }
            
            // Log the phase end with duration
            return $this->log_metric(
                $runid,
                "phase_duration_{$phase}",
                $duration_ms,
                [
                    'timestamp' => time(),
                    'phase' => $phase,
                    'duration_ms' => $duration_ms
                ]
            );
            
        } catch (\Exception $e) {
            debugging("Phase end logging failed: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Log section-specific QA scores
     * 
     * @param int $runid Run identifier
     * @param string $section Section name
     * @param array $scores QA scores array
     * @return bool Success status
     */
    public function log_section_qa($runid, $section, array $scores) {
        try {
            // Log individual score components
            if (isset($scores['coherence'])) {
                $this->log_metric(
                    $runid,
                    "qa_coherence_{$section}",
                    $scores['coherence'],
                    ['section' => $section, 'type' => 'coherence']
                );
            }
            
            if (isset($scores['pattern_alignment'])) {
                $this->log_metric(
                    $runid,
                    "qa_pattern_{$section}",
                    $scores['pattern_alignment'],
                    ['section' => $section, 'type' => 'pattern_alignment']
                );
            }
            
            // Log overall section score if available
            if (isset($scores['total'])) {
                $this->log_metric(
                    $runid,
                    "qa_total_{$section}",
                    $scores['total'],
                    ['section' => $section, 'scores' => $scores]
                );
            }
            
            return true;
            
        } catch (\Exception $e) {
            debugging("Section QA logging failed: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Log aggregate metrics
     * 
     * @param int $runid Run identifier
     * @param array $metrics Array of metric key-value pairs
     * @return bool Success status
     */
    public function log_aggregate_metrics($runid, array $metrics) {
        try {
            foreach ($metrics as $key => $value) {
                $this->log_metric(
                    $runid,
                    "aggregate_{$key}",
                    is_numeric($value) ? $value : null,
                    is_numeric($value) ? null : $value
                );
            }
            return true;
            
        } catch (\Exception $e) {
            debugging("Aggregate metrics logging failed: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Prepare payload for storage
     * 
     * @param mixed $payload Data to encode
     * @return string|null JSON encoded payload or null if encoding fails
     */
    private function prepare_payload($payload) {
        try {
            // Convert to JSON
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            if ($json === false) {
                debugging("Failed to encode payload: " . json_last_error_msg(), DEBUG_NORMAL);
                return null;
            }
            
            // Truncate if too long
            if (strlen($json) > self::MAX_PAYLOAD_SIZE) {
                $json = substr($json, 0, self::MAX_PAYLOAD_SIZE - 3) . '...';
            }
            
            return $json;
            
        } catch (\Exception $e) {
            debugging("Payload preparation failed: " . $e->getMessage(), DEBUG_NORMAL);
            return null;
        }
    }
    
    /**
     * Start tracking a phase (convenience method)
     * 
     * @param int $runid Run identifier
     * @param string $phase Phase name
     * @return bool Success status
     */
    public function start_phase($runid, $phase) {
        return $this->log_phase_start($runid, $phase);
    }
    
    /**
     * End tracking a phase (convenience method)
     * 
     * @param int $runid Run identifier
     * @param string $phase Phase name
     * @return bool Success status
     */
    public function end_phase($runid, $phase) {
        return $this->log_phase_end($runid, $phase);
    }
    
    /**
     * Check if detailed telemetry is enabled
     * 
     * @return bool
     */
    public function is_detailed_telemetry_enabled() {
        return $this->detailed_telemetry_enabled !== '0';
    }
    
    /**
     * Log lightweight phase summary (always enabled, regardless of detailed telemetry setting)
     * 
     * @param int $runid Run identifier
     * @param string $phase Phase name
     * @param array $summary Summary data (e.g., counts, sizes, durations)
     * @return bool Success status
     */
    public function log_phase_summary($runid, $phase, array $summary) {
        try {
            // Always log phase summaries regardless of detailed telemetry setting
            // for quick visibility into pipeline phases
            
            return $this->log_metric(
                $runid,
                "phase_summary_{$phase}",
                null, // No numeric value for summary
                [
                    'phase' => $phase,
                    'summary' => $summary,
                    'timestamp' => time(),
                    'type' => 'phase_summary'
                ]
            );
            
        } catch (\Exception $e) {
            debugging("Phase summary logging failed: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Log discovery phase summary
     * 
     * @param int $runid Run identifier
     * @param int $source_count Number of sources discovered
     * @param int $pattern_count Number of patterns detected
     * @param array $additional_data Additional discovery metrics
     * @return bool Success status
     */
    public function log_discovery_summary($runid, $source_count = 0, $pattern_count = 0, array $additional_data = []) {
        $summary = array_merge([
            'source_count' => $source_count,
            'pattern_count' => $pattern_count,
            'phase_type' => 'discovery'
        ], $additional_data);
        
        return $this->log_phase_summary($runid, 'discovery', $summary);
    }
    
    /**
     * Log NB orchestration phase summary
     * 
     * @param int $runid Run identifier
     * @param int $nb_count Number of NBs processed
     * @param int $normalized_fields Number of normalized data fields
     * @param array $additional_data Additional orchestration metrics
     * @return bool Success status
     */
    public function log_nb_orchestration_summary($runid, $nb_count = 0, $normalized_fields = 0, array $additional_data = []) {
        $summary = array_merge([
            'nb_count' => $nb_count,
            'normalized_fields' => $normalized_fields,
            'phase_type' => 'nb_orchestration'
        ], $additional_data);
        
        return $this->log_phase_summary($runid, 'nb_orchestration', $summary);
    }
    
    /**
     * Log assembler phase summary
     * 
     * @param int $runid Run identifier
     * @param int $section_count Number of sections assembled
     * @param int $total_content_size Total size of assembled content
     * @param array $additional_data Additional assembler metrics
     * @return bool Success status
     */
    public function log_assembler_summary($runid, $section_count = 0, $total_content_size = 0, array $additional_data = []) {
        $summary = array_merge([
            'section_count' => $section_count,
            'total_content_size' => $total_content_size,
            'phase_type' => 'assembler'
        ], $additional_data);
        
        return $this->log_phase_summary($runid, 'assembler', $summary);
    }
    
    /**
     * Log synthesis phase summary
     * 
     * @param int $runid Run identifier
     * @param int $section_count Number of sections synthesized
     * @param int $total_text_size Total size of synthesized text
     * @param array $additional_data Additional synthesis metrics
     * @return bool Success status
     */
    public function log_synthesis_summary($runid, $section_count = 0, $total_text_size = 0, array $additional_data = []) {
        $summary = array_merge([
            'section_count' => $section_count,
            'total_text_size' => $total_text_size,
            'phase_type' => 'synthesis'
        ], $additional_data);
        
        return $this->log_phase_summary($runid, 'synthesis', $summary);
    }
    
    /**
     * Log QA phase summary
     * 
     * @param int $runid Run identifier
     * @param float $overall_score Overall QA score
     * @param int $warning_count Number of QA warnings
     * @param array $additional_data Additional QA metrics
     * @return bool Success status
     */
    public function log_qa_summary($runid, $overall_score = 0.0, $warning_count = 0, array $additional_data = []) {
        $summary = array_merge([
            'overall_score' => $overall_score,
            'warning_count' => $warning_count,
            'phase_type' => 'qa'
        ], $additional_data);
        
        return $this->log_phase_summary($runid, 'qa', $summary);
    }
    
    /**
     * Get phase summaries for a run
     * 
     * @param int $runid Run identifier
     * @return array Array of phase summaries
     */
    public function get_phase_summaries($runid) {
        try {
            $summaries = $this->db->get_records_select(
                'local_ci_telemetry',
                'runid = ? AND metrickey LIKE ?',
                [$runid, 'phase_summary_%'],
                'timecreated ASC'
            );
            
            $result = [];
            foreach ($summaries as $summary) {
                $phase = str_replace('phase_summary_', '', $summary->metrickey);
                $payload = json_decode($summary->payload, true);
                
                if ($payload !== null && isset($payload['summary'])) {
                    $result[$phase] = $payload['summary'];
                    $result[$phase]['timestamp'] = $summary->timecreated;
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            debugging("Failed to retrieve phase summaries: " . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Clean up old telemetry data
     * 
     * @param int $days_to_keep Number of days to retain data
     * @return int Number of records deleted
     */
    public function cleanup_old_telemetry($days_to_keep = 30) {
        try {
            $cutoff_time = time() - ($days_to_keep * 86400);
            return $this->db->delete_records_select(
                'local_ci_telemetry',
                'timecreated < ?',
                [$cutoff_time]
            );
        } catch (\Exception $e) {
            debugging("Telemetry cleanup failed: " . $e->getMessage(), DEBUG_NORMAL);
            return 0;
        }
    }
}