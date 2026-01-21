<?php
/**
 * Diagnostic Archive Service
 * 
 * Generates comprehensive diagnostic archives for completed runs including:
 * - Synthesis artifacts (normalized_inputs_v16, synthesis_inputs)
 * - Compatibility adapter logs
 * - Telemetry and artifact database records
 * - System configuration and cache data
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/log_service.php');
require_once(__DIR__ . '/artifact_compatibility_adapter.php');

/**
 * Service for generating diagnostic archives for troubleshooting
 */
class diagnostic_archive_service {
    
    /** @var \moodle_database */
    private $db;
    
    /** @var artifact_compatibility_adapter */
    private $adapter;
    
    public function __construct() {
        global $DB;
        $this->db = $DB;
        $this->adapter = new artifact_compatibility_adapter();
    }
    
    /**
     * Generate complete diagnostic archive for a run
     * 
     * @param int $runid Run ID to generate diagnostics for
     * @return array Result with success status and file info
     */
    public function generate_diagnostic_archive($runid) {
        global $CFG;
        
        $start_time = microtime(true);
        $timestamp = date('Y-m-d_H-i-s');
        
        try {
            // Validate run exists and is completed
            $run = $this->db->get_record('local_ci_run', ['id' => $runid]);
            if (!$run) {
                throw new \invalid_parameter_exception("Run ID {$runid} not found");
            }
            
            if ($run->status !== 'completed') {
                throw new \invalid_parameter_exception("Run ID {$runid} is not completed (status: {$run->status})");
            }
            
            // Create temporary directory for archive contents
            $temp_dir = $CFG->tempdir . '/customerintel_diagnostics_' . $runid . '_' . time();
            if (!mkdir($temp_dir, 0755, true)) {
                throw new \moodle_exception('Cannot create temporary directory: ' . $temp_dir);
            }
            
            $archive_contents = [];
            
            // 1. Collect synthesis artifacts
            $artifacts_data = $this->collect_synthesis_artifacts($runid, $temp_dir);
            $archive_contents['artifacts'] = $artifacts_data;
            
            // 2. Collect compatibility adapter logs
            $compatibility_logs = $this->collect_compatibility_logs($runid, $temp_dir);
            $archive_contents['compatibility_logs'] = $compatibility_logs;
            
            // 3. Collect telemetry data (last 200 entries)
            $telemetry_data = $this->collect_telemetry_data($runid, $temp_dir);
            $archive_contents['telemetry'] = $telemetry_data;
            
            // 4. Collect artifact repository data (last 200 entries)
            $artifact_repo_data = $this->collect_artifact_repository_data($runid, $temp_dir);
            $archive_contents['artifact_repository'] = $artifact_repo_data;
            
            // 5. Collect system configuration and cache
            $system_data = $this->collect_system_data($runid, $temp_dir);
            $archive_contents['system'] = $system_data;
            
            // 6. Generate archive manifest
            $manifest_data = $this->generate_manifest($runid, $archive_contents, $start_time);
            file_put_contents($temp_dir . '/MANIFEST.json', json_encode($manifest_data, JSON_PRETTY_PRINT));
            
            // 7. Create ZIP archive
            $zip_filename = "run_{$runid}_diagnostic_{$timestamp}.zip";
            $zip_path = $CFG->tempdir . '/' . $zip_filename;
            
            $zip = new \ZipArchive();
            if ($zip->open($zip_path, \ZipArchive::CREATE) !== TRUE) {
                throw new \moodle_exception('Cannot create ZIP archive: ' . $zip_path);
            }
            
            // Add all files to ZIP
            $this->add_directory_to_zip($zip, $temp_dir, '');
            $zip->close();
            
            // Clean up temporary directory
            $this->remove_directory($temp_dir);
            
            $generation_time = round((microtime(true) - $start_time) * 1000, 2);
            
            // Log diagnostic generation
            log_service::info($runid, 
                "[Diagnostics] Run {$runid} diagnostic archive generated at {$timestamp} (duration: {$generation_time}ms)");
            
            return [
                'success' => true,
                'filename' => $zip_filename,
                'filepath' => $zip_path,
                'filesize' => filesize($zip_path),
                'generation_time_ms' => $generation_time,
                'manifest' => $manifest_data
            ];
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($temp_dir) && is_dir($temp_dir)) {
                $this->remove_directory($temp_dir);
            }
            
            log_service::error($runid, 
                "[Diagnostics] Failed to generate diagnostic archive: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'generation_time_ms' => round((microtime(true) - $start_time) * 1000, 2)
            ];
        }
    }
    
    /**
     * Collect all synthesis artifacts for the run
     */
    private function collect_synthesis_artifacts($runid, $temp_dir) {
        $artifacts_dir = $temp_dir . '/artifacts';
        mkdir($artifacts_dir, 0755, true);
        
        $collected = [];
        
        // Try to collect normalized_inputs_v16 (physical artifact)
        $normalized_artifact = $this->db->get_record('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'citation_normalization',
            'artifacttype' => 'normalized_inputs_v16'
        ]);
        
        if ($normalized_artifact && !empty($normalized_artifact->jsondata)) {
            $filename = "normalized_inputs_v16_{$runid}.json";
            file_put_contents($artifacts_dir . '/' . $filename, $normalized_artifact->jsondata);
            $collected['normalized_inputs_v16'] = [
                'filename' => $filename,
                'size' => strlen($normalized_artifact->jsondata),
                'source' => 'database_artifact',
                'created' => date('Y-m-d H:i:s', $normalized_artifact->timecreated)
            ];
        }
        
        // Try to collect via compatibility adapter (logical artifact)
        $synthesis_inputs = $this->adapter->load_artifact($runid, 'synthesis_inputs');
        if ($synthesis_inputs) {
            $filename = "synthesis_inputs_{$runid}_via_adapter.json";
            file_put_contents($artifacts_dir . '/' . $filename, json_encode($synthesis_inputs, JSON_PRETTY_PRINT));
            $collected['synthesis_inputs_adapter'] = [
                'filename' => $filename,
                'size' => filesize($artifacts_dir . '/' . $filename),
                'source' => 'compatibility_adapter',
                'has_domain_analysis' => isset($synthesis_inputs['domain_analysis']),
                'citation_count' => count($synthesis_inputs['normalized_citations'] ?? [])
            ];
        }
        
        // Collect synthesis bundle
        $synthesis_bundle = $this->adapter->load_synthesis_bundle($runid);
        if ($synthesis_bundle) {
            $filename = "synthesis_bundle_{$runid}.json";
            file_put_contents($artifacts_dir . '/' . $filename, json_encode($synthesis_bundle, JSON_PRETTY_PRINT));
            $collected['synthesis_bundle'] = [
                'filename' => $filename,
                'size' => filesize($artifacts_dir . '/' . $filename),
                'source' => 'compatibility_adapter',
                'has_v15_structure' => isset($synthesis_bundle['v15_structure']),
                'field_count' => count($synthesis_bundle)
            ];
        }
        
        // Collect all other artifacts for the run
        $all_artifacts = $this->db->get_records('local_ci_artifact', ['runid' => $runid], 'phase ASC, artifacttype ASC');
        foreach ($all_artifacts as $artifact) {
            $filename = "artifact_{$artifact->phase}_{$artifact->artifacttype}_{$runid}.json";
            if (!empty($artifact->jsondata)) {
                file_put_contents($artifacts_dir . '/' . $filename, $artifact->jsondata);
                $collected["artifact_{$artifact->phase}_{$artifact->artifacttype}"] = [
                    'filename' => $filename,
                    'size' => strlen($artifact->jsondata),
                    'source' => 'database_artifact',
                    'phase' => $artifact->phase,
                    'type' => $artifact->artifacttype,
                    'created' => date('Y-m-d H:i:s', $artifact->timecreated)
                ];
            }
        }
        
        return $collected;
    }
    
    /**
     * Collect compatibility adapter logs for the run
     */
    private function collect_compatibility_logs($runid, $temp_dir) {
        $logs_dir = $temp_dir . '/logs';
        mkdir($logs_dir, 0755, true);
        
        // Get all log entries for this run that mention compatibility
        $compatibility_logs = $this->db->get_records_sql(
            "SELECT * FROM {local_ci_log} 
             WHERE runid = :runid 
             AND (message LIKE '%[Compatibility]%' OR message LIKE '%compatibility%' OR message LIKE '%adapter%')
             ORDER BY timecreated ASC",
            ['runid' => $runid]
        );
        
        $log_entries = [];
        foreach ($compatibility_logs as $log) {
            $log_entries[] = [
                'timestamp' => date('Y-m-d H:i:s', $log->timecreated),
                'level' => $log->level,
                'message' => $log->message,
                'context' => $log->context
            ];
        }
        
        $filename = "compatibility_logs_{$runid}.json";
        file_put_contents($logs_dir . '/' . $filename, json_encode($log_entries, JSON_PRETTY_PRINT));
        
        return [
            'filename' => $filename,
            'entry_count' => count($log_entries),
            'size' => filesize($logs_dir . '/' . $filename)
        ];
    }
    
    /**
     * Collect telemetry data (last 200 entries for the run)
     */
    private function collect_telemetry_data($runid, $temp_dir) {
        $telemetry_dir = $temp_dir . '/telemetry';
        mkdir($telemetry_dir, 0755, true);
        
        $telemetry_records = $this->db->get_records('local_ci_telemetry', 
            ['runid' => $runid], 'timecreated DESC', '*', 0, 200);
        
        $telemetry_data = [];
        foreach ($telemetry_records as $record) {
            $telemetry_data[] = [
                'timestamp' => date('Y-m-d H:i:s', $record->timecreated),
                'metric_key' => $record->metrickey,
                'metric_value' => $record->metricvaluenum,
                'payload' => $record->payload ? json_decode($record->payload, true) : null
            ];
        }
        
        $filename = "telemetry_{$runid}_last_200.json";
        file_put_contents($telemetry_dir . '/' . $filename, json_encode($telemetry_data, JSON_PRETTY_PRINT));
        
        return [
            'filename' => $filename,
            'record_count' => count($telemetry_data),
            'size' => filesize($telemetry_dir . '/' . $filename)
        ];
    }
    
    /**
     * Collect artifact repository data (last 200 entries for the run)
     */
    private function collect_artifact_repository_data($runid, $temp_dir) {
        $artifact_dir = $temp_dir . '/artifact_repository';
        mkdir($artifact_dir, 0755, true);
        
        $artifact_records = $this->db->get_records('local_ci_artifact', 
            ['runid' => $runid], 'timecreated DESC', 'id, runid, phase, artifacttype, timecreated, LENGTH(jsondata) as data_size', 0, 200);
        
        $artifact_data = [];
        foreach ($artifact_records as $record) {
            $artifact_data[] = [
                'id' => $record->id,
                'timestamp' => date('Y-m-d H:i:s', $record->timecreated),
                'phase' => $record->phase,
                'artifact_type' => $record->artifacttype,
                'data_size_bytes' => $record->data_size
            ];
        }
        
        $filename = "artifact_repository_{$runid}_last_200.json";
        file_put_contents($artifact_dir . '/' . $filename, json_encode($artifact_data, JSON_PRETTY_PRINT));
        
        return [
            'filename' => $filename,
            'record_count' => count($artifact_data),
            'size' => filesize($artifact_dir . '/' . $filename)
        ];
    }
    
    /**
     * Collect system configuration and cache data
     */
    private function collect_system_data($runid, $temp_dir) {
        $system_dir = $temp_dir . '/system';
        mkdir($system_dir, 0755, true);
        
        $collected = [];
        
        // Get run details
        $run = $this->db->get_record('local_ci_run', ['id' => $runid]);
        if ($run) {
            file_put_contents($system_dir . "/run_{$runid}_details.json", json_encode($run, JSON_PRETTY_PRINT));
            $collected['run_details'] = [
                'filename' => "run_{$runid}_details.json",
                'status' => $run->status,
                'company_id' => $run->companyid,
                'target_company_id' => $run->targetcompanyid
            ];
        }
        
        // Get synthesis record (cache data)
        $synthesis = $this->db->get_record('local_ci_synthesis', ['runid' => $runid]);
        if ($synthesis) {
            file_put_contents($system_dir . "/synthesis_cache_{$runid}.json", $synthesis->jsoncontent ?: '{}');
            $collected['synthesis_cache'] = [
                'filename' => "synthesis_cache_{$runid}.json",
                'size' => strlen($synthesis->jsoncontent ?: '{}'),
                'last_updated' => date('Y-m-d H:i:s', $synthesis->updatedat)
            ];
        }
        
        // Get compatibility info
        $compat_info = artifact_compatibility_adapter::get_compatibility_info();
        file_put_contents($system_dir . '/compatibility_info.json', json_encode($compat_info, JSON_PRETTY_PRINT));
        $collected['compatibility_info'] = [
            'filename' => 'compatibility_info.json',
            'version' => $compat_info['version']
        ];
        
        // Get system configuration
        $config = [
            'enable_trace_mode' => get_config('local_customerintel', 'enable_trace_mode'),
            'safe_mode' => get_config('local_customerintel', 'safe_mode'),
            'perplexity_model' => get_config('local_customerintel', 'perplexity_model'),
            'moodle_version' => get_config('', 'version'),
            'php_version' => PHP_VERSION,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        file_put_contents($system_dir . '/system_config.json', json_encode($config, JSON_PRETTY_PRINT));
        $collected['system_config'] = [
            'filename' => 'system_config.json',
            'trace_mode_enabled' => $config['enable_trace_mode'] === '1'
        ];
        
        return $collected;
    }
    
    /**
     * Generate archive manifest
     */
    private function generate_manifest($runid, $archive_contents, $start_time) {
        return [
            'diagnostic_archive_version' => '1.0',
            'compatibility_system_version' => 'v17.1',
            'runid' => $runid,
            'generated_at' => date('Y-m-d H:i:s'),
            'generation_time_ms' => round((microtime(true) - $start_time) * 1000, 2),
            'archive_contents' => $archive_contents,
            'summary' => [
                'artifacts_found' => count($archive_contents['artifacts'] ?? []),
                'compatibility_log_entries' => $archive_contents['compatibility_logs']['entry_count'] ?? 0,
                'telemetry_records' => $archive_contents['telemetry']['record_count'] ?? 0,
                'artifact_repository_records' => $archive_contents['artifact_repository']['record_count'] ?? 0
            ],
            'diagnostic_purpose' => 'Complete troubleshooting data for CustomerIntel run including artifacts, logs, and system state'
        ];
    }
    
    /**
     * Recursively add directory contents to ZIP
     */
    private function add_directory_to_zip($zip, $dir, $zip_path) {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = $zip_path . substr($file_path, strlen($dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    /**
     * Remove directory and all contents
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($dir);
    }
}