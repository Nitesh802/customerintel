# Evidence Diversity Validation Command

## Overview

The Evidence Diversity Validation Command automatically monitors citation diversity health after each NB orchestration cycle, providing real-time assessment of evidence quality and alerting when rebalancing is needed.

## Purpose

- **Automated Quality Assurance**: Validates evidence diversity meets minimum thresholds
- **Trend Monitoring**: Tracks diversity metrics over time to identify degradation patterns  
- **Rebalancing Triggers**: Automatically flags runs requiring diversity intervention
- **JSON Reporting**: Outputs structured data for monitoring dashboards and alerts

## Execution

### Automatic Trigger
- Runs after each successful NB orchestration completion
- Triggered by `nb_orchestrator.php` post-execution hook
- Executes before synthesis phase begins

### Manual Execution
```bash
php /local_customerintel/cli/validate_evidence_diversity.php --runid=123
php /local_customerintel/cli/validate_evidence_diversity.php --latest
php /local_customerintel/cli/validate_evidence_diversity.php --trend-analysis --days=30
```

## Data Sources

### Primary Sources
1. **Citation Metrics Table**: `local_ci_citation_metrics.runid`
2. **Artifact Repository**: `diversity_metrics` artifacts from `retrieval_rebalancing` phase
3. **Telemetry Logs**: Real-time diversity measurements
4. **NB Results**: Current run artifact metadata

### Artifact Meta Values
- `domain_diversity_score`: Numeric 0.0-1.0 diversity measurement
- `unique_domains`: Count of distinct source domains
- `category_mix`: Distribution across Financial/News/Analyst/Company sources
- `max_domain_concentration`: Highest single domain percentage
- `rebalancing_applied`: Boolean indicating if rebalancing was executed
- `confidence_metrics`: Citation confidence scoring data

## Validation Logic

### Quality Thresholds

#### Minimum Standards
```
domain_diversity_score >= 0.75   (75/100)
unique_domains >= 10
max_domain_concentration <= 0.25  (25%)
high_confidence_citations >= 0.60  (60%)
recent_sources_ratio >= 0.20  (20%)
```

#### Warning Levels
```
domain_diversity_score >= 0.65   (Warning: Moderate diversity)
unique_domains >= 8              (Warning: Limited diversity)
max_domain_concentration <= 0.35  (Warning: High concentration)
```

#### Critical Levels
```
domain_diversity_score < 0.50    (Critical: Poor diversity)
unique_domains < 6               (Critical: Very limited sources)
max_domain_concentration > 0.40   (Critical: Excessive concentration)
```

### Assessment Categories

#### PASS
- All minimum thresholds met
- No critical issues detected
- Synthesis can proceed with confidence

#### NEEDS_REBALANCE  
- One or more thresholds below minimum
- Rebalancing recommended before synthesis
- Evidence quality may impact report reliability

#### CRITICAL
- Multiple thresholds in critical range
- Synthesis should be halted
- Manual intervention required

## Output Structure

### JSON Report: `/data_trace/diversity_validation.json`

```json
{
  "validation_timestamp": "2024-10-22T14:30:00Z",
  "run_id": 789,
  "company_id": 42,
  "assessment": {
    "overall_status": "PASS|NEEDS_REBALANCE|CRITICAL",
    "score": 78.5,
    "grade": "B+",
    "rebalancing_recommended": false
  },
  "current_metrics": {
    "domain_diversity_score": 0.785,
    "unique_domains": 12,
    "max_domain_concentration": 0.184,
    "total_citations": 47,
    "category_distribution": {
      "financial": 0.38,
      "news": 0.32,
      "analyst": 0.18,
      "company": 0.12
    },
    "confidence_metrics": {
      "average": 0.74,
      "high_confidence_count": 32,
      "low_confidence_count": 4
    },
    "recency_metrics": {
      "recent_sources_count": 14,
      "recent_sources_ratio": 0.30,
      "oldest_source_days": 365,
      "average_age_days": 67
    }
  },
  "threshold_analysis": {
    "diversity_score": {
      "value": 0.785,
      "threshold": 0.75,
      "status": "PASS",
      "margin": 0.035
    },
    "unique_domains": {
      "value": 12,
      "threshold": 10,
      "status": "PASS",
      "margin": 2
    },
    "domain_concentration": {
      "value": 0.184,
      "threshold": 0.25,
      "status": "PASS",
      "margin": 0.066
    },
    "confidence_ratio": {
      "value": 0.68,
      "threshold": 0.60,
      "status": "PASS",
      "margin": 0.08
    }
  },
  "trend_analysis": {
    "previous_run_id": 756,
    "time_delta_hours": 72,
    "diversity_trend": {
      "score_delta": 0.116,
      "domains_delta": 4,
      "concentration_delta": -0.128,
      "trend_direction": "IMPROVING"
    },
    "moving_averages": {
      "diversity_score_7day": 0.763,
      "unique_domains_7day": 10.8,
      "rebalancing_frequency_7day": 0.43
    }
  },
  "rebalancing_impact": {
    "was_applied": true,
    "strategy_used": "domain_diversification",
    "citations_modified": 12,
    "improvement_metrics": {
      "score_improvement": 0.116,
      "concentration_reduction": 0.128,
      "domains_added": 4
    }
  },
  "quality_flags": [
    {
      "type": "INFO",
      "message": "Domain diversity meets all thresholds"
    },
    {
      "type": "WARNING", 
      "message": "Financial sources represent 38% of citations - monitor for over-concentration"
    }
  ],
  "recommendations": [
    "Continue current diversification strategy",
    "Monitor bloomberg.com concentration in future runs",
    "Consider expanding analyst report inclusion"
  ],
  "validation_details": {
    "data_sources_checked": [
      "local_ci_citation_metrics",
      "local_ci_artifact.diversity_metrics", 
      "local_ci_telemetry"
    ],
    "artifacts_found": 3,
    "telemetry_records": 15,
    "validation_duration_ms": 247
  }
}
```

### Console Output

```
=== EVIDENCE DIVERSITY VALIDATION ===
Run ID: 789 | Company: Test Company ABC123
Timestamp: 2024-10-22 14:30:00

ASSESSMENT: PASS (Score: 78.5/100, Grade: B+)

✅ Domain Diversity Score: 78.5/100 (Target: ≥75)
✅ Unique Domains: 12 (Target: ≥10) 
✅ Max Concentration: 18.4% (Target: ≤25%)
✅ High Confidence: 68% (Target: ≥60%)

TREND ANALYSIS (vs Run 756, 72h ago):
📈 Diversity Score: +11.6 points (67.3 → 78.9) IMPROVING
📈 Unique Domains: +4 domains (8 → 12) IMPROVING  
📉 Max Concentration: -12.8% (31.2% → 18.4%) IMPROVING

REBALANCING IMPACT:
🔄 Applied: domain_diversification strategy
📊 Citations Modified: 12/47 (25.5%)
📈 Score Improvement: +11.6 points

RECOMMENDATIONS:
• Continue current diversification strategy
• Monitor bloomberg.com concentration (18.4%)
• Consider expanding analyst report inclusion

VALIDATION COMPLETE ✅
```

## Implementation

### Core Script: `validate_evidence_diversity.php`

```php
#!/usr/bin/env php
<?php
/**
 * Evidence Diversity Validation Command
 * 
 * Validates citation diversity metrics against quality thresholds
 * and generates JSON reports for monitoring.
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// CLI options
list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'runid' => null,
    'latest' => false,
    'trend-analysis' => false,
    'days' => 7,
    'output-json' => true,
    'verbose' => false
], ['h' => 'help', 'r' => 'runid', 'l' => 'latest', 't' => 'trend-analysis', 'v' => 'verbose']);

// Validation logic implementation
// [Full PHP implementation would go here]
```

### Integration Points

#### 1. NB Orchestrator Hook
```php
// In nb_orchestrator.php after successful completion
if ($nb_execution_success) {
    // Trigger diversity validation
    $validation_cmd = $CFG->dirroot . '/local/customerintel/cli/validate_evidence_diversity.php';
    exec("php {$validation_cmd} --runid={$runid} --output-json", $output, $return_code);
    
    if ($return_code !== 0) {
        debugging('Evidence diversity validation failed', DEBUG_DEVELOPER);
    }
}
```

#### 2. Synthesis Engine Pre-Check
```php
// In synthesis_engine.php before build_report()
$diversity_report = $this->check_diversity_validation($runid);
if ($diversity_report['assessment']['overall_status'] === 'CRITICAL') {
    throw new \moodle_exception('synthesis_halted_poor_diversity', 'local_customerintel');
}
```

## Monitoring Integration

### Dashboard Metrics
- Real-time diversity health status
- Trend charts for diversity scores over time
- Rebalancing frequency and effectiveness
- Alert notifications for critical diversity issues

### Alerting Rules
```
Critical Alert: diversity_score < 0.50 for 2+ consecutive runs
Warning Alert: diversity_score < 0.65 for 3+ consecutive runs  
Info Alert: rebalancing_frequency > 0.60 over 7 days
```

### Report Retention
- Keep validation reports for 90 days
- Archive trend data for 1 year
- Export monthly diversity health summaries

## Configuration

### Environment Variables
```
DIVERSITY_VALIDATION_ENABLED=true
DIVERSITY_MIN_SCORE=0.75
DIVERSITY_MIN_DOMAINS=10
DIVERSITY_MAX_CONCENTRATION=0.25
DIVERSITY_OUTPUT_PATH=/data_trace/
```

### Settings Integration
```php
// In local_customerintel settings
set_config('diversity_validation_enabled', true, 'local_customerintel');
set_config('diversity_min_score', 0.75, 'local_customerintel');
set_config('diversity_min_domains', 10, 'local_customerintel');
set_config('diversity_output_path', '/data_trace/', 'local_customerintel');
```

## Testing

### Unit Tests
- Threshold validation logic
- Trend calculation accuracy
- JSON output format validation
- Error handling for missing data

### Integration Tests  
- End-to-end validation after NB orchestration
- Synthesis engine integration with diversity checks
- Dashboard data consumption from JSON reports

### Performance Tests
- Validation execution time under 500ms
- Memory usage within 64MB limit
- Concurrent validation handling

## Maintenance

### Regular Tasks
- Review and adjust thresholds based on data patterns
- Update trend analysis algorithms
- Monitor validation performance and accuracy
- Archive old validation reports

### Troubleshooting
- Check data_trace directory permissions
- Verify database connectivity for metrics retrieval
- Validate artifact repository access
- Review telemetry logging configuration