# CustomerIntel v1.0.2 - Configuration Requirements

## Plugin Configuration Overview

CustomerIntel requires comprehensive configuration across multiple settings categories to ensure optimal performance and functionality.

## Core API Configuration

### OpenAI Settings
- **API Key**: Required for GPT-4 synthesis operations
- **Model**: `gpt-4-turbo-preview` (recommended) or `gpt-4`
- **Max Tokens**: `4000` (default, adjust based on needs)
- **Temperature**: `0.3` (for consistent, focused output)
- **Timeout**: `120` seconds (API request timeout)

### Perplexity Settings  
- **API Key**: Required for web research and fact-checking
- **Model**: `pplx-7b-online` (default)
- **Max Tokens**: `2000`
- **Temperature**: `0.2` (for factual accuracy)

## Synthesis Engine Configuration

### Processing Settings
- **Max Concurrent Jobs**: `3` (adjust based on server capacity)
- **Job Timeout**: `3600` seconds (1 hour)
- **Retry Attempts**: `3`
- **Backoff Strategy**: `exponential`

### Quality Control
- **Enable Self-Check**: `true` (recommended)
- **Validation Level**: `strict` | `moderate` | `relaxed`
- **Citation Enrichment**: `true`
- **Voice Enforcement**: `true`

### Cost Management
- **Cost Threshold Per Analysis**: `$10.00` (USD)
- **Daily Cost Limit**: `$100.00` (USD)
- **Enable Cost Alerts**: `true`
- **Alert Threshold**: `80%` of limits

## Database Configuration

### Connection Settings
- **Engine**: MySQL 5.7+ or PostgreSQL 11+
- **Character Set**: `utf8mb4_unicode_ci` (MySQL)
- **Connection Pool**: `10` connections minimum
- **Query Timeout**: `60` seconds

### Performance Settings
- **Query Cache**: Enabled
- **Index Usage**: Optimized for synthesis queries
- **Connection Timeout**: `30` seconds
- **Memory Allocation**: `512MB+` for large result sets

## Notebook Configuration

### Schema Validation
Each of the 15 notebooks requires proper JSON schema configuration:

#### NB1-5: Foundation Analysis
- **Company Fundamentals** (nb1): Basic company data
- **Financial Performance** (nb2): Revenue/growth metrics  
- **Market Position** (nb3): Competitive landscape
- **Technology Stack** (nb4): Technical capabilities
- **Leadership Analysis** (nb5): Management assessment

#### NB6-10: Strategic Analysis
- **Partnership Ecosystem** (nb6): Strategic alliances
- **Product Portfolio** (nb7): Offering analysis
- **Customer Base** (nb8): Client demographics
- **Competitive Intelligence** (nb9): Market competition
- **Growth Opportunities** (nb10): Expansion potential

#### NB11-15: Advanced Intelligence
- **Risk Assessment** (nb11): Business risks
- **Digital Presence** (nb12): Online footprint
- **Innovation Pipeline** (nb13): R&D activities
- **Operational Excellence** (nb14): Process efficiency
- **Strategic Synthesis** (nb15): Final recommendations

### Notebook Processing
- **Sequential Processing**: Notebooks 1-14, then synthesis (15)
- **Parallel Options**: NB1-4 can run concurrently
- **Dependencies**: NB15 requires all others complete
- **Validation**: Each notebook validates against JSON schema

## User Access Configuration

### Capability Requirements
```php
// View access - basic report viewing
'local/customerintel:view'

// Management access - full admin capabilities  
'local/customerintel:manage'

// Edit access - modify companies/sources
'local/customerintel:edit'
```

### Role Assignments
- **Site Administrator**: All capabilities
- **Manager**: view + edit capabilities
- **User**: view capability only

## Cron & Scheduling Configuration

### Required Cron Jobs
```bash
# Main Moodle cron (includes plugin tasks)
*/5 * * * * /usr/bin/php /path/to/moodle/admin/cli/cron.php

# Optional: Dedicated synthesis processing
*/10 * * * * /usr/bin/php /path/to/moodle/local/local_customerintel/cli/process_queue.php
```

### Task Scheduling
- **Job Queue Processing**: Every 5 minutes
- **Cost Tracking Update**: Every hour
- **Telemetry Collection**: Every 30 minutes
- **Cache Cleanup**: Daily at 2 AM

## Security Configuration

### API Key Management
- Store keys in Moodle's encrypted config system
- Rotate keys quarterly
- Use environment variables where possible
- Implement key validation on save

### Access Control
- Enforce capability checks on all operations
- Log all administrative actions
- Implement session timeouts
- Use HTTPS for all API communications

### Data Protection
- Encrypt sensitive data at rest
- Implement data retention policies
- Provide GDPR compliance tools
- Secure export functionality

## Performance Configuration

### Memory Settings
```ini
; PHP configuration
memory_limit = 1024M
max_execution_time = 3600
upload_max_filesize = 50M
post_max_size = 50M
```

### Cache Configuration
- **Moodle Cache (MUC)**: Enabled for plugin data
- **Database Query Cache**: Enabled
- **Application Cache**: Redis recommended
- **Session Storage**: Database or Redis

### Optimization Settings
- **OPCache**: Enabled for PHP acceleration
- **Gzip Compression**: Enabled for API responses
- **Connection Pooling**: For high-volume usage
- **Lazy Loading**: For large datasets

## Monitoring Configuration

### Logging Levels
- **Debug**: Verbose logging for development
- **Info**: Standard operational logging
- **Warning**: Important issues requiring attention
- **Error**: Critical failures requiring immediate action

### Telemetry Collection
- **Usage Analytics**: Track synthesis operations
- **Performance Metrics**: API response times
- **Cost Analytics**: API usage and expenditure
- **Error Tracking**: Failed operations and causes

### Alerting Configuration
- **Cost Alerts**: Email when thresholds exceeded
- **Error Alerts**: Immediate notification of failures
- **Performance Alerts**: When operations exceed SLA
- **Capacity Alerts**: When approaching resource limits

## Environment-Specific Settings

### Development Environment
```php
// Development-specific settings
'debug_mode' => true,
'cost_limits_enabled' => false,
'test_mode' => true,
'mock_api_responses' => true
```

### Production Environment
```php
// Production-specific settings
'debug_mode' => false,
'cost_limits_enabled' => true,
'test_mode' => false,
'mock_api_responses' => false,
'strict_validation' => true
```

### Staging Environment
```php
// Staging-specific settings
'debug_mode' => true,
'cost_limits_enabled' => true,
'test_mode' => false,
'reduced_api_calls' => true
```

## Integration Configuration

### Moodle Integration
- **Theme Compatibility**: Works with all standard themes
- **Block Integration**: Dashboard widgets available
- **Navigation**: Integrated into admin menu
- **Permissions**: Uses Moodle capability system

### External Integrations
- **Webhook Support**: For external notifications
- **API Endpoints**: RESTful API for integrations
- **Export Formats**: HTML, PDF, JSON
- **Import Sources**: CSV, API, manual entry

## Backup & Recovery Configuration

### Backup Strategy
- **Database**: Include all plugin tables
- **Files**: Plugin directory and generated reports
- **Configuration**: Export plugin settings
- **Schedules**: Daily incremental, weekly full

### Recovery Procedures
- **Data Restoration**: From database backups
- **Configuration Reset**: Via CLI tools
- **Emergency Procedures**: Manual intervention guides
- **Validation**: Post-recovery testing

## Maintenance Configuration

### Regular Maintenance
- **Log Rotation**: Weekly cleanup of old logs
- **Cache Clearing**: Monthly comprehensive cache clear
- **Database Optimization**: Monthly table optimization
- **Configuration Audit**: Quarterly settings review

### Health Checks
- **API Connectivity**: Daily automated tests
- **Database Performance**: Weekly analysis
- **System Resources**: Continuous monitoring
- **Error Rates**: Real-time tracking

---

**Configuration Summary**

For optimal performance, ensure:
1. All API keys are properly configured and tested
2. Database is optimized with appropriate indexes
3. Cron jobs are running every 5 minutes
4. Memory limits are set to 1GB+ for synthesis operations
5. Monitoring and alerting are properly configured
6. Security settings follow best practices

Refer to the deployment guide for step-by-step configuration instructions.