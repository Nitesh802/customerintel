# Citation Validation Implementation Example

## Complete Working Example

This example demonstrates how citations should be processed and validated according to the V15 Intelligence Playbook requirements.

### Input Data Example

```php
// Raw citation sources from NB data
$raw_sources = [
    ['url' => 'https://company.com/annual-report-2024', 'title' => 'Annual Report 2024', 'year' => 2024],
    ['url' => 'https://news.com/market-analysis', 'title' => 'Market Analysis Q4', 'year' => 2024],
    ['url' => 'https://investor.com/earnings-call', 'title' => 'Q3 Earnings Call', 'year' => 2024],
    ['url' => 'https://company.com/strategy-update', 'title' => 'Strategy Update', 'year' => 2024],
    ['url' => 'https://unused-source.com/data', 'title' => 'Unused Data', 'year' => 2024]  // Won't be used
];
```

### Step 1: Initialize Citation Manager

```php
$citation_manager = new CitationManager();

// Register all potential sources
$citation_ids = [];
foreach ($raw_sources as $source) {
    $citation_ids[$source['url']] = $citation_manager->add_citation($source);
}
// Result: citation_ids = [
//   'https://company.com/annual-report-2024' => 1,
//   'https://news.com/market-analysis' => 2,
//   'https://investor.com/earnings-call' => 3,
//   'https://company.com/strategy-update' => 4,
//   'https://unused-source.com/data' => 5
// ]
```

### Step 2: Generate Section Text with Citations

```php
// Executive Insight section
$exec_text = "The company achieved 20% revenue growth [1] despite challenging market conditions [2]. " .
             "Leadership emphasized operational efficiency [1] as the key priority for 2025. " .
             "Q3 earnings showed margin expansion [3] driven by cost optimization initiatives.";

// Process citations for this section
$exec_result = $citation_manager->process_section_citations($exec_text, 'executive_insight');
// Result: 
// exec_result = [
//   'text' => (unchanged text with [1], [2], [3]),
//   'inline_citations' => [1, 2, 3]  // Order of first appearance
// ]

// Customer Fundamentals section  
$cust_text = "The B2B segment represents 60% of revenue [1] with enterprise clients showing " .
             "strong retention rates. New market analysis [2] indicates expansion opportunities " .
             "in adjacent verticals.";

$cust_result = $citation_manager->process_section_citations($cust_text, 'customer_fundamentals');
// Result:
// cust_result = [
//   'text' => (unchanged text),
//   'inline_citations' => [1, 2]  // Note: 1 already in global_order from exec section
// ]

// Financial Trajectory section
$fin_text = "Margin pressures from labor costs [4] are offset by pricing power in core segments. " .
            "The earnings trajectory [3] remains positive with 15% EBITDA growth projected.";

$fin_result = $citation_manager->process_section_citations($fin_text, 'financial_trajectory');
// Result:
// fin_result = [
//   'text' => (unchanged text),
//   'inline_citations' => [4, 3]  // Order matters: [4] appears first in text
// ]
```

### Step 3: Build Complete Report Structure

```php
$report = [
    'meta' => [
        'source_company' => 'Acme Corp',
        'target_company' => 'Partner Inc',
        'generated_at' => '2024-01-15T10:30:00Z',
        'version' => 'v15-playbook-s1'
    ],
    'report' => [
        'executive_insight' => [
            'text' => $exec_result['text'],
            'inline_citations' => $exec_result['inline_citations'],  // [1, 2, 3]
            'notes' => ''
        ],
        'customer_fundamentals' => [
            'text' => $cust_result['text'],
            'inline_citations' => $cust_result['inline_citations'],  // [1, 2]
            'notes' => ''
        ],
        'financial_trajectory' => [
            'text' => $fin_result['text'],
            'inline_citations' => $fin_result['inline_citations'],  // [4, 3]
            'notes' => ''
        ],
        // ... other 6 sections with empty/minimal content for this example
        'margin_pressures' => ['text' => 'Labor cost pressures...', 'inline_citations' => [], 'notes' => ''],
        'strategic_priorities' => ['text' => 'Digital transformation...', 'inline_citations' => [], 'notes' => ''],
        'growth_levers' => ['text' => 'Geographic expansion...', 'inline_citations' => [], 'notes' => ''],
        'buying_behavior' => ['text' => 'Consensus-driven...', 'inline_citations' => [], 'notes' => ''],
        'current_initiatives' => ['text' => 'Cloud migration...', 'inline_citations' => [], 'notes' => ''],
        'risk_signals' => ['text' => 'Regulatory changes...', 'inline_citations' => [], 'notes' => '']
    ],
    'citations' => $citation_manager->get_output(),
    'qa' => [
        'scores' => [
            'relevance_density' => 0.72,
            'pov_strength' => 0.68,
            'evidence_health' => 0.75,
            'precision' => 0.70,
            'target_awareness' => 0.82
        ],
        'warnings' => []
    ]
];
```

### Step 4: Final Citation Output

After processing all sections, the CitationManager produces:

```php
$citation_manager->get_output();
// Returns:
[
    'global_order' => [1, 2, 3, 4],  // Order of first use across ALL sections
    'sources' => [
        ['id' => 1, 'url' => 'https://company.com/annual-report-2024', 'title' => 'Annual Report 2024', 'year' => 2024, ...],
        ['id' => 2, 'url' => 'https://news.com/market-analysis', 'title' => 'Market Analysis Q4', 'year' => 2024, ...],
        ['id' => 3, 'url' => 'https://investor.com/earnings-call', 'title' => 'Q3 Earnings Call', 'year' => 2024, ...],
        ['id' => 4, 'url' => 'https://company.com/strategy-update', 'title' => 'Strategy Update', 'year' => 2024, ...]
    ]
    // Note: Citation ID 5 is NOT included because it was never used in any section
]
```

### Step 5: Validation Checks

```php
// Validation Rule 1: inline_citations ⊆ global_order
foreach ($report['report'] as $section_name => $section) {
    foreach ($section['inline_citations'] as $cit_id) {
        assert(in_array($cit_id, $report['citations']['global_order']), 
               "Citation {$cit_id} in {$section_name} not in global_order");
    }
}

// Validation Rule 2: Order in text matches inline_citations
// For executive_insight: text has [1], [2], [1], [3] → first appearance order is [1, 2, 3] ✓
// For financial_trajectory: text has [4], [3] → order is [4, 3] ✓

// Validation Rule 3: Sources only contains used citations
$source_ids = array_column($report['citations']['sources'], 'id');
foreach ($source_ids as $id) {
    assert(in_array($id, $report['citations']['global_order']), 
           "Source {$id} not in global_order");
}
assert(!in_array(5, $source_ids), "Unused citation 5 should not be in sources");
```

## Key Implementation Points

### 1. Citation Deduplication
- Same URL used multiple times gets same citation ID
- Citation [1] can appear in multiple sections but only counted once in global_order

### 2. Order Preservation
- global_order reflects first use across entire report, not per section
- If citation [3] appears in section 1 and citation [4] in section 2, but [4] is used first in section 2's text before [3] is used in section 1, then global_order respects document flow

### 3. Maximum Citations per Section
- Each section's inline_citations array is capped at 8 items
- If more than 8 unique citations in text, only first 8 are kept

### 4. Sources List Generation
- Plain text format for final rendering:
```
Sources
[1] "Annual Report 2024", Acme Corp (2024) (company.com/annual-report-2024)
[2] "Market Analysis Q4", News Corp (2024) (news.com/market-analysis)
[3] "Q3 Earnings Call", Investor Relations (2024) (investor.com/earnings-call)
[4] "Strategy Update", Acme Corp (2024) (company.com/strategy-update)
```

## Common Pitfalls to Avoid

1. **Don't renumber citations per section** - Use global IDs consistently
2. **Don't include unused citations in sources** - Filter before output
3. **Don't ignore order of appearance** - Process [n] tokens sequentially
4. **Don't exceed 8 citations per section** - Truncate inline_citations array
5. **Don't create duplicate citation IDs** - Deduplicate by URL