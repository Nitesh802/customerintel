<?php
/**
 * Analytics Renderer for Customer Intelligence Dashboard (Slice 10)
 * 
 * Provides Chart.js components for analytics visualization
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customerintel\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Analytics Renderer - Chart.js Integration
 * 
 * Renders interactive charts and analytics components using Chart.js
 */
class analytics_renderer extends \plugin_renderer_base {
    
    /**
     * Render QA Score Trends line chart
     * 
     * @param array $trend_data Trend data from analytics service
     * @param string $chart_id Unique chart identifier
     * @return string HTML output for trends chart
     */
    public function render_qa_trends_chart($trend_data, $chart_id = 'qa-trends-chart') {
        if (empty($trend_data) || empty($trend_data['labels'])) {
            return $this->render_no_data_message('No trend data available for the selected time period.');
        }
        
        $output = '';
        
        // Chart container
        $output .= html_writer::start_div('analytics-chart-container');
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '400',
            'height' => '200',
            'style' => 'max-height: 400px;'
        ]);
        $output .= html_writer::end_div();
        
        // Initialize Chart.js
        $this->page->requires->js_call_amd('local_customerintel/analytics_charts', 'init_trends_chart', [
            $chart_id,
            $trend_data
        ]);
        
        return $output;
    }
    
    /**
     * Render Phase Duration Breakdown stacked bar chart
     * 
     * @param array $phase_data Phase duration data
     * @param string $chart_id Unique chart identifier
     * @return string HTML output for phase chart
     */
    public function render_phase_duration_chart($phase_data, $chart_id = 'phase-duration-chart') {
        if (empty($phase_data) || empty($phase_data['datasets'])) {
            return $this->render_no_data_message('No phase duration data available.');
        }
        
        $output = '';
        
        // Chart container
        $output .= html_writer::start_div('analytics-chart-container');
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '400',
            'height' => '200',
            'style' => 'max-height: 400px;'
        ]);
        $output .= html_writer::end_div();
        
        // Initialize Chart.js
        $this->page->requires->js_call_amd('local_customerintel/analytics_charts', 'init_phase_chart', [
            $chart_id,
            $phase_data
        ]);
        
        return $output;
    }
    
    /**
     * Render QA Score Distribution pie/doughnut chart
     * 
     * @param array $distribution_data Distribution data
     * @param string $chart_id Unique chart identifier
     * @return string HTML output for distribution chart
     */
    public function render_qa_distribution_chart($distribution_data, $chart_id = 'qa-distribution-chart') {
        if (empty($distribution_data) || empty($distribution_data['labels'])) {
            return $this->render_no_data_message('No QA score distribution data available.');
        }
        
        $output = '';
        
        // Chart container
        $output .= html_writer::start_div('analytics-chart-container');
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '300',
            'height' => '300',
            'style' => 'max-height: 300px;'
        ]);
        $output .= html_writer::end_div();
        
        // Initialize Chart.js
        $this->page->requires->js_call_amd('local_customerintel/analytics_charts', 'init_distribution_chart', [
            $chart_id,
            $distribution_data
        ]);
        
        return $output;
    }
    
    /**
     * Render Coherence vs Pattern Alignment scatter chart
     * 
     * @param array $correlation_data Correlation data
     * @param string $chart_id Unique chart identifier
     * @return string HTML output for correlation chart
     */
    public function render_correlation_chart($correlation_data, $chart_id = 'correlation-chart') {
        if (empty($correlation_data) || empty($correlation_data['datasets'])) {
            return $this->render_no_data_message('No correlation data available.');
        }
        
        $output = '';
        
        // Chart container
        $output .= html_writer::start_div('analytics-chart-container');
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '400',
            'height' => '300',
            'style' => 'max-height: 400px;'
        ]);
        $output .= html_writer::end_div();
        
        // Initialize Chart.js
        $this->page->requires->js_call_amd('local_customerintel/analytics_charts', 'init_correlation_chart', [
            $chart_id,
            $correlation_data
        ]);
        
        return $output;
    }
    
    /**
     * Render Citation Diversity vs Confidence bubble chart
     * 
     * @param array $citation_data Citation metrics data
     * @param string $chart_id Unique chart identifier
     * @return string HTML output for citation chart
     */
    public function render_citation_bubble_chart($citation_data, $chart_id = 'citation-bubble-chart') {
        if (empty($citation_data) || empty($citation_data['datasets'])) {
            return $this->render_no_data_message('No citation metrics data available.');
        }
        
        $output = '';
        
        // Chart container
        $output .= html_writer::start_div('analytics-chart-container');
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '600',
            'height' => '300',
            'style' => 'max-height: 400px;'
        ]);
        $output .= html_writer::end_div();
        
        // Initialize Chart.js
        $this->page->requires->js_call_amd('local_customerintel/analytics_charts', 'init_citation_chart', [
            $chart_id,
            $citation_data
        ]);
        
        return $output;
    }
    
    /**
     * Render summary statistics widgets
     * 
     * @param array $summary_data Summary statistics
     * @return string HTML output for summary widgets
     */
    public function render_summary_widgets($summary_data) {
        if (empty($summary_data)) {
            return $this->render_no_data_message('No summary statistics available.');
        }
        
        $output = '';
        
        $output .= html_writer::start_div('row analytics-summary-widgets');
        
        // Average QA Score Widget
        $qa_score = $summary_data['avg_qa_score'] ?? 0;
        $qa_color = $this->get_score_color($qa_score);
        
        $output .= html_writer::start_div('col-md-3');
        $output .= html_writer::start_div('card text-center analytics-widget qa-widget');
        $output .= html_writer::start_div('card-body');
        $output .= html_writer::tag('h3', number_format($qa_score, 2), ['class' => 'text-' . $qa_color]);
        $output .= html_writer::tag('p', 'Average QA Score', ['class' => 'card-text']);
        $output .= html_writer::tag('small', 'Last 30 days', ['class' => 'text-muted']);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        // Fastest Phase Widget
        $fastest_phase = $summary_data['fastest_phase'] ?? null;
        
        $output .= html_writer::start_div('col-md-3');
        $output .= html_writer::start_div('card text-center analytics-widget performance-widget');
        $output .= html_writer::start_div('card-body');
        $output .= html_writer::tag('h3', $fastest_phase ? $fastest_phase['duration'] . 's' : 'N/A', ['class' => 'text-success']);
        $output .= html_writer::tag('p', 'Fastest Phase', ['class' => 'card-text']);
        $output .= html_writer::tag('small', $fastest_phase ? $fastest_phase['name'] : 'No data', ['class' => 'text-muted']);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        // Success Rate Widget
        $success_rate = $summary_data['success_rate'] ?? 0;
        $total_runs = $summary_data['total_runs'] ?? 0;
        
        $output .= html_writer::start_div('col-md-3');
        $output .= html_writer::start_div('card text-center analytics-widget success-widget');
        $output .= html_writer::start_div('card-body');
        $output .= html_writer::tag('h3', number_format($success_rate, 1) . '%', ['class' => 'text-info']);
        $output .= html_writer::tag('p', 'Success Rate', ['class' => 'card-text']);
        $output .= html_writer::tag('small', $total_runs . ' total runs', ['class' => 'text-muted']);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        // Common Error Widget
        $common_error = $summary_data['common_error'] ?? 'None';
        
        $output .= html_writer::start_div('col-md-3');
        $output .= html_writer::start_div('card text-center analytics-widget error-widget');
        $output .= html_writer::start_div('card-body');
        $output .= html_writer::tag('h3', $common_error, ['class' => 'text-warning']);
        $output .= html_writer::tag('p', 'Common Error', ['class' => 'card-text']);
        $output .= html_writer::tag('small', 'Most frequent', ['class' => 'text-muted']);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render recent runs table
     * 
     * @param array $runs_data Recent runs data
     * @return string HTML output for runs table
     */
    public function render_recent_runs_table($runs_data) {
        if (empty($runs_data)) {
            return $this->render_no_data_message('No recent runs found.');
        }
        
        $output = '';
        
        $output .= html_writer::start_div('table-responsive');
        $output .= html_writer::start_tag('table', ['class' => 'table table-hover analytics-runs-table']);
        
        // Table header
        $output .= html_writer::start_tag('thead');
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('th', 'Run ID');
        $output .= html_writer::tag('th', 'Company');
        $output .= html_writer::tag('th', 'Target');
        $output .= html_writer::tag('th', 'QA Score');
        $output .= html_writer::tag('th', 'Coherence');
        $output .= html_writer::tag('th', 'Duration');
        $output .= html_writer::tag('th', 'Completed');
        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('thead');
        
        // Table body
        $output .= html_writer::start_tag('tbody');
        
        foreach ($runs_data as $run) {
            $output .= html_writer::start_tag('tr');
            
            // Run ID with link
            $run_url = new moodle_url('/local/customerintel/view_report.php', ['runid' => $run->id]);
            $output .= html_writer::tag('td', html_writer::link($run_url, $run->id));
            
            // Company
            $company_name = $run->company_name ?? 'Unknown';
            if ($run->company_ticker) {
                $company_name .= ' (' . $run->company_ticker . ')';
            }
            $output .= html_writer::tag('td', htmlspecialchars($company_name));
            
            // Target Company
            $target_name = $run->target_company_name ?? 'None';
            $output .= html_writer::tag('td', htmlspecialchars($target_name));
            
            // QA Score with badge
            $qa_score = $run->qa_metrics['qa_score_total'] ?? 0;
            $qa_badge_class = $this->get_score_badge_class($qa_score);
            $qa_badge = html_writer::tag('span', number_format($qa_score, 2), ['class' => 'badge badge-' . $qa_badge_class]);
            $output .= html_writer::tag('td', $qa_badge);
            
            // Coherence Score
            $coherence_score = $run->qa_metrics['coherence_score'] ?? 0;
            $output .= html_writer::tag('td', number_format($coherence_score, 2));
            
            // Duration
            $duration = $run->telemetry_summary['duration_seconds'] ?? $run->duration_seconds ?? 0;
            $output .= html_writer::tag('td', number_format($duration, 1) . 's');
            
            // Completed Date
            $completed_date = userdate($run->timecompleted, get_string('strftimedatefullshort'));
            $output .= html_writer::tag('td', $completed_date);
            
            $output .= html_writer::end_tag('tr');
        }
        
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render analytics filter controls
     * 
     * @param int $current_timerange Current time range
     * @param string $current_metric Current metric
     * @return string HTML output for filter controls
     */
    public function render_analytics_filters($current_timerange = 30, $current_metric = 'qa_score_total') {
        $output = '';
        
        $output .= html_writer::start_div('card analytics-filters');
        $output .= html_writer::start_div('card-body');
        $output .= html_writer::start_div('row');
        
        // Time Range Filter
        $output .= html_writer::start_div('col-md-4');
        $output .= html_writer::tag('label', 'Time Range:', ['for' => 'timerange-select']);
        
        $timerange_options = [
            7 => 'Last 7 days',
            30 => 'Last 30 days',
            90 => 'Last 90 days'
        ];
        
        $select_attributes = ['id' => 'timerange-select', 'class' => 'form-control analytics-filter'];
        $output .= html_writer::select($timerange_options, 'timerange', $current_timerange, false, $select_attributes);
        $output .= html_writer::end_div();
        
        // Metric Filter
        $output .= html_writer::start_div('col-md-4');
        $output .= html_writer::tag('label', 'Primary Metric:', ['for' => 'metric-select']);
        
        $metric_options = [
            'qa_score_total' => 'QA Score Total',
            'coherence_score' => 'Coherence Score',
            'pattern_alignment_score' => 'Pattern Alignment'
        ];
        
        $select_attributes = ['id' => 'metric-select', 'class' => 'form-control analytics-filter'];
        $output .= html_writer::select($metric_options, 'metric', $current_metric, false, $select_attributes);
        $output .= html_writer::end_div();
        
        // Refresh Button
        $output .= html_writer::start_div('col-md-4');
        $output .= html_writer::tag('label', '&nbsp;', [], false);
        $output .= html_writer::tag('button', 
            html_writer::tag('i', '', ['class' => 'fa fa-refresh']) . ' Refresh Charts',
            ['id' => 'refresh-charts', 'class' => 'btn btn-primary form-control']
        );
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render safe mode notice
     * 
     * @return string HTML output for safe mode notice
     */
    public function render_safe_mode_notice() {
        $output = '';
        
        $output .= html_writer::start_div('alert alert-warning analytics-safe-mode');
        $output .= html_writer::tag('h5', html_writer::tag('i', '', ['class' => 'fa fa-shield']) . ' Safe Mode Active');
        $output .= html_writer::tag('p', 'Analytics dashboard is running in safe mode with limited data to ensure fast performance. Advanced charts are disabled.');
        $output .= html_writer::tag('small', 'Disable safe mode in plugin settings to access full analytics features.');
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render loading indicator
     * 
     * @param string $message Loading message
     * @return string HTML output for loading indicator
     */
    public function render_loading_indicator($message = 'Loading...') {
        $output = '';
        
        $output .= html_writer::start_div('text-center py-4 analytics-loading');
        $output .= html_writer::start_div('spinner-border', ['role' => 'status']);
        $output .= html_writer::tag('span', 'Loading...', ['class' => 'sr-only']);
        $output .= html_writer::end_div();
        $output .= html_writer::tag('p', $message, ['class' => 'mt-2 text-muted']);
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render no data message
     * 
     * @param string $message No data message
     * @return string HTML output for no data message
     */
    private function render_no_data_message($message) {
        $output = '';
        
        $output .= html_writer::start_div('text-center py-4 analytics-no-data');
        $output .= html_writer::tag('i', '', ['class' => 'fa fa-chart-bar fa-3x text-muted mb-3']);
        $output .= html_writer::tag('h5', 'No Data Available');
        $output .= html_writer::tag('p', $message, ['class' => 'text-muted']);
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Get score color class based on value
     * 
     * @param float $score Score value (0-1)
     * @return string Bootstrap color class
     */
    private function get_score_color($score) {
        if ($score >= 0.8) {
            return 'success';
        } elseif ($score >= 0.6) {
            return 'warning';
        } else {
            return 'danger';
        }
    }
    
    /**
     * Get score badge class based on value
     * 
     * @param float $score Score value (0-1)
     * @return string Bootstrap badge class
     */
    private function get_score_badge_class($score) {
        if ($score >= 0.8) {
            return 'success';
        } elseif ($score >= 0.6) {
            return 'warning';
        } else {
            return 'danger';
        }
    }
    
    /**
     * Render forecast chart with historical and predicted data
     * 
     * @param array $forecast_data Forecast data from predictive engine
     * @param string $chart_id Unique chart identifier
     * @return string HTML output for forecast chart
     */
    public function render_forecast_chart($forecast_data, $chart_id = 'forecast-chart') {
        if (empty($forecast_data) || isset($forecast_data['error'])) {
            return $this->render_no_data_message('Forecast data unavailable: ' . ($forecast_data['error'] ?? 'Unknown error'));
        }
        
        $output = '';
        
        // Chart container
        $output .= html_writer::start_div('analytics-chart-container');
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '600',
            'height' => '300',
            'style' => 'max-height: 400px;'
        ]);
        $output .= html_writer::end_div();
        
        // Initialize Chart.js for forecast
        $this->page->requires->js_call_amd('local_customerintel/analytics_charts', 'init_forecast_chart', [
            $chart_id,
            $forecast_data
        ]);
        
        return $output;
    }
    
    /**
     * Render anomalies table with severity indicators
     * 
     * @param array $anomalies_data Anomalies data from predictive engine
     * @return string HTML output for anomalies table
     */
    public function render_anomalies_table($anomalies_data) {
        if (empty($anomalies_data)) {
            return $this->render_no_data_message('No anomalies detected.');
        }
        
        $output = '';
        
        $output .= html_writer::start_div('table-responsive');
        $output .= html_writer::start_tag('table', ['class' => 'table table-hover anomalies-table']);
        
        // Table header
        $output .= html_writer::start_tag('thead');
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('th', 'Metric');
        $output .= html_writer::tag('th', 'Date');
        $output .= html_writer::tag('th', 'Deviation');
        $output .= html_writer::tag('th', 'Severity');
        $output .= html_writer::tag('th', 'Cause');
        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('thead');
        
        // Table body
        $output .= html_writer::start_tag('tbody');
        
        foreach (array_slice($anomalies_data, 0, 10) as $anomaly) {
            $output .= html_writer::start_tag('tr');
            
            // Metric
            $output .= html_writer::tag('td', htmlspecialchars($anomaly['metrickey']));
            
            // Date
            $output .= html_writer::tag('td', date('M j, Y', $anomaly['timestamp']));
            
            // Deviation
            $deviation = ($anomaly['deviation'] > 0 ? '+' : '') . number_format($anomaly['deviation'], 3);
            $output .= html_writer::tag('td', $deviation);
            
            // Severity with badge
            $severity_class = $this->get_severity_badge_class($anomaly['severity']);
            $severity_badge = html_writer::tag('span', ucfirst($anomaly['severity']), ['class' => 'badge badge-' . $severity_class]);
            $output .= html_writer::tag('td', $severity_badge);
            
            // Possible cause (truncated)
            $cause = strlen($anomaly['possible_cause']) > 50 ? 
                     substr($anomaly['possible_cause'], 0, 47) . '...' : 
                     $anomaly['possible_cause'];
            $output .= html_writer::tag('td', htmlspecialchars($cause), ['title' => htmlspecialchars($anomaly['possible_cause'])]);
            
            $output .= html_writer::end_tag('tr');
        }
        
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render risk signals radar/summary
     * 
     * @param array $risk_signals Risk signals from predictive engine
     * @return string HTML output for risk signals
     */
    public function render_risk_signals($risk_signals) {
        if (empty($risk_signals)) {
            return $this->render_no_data_message('No significant risk signals detected.');
        }
        
        $output = '';
        
        $output .= html_writer::start_div('risk-signals-container');
        
        foreach ($risk_signals as $signal) {
            $severity_class = $this->get_severity_badge_class($signal['severity']);
            
            $output .= html_writer::start_div('risk-signal-item mb-3 p-3 border rounded');
            
            // Header with metric name and risk score
            $output .= html_writer::start_div('d-flex justify-content-between align-items-center mb-2');
            $output .= html_writer::tag('h6', htmlspecialchars($signal['metric_display_name']), ['class' => 'mb-0']);
            $risk_badge = html_writer::tag('span', 'Risk: ' . $signal['risk_score'], ['class' => 'badge badge-' . $severity_class]);
            $output .= $risk_badge;
            $output .= html_writer::end_div();
            
            // Recommendation
            $output .= html_writer::tag('p', htmlspecialchars($signal['recommendation']), ['class' => 'small text-muted mb-2']);
            
            // Metrics row
            $output .= html_writer::start_div('row');
            $output .= html_writer::start_div('col-6');
            $output .= html_writer::tag('small', html_writer::tag('strong', 'Anomalies: ') . $signal['anomaly_count']);
            $output .= html_writer::end_div();
            $output .= html_writer::start_div('col-6');
            $output .= html_writer::tag('small', html_writer::tag('strong', 'Avg Z-score: ') . $signal['avg_z_score']);
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            
            $output .= html_writer::end_div();
        }
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render anomaly summary cards
     * 
     * @param array $summary_data Anomaly summary from predictive engine
     * @return string HTML output for anomaly summary
     */
    public function render_anomaly_summary($summary_data) {
        if (empty($summary_data)) {
            return $this->render_no_data_message('No anomaly summary available.');
        }
        
        $output = '';
        
        // Total anomalies
        $output .= html_writer::start_div('mb-3');
        $output .= html_writer::tag('h6', 'Total Anomalies: ' . html_writer::tag('span', $summary_data['total'], ['class' => 'badge badge-primary']), ['class' => 'mb-2']);
        $output .= html_writer::end_div();
        
        // By severity
        $output .= html_writer::start_div('mb-3');
        $output .= html_writer::tag('h6', 'By Severity:', ['class' => 'mb-2']);
        
        foreach ($summary_data['by_severity'] as $severity => $count) {
            if ($count > 0) {
                $severity_class = $this->get_severity_badge_class($severity);
                $badge = html_writer::tag('span', ucfirst($severity) . ': ' . $count, ['class' => 'badge badge-' . $severity_class . ' mr-1']);
                $output .= $badge;
            }
        }
        $output .= html_writer::end_div();
        
        // Most recent
        if (!empty($summary_data['recent'])) {
            $recent = $summary_data['recent'][0];
            $output .= html_writer::start_div('');
            $output .= html_writer::tag('h6', 'Most Recent:', ['class' => 'mb-2']);
            $output .= html_writer::tag('small', 
                htmlspecialchars($recent['metrickey']) . ' anomaly<br>' .
                htmlspecialchars($recent['date']) . '<br>' .
                'Z-score: ' . $recent['z_score'], 
                ['class' => 'text-muted']
            );
            $output .= html_writer::end_div();
        }
        
        return $output;
    }
    
    /**
     * Get severity badge class
     * 
     * @param string $severity Severity level
     * @return string Bootstrap badge class
     */
    private function get_severity_badge_class($severity) {
        switch ($severity) {
            case 'critical':
                return 'danger';
            case 'high':
                return 'warning';
            case 'medium':
                return 'info';
            case 'low':
            default:
                return 'secondary';
        }
    }
}