# Synthesis Engine Deployment Guide

## Overview
This guide covers the deployment of the Target-Aware Synthesis Engine for the Customer Intelligence platform. The synthesis engine automatically generates "Intelligence Playbooks" from NB analysis results.

## Core Components Verification

### ✅ Database Schema
- **local_ci_synthesis** table exists in `db/install.xml` (lines 250-270)
- Supports HTML content, JSON content, voice reports, and self-check reports
- Proper indexes and foreign key relationships to runs table

### ✅ Core Services
- **synthesis_engine.php** - Main synthesis orchestration service
- **selfcheck_validator.php** - Quality assurance validation service  
- **voice_enforcer.php** - Voice and style enforcement service
- **citation_resolver.php** - Citation enrichment service

### ✅ UI Integration
- **view_report.php** - Auto-generates synthesis on view with admin toggle
- **export.php** - Supports synthesis JSON export format
- **reports.php** - Lists reports with synthesis status
- Templates and CSS for synthesis display

## File Structure

### Core Synthesis Files
```
local_customerintel/
├── classes/services/
│   ├── synthesis_engine.php        # Main synthesis orchestrator
│   ├── selfcheck_validator.php     # QA validation service  
│   ├── voice_enforcer.php          # Voice & style checks
│   └── citation_resolver.php       # Citation enrichment
├── db/
│   └── install.xml                 # Database schema with synthesis table
├── view_report.php                 # UI with auto-synthesis generation
├── export.php                     # Export with synthesis JSON support
└── styles/
    └── report-export.css           # Synthesis display styling
```

### Supporting Infrastructure
```
├── classes/services/
│   ├── assembler.php              # Report assembly (fallback)
│   ├── nb_orchestrator.php        # NB result processing
│   ├── log_service.php            # Logging infrastructure
│   └── telemetry_service.php      # Performance metrics
├── tests/
│   ├── auto_synthesis_test.php    # Auto-synthesis testing
│   └── synthesis_persist_test.php # Persistence testing
└── templates/
    └── reports.mustache           # UI templates
```

## Configuration Requirements

### 1. Admin Settings
The synthesis engine requires these admin configuration options:

```php
// Auto-synthesis on report view (default: enabled)
set_config('auto_synthesis_on_view', 1, 'local_customerintel');

// Synthesis service provider (future: multiple providers)
set_config('synthesis_provider', 'openai', 'local_customerintel');

// Voice enforcement strictness
set_config('voice_enforcement_level', 'standard', 'local_customerintel');
```

### 2. Database Prerequisites
- Moodle database with proper permissions
- `local_ci_synthesis` table from install.xml
- Existing CustomerIntel core tables (company, run, nb_result, etc.)

### 3. API Dependencies
- OpenAI API access for synthesis generation
- Perplexity API for source enrichment (optional)
- LLM client configuration in admin settings

## Deployment Steps

### Step 1: Database Update
```bash
# Apply database schema updates
php admin/cli/upgrade.php --non-interactive
```

### Step 2: Verify Core Services
```bash
# Test synthesis engine availability
php local/customerintel/cli/test_integration.php --component=synthesis

# Validate database schema
php local/customerintel/cli/check_schema_consistency.php
```

### Step 3: Configure Admin Settings
1. Navigate to Site Administration → Plugins → Local plugins → Customer Intelligence
2. Enable auto-synthesis: `auto_synthesis_on_view = 1`
3. Configure API credentials for synthesis providers
4. Set voice enforcement level (standard/strict/disabled)

### Step 4: Test Synthesis Generation
```bash
# Run synthesis test
php local/customerintel/tests/auto_synthesis_test.php

# Test with actual run data
php local/customerintel/cli/manual_check.php --runid=123 --synthesis
```

## Key Features

### Auto-Generation on View
- Synthesis automatically generates when viewing completed reports
- Triggered by `view_report.php` if synthesis is missing or outdated
- Graceful fallback to raw NB results if synthesis fails

### Quality Assurance
- **Voice Enforcer**: Ensures consistent professional tone
- **Self-Check Validator**: Validates against quality rules:
  - No execution detail leakage
  - No consultant-speak
  - No unsupported claims
  - Proper citation enrichment
  - No repetition across opportunities

### Export Capabilities
- HTML with embedded CSS for standalone reports
- JSON export of synthesis data
- Markdown format for documentation
- Raw NB data export as fallback

### Admin Controls
- Toggle between synthesis and raw NB views
- QA summary with pass/fail status
- Violation details with suggested rewrites
- Performance metrics (tokens, cost, duration)

## Validation Checklist

### Pre-Deployment
- [ ] Database schema includes `local_ci_synthesis` table
- [ ] All synthesis service files present and readable
- [ ] Admin configuration options available
- [ ] API credentials configured

### Post-Deployment  
- [ ] Auto-synthesis generates on report view
- [ ] QA validation shows pass/fail status
- [ ] Export functions include synthesis formats
- [ ] Performance metrics are captured
- [ ] Fallback to raw NB results works

### User Experience
- [ ] Reports display synthesis playbooks by default
- [ ] Toggle between synthesis and raw results works
- [ ] QA details are collapsible and informative
- [ ] Export downloads work for all formats

## Troubleshooting

### Common Issues

**Synthesis not generating:**
- Check API credentials in admin settings
- Verify `auto_synthesis_on_view` is enabled
- Check logs for specific error messages

**Database errors:**
- Ensure schema upgrade completed successfully
- Check table permissions for synthesis table
- Validate foreign key relationships

**Performance issues:**
- Monitor token usage and costs
- Consider disabling auto-synthesis for large reports
- Use manual generation for testing

### Debug Commands
```bash
# Check synthesis engine status
php local/customerintel/cli/status_report.php --component=synthesis

# Manual synthesis generation
php local/customerintel/cli/manual_check.php --runid=X --synthesis --debug

# Validate specific run
php local/customerintel/tests/synthesis_persist_test.php --runid=X
```

## Security Considerations

- Synthesis content is sanitized before HTML output
- Citations are validated and enriched safely
- Admin-only access to debug information
- API keys are stored securely in Moodle config

## Performance Impact

- Synthesis generation adds ~30-60 seconds to report viewing
- Token costs: ~2000-5000 tokens per synthesis
- Database storage: ~50-200KB per synthesis record
- Auto-generation can be disabled for performance-critical deployments

## Rollback Plan

If issues occur, synthesis can be disabled without affecting core functionality:

1. Set `auto_synthesis_on_view = 0` in admin settings
2. Reports will automatically fall back to raw NB display
3. Existing synthesis data remains in database for future use
4. Export functionality continues with raw NB data

## Support

For technical issues:
1. Check Moodle logs for synthesis-related errors
2. Review `local_ci_log` table for run-specific issues
3. Use CLI debugging tools for detailed analysis
4. Fallback to raw NB display preserves core functionality