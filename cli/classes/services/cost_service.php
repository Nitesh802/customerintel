<?php
/**
 * Cost Service - Estimates and tracks costs
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * CostService class
 * 
 * Handles cost estimation, actual tracking, thresholds, and variance analysis.
 * PRD Section 11 - Architecture Overview / Key Services
 */
class cost_service {
    
    /** @var array Provider pricing per 1K tokens */
    const PROVIDER_PRICING = [
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
        'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],
        'perplexity' => ['search' => 0.005] // Per search call
    ];
    
    /** @var array Average tokens per NB from historical data */
    const AVG_TOKENS_PER_NB = [
        'NB1' => ['input' => 1500, 'output' => 800],
        'NB2' => ['input' => 1800, 'output' => 1000],
        'NB3' => ['input' => 2000, 'output' => 1200],
        'NB4' => ['input' => 1600, 'output' => 900],
        'NB5' => ['input' => 2200, 'output' => 1100],
        'NB6' => ['input' => 1700, 'output' => 850],
        'NB7' => ['input' => 1900, 'output' => 950],
        'NB8' => ['input' => 2100, 'output' => 1050],
        'NB9' => ['input' => 1800, 'output' => 900],
        'NB10' => ['input' => 2000, 'output' => 1000],
        'NB11' => ['input' => 1600, 'output' => 800],
        'NB12' => ['input' => 1700, 'output' => 850],
        'NB13' => ['input' => 1500, 'output' => 750],
        'NB14' => ['input' => 2500, 'output' => 1500],
        'NB15' => ['input' => 2800, 'output' => 1600]
    ];
    
    /**
     * Estimate cost for run
     * 
     * @param int $customerid Customer company ID
     * @param int $targetid Target company ID (optional for single company analysis)
     * @param bool $forcerefresh Force fresh data collection
     * @return array Cost estimate details
     * 
     * Implements per PRD Section 8.7 (Cost Estimator & Telemetry) and Section 16 (Cost Estimator & Controls)
     */
    public function estimate_cost(int $customerid, int $targetid = null, bool $forcerefresh = false): array {
        global $DB;
        
        $estimate = [
            'total_cost' => 0.0,
            'total_tokens' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'breakdown' => [],
            'warnings' => [],
            'can_proceed' => true,
            'reuse_savings' => 0.0
        ];
        
        // Check for reusable data if not forcing refresh
        if (!$forcerefresh) {
            $reusable = $this->check_reusable_data($customerid);
            if ($reusable['can_reuse']) {
                $estimate['reuse_savings'] = $reusable['savings'];
                $estimate['reused_nbs'] = $reusable['reused_nbs'];
            }
        }
        
        // Get provider and calibration data
        $provider = $this->get_configured_provider();
        $calibration = $this->get_calibration_factors();
        
        // Calculate tokens per NB with calibration
        foreach (self::AVG_TOKENS_PER_NB as $nbcode => $tokens) {
            // Skip if NB can be reused
            if (isset($estimate['reused_nbs']) && in_array($nbcode, $estimate['reused_nbs'])) {
                continue;
            }
            
            $adjusted_input = $tokens['input'] * $calibration['input_factor'];
            $adjusted_output = $tokens['output'] * $calibration['output_factor'];
            
            // Add source retrieval overhead (10% for input)
            $adjusted_input *= 1.1;
            
            $nb_cost = $this->calculate_cost($adjusted_input, $provider, 'input') +
                       $this->calculate_cost($adjusted_output, $provider, 'output');
            
            $estimate['breakdown'][$nbcode] = [
                'input_tokens' => (int)$adjusted_input,
                'output_tokens' => (int)$adjusted_output,
                'cost' => $nb_cost
            ];
            
            $estimate['input_tokens'] += (int)$adjusted_input;
            $estimate['output_tokens'] += (int)$adjusted_output;
            $estimate['total_cost'] += $nb_cost;
        }
        
        $estimate['total_tokens'] = $estimate['input_tokens'] + $estimate['output_tokens'];
        
        // If comparing two companies, double the cost
        if ($targetid) {
            $estimate['total_cost'] *= 2;
            $estimate['total_tokens'] *= 2;
            $estimate['input_tokens'] *= 2;
            $estimate['output_tokens'] *= 2;
            $estimate['is_comparison'] = true;
        }
        
        // Check thresholds
        $this->check_thresholds($estimate);
        
        // Add provider info
        $estimate['provider'] = $provider;
        $estimate['pricing'] = self::PROVIDER_PRICING[$provider] ?? [];
        
        return $estimate;
    }
    
    /**
     * Record actual costs after run completion
     * 
     * @param int $runid Run ID
     * @param int $totaltokens Total tokens used
     * @param float $totalcost Total cost
     * @param array $nbbreakdown Per-NB breakdown
     * @return void
     * 
     * Implements per PRD Section 8.7
     */
    public function record_actuals(int $runid, int $totaltokens, float $totalcost, array $nbbreakdown = []): void {
        global $DB;
        
        // Update run record with actuals
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        $run->actualtokens = $totaltokens;
        $run->actualcost = $totalcost;
        $DB->update_record('local_ci_run', $run);
        
        // Calculate variance from estimate
        $variance = 0;
        if ($run->estcost > 0) {
            $variance = $this->calculate_variance($run->estcost, $totalcost);
        }
        
        // Store summary telemetry
        $this->record_telemetry($runid, 'actual_cost', $totalcost, [
            'estimated_cost' => $run->estcost,
            'variance_pct' => $variance,
            'total_tokens' => $totaltokens,
            'provider' => $this->get_configured_provider()
        ]);
        
        $this->record_telemetry($runid, 'actual_tokens', $totaltokens, [
            'estimated_tokens' => $run->esttokens,
            'variance_pct' => $run->esttokens > 0 ? 
                (($totaltokens - $run->esttokens) / $run->esttokens) * 100 : 0
        ]);
        
        // Store per-NB telemetry if breakdown provided
        foreach ($nbbreakdown as $nbcode => $nbdata) {
            $this->record_telemetry($runid, "nb_{$nbcode}_tokens", $nbdata['tokens'] ?? 0, [
                'nbcode' => $nbcode,
                'duration_ms' => $nbdata['duration_ms'] ?? null,
                'cost' => $nbdata['cost'] ?? null,
                'input_tokens' => $nbdata['input_tokens'] ?? null,
                'output_tokens' => $nbdata['output_tokens'] ?? null
            ]);
        }
        
        // Update calibration data
        $this->update_calibration_data($runid, $totaltokens, $totalcost, $nbbreakdown);
    }
    
    /**
     * Record telemetry entry
     * 
     * @param int $runid Run ID
     * @param string $metrickey Metric key
     * @param float $value Numeric value
     * @param array $payload Additional data
     * @return void
     */
    public function record_telemetry(int $runid, string $metrickey, float $value, array $payload = []): void {
        global $DB;
        
        $telemetry = new \stdClass();
        $telemetry->runid = $runid;
        $telemetry->metrickey = $metrickey;
        $telemetry->metricvaluenum = $value;
        $telemetry->payload = !empty($payload) ? json_encode($payload) : null;
        $telemetry->timecreated = time();
        
        $DB->insert_record('local_ci_telemetry', $telemetry);
    }
    
    /**
     * Calculate cost variance
     * 
     * @param float $estimated Estimated cost
     * @param float $actual Actual cost
     * @return float Variance percentage
     */
    public function calculate_variance(float $estimated, float $actual): float {
        if ($estimated == 0) {
            return $actual > 0 ? 100 : 0;
        }
        
        return round((($actual - $estimated) / $estimated) * 100, 2);
    }
    
    /**
     * Check cost thresholds and enforce limits
     * 
     * @param array $estimate Cost estimate
     * @return array Warnings and blocks
     * 
     * Implements per PRD Section 16
     */
    protected function check_thresholds(array &$estimate): array {
        // Get thresholds from settings
        $warn_threshold = (float)get_config('local_customerintel', 'cost_warning_threshold') ?: 10.0;
        $hard_limit = (float)get_config('local_customerintel', 'cost_hard_limit') ?: 50.0;
        
        $estimate['thresholds'] = [
            'warning' => $warn_threshold,
            'limit' => $hard_limit
        ];
        
        if ($estimate['total_cost'] > $warn_threshold) {
            $estimate['warnings'][] = [
                'type' => 'warning',
                'message' => sprintf(
                    'Estimated cost ($%.2f) exceeds warning threshold ($%.2f)',
                    $estimate['total_cost'],
                    $warn_threshold
                ),
                'threshold' => $warn_threshold,
                'exceeded_by' => $estimate['total_cost'] - $warn_threshold
            ];
        }
        
        if ($estimate['total_cost'] > $hard_limit) {
            $estimate['warnings'][] = [
                'type' => 'error',
                'message' => sprintf(
                    'Estimated cost ($%.2f) exceeds hard limit ($%.2f). Run will be blocked.',
                    $estimate['total_cost'],
                    $hard_limit
                ),
                'threshold' => $hard_limit,
                'exceeded_by' => $estimate['total_cost'] - $hard_limit,
                'block_run' => true
            ];
            $estimate['can_proceed'] = false;
        }
        
        return $estimate['warnings'];
    }
    
    /**
     * Get cost history for calibration
     * 
     * @param int $limit Number of recent runs
     * @return array Historical cost data
     */
    public function get_cost_history(int $limit = 100): array {
        global $DB;
        
        // Get recent completed runs with estimates and actuals
        $sql = "SELECT r.*, 
                       COUNT(t.id) as telemetry_count
                FROM {local_ci_run} r
                LEFT JOIN {local_ci_telemetry} t ON r.id = t.runid
                WHERE r.status = 'completed' 
                  AND r.actualcost IS NOT NULL
                  AND r.estcost IS NOT NULL
                GROUP BY r.id
                ORDER BY r.timecompleted DESC";
        
        $runs = $DB->get_records_sql($sql, [], 0, $limit);
        
        $history = [];
        foreach ($runs as $run) {
            $variance = $this->calculate_variance($run->estcost, $run->actualcost);
            
            $history[] = [
                'run_id' => $run->id,
                'company_id' => $run->companyid,
                'date' => $run->timecompleted,
                'estimated_cost' => $run->estcost,
                'actual_cost' => $run->actualcost,
                'estimated_tokens' => $run->esttokens,
                'actual_tokens' => $run->actualtokens,
                'variance_pct' => $variance,
                'mode' => $run->mode,
                'duration' => $run->timecompleted - $run->timestarted
            ];
        }
        
        // Calculate summary statistics
        if (!empty($history)) {
            $variances = array_column($history, 'variance_pct');
            $summary = [
                'total_runs' => count($history),
                'avg_variance' => round(array_sum($variances) / count($variances), 2),
                'max_variance' => max($variances),
                'min_variance' => min($variances),
                'total_cost' => array_sum(array_column($history, 'actual_cost')),
                'avg_cost' => round(array_sum(array_column($history, 'actual_cost')) / count($history), 2)
            ];
        } else {
            $summary = [
                'total_runs' => 0,
                'avg_variance' => 0,
                'max_variance' => 0,
                'min_variance' => 0,
                'total_cost' => 0,
                'avg_cost' => 0
            ];
        }
        
        return [
            'runs' => $history,
            'summary' => $summary
        ];
    }
    
    /**
     * Get calibration factors based on historical data
     * 
     * @return array Calibration factors
     */
    protected function get_calibration_factors(): array {
        global $DB;
        
        // Get average variance from recent runs
        $sql = "SELECT AVG((actualtokens - esttokens) / esttokens) as token_variance
                FROM {local_ci_run}
                WHERE status = 'completed'
                  AND actualtokens > 0 
                  AND esttokens > 0
                  AND timecompleted > ?";
        
        // Look at last 30 days
        $cutoff = time() - (30 * 24 * 60 * 60);
        $result = $DB->get_record_sql($sql, [$cutoff]);
        
        $variance = $result ? $result->token_variance : 0;
        
        // Calculate calibration factor (1.0 + average overrun)
        $factor = 1.0 + max(0, $variance);
        
        // Separate input/output calibration if we have detailed telemetry
        $input_factor = $factor;
        $output_factor = $factor;
        
        // Try to get more specific calibration from telemetry
        $telemetry_sql = "SELECT metrickey, AVG(metricvaluenum) as avg_value
                          FROM {local_ci_telemetry}
                          WHERE metrickey LIKE 'calibration_%'
                          GROUP BY metrickey";
        
        $calibrations = $DB->get_records_sql($telemetry_sql);
        foreach ($calibrations as $cal) {
            if ($cal->metrickey == 'calibration_input_factor') {
                $input_factor = $cal->avg_value ?: $factor;
            } else if ($cal->metrickey == 'calibration_output_factor') {
                $output_factor = $cal->avg_value ?: $factor;
            }
        }
        
        return [
            'input_factor' => max(1.0, min(2.0, $input_factor)), // Cap between 1x and 2x
            'output_factor' => max(1.0, min(2.0, $output_factor)),
            'overall_factor' => $factor,
            'confidence' => $result ? 'calibrated' : 'default'
        ];
    }
    
    /**
     * Update calibration data based on actual results
     * 
     * @param int $runid Run ID
     * @param int $totaltokens Total actual tokens
     * @param float $totalcost Total actual cost
     * @param array $nbbreakdown Per-NB breakdown
     * @return void
     */
    protected function update_calibration_data(int $runid, int $totaltokens, float $totalcost, array $nbbreakdown): void {
        global $DB;
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid]);
        if (!$run || !$run->esttokens) {
            return;
        }
        
        // Calculate new calibration factors
        $actual_factor = $totaltokens / $run->esttokens;
        
        // Store calibration telemetry
        $this->record_telemetry($runid, 'calibration_factor', $actual_factor, [
            'estimated' => $run->esttokens,
            'actual' => $totaltokens,
            'cost_estimated' => $run->estcost,
            'cost_actual' => $totalcost
        ]);
        
        // Update per-NB calibration if we have breakdown
        if (!empty($nbbreakdown)) {
            foreach ($nbbreakdown as $nbcode => $data) {
                if (isset(self::AVG_TOKENS_PER_NB[$nbcode])) {
                    $expected = self::AVG_TOKENS_PER_NB[$nbcode]['input'] + 
                               self::AVG_TOKENS_PER_NB[$nbcode]['output'];
                    $actual = $data['tokens'] ?? 0;
                    
                    if ($expected > 0 && $actual > 0) {
                        $nb_factor = $actual / $expected;
                        $this->record_telemetry($runid, "calibration_nb_{$nbcode}", $nb_factor, [
                            'expected' => $expected,
                            'actual' => $actual
                        ]);
                    }
                }
            }
        }
    }
    
    /**
     * Check for reusable data
     * 
     * @param int $companyid Company ID
     * @return array Reuse information
     */
    protected function check_reusable_data(int $companyid): array {
        global $DB;
        
        // Check for recent snapshot
        $freshness_window = (int)get_config('local_customerintel', 'snapshot_freshness_days') ?: 30;
        $cutoff = time() - ($freshness_window * 24 * 60 * 60);
        
        $sql = "SELECT s.*, r.actualcost, r.actualtokens
                FROM {local_ci_snapshot} s
                JOIN {local_ci_run} r ON s.runid = r.id
                WHERE s.companyid = ?
                  AND s.timecreated > ?
                  AND r.status = 'completed'
                ORDER BY s.timecreated DESC
                LIMIT 1";
        
        $snapshot = $DB->get_record_sql($sql, [$companyid, $cutoff]);
        
        if (!$snapshot) {
            return ['can_reuse' => false];
        }
        
        // Calculate savings from reuse
        $provider = $this->get_configured_provider();
        $total_savings = 0;
        $reused_nbs = [];
        
        // Assume we can reuse all NBs from snapshot
        foreach (self::AVG_TOKENS_PER_NB as $nbcode => $tokens) {
            $nb_cost = $this->calculate_cost($tokens['input'], $provider, 'input') +
                      $this->calculate_cost($tokens['output'], $provider, 'output');
            $total_savings += $nb_cost;
            $reused_nbs[] = $nbcode;
        }
        
        return [
            'can_reuse' => true,
            'snapshot_id' => $snapshot->id,
            'snapshot_date' => $snapshot->timecreated,
            'savings' => $total_savings,
            'reused_nbs' => $reused_nbs,
            'original_cost' => $snapshot->actualcost
        ];
    }
    
    /**
     * Calculate cost with provider
     * 
     * @param int $tokens Number of tokens
     * @param string $provider Provider name
     * @param string $type Type (input/output)
     * @return float Cost in USD
     */
    protected function calculate_cost(int $tokens, string $provider, string $type = 'input'): float {
        if (!isset(self::PROVIDER_PRICING[$provider][$type])) {
            // Default fallback pricing
            return ($tokens / 1000) * 0.02;
        }
        
        $price_per_1k = self::PROVIDER_PRICING[$provider][$type];
        return ($tokens / 1000) * $price_per_1k;
    }
    
    /**
     * Get configured LLM provider
     * 
     * @return string Provider name
     */
    public function get_configured_provider(): string {
        $provider = get_config('local_customerintel', 'llm_provider');
        
        // Default to gpt-4 if not configured
        if (empty($provider) || !isset(self::PROVIDER_PRICING[$provider])) {
            return 'gpt-4';
        }
        
        return $provider;
    }
    
    /**
     * Can run proceed within budget
     * 
     * @param float $estimatedcost Estimated cost
     * @return bool True if within limits
     */
    public function can_proceed(float $estimatedcost): bool {
        $hard_limit = (float)get_config('local_customerintel', 'cost_hard_limit') ?: 50.0;
        return $estimatedcost <= $hard_limit;
    }
    
    /**
     * Get detailed cost report for run
     * 
     * @param int $runid Run ID
     * @return array Cost breakdown
     */
    public function get_run_cost_report(int $runid): array {
        global $DB;
        
        $run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);
        
        // Get all telemetry for run
        $telemetry = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        
        $nb_breakdown = [];
        $total_nb_tokens = 0;
        $total_nb_cost = 0;
        
        foreach ($telemetry as $entry) {
            if (strpos($entry->metrickey, 'nb_NB') === 0) {
                $nbcode = substr($entry->metrickey, 3, 3); // Extract NBx
                $payload = json_decode($entry->payload, true);
                
                $nb_breakdown[$nbcode] = [
                    'tokens' => (int)$entry->metricvaluenum,
                    'cost' => $payload['cost'] ?? 0,
                    'duration_ms' => $payload['duration_ms'] ?? 0,
                    'input_tokens' => $payload['input_tokens'] ?? 0,
                    'output_tokens' => $payload['output_tokens'] ?? 0
                ];
                
                $total_nb_tokens += (int)$entry->metricvaluenum;
                $total_nb_cost += $payload['cost'] ?? 0;
            }
        }
        
        // Calculate variance
        $cost_variance = 0;
        $token_variance = 0;
        
        if ($run->estcost > 0) {
            $cost_variance = $this->calculate_variance($run->estcost, $run->actualcost);
        }
        
        if ($run->esttokens > 0) {
            $token_variance = $this->calculate_variance($run->esttokens, $run->actualtokens);
        }
        
        return [
            'run_id' => $runid,
            'company_id' => $run->companyid,
            'status' => $run->status,
            'mode' => $run->mode,
            'estimated' => [
                'cost' => $run->estcost,
                'tokens' => $run->esttokens
            ],
            'actual' => [
                'cost' => $run->actualcost,
                'tokens' => $run->actualtokens
            ],
            'variance' => [
                'cost_pct' => $cost_variance,
                'cost_amount' => $run->actualcost - $run->estcost,
                'token_pct' => $token_variance,
                'token_amount' => $run->actualtokens - $run->esttokens
            ],
            'nb_breakdown' => $nb_breakdown,
            'provider' => $this->get_configured_provider(),
            'duration' => $run->timecompleted - $run->timestarted,
            'timestamp' => $run->timecompleted
        ];
    }
    
    /**
     * Get dashboard widget data for last N runs
     * 
     * @param int $limit Number of runs to include
     * @return array Dashboard data
     */
    public function get_dashboard_data(int $limit = 10): array {
        global $DB;
        
        // Get last N completed runs with cost data
        $sql = "SELECT r.*, c.name as company_name, c.ticker
                FROM {local_ci_run} r
                JOIN {local_ci_company} c ON r.companyid = c.id
                WHERE r.status = 'completed'
                  AND r.actualcost IS NOT NULL
                ORDER BY r.timecompleted DESC";
        
        $runs = $DB->get_records_sql($sql, [], 0, $limit);
        
        $dashboard_data = [];
        $total_estimated = 0;
        $total_actual = 0;
        
        foreach ($runs as $run) {
            $variance = $run->estcost > 0 ? 
                $this->calculate_variance($run->estcost, $run->actualcost) : 0;
            
            $dashboard_data[] = [
                'run_id' => $run->id,
                'company' => $run->company_name,
                'ticker' => $run->ticker,
                'date' => userdate($run->timecompleted, '%Y-%m-%d %H:%M'),
                'mode' => $run->mode,
                'estimated_cost' => sprintf('$%.2f', $run->estcost),
                'actual_cost' => sprintf('$%.2f', $run->actualcost),
                'variance' => sprintf('%+.1f%%', $variance),
                'variance_class' => $variance > 10 ? 'text-danger' : 
                                   ($variance < -10 ? 'text-success' : 'text-warning'),
                'estimated_tokens' => number_format($run->esttokens),
                'actual_tokens' => number_format($run->actualtokens),
                'duration' => $this->format_duration($run->timecompleted - $run->timestarted)
            ];
            
            $total_estimated += $run->estcost;
            $total_actual += $run->actualcost;
        }
        
        // Calculate summary stats
        $summary = [
            'total_runs' => count($runs),
            'total_estimated' => sprintf('$%.2f', $total_estimated),
            'total_actual' => sprintf('$%.2f', $total_actual),
            'total_variance' => $total_estimated > 0 ? 
                sprintf('%+.1f%%', $this->calculate_variance($total_estimated, $total_actual)) : '0.0%',
            'avg_cost' => count($runs) > 0 ? 
                sprintf('$%.2f', $total_actual / count($runs)) : '$0.00',
            'provider' => $this->get_configured_provider()
        ];
        
        return [
            'runs' => $dashboard_data,
            'summary' => $summary
        ];
    }
    
    /**
     * Format duration in human-readable format
     * 
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    protected function format_duration(int $seconds): string {
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return "{$minutes}m {$secs}s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }
    
    /**
     * Calculate cost for tokens
     * 
     * @param int $tokens Number of tokens
     * @param string $type Type (input/output)
     * @return float Cost in USD
     */
    public function calculate_token_cost(int $tokens, string $type = 'output'): float {
        $provider = $this->get_configured_provider();
        return $this->calculate_cost($tokens, $provider, $type);
    }
}