# QA Visibility and Troubleshooting Implementation

## Overview
Enhanced the report view with comprehensive QA details and admin debugging capabilities to give reviewers complete visibility into synthesis quality and enable easy troubleshooting.

## Features Implemented

### 1. View QA Details Collapsible Section ✅
- **Location**: `view_report.php:304-418`
- **Trigger**: Collapsible Bootstrap panel with chevron icon
- **Layout**: Three-column layout for organized information display
- **Behavior**: Smooth expand/collapse with icon rotation

### 2. Voice Report Display ✅
- **Column**: Left column of QA Details
- **Features**:
  - Pass/Fail badges for each voice check
  - Green (PASS) / Red (FAIL) color coding
  - Summary counter: "X/Y checks passed"
  - Individual check names (tone_check, clarity_check, etc.)
  - Graceful handling when voice report unavailable

**Example Display**:
```
Voice & Style Checks
✅ PASS  Tone Check
❌ FAIL  Clarity Check  
✅ PASS  Length Check
2/3 checks passed
```

### 3. Self-Check Violations Grouped by Category ✅
- **Column**: Middle column of QA Details
- **Features**:
  - Violations grouped by rule type (execution_leak, consultant_speak, etc.)
  - Violation count per category
  - Severity badges (ERROR/WARN) with appropriate colors
  - Location information (which section has the violation)
  - Message excerpts (truncated to 100 characters)
  - "No violations found" when clean

**Example Display**:
```
Self-Check Violations
Execution Leak (2)
  🔴 ERROR opportunities
    Found execution detail: "email" in opportunities
  🔴 ERROR convergence  
    Found execution detail: "schedule" in convergence

Consultant Speak (1)
  🟡 WARN executive_summary
    Found consultant-speak: "synergy" in executive_summary
```

### 4. Enriched Citations with Titles/Domains ✅
- **Column**: Right column of QA Details
- **Features**:
  - Numbered citation list [1], [2], etc.
  - Citation titles or "Untitled Source" fallback
  - Domain extraction from URLs
  - Fallback domain parsing when domain not provided
  - Citation count tracking

**Example Display**:
```
Enriched Citations
[1] Duke Health System Performance Report
    dukehealth.org
[2] Academic Health System Benchmarks  
    aamc.org
[3] Untitled Source
    Unknown domain
```

### 5. Admin-Only Debug Toggle ✅
- **Visibility**: Only shown to users with `local/customerintel:manage` capability
- **Badge**: Yellow "ADMIN" badge to indicate restricted access
- **Location**: Separate collapsible section below QA Details
- **Purpose**: Technical troubleshooting for synthesis engineers

### 6. Compact Tree View of Patterns and Bridge Items ✅
- **Layout**: Two-column layout (Patterns | Bridge Items)
- **Patterns Display**:
  - Pressure themes with source NB identification
  - Text excerpts (first 50 characters)
  - Hierarchical tree structure with bullet points
- **Bridge Items Display**:
  - Target relevance mapping
  - Timing synchronization details
  - Evidence support counts
- **Fallback**: Mock data shown when methods not implemented yet

**Example Debug Display**:
```
Patterns Detected
• Executive Pressure
  - Source: NB1
  - Text: Board expectations for Q4 performance...
• Financial Health  
  - Source: NB3
  - Text: Margin pressures from competitive...

Bridge Items
• Bridge Item 1
  - Relevance: Operational efficiency gains
  - Timing: Q4 2024 alignment
  - Evidence: 2 items
```

## Technical Implementation

### Data Flow
```php
// Parse synthesis reports
$voice_report_data = json_decode($synthesis->voice_report, true);
$selfcheck_data = json_decode($synthesis->selfcheck_report, true);
$json_data = json_decode($synthesis->jsoncontent, true);

// Group violations by rule
$violations_by_rule = [];
foreach ($selfcheck_data['violations'] as $violation) {
    $rule = $violation['rule'] ?? 'unknown';
    $violations_by_rule[$rule][] = $violation;
}
```

### Permission Checking
```php
// Admin-only debug section
if ($has_synthesis && has_capability('local/customerintel:manage', $context)) {
    // Show debug information
}
```

### Error Handling
```php
try {
    $patterns = $synthesis_engine->detect_patterns($debug_inputs);
    // Display real patterns
} catch (Exception $e) {
    // Fallback to mock data with clear indication
    echo "[Mock data - patterns not implemented yet]\n";
}
```

### UI Interactions
```javascript
// Collapsible behavior with icon rotation
$("#qaDetails").on("show.bs.collapse", function() {
    $("#qaDetailsIcon").removeClass("fa-chevron-right").addClass("fa-chevron-down");
});
```

## UI Design

### Color Coding
- **Pass/Success**: Bootstrap `badge-success` (green)
- **Fail/Error**: Bootstrap `badge-danger` (red)  
- **Warning**: Bootstrap `badge-warning` (yellow)
- **Admin Badge**: Bootstrap `badge-warning` (yellow)

### Layout Structure
```html
<div class="card mt-3">
  <div class="card-header">
    <button data-toggle="collapse" data-target="#qaDetails">
      <i class="fa fa-chevron-right"></i> View QA Details
    </button>
  </div>
  <div id="qaDetails" class="collapse">
    <div class="row">
      <div class="col-md-4">Voice & Style Checks</div>
      <div class="col-md-4">Self-Check Violations</div>  
      <div class="col-md-4">Enriched Citations</div>
    </div>
  </div>
</div>
```

## Testing Coverage

### Test File: `qa_visibility_test.php`
- **QA Details Structure**: Validates data parsing and grouping
- **Violation Severity**: Tests badge color mapping
- **Citation Domains**: Verifies URL domain extraction
- **Admin Capabilities**: Confirms permission checking
- **Debug Tree Format**: Validates data structure display
- **Error Handling**: Tests graceful degradation

### Key Test Cases
- Voice report with mixed pass/fail results
- Violations grouped by rule with multiple severities
- Citations with and without explicit domains
- Admin capability detection
- Exception handling in debug section

## Benefits for Reviewers

### 1. **Complete Quality Visibility**
- Voice & style enforcement results at a glance
- All self-check violations organized by category
- Citation quality assessment with source verification

### 2. **Easy Troubleshooting**
- Violations grouped by type for targeted fixes
- Location information for quick navigation
- Message excerpts provide context without overwhelming detail

### 3. **Citation Verification**
- All sources listed with titles and domains
- Easy verification of citation quality and diversity
- Quick identification of missing or poor citations

### 4. **Admin Debugging**
- Deep visibility into synthesis pipeline for technical users
- Pattern and bridge item inspection for algorithm tuning
- Input validation for troubleshooting synthesis issues

## Future Enhancements

### 1. **Interactive Debugging**
- Click to expand full violation messages
- Direct links to problematic sections
- Edit-in-place for quick fixes

### 2. **Quality Metrics**
- Quality scores and trends over time
- Comparison with previous runs
- Automated quality improvement suggestions

### 3. **Citation Enhancement**  
- Citation preview on hover
- Automatic broken link detection
- Source credibility scoring

### 4. **Export Capabilities**
- Export QA report as PDF
- Quality dashboard for multiple runs
- Integration with external QA tools

## Usage Instructions

### For Reviewers
1. **View Synthesis**: Standard playbook view shows QA summary
2. **Expand QA Details**: Click "View QA Details" to see comprehensive quality information
3. **Review Voice Checks**: Check left column for style compliance
4. **Examine Violations**: Middle column shows all quality issues grouped by type
5. **Verify Citations**: Right column lists all sources with domains

### For Admins
1. **Enable Debug Mode**: Ensure user has `local/customerintel:manage` capability
2. **Access Debug Data**: Click "Show Normalized Inputs" with ADMIN badge
3. **Review Patterns**: Examine detected pressure themes and patterns
4. **Inspect Bridge**: Verify target relevance mapping and timing
5. **Troubleshoot**: Use input summary to identify data quality issues

The implementation provides comprehensive visibility into synthesis quality while maintaining a clean, organized interface that doesn't overwhelm users with technical details unless explicitly requested.