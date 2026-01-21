#!/usr/bin/env php
<?php
/**
 * Evidence Diversity Validation Command
 * 
 * Validates citation diversity metrics against quality thresholds
 * and generates JSON reports for monitoring diversity health across runs.
 *
 * @package    local_customerintel
 * @copyright  2024 Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Bootstrap Moodle
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Load required services
require_once($CFG->dirroot . '/local/customerintel/classes/services/artifact_repository.php');
require_once($CFG->dirroot . '/local/customerintel/classes/services/telemetry_logger.php');

use local_customerintel\services\artifact_repository;
use local_customerintel\services\telemetry_logger;

// CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'runid' => null,
        'latest' => false,
        'trend-analysis' => false,
        'days' => 7,
        'output-json' => true,
        'output-path' => null,
        'verbose' => false,
        'threshold-override' => null
    ],
    [
        'h' => 'help',
        'r' => 'runid', 
        'l' => 'latest',
        't' => 'trend-analysis',
        'd' => 'days',
        'v' => 'verbose',
        'o' => 'output-path'
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Evidence Diversity Validation Command

Validates citation diversity metrics against quality thresholds and generates
monitoring reports for evidence health tracking.

Usage:
  php validate_evidence_diversity.php [OPTIONS]

Options:
  -h, --help                    Show this help message
  -r, --runid=ID               Validate specific run ID
  -l, --latest                 Validate latest completed run
  -t, --trend-analysis         Include trend analysis vs previous runs
  -d, --days=N                 Days for trend analysis (default: 7)
      --output-json            Generate JSON report (default: true)
  -o, --output-path=PATH       Custom output path (default: /data_trace/)
  -v, --verbose                Show detailed output
      --threshold-override=JSON Override default thresholds

Examples:
  # Validate latest run with trend analysis
  php validate_evidence_diversity.php --latest --trend-analysis

  # Validate specific run with custom output path
  php validate_evidence_diversity.php --runid=123 --output-path=/tmp/

  # Quick validation without JSON output
  php validate_evidence_diversity.php --latest --output-json=false

";
    exit(0);
}

/**
 * Evidence Diversity Validator Class
 */
class evidence_diversity_validator {
    
    private $db;
    private $artifact_repo;
    private $telemetry;
    private $thresholds;
    private $verbose;
    
    public function __construct($verbose = false) {
        global $DB;
        $this->db = $DB;
        $this->artifact_repo = new artifact_repository();
        $this->telemetry = new telemetry_logger();
        $this->verbose = $verbose;
        
        // Default thresholds - can be overridden
        $this->thresholds = [
            'diversity_score_min' => get_config('local_customerintel', 'diversity_min_score') ?: 0.75,
            'unique_domains_min' => get_config('local_customerintel', 'diversity_min_domains') ?: 10,
            'max_concentration' => get_config('local_customerintel', 'diversity_max_concentration') ?: 0.25,
            'confidence_ratio_min' => get_config('local_customerintel', 'diversity_confidence_min') ?: 0.60,
            'recent_sources_min' => get_config('local_customerintel', 'diversity_recent_min') ?: 0.20
        ];
    }
    
    /**
     * Validate diversity for a specific run
     */
    public function validate_run($runid, $include_trends = false, $trend_days = 7) {
        $start_time = microtime(true);
        
        if ($this->verbose) {
            cli_writeln("🔍 Starting diversity validation for run {$runid}...");
        }
        
        // Get run information
        $run = $this->db->get_record('local_ci_run', ['id' => $runid]);
        if (!$run) {
            throw new \moodle_exception('Run not found: ' . $runid);
        }
        
        $company = $this->db->get_record('local_ci_company', ['id' => $run->companyid]);
        
        // Collect diversity metrics from all sources
        $metrics = $this->collect_diversity_metrics($runid);
        
        // Perform threshold analysis
        $threshold_results = $this->analyze_thresholds($metrics);
        
        // Calculate overall assessment
        $assessment = $this->calculate_assessment($threshold_results, $metrics);
        
        // Get trend analysis if requested
        $trend_analysis = null;
        if ($include_trends) {
            $trend_analysis = $this->analyze_trends($runid, $trend_days);
        }
        
        // Get rebalancing impact if available
        $rebalancing_impact = $this->analyze_rebalancing_impact($runid);
        
        // Generate quality flags and recommendations
        $quality_flags = $this->generate_quality_flags($metrics, $threshold_results);
        $recommendations = $this->generate_recommendations($metrics, $threshold_results, $rebalancing_impact);
        
        $validation_duration = round((microtime(true) - $start_time) * 1000);
        
        // Build comprehensive report
        $report = [
            'validation_timestamp' => date('c'),
            'run_id' => $runid,
            'company_id' => $run->companyid,
            'company_name' => $company ? $company->name : 'Unknown',
            'assessment' => $assessment,
            'current_metrics' => $metrics,
            'threshold_analysis' => $threshold_results,
            'trend_analysis' => $trend_analysis,
            'rebalancing_impact' => $rebalancing_impact,
            'quality_flags' => $quality_flags,
            'recommendations' => $recommendations,
            'validation_details' => [
                'validator_version' => 'v16_stable',
                'data_sources_checked' => ['local_ci_citation_metrics', 'local_ci_artifact', 'local_ci_telemetry'],
                'validation_duration_ms' => $validation_duration,
                'thresholds_used' => $this->thresholds
            ],
            'metadata' => [
                'schema_version' => 'v16_stable',
                'generated_by' => 'validate_evidence_diversity.php v16_stable',
                'retention_expires' => date('c', strtotime('+90 days'))
            ]
        ];
        
        if ($this->verbose) {
            cli_writeln("✅ Validation completed in {$validation_duration}ms");
        }
        
        return $report;
    }
    
    /**
     * Collect diversity metrics from database and artifacts
     */
    private function collect_diversity_metrics($runid) {
        if ($this->verbose) {
            cli_writeln("📊 Collecting diversity metrics...");
        }
        
        // Primary source: citation metrics table
        $citation_metrics = $this->db->get_record('local_ci_citation_metrics', ['runid' => $runid]);
        
        // Secondary source: artifacts
        $diversity_artifacts = $this->db->get_records('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'retrieval_rebalancing',
            'artifacttype' => 'diversity_metrics'
        ]);
        
        // Tertiary source: telemetry
        $telemetry_metrics = $this->db->get_records_select(
            'local_ci_telemetry',
            'runid = ? AND metrickey LIKE ?',
            [$runid, '%diversity%']
        );
        
        if (!$citation_metrics && empty($diversity_artifacts)) {
            throw new \moodle_exception("No diversity data found for run {$runid}");
        }
        
        // Build unified metrics structure
        $metrics = [];
        
        if ($citation_metrics) {
            $metrics = [
                'domain_diversity_score' => (float)$citation_metrics->diversity_score,
                'unique_domains' => (int)$citation_metrics->unique_domains,
                'total_citations' => (int)$citation_metrics->total_citations,
                'max_domain_concentration' => (float)$citation_metrics->max_domain_concentration,
                'category_distribution' => json_decode($citation_metrics->source_distribution, true) ?: [],
                'confidence_metrics' => [
                    'average' => (float)$citation_metrics->avg_confidence,
                    'high_confidence_count' => (int)$citation_metrics->high_confidence_count,
                    'low_confidence_count' => (int)$citation_metrics->low_confidence_count
                ]
            ];
        }
        
        // Enhance with artifact data if available
        if (!empty($diversity_artifacts)) {
            foreach ($diversity_artifacts as $artifact) {
                $artifact_data = json_decode($artifact->jsondata, true);
                if (isset($artifact_data['after_rebalancing'])) {
                    $metrics = array_merge($metrics, $artifact_data['after_rebalancing']);
                }
            }
        }
        
        // Calculate derived metrics
        if (isset($metrics['confidence_metrics'])) {
            $total_citations = $metrics['total_citations'] ?? 1;
            $metrics['confidence_metrics']['high_confidence_ratio'] = 
                $metrics['confidence_metrics']['high_confidence_count'] / $total_citations;
        }
        
        return $metrics;
    }
    
    /**
     * Analyze metrics against thresholds
     */
    private function analyze_thresholds($metrics) {
        $results = [];
        
        // Diversity score analysis
        $diversity_score = $metrics['domain_diversity_score'] ?? 0;
        $results['diversity_score'] = [
            'value' => $diversity_score,
            'threshold' => $this->thresholds['diversity_score_min'],
            'status' => $diversity_score >= $this->thresholds['diversity_score_min'] ? 'PASS' : 'FAIL',
            'margin' => $diversity_score - $this->thresholds['diversity_score_min'],
            'description' => 'Domain diversity score assessment'
        ];
        
        // Unique domains analysis
        $unique_domains = $metrics['unique_domains'] ?? 0;
        $results['unique_domains'] = [
            'value' => $unique_domains,
            'threshold' => $this->thresholds['unique_domains_min'],
            'status' => $unique_domains >= $this->thresholds['unique_domains_min'] ? 'PASS' : 'FAIL',
            'margin' => $unique_domains - $this->thresholds['unique_domains_min'],
            'description' => 'Source domain variety assessment'
        ];
        
        // Domain concentration analysis
        $max_concentration = $metrics['max_domain_concentration'] ?? 1.0;
        $results['domain_concentration'] = [
            'value' => $max_concentration,
            'threshold' => $this->thresholds['max_concentration'],
            'status' => $max_concentration <= $this->thresholds['max_concentration'] ? 'PASS' : 'FAIL',
            'margin' => $this->thresholds['max_concentration'] - $max_concentration,
            'description' => 'Maximum single domain concentration'
        ];
        
        // Confidence ratio analysis
        $confidence_ratio = $metrics['confidence_metrics']['high_confidence_ratio'] ?? 0;
        $results['confidence_ratio'] = [
            'value' => $confidence_ratio,
            'threshold' => $this->thresholds['confidence_ratio_min'],
            'status' => $confidence_ratio >= $this->thresholds['confidence_ratio_min'] ? 'PASS' : 'FAIL',
            'margin' => $confidence_ratio - $this->thresholds['confidence_ratio_min'],
            'description' => 'High confidence citation ratio'
        ];
        
        return $results;
    }
    
    /**
     * Calculate overall assessment
     */
    private function calculate_assessment($threshold_results, $metrics) {
        $passes = 0;
        $total = count($threshold_results);
        $critical_failures = [];
        
        foreach ($threshold_results as $key => $result) {
            if ($result['status'] === 'PASS') {
                $passes++;
            } else {
                // Check for critical failures
                if ($key === 'diversity_score' && $result['value'] < 0.50) {
                    $critical_failures[] = 'Critical diversity score';
                }
                if ($key === 'unique_domains' && $result['value'] < 6) {
                    $critical_failures[] = 'Critical domain variety';
                }
                if ($key === 'domain_concentration' && $result['value'] > 0.40) {
                    $critical_failures[] = 'Critical domain concentration';
                }
            }
        }
        
        $pass_ratio = $passes / $total;
        $score = round($pass_ratio * 100, 1);
        
        // Determine overall status
        if (!empty($critical_failures)) {
            $status = 'CRITICAL';
            $synthesis_clearance = 'BLOCKED';
        } else if ($pass_ratio >= 0.75) {
            $status = 'PASS';
            $synthesis_clearance = 'APPROVED';
        } else {
            $status = 'NEEDS_REBALANCE';
            $synthesis_clearance = 'CONDITIONAL';
        }
        
        // Calculate grade
        $grade = $score >= 90 ? 'A' : ($score >= 80 ? 'B+' : ($score >= 70 ? 'B' : ($score >= 60 ? 'C' : 'F')));
        
        return [
            'overall_status' => $status,
            'score' => $score,
            'grade' => $grade,
            'pass_ratio' => $pass_ratio,
            'passes' => $passes,
            'total_checks' => $total,
            'critical_failures' => $critical_failures,
            'rebalancing_recommended' => $status !== 'PASS',
            'synthesis_clearance' => $synthesis_clearance
        ];
    }
    
    /**
     * Analyze trends vs previous runs
     */
    private function analyze_trends($runid, $days) {
        // Get previous run for comparison
        $previous_run = $this->db->get_record_sql(
            "SELECT * FROM {local_ci_run} WHERE id < ? AND status = 'completed' ORDER BY id DESC LIMIT 1",
            [$runid]
        );
        
        if (!$previous_run) {
            return ['comparison_available' => false, 'message' => 'No previous run found'];
        }
        
        // Get previous metrics
        $previous_metrics = $this->collect_diversity_metrics($previous_run->id);
        $current_metrics = $this->collect_diversity_metrics($runid);
        
        // Calculate deltas
        $score_delta = ($current_metrics['domain_diversity_score'] ?? 0) - ($previous_metrics['domain_diversity_score'] ?? 0);
        $domains_delta = ($current_metrics['unique_domains'] ?? 0) - ($previous_metrics['unique_domains'] ?? 0);
        $concentration_delta = ($current_metrics['max_domain_concentration'] ?? 0) - ($previous_metrics['max_domain_concentration'] ?? 0);
        
        // Determine trend direction
        $improvements = 0;
        if ($score_delta > 0) $improvements++;
        if ($domains_delta > 0) $improvements++;
        if ($concentration_delta < 0) $improvements++; // Lower concentration is better
        
        $trend_direction = $improvements >= 2 ? 'IMPROVING' : ($improvements === 1 ? 'MIXED' : 'DECLINING');
        
        return [
            'comparison_available' => true,
            'previous_run_id' => $previous_run->id,
            'time_delta_hours' => round((time() - $previous_run->timecompleted) / 3600, 1),
            'diversity_trend' => [
                'score_delta' => round($score_delta, 3),
                'domains_delta' => $domains_delta,
                'concentration_delta' => round($concentration_delta, 3),
                'trend_direction' => $trend_direction
            ]
        ];
    }
    
    /**
     * Analyze rebalancing impact
     */
    private function analyze_rebalancing_impact($runid) {
        // Check for rebalancing artifacts
        $rebalancing_artifacts = $this->db->get_records('local_ci_artifact', [
            'runid' => $runid,
            'phase' => 'retrieval_rebalancing'
        ]);
        
        if (empty($rebalancing_artifacts)) {
            return ['was_applied' => false, 'reason' => 'No rebalancing artifacts found'];
        }
        
        // Extract rebalancing metadata
        foreach ($rebalancing_artifacts as $artifact) {
            if ($artifact->artifacttype === 'diversity_metrics') {
                $data = json_decode($artifact->jsondata, true);
                if (isset($data['rebalancing_metadata'])) {
                    return [
                        'was_applied' => true,
                        'strategy_used' => $data['rebalancing_metadata']['strategy_type'] ?? 'unknown',
                        'citations_modified' => $data['improvement_metrics']['citations_rebalanced'] ?? 0,
                        'improvement_metrics' => $data['improvement_metrics'] ?? [],
                        'pre_rebalancing' => $data['before_rebalancing'] ?? []
                    ];
                }
            }
        }
        
        return ['was_applied' => false, 'reason' => 'Rebalancing metadata not found'];
    }
    
    /**
     * Generate quality flags
     */
    private function generate_quality_flags($metrics, $threshold_results) {
        $flags = [];
        
        // Check diversity score
        $diversity_score = $metrics['domain_diversity_score'] ?? 0;
        if ($diversity_score >= 0.85) {
            $flags[] = ['type' => 'INFO', 'category' => 'DIVERSITY', 'message' => 'Excellent domain diversity achieved'];
        } else if ($diversity_score < 0.60) {
            $flags[] = ['type' => 'WARNING', 'category' => 'DIVERSITY', 'message' => 'Low domain diversity detected'];
        }
        
        // Check concentration
        $max_concentration = $metrics['max_domain_concentration'] ?? 0;
        if ($max_concentration > 0.30) {
            $flags[] = ['type' => 'WARNING', 'category' => 'CONCENTRATION', 'message' => "High domain concentration: " . round($max_concentration * 100, 1) . "%"];
        }
        
        // Check confidence
        $confidence_ratio = $metrics['confidence_metrics']['high_confidence_ratio'] ?? 0;
        if ($confidence_ratio >= 0.70) {
            $flags[] = ['type' => 'INFO', 'category' => 'CONFIDENCE', 'message' => 'High confidence citation ratio supports reliable synthesis'];
        }
        
        return $flags;
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations($metrics, $threshold_results, $rebalancing_impact) {
        $recommendations = [];
        
        // Overall status recommendations
        $diversity_score = $metrics['domain_diversity_score'] ?? 0;
        if ($diversity_score >= 0.75) {
            $recommendations[] = [
                'priority' => 'LOW',
                'action' => 'Continue current diversification strategy',
                'rationale' => 'Diversity metrics meet quality standards'
            ];
        } else {
            $recommendations[] = [
                'priority' => 'HIGH',
                'action' => 'Apply rebalancing before synthesis',
                'rationale' => 'Diversity score below minimum threshold'
            ];
        }
        
        // Concentration-specific recommendations
        $max_concentration = $metrics['max_domain_concentration'] ?? 0;
        if ($max_concentration > 0.20) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'action' => 'Monitor top domain concentration in future runs',
                'rationale' => 'Single domain approaching concentration limit'
            ];
        }
        
        // Rebalancing effectiveness
        if ($rebalancing_impact['was_applied'] ?? false) {
            $recommendations[] = [
                'priority' => 'LOW',
                'action' => 'Evaluate rebalancing strategy effectiveness',
                'rationale' => 'Recent rebalancing applied - monitor impact'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get latest completed run
     */
    public function get_latest_run() {
        return $this->db->get_record_sql(
            "SELECT * FROM {local_ci_run} WHERE status = 'completed' ORDER BY timecompleted DESC LIMIT 1"
        );
    }
    
    /**
     * Save JSON report
     */
    public function save_json_report($report, $output_path = null) {
        $output_path = $output_path ?: get_config('local_customerintel', 'diversity_output_path') ?: '/data_trace/';
        
        // Ensure output directory exists
        $full_path = $CFG->dataroot . $output_path;
        if (!is_dir($full_path)) {
            if (!mkdir($full_path, 0755, true)) {
                throw new \moodle_exception("Cannot create output directory: {$full_path}");
            }
        }
        
        $filename = "diversity_validation_run{$report['run_id']}_" . date('Ymd_His') . ".json";
        $filepath = $full_path . $filename;
        
        if (file_put_contents($filepath, json_encode($report, JSON_PRETTY_PRINT)) === false) {
            throw new \moodle_exception("Cannot write JSON report to: {$filepath}");
        }
        
        // Also save as latest report
        $latest_filepath = $full_path . "diversity_validation.json";
        file_put_contents($latest_filepath, json_encode($report, JSON_PRETTY_PRINT));
        
        return $filepath;
    }
}

// Main execution logic
try {
    $validator = new evidence_diversity_validator($options['verbose']);
    
    // Determine run ID to validate
    $runid = null;
    if ($options['runid']) {
        $runid = (int)$options['runid'];
    } else if ($options['latest']) {
        $latest_run = $validator->get_latest_run();
        if (!$latest_run) {
            cli_error("No completed runs found in database");
        }
        $runid = $latest_run->id;
    } else {
        cli_error("Must specify --runid=N or --latest");
    }
    
    // Run validation
    $report = $validator->validate_run(
        $runid, 
        $options['trend-analysis'], 
        (int)$options['days']
    );
    
    // Output console summary
    cli_writeln("\n=== EVIDENCE DIVERSITY VALIDATION ===");
    cli_writeln("Run ID: {$report['run_id']} | Company: {$report['company_name']}");
    cli_writeln("Timestamp: " . date('Y-m-d H:i:s'));
    cli_writeln("");
    
    $assessment = $report['assessment'];
    $status_icon = $assessment['overall_status'] === 'PASS' ? '✅' : 
                  ($assessment['overall_status'] === 'CRITICAL' ? '❌' : '⚠️');
    
    cli_writeln("ASSESSMENT: {$status_icon} {$assessment['overall_status']} (Score: {$assessment['score']}/100, Grade: {$assessment['grade']})");
    cli_writeln("");
    
    // Show threshold results
    foreach ($report['threshold_analysis'] as $key => $result) {
        $icon = $result['status'] === 'PASS' ? '✅' : '❌';
        $name = ucwords(str_replace('_', ' ', $key));
        cli_writeln("{$icon} {$name}: {$result['value']} (Target: {$result['threshold']})");
    }
    
    // Show trends if available
    if (!empty($report['trend_analysis']['comparison_available'])) {
        cli_writeln("\nTREND ANALYSIS:");
        $trend = $report['trend_analysis']['diversity_trend'];
        $direction_icon = $trend['trend_direction'] === 'IMPROVING' ? '📈' : 
                         ($trend['trend_direction'] === 'DECLINING' ? '📉' : '➡️');
        cli_writeln("{$direction_icon} Overall Trend: {$trend['trend_direction']}");
        cli_writeln("   Diversity Score: {$trend['score_delta']:+.3f}");
        cli_writeln("   Unique Domains: {$trend['domains_delta']:+d}");
        cli_writeln("   Max Concentration: {$trend['concentration_delta']:+.3f}");
    }
    
    // Show rebalancing impact
    if (!empty($report['rebalancing_impact']['was_applied'])) {
        cli_writeln("\nREBALANCING IMPACT:");
        $rebalancing = $report['rebalancing_impact'];
        cli_writeln("🔄 Applied: {$rebalancing['strategy_used']} strategy");
        cli_writeln("📊 Citations Modified: {$rebalancing['citations_modified']}");
        if (isset($rebalancing['improvement_metrics']['score_improvement'])) {
            cli_writeln("📈 Score Improvement: +{$rebalancing['improvement_metrics']['score_improvement']:.3f}");
        }
    }
    
    // Show recommendations
    if (!empty($report['recommendations'])) {
        cli_writeln("\nRECOMMENDATIONS:");
        foreach ($report['recommendations'] as $rec) {
            $priority_icon = $rec['priority'] === 'HIGH' ? '🔴' : 
                           ($rec['priority'] === 'MEDIUM' ? '🟡' : '🟢');
            cli_writeln("• {$priority_icon} {$rec['action']}");
        }
    }
    
    // Save JSON report if requested
    if ($options['output-json']) {
        $filepath = $validator->save_json_report($report, $options['output-path']);
        cli_writeln("\n📄 JSON report saved: {$filepath}");
    }
    
    cli_writeln("\nVALIDATION COMPLETE " . ($assessment['overall_status'] === 'PASS' ? '✅' : '⚠️'));
    
    // Exit with appropriate code
    exit($assessment['overall_status'] === 'PASS' ? 0 : 1);
    
} catch (Exception $e) {
    cli_error("Validation failed: " . $e->getMessage());
}