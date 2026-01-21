<?php
/**
 * Customer Intelligence Dashboard - View Transparent Pipeline Trace
 *
 * Displays all artifacts for a given runid in a clean, organized interface
 * for transparency and debugging purposes.
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security
require_login();

try {

$context = context_system::instance();
require_capability('local/customerintel:view', $context);

// Required parameter
$runid = required_param('runid', PARAM_INT);

// Verify the run exists
$run = $DB->get_record('local_ci_run', ['id' => $runid], '*', MUST_EXIST);

// Check user permissions
$can_manage = has_capability('local/customerintel:manage', $context);
if ($run->initiatedbyuserid != $USER->id && !$can_manage) {
    throw new moodle_exception('nopermission', 'local_customerintel');
}

// Check if trace mode is enabled
$trace_mode_enabled = get_config('local_customerintel', 'enable_trace_mode');
if ($trace_mode_enabled !== '1') {
    throw new moodle_exception('tracemodenotenabled', 'local_customerintel', '', null, 
        'Transparent pipeline tracing is not enabled. Please enable it in the Customer Intelligence settings.');
}

// Get company details
$company = $DB->get_record('local_ci_company', ['id' => $run->companyid], '*', MUST_EXIST);
$targetcompany = null;
if ($run->targetcompanyid) {
    $targetcompany = $DB->get_record('local_ci_company', ['id' => $run->targetcompanyid]);
}

// Set up page
$PAGE->set_url(new moodle_url('/local/customerintel/view_trace.php', ['runid' => $runid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Data Trace - Run ' . $runid);
$PAGE->set_heading('Transparent Pipeline View');
$PAGE->set_pagelayout('admin');

// Add breadcrumbs
$PAGE->navbar->add('Reports', new moodle_url('/local/customerintel/reports.php'));
$PAGE->navbar->add('View Report', new moodle_url('/local/customerintel/view_report.php', ['runid' => $runid]));
$PAGE->navbar->add('Data Trace');

// Add CSS for styling
$PAGE->requires->css('/local/customerintel/styles/customerintel.css'); 

// Initialize artifact repository
require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_repository.php');
$artifact_repo = new \local_customerintel\services\artifact_repository();

// Get artifacts and statistics
$artifacts = $artifact_repo->get_artifacts_for_run($runid);
$stats = $artifact_repo->get_artifact_stats($runid);

// Organize artifacts by phase
$artifacts_by_phase = [];
foreach ($artifacts as $artifact) {
    if (!isset($artifacts_by_phase[$artifact->phase])) {
        $artifacts_by_phase[$artifact->phase] = [];
    }
    $artifacts_by_phase[$artifact->phase][] = $artifact;
}

// Define phase display order and titles
$phase_info = [
    'discovery' => [
        'title' => 'Discovery Phase',
        'description' => 'Pattern detection and target bridge building',
        'icon' => '🔍'
    ],
    'nb_orchestration' => [
        'title' => 'NB Orchestration',
        'description' => 'Normalized inputs from NB results processing',
        'icon' => '⚙️'
    ],
    'retrieval_rebalancing' => [
        'title' => 'Retrieval Rebalancing',
        'description' => 'Citation diversity optimization and domain concentration reduction',
        'icon' => '🎯'
    ],
    'assembler' => [
        'title' => 'Assembler Phase',
        'description' => 'Pre-assembled sections from assembler service',
        'icon' => '🔧'
    ],
    'synthesis' => [
        'title' => 'Synthesis Phase',
        'description' => 'Section drafting and final bundle creation',
        'icon' => '📝'
    ],
    'qa' => [
        'title' => 'Quality Assurance',
        'description' => 'QA scoring and validation results',
        'icon' => '✅'
    ]
];

echo $OUTPUT->header();

?>

<div class="customerintel-trace-container">
    <div class="trace-header">
        <h2>🔍 Transparent Pipeline View</h2>
        <p class="trace-subtitle">Complete data lineage for Run #<?php echo $runid; ?></p>
    </div>

    <!-- Run Metadata -->
    <div class="trace-run-info">
        <div class="run-metadata card">
            <div class="card-header">
                <h3>📊 Run Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>Run ID:</th><td><?php echo $runid; ?></td></tr>
                            <tr><th>Company:</th><td><?php echo format_string($company->name); ?></td></tr>
                            <?php if ($targetcompany): ?>
                            <tr><th>Target:</th><td><?php echo format_string($targetcompany->name); ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Status:</th><td><span class="badge badge-<?php echo $run->status === 'completed' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($run->status); ?></span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>Started:</th><td><?php echo $run->timestarted ? userdate($run->timestarted) : 'N/A'; ?></td></tr>
                            <tr><th>Completed:</th><td><?php echo $run->timecompleted ? userdate($run->timecompleted) : 'N/A'; ?></td></tr>
                            <tr><th>Duration:</th><td><?php echo ($run->timestarted && $run->timecompleted) ? gmdate('H:i:s', $run->timecompleted - $run->timestarted) : 'N/A'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Artifact Statistics -->
    <div class="trace-stats">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_count']; ?></div>
                <div class="stat-label">Total Artifacts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($stats['phases']); ?></div>
                <div class="stat-label">Pipeline Phases</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo \local_customerintel\services\artifact_repository::format_size($stats['total_size']); ?></div>
                <div class="stat-label">Total Data Size</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo !empty($stats['timespan']) ? gmdate('H:i:s', $stats['timespan']['duration_seconds']) : 'N/A'; ?></div>
                <div class="stat-label">Capture Duration</div>
            </div>
        </div>
    </div>

    <?php 
    // Get diversity metrics from local_ci_citation_metrics table if available
    $diversity_summary = null;
    try {
        $diversity_record = $DB->get_record('local_ci_citation_metrics', ['runid' => $runid]);
        if ($diversity_record) {
            $diversity_summary = [
                'total_citations' => $diversity_record->total_citations,
                'unique_domains' => $diversity_record->unique_domains,
                'diversity_score' => $diversity_record->diversity_score * 100, // Convert to 0-100 scale
                'source_distribution' => json_decode($diversity_record->source_distribution, true),
                'recency_mix' => json_decode($diversity_record->recency_mix, true)
            ];
        }
    } catch (Exception $e) {
        // Ignore errors - diversity summary is optional
    }
    ?>

    <?php if ($diversity_summary): ?>
    <!-- Diversity Summary -->
    <div class="diversity-summary-section">
        <div class="card">
            <div class="card-header">
                <h3>🎯 Citation Diversity Summary</h3>
                <p class="phase-description">Overall diversity metrics and rebalancing results for this run</p>
            </div>
            <div class="card-body">
                <div class="diversity-summary-grid">
                    <div class="diversity-stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo round($diversity_summary['diversity_score'], 1); ?>/100</div>
                            <div class="stat-label">Diversity Score</div>
                        </div>
                    </div>
                    <div class="diversity-stat-card">
                        <div class="stat-icon">🌐</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $diversity_summary['unique_domains']; ?></div>
                            <div class="stat-label">Unique Domains</div>
                        </div>
                    </div>
                    <div class="diversity-stat-card">
                        <div class="stat-icon">📚</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $diversity_summary['total_citations']; ?></div>
                            <div class="stat-label">Total Citations</div>
                        </div>
                    </div>
                    <div class="diversity-stat-card">
                        <div class="stat-icon">⚖️</div>
                        <div class="stat-content">
                            <?php 
                            $max_concentration = 0;
                            if ($diversity_summary['source_distribution'] && isset($diversity_summary['source_distribution']['max_concentration'])) {
                                $max_concentration = $diversity_summary['source_distribution']['max_concentration'];
                            }
                            ?>
                            <div class="stat-number"><?php echo round($max_concentration, 1); ?>%</div>
                            <div class="stat-label">Max Domain Concentration</div>
                        </div>
                    </div>
                </div>
                
                <?php if ($diversity_summary['recency_mix'] && isset($diversity_summary['recency_mix']['rebalancing_applied'])): ?>
                <div class="rebalancing-summary">
                    <?php 
                    $rebalancing_data = $diversity_summary['recency_mix'];
                    $rebalancing_applied = $rebalancing_data['rebalancing_applied'] ?? false;
                    ?>
                    <h5>🔄 Rebalancing Status</h5>
                    <?php if ($rebalancing_applied): ?>
                        <div class="alert alert-success">
                            <strong>✅ Rebalancing Applied</strong><br>
                            Strategy: <?php echo ucwords(str_replace('_', ' ', $rebalancing_data['strategy_type'] ?? 'Domain Diversification')); ?><br>
                            <?php if (isset($rebalancing_data['improvement_metrics'])): ?>
                                <?php $improvements = $rebalancing_data['improvement_metrics']; ?>
                                Improvements: 
                                <?php if ($improvements['diversity_score_improvement'] > 0): ?>
                                    Diversity +<?php echo round($improvements['diversity_score_improvement'], 1); ?>
                                <?php endif; ?>
                                <?php if ($improvements['unique_domains_increase'] > 0): ?>
                                    , Domains +<?php echo $improvements['unique_domains_increase']; ?>
                                <?php endif; ?>
                                <?php if ($improvements['domain_concentration_reduction'] > 0): ?>
                                    , Concentration -<?php echo round($improvements['domain_concentration_reduction'], 1); ?>%
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>ℹ️ No Rebalancing Needed</strong><br>
                            Citation diversity was already within acceptable thresholds.
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($artifacts)): ?>
    
    <!-- No artifacts message -->
    <div class="alert alert-info">
        <h4>🔍 No Pipeline Artifacts Found</h4>
        <p>No artifacts were captured for this run. This could happen if:</p>
        <ul>
            <li>The run was completed before transparent pipeline tracing was enabled</li>
            <li>Trace mode was disabled during this run's execution</li>
            <li>The artifacts have been cleaned up due to retention policies</li>
        </ul>
        <p><strong>Note:</strong> Trace mode is currently <span class="badge badge-success">enabled</span>. Future runs will capture pipeline artifacts.</p>
    </div>
    
    <?php else: ?>

    <!-- Pipeline Phases -->
    <div class="trace-phases">
        <?php foreach ($phase_info as $phase_key => $phase_data): ?>
            <?php if (isset($artifacts_by_phase[$phase_key])): ?>
            <div class="phase-section card">
                <div class="card-header">
                    <h3><?php echo $phase_data['icon']; ?> <?php echo $phase_data['title']; ?></h3>
                    <p class="phase-description"><?php echo $phase_data['description']; ?></p>
                </div>
                <div class="card-body">
                    <?php foreach ($artifacts_by_phase[$phase_key] as $artifact): ?>
                    <div class="artifact-item">
                        <div class="artifact-header">
                            <h4><?php echo format_string($artifact->artifacttype); ?></h4>
                            <div class="artifact-meta">
                                <span class="badge badge-secondary"><?php echo \local_customerintel\services\artifact_repository::format_size(strlen($artifact->jsondata)); ?></span>
                                <span class="text-muted"><?php echo userdate($artifact->timecreated); ?></span>
                            </div>
                        </div>
                        <div class="artifact-content">
                            <?php
                            $data = \local_customerintel\services\artifact_repository::decode_artifact_data($artifact->jsondata);
                            if ($data !== null) {
                                echo '<div class="artifact-summary">';
                                
                                // Create human-readable summary
                                if ($artifact->artifacttype === 'normalized_inputs') {
                                    $nb_count = isset($data['nb']) ? count($data['nb']) : 0;
                                    echo "<p><strong>Summary:</strong> Processed {$nb_count} NB results into normalized structure</p>";
                                    if ($nb_count > 0) {
                                        echo '<p><strong>NB Codes:</strong> ' . implode(', ', array_keys($data['nb'])) . '</p>';
                                    }
                                } elseif ($artifact->artifacttype === 'detected_patterns') {
                                    echo '<p><strong>Summary:</strong> Pattern detection analysis</p>';
                                    if (is_array($data)) {
                                        echo '<p><strong>Patterns:</strong> ' . count($data) . ' detected</p>';
                                    }
                                } elseif ($artifact->artifacttype === 'target_bridge') {
                                    echo '<p><strong>Summary:</strong> Target-relevance bridge construction</p>';
                                    if (isset($data['source_normalized']['name'])) {
                                        echo '<p><strong>Source:</strong> ' . format_string($data['source_normalized']['name']) . '</p>';
                                    }
                                    if (isset($data['target_normalized']['name'])) {
                                        echo '<p><strong>Target:</strong> ' . format_string($data['target_normalized']['name']) . '</p>';
                                    }
                                } elseif ($artifact->artifacttype === 'assembled_sections' || $artifact->artifacttype === 'drafted_sections') {
                                    $section_count = is_array($data) ? count($data) : 0;
                                    echo "<p><strong>Summary:</strong> {$section_count} sections " . ($artifact->artifacttype === 'assembled_sections' ? 'assembled' : 'drafted') . "</p>";
                                    if ($section_count > 0) {
                                        echo '<p><strong>Sections:</strong> ' . implode(', ', array_keys($data)) . '</p>';
                                    }
                                } elseif ($artifact->artifacttype === 'final_bundle') {
                                    echo '<p><strong>Summary:</strong> Complete synthesis bundle with HTML, JSON, and reports</p>';
                                    if (isset($data['html'])) {
                                        echo '<p><strong>HTML Size:</strong> ' . \local_customerintel\services\artifact_repository::format_size(strlen($data['html'])) . '</p>';
                                    }
                                    if (isset($data['citations']) && is_array($data['citations'])) {
                                        echo '<p><strong>Citations:</strong> ' . count($data['citations']) . ' sources</p>';
                                    }
                                } elseif ($artifact->artifacttype === 'qa_scores') {
                                    echo '<p><strong>Summary:</strong> Quality assurance scoring results</p>';
                                    if (isset($data['coherence_score'])) {
                                        echo '<p><strong>Coherence Score:</strong> ' . round($data['coherence_score'], 3) . '</p>';
                                    }
                                    if (isset($data['pattern_alignment_score'])) {
                                        echo '<p><strong>Pattern Alignment:</strong> ' . round($data['pattern_alignment_score'], 3) . '</p>';
                                    }
                                    if (isset($data['qa_warnings']) && is_array($data['qa_warnings'])) {
                                        echo '<p><strong>Warnings:</strong> ' . count($data['qa_warnings']) . '</p>';
                                    }
                                } elseif ($artifact->artifacttype === 'diversity_metrics') {
                                    echo '<p><strong>Summary:</strong> Citation diversity analysis and rebalancing results</p>';
                                    
                                    // Display before/after diversity metrics
                                    if (isset($data['before_rebalancing']) && isset($data['after_rebalancing'])) {
                                        $before = $data['before_rebalancing'];
                                        $after = $data['after_rebalancing'];
                                        
                                        echo '<div class="diversity-metrics-grid">';
                                        echo '<div class="diversity-before">';
                                        echo '<h5>📊 Before Rebalancing</h5>';
                                        echo '<p><strong>Diversity Score:</strong> ' . round($before['diversity_score'], 1) . '/100</p>';
                                        echo '<p><strong>Unique Domains:</strong> ' . $before['unique_domains'] . '</p>';
                                        echo '<p><strong>Max Concentration:</strong> ' . round($before['max_domain_concentration'], 1) . '%</p>';
                                        echo '</div>';
                                        
                                        echo '<div class="diversity-after">';
                                        echo '<h5>🎯 After Rebalancing</h5>';
                                        echo '<p><strong>Diversity Score:</strong> ' . round($after['diversity_score'], 1) . '/100</p>';
                                        echo '<p><strong>Unique Domains:</strong> ' . $after['unique_domains'] . '</p>';
                                        echo '<p><strong>Max Concentration:</strong> ' . round($after['max_domain_concentration'], 1) . '%</p>';
                                        echo '</div>';
                                        echo '</div>';
                                        
                                        // Show improvement metrics
                                        if (isset($data['improvement_metrics'])) {
                                            $improvements = $data['improvement_metrics'];
                                            echo '<div class="improvement-metrics">';
                                            echo '<h5>📈 Improvements</h5>';
                                            if ($improvements['diversity_score_improvement'] > 0) {
                                                echo '<p class="improvement-positive">📈 Diversity Score: +' . round($improvements['diversity_score_improvement'], 1) . '</p>';
                                            }
                                            if ($improvements['unique_domains_increase'] > 0) {
                                                echo '<p class="improvement-positive">📈 Unique Domains: +' . $improvements['unique_domains_increase'] . '</p>';
                                            }
                                            if ($improvements['domain_concentration_reduction'] > 0) {
                                                echo '<p class="improvement-positive">📉 Concentration Reduced: -' . round($improvements['domain_concentration_reduction'], 1) . '%</p>';
                                            }
                                            echo '</div>';
                                        }
                                        
                                        // Show rebalancing status
                                        $rebalancing_applied = $data['rebalancing_applied'] ?? false;
                                        $strategy_type = $data['strategy_type'] ?? 'unknown';
                                        echo '<div class="rebalancing-status">';
                                        echo '<p><strong>Rebalancing Applied:</strong> ';
                                        if ($rebalancing_applied) {
                                            echo '<span class="badge badge-success">✅ Yes</span></p>';
                                            echo '<p><strong>Strategy:</strong> ' . ucwords(str_replace('_', ' ', $strategy_type)) . '</p>';
                                        } else {
                                            echo '<span class="badge badge-info">ℹ️ Not Needed</span></p>';
                                            echo '<p><strong>Reason:</strong> Diversity already within acceptable thresholds</p>';
                                        }
                                        echo '</div>';
                                    }
                                } elseif ($artifact->artifacttype === 'rebalanced_inputs') {
                                    echo '<p><strong>Summary:</strong> Inputs after retrieval rebalancing optimization</p>';
                                    $nb_count = isset($data['nb']) ? count($data['nb']) : 0;
                                    echo "<p><strong>NB Results:</strong> {$nb_count} processed</p>";
                                    
                                    // Count total citations after rebalancing
                                    $total_citations = 0;
                                    if (isset($data['nb']) && is_array($data['nb'])) {
                                        foreach ($data['nb'] as $nb) {
                                            if (isset($nb['citations']) && is_array($nb['citations'])) {
                                                $total_citations += count($nb['citations']);
                                            }
                                        }
                                    }
                                    if (isset($data['citations']) && is_array($data['citations'])) {
                                        $total_citations += count($data['citations']);
                                    }
                                    echo "<p><strong>Total Citations:</strong> {$total_citations}</p>";
                                }
                                
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-warning">Unable to decode artifact data</div>';
                            }
                            ?>
                            
                            <div class="artifact-actions">
                                <button class="btn btn-sm btn-outline-secondary" onclick="toggleArtifactData(<?php echo $artifact->id; ?>)">
                                    🔍 View Raw Data
                                </button>
                                <a href="<?php echo new moodle_url('/local/customerintel/download_artifact.php', ['id' => $artifact->id]); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    💾 Download JSON
                                </a>
                            </div>
                            
                            <div id="artifact-data-<?php echo $artifact->id; ?>" class="artifact-raw-data" style="display: none;">
                                <pre><code><?php echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></code></pre>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>

</div>

<script>
function toggleArtifactData(artifactId) {
    const element = document.getElementById('artifact-data-' + artifactId);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}
</script>

<style>
.customerintel-trace-container {
    max-width: 1200px;
    margin: 0 auto;
}

.trace-header {
    text-align: center;
    margin-bottom: 2rem;
}

.trace-subtitle {
    color: #666;
    font-size: 1.1em;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    color: #666;
    margin-top: 0.5rem;
}

.phase-section {
    margin-bottom: 2rem;
}

.phase-description {
    color: #666;
    margin: 0;
    font-style: italic;
}

.artifact-item {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 1rem;
    overflow: hidden;
}

.artifact-header {
    background: #f8f9fa;
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.artifact-header h4 {
    margin: 0;
    font-size: 1.1rem;
    color: #495057;
}

.artifact-meta {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.artifact-content {
    padding: 1rem;
}

.artifact-summary {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.artifact-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.artifact-raw-data {
    margin-top: 1rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    max-height: 400px;
    overflow-y: auto;
}

.artifact-raw-data pre {
    margin: 0;
    padding: 1rem;
}

.trace-run-info {
    margin-bottom: 2rem;
}

/* Diversity Metrics Styling */
.diversity-metrics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin: 1rem 0;
}

.diversity-before,
.diversity-after {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 1rem;
}

.diversity-before {
    border-left: 4px solid #ffc107;
}

.diversity-after {
    border-left: 4px solid #28a745;
}

.diversity-before h5,
.diversity-after h5 {
    margin-top: 0;
    margin-bottom: 0.75rem;
    font-size: 1rem;
}

.improvement-metrics {
    background: #e8f5e8;
    border: 1px solid #c3e6cb;
    border-radius: 6px;
    padding: 1rem;
    margin: 1rem 0;
}

.improvement-metrics h5 {
    margin-top: 0;
    margin-bottom: 0.75rem;
    color: #155724;
}

.improvement-positive {
    color: #155724;
    margin-bottom: 0.5rem;
}

.rebalancing-status {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 1rem;
    margin: 1rem 0;
}

.rebalancing-status h5 {
    margin-top: 0;
    margin-bottom: 0.75rem;
}

@media (max-width: 768px) {
    .diversity-metrics-grid {
        grid-template-columns: 1fr;
    }
}

/* Diversity Summary Section */
.diversity-summary-section {
    margin-bottom: 2rem;
}

.diversity-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.diversity-stat-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.diversity-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.diversity-stat-card .stat-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.diversity-stat-card .stat-content {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.diversity-stat-card .stat-number {
    font-size: 1.8rem;
    font-weight: bold;
    color: #007bff;
    line-height: 1;
}

.diversity-stat-card .stat-label {
    color: #666;
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

.rebalancing-summary {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #dee2e6;
}

.rebalancing-summary h5 {
    margin-bottom: 1rem;
    color: #495057;
}

.rebalancing-summary .alert {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .diversity-summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .diversity-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php

} catch (Exception $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Error: ' . $e->getMessage(), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->footer();
?>