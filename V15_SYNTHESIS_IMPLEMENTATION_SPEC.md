# V15 Intelligence Playbook Implementation Specification

## Overview

This document provides the complete implementation specification for transforming the current synthesis_engine.php to meet the V15 Intelligence Playbook requirements defined in synthesis_blueprint.md and synthesis_contract.json.

## Data Structure Requirements

### 1. Main Output Structure

```php
public function build_report(int $runid, bool $force_regenerate = false): array {
    // Return structure must match JSON schema exactly:
    return [
        'meta' => [
            'source_company' => $source_company_name,  // string, required
            'target_company' => $target_company_name,  // string, required
            'generated_at' => date('c'),              // ISO 8601 datetime
            'version' => 'v15-playbook-s1'           // fixed value
        ],
        'report' => [
            'executive_insight' => $this->format_section(...),
            'customer_fundamentals' => $this->format_section(...),
            'financial_trajectory' => $this->format_section(...),
            'margin_pressures' => $this->format_section(...),
            'strategic_priorities' => $this->format_section(...),
            'growth_levers' => $this->format_section(...),
            'buying_behavior' => $this->format_section(...),
            'current_initiatives' => $this->format_section(...),
            'risk_signals' => $this->format_section(...)
        ],
        'citations' => [
            'global_order' => [1, 2, 3, ...],  // array of citation IDs used
            'sources' => [                     // array of citation objects
                [
                    'id' => 1,
                    'url' => 'https://...',
                    'title' => '...',
                    'publisher' => '...',
                    'domain' => '...',
                    'year' => 2024
                ]
            ]
        ],
        'qa' => [
            'scores' => [
                'relevance_density' => 0.75,
                'pov_strength' => 0.82,
                'evidence_health' => 0.68,
                'precision' => 0.71,
                'target_awareness' => 0.85
            ],
            'warnings' => []  // array of warning strings
        ]
    ];
}
```

### 2. Section Format Helper

```php
private function format_section(string $text, array $citation_ids = [], string $notes = ''): array {
    return [
        'text' => $text,                          // required, non-empty
        'inline_citations' => array_slice($citation_ids, 0, 8),  // max 8
        'notes' => $notes                         // optional
    ];
}
```

## Section Implementation Methods

### 1. Executive Insight (formerly Executive Summary)

```php
private function draft_executive_insight($inputs, $patterns, $citation_tracker): array {
    // Extract key data points
    $ceo_concerns = $this->extract_ceo_concerns($inputs);
    $cash_position = $this->extract_financial_metrics($inputs, 'cash');
    $growth_trajectory = $this->extract_growth_metrics($inputs);
    $risk_factors = $this->extract_risk_signals($patterns);
    
    // Build content with citations
    $text = $this->build_executive_narrative(
        $ceo_concerns,
        $cash_position,
        $growth_trajectory,
        $risk_factors
    );
    
    // Add commercial POV
    $text .= " " . $this->add_commercial_pov(
        "why_this_matters_now",
        "near_term_leverage_points"
    );
    
    // Extract citation IDs used in text
    $citation_ids = $this->extract_citation_ids($text, $citation_tracker);
    
    return $this->format_section($text, $citation_ids);
}
```

### 2. Customer Fundamentals

```php
private function draft_customer_fundamentals($inputs, $patterns, $citation_tracker): array {
    // Required content elements
    $operating_model = $this->extract_operating_model($inputs);
    $revenue_mix = $this->extract_revenue_breakdown($inputs);
    $payer_buyer_dynamics = $this->extract_stakeholder_dynamics($inputs);
    $macro_pressures = $this->extract_macro_factors($patterns);
    
    // Build narrative with contradictions
    $text = $this->build_fundamentals_narrative(
        $operating_model,
        $revenue_mix,
        $payer_buyer_dynamics,
        $macro_pressures,
        $this->identify_contradictions($inputs)  // what they say vs numbers
    );
    
    $citation_ids = $this->extract_citation_ids($text, $citation_tracker);
    return $this->format_section($text, $citation_ids);
}
```

### 3. Financial Trajectory

```php
private function draft_financial_trajectory($inputs, $patterns, $citation_tracker): array {
    // Extract financial trends
    $growth_trends = $this->extract_growth_trends($inputs);
    $margin_trends = $this->extract_margin_trends($inputs);
    $cost_structure = $this->extract_cost_structure($inputs);
    $capex_opex = $this->extract_spending_patterns($inputs);
    $balance_sheet = $this->extract_balance_sheet_flexibility($inputs);
    
    // Identify inflection points
    $inflections = $this->identify_trajectory_inflections(
        $growth_trends,
        $margin_trends
    );
    
    // Build narrative with spend implications
    $text = $this->build_trajectory_narrative(
        $growth_trends,
        $margin_trends,
        $cost_structure,
        $inflections,
        $this->derive_spend_implications($inflections)
    );
    
    $citation_ids = $this->extract_citation_ids($text, $citation_tracker);
    return $this->format_section($text, $citation_ids);
}
```

### 4. Margin Pressures

```php
private function draft_margin_pressures($inputs, $patterns, $citation_tracker): array {
    // Extract pressure points
    $cost_drivers = [
        'labor' => $this->extract_labor_costs($inputs),
        'procurement' => $this->extract_procurement_costs($inputs),
        'channel_mix' => $this->extract_channel_costs($inputs),
        'regulatory' => $this->extract_regulatory_costs($inputs)
    ];
    
    // Identify controllable levers
    $cfo_levers = $this->identify_cfo_control_points($cost_drivers);
    $aspirational = $this->identify_aspirational_programs($inputs);
    
    // Map to fundable initiatives
    $fundable_initiatives = $this->map_pressures_to_initiatives(
        $cost_drivers,
        $cfo_levers
    );
    
    $text = $this->build_pressures_narrative(
        $cost_drivers,
        $cfo_levers,
        $aspirational,
        $fundable_initiatives
    );
    
    $citation_ids = $this->extract_citation_ids($text, $citation_tracker);
    return $this->format_section($text, $citation_ids);
}
```

### 5-9. Additional Sections

Similar implementation patterns for:
- `draft_strategic_priorities()` - 3-5 actual themes, exec accountability, KPIs
- `draft_growth_levers()` - segments, products, routes to market, trade-offs
- `draft_buying_behavior()` - decision makers, blockers, patterns, metrics
- `draft_current_initiatives()` - active programs, RFPs, migrations, stuck vs moving
- `draft_risk_signals()` - timing windows, regulatory, constraints, consequences

## Citation Management System

```php
class CitationManager {
    private $citations = [];
    private $next_id = 1;
    private $global_order = [];
    private $section_citations = [];  // Track citations per section
    
    public function add_citation(array $source_data): int {
        // Check if citation already exists
        $existing_id = $this->find_existing_citation($source_data['url']);
        if ($existing_id) {
            return $existing_id;
        }
        
        // Add new citation
        $id = $this->next_id++;
        $this->citations[] = [
            'id' => $id,
            'url' => $source_data['url'],
            'title' => $source_data['title'] ?? null,
            'publisher' => $source_data['publisher'] ?? null,
            'domain' => $this->extract_domain($source_data['url']),
            'year' => $source_data['year'] ?? null
        ];
        
        return $id;
    }
    
    /**
     * Process text with inline citations and track usage
     * Converts [n] tokens to proper IDs and tracks in global_order
     * 
     * @param string $text Section text with [n] placeholders
     * @param string $section_name Name of the section being processed
     * @return array ['text' => processed_text, 'inline_citations' => [ids]]
     */
    public function process_section_citations(string $text, string $section_name): array {
        $inline_citations = [];
        $citation_map = [];  // Map placeholder [n] to actual citation IDs
        
        // Extract all [n] tokens from text
        preg_match_all('/\[(\d+)\]/', $text, $matches, PREG_OFFSET_CAPTURE);
        
        if (!empty($matches[1])) {
            // Process citations in order of first appearance
            $seen_placeholders = [];
            foreach ($matches[1] as $match) {
                $placeholder = (int)$match[0];
                
                // Only process each placeholder once (first occurrence)
                if (!in_array($placeholder, $seen_placeholders)) {
                    $seen_placeholders[] = $placeholder;
                    
                    // Get or create actual citation ID for this placeholder
                    if (!isset($citation_map[$placeholder])) {
                        // This would need to be mapped to actual citation data
                        // For now, using placeholder as ID (in real impl, would lookup)
                        $citation_map[$placeholder] = $placeholder;
                    }
                    
                    $citation_id = $citation_map[$placeholder];
                    
                    // Add to inline citations for this section
                    if (!in_array($citation_id, $inline_citations)) {
                        $inline_citations[] = $citation_id;
                        $this->mark_used($citation_id);
                    }
                }
            }
        }
        
        // Store section citations for validation
        $this->section_citations[$section_name] = $inline_citations;
        
        return [
            'text' => $text,
            'inline_citations' => array_slice($inline_citations, 0, 8)  // Max 8 per section
        ];
    }
    
    public function mark_used(int $citation_id): void {
        // Add to global_order in order of first use across all sections
        if (!in_array($citation_id, $this->global_order)) {
            $this->global_order[] = $citation_id;
        }
    }
    
    /**
     * Validate citations according to implementation rules
     * 
     * @throws \Exception if validation fails
     */
    public function validate(): void {
        // Rule 1: inline_citations must be subset of global_order
        foreach ($this->section_citations as $section => $citations) {
            foreach ($citations as $cit_id) {
                if (!in_array($cit_id, $this->global_order)) {
                    throw new \Exception("Citation {$cit_id} in section {$section} not in global_order");
                }
            }
        }
        
        // Rule 2: Sources list must only contain IDs from global_order
        $source_ids = array_column($this->citations, 'id');
        foreach ($this->global_order as $global_id) {
            if (!in_array($global_id, $source_ids)) {
                throw new \Exception("Global order ID {$global_id} not found in sources");
            }
        }
    }
    
    public function get_output(): array {
        // Only return sources that are actually used (in global_order)
        $used_sources = array_filter($this->citations, function($citation) {
            return in_array($citation['id'], $this->global_order);
        });
        
        // Re-index array to maintain proper JSON array format
        $used_sources = array_values($used_sources);
        
        return [
            'global_order' => $this->global_order,
            'sources' => $used_sources
        ];
    }
}
```

## Citation Validation Rules

### Implementation Requirements

1. **inline_citations subset rule:**
   - Every citation ID in a section's `inline_citations` array MUST exist in `citations.global_order`
   - Validation: Check each section's inline_citations against global_order

2. **Order-of-first-use rule:**
   - Inline [n] tokens in text must match the order in `inline_citations` array
   - When [1] appears before [2] in text, citation ID for [1] must appear before [2] in inline_citations
   - Process citations in order of first appearance in text

3. **Sources list filtering:**
   - The `citations.sources` array must ONLY contain citations present in `global_order`
   - Filter out any unused citations before output
   - Maintain proper array indexing in JSON output

### Validation Helper Methods

```php
/**
 * Extract and validate citation tokens from text
 * Ensures [n] tokens match inline_citations order
 */
private function validate_citation_order(string $text, array $inline_citations): bool {
    preg_match_all('/\[(\d+)\]/', $text, $matches, PREG_OFFSET_CAPTURE);
    
    if (empty($matches[1])) {
        return empty($inline_citations);  // No citations in text, should be none in array
    }
    
    // Build order of first appearance
    $text_order = [];
    $seen = [];
    foreach ($matches[1] as $match) {
        $num = (int)$match[0];
        if (!in_array($num, $seen)) {
            $text_order[] = $num;
            $seen[] = $num;
        }
    }
    
    // Verify inline_citations matches text order
    return count($text_order) === count($inline_citations);
}

/**
 * Validate entire report structure for citation consistency
 */
private function validate_report_citations(array $report_data): array {
    $warnings = [];
    
    // Check each section
    foreach ($report_data['report'] as $section_name => $section) {
        // Rule 1: inline_citations must be subset of global_order
        foreach ($section['inline_citations'] as $cit_id) {
            if (!in_array($cit_id, $report_data['citations']['global_order'])) {
                $warnings[] = "Section '{$section_name}': citation {$cit_id} not in global_order";
            }
        }
        
        // Rule 2: Check [n] token order matches inline_citations
        if (!$this->validate_citation_order($section['text'], $section['inline_citations'])) {
            $warnings[] = "Section '{$section_name}': citation order mismatch";
        }
    }
    
    // Rule 3: Verify sources only contains used citations
    $source_ids = array_column($report_data['citations']['sources'], 'id');
    foreach ($source_ids as $source_id) {
        if (!in_array($source_id, $report_data['citations']['global_order'])) {
            $warnings[] = "Source ID {$source_id} not in global_order";
        }
    }
    
    return $warnings;
}
```

## QA Scoring Implementation

```php
class QAScorer {
    public function calculate_scores(array $sections, array $inputs): array {
        return [
            'relevance_density' => $this->calculate_relevance_density($sections),
            'pov_strength' => $this->calculate_pov_strength($sections),
            'evidence_health' => $this->calculate_evidence_health($sections),
            'precision' => $this->calculate_precision($sections),
            'target_awareness' => $this->calculate_target_awareness($sections, $inputs)
        ];
    }
    
    private function calculate_relevance_density(array $sections): float {
        // Ratio of signal sentences to total sentences
        $total_sentences = 0;
        $signal_sentences = 0;
        
        foreach ($sections as $section) {
            $sentences = $this->split_sentences($section['text']);
            $total_sentences += count($sentences);
            
            foreach ($sentences as $sentence) {
                if ($this->is_signal_sentence($sentence)) {
                    $signal_sentences++;
                }
            }
        }
        
        return $total_sentences > 0 ? $signal_sentences / $total_sentences : 0;
    }
    
    private function calculate_pov_strength(array $sections): float {
        // Presence and clarity of commercial POV
        $pov_count = 0;
        $section_count = count($sections);
        
        foreach ($sections as $section) {
            if ($this->contains_commercial_pov($section['text'])) {
                $pov_count++;
            }
        }
        
        return $section_count > 0 ? $pov_count / $section_count : 0;
    }
    
    private function calculate_evidence_health(array $sections): float {
        // Fraction of claims with citations
        $total_claims = 0;
        $cited_claims = 0;
        
        foreach ($sections as $section) {
            $claims = $this->extract_claims($section['text']);
            $total_claims += count($claims);
            $cited_claims += count($section['inline_citations']);
        }
        
        return $total_claims > 0 ? min(1.0, $cited_claims / $total_claims) : 0;
    }
    
    private function calculate_precision(array $sections): float {
        // Low generic language, concrete nouns, numbers, roles
        $precision_score = 0;
        $section_count = count($sections);
        
        foreach ($sections as $section) {
            $text = $section['text'];
            $precision_score += $this->score_precision($text);
        }
        
        return $section_count > 0 ? $precision_score / $section_count : 0;
    }
    
    private function calculate_target_awareness(array $sections, array $inputs): float {
        // Specific to named target, not generic
        $target_name = $inputs['company_target']['name'] ?? '';
        if (empty($target_name)) return 0.8; // Default if no target
        
        $target_mentions = 0;
        $total_opportunities = 0;
        
        foreach ($sections as $section) {
            $text = $section['text'];
            $target_mentions += substr_count(strtolower($text), strtolower($target_name));
            $total_opportunities += $this->count_target_opportunities($text);
        }
        
        return $total_opportunities > 0 ? min(1.0, $target_mentions / $total_opportunities) : 0.8;
    }
}
```

## Voice Enforcement Integration

```php
private function apply_voice_enforcement(string $text, string $voice_type = 'strategic_direct'): string {
    // Load voice enforcer service
    $voice_enforcer = new \local_customerintel\services\voice_enforcer();
    
    // Apply voice transformation
    $enforced_text = $voice_enforcer->enforce(
        $text,
        [
            'voice' => $voice_type,
            'remove_consultant_speak' => true,
            'add_commercial_pov' => true,
            'compression_level' => 'moderate'
        ]
    );
    
    return $enforced_text;
}
```

## Implementation Phases

### Phase 1: Core Structure (Days 1-3)
1. Refactor `build_report()` to return new structure
2. Implement `format_section()` helper
3. Create CitationManager class
4. Update cache handling for new structure

### Phase 2: Section Methods (Days 4-7)
1. Implement all 9 `draft_*` methods
2. Add content extraction helpers
3. Implement commercial POV insertion
4. Add contradiction detection

### Phase 3: Citation System (Days 8-9)
1. Complete CitationManager implementation
2. Add inline citation formatting
3. Implement citation deduplication
4. Add Sources list generation

### Phase 4: QA Scoring (Days 10-11)
1. Implement QAScorer class
2. Add all 5 scoring metrics
3. Implement warning generation
4. Add threshold validation

### Phase 5: Voice & Testing (Days 12-13)
1. Integrate voice_enforcer service
2. Add compression algorithms
3. Implement evidence gap detection
4. Complete integration testing

## Validation Checklist

- [ ] All 9 sections present and non-empty
- [ ] Each section has text, inline_citations, optional notes
- [ ] Citations properly numbered and deduplicated
- [ ] All QA scores calculated (0.0-1.0 range)
- [ ] Meta object with required fields
- [ ] Version string exactly "v15-playbook-s1"
- [ ] ISO 8601 datetime format
- [ ] Maximum 8 citations per section
- [ ] No invented data
- [ ] Commercial POV in each section
- [ ] Target awareness throughout
- [ ] Voice consistency maintained