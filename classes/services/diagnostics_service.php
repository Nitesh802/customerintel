<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Automated Run Doctor and Diagnostics Service
 *
 * Performs automated self-diagnosis of synthesis runs, checking for:
 * - Presence and size of key artifacts
 * - Complete trace coverage through all phases
 * - Identification of skipped or zero-duration phases
 * - Performance anomaly detection
 *
 * @package    local_customerintel
 * @subpackage services
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/customerintel/lib.php');

class diagnostics_service {
    
    /** @var array Expected synthesis phases */
    private const EXPECTED_PHASES = [
        'normalization',
        'rebalancing', 
        'validation',
        'drafting',
        'bundle'
    ];
    
    /** @var array Expected artifacts */
    private const EXPECTED_ARTIFACTS = [
        'normalized_inputs',
        'diversity_metrics',
        'final_bundle',
        'synthesis_record'
    ];
    
    /** @var array Performance thresholds (in milliseconds) */
    private const PERFORMANCE_THRESHOLDS = [
        'normalization' => ['min' => 100, 'max' => 30000],
        'rebalancing' => ['min' => 50, 'max' => 15000],
        'validation' => ['min' => 10, 'max' => 5000],
        'drafting' => ['min' => 1000, 'max' => 120000],
        'bundle' => ['min' => 100, 'max' => 10000]
    ];

    /**
     * Run complete diagnostics on a synthesis run
     *
     * @param int $runid Run ID to diagnose
     * @return array Diagnostic results
     */
    public function run_diagnostics(int $runid): array {
        global $DB;
        
        $diagnostics = [
            'runid' => $runid,
            'timestamp' => time(),
            'overall_health' => 'OK',
            'summary' => '',
            'issues' => [],
            'warnings' => [],
            'artifacts' => [],
            'phases' => [],
            'performance' => []
        ];
        
        try {
            // 1. Check artifact presence and integrity
            $artifacts_check = $this->check_artifacts($runid);
            $diagnostics['artifacts'] = $artifacts_check;
            
            // 2. Analyze trace coverage and phase completion
            $phases_check = $this->check_phase_coverage($runid);
            $diagnostics['phases'] = $phases_check;
            
            // 3. Performance analysis
            $performance_check = $this->check_performance($runid);
            $diagnostics['performance'] = $performance_check;
            
            // 4. Predictive anomaly detection
            $anomalies_check = $this->detect_anomalies($runid);
            $diagnostics['anomalies'] = $anomalies_check;
            
            // 5. Determine overall health status
            $health_assessment = $this->assess_overall_health($diagnostics);
            $diagnostics['overall_health'] = $health_assessment['status'];
            $diagnostics['summary'] = $health_assessment['summary'];
            $diagnostics['issues'] = $health_assessment['issues'];
            $diagnostics['warnings'] = $health_assessment['warnings'];
            
            // 6. Store diagnostics results
            $this->store_diagnostics($runid, $diagnostics);
            
        } catch (\Exception $e) {
            $diagnostics['overall_health'] = 'FAILED';
            $diagnostics['summary'] = 'Diagnostics failed: ' . $e->getMessage();
            $diagnostics['issues'][] = [
                'type' => 'diagnostic_error',
                'severity' => 'critical',
                'message' => 'Unable to complete diagnostic analysis',
                'details' => $e->getMessage()
            ];
        }
        
        return $diagnostics;
    }
    
    /**
     * Check presence and integrity of key artifacts
     */
    private function check_artifacts(int $runid): array {
        global $DB, $CFG;
        
        $artifacts = [
            'status' => 'OK',
            'found' => [],
            'missing' => [],
            'corrupted' => [],
            'details' => []
        ];
        
        // Check artifact repository entries
        require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_repository.php');
        $artifact_repo = new artifact_repository();
        
        foreach (self::EXPECTED_ARTIFACTS as $artifact_name) {
            try {
                $artifact_data = $artifact_repo->get_artifact($runid, 'synthesis', $artifact_name);
                
                if ($artifact_data === null) {
                    $artifacts['missing'][] = $artifact_name;
                    $artifacts['details'][$artifact_name] = ['status' => 'missing'];
                } else {
                    $size = is_string($artifact_data) ? strlen($artifact_data) : 
                           (is_array($artifact_data) ? count($artifact_data) : 1);
                    
                    if ($size === 0) {
                        $artifacts['corrupted'][] = $artifact_name;
                        $artifacts['details'][$artifact_name] = [
                            'status' => 'corrupted', 
                            'reason' => 'empty_data'
                        ];
                    } else {
                        $artifacts['found'][] = $artifact_name;
                        $artifacts['details'][$artifact_name] = [
                            'status' => 'found',
                            'size' => $size,
                            'type' => gettype($artifact_data)
                        ];
                    }
                }
            } catch (\Exception $e) {
                $artifacts['corrupted'][] = $artifact_name;
                $artifacts['details'][$artifact_name] = [
                    'status' => 'error',
                    'reason' => $e->getMessage()
                ];
            }
        }
        
        // Determine artifact status
        if (!empty($artifacts['missing']) || !empty($artifacts['corrupted'])) {
            $artifacts['status'] = !empty($artifacts['missing']) ? 'MISSING' : 'DEGRADED';
        }
        
        return $artifacts;
    }
    
    /**
     * Check trace coverage and phase completion
     */
    private function check_phase_coverage(int $runid): array {
        global $DB;
        
        $phases = [
            'status' => 'OK',
            'completed' => [],
            'skipped' => [],
            'zero_duration' => [],
            'details' => []
        ];
        
        // Get all trace records for this run
        $traces = $DB->get_records('local_ci_telemetry', 
            ['runid' => $runid, 'metrickey' => 'trace_phase'], 
            'timecreated ASC'
        );
        
        $phase_data = [];
        
        foreach ($traces as $trace) {
            $payload = json_decode($trace->payload, true);
            if (!$payload || !isset($payload['phase_name'])) {
                continue;
            }
            
            $phase_name = $payload['phase_name'];
            
            if (!isset($phase_data[$phase_name])) {
                $phase_data[$phase_name] = [
                    'start_time' => null,
                    'end_time' => null,
                    'duration_ms' => 0,
                    'status' => 'unknown',
                    'events' => []
                ];
            }
            
            $phase_data[$phase_name]['events'][] = $payload;
            
            // Track timing information
            if (isset($payload['timestamp_start'])) {
                $phase_data[$phase_name]['start_time'] = $payload['timestamp_start'];
            }
            if (isset($payload['timestamp_end'])) {
                $phase_data[$phase_name]['end_time'] = $payload['timestamp_end'];
            }
            if (isset($payload['duration_ms'])) {
                $phase_data[$phase_name]['duration_ms'] = $payload['duration_ms'];
            }
            if (isset($payload['status'])) {
                $phase_data[$phase_name]['status'] = $payload['status'];
            }
        }
        
        // Analyze each expected phase
        foreach (self::EXPECTED_PHASES as $expected_phase) {
            if (isset($phase_data[$expected_phase])) {
                $phase_info = $phase_data[$expected_phase];
                
                if ($phase_info['duration_ms'] == 0 && $phase_info['status'] !== 'error') {
                    $phases['zero_duration'][] = $expected_phase;
                } else {
                    $phases['completed'][] = $expected_phase;
                }
                
                $phases['details'][$expected_phase] = $phase_info;
            } else {
                $phases['skipped'][] = $expected_phase;
                $phases['details'][$expected_phase] = [
                    'status' => 'skipped',
                    'duration_ms' => 0,
                    'events' => []
                ];
            }
        }
        
        // Determine phase coverage status
        if (!empty($phases['skipped'])) {
            $phases['status'] = 'INCOMPLETE';
        } elseif (!empty($phases['zero_duration'])) {
            $phases['status'] = 'DEGRADED';
        }
        
        return $phases;
    }
    
    /**
     * Check performance characteristics
     */
    private function check_performance(int $runid): array {
        global $DB;
        
        $performance = [
            'status' => 'OK',
            'total_duration_ms' => 0,
            'phase_performance' => [],
            'anomalies' => []
        ];
        
        // Get telemetry data for performance analysis
        $telemetry_records = $DB->get_records('local_ci_telemetry', 
            ['runid' => $runid], 
            'timecreated ASC'
        );
        
        $phase_durations = [];
        
        foreach ($telemetry_records as $record) {
            $payload = json_decode($record->payload, true);
            
            if ($record->metrickey === 'trace_phase' && 
                isset($payload['duration_ms']) && 
                isset($payload['phase_name'])) {
                
                $phase_name = $payload['phase_name'];
                $duration = $payload['duration_ms'];
                
                $phase_durations[$phase_name] = $duration;
                $performance['total_duration_ms'] += $duration;
                
                // Check against thresholds
                if (isset(self::PERFORMANCE_THRESHOLDS[$phase_name])) {
                    $thresholds = self::PERFORMANCE_THRESHOLDS[$phase_name];
                    
                    $status = 'normal';
                    $issues = [];
                    
                    if ($duration < $thresholds['min']) {
                        $status = 'too_fast';
                        $issues[] = "Duration below minimum threshold ({$thresholds['min']}ms)";
                    } elseif ($duration > $thresholds['max']) {
                        $status = 'too_slow';
                        $issues[] = "Duration exceeds maximum threshold ({$thresholds['max']}ms)";
                    }
                    
                    $performance['phase_performance'][$phase_name] = [
                        'duration_ms' => $duration,
                        'status' => $status,
                        'thresholds' => $thresholds,
                        'issues' => $issues
                    ];
                    
                    if ($status !== 'normal') {
                        $performance['anomalies'][] = [
                            'phase' => $phase_name,
                            'type' => $status,
                            'duration' => $duration,
                            'issues' => $issues
                        ];
                    }
                }
            }
        }
        
        // Determine performance status
        if (!empty($performance['anomalies'])) {
            $performance['status'] = 'ANOMALOUS';
        }
        
        return $performance;
    }
    
    /**
     * Detect predictive anomalies using heuristic rules
     */
    private function detect_anomalies(int $runid): array {
        global $DB;
        
        $anomalies = [
            'status' => 'OK',
            'predictive_alerts' => [],
            'heuristic_warnings' => []
        ];
        
        // Rule 1: All NB modules complete but normalization has < 10 citations
        $nb_completion_check = $this->check_nb_vs_citations($runid);
        if ($nb_completion_check['alert']) {
            $anomalies['predictive_alerts'][] = [
                'type' => 'normalization_bypass_suspected',
                'confidence' => $nb_completion_check['confidence'],
                'description' => 'NB modules completed but low citation count suggests normalization may have been bypassed',
                'recommendation' => 'Check normalized_inputs_v16 artifact and citation rebalancing logs'
            ];
        }
        
        // Rule 2: Validation phase completes in < 1s
        $validation_timing = $this->check_validation_timing($runid);
        if ($validation_timing['alert']) {
            $anomalies['predictive_alerts'][] = [
                'type' => 'empty_artifact_probable',
                'confidence' => $validation_timing['confidence'],
                'description' => 'Validation completed unusually quickly, indicating possible empty artifact condition',
                'recommendation' => 'Verify validation input data and check for empty NB results'
            ];
        }
        
        // Rule 3: Diversity metrics = 0 for multiple runs
        $diversity_check = $this->check_diversity_metrics($runid);
        if ($diversity_check['alert']) {
            $anomalies['predictive_alerts'][] = [
                'type' => 'diversity_calculation_failure',
                'confidence' => $diversity_check['confidence'],
                'description' => 'Diversity metrics are consistently zero, suggesting calculation issues',
                'recommendation' => 'Check citation rebalancing process and diversity metric calculation'
            ];
        }
        
        // Determine anomaly status
        if (!empty($anomalies['predictive_alerts'])) {
            $anomalies['status'] = 'ALERTS_DETECTED';
        }
        
        return $anomalies;
    }
    
    /**
     * Check for NB completion vs citation count mismatch
     */
    private function check_nb_vs_citations(int $runid): array {
        global $DB;
        
        // Check if we have NB completion data but low citations
        $nb_count = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_ci_nb_results} WHERE runid = ? AND result IS NOT NULL",
            [$runid]
        );
        
        $citation_count = $DB->get_field_sql(
            "SELECT COUNT(*) FROM {local_ci_telemetry} 
             WHERE runid = ? AND metrickey LIKE '%citation%' AND payload LIKE '%count%'",
            [$runid]
        );
        
        $alert = ($nb_count >= 10 && $citation_count < 5);
        $confidence = $alert ? min(90, $nb_count * 10 - $citation_count * 5) : 0;
        
        return ['alert' => $alert, 'confidence' => $confidence];
    }
    
    /**
     * Check validation timing anomalies
     */
    private function check_validation_timing(int $runid): array {
        global $DB;
        
        $validation_record = $DB->get_record_sql(
            "SELECT payload FROM {local_ci_telemetry} 
             WHERE runid = ? AND metrickey = 'trace_phase' 
             AND payload LIKE '%validation%' AND payload LIKE '%duration_ms%'
             ORDER BY timecreated DESC LIMIT 1",
            [$runid]
        );
        
        $alert = false;
        $confidence = 0;
        
        if ($validation_record) {
            $payload = json_decode($validation_record->payload, true);
            if (isset($payload['duration_ms']) && $payload['duration_ms'] < 1000) {
                $alert = true;
                $confidence = 100 - ($payload['duration_ms'] / 10); // Higher confidence for shorter durations
            }
        }
        
        return ['alert' => $alert, 'confidence' => min(95, $confidence)];
    }
    
    /**
     * Check diversity metrics consistency
     */
    private function check_diversity_metrics(int $runid): array {
        global $DB;
        
        // Check recent runs for diversity metrics
        $recent_runs = $DB->get_records_sql(
            "SELECT DISTINCT runid FROM {local_ci_telemetry} 
             WHERE runid >= ? - 10 AND metrickey = 'diversity_score' 
             ORDER BY runid DESC LIMIT 5",
            [$runid]
        );
        
        $zero_diversity_count = 0;
        
        foreach ($recent_runs as $run) {
            $diversity_score = $DB->get_field('local_ci_telemetry',
                'payload',
                ['runid' => $run->runid, 'metrickey' => 'diversity_score']
            );
            
            if ($diversity_score !== false) {
                $score_data = json_decode($diversity_score, true);
                if (isset($score_data['value']) && $score_data['value'] == 0) {
                    $zero_diversity_count++;
                }
            }
        }
        
        $alert = ($zero_diversity_count >= 3);
        $confidence = $alert ? ($zero_diversity_count * 25) : 0;
        
        return ['alert' => $alert, 'confidence' => min(95, $confidence)];
    }
    
    /**
     * Assess overall health based on all diagnostic components
     */
    private function assess_overall_health(array $diagnostics): array {
        $issues = [];
        $warnings = [];
        $status = 'OK';
        
        // Assess artifacts
        if ($diagnostics['artifacts']['status'] === 'MISSING') {
            $status = 'FAILED';
            $issues[] = [
                'type' => 'missing_artifacts',
                'severity' => 'critical',
                'message' => 'Critical synthesis artifacts are missing',
                'details' => 'Missing: ' . implode(', ', $diagnostics['artifacts']['missing'])
            ];
        } elseif ($diagnostics['artifacts']['status'] === 'DEGRADED') {
            $status = ($status === 'OK') ? 'DEGRADED' : $status;
            $warnings[] = [
                'type' => 'corrupted_artifacts',
                'severity' => 'medium',
                'message' => 'Some synthesis artifacts are corrupted or empty',
                'details' => 'Corrupted: ' . implode(', ', $diagnostics['artifacts']['corrupted'])
            ];
        }
        
        // Assess phases
        if ($diagnostics['phases']['status'] === 'INCOMPLETE') {
            $status = 'FAILED';
            $issues[] = [
                'type' => 'incomplete_phases',
                'severity' => 'critical',
                'message' => 'Synthesis phases were skipped or not completed',
                'details' => 'Skipped: ' . implode(', ', $diagnostics['phases']['skipped'])
            ];
        } elseif ($diagnostics['phases']['status'] === 'DEGRADED') {
            $status = ($status === 'OK') ? 'DEGRADED' : $status;
            $warnings[] = [
                'type' => 'zero_duration_phases',
                'severity' => 'medium',
                'message' => 'Some phases completed with zero duration',
                'details' => 'Zero duration: ' . implode(', ', $diagnostics['phases']['zero_duration'])
            ];
        }
        
        // Assess performance
        if ($diagnostics['performance']['status'] === 'ANOMALOUS') {
            $status = ($status === 'OK') ? 'DEGRADED' : $status;
            $warnings[] = [
                'type' => 'performance_anomalies',
                'severity' => 'medium',
                'message' => 'Performance anomalies detected in phase timing',
                'details' => 'Anomalous phases: ' . count($diagnostics['performance']['anomalies'])
            ];
        }
        
        // Assess predictive alerts
        if ($diagnostics['anomalies']['status'] === 'ALERTS_DETECTED') {
            $status = ($status === 'OK') ? 'DEGRADED' : $status;
            foreach ($diagnostics['anomalies']['predictive_alerts'] as $alert) {
                $warnings[] = [
                    'type' => 'predictive_alert',
                    'severity' => 'high',
                    'message' => $alert['description'],
                    'details' => $alert['recommendation']
                ];
            }
        }
        
        // Generate summary
        $summary = $this->generate_health_summary($status, count($issues), count($warnings));
        
        return [
            'status' => $status,
            'summary' => $summary,
            'issues' => $issues,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Generate human-readable health summary
     */
    private function generate_health_summary(string $status, int $issue_count, int $warning_count): string {
        switch ($status) {
            case 'OK':
                return 'Run completed successfully with no detected issues.';
            case 'DEGRADED':
                return "Run completed with {$warning_count} warning(s). Some components may not be optimal.";
            case 'FAILED':
                return "Run encountered {$issue_count} critical issue(s) and {$warning_count} warning(s). Manual review required.";
            default:
                return 'Run status unknown. Unable to complete diagnostic analysis.';
        }
    }
    
    /**
     * Store diagnostic results in database
     */
    private function store_diagnostics(int $runid, array $diagnostics): void {
        global $DB;
        
        // Store in local_ci_diagnostics table (will create this table next)
        $record = new \stdClass();
        $record->runid = $runid;
        $record->status = $diagnostics['overall_health'];
        $record->summary = $diagnostics['summary'];
        $record->issues_count = count($diagnostics['issues']);
        $record->warnings_count = count($diagnostics['warnings']);
        $record->diagnostics_data = json_encode($diagnostics);
        $record->timecreated = time();
        
        // Delete existing diagnostics for this run
        $DB->delete_records('local_ci_diagnostics', ['runid' => $runid]);
        
        // Insert new diagnostics
        $DB->insert_record('local_ci_diagnostics', $record);
    }
    
    /**
     * Get stored diagnostics for a run
     */
    public function get_diagnostics(int $runid): ?array {
        global $DB;
        
        $record = $DB->get_record('local_ci_diagnostics', ['runid' => $runid]);
        
        if (!$record) {
            return null;
        }
        
        $diagnostics = json_decode($record->diagnostics_data, true);
        $diagnostics['stored_at'] = $record->timecreated;
        
        return $diagnostics;
    }
    
    /**
     * Get diagnostics summary for multiple runs
     */
    public function get_diagnostics_summary(array $runids): array {
        global $DB;
        
        if (empty($runids)) {
            return [];
        }
        
        list($insql, $params) = $DB->get_in_or_equal($runids);
        
        $records = $DB->get_records_sql(
            "SELECT runid, status, summary, issues_count, warnings_count, timecreated 
             FROM {local_ci_diagnostics} 
             WHERE runid $insql 
             ORDER BY runid DESC",
            $params
        );
        
        $summary = [];
        foreach ($records as $record) {
            $summary[$record->runid] = [
                'status' => $record->status,
                'summary' => $record->summary,
                'issues_count' => $record->issues_count,
                'warnings_count' => $record->warnings_count,
                'checked_at' => $record->timecreated
            ];
        }
        
        return $summary;
    }
}