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
 * Telemetry Manager for Historical Performance Tracking
 *
 * Manages historical telemetry data, performance trends, and provides
 * analytics for synthesis pipeline health monitoring over time.
 *
 * @package    local_customerintel
 * @subpackage services
 * @copyright  2024 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/customerintel/lib.php');

class telemetry_manager {
    
    /** @var int Default number of days to analyze for trends */
    private const DEFAULT_TREND_DAYS = 30;
    
    /** @var array Phase color codes for visualization */
    private const PHASE_COLORS = [
        'normalization' => '#2E8B57',   // SeaGreen
        'rebalancing' => '#4682B4',     // SteelBlue
        'validation' => '#FF8C00',      // DarkOrange
        'drafting' => '#9932CC',        // DarkOrchid
        'bundle' => '#DC143C'           // Crimson
    ];
    
    /**
     * Get performance trends for dashboard visualization
     *
     * @param int $days Number of days to analyze (default 30)
     * @return array Performance trend data
     */
    public function get_performance_trends(int $days = self::DEFAULT_TREND_DAYS): array {
        global $DB;
        
        $since_timestamp = time() - ($days * 24 * 60 * 60);
        
        $trends = [
            'period_days' => $days,
            'period_start' => $since_timestamp,
            'period_end' => time(),
            'phase_durations' => [],
            'error_frequency' => [],
            'longest_runs' => [],
            'overall_stats' => [],
            'health_distribution' => []
        ];
        
        try {
            // 1. Calculate average phase durations over time
            $trends['phase_durations'] = $this->get_phase_duration_trends($since_timestamp);
            
            // 2. Analyze error frequency patterns
            $trends['error_frequency'] = $this->get_error_frequency_trends($since_timestamp);
            
            // 3. Get top N longest runs
            $trends['longest_runs'] = $this->get_longest_runs($since_timestamp, 10);
            
            // 4. Overall statistics
            $trends['overall_stats'] = $this->get_overall_statistics($since_timestamp);
            
            // 5. Health status distribution
            $trends['health_distribution'] = $this->get_health_distribution($since_timestamp);
            
            // 6. Daily aggregates for charts
            $trends['daily_aggregates'] = $this->get_daily_aggregates($since_timestamp);
            
        } catch (\Exception $e) {
            debugging("Failed to generate performance trends: " . $e->getMessage(), DEBUG_DEVELOPER);
            $trends['error'] = $e->getMessage();
        }
        
        return $trends;
    }
    
    /**
     * Get phase duration trends with statistical analysis
     */
    private function get_phase_duration_trends(int $since_timestamp): array {
        global $DB;
        
        $phase_trends = [];
        
        // Get all trace records with duration data
        $sql = "SELECT payload, timecreated 
                FROM {local_ci_telemetry} 
                WHERE timecreated >= ? 
                AND metrickey = 'trace_phase' 
                AND payload LIKE '%duration_ms%'
                ORDER BY timecreated ASC";
        
        $records = $DB->get_records_sql($sql, [$since_timestamp]);
        
        $phase_data = [];
        
        foreach ($records as $record) {
            $payload = json_decode($record->payload, true);
            if (!$payload || !isset($payload['phase_name']) || !isset($payload['duration_ms'])) {
                continue;
            }
            
            $phase = $payload['phase_name'];
            $duration = $payload['duration_ms'];
            $date = date('Y-m-d', $record->timecreated);
            
            if (!isset($phase_data[$phase])) {
                $phase_data[$phase] = [
                    'durations' => [],
                    'daily_averages' => [],
                    'color' => self::PHASE_COLORS[$phase] ?? '#666666'
                ];
            }
            
            $phase_data[$phase]['durations'][] = $duration;
            
            if (!isset($phase_data[$phase]['daily_averages'][$date])) {
                $phase_data[$phase]['daily_averages'][$date] = [];
            }
            $phase_data[$phase]['daily_averages'][$date][] = $duration;
        }
        
        // Calculate statistics for each phase
        foreach ($phase_data as $phase => $data) {
            if (empty($data['durations'])) {
                continue;
            }
            
            $durations = $data['durations'];
            sort($durations);
            
            $count = count($durations);
            $avg = array_sum($durations) / $count;
            $median = $count % 2 === 0 ? 
                ($durations[$count/2 - 1] + $durations[$count/2]) / 2 : 
                $durations[floor($count/2)];
            
            $variance = array_sum(array_map(function($x) use ($avg) { return pow($x - $avg, 2); }, $durations)) / $count;
            $std_dev = sqrt($variance);
            
            // Calculate daily averages
            $daily_avg = [];
            foreach ($data['daily_averages'] as $date => $day_durations) {
                $daily_avg[$date] = array_sum($day_durations) / count($day_durations);
            }
            
            // Determine health status based on recent performance
            $recent_avg = array_slice($durations, -10);
            $recent_average = !empty($recent_avg) ? array_sum($recent_avg) / count($recent_avg) : $avg;
            
            $health_status = 'green';
            if ($recent_average > $avg + (2 * $std_dev)) {
                $health_status = 'red';
            } elseif ($recent_average > $avg + $std_dev) {
                $health_status = 'yellow';
            }
            
            $phase_trends[$phase] = [
                'average_ms' => round($avg, 2),
                'median_ms' => round($median, 2),
                'std_deviation_ms' => round($std_dev, 2),
                'min_ms' => min($durations),
                'max_ms' => max($durations),
                'count' => $count,
                'health_status' => $health_status,
                'recent_average_ms' => round($recent_average, 2),
                'daily_averages' => $daily_avg,
                'color' => $data['color'],
                'trend_direction' => $this->calculate_trend_direction($daily_avg)
            ];
        }
        
        return $phase_trends;
    }
    
    /**
     * Calculate trend direction from daily averages
     */
    private function calculate_trend_direction(array $daily_averages): string {
        if (count($daily_averages) < 3) {
            return 'stable';
        }
        
        $values = array_values($daily_averages);
        $recent_values = array_slice($values, -7); // Last 7 days
        $earlier_values = array_slice($values, -14, 7); // Previous 7 days
        
        if (empty($earlier_values)) {
            return 'stable';
        }
        
        $recent_avg = array_sum($recent_values) / count($recent_values);
        $earlier_avg = array_sum($earlier_values) / count($earlier_values);
        
        $change_percent = (($recent_avg - $earlier_avg) / $earlier_avg) * 100;
        
        if ($change_percent > 10) {
            return 'increasing';
        } elseif ($change_percent < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Get error frequency trends
     */
    private function get_error_frequency_trends(int $since_timestamp): array {
        global $DB;
        
        $error_trends = [
            'total_errors' => 0,
            'total_warnings' => 0,
            'by_phase' => [],
            'by_type' => [],
            'daily_counts' => [],
            'most_common' => []
        ];
        
        // Get error and warning records
        $sql = "SELECT payload, timecreated 
                FROM {local_ci_telemetry} 
                WHERE timecreated >= ? 
                AND (metrickey = 'trace_phase' AND (payload LIKE '%error%' OR payload LIKE '%warning%'))
                ORDER BY timecreated ASC";
        
        $records = $DB->get_records_sql($sql, [$since_timestamp]);
        
        $error_types = [];
        
        foreach ($records as $record) {
            $payload = json_decode($record->payload, true);
            if (!$payload) continue;
            
            $date = date('Y-m-d', $record->timecreated);
            $phase = $payload['phase_name'] ?? 'unknown';
            $status = $payload['status'] ?? 'info';
            
            // Initialize daily counter
            if (!isset($error_trends['daily_counts'][$date])) {
                $error_trends['daily_counts'][$date] = ['errors' => 0, 'warnings' => 0];
            }
            
            if ($status === 'error') {
                $error_trends['total_errors']++;
                $error_trends['daily_counts'][$date]['errors']++;
                
                if (!isset($error_trends['by_phase'][$phase])) {
                    $error_trends['by_phase'][$phase] = ['errors' => 0, 'warnings' => 0];
                }
                $error_trends['by_phase'][$phase]['errors']++;
                
                // Track error types
                if (isset($payload['anomalies'])) {
                    foreach ($payload['anomalies'] as $anomaly) {
                        $type = $anomaly['issue'] ?? 'unknown_error';
                        $error_types[$type] = ($error_types[$type] ?? 0) + 1;
                    }
                }
                
            } elseif ($status === 'warning') {
                $error_trends['total_warnings']++;
                $error_trends['daily_counts'][$date]['warnings']++;
                
                if (!isset($error_trends['by_phase'][$phase])) {
                    $error_trends['by_phase'][$phase] = ['errors' => 0, 'warnings' => 0];
                }
                $error_trends['by_phase'][$phase]['warnings']++;
            }
        }
        
        // Sort error types by frequency
        arsort($error_types);
        $error_trends['most_common'] = array_slice($error_types, 0, 10, true);
        
        return $error_trends;
    }
    
    /**
     * Get top N longest running synthesis operations
     */
    private function get_longest_runs(int $since_timestamp, int $limit = 10): array {
        global $DB;
        
        $sql = "SELECT runid, 
                       SUM(CASE WHEN metrickey = 'total_duration_ms' 
                           THEN CAST(JSON_EXTRACT(payload, '$.value') AS UNSIGNED) 
                           ELSE 0 END) as total_duration_ms,
                       MAX(timecreated) as completed_at
                FROM {local_ci_telemetry} 
                WHERE timecreated >= ? 
                AND metrickey IN ('total_duration_ms', 'trace_phase')
                GROUP BY runid 
                HAVING total_duration_ms > 0
                ORDER BY total_duration_ms DESC 
                LIMIT ?";
        
        $records = $DB->get_records_sql($sql, [$since_timestamp, $limit]);
        
        $longest_runs = [];
        foreach ($records as $record) {
            $longest_runs[] = [
                'runid' => $record->runid,
                'duration_ms' => $record->total_duration_ms,
                'duration_seconds' => round($record->total_duration_ms / 1000, 2),
                'completed_at' => $record->completed_at,
                'completed_date' => date('Y-m-d H:i:s', $record->completed_at)
            ];
        }
        
        return $longest_runs;
    }
    
    /**
     * Get overall statistics for the period
     */
    private function get_overall_statistics(int $since_timestamp): array {
        global $DB;
        
        $stats = [
            'total_runs' => 0,
            'successful_runs' => 0,
            'failed_runs' => 0,
            'average_duration_ms' => 0,
            'total_synthesis_time_hours' => 0,
            'runs_per_day' => 0,
            'success_rate_percent' => 0
        ];
        
        // Count total runs
        $stats['total_runs'] = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT runid) FROM {local_ci_telemetry} WHERE timecreated >= ?",
            [$since_timestamp]
        );
        
        // Get diagnostic status distribution
        $diagnostic_stats = $DB->get_records_sql(
            "SELECT status, COUNT(*) as count 
             FROM {local_ci_diagnostics} 
             WHERE timecreated >= ? 
             GROUP BY status",
            [$since_timestamp]
        );
        
        foreach ($diagnostic_stats as $stat) {
            if ($stat->status === 'OK') {
                $stats['successful_runs'] += $stat->count;
            } else {
                $stats['failed_runs'] += $stat->count;
            }
        }
        
        // Calculate success rate
        if ($stats['total_runs'] > 0) {
            $stats['success_rate_percent'] = round(($stats['successful_runs'] / $stats['total_runs']) * 100, 1);
        }
        
        // Get average duration
        $avg_duration = $DB->get_field_sql(
            "SELECT AVG(CAST(JSON_EXTRACT(payload, '$.value') AS UNSIGNED)) 
             FROM {local_ci_telemetry} 
             WHERE timecreated >= ? AND metrickey = 'total_duration_ms'",
            [$since_timestamp]
        );
        
        $stats['average_duration_ms'] = round($avg_duration ?? 0, 2);
        
        // Calculate total synthesis time
        $total_duration = $DB->get_field_sql(
            "SELECT SUM(CAST(JSON_EXTRACT(payload, '$.value') AS UNSIGNED)) 
             FROM {local_ci_telemetry} 
             WHERE timecreated >= ? AND metrickey = 'total_duration_ms'",
            [$since_timestamp]
        );
        
        $stats['total_synthesis_time_hours'] = round(($total_duration ?? 0) / (1000 * 60 * 60), 2);
        
        // Calculate runs per day
        $days_in_period = max(1, (time() - $since_timestamp) / (24 * 60 * 60));
        $stats['runs_per_day'] = round($stats['total_runs'] / $days_in_period, 1);
        
        return $stats;
    }
    
    /**
     * Get health status distribution
     */
    private function get_health_distribution(int $since_timestamp): array {
        global $DB;
        
        $distribution = ['OK' => 0, 'DEGRADED' => 0, 'FAILED' => 0];
        
        $records = $DB->get_records_sql(
            "SELECT status, COUNT(*) as count 
             FROM {local_ci_diagnostics} 
             WHERE timecreated >= ? 
             GROUP BY status",
            [$since_timestamp]
        );
        
        foreach ($records as $record) {
            if (isset($distribution[$record->status])) {
                $distribution[$record->status] = $record->count;
            }
        }
        
        return $distribution;
    }
    
    /**
     * Get daily aggregates for time-series charts
     */
    private function get_daily_aggregates(int $since_timestamp): array {
        global $DB;
        
        $daily_data = [];
        
        // Get daily run counts and average durations
        $sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date,
                       COUNT(DISTINCT runid) as run_count,
                       AVG(CASE WHEN metrickey = 'total_duration_ms' 
                           THEN CAST(JSON_EXTRACT(payload, '$.value') AS UNSIGNED) 
                           ELSE NULL END) as avg_duration_ms
                FROM {local_ci_telemetry} 
                WHERE timecreated >= ?
                GROUP BY DATE(FROM_UNIXTIME(timecreated))
                ORDER BY date ASC";
        
        $records = $DB->get_records_sql($sql, [$since_timestamp]);
        
        foreach ($records as $record) {
            $daily_data[$record->date] = [
                'date' => $record->date,
                'run_count' => $record->run_count,
                'avg_duration_ms' => round($record->avg_duration_ms ?? 0, 2),
                'avg_duration_minutes' => round(($record->avg_duration_ms ?? 0) / (1000 * 60), 2)
            ];
        }
        
        return $daily_data;
    }
    
    /**
     * Get specific metrics for a time period
     */
    public function get_metrics_summary(string $metric_key, int $days = 7): array {
        global $DB;
        
        $since_timestamp = time() - ($days * 24 * 60 * 60);
        
        $sql = "SELECT runid, payload, timecreated 
                FROM {local_ci_telemetry} 
                WHERE timecreated >= ? 
                AND metrickey = ? 
                ORDER BY timecreated DESC";
        
        $records = $DB->get_records_sql($sql, [$since_timestamp, $metric_key]);
        
        $values = [];
        foreach ($records as $record) {
            $payload = json_decode($record->payload, true);
            if (isset($payload['value'])) {
                $values[] = [
                    'runid' => $record->runid,
                    'value' => $payload['value'],
                    'timestamp' => $record->timecreated,
                    'date' => date('Y-m-d H:i:s', $record->timecreated)
                ];
            }
        }
        
        return [
            'metric_key' => $metric_key,
            'period_days' => $days,
            'count' => count($values),
            'values' => $values,
            'average' => !empty($values) ? array_sum(array_column($values, 'value')) / count($values) : 0,
            'min' => !empty($values) ? min(array_column($values, 'value')) : 0,
            'max' => !empty($values) ? max(array_column($values, 'value')) : 0
        ];
    }
    
    /**
     * Export telemetry data for external analysis
     */
    public function export_telemetry_data(int $runid = null, int $days = null): array {
        global $DB;
        
        $conditions = [];
        $params = [];
        
        if ($runid !== null) {
            $conditions[] = "runid = ?";
            $params[] = $runid;
        }
        
        if ($days !== null) {
            $since_timestamp = time() - ($days * 24 * 60 * 60);
            $conditions[] = "timecreated >= ?";
            $params[] = $since_timestamp;
        }
        
        $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "SELECT * FROM {local_ci_telemetry} {$where_clause} ORDER BY runid, timecreated";
        
        $records = $DB->get_records_sql($sql, $params);
        
        $export_data = [
            'export_timestamp' => time(),
            'export_date' => date('Y-m-d H:i:s'),
            'filters' => [
                'runid' => $runid,
                'days' => $days
            ],
            'record_count' => count($records),
            'telemetry_data' => []
        ];
        
        foreach ($records as $record) {
            $export_data['telemetry_data'][] = [
                'id' => $record->id,
                'runid' => $record->runid,
                'metrickey' => $record->metrickey,
                'level' => $record->level,
                'payload' => json_decode($record->payload, true),
                'timecreated' => $record->timecreated,
                'created_date' => date('Y-m-d H:i:s', $record->timecreated)
            ];
        }
        
        return $export_data;
    }
}