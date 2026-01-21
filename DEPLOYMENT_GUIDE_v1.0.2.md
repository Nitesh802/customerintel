# CustomerIntel Synthesis v1.0.2 - Deployment Guide

## Overview
CustomerIntel Synthesis is a comprehensive Moodle plugin that provides automated customer intelligence analysis and report generation using AI-powered synthesis engines.

## Package Contents
- **customerintel-synthesis-v1.0.2.zip** - Complete plugin package
- Database schema with 15 optimized tables
- Synthesis engine with 15 specialized AI notebooks
- 40+ service classes for orchestration, validation, and processing
- Comprehensive test suite (25+ test files)
- CLI utilities for maintenance and diagnostics

## Prerequisites

### System Requirements
- **Moodle**: 3.9+ (tested up to 4.1)
- **PHP**: 7.4+ (8.0+ recommended)
- **Database**: MySQL 5.7+ or PostgreSQL 11+
- **Memory**: 512MB+ (1GB+ recommended for synthesis operations)
- **Storage**: 100MB+ for plugin files, additional space for reports/logs

### API Requirements
- **OpenAI API Key** - For GPT-4 synthesis operations
- **Perplexity API Key** - For web research and fact-checking
- Valid API quotas for both services

### PHP Extensions
- `curl` - For API communications
- `json` - For data processing
- `mbstring` - For text handling
- `zip` - For export functionality

## Installation Steps

### 1. Extract Plugin Files
```bash
cd /path/to/moodle/local/
unzip customerintel-synthesis-v1.0.2.zip
```

### 2. Set File Permissions
```bash
chmod -R 755 local_customerintel/
chown -R www-data:www-data local_customerintel/
```

### 3. Database Installation
Navigate to Moodle admin interface:
1. Go to **Site administration** → **Notifications**
2. Follow upgrade prompts to install database schema
3. Verify all 15 tables are created successfully

### 4. Configure Plugin Settings
Go to **Site administration** → **Plugins** → **Local plugins** → **Customer Intelligence**:

#### API Configuration
- **OpenAI API Key**: Your OpenAI API key
- **Perplexity API Key**: Your Perplexity API key
- **Default Model**: `gpt-4-turbo-preview` (recommended)
- **Max Tokens**: `4000` (default)
- **Temperature**: `0.3` (for consistency)

#### Synthesis Settings
- **Enable Auto Synthesis**: Yes/No
- **Max Concurrent Jobs**: `3` (adjust based on server capacity)
- **Job Timeout**: `3600` seconds (1 hour)
- **Cost Threshold**: `$10.00` (per analysis)

#### Quality Settings
- **Enable Self-Check**: Yes (recommended)
- **Validation Level**: `strict`
- **Citation Enrichment**: Yes

### 5. Verify Installation
Run the pre-deployment check:
```bash
cd /path/to/moodle/local/local_customerintel/cli/
php pre_deploy_check.php
```

## Post-Installation Configuration

### 1. Create Initial Data
Access the plugin at `/local/customerintel/` and:
1. Add your first company via **Companies** section
2. Configure source types in **Sources** section
3. Test with a small analysis run

### 2. User Permissions
Assign appropriate capabilities:
- `local/customerintel:view` - View reports
- `local/customerintel:manage` - Full admin access
- `local/customerintel:edit` - Edit companies/sources

### 3. Scheduled Tasks
Enable Moodle cron for automated processing:
```bash
# Add to crontab for regular job processing
*/5 * * * * /usr/bin/php /path/to/moodle/admin/cli/cron.php
```

## Database Schema Summary

The plugin creates 15 optimized tables:

### Core Tables
- `local_ci_company` - Company records
- `local_ci_source` - Source definitions  
- `local_ci_run` - Analysis run tracking
- `local_ci_run_sources` - Run-source associations

### Processing Tables
- `local_ci_nb_result` - Notebook results storage
- `local_ci_synthesis` - Synthesis data
- `local_ci_job_queue` - Async job management
- `local_ci_cost_tracking` - Cost monitoring

### Quality & Versioning
- `local_ci_validation` - Quality validation results
- `local_ci_snapshots` - Version snapshots
- `local_ci_citations` - Citation management
- `local_ci_logs` - Activity logging

### Configuration & Telemetry
- `local_ci_settings` - Plugin configuration
- `local_ci_telemetry` - Usage analytics
- `local_ci_orchestrator_state` - Process state

## Synthesis Engine Architecture

### 15 Specialized Notebooks
1. **Company Fundamentals** (nb1) - Basic company analysis
2. **Financial Performance** (nb2) - Revenue and growth metrics
3. **Market Position** (nb3) - Competitive landscape
4. **Technology Stack** (nb4) - Technical capabilities
5. **Leadership Analysis** (nb5) - Management assessment
6. **Partnership Ecosystem** (nb6) - Strategic alliances
7. **Product Portfolio** (nb7) - Offering analysis
8. **Customer Base** (nb8) - Client demographics
9. **Competitive Intelligence** (nb9) - Market competition
10. **Growth Opportunities** (nb10) - Expansion potential
11. **Risk Assessment** (nb11) - Business risks
12. **Digital Presence** (nb12) - Online footprint
13. **Innovation Pipeline** (nb13) - R&D activities
14. **Operational Excellence** (nb14) - Process efficiency
15. **Strategic Synthesis** (nb15) - Final recommendations

### Quality Assurance
- **Self-Check Validator** - Automated quality validation
- **Citation Resolver** - Source verification
- **Voice Enforcer** - Consistent tone and style
- **Cost Monitor** - API usage tracking

## Maintenance & Monitoring

### CLI Tools
Located in `/local_customerintel/cli/`:

- `pre_deploy_check.php` - Pre-deployment validation
- `status_report.php` - System status overview
- `test_api_keys.php` - API connectivity test
- `rebuild_cache.php` - Cache management
- `inspect_table.php` - Database inspection

### Logging
Comprehensive logging system tracks:
- Synthesis operations
- API usage and costs
- Quality validation results
- System errors and warnings

Access logs via `/local/customerintel/logs.php`

### Performance Monitoring
- **Telemetry Service** - Usage analytics
- **Cost Service** - API expenditure tracking
- **Job Queue** - Async processing status

## Troubleshooting

### Common Issues

#### 1. API Connection Failures
```bash
php cli/test_api_keys.php
```
Check API keys and network connectivity.

#### 2. Database Schema Issues
```bash
php cli/check_schema_consistency.php
```
Verify table structure and constraints.

#### 3. Memory Issues
Increase PHP memory limit:
```php
ini_set('memory_limit', '1G');
```

#### 4. Timeout Issues
Adjust job timeout in plugin settings or:
```php
ini_set('max_execution_time', 3600);
```

### Debug Mode
Enable debug logging in plugin settings:
- Set **Debug Level** to `verbose`
- Check `/local/customerintel/logs.php` for detailed output

## Security Considerations

### API Key Security
- Store API keys in Moodle's encrypted settings
- Use environment variables where possible
- Rotate keys regularly

### Access Control
- Implement proper capability checks
- Restrict access to sensitive operations
- Monitor user activity logs

### Data Privacy
- Configure data retention policies
- Implement GDPR compliance measures
- Secure export functionality

## Support & Documentation

### Additional Resources
- Plugin source code includes comprehensive PHPDoc comments
- Test files demonstrate usage patterns
- CLI tools provide operational utilities

### Version Information
- **Plugin Version**: 1.0.2
- **Moodle Compatibility**: 3.9+
- **Release Date**: 2024
- **Maintainer**: Fused / Rubi Platform

## Upgrade Instructions

When upgrading from previous versions:

1. **Backup Database** - Always backup before upgrading
2. **Extract New Files** - Replace plugin directory
3. **Run Upgrade** - Visit Moodle notifications page
4. **Test Functionality** - Verify all features work
5. **Update Configuration** - Check for new settings

## Performance Optimization

### Recommended Settings
- **PHP OPCache**: Enabled
- **Database Query Cache**: Enabled  
- **Moodle Cache**: MUC configured
- **Background Processing**: Cron every 5 minutes

### Scaling Considerations
- Increase `max_concurrent_jobs` for higher throughput
- Monitor API rate limits
- Consider database connection pooling
- Implement Redis for session storage

---

**Installation Complete!** 

Access your Customer Intelligence dashboard at: `/local/customerintel/`

For technical support, please refer to the comprehensive test suite and CLI diagnostics included in the package.