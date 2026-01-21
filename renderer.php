<?php
/**
 * Customer Intelligence Dashboard - UI Renderer (Slice 8)
 * 
 * Transforms telemetry and QA data into interactive visual components
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Customer Intelligence Dashboard Renderer
 * 
 * Provides interactive visual components for QA metrics, telemetry charts,
 * citation analytics, and section diagnostics
 */
class local_customerintel_renderer extends plugin_renderer_base {
    
    /**
     * Render QA summary with interactive visual components
     * 
     * @param int $runid Run identifier
     * @return string HTML output for QA summary
     */
    public function render_qa_summary($runid) {
        global $DB;
        
        // Check feature flag
        $interactive_ui_enabled = get_config('local_customerintel', 'enable_interactive_ui');
        if ($interactive_ui_enabled === '0') {
            return $this->render_qa_summary_fallback($runid);
        }
        
        // Get telemetry QA data
        $qa_metrics = $this->get_qa_telemetry_data($runid);
        
        // Get synthesis QA scores from synthesis table
        $synthesis_qa = $this->get_synthesis_qa_scores($runid);
        
        $output = '';
        
        // Main QA Summary Card
        $output .= html_writer::start_div('card qa-summary-card');
        $output .= html_writer::start_div('card-header bg-primary text-white');
        $output .= html_writer::tag('h4', 'Quality Assessment Summary', ['class' => 'mb-0']);
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('card-body');
        
        // Overall QA Score
        $overall_score = $qa_metrics['qa_score_total'] ?? $synthesis_qa['total_weighted'] ?? 0;
        $score_color = $this->get_score_color($overall_score);
        
        $output .= html_writer::start_div('row mb-4');
        $output .= html_writer::start_div('col-md-4 text-center');
        $output .= html_writer::tag('h2', number_format($overall_score, 2), [
            'class' => 'score-display text-' . $score_color,
            'data-score' => $overall_score
        ]);
        $output .= html_writer::tag('p', 'Overall QA Score', ['class' => 'text-muted mb-0']);
        $output .= $this->render_score_progress_bar($overall_score);
        $output .= html_writer::end_div();
        
        // Core Metrics
        $output .= html_writer::start_div('col-md-8');
        $output .= html_writer::start_div('row');
        
        // Coherence Score
        $coherence_score = $qa_metrics['coherence_score'] ?? $synthesis_qa['coherence'] ?? 0;
        $output .= $this->render_metric_card('Coherence', $coherence_score, 'fas fa-link', 'primary');
        
        // Pattern Alignment Score
        $pattern_score = $qa_metrics['pattern_alignment_score'] ?? $synthesis_qa['pattern_alignment'] ?? 0;
        $output .= $this->render_metric_card('Gold Standard Alignment', $pattern_score, 'fas fa-bullseye', 'success');
        
        // Completeness Score
        $completeness_score = $synthesis_qa['completeness'] ?? 0;
        $output .= $this->render_metric_card('Completeness', $completeness_score, 'fas fa-check-circle', 'info');
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        // Section-by-Section Breakdown
        if (!empty($qa_metrics['section_scores'])) {
            $output .= html_writer::tag('h5', 'Section Quality Breakdown', ['class' => 'mt-4 mb-3']);
            $output .= $this->render_section_qa_breakdown($qa_metrics['section_scores']);
        }
        
        // QA Warnings if any
        $warnings_count = $qa_metrics['qa_warnings_count'] ?? 0;
        if ($warnings_count > 0) {
            $output .= html_writer::start_div('alert alert-warning mt-3');
            $output .= html_writer::tag('strong', 'Quality Warnings: ');
            $output .= html_writer::tag('span', $warnings_count . ' issues detected');
            $output .= html_writer::end_div();
        }
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render telemetry chart with phase durations and metrics
     * 
     * @param int $runid Run identifier
     * @return string HTML output for telemetry chart
     */
    public function render_telemetry_chart($runid) {
        global $DB;
        
        // Check feature flag
        $interactive_ui_enabled = get_config('local_customerintel', 'enable_interactive_ui');
        if ($interactive_ui_enabled === '0') {
            return '';
        }
        
        // Get telemetry data
        $telemetry_data = $this->get_telemetry_chart_data($runid);
        
        if (empty($telemetry_data)) {
            return $this->render_no_telemetry_message();
        }
        
        $output = '';
        
        // Telemetry Chart Card
        $output .= html_writer::start_div('card telemetry-chart-card mt-3');
        $output .= html_writer::start_div('card-header');
        $output .= html_writer::tag('h4', 'Performance Metrics', ['class' => 'mb-0']);
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('card-body');
        
        // Chart container
        $chart_id = 'telemetry-chart-' . $runid;
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '400',
            'height' => '200',
            'style' => 'max-height: 400px;'
        ]);
        
        // Performance summary table
        $output .= html_writer::start_div('row mt-4');
        $output .= html_writer::start_div('col-md-6');
        $output .= html_writer::tag('h5', 'Phase Durations');
        $output .= $this->render_phase_duration_table($telemetry_data['phase_durations']);
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('col-md-6');
        $output .= html_writer::tag('h5', 'Key Metrics');
        $output .= $this->render_key_metrics_table($telemetry_data['metrics']);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        // Add Chart.js initialization
        $this->page->requires->js_call_amd('local_customerintel/telemetry_chart', 'init', [
            $chart_id,
            $telemetry_data
        ]);
        
        return $output;
    }
    
    /**
     * Render citation metrics summary
     * 
     * @param int $runid Run identifier
     * @return string HTML output for citation metrics
     */
    public function render_citation_metrics($runid) {
        global $DB;
        
        // Check feature flag
        $citation_charts_enabled = get_config('local_customerintel', 'enable_citation_charts');
        if ($citation_charts_enabled === '0') {
            return '';
        }
        
        // Get citation metrics
        $citation_data = $this->get_citation_metrics_data($runid);
        
        if (empty($citation_data)) {
            return $this->render_no_citation_message();
        }
        
        $output = '';
        
        // Citation Metrics Card
        $output .= html_writer::start_div('card citation-metrics-card mt-3');
        $output .= html_writer::start_div('card-header bg-info text-white');
        $output .= html_writer::tag('h4', 'Citation Analytics', ['class' => 'mb-0']);
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('card-body');
        
        // Metrics table
        $output .= html_writer::start_tag('table', ['class' => 'table table-striped citation-metrics-table']);
        $output .= html_writer::start_tag('tbody');
        
        // Total Citations
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('td', html_writer::tag('i', '', ['class' => 'fas fa-quote-right mr-2']) . 'Total Citations');
        $output .= html_writer::tag('td', html_writer::tag('strong', $citation_data['total_citations']), ['class' => 'text-right']);
        $output .= html_writer::end_tag('tr');
        
        // Unique Domains
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('td', html_writer::tag('i', '', ['class' => 'fas fa-globe mr-2']) . 'Unique Domains');
        $output .= html_writer::tag('td', html_writer::tag('strong', $citation_data['unique_domains']), ['class' => 'text-right']);
        $output .= html_writer::end_tag('tr');
        
        // Average Confidence
        $avg_confidence = number_format($citation_data['confidence_avg'], 2);
        $confidence_color = $this->get_score_color($citation_data['confidence_avg']);
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('td', html_writer::tag('i', '', ['class' => 'fas fa-chart-line mr-2']) . 'Average Confidence');
        $output .= html_writer::tag('td', html_writer::tag('strong', $avg_confidence, ['class' => 'text-' . $confidence_color]), ['class' => 'text-right']);
        $output .= html_writer::end_tag('tr');
        
        // Diversity Score
        $diversity_score = number_format($citation_data['diversity_score'], 2);
        $diversity_color = $this->get_score_color($citation_data['diversity_score']);
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('td', html_writer::tag('i', '', ['class' => 'fas fa-sitemap mr-2']) . 'Diversity Score');
        $output .= html_writer::tag('td', html_writer::tag('strong', $diversity_score, ['class' => 'text-' . $diversity_color]), ['class' => 'text-right']);
        $output .= html_writer::end_tag('tr');
        
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        
        // Source type breakdown if available
        if (!empty($citation_data['source_breakdown'])) {
            $output .= html_writer::tag('h5', 'Source Type Distribution', ['class' => 'mt-4']);
            $output .= $this->render_source_breakdown_chart($citation_data['source_breakdown'], $runid);
        }
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render section diagnostics with Gold Standard alignment feedback
     * 
     * @param array $sectiondata Section data with QA metrics
     * @return string HTML output for section diagnostics
     */
    public function render_section_diagnostics($sectiondata) {
        if (empty($sectiondata)) {
            return '';
        }
        
        $output = '';
        
        $output .= html_writer::start_div('section-diagnostics-container');
        $output .= html_writer::tag('h5', 'Section Diagnostics', ['class' => 'mb-3']);
        
        foreach ($sectiondata as $section_name => $data) {
            $output .= $this->render_section_diagnostic_card($section_name, $data);
        }
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render individual section diagnostic card
     * 
     * @param string $section_name Section name
     * @param array $data Section diagnostic data
     * @return string HTML output for section card
     */
    private function render_section_diagnostic_card($section_name, $data) {
        $coherence = $data['coherence'] ?? 0;
        $pattern_alignment = $data['pattern_alignment'] ?? 0;
        $completeness = $data['completeness'] ?? 0;
        
        $overall_score = ($coherence + $pattern_alignment + $completeness) / 3;
        $card_class = $this->get_card_class_by_score($overall_score);
        
        $output = '';
        
        $output .= html_writer::start_div('card section-diagnostic-card ' . $card_class . ' mb-2');
        $output .= html_writer::start_div('card-body p-3');
        
        // Header
        $output .= html_writer::start_div('d-flex justify-content-between align-items-center mb-2');
        $output .= html_writer::tag('h6', ucfirst(str_replace('_', ' ', $section_name)), ['class' => 'mb-0 font-weight-bold']);
        $output .= html_writer::tag('span', number_format($overall_score, 2), ['class' => 'badge badge-light']);
        $output .= html_writer::end_div();
        
        // Mini progress bars
        $output .= html_writer::start_div('row');
        
        $output .= html_writer::start_div('col-4');
        $output .= html_writer::tag('small', 'Coherence', ['class' => 'text-muted']);
        $output .= $this->render_mini_progress_bar($coherence);
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('col-4');
        $output .= html_writer::tag('small', 'Alignment', ['class' => 'text-muted']);
        $output .= $this->render_mini_progress_bar($pattern_alignment);
        $output .= html_writer::end_div();
        
        $output .= html_writer::start_div('col-4');
        $output .= html_writer::tag('small', 'Complete', ['class' => 'text-muted']);
        $output .= $this->render_mini_progress_bar($completeness);
        $output .= html_writer::end_div();
        
        $output .= html_writer::end_div();
        
        // Feedback messages
        if (!empty($data['feedback'])) {
            $output .= html_writer::start_div('mt-2');
            foreach ($data['feedback'] as $feedback) {
                $icon_class = $feedback['type'] === 'warning' ? 'fas fa-exclamation-triangle text-warning' : 'fas fa-info-circle text-info';
                $output .= html_writer::tag('small', 
                    html_writer::tag('i', '', ['class' => $icon_class . ' mr-1']) . $feedback['message'],
                    ['class' => 'd-block text-muted']
                );
            }
            $output .= html_writer::end_div();
        }
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Get QA telemetry data from database
     * 
     * @param int $runid Run identifier
     * @return array QA metrics data
     */
    private function get_qa_telemetry_data($runid) {
        global $DB;
        
        $data = [
            'qa_score_total' => 0,
            'coherence_score' => 0,
            'pattern_alignment_score' => 0,
            'qa_warnings_count' => 0,
            'section_scores' => []
        ];
        
        // Get telemetry records for this run
        $telemetry_records = $DB->get_records('local_ci_telemetry', ['runid' => $runid]);
        
        foreach ($telemetry_records as $record) {
            switch ($record->metrickey) {
                case 'qa_score_total':
                    $data['qa_score_total'] = (float)$record->metricvaluenum;
                    break;
                case 'coherence_score':
                    $data['coherence_score'] = (float)$record->metricvaluenum;
                    break;
                case 'pattern_alignment_score':
                    $data['pattern_alignment_score'] = (float)$record->metricvaluenum;
                    break;
                case 'qa_warnings_count':
                    $data['qa_warnings_count'] = (int)$record->metricvaluenum;
                    break;
                default:
                    // Check for section-specific scores
                    if (strpos($record->metrickey, 'qa_coherence_') === 0) {
                        $section = str_replace('qa_coherence_', '', $record->metrickey);
                        $data['section_scores'][$section]['coherence'] = (float)$record->metricvaluenum;
                    } elseif (strpos($record->metrickey, 'qa_pattern_') === 0) {
                        $section = str_replace('qa_pattern_', '', $record->metrickey);
                        $data['section_scores'][$section]['pattern_alignment'] = (float)$record->metricvaluenum;
                    }
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Get synthesis QA scores from synthesis table
     * 
     * @param int $runid Run identifier
     * @return array QA scores from synthesis
     */
    private function get_synthesis_qa_scores($runid) {
        global $DB;
        
        $synthesis = $DB->get_record('local_ci_synthesis', ['runid' => $runid]);
        if (!$synthesis || empty($synthesis->qa_scores)) {
            return [];
        }
        
        $qa_scores = json_decode($synthesis->qa_scores, true);
        return $qa_scores ?: [];
    }
    
    /**
     * Get telemetry chart data
     * 
     * @param int $runid Run identifier
     * @return array Chart data
     */
    private function get_telemetry_chart_data($runid) {
        global $DB;
        
        $data = [
            'phase_durations' => [],
            'metrics' => []
        ];
        
        // Get all telemetry records for this run
        $telemetry_records = $DB->get_records('local_ci_telemetry', ['runid' => $runid], 'timecreated ASC');
        
        foreach ($telemetry_records as $record) {
            if (strpos($record->metrickey, 'phase_duration_') === 0) {
                $phase_name = str_replace('phase_duration_', '', $record->metrickey);
                $data['phase_durations'][$phase_name] = [
                    'duration_ms' => (float)$record->metricvaluenum,
                    'duration_seconds' => round((float)$record->metricvaluenum / 1000, 2)
                ];
            } elseif (in_array($record->metrickey, ['total_duration_ms', 'coherence_score', 'pattern_alignment_score', 'qa_score_total'])) {
                $data['metrics'][$record->metrickey] = (float)$record->metricvaluenum;
            }
        }
        
        return $data;
    }
    
    /**
     * Get citation metrics data
     * 
     * @param int $runid Run identifier
     * @return array Citation metrics
     */
    private function get_citation_metrics_data($runid) {
        global $DB;
        
        // Get citation metrics from database
        $citation_metrics = $DB->get_record('local_ci_citation_metrics', ['runid' => $runid]);
        
        if (!$citation_metrics) {
            return [];
        }
        
        return [
            'total_citations' => $citation_metrics->total_citations,
            'unique_domains' => $citation_metrics->unique_domains,
            'confidence_avg' => (float)$citation_metrics->confidence_avg,
            'diversity_score' => (float)$citation_metrics->diversity_score,
            'source_breakdown' => json_decode($citation_metrics->source_type_breakdown ?? '[]', true)
        ];
    }
    
    /**
     * Render metric card
     * 
     * @param string $title Metric title
     * @param float $value Metric value
     * @param string $icon Icon class
     * @param string $color Bootstrap color
     * @return string HTML output
     */
    private function render_metric_card($title, $value, $icon, $color) {
        $output = '';
        
        $output .= html_writer::start_div('col-md-4 mb-3');
        $output .= html_writer::start_div('card border-' . $color);
        $output .= html_writer::start_div('card-body text-center p-3');
        
        $output .= html_writer::tag('i', '', ['class' => $icon . ' text-' . $color . ' fa-2x mb-2']);
        $output .= html_writer::tag('h5', number_format($value, 2), ['class' => 'text-' . $color]);
        $output .= html_writer::tag('small', $title, ['class' => 'text-muted']);
        
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render score progress bar
     * 
     * @param float $score Score value (0-1)
     * @return string HTML output
     */
    private function render_score_progress_bar($score) {
        $percentage = $score * 100;
        $color_class = $this->get_score_color($score);
        
        $output = '';
        $output .= html_writer::start_div('progress mt-2', ['style' => 'height: 8px;']);
        $output .= html_writer::div('', 'progress-bar bg-' . $color_class, [
            'style' => 'width: ' . $percentage . '%',
            'aria-valuenow' => $percentage,
            'aria-valuemin' => '0',
            'aria-valuemax' => '100'
        ]);
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render mini progress bar for section diagnostics
     * 
     * @param float $score Score value (0-1)
     * @return string HTML output
     */
    private function render_mini_progress_bar($score) {
        $percentage = $score * 100;
        $color_class = $this->get_score_color($score);
        
        $output = '';
        $output .= html_writer::start_div('progress', ['style' => 'height: 4px;']);
        $output .= html_writer::div('', 'progress-bar bg-' . $color_class, [
            'style' => 'width: ' . $percentage . '%'
        ]);
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
     * Get card class based on score
     * 
     * @param float $score Score value (0-1)
     * @return string Card class
     */
    private function get_card_class_by_score($score) {
        if ($score >= 0.8) {
            return 'border-success';
        } elseif ($score >= 0.6) {
            return 'border-warning';
        } else {
            return 'border-danger';
        }
    }
    
    /**
     * Render fallback QA summary for when interactive UI is disabled
     * 
     * @param int $runid Run identifier
     * @return string HTML output
     */
    private function render_qa_summary_fallback($runid) {
        $qa_data = $this->get_qa_telemetry_data($runid);
        $synthesis_qa = $this->get_synthesis_qa_scores($runid);
        
        $output = '';
        $output .= html_writer::start_div('alert alert-info');
        $output .= html_writer::tag('h5', 'Quality Assessment Summary');
        $output .= html_writer::tag('p', 'Overall Score: ' . number_format($qa_data['qa_score_total'] ?: $synthesis_qa['total_weighted'] ?: 0, 2));
        $output .= html_writer::tag('p', 'Coherence: ' . number_format($qa_data['coherence_score'] ?: $synthesis_qa['coherence'] ?: 0, 2));
        $output .= html_writer::tag('p', 'Pattern Alignment: ' . number_format($qa_data['pattern_alignment_score'] ?: $synthesis_qa['pattern_alignment'] ?: 0, 2));
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render "no telemetry" message
     * 
     * @return string HTML output
     */
    private function render_no_telemetry_message() {
        return html_writer::div(
            html_writer::tag('p', 'No telemetry data available for this report.', ['class' => 'text-muted text-center']),
            'alert alert-info'
        );
    }
    
    /**
     * Render "no citation" message
     * 
     * @return string HTML output
     */
    private function render_no_citation_message() {
        return html_writer::div(
            html_writer::tag('p', 'No citation metrics available for this report.', ['class' => 'text-muted text-center']),
            'alert alert-info'
        );
    }
    
    /**
     * Render phase duration table
     * 
     * @param array $phase_durations Phase duration data
     * @return string HTML output
     */
    private function render_phase_duration_table($phase_durations) {
        if (empty($phase_durations)) {
            return html_writer::tag('p', 'No phase data available', ['class' => 'text-muted']);
        }
        
        $output = '';
        $output .= html_writer::start_tag('table', ['class' => 'table table-sm']);
        $output .= html_writer::start_tag('tbody');
        
        foreach ($phase_durations as $phase => $data) {
            $phase_name = ucfirst(str_replace('_', ' ', $phase));
            $duration = $data['duration_seconds'] . 's';
            
            $output .= html_writer::start_tag('tr');
            $output .= html_writer::tag('td', $phase_name);
            $output .= html_writer::tag('td', $duration, ['class' => 'text-right']);
            $output .= html_writer::end_tag('tr');
        }
        
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        
        return $output;
    }
    
    /**
     * Render key metrics table
     * 
     * @param array $metrics Metrics data
     * @return string HTML output
     */
    private function render_key_metrics_table($metrics) {
        if (empty($metrics)) {
            return html_writer::tag('p', 'No metrics available', ['class' => 'text-muted']);
        }
        
        $output = '';
        $output .= html_writer::start_tag('table', ['class' => 'table table-sm']);
        $output .= html_writer::start_tag('tbody');
        
        $metric_labels = [
            'total_duration_ms' => 'Total Duration',
            'coherence_score' => 'Coherence',
            'pattern_alignment_score' => 'Pattern Alignment',
            'qa_score_total' => 'QA Score'
        ];
        
        foreach ($metrics as $key => $value) {
            $label = $metric_labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            $display_value = $key === 'total_duration_ms' ? round($value / 1000, 2) . 's' : number_format($value, 2);
            
            $output .= html_writer::start_tag('tr');
            $output .= html_writer::tag('td', $label);
            $output .= html_writer::tag('td', $display_value, ['class' => 'text-right']);
            $output .= html_writer::end_tag('tr');
        }
        
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        
        return $output;
    }
    
    /**
     * Render section QA breakdown
     * 
     * @param array $section_scores Section scores data
     * @return string HTML output
     */
    private function render_section_qa_breakdown($section_scores) {
        $output = '';
        $output .= html_writer::start_div('row');
        
        foreach ($section_scores as $section => $scores) {
            $coherence = $scores['coherence'] ?? 0;
            $pattern = $scores['pattern_alignment'] ?? 0;
            $avg_score = ($coherence + $pattern) / 2;
            
            $output .= html_writer::start_div('col-md-6 mb-3');
            $output .= html_writer::start_div('card border-left-' . $this->get_score_color($avg_score));
            $output .= html_writer::start_div('card-body p-3');
            
            $output .= html_writer::tag('h6', ucfirst(str_replace('_', ' ', $section)), ['class' => 'font-weight-bold']);
            $output .= html_writer::tag('small', 'Coherence: ' . number_format($coherence, 2), ['class' => 'd-block text-muted']);
            $output .= html_writer::tag('small', 'Alignment: ' . number_format($pattern, 2), ['class' => 'd-block text-muted']);
            
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
            $output .= html_writer::end_div();
        }
        
        $output .= html_writer::end_div();
        
        return $output;
    }
    
    /**
     * Render source breakdown chart
     * 
     * @param array $source_breakdown Source type breakdown
     * @param int $runid Run identifier
     * @return string HTML output
     */
    private function render_source_breakdown_chart($source_breakdown, $runid) {
        if (empty($source_breakdown)) {
            return html_writer::tag('p', 'No source breakdown available', ['class' => 'text-muted']);
        }
        
        $chart_id = 'source-breakdown-' . $runid;
        
        $output = '';
        $output .= html_writer::tag('canvas', '', [
            'id' => $chart_id,
            'width' => '300',
            'height' => '150',
            'style' => 'max-height: 200px;'
        ]);
        
        // Add Chart.js initialization for source breakdown
        $this->page->requires->js_call_amd('local_customerintel/source_chart', 'init', [
            $chart_id,
            $source_breakdown
        ]);
        
        return $output;
    }
}