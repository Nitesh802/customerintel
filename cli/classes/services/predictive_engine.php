<?php
/**
 * Predictive Engine for Customer Intelligence Dashboard (Slice 11)
 * 
 * Provides predictive analytics, anomaly detection, and risk assessment
 * using historical telemetry and QA data
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Predictive Engine Service - Forward-looking Intelligence Analysis
 * 
 * Provides forecasting, anomaly detection, and risk ranking capabilities
 */
class predictive_engine {
    
    /**
     * @var \moodle_database Database connection
     */
    private $db;
    
    /**
     * @var bool Predictive engine enabled flag
     */
    private $predictive_enabled;
    
    /**
     * @var bool Anomaly alerts enabled flag
     */
    private $anomaly_alerts_enabled;
    
    /**
     * @var bool Safe mode flag for limited functionality
     */
    private $safe_mode_enabled;
    
    /**
     * @var int Forecast horizon in days
     */
    private $forecast_horizon_days;
    
    /**
     * @var array Supported metrics for prediction
     */
    private $supported_metrics = [
        'qa_score_total',
        'coherence_score', 
        'pattern_alignment_score',
        'total_duration_ms',
        'synth_citation_count'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
        
        // Load feature flags
        $this->predictive_enabled = get_config('local_customerintel', 'enable_predictive_engine') !== '0';
        $this->anomaly_alerts_enabled = get_config('local_customerintel', 'enable_anomaly_alerts') !== '0';
        $this->safe_mode_enabled = get_config('local_customerintel', 'enable_safe_mode') === '1';
        $this->forecast_horizon_days = intval(get_config('local_customerintel', 'forecast_horizon_days') ?: 30);
    }
    
    /**
     * Forecast metric trend using linear regression
     * 
     * @param string $metrickey Metric to forecast
     * @param int $days_ahead Number of days to forecast (default 30)
     * @return array Forecast data with predictions and confidence intervals
     */
    public function forecast_metric_trend($metrickey, $days_ahead = 30) {
        if (!$this->predictive_enabled || $this->safe_mode_enabled) {
            return ['error' => 'Predictive engine disabled'];
        }
        
        if (!in_array($metrickey, $this->supported_metrics)) {
            return ['error' => 'Unsupported metric for forecasting'];
        }
        
        // Apply safe mode limits
        if ($this->safe_mode_enabled && $days_ahead > 7) {
            $days_ahead = 7;
        }
        
        try {
            // Get historical data for the last 90 days
            $cutoff_time = time() - (90 * 86400);
            
            $sql = "SELECT DATE(FROM_UNIXTIME(t.timecreated)) as metric_date,
                           AVG(t.metricvaluenum) as avg_value,
                           COUNT(t.id) as sample_count,
                           UNIX_TIMESTAMP(DATE(FROM_UNIXTIME(t.timecreated))) as date_timestamp
                    FROM {local_ci_telemetry} t
                    INNER JOIN {local_ci_run} r ON r.id = t.runid
                    WHERE t.metrickey = ?
                    AND t.timecreated >= ?
                    AND r.status = 'completed'
                    GROUP BY DATE(FROM_UNIXTIME(t.timecreated))
                    HAVING sample_count >= 2
                    ORDER BY metric_date ASC";
            
            $historical_data = $this->db->get_records_sql($sql, [$metrickey, $cutoff_time]);
            
            if (count($historical_data) < 7) {
                return ['error' => 'Insufficient historical data for forecasting (minimum 7 days required)'];
            }
            
            // Prepare data for linear regression
            $x_values = [];
            $y_values = [];
            $dates = [];
            
            $day_counter = 0;
            foreach ($historical_data as $data_point) {
                $x_values[] = $day_counter;
                $y_values[] = $data_point->avg_value;
                $dates[] = $data_point->metric_date;
                $day_counter++;
            }
            
            // Calculate linear regression
            $regression = $this->calculate_linear_regression($x_values, $y_values);
            
            // Generate forecast
            $forecast_data = [
                'metrickey' => $metrickey,
                'historical' => [
                    'labels' => $dates,
                    'values' => $y_values
                ],
                'forecast' => [
                    'labels' => [],
                    'values' => [],
                    'confidence_upper' => [],
                    'confidence_lower' => []
                ],
                'regression' => $regression,
                'forecast_horizon_days' => $days_ahead
            ];
            
            // Calculate standard error for confidence intervals
            $residuals = [];
            for ($i = 0; $i < count($y_values); $i++) {
                $predicted = $regression['slope'] * $x_values[$i] + $regression['intercept'];
                $residuals[] = abs($y_values[$i] - $predicted);
            }
            $standard_error = $this->calculate_standard_deviation($residuals);
            
            // Generate future predictions
            $last_timestamp = max(array_map(function($data) { return $data->date_timestamp; }, $historical_data));
            
            for ($day = 1; $day <= $days_ahead; $day++) {
                $x_future = count($x_values) + $day - 1;
                $predicted_value = $regression['slope'] * $x_future + $regression['intercept'];
                
                // Confidence interval (±2 standard errors ≈ 95% confidence)
                $confidence_margin = 2 * $standard_error * sqrt(1 + 1/count($y_values) + pow($x_future - array_sum($x_values)/count($x_values), 2) / array_sum(array_map(function($x) use ($x_values) { return pow($x - array_sum($x_values)/count($x_values), 2); }, $x_values)));
                
                $future_date = date('Y-m-d', $last_timestamp + ($day * 86400));
                
                $forecast_data['forecast']['labels'][] = $future_date;
                $forecast_data['forecast']['values'][] = round($predicted_value, 4);
                $forecast_data['forecast']['confidence_upper'][] = round($predicted_value + $confidence_margin, 4);
                $forecast_data['forecast']['confidence_lower'][] = round(max(0, $predicted_value - $confidence_margin), 4);
            }
            
            return $forecast_data;
            
        } catch (\Exception $e) {
            debugging('Failed to forecast metric trend: ' . $e->getMessage(), DEBUG_NORMAL);
            return ['error' => 'Forecasting failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Detect anomalies in metric data using z-score analysis
     * 
     * @param string $metrickey Metric to analyze
     * @param float $threshold Z-score threshold for anomaly detection (default 2.0)
     * @return array Array of detected anomalies with details
     */
    public function detect_anomalies($metrickey, $threshold = 2.0) {
        if (!$this->predictive_enabled) {
            return [];
        }
        
        if (!in_array($metrickey, $this->supported_metrics)) {
            return [];
        }
        
        try {
            // Get data from last 30 days for anomaly detection
            $cutoff_time = time() - (30 * 86400);
            
            $sql = "SELECT t.id, t.runid, t.metricvaluenum, t.timecreated,
                           r.companyid, c.name as company_name
                    FROM {local_ci_telemetry} t
                    INNER JOIN {local_ci_run} r ON r.id = t.runid
                    LEFT JOIN {local_ci_company} c ON c.id = r.companyid
                    WHERE t.metrickey = ?
                    AND t.timecreated >= ?
                    AND r.status = 'completed'
                    ORDER BY t.timecreated DESC";
            
            $metric_data = $this->db->get_records_sql($sql, [$metrickey, $cutoff_time]);
            
            if (count($metric_data) < 10) {
                return []; // Need minimum data for meaningful anomaly detection
            }
            
            // Calculate statistical measures
            $values = array_map(function($data) { return $data->metricvaluenum; }, $metric_data);
            $mean = array_sum($values) / count($values);
            $std_dev = $this->calculate_standard_deviation($values);
            
            if ($std_dev == 0) {
                return []; // No variance means no anomalies
            }
            
            // Detect anomalies
            $anomalies = [];
            foreach ($metric_data as $data_point) {
                $z_score = abs(($data_point->metricvaluenum - $mean) / $std_dev);
                
                if ($z_score >= $threshold) {
                    $possible_cause = $this->analyze_anomaly_cause($metrickey, $data_point, $mean);
                    
                    $anomaly = [
                        'id' => $data_point->id,
                        'runid' => $data_point->runid,
                        'metrickey' => $metrickey,
                        'value' => round($data_point->metricvaluenum, 4),
                        'expected_value' => round($mean, 4),
                        'deviation' => round($data_point->metricvaluenum - $mean, 4),
                        'z_score' => round($z_score, 2),
                        'severity' => $this->classify_anomaly_severity($z_score),
                        'timestamp' => $data_point->timecreated,
                        'date' => date('Y-m-d H:i:s', $data_point->timecreated),
                        'company_name' => $data_point->company_name ?? 'Unknown',
                        'possible_cause' => $possible_cause
                    ];
                    
                    $anomalies[] = $anomaly;
                    
                    // Log anomaly to telemetry and trigger event
                    $this->log_anomaly_detection($anomaly);
                    $this->trigger_anomaly_event($anomaly);
                }
            }
            
            // Sort by z-score (most severe first)
            usort($anomalies, function($a, $b) {
                return $b['z_score'] <=> $a['z_score'];
            });
            
            return array_slice($anomalies, 0, 20); // Return top 20 anomalies
            
        } catch (\Exception $e) {
            debugging('Failed to detect anomalies: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Rank risk signals across multiple metrics for recent runs
     * 
     * @param array $run_data Array of run data to analyze (optional, uses recent runs if empty)
     * @return array Ranked risk signals with scores and recommendations
     */
    public function rank_risk_signals($run_data = []) {
        if (!$this->predictive_enabled) {
            return [];
        }
        
        try {
            // If no run data provided, get recent runs
            if (empty($run_data)) {
                $run_data = $this->get_recent_run_data(50);
            }
            
            $risk_signals = [];
            
            foreach ($this->supported_metrics as $metric) {
                $anomalies = $this->detect_anomalies($metric, 1.5); // Lower threshold for risk assessment
                
                if (!empty($anomalies)) {
                    $recent_anomalies = array_filter($anomalies, function($anomaly) {
                        return $anomaly['timestamp'] > (time() - (7 * 86400)); // Last 7 days
                    });
                    
                    if (!empty($recent_anomalies)) {
                        $avg_z_score = array_sum(array_column($recent_anomalies, 'z_score')) / count($recent_anomalies);
                        $frequency = count($recent_anomalies);
                        
                        $risk_score = $this->calculate_risk_score($avg_z_score, $frequency, $metric);
                        $recommendation = $this->generate_risk_recommendation($metric, $recent_anomalies);
                        
                        $risk_signals[] = [
                            'metric' => $metric,
                            'metric_display_name' => $this->format_metric_name($metric),
                            'risk_score' => round($risk_score, 2),
                            'severity' => $this->classify_risk_severity($risk_score),
                            'anomaly_count' => $frequency,
                            'avg_z_score' => round($avg_z_score, 2),
                            'last_anomaly_date' => max(array_column($recent_anomalies, 'timestamp')),
                            'recommendation' => $recommendation,
                            'recent_anomalies' => array_slice($recent_anomalies, 0, 3) // Top 3 recent anomalies
                        ];
                    }
                }
            }
            
            // Sort by risk score (highest first)
            usort($risk_signals, function($a, $b) {
                return $b['risk_score'] <=> $a['risk_score'];
            });
            
            return array_slice($risk_signals, 0, 5); // Return top 5 risk signals
            
        } catch (\Exception $e) {
            debugging('Failed to rank risk signals: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Log anomaly detection event to telemetry
     * 
     * @param array $anomaly Anomaly data
     */
    public function log_anomaly_detection($anomaly) {
        if (!$this->anomaly_alerts_enabled) {
            return;
        }
        
        try {
            require_once(__DIR__ . '/telemetry_logger.php');
            $telemetry = new telemetry_logger();
            
            $telemetry->log_metric(
                $anomaly['runid'] ?? 0,
                'anomaly_detected',
                $anomaly['z_score'],
                [
                    'metric' => $anomaly['metrickey'],
                    'deviation' => $anomaly['deviation'],
                    'severity' => $anomaly['severity'],
                    'timestamp' => $anomaly['timestamp'],
                    'possible_cause' => $anomaly['possible_cause']
                ]
            );
            
        } catch (\Exception $e) {
            debugging('Failed to log anomaly detection: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
    
    /**
     * Get anomaly detection summary for dashboard
     * 
     * @return array Summary of recent anomalies across all metrics
     */
    public function get_anomaly_summary() {
        if (!$this->predictive_enabled) {
            return ['total' => 0, 'by_severity' => [], 'recent' => []];
        }
        
        $all_anomalies = [];
        
        foreach ($this->supported_metrics as $metric) {
            $metric_anomalies = $this->detect_anomalies($metric, 2.0);
            $all_anomalies = array_merge($all_anomalies, $metric_anomalies);
        }
        
        // Group by severity
        $by_severity = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];
        
        foreach ($all_anomalies as $anomaly) {
            $by_severity[$anomaly['severity']]++;
        }
        
        // Get most recent anomalies
        usort($all_anomalies, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        return [
            'total' => count($all_anomalies),
            'by_severity' => $by_severity,
            'recent' => array_slice($all_anomalies, 0, 10)
        ];
    }
    
    /**
     * Calculate linear regression coefficients
     * 
     * @param array $x_values Independent variable values
     * @param array $y_values Dependent variable values
     * @return array Regression coefficients and statistics
     */
    private function calculate_linear_regression($x_values, $y_values) {
        $n = count($x_values);
        
        if ($n != count($y_values) || $n < 2) {
            throw new \InvalidArgumentException('Invalid data for regression calculation');
        }
        
        $sum_x = array_sum($x_values);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_x_squared = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_values[$i] * $y_values[$i];
            $sum_x_squared += $x_values[$i] * $x_values[$i];
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x_squared - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Calculate R-squared
        $y_mean = $sum_y / $n;
        $ss_tot = 0;
        $ss_res = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $y_pred = $slope * $x_values[$i] + $intercept;
            $ss_tot += pow($y_values[$i] - $y_mean, 2);
            $ss_res += pow($y_values[$i] - $y_pred, 2);
        }
        
        $r_squared = $ss_tot > 0 ? 1 - ($ss_res / $ss_tot) : 0;
        
        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $r_squared,
            'confidence' => $this->classify_forecast_confidence($r_squared)
        ];
    }
    
    /**
     * Calculate standard deviation
     * 
     * @param array $values Array of numeric values
     * @return float Standard deviation
     */
    private function calculate_standard_deviation($values) {
        $n = count($values);
        
        if ($n < 2) {
            return 0;
        }
        
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $values)) / ($n - 1);
        
        return sqrt($variance);
    }
    
    /**
     * Analyze possible cause of anomaly
     * 
     * @param string $metrickey Metric that has anomaly
     * @param object $data_point Anomalous data point
     * @param float $expected_value Expected value
     * @return string Possible cause description
     */
    private function analyze_anomaly_cause($metrickey, $data_point, $expected_value) {
        $deviation_type = $data_point->metricvaluenum > $expected_value ? 'increase' : 'decrease';
        
        $causes = [
            'qa_score_total' => [
                'increase' => 'Improved synthesis quality or enhanced NB data',
                'decrease' => 'Data quality issues, synthesis engine problems, or incomplete NB results'
            ],
            'coherence_score' => [
                'increase' => 'Better content structure or improved coherence engine',
                'decrease' => 'Fragmented content, missing sections, or engine misconfiguration'
            ],
            'pattern_alignment_score' => [
                'increase' => 'Better gold standard compliance or enhanced pattern detection',
                'decrease' => 'Deviation from expected patterns or incomplete analysis'
            ],
            'total_duration_ms' => [
                'increase' => 'Complex synthesis, API latency, or resource constraints',
                'decrease' => 'Optimized processing, cached results, or reduced complexity'
            ],
            'synth_citation_count' => [
                'increase' => 'Rich source material or enhanced citation discovery',
                'decrease' => 'Limited sources, API issues, or citation filtering'
            ]
        ];
        
        return $causes[$metrickey][$deviation_type] ?? 'Unknown cause - requires investigation';
    }
    
    /**
     * Classify anomaly severity based on z-score
     * 
     * @param float $z_score Z-score value
     * @return string Severity classification
     */
    private function classify_anomaly_severity($z_score) {
        if ($z_score >= 3.5) {
            return 'critical';
        } elseif ($z_score >= 3.0) {
            return 'high';
        } elseif ($z_score >= 2.5) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Calculate risk score based on anomaly metrics
     * 
     * @param float $avg_z_score Average z-score of recent anomalies
     * @param int $frequency Number of recent anomalies
     * @param string $metric Metric name for weighting
     * @return float Risk score (0-100)
     */
    private function calculate_risk_score($avg_z_score, $frequency, $metric) {
        // Base score from z-score (0-50)
        $z_score_component = min(50, $avg_z_score * 10);
        
        // Frequency component (0-30)
        $frequency_component = min(30, $frequency * 5);
        
        // Metric importance weighting (0-20)
        $metric_weights = [
            'qa_score_total' => 20,
            'coherence_score' => 15,
            'pattern_alignment_score' => 15,
            'total_duration_ms' => 10,
            'synth_citation_count' => 10
        ];
        
        $weight_component = $metric_weights[$metric] ?? 5;
        
        return min(100, $z_score_component + $frequency_component + $weight_component);
    }
    
    /**
     * Classify risk severity based on risk score
     * 
     * @param float $risk_score Risk score (0-100)
     * @return string Risk severity
     */
    private function classify_risk_severity($risk_score) {
        if ($risk_score >= 80) {
            return 'critical';
        } elseif ($risk_score >= 60) {
            return 'high';
        } elseif ($risk_score >= 40) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Generate risk recommendation based on metric and anomalies
     * 
     * @param string $metric Metric name
     * @param array $anomalies Recent anomalies
     * @return string Recommendation text
     */
    private function generate_risk_recommendation($metric, $anomalies) {
        $recommendations = [
            'qa_score_total' => 'Review synthesis quality and NB data completeness. Consider checking recent API changes or data sources.',
            'coherence_score' => 'Investigate content structure and coherence engine configuration. Check for template or pattern changes.',
            'pattern_alignment_score' => 'Review gold standard patterns and alignment algorithms. Verify pattern detection accuracy.',
            'total_duration_ms' => 'Monitor system performance and API response times. Consider load balancing or optimization.',
            'synth_citation_count' => 'Check source availability and citation discovery mechanisms. Verify API connectivity.'
        ];
        
        $base_recommendation = $recommendations[$metric] ?? 'Monitor metric trends and investigate underlying causes.';
        
        // Add severity-specific advice
        $max_z_score = max(array_column($anomalies, 'z_score'));
        if ($max_z_score >= 3.5) {
            $base_recommendation .= ' URGENT: Immediate investigation required due to critical deviations.';
        } elseif (count($anomalies) >= 5) {
            $base_recommendation .= ' Pattern of frequent anomalies detected - systematic issue likely.';
        }
        
        return $base_recommendation;
    }
    
    /**
     * Classify forecast confidence based on R-squared
     * 
     * @param float $r_squared R-squared value
     * @return string Confidence level
     */
    private function classify_forecast_confidence($r_squared) {
        if ($r_squared >= 0.8) {
            return 'high';
        } elseif ($r_squared >= 0.6) {
            return 'medium';
        } elseif ($r_squared >= 0.4) {
            return 'low';
        } else {
            return 'very_low';
        }
    }
    
    /**
     * Format metric name for display
     * 
     * @param string $metrickey Metric key
     * @return string Formatted name
     */
    private function format_metric_name($metrickey) {
        $names = [
            'qa_score_total' => 'QA Score Total',
            'coherence_score' => 'Coherence Score',
            'pattern_alignment_score' => 'Pattern Alignment',
            'total_duration_ms' => 'Processing Duration',
            'synth_citation_count' => 'Citation Count'
        ];
        
        return $names[$metrickey] ?? ucwords(str_replace('_', ' ', $metrickey));
    }
    
    /**
     * Get recent run data for analysis
     * 
     * @param int $limit Number of recent runs to retrieve
     * @return array Recent run data
     */
    private function get_recent_run_data($limit = 50) {
        try {
            $sql = "SELECT r.id, r.companyid, r.timecompleted, r.status
                    FROM {local_ci_run} r
                    WHERE r.status = 'completed'
                    AND r.timecompleted >= ?
                    ORDER BY r.timecompleted DESC
                    LIMIT ?";
            
            $cutoff_time = time() - (30 * 86400); // Last 30 days
            return $this->db->get_records_sql($sql, [$cutoff_time, $limit]);
            
        } catch (\Exception $e) {
            debugging('Failed to get recent run data: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Check if predictive engine is enabled
     * 
     * @return bool
     */
    public function is_predictive_enabled() {
        return $this->predictive_enabled;
    }
    
    /**
     * Check if anomaly alerts are enabled
     * 
     * @return bool
     */
    public function is_anomaly_alerts_enabled() {
        return $this->anomaly_alerts_enabled;
    }
    
    /**
     * Check if safe mode is enabled
     * 
     * @return bool
     */
    public function is_safe_mode_enabled() {
        return $this->safe_mode_enabled;
    }
    
    /**
     * Get forecast horizon in days
     * 
     * @return int
     */
    public function get_forecast_horizon_days() {
        return $this->forecast_horizon_days;
    }
    
    /**
     * Get supported metrics for prediction
     * 
     * @return array
     */
    public function get_supported_metrics() {
        return $this->supported_metrics;
    }
    
    /**
     * Log anomaly detection event to telemetry
     * 
     * @param array $anomaly Anomaly data
     */
    public function log_anomaly_detection($anomaly) {
        if (!$this->anomaly_alerts_enabled) {
            return;
        }
        
        try {
            require_once(__DIR__ . '/telemetry_logger.php');
            $telemetry = new telemetry_logger();
            
            $telemetry->log_metric(
                $anomaly['runid'] ?? 0,
                'anomaly_detected',
                $anomaly['z_score'],
                [
                    'metric' => $anomaly['metrickey'],
                    'deviation' => $anomaly['deviation'],
                    'severity' => $anomaly['severity'],
                    'timestamp' => $anomaly['timestamp'],
                    'possible_cause' => $anomaly['possible_cause']
                ]
            );
            
        } catch (\Exception $e) {
            debugging('Failed to log anomaly detection: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
    
    /**
     * Trigger anomaly detected event
     * 
     * @param array $anomaly Anomaly data
     */
    public function trigger_anomaly_event($anomaly) {
        if (!$this->anomaly_alerts_enabled) {
            return;
        }
        
        try {
            require_once(__DIR__ . '/../event/anomaly_detected.php');
            
            $event = \local_customerintel\event\anomaly_detected::create_from_anomaly(
                $anomaly['runid'],
                $anomaly['metrickey'],
                $anomaly['z_score'],
                $anomaly['severity'],
                [
                    'deviation' => $anomaly['deviation'],
                    'value' => $anomaly['value'],
                    'expected_value' => $anomaly['expected_value'],
                    'possible_cause' => $anomaly['possible_cause'],
                    'company_name' => $anomaly['company_name']
                ]
            );
            
            $event->trigger();
            
        } catch (\Exception $e) {
            debugging('Failed to trigger anomaly event: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
}