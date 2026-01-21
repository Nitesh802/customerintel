# Retrieval Scope Rebalancing System

## Overview

The Retrieval Scope Rebalancing System analyzes NB orchestration citation patterns to identify domain clustering and redundancy, then generates enhanced retrieval strategies for better source diversification.

## Components

### 1. Citation Analysis Scripts

#### `analyze_citations.php`
- Extracts citations from NB orchestration data
- Analyzes domain frequency and concentration
- Categorizes sources by type (financial news, industry reports, etc.)
- Identifies problematic domains (>25% representation)

#### `extract_artifact_citations.php`
- Extracts citations from the new artifact repository
- Works with transparent pipeline view data
- Provides more recent and structured citation analysis

### 2. Rebalancing Analysis

#### `rebalance_retrieval_scope.php`
- Main analysis engine
- Generates comprehensive domain concentration report
- Proposes diversification strategies
- Creates enhanced query templates
- Outputs JSON patch file with recommendations

### 3. Implementation Tools

#### `implement_retrieval_rebalancing.php`
- Applies rebalancing recommendations
- Generates configuration files
- Creates implementation code snippets
- Sets up monitoring framework

## Key Features

### Domain Concentration Analysis
- **Critical Threshold**: Flags domains representing >25% of total sources
- **High Concentration**: Identifies domains with 15-25% representation
- **Category Analysis**: Groups domains by type for balanced coverage

### Diversification Strategy
Target domain distributions:
- **Financial News**: 20% (Bloomberg, Reuters, WSJ, FT)
- **Industry Reports**: 25% (Gartner, Forrester, IDC, McKinsey)
- **Government/Regulatory**: 15% (SEC, FDA, FTC, Federal Reserve)
- **Academic/Research**: 10% (Universities, SSRN, research institutions)
- **Business Media**: 15% (Forbes, Fortune, Business Insider)
- **Other Sources**: 15% (Trade publications, international sources)

### Enhanced Query Templates

#### 1. Industry Analysis
```json
{
  "base_query": "{company_name} market analysis OR industry trends OR competitive landscape",
  "domain_weights": {
    "gartner.com": 3,
    "forrester.com": 3,
    "idc.com": 3,
    "mckinsey.com": 2
  },
  "additional_terms": ["market share", "industry report", "competitive analysis"]
}
```

#### 2. Financial Performance
```json
{
  "base_query": "{company_name} earnings OR financial results OR revenue",
  "domain_weights": {
    "sec.gov": 4,
    "bloomberg.com": 2,
    "reuters.com": 2,
    "wsj.com": 2
  },
  "additional_terms": ["quarterly earnings", "annual report", "10-K", "10-Q"]
}
```

#### 3. Strategic Initiatives
```json
{
  "base_query": "{company_name} strategy OR partnerships OR acquisitions",
  "domain_weights": {
    "businesswire.com": 2,
    "prnewswire.com": 2,
    "crunchbase.com": 2
  },
  "additional_terms": ["strategic partnership", "merger", "acquisition"]
}
```

#### 4. Regulatory Compliance
```json
{
  "base_query": "{company_name} regulatory OR compliance OR investigation",
  "domain_weights": {
    "sec.gov": 4,
    "fda.gov": 3,
    "ftc.gov": 3
  },
  "additional_terms": ["regulatory filing", "compliance violation"]
}
```

#### 5. Innovation & Technology
```json
{
  "base_query": "{company_name} innovation OR technology OR R&D OR patents",
  "domain_weights": {
    "techcrunch.com": 2,
    "mit.edu": 3,
    "stanford.edu": 3
  },
  "additional_terms": ["patent filing", "research and development"]
}
```

## Recommended New Domains

### Industry Analysts (8 domains)
- `idc.com`, `gartner.com`, `forrester.com`, `frost.com`
- `grandviewresearch.com`, `mordorintelligence.com`, `technavio.com`, `researchandmarkets.com`

### Competitor Intelligence (9 domains)
- `owler.com`, `similarweb.com`, `crunchbase.com`, `pitchbook.com`
- `cbinsights.com`, `tracxn.com`, `dealroom.co`, `venturebeat.com`

### Customer Partnerships (7 domains)
- `businesswire.com`, `prnewswire.com`, `globenewswire.com`
- `marketscreener.com`, `yahoo.com/finance`, `investing.com`, `seekingalpha.com`

### Investor Relations (8 domains)
- `sec.gov`, `investor.*.com`, `ir.*.com`, `investors.*.com`
- `earnings.com`, `zacks.com`, `morningstar.com`, `fool.com`

### Government & Academic (11 domains)
- `nist.gov`, `census.gov`, `bls.gov`, `federalreserve.gov`, `treasury.gov`
- `mit.edu`, `stanford.edu`, `harvard.edu`, `berkeley.edu`, `ssrn.com`

### Trade Publications (6 domains)
- `industryweek.com`, `supplychainmanagement.com`, `logisticsmgmt.com`
- `manufacturingnews.com`, `plantservices.com`, `automationworld.com`

### International Sources (8 domains)
- `economist.com`, `stratfor.com`, `euromonitor.com`, `export.gov`
- `trade.gov`, `wto.org`, `oecd.org`, `worldbank.org`

### Regulatory & Compliance (8 domains)
- `compliance.com`, `thomsonreuters.com`, `lexisnexis.com`, `westlaw.com`
- `sec.gov`, `finra.org`, `cftc.gov`, `federalregister.gov`

## Implementation Guidelines

### 1. Query Diversification
- Rotate domain preferences per query
- Limit results per domain per search (max 3-4)
- Include minimum unique domains per search (5+)
- Weight newer sources higher
- Penalize overused domains

### 2. Source Validation
- Verify domain authority scores (min 0.6)
- Check content freshness (prefer <90 days)
- Validate content relevance (min 0.7 score)
- Assess source credibility

### 3. Monitoring Metrics
- Track domain distribution per run
- Monitor citation diversity scores
- Alert on concentration thresholds
- Report new domain discovery

### 4. Domain Weight Calculation
```php
function calculate_domain_weight($domain, $category, $current_count, $total_count) {
    $base_weight = 1.0;
    $current_percentage = ($current_count / $total_count) * 100;
    
    // Penalize overrepresented domains
    if ($current_percentage > 25) {
        $base_weight *= 0.3; // Heavy penalty
    } elseif ($current_percentage > 15) {
        $base_weight *= 0.6; // Moderate penalty
    }
    
    // Apply category multiplier and priority bonuses
    return max(0.1, min(3.0, $base_weight));
}
```

## Usage Instructions

### 1. Run Citation Analysis
```bash
# Analyze existing citations
php /local/customerintel/analyze_citations.php

# Or use artifact repository data
php /local/customerintel/extract_artifact_citations.php
```

### 2. Generate Rebalancing Plan
```bash
php /local/customerintel/rebalance_retrieval_scope.php
```

### 3. Implement Recommendations
```bash
php /local/customerintel/implement_retrieval_rebalancing.php
```

### 4. Review Generated Files
- `retrieval_scope_rebalancing_patch.json` - Main recommendations
- `config/enhanced_domain_config.json` - Domain weight configuration
- `config/enhanced_query_templates.json` - Updated query templates
- `config/retrieval_monitoring.json` - Monitoring settings
- `implementation_code_snippets.php` - Ready-to-use code

## Integration Points

### NB Orchestrator Integration
1. Import domain weight configuration
2. Update query generation logic
3. Implement diversity checking
4. Add monitoring telemetry

### Synthesis Engine Integration
1. Use enhanced citation manager
2. Apply diversity scoring
3. Track domain metrics
4. Generate diversity reports

## Expected Outcomes

### Immediate Benefits
- Reduced domain concentration risk
- Improved source diversity
- Enhanced research coverage
- Better regulatory compliance

### Long-term Impact
- More balanced intelligence reports
- Reduced single-point-of-failure risks
- Improved research credibility
- Enhanced competitive intelligence

## Monitoring & Maintenance

### Key Metrics
- Domain concentration ratios
- Citation diversity scores
- New domain discovery rate
- Source quality metrics

### Regular Reviews
- Monthly domain distribution analysis
- Quarterly template effectiveness review
- Semi-annual diversification strategy update
- Annual comprehensive rebalancing audit

## Files Generated

1. **Citation Analysis**: `citation_analysis.json`
2. **Rebalancing Patch**: `retrieval_scope_rebalancing_patch.json`
3. **Domain Configuration**: `config/enhanced_domain_config.json`
4. **Query Templates**: `config/enhanced_query_templates.json`
5. **Monitoring Config**: `config/retrieval_monitoring.json`
6. **Implementation Code**: `implementation_code_snippets.php`
7. **Summary Report**: `retrieval_rebalancing_summary.json`

This system provides a comprehensive approach to rebalancing retrieval scope and ensuring diverse, high-quality source coverage across all intelligence operations.