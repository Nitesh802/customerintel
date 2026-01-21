# M1 Task 4: Programmatic Refresh Control - Quick Reference

## 🚀 Quick Start

### Test the Implementation
```bash
# Access test suite in browser
http://your-moodle-site/local/customerintel/test_m1t4.php
```

### Set Refresh Config (SQL)
```sql
-- Force all NBs to regenerate
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":true,"force_synthesis_refresh":false,"refresh_source":false,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;

-- Force synthesis only
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":false,"force_synthesis_refresh":true,"refresh_source":false,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;

-- Refresh source NBs only (NB-1 to NB-7)
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":false,"force_synthesis_refresh":false,"refresh_source":true,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;

-- Refresh target NBs only (NB-8 to NB-15)
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":false,"force_synthesis_refresh":false,"refresh_source":false,"refresh_target":true}'
WHERE id = YOUR_RUN_ID;

-- Default behavior (use UI cache decision)
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":false,"force_synthesis_refresh":false,"refresh_source":false,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;
```

---

## 📊 Refresh Config Options

| Flag | Type | Description | Impact |
|------|------|-------------|--------|
| `force_nb_refresh` | boolean | Force all NB regeneration | Regenerates all 15 NBs + synthesis (~3 min) |
| `force_synthesis_refresh` | boolean | Force synthesis regeneration | Reuses NBs, regenerates synthesis (~30 sec) |
| `refresh_source` | boolean | Refresh source NBs only | Regenerates NB-1 to NB-7 + synthesis (~2 min) |
| `refresh_target` | boolean | Refresh target NBs only | Regenerates NB-8 to NB-15 + synthesis (~2 min) |

---

## 🔍 Verification Queries

### Check Refresh Decisions
```sql
-- View recent refresh decisions
SELECT runid, metricvalue, FROM_UNIXTIME(timecreated) as time
FROM mdl_local_ci_telemetry
WHERE metrickey = 'refresh_decision'
ORDER BY timecreated DESC
LIMIT 10;
```

### Check Diagnostics
```sql
-- View refresh-related diagnostics
SELECT runid, metric, severity, message, FROM_UNIXTIME(timecreated) as time
FROM mdl_local_ci_diagnostics
WHERE metric LIKE '%refresh%'
ORDER BY timecreated DESC
LIMIT 20;
```

### Check Run Configuration
```sql
-- View current refresh_config for a run
SELECT id, companyid, targetcompanyid, cache_strategy, refresh_config
FROM mdl_local_ci_run
WHERE id = YOUR_RUN_ID;
```

---

## 🎯 Common Use Cases

### Use Case 1: Fresh Analysis (No Cache)
**Goal:** Generate completely fresh data for a company pair

**Solution:**
```sql
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":true,"force_synthesis_refresh":false,"refresh_source":false,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;
```

### Use Case 2: Update Source Company Only
**Goal:** Company A changed, Company B unchanged - update only source NBs

**Solution:**
```sql
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":false,"force_synthesis_refresh":false,"refresh_source":true,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;
```

### Use Case 3: New Synthesis with Cached NBs
**Goal:** NBs are good, but want fresh synthesis (e.g., after prompt changes)

**Solution:**
```sql
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":false,"force_synthesis_refresh":true,"refresh_source":false,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;
```

### Use Case 4: UI-Driven Decision
**Goal:** Let user decide via "Reuse" or "Full Refresh" buttons

**Solution:**
```sql
-- Set all flags to false (or leave refresh_config NULL)
UPDATE mdl_local_ci_run
SET refresh_config = '{"force_nb_refresh":false,"force_synthesis_refresh":false,"refresh_source":false,"refresh_target":false}'
WHERE id = YOUR_RUN_ID;
```

---

## 🛠️ Troubleshooting

### Problem: Refresh config not working
**Check:**
1. Is refresh_config valid JSON?
   ```sql
   SELECT id, refresh_config FROM mdl_local_ci_run WHERE id = YOUR_RUN_ID;
   ```
2. Check diagnostics table:
   ```sql
   SELECT * FROM mdl_local_ci_diagnostics WHERE runid = YOUR_RUN_ID AND metric LIKE '%refresh%';
   ```

### Problem: NBs still using cache
**Check:**
1. Is cache_strategy set to 'force_refresh'? (This overrides refresh_config)
   ```sql
   SELECT cache_strategy FROM mdl_local_ci_run WHERE id = YOUR_RUN_ID;
   ```
2. Check telemetry for refresh decisions:
   ```sql
   SELECT * FROM mdl_local_ci_telemetry WHERE runid = YOUR_RUN_ID AND metrickey = 'refresh_decision';
   ```

### Problem: Synthesis not regenerating
**Check:**
1. Did NBs change?
   ```sql
   SELECT * FROM mdl_local_ci_diagnostics WHERE runid = YOUR_RUN_ID AND metric = 'synthesis_refresh_decision';
   ```
2. Is force_synthesis_refresh set?
   ```sql
   SELECT refresh_config FROM mdl_local_ci_run WHERE id = YOUR_RUN_ID;
   ```

---

## 📁 Modified Files

| File | Purpose | Backup |
|------|---------|--------|
| [cache_manager.php](classes/services/cache_manager.php) | Refresh strategy logic | ✅ .backup_m1t4 |
| [nb_orchestrator.php](classes/services/nb_orchestrator.php) | NB refresh integration | ✅ .backup_m1t4 |
| [synthesis_engine.php](classes/services/synthesis_engine.php) | Synthesis refresh integration | ✅ .backup_m1t4 |
| [version.php](version.php) | Version 2025203026 | ✅ .backup_m1t4 |
| [test_m1t4.php](test_m1t4.php) | Test suite | ➕ NEW |

---

## 📚 Documentation

- **Full Details:** [M1T4_IMPLEMENTATION_SUMMARY.md](M1T4_IMPLEMENTATION_SUMMARY.md)
- **Test Suite:** [test_m1t4.php](test_m1t4.php)
- **This Guide:** M1T4_QUICK_REFERENCE.md

---

## ✅ Deployment Checklist

- [ ] Backups verified (ls -lh *.backup_m1t4)
- [ ] Version updated to 2025203026
- [ ] Test suite executed (test_m1t4.php)
- [ ] Manual testing completed
- [ ] Telemetry verified
- [ ] Diagnostics verified
- [ ] Production deployment

---

## 🔄 Rollback

```bash
cd /mnt/c/Users/Jasmina/Documents/GitHub/CustomerIntel_Rubi/local_customerintel

# Restore all backups
cp classes/services/cache_manager.php.backup_m1t4 classes/services/cache_manager.php
cp classes/services/nb_orchestrator.php.backup_m1t4 classes/services/nb_orchestrator.php
cp classes/services/synthesis_engine.php.backup_m1t4 classes/services/synthesis_engine.php
cp version.php.backup_m1t4 version.php

# Update version in Moodle (visit Notifications page)
```

---

## 📞 Support

**Milestone:** M1 Task 4 of 11
**Status:** ✅ COMPLETED
**Version:** 2025203026

**Need Help?**
1. Review [M1T4_IMPLEMENTATION_SUMMARY.md](M1T4_IMPLEMENTATION_SUMMARY.md)
2. Run [test_m1t4.php](test_m1t4.php)
3. Check diagnostics table
4. Review code comments in modified files
