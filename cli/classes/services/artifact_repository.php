<?php
/**
 * Artifact Repository for Transparent Pipeline View System
 * 
 * Responsible for saving and retrieving JSON artifacts linked to runids
 * for transparent pipeline tracing and debugging.
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Artifact Repository - Pipeline Artifact Storage
 * 
 * Manages JSON artifacts from synthesis pipeline phases
 * Stores to local_ci_artifact table with automatic payload truncation
 */
class artifact_repository {
    
    /**
     * @var \moodle_database Database connection
     */
    private $db;
    
    /**
     * @var int Maximum JSON payload size in characters (2MB)
     */
    const MAX_PAYLOAD_SIZE = 2097152;
    
    /**
     * @var int Truncated payload warning size (1.8MB)
     */
    const PAYLOAD_WARNING_SIZE = 1887437;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
    }
    
    /**
     * Save an artifact for a given run and phase
     * 
     * @param int $runid Run identifier
     * @param string $phase Phase name (discovery|nb_orchestration|assembler|synthesis|qa)
     * @param string $artifacttype Artifact type identifier
     * @param mixed $data Data to be JSON encoded and stored
     * @return bool Success status
     */
    public function save_artifact($runid, $phase, $artifacttype, $data) {
        try {
            // Check if trace mode is enabled
            if (!$this->is_trace_mode_enabled()) {
                debugging("Artifact save skipped: trace mode disabled", DEBUG_DEVELOPER);
                return true;
            }
            
            // Encode data to JSON
            $jsondata = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            if ($jsondata === false) {
                debugging("Failed to encode artifact data: " . json_last_error_msg(), DEBUG_NORMAL);
                return false;
            }
            
            // Check payload size and truncate if necessary
            $original_size = strlen($jsondata);
            if ($original_size > self::MAX_PAYLOAD_SIZE) {
                $jsondata = substr($jsondata, 0, self::MAX_PAYLOAD_SIZE - 100) . '...[TRUNCATED]';
                debugging("Artifact payload truncated from {$original_size} to " . strlen($jsondata) . " characters", DEBUG_NORMAL);
            } elseif ($original_size > self::PAYLOAD_WARNING_SIZE) {
                debugging("Large artifact payload: {$original_size} characters for phase {$phase}", DEBUG_DEVELOPER);
            }
            
            // Create record
            $record = new \stdClass();
            $record->runid = $runid;
            $record->phase = $phase;
            $record->artifacttype = $artifacttype;
            $record->jsondata = $jsondata;
            $record->timecreated = time();
            $record->timemodified = time();
            
            // Delete existing artifact for this run/phase/type combination
            $this->db->delete_records('local_ci_artifact', [
                'runid' => $runid,
                'phase' => $phase,
                'artifacttype' => $artifacttype
            ]);
            
            // Insert new record
            $id = $this->db->insert_record('local_ci_artifact', $record);
            
            debugging("Artifact saved: runid={$runid}, phase={$phase}, type={$artifacttype}, size=" . strlen($jsondata), DEBUG_DEVELOPER);
            
            return $id !== false;
            
        } catch (\Exception $e) {
            // Fail silently to avoid breaking synthesis pipeline
            debugging("Artifact save failed: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Retrieve all artifacts for a given runid
     * 
     * @param int $runid Run identifier
     * @return array Array of artifact records
     */
    public function get_artifacts_for_run($runid) {
        try {
            return $this->db->get_records('local_ci_artifact', 
                ['runid' => $runid], 
                'phase ASC, artifacttype ASC, timecreated ASC'
            );
        } catch (\Exception $e) {
            debugging("Failed to retrieve artifacts for run {$runid}: " . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Retrieve a specific artifact for a run and phase
     * 
     * @param int $runid Run identifier
     * @param string $phase Phase name
     * @param string $artifacttype Artifact type (optional)
     * @return \stdClass|null Artifact record or null if not found
     */
    public function get_artifact($runid, $phase, $artifacttype = null) {
        try {
            $conditions = [
                'runid' => $runid,
                'phase' => $phase
            ];
            
            if ($artifacttype !== null) {
                $conditions['artifacttype'] = $artifacttype;
            }
            
            return $this->db->get_record('local_ci_artifact', $conditions);
            
        } catch (\Exception $e) {
            debugging("Failed to retrieve artifact: " . $e->getMessage(), DEBUG_NORMAL);
            return null;
        }
    }
    
    /**
     * Get artifact statistics for a run
     * 
     * @param int $runid Run identifier
     * @return array Statistics including count, total size, phase breakdown
     */
    public function get_artifact_stats($runid) {
        try {
            $artifacts = $this->get_artifacts_for_run($runid);
            
            $stats = [
                'total_count' => 0,
                'total_size' => 0,
                'phases' => [],
                'types' => [],
                'timespan' => []
            ];
            
            $earliest = null;
            $latest = null;
            
            foreach ($artifacts as $artifact) {
                $stats['total_count']++;
                $stats['total_size'] += strlen($artifact->jsondata);
                
                // Phase breakdown
                if (!isset($stats['phases'][$artifact->phase])) {
                    $stats['phases'][$artifact->phase] = 0;
                }
                $stats['phases'][$artifact->phase]++;
                
                // Type breakdown
                if (!isset($stats['types'][$artifact->artifacttype])) {
                    $stats['types'][$artifact->artifacttype] = 0;
                }
                $stats['types'][$artifact->artifacttype]++;
                
                // Time tracking
                if ($earliest === null || $artifact->timecreated < $earliest) {
                    $earliest = $artifact->timecreated;
                }
                if ($latest === null || $artifact->timecreated > $latest) {
                    $latest = $artifact->timecreated;
                }
            }
            
            if ($earliest !== null && $latest !== null) {
                $stats['timespan'] = [
                    'earliest' => $earliest,
                    'latest' => $latest,
                    'duration_seconds' => $latest - $earliest
                ];
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            debugging("Failed to calculate artifact stats: " . $e->getMessage(), DEBUG_NORMAL);
            return [
                'total_count' => 0,
                'total_size' => 0,
                'phases' => [],
                'types' => [],
                'timespan' => []
            ];
        }
    }
    
    /**
     * Delete all artifacts for a run
     * 
     * @param int $runid Run identifier
     * @return bool Success status
     */
    public function delete_artifacts_for_run($runid) {
        try {
            return $this->db->delete_records('local_ci_artifact', ['runid' => $runid]);
        } catch (\Exception $e) {
            debugging("Failed to delete artifacts for run {$runid}: " . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
    
    /**
     * Clean up old artifacts
     * 
     * @param int $days_to_keep Number of days to retain artifacts
     * @return int Number of records deleted
     */
    public function cleanup_old_artifacts($days_to_keep = 30) {
        try {
            $cutoff_time = time() - ($days_to_keep * 86400);
            return $this->db->delete_records_select(
                'local_ci_artifact',
                'timecreated < ?',
                [$cutoff_time]
            );
        } catch (\Exception $e) {
            debugging("Artifact cleanup failed: " . $e->getMessage(), DEBUG_NORMAL);
            return 0;
        }
    }
    
    /**
     * Check if trace mode is enabled
     * 
     * @return bool True if trace mode is enabled
     */
    private function is_trace_mode_enabled() {
        return get_config('local_customerintel', 'enable_trace_mode') === '1';
    }
    
    /**
     * Get human-readable size
     * 
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    public static function format_size($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Decode artifact JSON data safely
     * 
     * @param string $jsondata JSON string
     * @return mixed Decoded data or null on error
     */
    public static function decode_artifact_data($jsondata) {
        try {
            $data = json_decode($jsondata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging("Failed to decode artifact JSON: " . json_last_error_msg(), DEBUG_NORMAL);
                return null;
            }
            return $data;
        } catch (\Exception $e) {
            debugging("Exception decoding artifact JSON: " . $e->getMessage(), DEBUG_NORMAL);
            return null;
        }
    }
}