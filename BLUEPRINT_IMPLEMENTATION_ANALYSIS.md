# V15 Intelligence Playbook Blueprint vs Current Implementation Analysis

## Executive Summary

This document analyzes the gaps between the V15 Intelligence Playbook blueprint specification and the current synthesis_engine.php implementation.

## Key Findings

### 1. Section Structure Mismatch

**Blueprint Requirement (9 sections):**
1. Executive Insight
2. Customer Fundamentals
3. Financial Trajectory
4. Margin Pressures
5. Strategic Priorities
6. Growth Levers
7. Buying Behavior
8. Current Initiatives
9. Risk Signals

**Current Implementation (4 sections):**
- Executive Summary (draft_executive_summary)
- What's Overlooked (draft_whats_overlooked)
- Opportunity Blueprints (draft_opportunity_blueprints)
- Convergence Insight (draft_convergence_insight)

**Gap:** Complete mismatch in section count and structure. Current implementation lacks 5 required sections and has different section names.

### 2. Citation System

**Blueprint Requirement:**
- Inline numeric citations [n]
- Global numbering across report
- Plain-text Sources list at bottom
- Per-section cap of 8 citations
- Format: **[n]** "Title", Publisher *(Year)* (domain/path…)

**Current Implementation:**
- Basic citation_tracker object exists
- add_citation_reference() method mentioned but implementation unclear
- Citations tracked in draft_sections() method
- No clear Sources list generation

**Gap:** Citation system partially implemented but needs enhancement for format compliance.

### 3. Quality Assurance & Scoring

**Blueprint Requirement:**
- Relevance Density (≥0.6)
- POV Strength (≥0.7) 
- Evidence Health (≥0.6)
- Precision (≥0.7)
- Target Awareness (≥0.8)

**Current Implementation:**
- section_ok_tolerant() validation exists
- qa_warnings array tracks issues
- No scoring metrics implementation

**Gap:** QA exists but lacks the specific scoring metrics required.

### 4. Voice & POV

**Blueprint Requirement:**
- Strategic + Direct voice
- Commercial POV embedded in each section
- "So what for us" after factual blocks
- Crisp, no slogans

**Current Implementation:**
- voice_enforcer service referenced but not integrated
- No clear commercial POV insertion

**Gap:** Voice enforcement needs to be fully integrated.

### 5. Adaptive Depth & Content Rules

**Blueprint Requirement:**
- Adaptive depth, avoid fluff
- Compress repetition
- Never invent data
- Evidence gaps labeled

**Current Implementation:**
- Fallback content generation exists
- generate_fallback_* methods provide minimal content
- Some defensive programming with empty data handling

**Gap:** Needs more sophisticated content compression and evidence gap handling.

## Implementation Recommendations

### Priority 1: Restructure Section Generation
1. Replace current 4-section structure with required 9 sections
2. Create new drafting methods:
   - draft_customer_fundamentals()
   - draft_financial_trajectory()
   - draft_margin_pressures()
   - draft_strategic_priorities()
   - draft_growth_levers()
   - draft_buying_behavior()
   - draft_current_initiatives()
   - draft_risk_signals()
3. Rename draft_executive_summary() to draft_executive_insight()

### Priority 2: Enhance Citation System
1. Implement proper citation formatting
2. Add Sources list generation at report bottom
3. Enforce per-section citation caps
4. Implement global numbering system

### Priority 3: Implement QA Scoring
1. Add scoring metrics calculation for each section
2. Implement threshold checks
3. Add scoring results to output

### Priority 4: Integrate Voice Enforcement
1. Connect voice_enforcer service
2. Add commercial POV insertion logic
3. Implement "So what for us" blocks

### Priority 5: Content Quality Controls
1. Add evidence gap labeling
2. Implement repetition detection and compression
3. Enhance data validation to prevent invention

## File Modifications Required

### synthesis_engine.php
- Major refactoring of draft_sections() method
- Addition of 5 new section drafting methods
- Enhancement of citation system
- Integration of QA scoring
- Voice enforcement integration

### Supporting Files
- voice_enforcer.php - needs to be connected
- citation_resolver.php - needs enhancement
- selfcheck_validator.php - needs scoring metrics

## Migration Path

1. **Phase 1:** Create new section drafting methods alongside existing ones
2. **Phase 2:** Implement citation system enhancements
3. **Phase 3:** Add QA scoring metrics
4. **Phase 4:** Integrate voice enforcement
5. **Phase 5:** Switch to new 9-section structure
6. **Phase 6:** Remove deprecated 4-section methods

## Testing Requirements

- Unit tests for each new section drafting method
- Integration tests for complete 9-section generation
- Citation formatting validation tests
- QA scoring threshold tests
- Voice consistency tests

## Risk Assessment

- **High Risk:** Breaking existing synthesis functionality during migration
- **Medium Risk:** Performance impact from additional sections
- **Low Risk:** Citation formatting issues

## Estimated Effort

- Section restructuring: 3-4 days
- Citation system: 1-2 days
- QA scoring: 2 days
- Voice integration: 1-2 days
- Testing: 2-3 days

**Total: 9-13 days**

## JSON Contract Schema Requirements

Based on synthesis_contract.json, the output must conform to this structure:

### Required Output Structure

```json
{
  "meta": {
    "source_company": "string (required)",
    "target_company": "string (required)",
    "generated_at": "ISO 8601 datetime",
    "version": "v15-playbook-s1" (fixed value)
  },
  "report": {
    "executive_insight": { "text": "...", "inline_citations": [1,2,3], "notes": "..." },
    "customer_fundamentals": { "text": "...", "inline_citations": [...], "notes": "..." },
    "financial_trajectory": { "text": "...", "inline_citations": [...], "notes": "..." },
    "margin_pressures": { "text": "...", "inline_citations": [...], "notes": "..." },
    "strategic_priorities": { "text": "...", "inline_citations": [...], "notes": "..." },
    "growth_levers": { "text": "...", "inline_citations": [...], "notes": "..." },
    "buying_behavior": { "text": "...", "inline_citations": [...], "notes": "..." },
    "current_initiatives": { "text": "...", "inline_citations": [...], "notes": "..." },
    "risk_signals": { "text": "...", "inline_citations": [...], "notes": "..." }
  },
  "citations": {
    "global_order": [1, 2, 3, 4, ...],
    "sources": [
      {
        "id": 1,
        "url": "required string",
        "title": "optional string",
        "publisher": "optional string",
        "domain": "optional string",
        "year": "integer or null"
      }
    ]
  },
  "qa": {
    "scores": {
      "relevance_density": 0.0-1.0,
      "pov_strength": 0.0-1.0,
      "evidence_health": 0.0-1.0,
      "precision": 0.0-1.0,
      "target_awareness": 0.0-1.0
    },
    "warnings": ["array of warning strings"]
  }
}
```

### Key Schema Constraints

1. **Section Structure:**
   - Each section must have `text` (non-empty string)
   - Each section must have `inline_citations` array (max 8 items)
   - Each section can have optional `notes` field

2. **Citation Structure:**
   - Global numbering with `global_order` array
   - Sources array with required `id` and `url`
   - Optional metadata: title, publisher, domain, year

3. **QA Metrics:**
   - All scores between 0.0 and 1.0
   - Five specific metrics required
   - Warnings array for issues

4. **Meta Requirements:**
   - Must include both source and target company names
   - ISO 8601 datetime for generated_at
   - Fixed version string: "v15-playbook-s1"

### PHP Implementation Requirements

The synthesis_engine.php must return data in this exact structure. Current gaps:

1. **Output format:** Current returns flat array, needs nested object structure
2. **Section format:** Current sections are strings/arrays, need objects with text/inline_citations/notes
3. **Citation tracking:** Needs id-based system with global_order tracking
4. **QA scores:** Must calculate and return all 5 metrics
5. **Meta object:** Not present in current implementation

### Validation Requirements

- JSON Schema validation should be implemented
- All required fields must be present
- Type constraints must be enforced
- Citation ID references must be valid

### Citation Validation Rules (Critical)

1. **Subset Rule:** `inline_citations` arrays in each section MUST only contain IDs that exist in `citations.global_order`
   - Example: If section has `inline_citations: [2, 5]`, both 2 and 5 must be in `global_order`
   - Implementation: Validate each section's inline_citations against global_order array

2. **Order-of-First-Use Rule:** Inline [n] tokens in text must match the sequence in `inline_citations` array
   - Example: If text has "revenue grew 15% [2] while costs increased [5]", inline_citations must be [2, 5] not [5, 2]
   - Implementation: Parse [n] tokens in order of first appearance, build inline_citations array accordingly

3. **Sources Filtering Rule:** The `citations.sources` array must ONLY include citations present in `global_order`
   - Example: If global_order is [1, 3, 5], sources should only contain citation objects with IDs 1, 3, and 5
   - Implementation: Filter sources array before output to exclude unused citations

### Example Citation Flow

1. **Text Generation:** "The company reported 20% growth [1] despite market headwinds [3]."
2. **Extraction:** Find [1] and [3] in order of appearance
3. **inline_citations:** [1, 3] (matches order in text)
4. **global_order update:** Add 1 and 3 if not already present
5. **Sources filtering:** Only include citation objects with IDs in global_order
6. **Validation:** Ensure inline_citations ⊆ global_order for all sections