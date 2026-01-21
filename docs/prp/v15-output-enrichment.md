# Product Requirements Prompt (PRP): V15 Output Enrichment

**Generated:** 2024-01-15
**Repository:** CustomerIntel_Rubi (PHP/Moodle Plugin)
**Feature:** V15 Intelligence Playbook Output Enrichment

---

## Executive Summary

Enhance the existing V15 Intelligence Playbook implementation in the CustomerIntel Moodle plugin to improve synthesis depth, report quality, and alignment with Gold Standard Report requirements. The implementation will enrich the 9-section structure with deeper NB data integration, improved citation accuracy, enhanced POV insertion, and more sophisticated content generation based on actual company data patterns.

---

## Architecture Overview

### Current State
- **Core Engine:** `/local_customerintel/classes/services/synthesis_engine.php`
- **9 Sections:** Executive Insight, Customer Fundamentals, Financial Trajectory, Margin Pressures, Strategic Priorities, Growth Levers, Buying Behavior, Current Initiatives, Risk Signals
- **Citation System:** CitationManager class with inline [n] markers
- **QA Scoring:** 5 metrics (relevance_density, pov_strength, evidence_health, precision, target_awareness)
- **Rendering:** HTML output with Sources section in view_report.php

### Proposed Enhancements
1. **Deeper NB Integration:** Extract and utilize more granular data from NB1-NB15
2. **Dynamic Content Generation:** Replace static fallbacks with NB-driven narratives
3. **Citation Enrichment:** Populate from actual NB sources and company data
4. **POV Strengthening:** Embed commercial insights based on patterns
5. **Contradiction Detection:** Identify and highlight mismatches between claims and data

---

## File Plan

### Files to Modify

1. **`/local_customerintel/classes/services/synthesis_engine.php`** (~500 LOC changes)
   - Purpose: Enhance section drafting methods with NB data extraction
   - Changes:
     - Improve `populate_citations()` to extract from all NB sources
     - Enhance each `draft_*` method to use actual NB data
     - Add `extract_nb_insights()` helper method
     - Implement `detect_contradictions()` method
     - Enhance `calculate_qa_scores()` with better metrics

2. **`/local_customerintel/classes/services/nb_data_extractor.php`** (NEW ~300 LOC)
   - Purpose: Dedicated service for extracting structured data from NB results
   - Methods:
     - `extract_financial_metrics($nb_data)`
     - `extract_strategic_themes($nb_data)`
     - `extract_risk_factors($nb_data)`
     - `extract_growth_indicators($nb_data)`
     - `extract_operational_insights($nb_data)`

3. **`/local_customerintel/classes/services/content_enricher.php`** (NEW ~250 LOC)
   - Purpose: Enrich section content with patterns and insights
   - Methods:
     - `enrich_with_contradictions($text, $patterns)`
     - `add_commercial_pov($text, $context)`
     - `enhance_precision($text, $metrics)`
     - `strengthen_target_awareness($text, $target_company)`

4. **`/local_customerintel/tests/v15_enrichment_test.php`** (NEW ~400 LOC)
   - Purpose: Comprehensive tests for enrichment functionality
   - Tests:
     - NB data extraction accuracy
     - Section content quality metrics
     - Citation validation
     - QA score calculations
     - Contradiction detection

5. **`/local_customerintel/view_report.php`** (~50 LOC changes)
   - Purpose: Enhanced display of enriched content
   - Changes:
     - Display contradiction warnings
     - Show data source indicators
     - Highlight POV statements

### Files to Create

6. **`/docs/ENRICHMENT_GUIDE.md`** (NEW)
   - Purpose: Document enrichment patterns and best practices

7. **`/local_customerintel/schemas/enrichment_patterns.json`** (NEW)
   - Purpose: Define patterns for content enrichment

---

## Data Models & APIs

### NB Data Extraction Model
```php
class NBDataExtract {
    public array $financial_metrics;    // Revenue, margins, growth rates
    public array $strategic_themes;     // Key initiatives and priorities  
    public array $risk_indicators;      // Timing, regulatory, market risks
    public array $operational_data;     // Efficiency metrics, cost drivers
    public array $citations;            // Source URLs and metadata
}
```

### Enrichment Pattern Model
```php
class EnrichmentPattern {
    public string $section;
    public string $pattern_type;  // contradiction, insight, metric
    public string $template;
    public array $required_data;
    public float $confidence;
}
```

### Enhanced Section Output
```php
[
    'text' => 'Enriched narrative with [1] citations',
    'inline_citations' => [1, 2, 3],
    'notes' => 'Data quality warnings',
    'contradictions' => ['claim vs reality'],
    'confidence' => 0.85
]
```

---

## Testing Strategy

### Unit Tests
- `test_nb_data_extraction()` - Verify accurate data extraction from each NB
- `test_contradiction_detection()` - Validate contradiction identification
- `test_pov_insertion()` - Check commercial POV placement
- `test_citation_enrichment()` - Verify citation accuracy

### Integration Tests
- `test_full_synthesis_enrichment()` - End-to-end enriched synthesis
- `test_qa_score_improvements()` - Verify score calculations
- `test_section_interdependencies()` - Check cross-section consistency

### Acceptance Tests
- Generate report for known company with rich NB data
- Verify all 9 sections contain company-specific content
- Validate citations trace to actual sources
- Confirm QA scores meet thresholds (≥0.6 minimum, ≥0.7 target)

---

## Performance Targets

- **Synthesis Generation:** < 5 seconds for full enrichment
- **NB Data Extraction:** < 500ms per NB
- **Citation Resolution:** < 100ms per citation
- **Memory Usage:** < 128MB peak during synthesis
- **Cache Hit Rate:** > 80% for repeated runs

---

## Rollout Plan

### Phase 1: NB Data Extraction (Day 1-2)
1. Implement `nb_data_extractor.php`
2. Add extraction tests
3. Integrate with synthesis_engine

### Phase 2: Content Enrichment (Day 3-4)
1. Implement `content_enricher.php`
2. Update section drafting methods
3. Add enrichment tests

### Phase 3: Enhanced Citations (Day 5)
1. Improve `populate_citations()`
2. Add source metadata extraction
3. Update citation rendering

### Phase 4: QA & Polish (Day 6-7)
1. Refine QA scoring algorithms
2. Add contradiction detection
3. Update view_report.php display
4. Comprehensive testing

---

## Risks, Unknowns, and Mitigation

### Risk 1: NB Data Inconsistency
- **Risk:** NB outputs may have varying structures
- **Impact:** Data extraction failures
- **Mitigation:** Implement defensive parsing with fallbacks

### Risk 2: Performance Degradation
- **Risk:** Enrichment adds processing overhead
- **Impact:** Slower report generation
- **Mitigation:** Implement caching layers, optimize extraction

### Risk 3: Over-Enrichment
- **Risk:** Too much detail makes reports unwieldy
- **Impact:** Reduced readability
- **Mitigation:** Implement adaptive depth controls

### Risk 4: Citation Accuracy
- **Risk:** Incorrect citation mapping
- **Impact:** Invalid source references
- **Mitigation:** Validation layer with warning system

### Unknown 1: Optimal POV Density
- **Question:** How much commercial POV is appropriate?
- **Approach:** Start conservative, measure impact on scores

### Unknown 2: Contradiction Threshold
- **Question:** When to flag vs. reconcile contradictions?
- **Approach:** Log all contradictions, surface significant ones

---

## References

- `/docs/synthesis_blueprint.md` - V15 specification
- `/docs/synthesis_contract.json` - Output schema
- `/local_customerintel/classes/services/synthesis_engine.php` - Current implementation
- `BLUEPRINT_IMPLEMENTATION_ANALYSIS.md` - Gap analysis
- `V15_SYNTHESIS_IMPLEMENTATION_SPEC.md` - Implementation guide

---

## Success Criteria

1. **All 9 sections** contain company-specific content (not generic fallbacks)
2. **QA scores** meet thresholds:
   - relevance_density ≥ 0.7
   - pov_strength ≥ 0.7  
   - evidence_health ≥ 0.6
   - precision ≥ 0.7
   - target_awareness ≥ 0.8
3. **Citations** trace to actual NB sources (≥80% coverage)
4. **Contradictions** identified and surfaced appropriately
5. **Performance** within targets (<5s total generation)
6. **Tests** achieve >80% code coverage

---

## Task Breakdown

### Slice 1: NB Data Extraction Foundation
- [ ] Create `nb_data_extractor.php` with basic structure
- [ ] Implement `extract_financial_metrics()`
- [ ] Add unit tests for extraction
- [ ] Integrate with synthesis_engine

### Slice 2: Strategic Data Extraction
- [ ] Implement `extract_strategic_themes()`
- [ ] Implement `extract_risk_factors()`
- [ ] Add corresponding tests
- [ ] Update Executive Insight section

### Slice 3: Content Enricher Base
- [ ] Create `content_enricher.php`
- [ ] Implement `add_commercial_pov()`
- [ ] Update Strategic Priorities section
- [ ] Add enrichment tests

### Slice 4: Enhanced Section Drafting
- [ ] Update `draft_customer_fundamentals()` with NB data
- [ ] Update `draft_financial_trajectory()` with metrics
- [ ] Update `draft_margin_pressures()` with cost data
- [ ] Add section-specific tests

### Slice 5: Citation System Enhancement
- [ ] Enhance `populate_citations()` method
- [ ] Extract citation metadata from NBs
- [ ] Update citation rendering
- [ ] Add citation validation tests

### Slice 6: Contradiction Detection
- [ ] Implement `detect_contradictions()` method
- [ ] Add contradiction highlighting
- [ ] Update QA warnings system
- [ ] Add contradiction tests

### Slice 7: QA Scoring Refinement
- [ ] Enhance scoring algorithms
- [ ] Add metric-specific calculations
- [ ] Update thresholds
- [ ] Validate against test data

### Slice 8: UI Enhancement & Polish
- [ ] Update view_report.php display
- [ ] Add enrichment indicators
- [ ] Document in ENRICHMENT_GUIDE.md
- [ ] Final integration testing

---

## Approval Required

**Before proceeding with implementation:**
1. Confirm understanding of V15 enrichment goals
2. Approve file modification plan
3. Validate performance targets
4. Confirm test coverage requirements

**Type `PROCEED` to begin implementation of Slice 1, or provide feedback for adjustments.**