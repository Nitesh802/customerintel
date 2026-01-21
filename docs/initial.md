# CustomerIntel_Rubi – V15 Output Enrichment (Gold Standard Alignment)

## Objective
Enhance the existing V15 synthesis engine in `/local/customerintel/synthesis_engine.php` so that the generated Intelligence Playbook reports consistently match the structure, narrative depth, and analytical clarity demonstrated in the Gold Standard Report.

The enhancement will refine synthesis depth, strengthen section-level insight logic, improve coherence across sections, and better integrate citation traceability and QA scoring.

## Non-Goals
- Replacing or rebuilding the entire synthesis pipeline.
- Introducing new external APIs.
- Modifying Moodle UI templates or unrelated plugin components.
- Migrating to a new model version beyond V15.

## Current Baseline
The existing V15 pipeline already:
- Uses a 9-section structure (defined in `synthesis_engine.php`).
- Integrates a `CitationManager` class for source tracking.
- Implements a 5-metric QA scoring system.
- Renders HTML reports with a Sources section.
- Utilizes caching and error logging through `cache_helper`.

The current limitation is the **report depth and narrative quality** — insights lack clarity, contextual linkage, and depth found in the Gold Standard example.

## Enhancement Goals
1. **Synthesis Depth & Relevance**
   - Expand the reasoning depth per section (especially in "Strategic Implications", "Customer Priorities", and "Growth Opportunities").
   - Ensure accurate linkage between data points and corresponding insights.
   - Avoid repetition or generic phrasing.

2. **Gold Standard Alignment**
   - Match the Gold Standard Report’s tone, hierarchy, and narrative progression.
   - Ensure a consistent 9-section flow with strong transitions and subhead summaries.
   - Enforce explicit customer/target alignment in synthesis logic.

3. **Citation Enrichment**
   - Enhance the `CitationManager` so that:
     - Each insight can list its citation(s) inline.
     - Confidence scores are captured in the JSON response.
     - Citations appear contextually relevant to their sections.

4. **QA Scoring Enhancement**
   - Adjust weighting to prioritize:
     - Clarity (30%)
     - Relevance (25%)
     - Insight Depth (20%)
     - Evidence Strength (15%)
     - Structural Consistency (10%)
   - Ensure QA scoring logs and aggregates section-level performance metrics.

5. **Output & Rendering**
   - JSON output structure should include:
     - `sections[] → { title, content, citations[], qa_score }`
   - Maintain backward compatibility for `view_report.php`.
   - Continue rendering citations as a “Sources” list below each section.

## Acceptance Criteria
**Given** a valid V15 input prompt and dataset,  
**When** the synthesis engine runs,  
**Then**:
1. The resulting report matches the structure, tone, and quality of the Gold Standard Report.
2. Each section has at least one citation linked via `CitationManager`.
3. QA scoring reflects the new weighting and logs results to the DB.
4. The report renders cleanly in `view_report.php` with citations intact.
5. Cached reports remain valid and retrievable.

## Performance & Observability
- Performance degradation ≤ 15% from current synthesis time.
- Extend `cache_helper` to store enriched JSON output.
- Add logging for section-level QA scores and total synthesis time.

## Deployment Notes
- All implementation within `/local/customerintel/`.
- Requires updates to `synthesis_engine.php`, possibly `CitationManager`, and QA scoring modules.
- Add new PHPUnit tests for:
  - Section synthesis completeness.
  - Citation validation.
  - QA score accuracy.
- No DB schema changes required.

## Risks
- JSON parsing failures from model output if section nesting changes.
- Minor increases in token cost or runtime.
- QA score drift between enriched and legacy reports.

## Open Questions
- Should low-confidence sections trigger a visual indicator in `view_report.php`?
- Should enriched reports be version-tagged (e.g., V15.1) for traceability?
- Should the new QA metrics feed into future analytics dashboards?