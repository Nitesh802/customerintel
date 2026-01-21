<?php
/**
 * Telemetry Service - Provides dashboard analytics
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * TelemetryService class
 * 
 * Aggregates telemetry data for dashboard visualization and analytics.
 * PRD Section 8.7 - Cost Estimator & Telemetry
 */
class telemetry_service {
    
    /**
     * Get telemetry dashboard data
     * 
     * @param int $days Number of days to look back
     * @return array Dashboard data
     */
    public function get_dashboard_data(int $days = 30): array {
        global $DB;
        
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        return [
            'summary' => $this->get_summary_stats($cutoff),
            'performance' => $this->get_performance_metrics($cutoff),
            'costs' => $this->get_cost_analytics($cutoff),
            'errors' => $this->get_error_analytics($cutoff),
            'nb_performance' => $this->get_nb_performance($cutoff),
            'time_series' => $this->get_time_series_data($cutoff),
            'provider_comparison' => $this->get_provider_comparison($cutoff)
        ];
    }
    
    /**
     * Get summary statistics
     * 
     * @param int $cutoff Timestamp cutoff
     * @return array Summary stats
     */
    protected function get_summary_stats(int $cutoff): array {
        global $DB;
        
        // Total runs
        $totalruns = $DB->count_records_select('local_ci_run', 
            'timecreated > ?', [$cutoff]);
        
        // Successful runs
        $successful = $DB->count_records_select('local_ci_run',
            'timecreated > ? AND status = ?', [$cutoff, 'completed']);
        
        // Failed runs
        $failed = $DB->count_records_select('local_ci_run',
            'timecreated > ? AND status = ?', [$cutoff, 'failed']);
        
        // Average duration
        $avgduration = $DB->get_field_sql(
            "SELECT AVG(timecompleted - timestarted)
             FROM {local_ci_run}
             WHERE timecreated > ? AND status = 'completed' 
               AND timecompleted IS NOT NULL",
            [$cutoff]
        );
        
        // Total cost
        $totalcost = $DB->get_field_sql(
            "SELECT SUM(actualcost)
             FROM {local_ci_run}
             WHERE timecreated > ? AND actualcost IS NOT NULL",
            [$cutoff]
        );
        
        // Total tokens
        $totaltokens = $DB->get_field_sql(
            "SELECT SUM(actualtokens)
             FROM {local_ci_run}
             WHERE timecreated > ? AND actualtokens IS NOT NULL",
            [$cutoff]
        );
        
        return [
            'total_runs' => $totalruns,
            'successful_runs' => $successful,
            'failed_runs' => $failed,
            'success_rate' => $totalruns > 0 ? 
                round(($successful / $totalruns) * 100, 1) : 0,
            'avg_duration_seconds' => round($avgduration ?: 0),
            'total_cost' => round($totalcost ?: 0, 2),
            'total_tokens' => $totaltokens ?: 0,
            'avg_cost_per_run' => $totalruns > 0 ? 
                round(($totalcost ?: 0) / $totalruns, 2) : 0
        ];
    }
    
    /**
     * Get performance metrics
     * 
     * @param int $cutoff Timestamp cutoff
     * @return array Performance data
     */
    protected function get_performance_metrics(int $cutoff): array {
        global $DB;
        
        // NB completion times
        $sql = "SELECT nbcode, 
                       AVG(durationms) as avg_duration,
                       MIN(durationms) as min_duration,
                       MAX(durationms) as max_duration,
                       COUNT(*) as count
                FROM {local_ci_nb_result}
                WHERE timecreated > ? AND status = 'completed'
                GROUP BY nbcode
                ORDER BY nbcode";
        
        $nbmetrics = $DB->get_records_sql($sql, [$cutoff]);
        
        // Queue performance
        $queuewait = $DB->get_field_sql(
            "SELECT AVG(timestarted - timecreated)
             FROM {local_ci_run}
             WHERE timecreated > ? AND timestarted IS NOT NULL",
            [$cutoff]
        );
        
        // Retry statistics
        $retries = $DB->get_records_sql(
            "SELECT COUNT(*) as retry_count,
                    COUNT(DISTINCT runid) as runs_with_retries
             FROM {local_ci_telemetry}
             WHERE timecreated > ? AND metrickey = 'retry_attempt'",
            [$cutoff]
        );
        
        return [
            'nb_metrics' => array_values($nbmetrics),
            'avg_queue_wait' => round($queuewait ?: 0),
            'total_retries' => $retries ? $retries[0]->retry_count : 0,
            'runs_with_retries' => $retries ? $retries[0]->runs_with_retries : 0
        ];
    }
    
    /**
     * Get cost analytics
     * 
     * @param int $cutoff Timestamp cutoff
     * @return array Cost data
     */
    protected function get_cost_analytics(int $cutoff): array {
        global $DB;
        
        // Cost variance analysis
        $sql = "SELECT AVG((actualcost - estcost) / estcost) * 100 as avg_variance,
                       MAX(actualcost - estcost) as max_overrun,
                       MIN(actualcost - estcost) as max_underrun
                FROM {local_ci_run}
                WHERE timecreated > ? 
                  AND actualcost IS NOT NULL 
                  AND estcost > 0";
        
        $variance = $DB->get_record_sql($sql, [$cutoff]);
        
        // Cost by company type
        $sql = "SELECT c.type,
                       COUNT(r.id) as run_count,
                       AVG(r.actualcost) as avg_cost,
                       SUM(r.actualcost) as total_cost
                FROM {local_ci_run} r
                JOIN {local_ci_company} c ON r.companyid = c.id
                WHERE r.timecreated > ? AND r.actualcost IS NOT NULL
                GROUP BY c.type";
        
        $costbytype = $DB->get_records_sql($sql, [$cutoff]);
        
        // Cost by mode
        $sql = "SELECT mode,
                       COUNT(*) as run_count,
                       AVG(actualcost) as avg_cost,
                       SUM(actualcost) as total_cost
                FROM {local_ci_run}
                WHERE timecreated > ? AND actualcost IS NOT NULL
                GROUP BY mode";
        
        $costbymode = $DB->get_records_sql($sql, [$cutoff]);
        
        return [
            'variance' => [
                'avg_variance_pct' => round($variance->avg_variance ?: 0, 2),
                'max_overrun' => round($variance->max_overrun ?: 0, 2),
                'max_underrun' => round(abs($variance->max_underrun ?: 0), 2)
            ],
            'by_company_type' => array_values($costbytype),
            'by_mode' => array_values($costbymode)
        ];
    }
    
    /**
     * Get error analytics
     * 
     * @param int $cutoff Timestamp cutoff
     * @return array Error data
     */
    protected function get_error_analytics(int $cutoff): array {
        global $DB;
        
        // Error frequency by NB
        $sql = "SELECT nbcode, COUNT(*) as error_count
                FROM {local_ci_nb_result}
                WHERE timecreated > ? AND status = 'failed'
                GROUP BY nbcode
                ORDER BY error_count DESC";
        
        $nberrors = $DB->get_records_sql($sql, [$cutoff]);
        
        // Common error types
        $sql = "SELECT metrickey, COUNT(*) as count
                FROM {local_ci_telemetry}
                WHERE timecreated > ? 
                  AND metrickey LIKE 'event_run_error'
                GROUP BY metrickey
                ORDER BY count DESC
                LIMIT 10";
        
        $errortypes = $DB->get_records_sql($sql, [$cutoff]);
        
        // Failure rate by hour of day
        $sql = "SELECT HOUR(FROM_UNIXTIME(timecreated)) as hour,
                       COUNT(*) as total,
                       SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failures
                FROM {local_ci_run}
                WHERE timecreated > ?
                GROUP BY HOUR(FROM_UNIXTIME(timecreated))
                ORDER BY hour";
        
        $failurebyhour = $DB->get_records_sql($sql, [$cutoff]);
        
        return [
            'nb_errors' => array_values($nberrors),
            'error_types' => array_values($errortypes),
            'failure_by_hour' => array_values($failurebyhour)
        ];
    }
    
    /**
     * Get NB performance breakdown
     * 
     * @param int $cutoff Timestamp cutoff
     * @return array NB performance data
     */
    protected function get_nb_performance(int $cutoff): array {
        global $DB;
        
        $nbdata = [];
        
        // For each NB, get detailed metrics
        for ($i = 1; $i <= 15; $i++) {
            $nbcode = 'NB' . $i;
            
            $sql = "SELECT COUNT(*) as total_executions,
                           AVG(tokensused) as avg_tokens,
                           AVG(durationms) as avg_duration,
                           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                           SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM {local_ci_nb_result}
                    WHERE nbcode = ? AND timecreated > ?";
            
            $metrics = $DB->get_record_sql($sql, [$nbcode, $cutoff]);
            
            if ($metrics && $metrics->total_executions > 0) {
                $nbdata[$nbcode] = [
                    'executions' => $metrics->total_executions,
                    'avg_tokens' => round($metrics->avg_tokens ?: 0),
                    'avg_duration_ms' => round($metrics->avg_duration ?: 0),
                    'success_rate' => round(
                        ($metrics->successful / $metrics->total_executions) * 100, 1
                    )
                ];
            }
        }
        
        return $nbdata;
    }
    
    /**
     * Get time series data for charts
     * 
     * @param int $cutoff Timestamp cutoff
     * @return array Time series data
     */
    protected function get_time_series_data(int $cutoff): array {
        global $DB;
        
        // Daily aggregation
        $sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date,
                       COUNT(*) as run_count,
                       SUM(actualcost) as total_cost,
                       SUM(actualtokens) as total_tokens,
                       AVG(timecompleted - timestarted) as avg_duration
                FROM {local_ci_run}
                WHERE timecreated > ? AND status = 'completed'
                GROUP BY DATE(FROM_UNIXTIME(timecreated))
                ORDER BY date";
        
        $daily = $DB->get_records_sql($sql, [$cutoff]);
        
        // Hourly distribution for last 7 days
        $recent = time() - (7 * 24 * 60 * 60);
        $sql = "SELECT HOUR(FROM_UNIXTIME(timecreated)) as hour,
                       COUNT(*) as count
                FROM {local_ci_run}
                WHERE timecreated > ?
                GROUP BY HOUR(FROM_UNIXTIME(timecreated))
                ORDER BY hour";
        
        $hourly = $DB->get_records_sql($sql, [$recent]);
        
        return [
            'daily' => array_values($daily),
            'hourly_distribution' => array_values($hourly)
        ];
    }
    
    /**
     * Get provider comparison data
     * 
     * @param int $cutoff Timestamp cutoff
     * @return array Provider comparison
     */
    protected function get_provider_comparison(int $cutoff): array {
        global $DB;
        
        // Get metrics by provider
        $sql = "SELECT payload->>'$.provider' as provider,
                       COUNT(*) as run_count,
                       AVG(metricvaluenum) as avg_cost
                FROM {local_ci_telemetry}
                WHERE timecreated > ? 
                  AND metrickey = 'actual_cost'
                  AND payload IS NOT NULL
                GROUP BY payload->>'$.provider'";
        
        $providers = $DB->get_records_sql($sql, [$cutoff]);
        
        return array_values($providers);
    }
    
    /**
     * Get real-time queue status
     * 
     * @return array Queue status
     */
    public function get_queue_status(): array {
        global $DB;
        
        $status = [];
        
        // Current queue depth
        $status['queued'] = $DB->count_records('local_ci_run', ['status' => 'queued']);
        $status['running'] = $DB->count_records('local_ci_run', ['status' => 'running']);
        $status['retrying'] = $DB->count_records('local_ci_run', ['status' => 'retrying']);
        
        // Oldest queued item
        $oldest = $DB->get_field_sql(
            "SELECT MIN(timecreated) 
             FROM {local_ci_run} 
             WHERE status = 'queued'"
        );
        
        $status['oldest_queued_age'] = $oldest ? time() - $oldest : 0;
        
        // Currently executing NBs
        $executing = $DB->get_records_sql(
            "SELECT r.id, r.companyid, c.name, 
                    (SELECT nbcode FROM {local_ci_nb_result} 
                     WHERE runid = r.id AND status = 'running' 
                     ORDER BY id DESC LIMIT 1) as current_nb
             FROM {local_ci_run} r
             JOIN {local_ci_company} c ON r.companyid = c.id
             WHERE r.status = 'running'"
        );
        
        $status['executing'] = array_values($executing);
        
        return $status;
    }
    
    /**
     * Get cost estimation accuracy metrics
     * 
     * @param int $days Number of days to analyze
     * @return array Accuracy metrics
     */
    public function get_estimation_accuracy(int $days = 30): array {
        global $DB;
        
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        // Get runs with both estimates and actuals
        $sql = "SELECT estcost, actualcost, esttokens, actualtokens
                FROM {local_ci_run}
                WHERE timecreated > ?
                  AND status = 'completed'
                  AND estcost > 0
                  AND actualcost IS NOT NULL";
        
        $runs = $DB->get_records_sql($sql, [$cutoff]);
        
        if (empty($runs)) {
            return [
                'sample_size' => 0,
                'cost_accuracy' => 0,
                'token_accuracy' => 0
            ];
        }
        
        $costdiffs = [];
        $tokendiffs = [];
        
        foreach ($runs as $run) {
            $costdiff = abs(($run->actualcost - $run->estcost) / $run->estcost) * 100;
            $costdiffs[] = $costdiff;
            
            if ($run->esttokens > 0 && $run->actualtokens > 0) {
                $tokendiff = abs(($run->actualtokens - $run->esttokens) / $run->esttokens) * 100;
                $tokendiffs[] = $tokendiff;
            }
        }
        
        return [
            'sample_size' => count($runs),
            'cost_accuracy' => [
                'avg_error_pct' => round(array_sum($costdiffs) / count($costdiffs), 2),
                'max_error_pct' => round(max($costdiffs), 2),
                'min_error_pct' => round(min($costdiffs), 2),
                'within_10_pct' => count(array_filter($costdiffs, fn($d) => $d <= 10)),
                'within_20_pct' => count(array_filter($costdiffs, fn($d) => $d <= 20))
            ],
            'token_accuracy' => !empty($tokendiffs) ? [
                'avg_error_pct' => round(array_sum($tokendiffs) / count($tokendiffs), 2),
                'max_error_pct' => round(max($tokendiffs), 2),
                'min_error_pct' => round(min($tokendiffs), 2)
            ] : null
        ];
    }
    
    /**
     * Export telemetry data for analysis
     * 
     * @param int $runid Optional run ID
     * @param int $days Number of days if no run ID
     * @return array Telemetry data
     */
    public function export_telemetry(int $runid = null, int $days = 30): array {
        global $DB;
        
        if ($runid) {
            $telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        } else {
            $cutoff = time() - ($days * 24 * 60 * 60);
            $telemetry = $DB->get_records_select('local_ci_telemetry', 
                'timecreated > ?', [$cutoff]);
        }
        
        $export = [];
        foreach ($telemetry as $entry) {
            $export[] = [
                'run_id' => $entry->runid,
                'metric' => $entry->metrickey,
                'value' => $entry->metricvaluenum,
                'payload' => json_decode($entry->payload, true),
                'timestamp' => $entry->timecreated
            ];
        }
        
        return $export;
    }
}