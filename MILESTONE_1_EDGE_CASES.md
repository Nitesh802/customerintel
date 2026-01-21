# Milestone 1: Edge Cases & Special Scenarios

## Overview

This document covers edge cases, special scenarios, and operational considerations for the M1 per-company NB caching system.

**Status:** Living Document  
**Last Updated:** November 5, 2025  
**Related:** MILESTONE_1_IMPLEMENTATION_SUMMARY.md

---

## 1. Single Entity Analysis (No Target Company)

### Scenario
User wants to generate a report for a single company without a competitor comparison.

### Current Behavior (M1)
**Code Location:** `nb_orchestrator.php`, lines 486-500

```php
if ($nb_number >= 1 && $nb_number <= 7) {
    // NB-1 to NB-7: Cache under source company
    $cache_company_id = $company->id;
} else if ($nb_number >= 8 && $nb_number <= 15 && $targetcompany) {
    // NB-8 to NB-15: Cache under target company (if exists)
    $cache_company_id = $targetcompany->id;
} else {
    // Fallback for single-entity runs
    $cache_company_id = $company->id;
}
```

**Expected Behavior:**
- If `$targetcompany` is NULL or not provided:
  - NB-1 to NB-7: Cache under source company ✅
  - NB-8 to NB-15: Fall back to caching under source company ✅

**Cache Structure:**
```
Company: Acme Corp (single entity run)
Cached NBs: NB-1, NB-2, NB-3, NB-4, NB-5, NB-6, NB-7, NB-8, NB-9, NB-10, NB-11, NB-12, NB-13, NB-14, NB-15
```

### Testing Status
- ⚠️ **NOT YET TESTED** - This scenario has not been validated in production
- 📋 **Action Required:** Test single entity analysis before Task 2

### Test Plan
1. Create run with source company only (no target)
2. Verify all 15 NBs cache under source company
3. Check cache query:
```sql
SELECT company_id, nbcode, version
FROM mdl_local_ci_nb_cache
WHERE company_id = [source_company_id]
ORDER BY nbcode;
-- Expected: 15 rows (NB-1 through NB-15)
```

---

## 2. Cache Invalidation Strategy

### When to Invalidate Cache?

#### Scenario A: Company Data Changed
**Trigger:** User updates company information (name, ticker, sector, etc.)

**Impact:** Cached NBs may contain outdated company information

**Recommendation:** 
- **Option 1 (Conservative):** Invalidate cache on ANY company field change
- **Option 2 (Selective):** Only invalidate if "material" fields change (sector, headquarters, description)
- **Option 3 (Versioned):** Increment cache version, keep old cached until natural expiration

**Implementation:**
```php
// In company update handler
if ($company_data_changed) {
    \local_customerintel\services\nb_cache_service::invalidate_company_cache($company_id);
}
```

#### Scenario B: User Request
**Trigger:** User clicks "Force Refresh" or similar UI control

**Current M1 Implementation:** Not yet supported (Task 4: Programmatic Refresh Control)

**Future M2 Implementation:**
```php
// Check refresh_config field
$refresh_config = json_decode($run->refresh_config);
if ($refresh_config->force_nb_refresh) {
    // Skip cache, regenerate all NBs
}
```

#### Scenario C: Cache Expiration (Time-Based)
**Trigger:** Cached NBs older than X days

**Current Status:** No automatic expiration implemented

**Recommendation:**
- **Short-term:** Manual cleanup via admin tool
- **Long-term (M2+):** Add `expires_at` field to `local_ci_nb_cache` table

**Manual Cleanup Query:**
```sql
-- Delete cache older than 90 days
DELETE FROM mdl_local_ci_nb_cache
WHERE timecreated < UNIX_TIMESTAMP(NOW() - INTERVAL 90 DAY);
```

#### Scenario D: API Changes / Prompt Updates
**Trigger:** NB generation prompts are modified, requiring regeneration

**Current Status:** No detection mechanism

**Recommendation:**
- Increment cache version in code when prompts change
- Add `prompt_version` field to track which prompt generated the NB
- Invalidate cache if `prompt_version` mismatch

---

## 3. Cache Version Strategy

### Current Implementation
**Table:** `local_ci_nb_cache`  
**Field:** `version INT DEFAULT 1`

**Version Behavior:**
- Auto-increments when storing to same `(company_id, nbcode)` pair
- `get_cached_nb()` retrieves LATEST version by default
- Old versions retained (not deleted)

### Use Cases for Versioning

#### Use Case 1: A/B Testing
Compare NB quality across different prompt versions:
```php
// Get version 1 (old prompt)
$nb_v1 = nb_cache_service::get_cached_nb($company_id, 'NB-1', 1);

// Get version 2 (new prompt)
$nb_v2 = nb_cache_service::get_cached_nb($company_id, 'NB-1', 2);
```

#### Use Case 2: Rollback
If new prompt generates poor results, revert to previous version:
```sql
-- Promote version 1 to version 3 (latest)
INSERT INTO mdl_local_ci_nb_cache (company_id, nbcode, jsonpayload, citations, version, timecreated)
SELECT company_id, nbcode, jsonpayload, citations, 3, UNIX_TIMESTAMP()
FROM mdl_local_ci_nb_cache
WHERE company_id = ? AND nbcode = ? AND version = 1;
```

#### Use Case 3: Cache Cleanup
Delete old versions to save space:
```sql
-- Keep only latest 2 versions per company+nbcode
DELETE c1 FROM mdl_local_ci_nb_cache c1
INNER JOIN (
    SELECT company_id, nbcode, MAX(version) as max_version
    FROM mdl_local_ci_nb_cache
    GROUP BY company_id, nbcode
) c2 ON c1.company_id = c2.company_id 
    AND c1.nbcode = c2.nbcode
    AND c1.version < c2.max_version - 1;
```

### Recommendation
- **M1:** Keep all versions (storage is cheap, data is valuable)
- **M2:** Add admin UI for version management
- **M3+:** Implement automatic version pruning (keep latest 3 versions)

---

## 4. Concurrent Runs & Race Conditions

### Scenario
Two users generate reports for the same company simultaneously.

**Potential Issue:** Duplicate cache writes

**Database Protection:**
- UNIQUE index on `(company_id, nbcode, version)` prevents duplicates
- If duplicate insert attempted, MySQL will throw error

**Current Handling:**
```php
// In nb_cache_service::store_nb()
try {
    $id = $DB->insert_record('local_ci_nb_cache', $record);
} catch (\Exception $e) {
    // Duplicate key error - another process cached it first
    // This is SAFE - just log and continue
    self::log_error('store_nb', $e->getMessage(), [/* context */]);
    return false;
}
```

**Expected Behavior:**
1. User A starts Run 100 (ViiV vs Gilead) at 2:00:00 PM
2. User B starts Run 101 (ViiV vs Merck) at 2:00:05 PM
3. Both generate NB-1 for ViiV simultaneously
4. User A's cache write succeeds (version 1)
5. User B's cache write fails (duplicate key) ← **SAFE**
6. User B retrieves User A's cached version ← **REUSES CACHE**

**Status:** ✅ Safe by design (database constraint prevents corruption)

---

## 5. Cache Size & Performance

### Current State (After 3 Test Runs)
```
Companies Cached: 3 (ViiV Healthcare, Merck, Pfizer)
Total NBs: 23 (7 + 8 + 8)
Average NB Size: ~15 KB
Total Cache Size: ~345 KB
```

### Projected Growth

**Scenario A: 100 Companies**
- NBs per company: ~7-8 (average)
- Total NBs: ~750
- Total size: ~11.25 MB
- **Impact:** Negligible

**Scenario B: 1,000 Companies**
- Total NBs: ~7,500
- Total size: ~112.5 MB
- **Impact:** Still small, no index degradation expected

**Scenario C: 10,000 Companies (Large Scale)**
- Total NBs: ~75,000
- Total size: ~1.125 GB
- **Impact:** May need index optimization, periodic cleanup

### Performance Considerations

**Query Performance:**
```sql
-- Primary cache lookup (indexed on company_id, nbcode, version)
SELECT * FROM mdl_local_ci_nb_cache
WHERE company_id = ? AND nbcode = ?
ORDER BY version DESC LIMIT 1;
-- Expected: <1ms (indexed query)
```

**Cleanup Strategy (Future):**
- Archive cache older than 1 year to separate table
- Delete cache for deleted companies
- Prune old versions (keep latest 3)

---

## 6. Error Handling & Recovery

### Error Scenario 1: Cache Write Fails

**Cause:** Database error, disk full, constraint violation

**Current Handling:**
```php
// In nb_cache_service::store_nb()
if ($id === false) {
    // Log error but don't fail the run
    self::log_error('store_nb', 'Failed to cache NB', [/* context */]);
    return false;
}
```

**Impact:** 
- Run continues successfully
- NB not cached (will regenerate next time)
- No user-visible error

**Recovery:** Automatic on next run (will attempt cache write again)

### Error Scenario 2: Cache Read Returns Corrupted Data

**Cause:** Database corruption, partial write, encoding issue

**Detection:**
```php
$cache = nb_cache_service::get_cached_nb($company_id, $nbcode);
$payload = json_decode($cache->jsonpayload, true);

if ($payload === null) {
    // JSON decode failed - corrupted cache
    // Delete corrupted entry
    $DB->delete_records('local_ci_nb_cache', ['id' => $cache->id]);
    // Fall back to API generation
    $cache = false;
}
```

**Status:** ⚠️ Not yet implemented (add in M1.1 or M2)

### Error Scenario 3: Foreign Key Violation

**Cause:** Company deleted but cache entries remain

**Prevention:**
```sql
-- Foreign key constraint defined in schema
FOREIGN KEY (company_id) REFERENCES local_ci_company(id) ON DELETE CASCADE
```

**Behavior:** Cache entries automatically deleted when company deleted

**Status:** ✅ Protected by schema design

---

## 7. Migration & Rollback

### Forward Migration (M0 → M1)

**Steps:**
1. Database schema update (new table created)
2. Code deploys (cache service, orchestrator integration)
3. First run after migration:
   - All NBs cache MISS (new table empty)
   - NBs generated via API and cached
4. Second run with same company:
   - Cache HIT for matching company NBs

**Impact:** No breaking changes, graceful degradation

### Rollback (M1 → M0)

**If Issues Occur:**
```sql
-- 1. Drop new table
DROP TABLE IF EXISTS mdl_local_ci_nb_cache;

-- 2. Revert version
UPDATE mdl_config_plugins 
SET value = '2025203022'
WHERE plugin = 'local_customerintel' AND name = 'version';

-- 3. Clear upgrade log
DELETE FROM mdl_upgrade_log
WHERE plugin = 'local_customerintel' AND version = '2025203023';
```

**Code Rollback:**
```bash
# Restore backups
cp db/upgrade.php.backup db/upgrade.php
cp version.php.backup version.php
cp classes/services/nb_orchestrator.php.backup classes/services/nb_orchestrator.php
rm classes/services/nb_cache_service.php

# Re-run upgrade
php admin/cli/upgrade.php
```

**Impact:** 
- M0 functionality restored
- New M1 runs will regenerate all NBs (no cache)
- M0 cached runs unaffected (different table)

---

## 8. Future Enhancements (M2+)

### Admin UI for Cache Management
- View cache statistics per company
- Manually invalidate specific company cache
- View cache versions and compare
- Export cache analytics

### Smart Cache Warming
- Pre-generate NBs for frequently analyzed companies
- Background job to refresh stale cache
- Predictive caching based on usage patterns

### Cache Analytics Dashboard
- Cache hit rate over time
- Cost savings attribution
- Top cached companies
- Cache size trends

### Multi-Tenant Considerations
- Separate cache namespaces per tenant
- Tenant-specific cache quotas
- Cross-tenant cache sharing (for public companies)

---

## Summary & Action Items

### Tested & Working ✅
- Basic cache hit/miss flow
- Company-level NB storage
- Backward compatibility with M0
- Cost/performance benefits validated

### Needs Testing ⚠️
- Single entity analysis (no target company)
- Concurrent run race conditions
- Cache corruption recovery
- Large-scale performance (100+ companies)

### Future Work 📋
- Automatic cache expiration
- Version pruning strategy
- Admin UI for cache management
- Cache warming and predictive caching

---

**Document Status:** Draft v1.0  
**Next Review:** After Task 2 completion  
**Owner:** Jasmina (Developer) / Jon (Product Owner)
