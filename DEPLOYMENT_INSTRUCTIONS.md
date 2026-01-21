# CustomerIntel Deployment Instructions

## Quick Start

### New Installation

1. **Download the plugin**
   ```bash
   wget https://github.com/yourorg/customerintel/releases/download/v1.0.0/customerintel-v1.0.0.zip
   ```

2. **Extract to Moodle directory**
   ```bash
   unzip customerintel-v1.0.0.zip -d /path/to/moodle/local/
   ```

3. **Install via Moodle**
   - Log in as administrator
   - Navigate to Site Administration > Notifications
   - Follow the installation wizard
   - Configure plugin settings

4. **Configure API Keys**
   - Go to Site Administration > Plugins > Local plugins > CustomerIntel
   - Enter at least one API key:
     - OpenAI API Key
     - Claude API Key
     - Local Model Endpoint
   - Or enable Mock Mode for testing

5. **Run pre-deployment check**
   ```bash
   php local/customerintel/cli/pre_deploy_check.php
   ```
   Should output: "✅ OK for Production"

### Upgrading from Previous Version

1. **Backup your data**
   ```bash
   # Backup database
   mysqldump moodle > moodle_backup.sql
   
   # Backup files
   tar -czf moodle_files_backup.tar.gz /path/to/moodledata
   ```

2. **Download and extract update**
   ```bash
   wget https://github.com/yourorg/customerintel/releases/download/v1.0.0/customerintel-v1.0.0.zip
   unzip -o customerintel-v1.0.0.zip -d /path/to/moodle/local/
   ```

3. **Run upgrade**
   - Navigate to Site Administration > Notifications
   - Follow upgrade prompts

4. **Verify upgrade**
   ```bash
   php local/customerintel/cli/check_schema_consistency.php
   ```

## Production Configuration

### 1. Cron Setup

Add to your system crontab:
```bash
# Process background jobs every 5 minutes
*/5 * * * * www-data php /path/to/moodle/local/customerintel/cli/process_queue.php

# Clean old data weekly
0 2 * * 0 www-data php /path/to/moodle/local/customerintel/cli/cleanup.php
```

### 2. Performance Settings

Edit `/path/to/moodle/config.php`:
```php
// Increase memory for CustomerIntel operations
ini_set('memory_limit', '256M');

// Extend execution time for complex analyses
ini_set('max_execution_time', 900);
```

### 3. Security Configuration

```php
// Ensure API keys are encrypted
$CFG->customerintel_encrypt_keys = true;

// Set rate limits
$CFG->customerintel_rate_limit = 100; // requests per hour
```

### 4. Optional Components

#### PDF Export
```bash
# Option 1: TCPDF
composer require tecnickcom/tcpdf

# Option 2: DOMPDF  
composer require dompdf/dompdf
```

#### Email Notifications
Configure in Site Administration > Plugins > Local plugins > CustomerIntel:
- Enable email notifications
- Set notification recipients
- Configure alert thresholds

## File Structure (ZIP Package)

```
customerintel-v1.0.0.zip
├── customerintel/
│   ├── classes/
│   │   ├── clients/
│   │   ├── services/
│   │   └── task/
│   ├── cli/
│   │   ├── pre_deploy_check.php
│   │   ├── test_integration.php
│   │   └── check_schema_consistency.php
│   ├── db/
│   │   ├── install.xml
│   │   ├── upgrade.php
│   │   └── access.php
│   ├── docs/
│   │   ├── TESTING_GUIDE.md
│   │   └── FINAL_VALIDATION_SUMMARY.md
│   ├── lang/
│   │   └── en/
│   │       └── local_customerintel.php
│   ├── styles/
│   │   └── customerintel.css
│   ├── index.php
│   ├── view.php
│   ├── export.php
│   ├── lib.php
│   ├── settings.php
│   ├── version.php
│   ├── README.md
│   └── CHANGELOG.md
```

## Verification Steps

### 1. Installation Verification

```bash
# Check installation
php local/customerintel/cli/pre_deploy_check.php

# Expected output:
# ✅ OK for Production
```

### 2. Functionality Test

```bash
# Run mock test
php local/customerintel/cli/test_integration.php --mode=quick

# Expected: All tests pass
```

### 3. Database Verification

```bash
# Check schema
php local/customerintel/cli/check_schema_consistency.php

# Expected: "✓ Schema is consistent with install.xml"
```

## Troubleshooting

### Common Issues

#### Issue: API keys not working
```bash
# Test API connectivity
php local/customerintel/cli/test_api.php
```

#### Issue: Background jobs not processing
```bash
# Check job queue
php local/customerintel/cli/check_queue.php

# Process manually
php local/customerintel/cli/process_queue.php --force
```

#### Issue: Memory errors
```php
// Increase in config.php
ini_set('memory_limit', '512M');
```

#### Issue: Permission denied
```bash
# Fix permissions
chown -R www-data:www-data /path/to/moodle/local/customerintel
chmod -R 755 /path/to/moodle/local/customerintel
```

## Rollback Procedure

If issues occur after deployment:

1. **Restore database**
   ```bash
   mysql moodle < moodle_backup.sql
   ```

2. **Restore files**
   ```bash
   rm -rf /path/to/moodle/local/customerintel
   tar -xzf customerintel_backup.tar.gz -C /path/to/moodle/local/
   ```

3. **Clear caches**
   ```bash
   php /path/to/moodle/admin/cli/purge_caches.php
   ```

## Post-Deployment

### 1. Monitor Performance

Check metrics after 24 hours:
```bash
php local/customerintel/cli/report_metrics.php
```

### 2. Review Logs

```bash
tail -f /path/to/moodledata/customerintel.log
```

### 3. Set Up Alerts

Configure monitoring for:
- High API usage
- Failed jobs
- Performance degradation

## Support

- **Documentation**: /docs/
- **Issue Tracker**: https://github.com/yourorg/customerintel/issues
- **Community Forum**: https://moodle.org/plugins/local_customerintel

## Requirements Checklist

- [ ] PHP 7.4 or higher
- [ ] Moodle 4.0 or higher
- [ ] 256MB RAM minimum
- [ ] API keys configured
- [ ] Cron job configured
- [ ] SSL certificate (for API calls)
- [ ] Write permissions on data directory

## Final Steps

1. Run pre-deployment check: ✅
2. Configure API keys: ✅
3. Set up cron jobs: ✅
4. Test with mock run: ✅
5. Go live! 🚀

---

**Version**: 1.0.0  
**Release Date**: 2025-01-13  
**Support**: support@yourcompany.com