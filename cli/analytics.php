<?php
/**
 * Customer Intelligence Dashboard - Analytics & Historical Insights
 * 
 * Provides trend visualization and longitudinal analysis across runs
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();

// Wrap everything in try-catch to capture and display errors
try {

$context = context_system::instance();
require_capability('local/customerintel:view', $context);

// Parameters
$timerange = optional_param('timerange', 30, PARAM_INT);
$metric = optional_param('metric', 'qa_score_total', PARAM_ALPHANUMEXT);
$ajax = optional_param('ajax', 0, PARAM_INT);

// Validate parameters
$valid_timeranges = [7, 30, 90];
$valid_metrics = ['qa_score_total', 'coherence_score', 'pattern_alignment_score'];

if (!in_array($timerange, $valid_timeranges)) {
    $timerange = 30;
}

if (!in_array($metric, $valid_metrics)) {
    $metric = 'qa_score_total';
}

// Set up page
$PAGE->set_url(new moodle_url('/local/customerintel/analytics.php', [
    'timerange' => $timerange,
    'metric' => $metric
]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Analytics Dashboard');
$PAGE->set_heading('Customer Intelligence Analytics');
$PAGE->set_pagelayout('admin');

// Add breadcrumbs
$PAGE->navbar->add('Customer Intelligence', new moodle_url('/local/customerintel/dashboard.php'));
$PAGE->navbar->add('Analytics Dashboard');

// Check if analytics is enabled
$analytics_enabled = get_config('local_customerintel', 'enable_analytics_dashboard');
if ($analytics_enabled === '0') {
    throw new moodle_exception('analyticsnotenabled', 'local_customerintel', 
        new moodle_url('/local/customerintel/dashboard.php'),
        null, 'Analytics dashboard is disabled in plugin settings');
}

// Load required services
require_once($CFG->dirroot . '/local/customerintel/classes/services/analytics_service.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/predictive_engine.php');
$analytics_service = new \local_customerintel\services\analytics_service();
$predictive_engine = new \local_customerintel\services\predictive_engine();

// Initialize renderer
$renderer = $PAGE->get_renderer('local_customerintel');

// Add required CSS and JS
$PAGE->requires->css('/local/customerintel/styles/customerintel.css');
$PAGE->requires->js(new moodle_url('/local/customerintel/js/chart.min.js'));

// Handle AJAX requests
if ($ajax) {
    // Return JSON data for AJAX requests
    header('Content-Type: application/json');
    
    $start_time = microtime(true);
    
    $response = [];
    
    switch ($_GET['action'] ?? '') {
        case 'trends':
            $response = $analytics_service->get_run_trends($metric, $timerange);
            break;
        case 'distribution':
            $response = $analytics_service->get_qa_distribution();
            break;
        case 'correlation':
            $response = $analytics_service->get_coherence_vs_pattern_correlation();
            break;
        case 'citations':
            $response = $analytics_service->get_citation_diversity_vs_confidence();
            break;
        case 'phases':
            $response = $analytics_service->get_phase_duration_breakdown($timerange);
            break;
        case 'summary':
            $response = $analytics_service->get_summary_statistics();
            break;
        case 'recent_runs':
            $limit = intval($_GET['limit'] ?? 20);
            $response = $analytics_service->get_recent_runs($limit);
            break;
        case 'forecast':
            $forecast_metric = $_GET['forecast_metric'] ?? 'qa_score_total';
            $days_ahead = intval($_GET['days_ahead'] ?? 30);
            $response = $predictive_engine->forecast_metric_trend($forecast_metric, $days_ahead);
            break;
        case 'anomalies':
            $anomaly_metric = $_GET['anomaly_metric'] ?? 'qa_score_total';
            $threshold = floatval($_GET['threshold'] ?? 2.0);
            $response = $predictive_engine->detect_anomalies($anomaly_metric, $threshold);
            break;
        case 'risk_signals':
            $response = $predictive_engine->rank_risk_signals();
            break;
        case 'anomaly_summary':
            $response = $predictive_engine->get_anomaly_summary();
            break;
        default:
            $response = ['error' => 'Invalid action'];
            http_response_code(400);
    }
    
    // Log analytics load time
    $load_time = round((microtime(true) - $start_time) * 1000);
    $analytics_service->log_analytics_usage('load_time_ms', [
        'action' => $_GET['action'] ?? 'unknown',
        'duration' => $load_time,
        'timerange' => $timerange,
        'metric' => $metric
    ]);
    
    echo json_encode($response);
    exit;
}

// Log dashboard view
$analytics_service->log_analytics_usage('dashboard_view', [
    'timerange' => $timerange,
    'metric' => $metric
]);

// Get initial data for page load
$start_time = microtime(true);

$initial_data = [
    'summary' => $analytics_service->get_summary_statistics(),
    'recent_runs' => $analytics_service->get_recent_runs(10),
    'trends' => $analytics_service->get_run_trends($metric, $timerange),
    'distribution' => $analytics_service->get_qa_distribution(),
    'phases' => $analytics_service->get_phase_duration_breakdown($timerange)
];

// Only load heavy charts if not in safe mode
if (!$analytics_service->is_safe_mode_enabled()) {
    $initial_data['correlation'] = $analytics_service->get_coherence_vs_pattern_correlation();
    $initial_data['citations'] = $analytics_service->get_citation_diversity_vs_confidence();
}

// Load predictive data if enabled
if ($predictive_engine->is_predictive_enabled() && !$predictive_engine->is_safe_mode_enabled()) {
    $initial_data['forecast'] = $predictive_engine->forecast_metric_trend($metric, 30);
    $initial_data['anomaly_summary'] = $predictive_engine->get_anomaly_summary();
    $initial_data['risk_signals'] = $predictive_engine->rank_risk_signals();
}

$load_time = round((microtime(true) - $start_time) * 1000);

// Output header
echo $OUTPUT->header();

?>

<div class="analytics-dashboard">
    
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Analytics Dashboard</h2>
            <p class="text-muted">Historical insights and trend analysis across intelligence reports</p>
        </div>
        <div class="col-md-4 text-right">
            <?php if ($analytics_service->is_safe_mode_enabled()): ?>
                <span class="badge badge-warning">Safe Mode: Limited Data</span>
            <?php endif; ?>
            <span class="badge badge-info" id="load-time">Load Time: <?php echo $load_time; ?>ms</span>
        </div>
    </div>
    
    <!-- Filter Controls -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <label for="timerange-select">Time Range:</label>
                    <select id="timerange-select" class="form-control">
                        <option value="7" <?php echo $timerange == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $timerange == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $timerange == 90 ? 'selected' : ''; ?>>Last 90 days</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="metric-select">Primary Metric:</label>
                    <select id="metric-select" class="form-control">
                        <option value="qa_score_total" <?php echo $metric == 'qa_score_total' ? 'selected' : ''; ?>>QA Score Total</option>
                        <option value="coherence_score" <?php echo $metric == 'coherence_score' ? 'selected' : ''; ?>>Coherence Score</option>
                        <option value="pattern_alignment_score" <?php echo $metric == 'pattern_alignment_score' ? 'selected' : ''; ?>>Pattern Alignment</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>&nbsp;</label>
                    <button id="refresh-charts" class="btn btn-primary form-control">
                        <i class="fa fa-refresh"></i> Refresh Charts
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="card mb-4">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="analytics-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="historical-tab" data-toggle="tab" href="#historical" role="tab" aria-controls="historical" aria-selected="true">
                        <i class="fa fa-chart-line"></i> Historical Analytics
                    </a>
                </li>
                <?php if ($predictive_engine->is_predictive_enabled() && !$predictive_engine->is_safe_mode_enabled()): ?>
                <li class="nav-item">
                    <a class="nav-link" id="predictive-tab" data-toggle="tab" href="#predictive" role="tab" aria-controls="predictive" aria-selected="false">
                        <i class="fa fa-crystal-ball"></i> Forecast & Anomalies
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="analytics-tab-content">
                
                <!-- Historical Analytics Tab -->
                <div class="tab-pane fade show active" id="historical" role="tabpanel" aria-labelledby="historical-tab">
                    
                    <!-- Summary Widgets -->
                    <div class="row mb-4" id="summary-widgets">
                        <!-- Widgets will be populated by JavaScript -->
                    </div>
    
    <!-- Main Charts Row -->
    <div class="row mb-4">
        
        <!-- QA Score Trends -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">QA Score Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="trends-chart" height="200"></canvas>
                    <div id="trends-loading" class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Phase Duration Breakdown -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Phase Duration Breakdown</h5>
                </div>
                <div class="card-body">
                    <canvas id="phases-chart" height="200"></canvas>
                    <div id="phases-loading" class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Secondary Charts Row -->
    <div class="row mb-4">
        
        <!-- QA Score Distribution -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">QA Score Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="distribution-chart" height="200"></canvas>
                    <div id="distribution-loading" class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advanced Charts (if not in safe mode) -->
        <?php if (!$analytics_service->is_safe_mode_enabled()): ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Coherence vs Pattern Alignment</h5>
                </div>
                <div class="card-body">
                    <canvas id="correlation-chart" height="200"></canvas>
                    <div id="correlation-loading" class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center text-muted">
                    <i class="fa fa-shield fa-3x mb-3"></i>
                    <h5>Advanced Charts Disabled</h5>
                    <p>Enable full analytics mode in settings to view correlation and citation analysis charts.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <?php if (!$analytics_service->is_safe_mode_enabled()): ?>
    <!-- Citation Analysis Row -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Citation Diversity vs Confidence</h5>
                </div>
                <div class="card-body">
                    <canvas id="citations-chart" height="100"></canvas>
                    <div id="citations-loading" class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Runs Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Runs</h5>
                    <button id="load-more-runs" class="btn btn-sm btn-outline-primary">Load More</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="recent-runs-table">
                            <thead>
                                <tr>
                                    <th>Run ID</th>
                                    <th>Company</th>
                                    <th>Target</th>
                                    <th>QA Score</th>
                                    <th>Coherence</th>
                                    <th>Duration</th>
                                    <th>Completed</th>
                                </tr>
                            </thead>
                            <tbody id="recent-runs-tbody">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
                </div>
                <!-- End Historical Analytics Tab -->
                
                <?php if ($predictive_engine->is_predictive_enabled() && !$predictive_engine->is_safe_mode_enabled()): ?>
                <!-- Predictive Analytics Tab -->
                <div class="tab-pane fade" id="predictive" role="tabpanel" aria-labelledby="predictive-tab">
                    
                    <!-- Predictive Summary Row -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fa fa-chart-line"></i> Metric Forecast</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="forecast-metric-select">Forecast Metric:</label>
                                            <select id="forecast-metric-select" class="form-control">
                                                <option value="qa_score_total">QA Score Total</option>
                                                <option value="coherence_score">Coherence Score</option>
                                                <option value="pattern_alignment_score">Pattern Alignment</option>
                                                <option value="total_duration_ms">Processing Duration</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="forecast-days-select">Forecast Horizon:</label>
                                            <select id="forecast-days-select" class="form-control">
                                                <option value="7">7 days</option>
                                                <option value="14">14 days</option>
                                                <option value="30" selected>30 days</option>
                                                <option value="60">60 days</option>
                                            </select>
                                        </div>
                                    </div>
                                    <canvas id="forecast-chart" height="200"></canvas>
                                    <div id="forecast-loading" class="text-center py-4" style="display: none;">
                                        <div class="spinner-border" role="status">
                                            <span class="sr-only">Loading forecast...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fa fa-exclamation-triangle"></i> Anomaly Summary</h5>
                                </div>
                                <div class="card-body" id="anomaly-summary-card">
                                    <!-- Populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Risk Signals and Anomalies Row -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fa fa-radar-chart"></i> Risk Radar</h5>
                                </div>
                                <div class="card-body">
                                    <div id="risk-signals-container">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fa fa-exclamation-circle"></i> Recent Anomalies</h5>
                                    <div>
                                        <label for="anomaly-threshold" class="sr-only">Threshold</label>
                                        <select id="anomaly-threshold" class="form-control form-control-sm d-inline-block" style="width: auto;">
                                            <option value="1.5">Low Sensitivity (1.5σ)</option>
                                            <option value="2.0" selected>Normal (2.0σ)</option>
                                            <option value="2.5">High Sensitivity (2.5σ)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm" id="anomalies-table">
                                            <thead>
                                                <tr>
                                                    <th>Metric</th>
                                                    <th>Date</th>
                                                    <th>Deviation</th>
                                                    <th>Severity</th>
                                                </tr>
                                            </thead>
                                            <tbody id="anomalies-tbody">
                                                <!-- Populated by JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <!-- End Predictive Analytics Tab -->
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
</div>

<!-- JavaScript for Chart Management and AJAX -->
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    
    // Global chart objects
    let charts = {};
    let currentTimerange = <?php echo $timerange; ?>;
    let currentMetric = '<?php echo $metric; ?>';
    let initialData = <?php echo json_encode($initial_data); ?>;
    
    // Initialize charts with initial data
    initializeCharts();
    populateSummaryWidgets(initialData.summary);
    populateRecentRuns(initialData.recent_runs);
    
    // Initialize predictive features if available
    if (initialData.forecast) {
        initializePredictiveFeatures();
    }
    
    // Event listeners
    document.getElementById('refresh-charts').addEventListener('click', refreshAllCharts);
    document.getElementById('timerange-select').addEventListener('change', handleFilterChange);
    document.getElementById('metric-select').addEventListener('change', handleFilterChange);
    document.getElementById('load-more-runs').addEventListener('click', loadMoreRuns);
    
    // Predictive event listeners
    const forecastMetricSelect = document.getElementById('forecast-metric-select');
    const forecastDaysSelect = document.getElementById('forecast-days-select');
    const anomalyThresholdSelect = document.getElementById('anomaly-threshold');
    
    if (forecastMetricSelect) {
        forecastMetricSelect.addEventListener('change', updateForecastChart);
        forecastDaysSelect.addEventListener('change', updateForecastChart);
        anomalyThresholdSelect.addEventListener('change', updateAnomaliesTable);
    }
    
    function initializeCharts() {
        // Hide loading indicators and show charts
        hideLoadingIndicators();
        
        // Initialize Trends Chart
        if (initialData.trends && initialData.trends.labels) {
            charts.trends = new Chart(document.getElementById('trends-chart'), {
                type: 'line',
                data: initialData.trends,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'QA Score Trends Over Time' },
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 1.0 }
                    }
                }
            });
        }
        
        // Initialize Phase Duration Chart
        if (initialData.phases && initialData.phases.datasets) {
            charts.phases = new Chart(document.getElementById('phases-chart'), {
                type: 'bar',
                data: initialData.phases,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'Average Phase Durations' }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, title: { display: true, text: 'Seconds' } }
                    }
                }
            });
        }
        
        // Initialize Distribution Chart
        if (initialData.distribution && initialData.distribution.labels) {
            charts.distribution = new Chart(document.getElementById('distribution-chart'), {
                type: 'doughnut',
                data: initialData.distribution,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'QA Score Distribution' },
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
        
        // Initialize Correlation Chart (if available)
        if (initialData.correlation && initialData.correlation.datasets) {
            charts.correlation = new Chart(document.getElementById('correlation-chart'), {
                type: 'scatter',
                data: initialData.correlation,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'Coherence vs Pattern Alignment' }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Coherence Score' }, min: 0, max: 1 },
                        y: { title: { display: true, text: 'Pattern Alignment Score' }, min: 0, max: 1 }
                    }
                }
            });
        }
        
        // Initialize Citations Chart (if available)
        if (initialData.citations && initialData.citations.datasets) {
            charts.citations = new Chart(document.getElementById('citations-chart'), {
                type: 'bubble',
                data: initialData.citations,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'Citation Diversity vs Confidence (Bubble size = Citation count)' }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Confidence Average' }, min: 0, max: 1 },
                        y: { title: { display: true, text: 'Diversity Score' }, min: 0, max: 1 }
                    }
                }
            });
        }
    }
    
    function hideLoadingIndicators() {
        ['trends', 'phases', 'distribution', 'correlation', 'citations'].forEach(chart => {
            const loading = document.getElementById(chart + '-loading');
            if (loading) loading.style.display = 'none';
        });
    }
    
    function populateSummaryWidgets(summary) {
        if (!summary) return;
        
        const widgetsHtml = `
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary">${summary.avg_qa_score || '0.00'}</h3>
                        <p class="card-text">Average QA Score</p>
                        <small class="text-muted">Last 30 days</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success">${summary.fastest_phase ? summary.fastest_phase.duration + 's' : 'N/A'}</h3>
                        <p class="card-text">Fastest Phase</p>
                        <small class="text-muted">${summary.fastest_phase ? summary.fastest_phase.name : 'No data'}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info">${summary.success_rate || '0'}%</h3>
                        <p class="card-text">Success Rate</p>
                        <small class="text-muted">${summary.total_runs || 0} total runs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning">${summary.common_error || 'None'}</h3>
                        <p class="card-text">Common Error</p>
                        <small class="text-muted">Most frequent</small>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('summary-widgets').innerHTML = widgetsHtml;
    }
    
    function populateRecentRuns(runs) {
        if (!runs || !runs.length) return;
        
        const tbody = document.getElementById('recent-runs-tbody');
        const rowsHtml = runs.map(run => `
            <tr>
                <td><a href="view_report.php?runid=${run.id}">${run.id}</a></td>
                <td>${run.company_name || 'Unknown'} ${run.company_ticker ? '(' + run.company_ticker + ')' : ''}</td>
                <td>${run.target_company_name || 'None'}</td>
                <td><span class="badge badge-${getScoreBadgeClass(run.qa_metrics?.qa_score_total || 0)}">${(run.qa_metrics?.qa_score_total || 0).toFixed(2)}</span></td>
                <td>${(run.qa_metrics?.coherence_score || 0).toFixed(2)}</td>
                <td>${run.telemetry_summary?.duration_seconds || 0}s</td>
                <td>${new Date(run.timecompleted * 1000).toLocaleDateString()}</td>
            </tr>
        `).join('');
        
        tbody.innerHTML = rowsHtml;
    }
    
    function getScoreBadgeClass(score) {
        if (score >= 0.8) return 'success';
        if (score >= 0.6) return 'warning';
        return 'danger';
    }
    
    function handleFilterChange() {
        currentTimerange = parseInt(document.getElementById('timerange-select').value);
        currentMetric = document.getElementById('metric-select').value;
        refreshAllCharts();
    }
    
    function refreshAllCharts() {
        const startTime = Date.now();
        
        // Show loading indicators
        ['trends', 'phases', 'distribution', 'correlation', 'citations'].forEach(chart => {
            const loading = document.getElementById(chart + '-loading');
            if (loading) loading.style.display = 'block';
        });
        
        // Refresh summary
        fetch(`analytics.php?ajax=1&action=summary&timerange=${currentTimerange}`)
            .then(response => response.json())
            .then(data => populateSummaryWidgets(data));
        
        // Refresh trends chart
        fetch(`analytics.php?ajax=1&action=trends&metric=${currentMetric}&timerange=${currentTimerange}`)
            .then(response => response.json())
            .then(data => {
                if (charts.trends) {
                    charts.trends.data = data;
                    charts.trends.update();
                }
                document.getElementById('trends-loading').style.display = 'none';
            });
        
        // Refresh phases chart
        fetch(`analytics.php?ajax=1&action=phases&timerange=${currentTimerange}`)
            .then(response => response.json())
            .then(data => {
                if (charts.phases) {
                    charts.phases.data = data;
                    charts.phases.update();
                }
                document.getElementById('phases-loading').style.display = 'none';
            });
        
        // Update load time
        const loadTime = Date.now() - startTime;
        document.getElementById('load-time').textContent = `Load Time: ${loadTime}ms`;
    }
    
    function loadMoreRuns() {
        const currentRows = document.querySelectorAll('#recent-runs-tbody tr').length;
        fetch(`analytics.php?ajax=1&action=recent_runs&limit=${currentRows + 20}`)
            .then(response => response.json())
            .then(data => populateRecentRuns(data));
    }
    
    // Predictive Analytics Functions
    function initializePredictiveFeatures() {
        if (initialData.forecast && initialData.forecast.forecast) {
            initializeForecastChart();
        }
        
        if (initialData.anomaly_summary) {
            populateAnomalySummary(initialData.anomaly_summary);
        }
        
        if (initialData.risk_signals) {
            populateRiskSignals(initialData.risk_signals);
        }
        
        // Load initial anomalies for qa_score_total
        updateAnomaliesTable();
    }
    
    function initializeForecastChart() {
        const canvas = document.getElementById('forecast-chart');
        if (!canvas || !initialData.forecast || !initialData.forecast.forecast) return;
        
        const ctx = canvas.getContext('2d');
        const forecastData = initialData.forecast;
        
        // Combine historical and forecast data for chart
        const allLabels = [...forecastData.historical.labels, ...forecastData.forecast.labels];
        const historicalValues = [...forecastData.historical.values, ...Array(forecastData.forecast.values.length).fill(null)];
        const forecastValues = [...Array(forecastData.historical.values.length).fill(null), ...forecastData.forecast.values];
        const upperBound = [...Array(forecastData.historical.values.length).fill(null), ...forecastData.forecast.confidence_upper];
        const lowerBound = [...Array(forecastData.historical.values.length).fill(null), ...forecastData.forecast.confidence_lower];
        
        charts.forecast = new Chart(ctx, {
            type: 'line',
            data: {
                labels: allLabels,
                datasets: [
                    {
                        label: 'Historical',
                        data: historicalValues,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.4,
                        pointRadius: 3
                    },
                    {
                        label: 'Forecast',
                        data: forecastValues,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        borderDash: [5, 5],
                        tension: 0.4,
                        pointRadius: 3
                    },
                    {
                        label: 'Confidence Range',
                        data: upperBound,
                        borderColor: 'rgba(255, 99, 132, 0.3)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        fill: '+1',
                        pointRadius: 0
                    },
                    {
                        label: '',
                        data: lowerBound,
                        borderColor: 'rgba(255, 99, 132, 0.3)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: `${forecastData.metrickey} Forecast (R² = ${(forecastData.regression.r_squared * 100).toFixed(1)}%)`
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Date' }
                    },
                    y: {
                        title: { display: true, text: 'Value' },
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function populateAnomalySummary(summary) {
        const container = document.getElementById('anomaly-summary-card');
        if (!container) return;
        
        const severityColors = {
            critical: 'danger',
            high: 'warning', 
            medium: 'info',
            low: 'secondary'
        };
        
        let html = `
            <div class="mb-3">
                <h6 class="mb-2">Total Anomalies: <span class="badge badge-primary">${summary.total}</span></h6>
            </div>
            <div class="mb-3">
                <h6 class="mb-2">By Severity:</h6>
        `;
        
        Object.entries(summary.by_severity).forEach(([severity, count]) => {
            if (count > 0) {
                html += `<span class="badge badge-${severityColors[severity]} mr-1">${severity}: ${count}</span>`;
            }
        });
        
        html += `</div>`;
        
        if (summary.recent.length > 0) {
            html += `
                <div>
                    <h6 class="mb-2">Most Recent:</h6>
                    <small class="text-muted">
                        ${summary.recent[0].metrickey} anomaly<br>
                        ${summary.recent[0].date}<br>
                        Z-score: ${summary.recent[0].z_score}
                    </small>
                </div>
            `;
        }
        
        container.innerHTML = html;
    }
    
    function populateRiskSignals(riskSignals) {
        const container = document.getElementById('risk-signals-container');
        if (!container) return;
        
        if (riskSignals.length === 0) {
            container.innerHTML = '<p class="text-muted">No significant risk signals detected.</p>';
            return;
        }
        
        const severityColors = {
            critical: 'danger',
            high: 'warning',
            medium: 'info', 
            low: 'success'
        };
        
        let html = '<div class="risk-signals-list">';
        
        riskSignals.forEach((signal, index) => {
            html += `
                <div class="risk-signal-item mb-3 p-3 border rounded">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">${signal.metric_display_name}</h6>
                        <span class="badge badge-${severityColors[signal.severity]}">
                            Risk: ${signal.risk_score}
                        </span>
                    </div>
                    <p class="small text-muted mb-2">${signal.recommendation}</p>
                    <div class="row">
                        <div class="col-6">
                            <small><strong>Anomalies:</strong> ${signal.anomaly_count}</small>
                        </div>
                        <div class="col-6">
                            <small><strong>Avg Z-score:</strong> ${signal.avg_z_score}</small>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    function updateForecastChart() {
        const metric = document.getElementById('forecast-metric-select').value;
        const days = parseInt(document.getElementById('forecast-days-select').value);
        
        document.getElementById('forecast-loading').style.display = 'block';
        
        fetch(`analytics.php?ajax=1&action=forecast&forecast_metric=${metric}&days_ahead=${days}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('forecast-loading').style.display = 'none';
                
                if (data.error) {
                    console.error('Forecast error:', data.error);
                    return;
                }
                
                // Update chart with new data
                if (charts.forecast) {
                    charts.forecast.destroy();
                }
                
                // Update initialData for the chart initialization
                initialData.forecast = data;
                initializeForecastChart();
            })
            .catch(error => {
                document.getElementById('forecast-loading').style.display = 'none';
                console.error('Forecast request failed:', error);
            });
    }
    
    function updateAnomaliesTable() {
        const metric = document.getElementById('forecast-metric-select') ? 
                      document.getElementById('forecast-metric-select').value : 'qa_score_total';
        const threshold = parseFloat(document.getElementById('anomaly-threshold').value);
        
        fetch(`analytics.php?ajax=1&action=anomalies&anomaly_metric=${metric}&threshold=${threshold}`)
            .then(response => response.json())
            .then(anomalies => {
                const tbody = document.getElementById('anomalies-tbody');
                if (!tbody) return;
                
                if (anomalies.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No anomalies detected</td></tr>';
                    return;
                }
                
                const severityColors = {
                    critical: 'danger',
                    high: 'warning',
                    medium: 'info',
                    low: 'secondary'
                };
                
                const rowsHtml = anomalies.slice(0, 10).map(anomaly => `
                    <tr>
                        <td>${anomaly.metrickey}</td>
                        <td>${new Date(anomaly.timestamp * 1000).toLocaleDateString()}</td>
                        <td>${anomaly.deviation > 0 ? '+' : ''}${anomaly.deviation.toFixed(3)}</td>
                        <td><span class="badge badge-${severityColors[anomaly.severity]}">${anomaly.severity}</span></td>
                    </tr>
                `).join('');
                
                tbody.innerHTML = rowsHtml;
            })
            .catch(error => {
                console.error('Anomalies request failed:', error);
            });
    }
    
});
</script>

<?php

echo $OUTPUT->footer();

} catch (Throwable $e) {
    // Log the error to server logs
    error_log("CustomerIntel Analytics Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Display error on screen for debugging
    echo "<div style='background-color: #ffcccc; border: 2px solid #ff0000; padding: 20px; margin: 20px; font-family: monospace;'>";
    echo "<h2 style='color: #cc0000;'>CustomerIntel Analytics Error</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    
    echo "<details style='margin-top: 20px;'>";
    echo "<summary style='cursor: pointer; color: #cc0000; font-weight: bold;'>Stack Trace (click to expand)</summary>";
    echo "<pre style='background-color: #f0f0f0; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</details>";
    echo "</div>";
    
    // Also try to output the footer if possible
    if (isset($OUTPUT)) {
        try {
            echo $OUTPUT->footer();
        } catch (Exception $footer_error) {
            // Ignore footer errors
        }
    }
    
    exit;
}
?>