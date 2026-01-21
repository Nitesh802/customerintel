# Synthesis Blueprint v16: Enhanced with Evidence Diversity Context

## Overview

This blueprint defines the enhanced synthesis process that integrates diversity metrics from the retrieval rebalancing phase to provide transparent evidence quality context for synthesis prompt generation.

## Evidence Diversity Context Section

### Purpose
The Evidence Diversity Context provides the synthesis LLM with critical metadata about the source quality and balance of citations being used, enabling informed synthesis decisions and quality-aware content generation.

### Integration Point
The diversity context is injected into each section synthesis prompt after the NB data preparation and before content generation begins.

### Context Template

```
## Evidence Diversity Context

**Domain Diversity Score**: {diversity_score}/100
- Measurement of citation source variety across domains
- Optimal range: 70-100 (diverse sources)
- Current assessment: {diversity_assessment}

**Unique Domain Count**: {unique_domains}
- Number of distinct source domains represented
- Minimum threshold: 10 domains for balanced analysis
- Domain concentration analysis: {concentration_analysis}

**Top Source Domains**:
{top_domains_list}

**Source Category Distribution**:
- Financial/Regulatory: {financial_percentage}%
- News/Media: {news_percentage}%
- Industry Analysis: {analyst_percentage}%
- Company Publications: {company_percentage}%

**Rebalancing Status**: {rebalancing_applied}
{rebalancing_details}

**Evidence Quality Indicators**:
- High Confidence Citations: {high_confidence_count}
- Average Confidence Score: {avg_confidence_score}
- Recent Sources (≤30 days): {recent_sources_count}
- Regulatory/Official Sources: {regulatory_sources_count}

---
```

## Synthesis Prompt Enhancement

### Section Generation Template

Each section synthesis now includes the Evidence Diversity Context:

```
You are generating the {section_name} section for an intelligence report.

{evidence_diversity_context}

**Company Context**:
- Source Company: {source_company_name}
- Target Company: {target_company_name} (if applicable)
- Industry Sector: {industry_sector}

**NB Analysis Data**:
{nb_processed_data}

**Section Requirements**:
{section_specific_requirements}

**Synthesis Instructions**:
1. Consider the evidence diversity metrics when making claims
2. Prioritize insights from high-confidence, diverse sources
3. Note any limitations from domain concentration or source gaps
4. Use the rebalancing context to understand evidence completeness
5. Reference citation markers using section-specific prefixes

Generate a comprehensive {section_name} section that leverages the diverse evidence base while acknowledging any source limitations.
```

## Implementation Details

### Diversity Metrics Extraction

The synthesis engine extracts diversity metrics from these sources:

1. **Citation Metrics Table**: `local_ci_citation_metrics.runid`
2. **Artifact Repository**: `diversity_metrics` artifacts from `retrieval_rebalancing` phase
3. **Telemetry Logs**: Real-time diversity measurements
4. **Enhanced Citation Manager**: In-memory diversity calculations

### Data Flow

```
NB Orchestration → Retrieval Rebalancing → Diversity Metrics Calculation
                                        ↓
                                   Artifact Storage
                                        ↓
                              Synthesis Engine Retrieval
                                        ↓
                               Evidence Context Injection
                                        ↓
                              Section Prompt Generation
```

### Metric Calculation Code Points

Key integration points in `synthesis_engine.php`:

1. **Line 340-342**: Diversity metrics calculation trigger
2. **Line 1350-1357**: Telemetry diversity logging
3. **Line 1589**: Artifact storage for diversity metadata
4. **Line 2058-2059**: Citation population from NB data
5. **Line 2186-2202**: Enhanced metrics extraction

## Section-Specific Adaptations

### Executive Insight
- Emphasizes source authority and recency
- Highlights regulatory vs. market sentiment balance
- Notes confidence levels for strategic claims

### Financial Trajectory  
- Prioritizes financial source dominance assessment
- Compares analyst vs. official reporting balance
- Indicates data completeness for financial metrics

### Strategic Priorities
- Evaluates management communication vs. external analysis ratio
- Assesses industry vs. company-specific source mix
- Notes strategic claim confidence levels

### Growth Levers
- Balances internal announcements with market analysis
- Evaluates forward-looking vs. historical source mix
- Indicates projection confidence based on source diversity

### Risk Signals
- Prioritizes external analysis and regulatory sources
- Evaluates news vs. analytical source balance
- Highlights early warning indicator source quality

## Prompt Variables

### Required Variables

- `{diversity_score}`: Numeric 0-100 diversity score
- `{unique_domains}`: Count of unique source domains
- `{diversity_assessment}`: Qualitative assessment (Excellent/Good/Moderate/Poor)
- `{concentration_analysis}`: Analysis of domain concentration issues
- `{top_domains_list}`: Formatted list of top 5 domains with percentages
- `{financial_percentage}`: Percentage of financial/regulatory sources
- `{news_percentage}`: Percentage of news/media sources
- `{analyst_percentage}`: Percentage of industry analysis sources
- `{company_percentage}`: Percentage of company publication sources
- `{rebalancing_applied}`: Boolean indicating if rebalancing was applied
- `{rebalancing_details}`: Details about rebalancing strategy and impact
- `{high_confidence_count}`: Count of high-confidence citations
- `{avg_confidence_score}`: Average confidence score across citations
- `{recent_sources_count}`: Count of sources ≤30 days old
- `{regulatory_sources_count}`: Count of official/regulatory sources

### Optional Enhancement Variables

- `{domain_entropy}`: Shannon entropy of domain distribution
- `{temporal_spread}`: Range of publication dates
- `{source_authority_score}`: Weighted authority assessment
- `{citation_completeness}`: Percentage of sections with adequate citations

## Quality Thresholds

### Diversity Score Ranges
- **90-100**: Excellent diversity, minimal concentration risk
- **70-89**: Good diversity, acceptable for synthesis
- **50-69**: Moderate diversity, note limitations in output
- **<50**: Poor diversity, require rebalancing or flag limitations

### Domain Concentration Limits
- **<15%**: Optimal concentration from any single domain
- **15-25%**: Acceptable concentration with monitoring
- **25-40%**: High concentration, rebalancing recommended
- **>40%**: Excessive concentration, synthesis quality at risk

### Minimum Thresholds
- **Unique Domains**: Minimum 8 for acceptable analysis
- **High Confidence Citations**: Minimum 60% above 0.6 confidence
- **Recent Sources**: Minimum 20% within 90 days
- **Regulatory Sources**: Minimum 10% for financial claims

## Error Handling

### Missing Diversity Data
When diversity metrics are unavailable:

1. Log warning: "Diversity metrics unavailable for run {runid}"
2. Proceed with synthesis using basic citation counting
3. Include disclaimer about evidence assessment limitations
4. Flag for manual review in QA process

### Incomplete Rebalancing
When rebalancing fails or is incomplete:

1. Use pre-rebalancing diversity metrics
2. Include warning about potential source concentration
3. Note rebalancing failure in evidence context
4. Proceed with enhanced caution flags

## Testing Integration

### Validation Points

1. **Diversity Context Injection**: Verify context appears in all section prompts
2. **Metric Accuracy**: Validate calculated metrics match artifact data
3. **Threshold Enforcement**: Confirm quality warnings trigger appropriately
4. **Error Graceful Handling**: Test synthesis continues with missing data

### Test Scenarios

1. **High Diversity Run**: Score >80, balanced domains, verify positive context
2. **Low Diversity Run**: Score <50, concentrated domains, verify warnings
3. **Missing Metrics**: No diversity data, verify fallback behavior
4. **Rebalancing Applied**: Before/after comparison, verify improvement noted

## Performance Considerations

### Caching Strategy
- Cache diversity metrics per runid for session duration
- Avoid recalculating during section generation loop
- Pre-load context template for all sections

### Memory Management
- Limit artifact payload size for diversity metadata
- Stream large domain distribution data
- Clean up temporary diversity calculation objects

## Monitoring and Alerting

### Key Metrics to Track
- Average diversity scores across reports
- Rebalancing application frequency
- Context injection success rate
- Synthesis quality correlation with diversity scores

### Alert Conditions
- Diversity score <50 for multiple consecutive runs
- Rebalancing failure rate >10%
- Missing diversity context in >5% of sections
- Synthesis time increase >50% with diversity integration

## Version History

- **v16.0**: Initial release with Evidence Diversity Context integration
- **v15.x**: Baseline synthesis without diversity context
- **Future**: Planned adaptive thresholds based on industry/company type

## Configuration

### Feature Flags
- `enable_diversity_context`: Master switch for diversity integration
- `require_rebalancing_artifacts`: Fail synthesis without diversity data
- `diversity_threshold_warnings`: Show warnings for poor diversity scores
- `enhanced_citation_metrics`: Enable full confidence + diversity scoring

### Settings
- `diversity_score_minimum`: Default 50 (configurable per deployment)
- `domain_concentration_max`: Default 25% (configurable per industry)
- `unique_domains_minimum`: Default 8 (configurable based on query scope)