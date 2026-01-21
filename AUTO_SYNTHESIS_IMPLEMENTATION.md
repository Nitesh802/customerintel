# Auto-Synthesis on View Implementation

## Overview
Implemented on-demand synthesis generation when viewing reports, with admin control and proper fallback handling.

## Features Implemented

### 1. Admin Setting
- **Location**: `settings.php:61-67`, `classes/forms/admin_settings_form.php:211-215`
- **Setting**: `auto_synthesis_on_view` (checkbox, default: enabled)
- **Description**: "Generate synthesis automatically on view"
- **Help Text**: "Automatically generate synthesis when viewing reports if no synthesis exists or if the run is newer than existing synthesis."

### 2. Synthesis Freshness Detection
- **Location**: `view_report.php:55-99`
- **Logic**:
  - No synthesis exists → needs synthesis
  - Run completion time > synthesis update time → needs synthesis
  - Otherwise → synthesis is current

### 3. On-Demand Generation
- **Trigger**: When viewing a report and synthesis is needed
- **Process**:
  1. Check admin setting (`auto_synthesis_on_view`)
  2. If enabled and synthesis needed, call `synthesis_engine->build_report($runid)`
  3. Persist results using `synthesis_engine->persist($runid, $bundle)`
  4. Reload synthesis data
  5. Log success

### 4. Exception Handling & Fallback
- **Location**: `view_report.php:91-98`
- **Behavior**:
  - Catch all synthesis generation exceptions
  - Log warning with error details
  - Continue with normal report rendering (raw NB results)
  - No user disruption

### 5. User Feedback
- **Location**: `view_report.php:199-207`
- **Feature**: Success notification when synthesis is auto-generated
- **Style**: Bootstrap alert with dismiss functionality

## Database Integration

### Synthesis Freshness Check
```php
// Check if synthesis needs regeneration
if (!$synthesis || empty($synthesis->htmlcontent)) {
    $needs_synthesis = true;
} else if ($run->timecompleted && $synthesis->updatedat && $run->timecompleted > $synthesis->updatedat) {
    $needs_synthesis = true;
}
```

### Admin Setting Check
```php
$auto_synthesis_enabled = get_config('local_customerintel', 'auto_synthesis_on_view') ?? 1;
```

## Configuration

### Admin Settings
1. Go to Site Administration → Plugins → Local Plugins → Customer Intelligence Dashboard
2. Enable/disable "Generate synthesis automatically on view"
3. Default: Enabled

### Language Strings
- `autosynthesisonview`: Setting label
- `autosynthesisonview_help`: Setting description

## Testing

### Test Coverage
- **File**: `tests/auto_synthesis_test.php`
- **Tests**:
  - Synthesis freshness detection logic
  - Admin setting control
  - Complete generation flow

### Manual Testing
1. Create a completed run without synthesis
2. View the report → synthesis should auto-generate
3. Disable the admin setting
4. View another report → no auto-generation
5. Complete a new run with existing synthesis → synthesis should regenerate

## Error Handling

### Graceful Degradation
- If synthesis generation fails, displays warning in logs
- Falls back to existing report rendering (raw NB results)
- User experience is not disrupted

### Logging
- Success: `Synthesis automatically generated on view for run {$runid}`
- Failure: `Failed to auto-generate synthesis on view: {error_message}`

## Integration Points

### Files Modified
1. `view_report.php` - Main logic
2. `settings.php` - Admin setting registration
3. `classes/forms/admin_settings_form.php` - Form element
4. `lang/en/local_customerintel.php` - Language strings

### Dependencies
- `synthesis_engine::build_report()` method (gracefully handles not-implemented)
- `synthesis_engine::persist()` method (implemented)
- `log_service` for logging
- Standard Moodle config system

## Benefits

1. **Seamless User Experience**: Reports always show synthesis when possible
2. **Admin Control**: Can be disabled if needed
3. **Automatic Updates**: Regenerates when runs are newer than synthesis
4. **Robust Fallback**: Never breaks the view even if synthesis fails
5. **Transparent Operation**: Clear logging and user feedback

## Future Enhancements

1. Progress indicators for synthesis generation
2. Background processing for large reports
3. Configurable synthesis cache duration
4. Synthesis quality indicators in UI