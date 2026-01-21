# Deployment Instructions - v1.0.2 Update

## Version Information
- **Previous Version:** v1.0.1
- **New Version:** v1.0.2
- **Changes:** UI fixes, Company Management feature, admin settings fixes

## Files to Update via FTP

### Modified Files
Upload these files to their respective directories:

```
/local/customerintel/version.php                    [UPDATED - Version bumped to 2025101402]
/local/customerintel/db/upgrade.php                 [UPDATED - Added v1.0.2 upgrade step]
/local/customerintel/admin_settings.php             [MODIFIED - Emptied]
/local/customerintel/settings.php                   [MODIFIED - Fixed admin registration]
/local/customerintel/dashboard.php                  [MODIFIED - Fixed queries]
/local/customerintel/run.php                        [MODIFIED - Fixed form]
/local/customerintel/reports.php                    [MODIFIED - Simplified]
/local/customerintel/sources.php                    [MODIFIED - Fixed database insertion]
/local/customerintel/lang/en/local_customerintel.php [MODIFIED - Added strings]
```

### New Files
Create these new files:

```
/local/customerintel/companies.php                  [NEW - Company Management page]
/local/customerintel/templates/companies.mustache   [NEW - Company Management template]
```

## Deployment Steps

### Step 1: Backup
1. Download a backup of your current `/local/customerintel/` directory
2. Export your database tables starting with `mdl_local_ci_` (optional but recommended)

### Step 2: Upload Files
1. Connect to your server via FTP
2. Navigate to `/path/to/moodle/local/customerintel/`
3. Upload all modified files listed above
4. Create the new files in their specified locations
5. Ensure file permissions are correct (typically 644 for files, 755 for directories)

### Step 3: Trigger Moodle Upgrade
1. Log in to Moodle as an administrator
2. Navigate to **Site administration** (you should be automatically redirected to the upgrade page)
3. If not redirected, go to: **Site administration → Notifications**
4. Moodle will detect the version change (2025101401 → 2025101402)
5. Click **Upgrade Moodle database now**
6. Wait for the upgrade to complete

### Step 4: Clear Caches
1. Go to **Site administration → Development → Purge all caches**
2. Click **Purge all caches**
3. Alternatively, if you have CLI access (which you mentioned you don't), this would be: `php admin/cli/purge_caches.php`

### Step 5: Verify Installation
1. Navigate to **Site administration → Plugins → Local plugins → Customer Intelligence Dashboard**
2. Verify the settings page loads without errors
3. Test the main dashboard at `/local/customerintel/dashboard.php`
4. Test the new Companies page at `/local/customerintel/companies.php`
5. Test Sources page at `/local/customerintel/sources.php`

### Step 6: Configure Settings (if needed)
1. Go to the plugin settings page
2. Enter your API keys:
   - Perplexity API Key
   - OpenAI API Key
3. Configure cost limits and other settings as needed
4. Save settings

## Important Notes

### About Database Updates
- **No new tables needed** - The database structure already has all required tables
- The upgrade.php file has been updated but only adds a version marker
- Existing data will not be affected

### Version Management
- The version number has been incremented from 2025101401 to 2025101402
- This triggers Moodle's upgrade process which ensures proper cache clearing
- The release string has been updated to v1.0.2

### Troubleshooting
If you encounter issues after deployment:

1. **"Plugin not installed" error:**
   - Ensure all files were uploaded to the correct locations
   - Check file permissions

2. **Settings page errors:**
   - Clear all caches again
   - Check that language strings file was updated

3. **Database errors:**
   - Verify tables exist: local_ci_company, local_ci_source, local_ci_run, etc.
   - Check error logs in `/moodledata/phperrors.log`

4. **Page not found errors:**
   - Ensure new files (companies.php) were created
   - Check file permissions (should be readable by web server)

## Summary
This update primarily fixes UI issues and adds the Company Management feature. The database structure remains unchanged, so this is a safe update that only modifies PHP code and templates.