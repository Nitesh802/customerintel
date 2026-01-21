# NB Mapping Tweaks for Source vs Target Context

## Overview
Enhanced the synthesis engine to ensure proper Source vs Target company context flows through all synthesis steps, with specific handling for academic health systems like Duke Health.

## Key Changes Implemented

### 1. Complete Build Report Pipeline ✅
- **Location**: `synthesis_engine.php:45-83`
- **Features**:
  - Orchestrates full synthesis pipeline: inputs → patterns → bridge → sections → validation → rendering
  - Passes company context through all steps
  - Generates both HTML and JSON outputs
  - Integrates self-check validation

### 2. Enhanced Section Drafting ✅
- **Updated Methods**:
  - `draft_executive_summary()` - Source company named as capability holder
  - `draft_opportunity_blueprints()` - Target company context explicitly mentioned
  - `draft_whats_overlooked()` - Company context aware
  - `draft_convergence_insight()` - Source/Target roles clear

### 3. Context Header in Playbook ✅
- **Location**: `render_playbook_html()` method
- **Format**: "Source: {SourceName} → Target: {TargetName}"
- **Styling**: Bootstrap-styled context panel with purple left border
- **Placement**: Top of Intelligence Playbook

### 4. Target Company Context Generation ✅
- **Method**: `generate_target_context()`
- **Duke Health Specific**:
  - "academic health system cadence"
  - "mixed payer environment" 
  - "research calendar constraints"
- **Generic Contexts**:
  - Healthcare: regulatory compliance requirements
  - Financial: capital efficiency requirements
  - Technology: rapid innovation cycles

### 5. Assumptions Tracking ✅
- **Location**: `compile_json_output()` method
- **Features**:
  - Automatic detection of missing company details
  - Duke Health pattern recognition
  - Default assumptions for academic health systems
  - Stored in `jsoncontent.assumptions` array

### 6. Opportunity Blueprint Enhancement ✅
- **Source Role**: "{SourceName}'s {capability}"
- **Target Role**: "addresses {TargetName}'s {need}, particularly given {context}"
- **Context Integration**: Explicit mention of target's operational environment

## Technical Implementation

### Company Context Flow
```php
// 1. Load companies in get_normalized_inputs()
$inputs = [
    'company_source' => $source_company_object,
    'company_target' => $target_company_object,
    // ... other data
];

// 2. Pass through to section drafting
$sections = $this->draft_sections($patterns, $bridge, $inputs);

// 3. Use in opportunity blueprints
$target_context = $this->generate_target_context($target_company);
$body_parts[] = "addresses {$target_name}'s {$need}, particularly given {$target_context}";
```

### Duke Health Detection Logic
```php
if (stripos($target_company->name, "duke") !== false || 
    stripos($target_company->name, "health") !== false ||
    stripos($target_company->sector, "health") !== false) {
    return "academic health system cadence, mixed payer environment, and research calendar constraints";
}
```

### JSON Output Structure
```json
{
    "context": {
        "source_company": {
            "name": "ViiV Healthcare",
            "role": "capability_holder"
        },
        "target_company": {
            "name": "Duke Health", 
            "role": "beneficiary_or_risk_holder"
        }
    },
    "assumptions": [
        "Academic health system cadence (quarterly research reviews, academic calendar constraints)",
        "Mixed payer environment (commercial, Medicaid, Medicare, self-pay)",
        "Research calendars align with fiscal year planning cycles"
    ]
}
```

## ViiV → Duke Health Example

### Context Header
```html
<div class="playbook-context">
    <strong>Context:</strong> Source: ViiV Healthcare → Target: Duke Health
</div>
```

### Executive Summary
"ViiV Healthcare faces operational pressures... For Duke Health, given academic health system cadence, mixed payer environment, and research calendar constraints, this creates strategic alignment opportunities..."

### Opportunity Blueprint
"ViiV Healthcare's pharmaceutical capabilities addresses Duke Health's operational efficiency gains, particularly given academic health system cadence, mixed payer environment, and research calendar constraints. The Q4 2024 timing window creates 25% advantage potential..."

### Assumptions in JSON
- Academic health system cadence (quarterly research reviews, academic calendar constraints)
- Mixed payer environment (commercial, Medicaid, Medicare, self-pay)  
- Research calendars align with fiscal year planning cycles

## Testing Coverage

### Test File: `nb_mapping_context_test.php`
- ViiV → Duke Health scenario verification
- Context header generation
- Assumptions tracking
- Company role assignments
- Target context detection

### Key Assertions
- Source company role: "capability_holder"
- Target company role: "beneficiary_or_risk_holder"
- Duke Health context includes academic health system characteristics
- Context header properly formatted
- Assumptions automatically generated

## Benefits

1. **Clear Role Distinction**: Source companies are capability holders, targets are beneficiaries
2. **Contextual Accuracy**: Duke Health scenarios include relevant academic health system details
3. **Assumption Transparency**: Missing details are explicitly noted and reasonable defaults provided
4. **Visual Context**: Users immediately understand the Source → Target relationship
5. **Consistent Messaging**: All sections reference companies in their proper roles

## Future Enhancements

1. **Sector-Specific Templates**: More nuanced context for different industries
2. **Company Size Detection**: Different messaging for enterprise vs. SMB targets
3. **Geographic Context**: Regional considerations for multinational scenarios
4. **Competitive Landscape**: How Source/Target relationship fits broader market dynamics
5. **Integration Timeline**: More sophisticated timing recommendations based on target characteristics