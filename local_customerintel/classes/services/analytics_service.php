<?php
/**
 * Analytics Service for Customer Intelligence Dashboard (Slice 10)
 * 
 * Provides read-only analytics data for trend visualization and reporting
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\services;

defined('MOODLE_INTERNAL') || die();

/**
 * Analytics Service - Historical Insight & Trend Analysis
 * 
 * Provides read-only methods for analytics dashboard data retrieval
 */
class analytics_service {
    
    /**
     * @var \moodle_database Database connection
     */
    private $db;
    
    /**
     * @var bool Safe mode flag for limited queries
     */
    private $safe_mode_enabled;
    
    /**
     * @var bool Analytics dashboard enabled flag
     */
    private $analytics_enabled;
    
    /**
     * @var bool Telemetry trends enabled flag
     */
    private $telemetry_trends_enabled;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
        
        // Load feature flags
        $this->safe_mode_enabled = get_config('local_customerintel', 'enable_safe_mode') === '1';
        $this->analytics_enabled = get_config('local_customerintel', 'enable_analytics_dashboard') !== '0';
        $this->telemetry_trends_enabled = get_config('local_customerintel', 'enable_telemetry_trends') !== '0';
    }
    
    /**
     * Get recent completed runs with basic metrics
     * 
     * @param int $limit Maximum number of runs to return
     * @return array Array of run objects with basic metrics
     */
    public function get_recent_runs($limit = 50) {
        if (!$this->analytics_enabled) {
            return [];
        }
        
        // Apply safe mode limits
        if ($this->safe_mode_enabled && $limit > 10) {
            $limit = 10;
        }
        
        try {
            $sql = "SELECT r.id, r.companyid, r.targetcompanyid, r.status, 
                           r.timecreated, r.timecompleted, r.initiatedbyuserid,
                           c.name as company_name, c.ticker as company_ticker,
                           tc.name as target_company_name, tc.ticker as target_company_ticker,
                           (r.timecompleted - r.timecreated) as duration_seconds
                    FROM {local_ci_run} r
                    LEFT JOIN {local_ci_company} c ON c.id = r.companyid
                    LEFT JOIN {local_ci_company} tc ON tc.id = r.targetcompanyid
                    WHERE r.status = 'completed'
                    ORDER BY r.timecompleted DESC
                    LIMIT ?";
            
            $runs = $this->db->get_records_sql($sql, [$limit]);
            
            // Enhance with telemetry data
            foreach ($runs as &$run) {
                $run->qa_metrics = $this->get_run_qa_summary($run->id);
                $run->telemetry_summary = $this->get_run_telemetry_summary($run->id);
            }
            
            return array_values($runs);
            
        } catch (\Exception $e) {
            debugging('Failed to get recent runs: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Get trend data for a specific metric over time
     * 
     * @param string $metrickey Metric key to analyze
     * @param int $days Number of days to look back
     * @return array Trend data with dates and values
     */
    public function get_run_trends($metrickey, $days = 30) {
        if (!$this->telemetry_trends_enabled) {
            return [];
        }
        
        // Apply safe mode limits
        if ($this->safe_mode_enabled && $days > 7) {
            $days = 7;
        }
        
        try {
            $cutoff_time = time() - ($days * 86400);
            
            $sql = "SELECT DATE(FROM_UNIXTIME(t.timecreated)) as trend_date,
                           AVG(t.metricvaluenum) as avg_value,
                           MAX(t.metricvaluenum) as max_value,
                           MIN(t.metricvaluenum) as min_value,
                           COUNT(t.id) as sample_count
                    FROM {local_ci_telemetry} t
                    INNER JOIN {local_ci_run} r ON r.id = t.runid
                    WHERE t.metrickey = ?
                    AND t.timecreated >= ?
                    AND r.status = 'completed'
                    GROUP BY DATE(FROM_UNIXTIME(t.timecreated))
                    ORDER BY trend_date ASC";
            
            $trends = $this->db->get_records_sql($sql, [$metrickey, $cutoff_time]);
            
            // Convert to chart-friendly format
            $chart_data = [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Average ' . $this->format_metric_name($metrickey),
                        'data' => [],
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
                        'tension' => 0.4
                    ],
                    [
                        'label' => 'Range',
                        'data' => [],
                        'borderColor' => 'rgba(255, 99, 132, 0.3)',
                        'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
                        'fill' => '+1'
                    ]
                ]
            ];
            
            foreach ($trends as $trend) {
                $chart_data['labels'][] = $trend->trend_date;
                $chart_data['datasets'][0]['data'][] = round($trend->avg_value, 3);
                $chart_data['datasets'][1]['data'][] = round($trend->max_value, 3);
            }
            
            return $chart_data;
            
        } catch (\Exception $e) {
            debugging('Failed to get run trends: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Get QA score distribution across all completed runs
     * 
     * @return array Distribution data for QA scores
     */
    public function get_qa_distribution() {
        if (!$this->analytics_enabled) {
            return [];
        }
        
        try {
            $sql = "SELECT 
                        CASE 
                            WHEN t.metricvaluenum >= 0.8 THEN 'Excellent (0.8+)'
                            WHEN t.metricvaluenum >= 0.6 THEN 'Good (0.6-0.79)'
                            WHEN t.metricvaluenum >= 0.4 THEN 'Fair (0.4-0.59)'
                            ELSE 'Needs Improvement (<0.4)'
                        END as score_range,
                        COUNT(*) as count
                    FROM {local_ci_telemetry} t
                    INNER JOIN {local_ci_run} r ON r.id = t.runid
                    WHERE t.metrickey = 'qa_score_total'
                    AND r.status = 'completed'
                    GROUP BY score_range
                    ORDER BY MIN(t.metricvaluenum) DESC";
            
            $distribution = $this->db->get_records_sql($sql);
            
            // Convert to chart format
            $chart_data = [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'QA Score Distribution',
                        'data' => [],
                        'backgroundColor' => [
                            'rgba(75, 192, 192, 0.8)',  // Excellent - Green
                            'rgba(255, 205, 86, 0.8)',  // Good - Yellow
                            'rgba(255, 159, 64, 0.8)',  // Fair - Orange
                            'rgba(255, 99, 132, 0.8)'   // Needs Improvement - Red
                        ]
                    ]
                ]
            ];
            
            foreach ($distribution as $dist) {
                $chart_data['labels'][] = $dist->score_range;
                $chart_data['datasets'][0]['data'][] = $dist->count;
            }
            
            return $chart_data;
            
        } catch (\Exception $e) {
            debugging('Failed to get QA distribution: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Get correlation data between coherence and pattern alignment scores
     * 
     * @return array Scatter plot data for correlation analysis
     */
    public function get_coherence_vs_pattern_correlation() {
        if (!$this->analytics_enabled || $this->safe_mode_enabled) {
            return [];
        }
        
        try {
            $sql = "SELECT r.id as runid,
                           c.metricvaluenum as coherence_score,
                           p.metricvaluenum as pattern_score,
                           r.timecompleted
                    FROM {local_ci_run} r
                    INNER JOIN {local_ci_telemetry} c ON c.runid = r.id AND c.metrickey = 'coherence_score'
                    INNER JOIN {local_ci_telemetry} p ON p.runid = r.id AND p.metrickey = 'pattern_alignment_score'
                    WHERE r.status = 'completed'
                    AND r.timecompleted >= ?
                    ORDER BY r.timecompleted DESC
                    LIMIT 100";
            
            $cutoff_time = time() - (30 * 86400); // Last 30 days
            $correlations = $this->db->get_records_sql($sql, [$cutoff_time]);
            
            // Convert to scatter plot format
            $chart_data = [
                'datasets' => [
                    [
                        'label' => 'Coherence vs Pattern Alignment',
                        'data' => [],
                        'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'pointRadius' => 4
                    ]
                ]
            ];
            
            foreach ($correlations as $correlation) {
                $chart_data['datasets'][0]['data'][] = [
                    'x' => round($correlation->coherence_score, 3),
                    'y' => round($correlation->pattern_score, 3),
                    'runid' => $correlation->runid
                ];
            }
            
            return $chart_data;
            
        } catch (\Exception $e) {
            debugging('Failed to get coherence vs pattern correlation: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Get citation diversity vs confidence correlation for bubble chart
     * 
     * @return array Bubble chart data
     */
    public function get_citation_diversity_vs_confidence() {
        if (!$this->analytics_enabled || $this->safe_mode_enabled) {
            return [];
        }
        
        try {
            $sql = "SELECT cm.runid,
                           cm.confidence_avg,
                           cm.diversity_score,
                           cm.total_citations,
                           r.timecompleted,
                           c.name as company_name
                    FROM {local_ci_citation_metrics} cm
                    INNER JOIN {local_ci_run} r ON r.id = cm.runid
                    INNER JOIN {local_ci_company} c ON c.id = r.companyid
                    WHERE r.status = 'completed'
                    AND r.timecompleted >= ?
                    ORDER BY r.timecompleted DESC
                    LIMIT 100";
            
            $cutoff_time = time() - (30 * 86400); // Last 30 days
            $citations = $this->db->get_records_sql($sql, [$cutoff_time]);
            
            // Convert to bubble chart format
            $chart_data = [
                'datasets' => [
                    [
                        'label' => 'Citation Metrics',
                        'data' => [],
                        'backgroundColor' => 'rgba(255, 99, 132, 0.6)',
                        'borderColor' => 'rgba(255, 99, 132, 1)'
                    ]
                ]
            ];
            
            foreach ($citations as $citation) {
                $chart_data['datasets'][0]['data'][] = [
                    'x' => round($citation->confidence_avg, 3),
                    'y' => round($citation->diversity_score, 3),
                    'r' => min(20, max(3, $citation->total_citations / 2)), // Bubble size
                    'runid' => $citation->runid,
                    'company' => $citation->company_name
                ];
            }
            
            return $chart_data;
            
        } catch (\Exception $e) {
            debugging('Failed to get citation diversity vs confidence: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Get phase duration breakdown for stacked bar chart
     * 
     * @param int $days Number of days to analyze
     * @return array Stacked bar chart data
     */
    public function get_phase_duration_breakdown($days = 30) {
        if (!$this->telemetry_trends_enabled) {
            return [];
        }
        
        // Apply safe mode limits
        if ($this->safe_mode_enabled && $days > 7) {
            $days = 7;
        }
        
        try {
            $cutoff_time = time() - ($days * 86400);
            
            $sql = "SELECT t.metrickey,
                           AVG(t.metricvaluenum) as avg_duration,
                           COUNT(t.id) as sample_count
                    FROM {local_ci_telemetry} t
                    INNER JOIN {local_ci_run} r ON r.id = t.runid
                    WHERE t.metrickey LIKE 'phase_duration_%'
                    AND t.timecreated >= ?
                    AND r.status = 'completed'
                    GROUP BY t.metrickey
                    ORDER BY avg_duration DESC";
            
            $phases = $this->db->get_records_sql($sql, [$cutoff_time]);
            
            // Convert to stacked bar format
            $chart_data = [
                'labels' => ['Average Phase Durations'],
                'datasets' => []
            ];
            
            $colors = [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)'
            ];
            
            $color_index = 0;
            foreach ($phases as $phase) {
                $phase_name = str_replace('phase_duration_', '', $phase->metrickey);
                $phase_name = $this->format_phase_name($phase_name);
                
                $chart_data['datasets'][] = [
                    'label' => $phase_name,
                    'data' => [round($phase->avg_duration / 1000, 2)], // Convert to seconds
                    'backgroundColor' => $colors[$color_index % count($colors)]
                ];
                
                $color_index++;
            }
            
            return $chart_data;
            
        } catch (\Exception $e) {
            debugging('Failed to get phase duration breakdown: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Get summary statistics for dashboard widgets
     * 
     * @return array Summary statistics
     */
    public function get_summary_statistics() {
        if (!$this->analytics_enabled) {
            return [];
        }
        
        try {
            $cutoff_time = time() - (30 * 86400); // Last 30 days
            
            // Average QA Score
            $avg_qa_sql = "SELECT AVG(t.metricvaluenum) as avg_qa_score
                          FROM {local_ci_telemetry} t
                          INNER JOIN {local_ci_run} r ON r.id = t.runid
                          WHERE t.metrickey = 'qa_score_total'
                          AND t.timecreated >= ?
                          AND r.status = 'completed'";
            
            $avg_qa = $this->db->get_record_sql($avg_qa_sql, [$cutoff_time]);
            
            // Fastest Phase
            $fastest_phase_sql = "SELECT t.metrickey, AVG(t.metricvaluenum) as avg_duration
                                 FROM {local_ci_telemetry} t
                                 INNER JOIN {local_ci_run} r ON r.id = t.runid
                                 WHERE t.metrickey LIKE 'phase_duration_%'
                                 AND t.timecreated >= ?
                                 AND r.status = 'completed'
                                 GROUP BY t.metrickey
                                 ORDER BY avg_duration ASC
                                 LIMIT 1";
            
            $fastest_phase = $this->db->get_record_sql($fastest_phase_sql, [$cutoff_time]);
            
            // Report Success Rate
            $success_rate_sql = "SELECT 
                                COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*) as success_rate
                                FROM {local_ci_run}
                                WHERE timecreated >= ?";
            
            $success_rate = $this->db->get_record_sql($success_rate_sql, [$cutoff_time]);
            
            // Most Common Error Type (placeholder - would need error logging enhancement)
            $error_type = 'synthesis_timeout'; // Default placeholder
            
            return [
                'avg_qa_score' => $avg_qa ? round($avg_qa->avg_qa_score, 3) : 0,
                'fastest_phase' => $fastest_phase ? [
                    'name' => $this->format_phase_name(str_replace('phase_duration_', '', $fastest_phase->metrickey)),
                    'duration' => round($fastest_phase->avg_duration / 1000, 2) // Convert to seconds
                ] : null,
                'success_rate' => $success_rate ? round($success_rate->success_rate, 1) : 0,
                'common_error' => $error_type,
                'total_runs' => $this->db->count_records_select('local_ci_run', 'timecreated >= ?', [$cutoff_time])
            ];
            
        } catch (\Exception $e) {
            debugging('Failed to get summary statistics: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Log analytics usage for telemetry
     * 
     * @param string $action Action performed (view, filter, etc.)
     * @param array $metadata Additional metadata
     */
    public function log_analytics_usage($action, $metadata = []) {
        try {
            require_once(__DIR__ . '/telemetry_logger.php');
            $telemetry = new telemetry_logger();
            
            $telemetry->log_metric(
                0, // System-wide metrics use runid = 0
                'analytics_' . $action,
                1,
                array_merge($metadata, ['timestamp' => time()])
            );
            
        } catch (\Exception $e) {
            debugging('Failed to log analytics usage: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
    
    /**
     * Get QA summary for a specific run
     * 
     * @param int $runid Run ID
     * @return array QA metrics
     */
    private function get_run_qa_summary($runid) {
        try {
            $sql = "SELECT metrickey, metricvaluenum
                    FROM {local_ci_telemetry}
                    WHERE runid = ?
                    AND metrickey IN ('qa_score_total', 'coherence_score', 'pattern_alignment_score')";
            
            $metrics = $this->db->get_records_sql($sql, [$runid]);
            
            $qa_summary = [];
            foreach ($metrics as $metric) {
                $qa_summary[$metric->metrickey] = round($metric->metricvaluenum, 3);
            }
            
            return $qa_summary;
            
        } catch (\Exception $e) {
            debugging('Failed to get run QA summary: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
        }
    }
    
    /**
     * Get telemetry summary for a specific run
     * 
     * @param int $runid Run ID
     * @return array Telemetry metrics
     */
    private function get_run_telemetry_summary($runid) {
        try {
            $sql = "SELECT metrickey, metricvaluenum
                    FROM {local_ci_telemetry}
                    WHERE runid = ?
                    AND metrickey IN ('total_duration_ms', 'synth_citation_count')";
            
            $metrics = $this->db->get_records_sql($sql, [$runid]);
            
            $telemetry_summary = [];
            foreach ($metrics as $metric) {
                if ($metric->metrickey === 'total_duration_ms') {
                    $telemetry_summary['duration_seconds'] = round($metric->metricvaluenum / 1000, 2);
                } else {
                    $telemetry_summary[$metric->metrickey] = $metric->metricvaluenum;
                }
            }
            
            return $telemetry_summary;
            
        } catch (\Exception $e) {
            debugging('Failed to get run telemetry summary: ' . $e->getMessage(), DEBUG_NORMAL);
            return [];
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
            'qa_score_total' => 'QA Score',
            'coherence_score' => 'Coherence',
            'pattern_alignment_score' => 'Pattern Alignment',
            'total_duration_ms' => 'Total Duration'
        ];
        
        return $names[$metrickey] ?? ucwords(str_replace('_', ' ', $metrickey));
    }
    
    /**
     * Format phase name for display
     * 
     * @param string $phase_name Phase name
     * @return string Formatted name
     */
    private function format_phase_name($phase_name) {
        $names = [
            'nb_orchestration' => 'NB Orchestration',
            'synthesis_drafting' => 'Synthesis Drafting',
            'coherence_engine' => 'Coherence Analysis',
            'pattern_comparator' => 'Pattern Comparison',
            'qa_scoring' => 'QA Scoring'
        ];
        
        return $names[$phase_name] ?? ucwords(str_replace('_', ' ', $phase_name));
    }
    
    /**
     * Check if analytics features are enabled
     * 
     * @return bool
     */
    public function is_analytics_enabled() {
        return $this->analytics_enabled;
    }
    
    /**
     * Check if safe mode is enabled
     * 
     * @return bool
     */
    public function is_safe_mode_enabled() {
        return $this->safe_mode_enabled;
    }
}