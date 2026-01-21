# Evidence Diversity Overview

## Pipeline Flow Summary

The evidence diversity system ensures balanced, high-quality citations flow through the intelligence synthesis pipeline by measuring, rebalancing, and validating source distribution at each critical stage.

```
[NB Orchestration] → [Retrieval Rebalancing] → [Diversity Validation] → [Synthesis v16]
       ↓                      ↓                       ↓                    ↓
  Raw Citations        Balanced Citations        Quality Gates      Enhanced Context
```

---

## Stage 1: NB Orchestration (Raw Citation Collection)

### **Data Source**: `nb_orchestrator.php`
- Executes NB1-NB15 protocol
- Collects citations from LLM responses
- Stores raw citation data in `local_ci_nb_result`

### **Output Metrics**:
- Total citations collected
- Initial domain distribution
- Basic source categorization

---

## Stage 2: Retrieval Rebalancing (Source Optimization)

### **Trigger**: Automatic post-NB orchestration
**Component**: `synthesis_engine.php` lines 1559-1620

### **Key Formulas**:

#### **Diversity Score Calculation**
```
diversity_score = 1 - Σ(domain_percentage²)
```
- Range: 0.0 (single domain) to 1.0 (perfectly distributed)
- Higher scores indicate better diversity

#### **Domain Concentration**
```
max_concentration = max(citations_per_domain / total_citations)
```
- Range: 0.0 to 1.0
- Lower values indicate better distribution

#### **Rebalancing Decision Matrix**
```
IF max_concentration > 0.25 OR unique_domains < 10:
    apply_rebalancing = TRUE
    strategy = "domain_diversification"
ELSE:
    apply_rebalancing = FALSE
```

### **Rebalancing Strategies**:
1. **Domain Diversification**: Reduce over-concentrated domains
2. **Source Type Balancing**: Ensure Financial/News/Analyst/Company mix
3. **Recency Optimization**: Include recent sources for current relevance

### **Output Storage**:
- **Artifact Repository**: `diversity_metrics` artifacts in `retrieval_rebalancing` phase
- **Citation Metrics Table**: `local_ci_citation_metrics.runid`
- **Telemetry Logs**: Real-time diversity measurements

---

## Stage 3: Diversity Validation (Quality Gates)

### **Trigger**: Post-rebalancing, pre-synthesis
**Component**: `validate_evidence_diversity.php`

### **Quality Thresholds**:

| Metric | Minimum | Warning | Critical |
|--------|---------|---------|----------|
| Diversity Score | ≥ 0.75 | ≥ 0.65 | < 0.50 |
| Unique Domains | ≥ 10 | ≥ 8 | < 6 |
| Max Concentration | ≤ 25% | ≤ 35% | > 40% |
| High Confidence | ≥ 60% | ≥ 50% | < 40% |
| Recent Sources | ≥ 20% | ≥ 15% | < 10% |

### **Assessment Logic**:
```
pass_ratio = passed_thresholds / total_thresholds

IF critical_failures > 0:
    status = "CRITICAL" (Block synthesis)
ELSE IF pass_ratio ≥ 0.75:
    status = "PASS" (Approve synthesis)
ELSE:
    status = "NEEDS_REBALANCE" (Conditional approval)
```

### **Output**: JSON report to `/data_trace/diversity_validation.json`

---

## Stage 4: Synthesis Blueprint v16 (Context Integration)

### **Enhancement**: Evidence Diversity Context injection
**Component**: Enhanced section prompts in `synthesis_engine.php`

### **Context Template Variables**:
```
{diversity_score}           // 0-100 scale score
{unique_domains}           // Count of distinct domains  
{concentration_analysis}   // Domain concentration assessment
{top_domains_list}        // Top 5 domains with percentages
{rebalancing_applied}     // Boolean rebalancing status
{rebalancing_details}     // Strategy and impact summary
```

### **Section-Specific Adaptations**:

#### **Executive Insight** (`draft_executive_insight`)
- Emphasizes source authority and confidence levels
- Notes regulatory vs. market sentiment balance

#### **Financial Trajectory** (`draft_financial_trajectory`) 
- Prioritizes financial source concentration assessment
- Validates claim confidence based on official source percentage

#### **Strategic Priorities** (`draft_strategic_priorities`)
- Evaluates management vs. external analysis ratio
- Assesses industry vs. company-specific source mix

---

## Key Integration Points

### **Database Tables**:
1. **`local_ci_citation_metrics`**: Persistent diversity scores per run
2. **`local_ci_artifact`**: Detailed rebalancing metadata and before/after metrics
3. **`local_ci_telemetry`**: Real-time diversity tracking for monitoring

### **Code Integration Points**:
```php
// 1. Diversity calculation trigger
synthesis_engine.php:340-342

// 2. Rebalancing execution
synthesis_engine.php:1559-1620

// 3. Artifact storage
synthesis_engine.php:1589

// 4. Validation hook
nb_orchestrator.php:post_execution

// 5. Context injection
synthesis_engine.php:2058-2202
```

---

## Verification Methods

### **1. Manual Verification**
```bash
# Run diversity validation on specific run
php validate_evidence_diversity.php --runid=123 --verbose

# Check latest run with trend analysis
php validate_evidence_diversity.php --latest --trend-analysis
```

### **2. Database Verification**
```sql
-- Check diversity metrics for recent runs
SELECT runid, diversity_score, unique_domains, max_domain_concentration 
FROM local_ci_citation_metrics 
ORDER BY timecreated DESC LIMIT 10;

-- Verify rebalancing artifacts exist
SELECT runid, phase, artifacttype, LENGTH(jsondata) as size
FROM local_ci_artifact 
WHERE phase = 'retrieval_rebalancing' 
ORDER BY timecreated DESC LIMIT 5;
```

### **3. Artifact Inspection**
```bash
# View rebalancing artifacts for run
php view_trace.php --runid=123

# Extract diversity metrics from artifacts  
php extract_artifact_citations.php --runid=123 --phase=retrieval_rebalancing
```

### **4. Synthesis Output Verification**
- Check Evidence Diversity Context appears in section prompts
- Verify diversity scores influence synthesis confidence language
- Confirm rebalancing impact noted in generated content

---

## Monitoring Dashboard Metrics

### **Real-Time Indicators**:
- Average diversity score across last 7 runs
- Rebalancing application frequency
- Synthesis quality correlation with diversity scores
- Critical diversity failures count

### **Trend Analysis**:
- Diversity score improvement after rebalancing
- Domain concentration reduction effectiveness
- Unique domain count stability over time

### **Alert Conditions**:
- **Critical**: Diversity score < 50 for 2+ consecutive runs
- **Warning**: Rebalancing failure rate > 10%
- **Info**: Diversity improvement trend positive over 7 days

---

## Configuration Settings

### **Thresholds** (configurable per deployment):
```php
set_config('diversity_min_score', 0.75, 'local_customerintel');
set_config('diversity_min_domains', 10, 'local_customerintel');
set_config('diversity_max_concentration', 0.25, 'local_customerintel');
set_config('diversity_confidence_min', 0.60, 'local_customerintel');
```

### **Feature Flags**:
```php
set_config('enable_diversity_context', true, 'local_customerintel');
set_config('require_rebalancing_artifacts', false, 'local_customerintel');
set_config('diversity_threshold_warnings', true, 'local_customerintel');
```

---

## Quality Impact

### **Before Diversity Integration**:
- Citation clustering from single domains (>40% concentration)
- Limited source variety impacting analysis reliability
- No visibility into evidence quality for synthesis decisions

### **After Diversity Integration**:
- Balanced source distribution (<25% single domain concentration)
- Transparent evidence quality context in synthesis prompts
- Automatic rebalancing ensures consistent diversity standards
- Quality gates prevent poor-diversity synthesis execution

---

## Troubleshooting

### **Common Issues**:

1. **Missing Diversity Data**
   - **Cause**: Rebalancing phase skipped or failed
   - **Solution**: Check `nb_orchestrator.php` post-execution hooks

2. **Low Diversity Scores**
   - **Cause**: Limited source variety in NB collection
   - **Solution**: Review NB query strategies and source selection

3. **Rebalancing Not Applied**
   - **Cause**: Thresholds not met or rebalancing logic disabled
   - **Solution**: Verify threshold configuration and feature flags

4. **Synthesis Context Missing**
   - **Cause**: Blueprint v16 not properly integrated
   - **Solution**: Check `synthesis_engine.php` context injection points

### **Debug Commands**:
```bash
# Test full NB orchestration with diversity tracking
php test_orchestration.php --mock --full --verbose

# Test rebalancing integration
php test_retrieval_rebalancing.php

# Validate evidence diversity for specific run
php validate_evidence_diversity.php --runid=123 --verbose
```

This evidence diversity system ensures high-quality, balanced citations flow through the entire intelligence synthesis pipeline, providing transparency and automatic optimization for reliable analysis output.