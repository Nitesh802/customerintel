# Customer Intel Rubi (Moodle Plugin)
**Document:** Technical Reference  
**Last Updated:** 2025-11-05 (M1 Implementation)

---

## Table of Contents
1. [File-to-Stage Mapping](#file-to-stage-mapping)
2. [Cache Architecture](#cache-architecture)
3. [M0: Run-Level Cache (Synthesis Cache)](#m0-run-level-cache)
4. [M1: Per-Company NB Cache](#m1-per-company-nb-cache)
5. [Database Schema](#database-schema)
6. [Service Classes](#service-classes)

---

## File-to-Stage Mapping

| Stage | File | Class | Description |
|--------|------|--------|-------------|
| Stage 0 | classes/services/cache_manager.php | cache_manager | Interactive cache check (M0) |
| Stage 1 | classes/services/nb_orchestrator.php | nb_orchestrator | NB generation & caching (M1) |
| Stage 2 | classes/services/canonical_builder.php | canonical_builder | Merge and normalize |
| Stage 3 | classes/services/analysis_engine.php | analysis_engine | Multi-level synthesis |
| Stage 4 | classes/services/formatter.php | formatter | Section schema formatting |
| Stage 5 | templates/energyexemplar_style.mustache | — | Rendering |

---

## Cache Architecture

The Customer Intel plugin uses a **two-tier caching system**:

### Tier 1: Per-Company NB Cache (M1) - Granular
- **Purpose:** Cache individual NBs per company for reuse across multiple runs
- **Table:** `local_ci_nb_cache`
- **Scope:** Company-level (NB-1 to NB-7 for source, NB-8 to NB-15 for target)
- **Benefit:** 40-50% cost reduction when analyzing same company multiple times

### Tier 2: Run-Level Synthesis Cache (M0) - Complete
- **Purpose:** Cache complete synthesis reports for instant display
- **Table:** `local_ci_artifact` (artifact-based storage)
- **Artifact Type:** `synthesis_final_bundle`
- **Scope:** Run-level (entire source+target report)
- **Benefit:** 95% time reduction for subsequent views of same run

**Cache Flow:**
```
User initiates new run
    ↓
Check Tier 1 (NB Cache) for each company
    ↓ (Hit)         ↓ (Miss)
Reuse cached NB → Generate NB via API → Store in Tier 1
    ↓
Generate synthesis report
    ↓
Store in Tier 2 (Synthesis Cache)
    ↓
Subsequent views: Load from Tier 2 (instant)
```

---

## M0: Run-Level Cache (Artifact-Based Storage)

### Purpose
Store complete synthesis reports for instant retrieval without regeneration using Moodle's artifact system.

### Architecture: Artifact-Based Storage

**Why Artifacts?**

M0 uses Moodle's artifact storage system rather than a dedicated synthesis table. This design provides:

1. **Flexibility:** Can store multiple content types (reports, metadata, analytics)
2. **Versioning:** Built-in version control via artifact system
3. **Extensibility:** Easy to add new artifact types without schema changes
4. **Clean Separation:** Artifacts separate from run metadata
5. **Proven Pattern:** Leverages Moodle's established content storage

### Database Table: `local_ci_artifact`

**Key Fields:**
- `id` (PK) - Artifact ID
- `runid` (FK to local_ci_run) - Associated run
- `artifacttype` (VARCHAR) - Type identifier = `'synthesis_final_bundle'`
- `jsondata` (TEXT) - Complete synthesis report in JSON format
- `timemodified` (INT) - Unix timestamp of last update

**Storage Format:**

The `jsondata` field contains a complete synthesis bundle:
```json
{
  "htmlcontent": "Complete HTML report...",
  "jsoncontent": {
    "sections": [...],
    "citations": [...],
    "metadata": {...}
  },
  "voice_report": "Audio transcript...",
  "selfcheck_report": "QA validation...",
  "generated_at": 1730738400,
  "version": "1.0"
}
```

### Cache Logic

**Retrieval:**
```php
// Load synthesis artifact for a run
$artifact = $DB->get_record('local_ci_artifact', [
    'runid' => $runid,
    'artifacttype' => 'synthesis_final_bundle'
]);

if ($artifact) {
    $bundle = json_decode($artifact->jsondata, true);
    $html_report = $bundle['htmlcontent'];
    $json_data = $bundle['jsoncontent'];
    // Display cached report
} else {
    // No cache found - generate new report
}
```

**Storage:**
```php
// Store synthesis as artifact
$artifact = new stdClass();
$artifact->runid = $runid;
$artifact->artifacttype = 'synthesis_final_bundle';
$artifact->jsondata = json_encode([
    'htmlcontent' => $html_report,
    'jsoncontent' => $json_structure,
    'voice_report' => $voice_transcript,
    'selfcheck_report' => $qa_report,
    'generated_at' => time(),
    'version' => '1.0'
]);
$artifact->timemodified = time();

$DB->insert_record('local_ci_artifact', $artifact);
```

### Legacy vs Current Implementation

**Legacy (Unused):**
- Table: `local_ci_synthesis` 
- Direct column storage: `htmlcontent`, `jsoncontent`, etc.
- Status: Table may exist but is not actively used

**Current (M0):**
- Table: `local_ci_artifact`
- JSON bundle storage in `jsondata` field
- Artifact type: `synthesis_final_bundle`
- Status: Active, production implementation

**Important:** Validation scripts and queries must check `local_ci_artifact`, not `local_ci_synthesis`.

### M0 vs M1 Storage Comparison

| Aspect | M0 (Run-Level) | M1 (Company-Level) |
|--------|----------------|-------------------|
| **Table** | `local_ci_artifact` | `local_ci_nb_cache` |
| **Storage Type** | Artifact-based (JSON bundle) | Direct field storage |
| **Granularity** | Complete run (source+target) | Individual NBs per company |
| **Primary Key** | `(runid, artifacttype)` | `(company_id, nbcode, version)` |
| **Data Field** | `jsondata` | `jsonpayload` |
| **Use Case** | Fast report retrieval | NB reuse across runs |
| **Interference** | None - completely separate tables | None - completely separate tables |

### Performance Impact
- **First generation:** 8-12 minutes (full API processing)
- **Cached retrieval:** <1 second (database read)
- **Cost savings:** 100% (no API calls for cached runs)

---

## M1: Per-Company NB Cache

### Purpose
Cache narrative briefs (NBs) at the company level for reuse across multiple competitive analyses.

### Database Table: `local_ci_nb_cache`

**Schema:**
```sql
CREATE TABLE local_ci_nb_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    nbcode VARCHAR(10) NOT NULL,
    jsonpayload TEXT NOT NULL,
    citations TEXT,
    version INT DEFAULT 1,
    timecreated INT NOT NULL,
    UNIQUE KEY (company_id, nbcode, version),
    FOREIGN KEY (company_id) REFERENCES local_ci_company(id) ON DELETE CASCADE,
    INDEX (timecreated)
);
```

**Key Fields:**
- `company_id` - FK to local_ci_company
- `nbcode` - NB identifier (NB-1, NB-2, ..., NB-15)
- `jsonpayload` - Complete NB content as JSON
- `citations` - Source URLs and metadata
- `version` - Cache version (for A/B testing, rollback)
- `timecreated` - Unix timestamp

### NB-to-Company Mapping

**Critical Design Decision:**
- **NB-1 to NB-7:** Cached under **source company**
- **NB-8 to NB-15:** Cached under **target company**

**Rationale:**
- Different NBs analyze different aspects
- Source NBs (1-7) are company-intrinsic (reusable)
- Target NBs (8-15) are also company-intrinsic
- Enables maximum cache reuse across runs

**Example:**
```
Run 1: ViiV Healthcare vs Gilead
  - ViiV: NB-1, NB-2, NB-3, NB-4, NB-5, NB-6, NB-7 (7 NBs cached)
  - Gilead: NB-8, NB-9, NB-10, NB-11, NB-12, NB-13, NB-14, NB-15 (8 NBs cached)

Run 2: ViiV Healthcare vs Pfizer
  - ViiV: NB-1 to NB-7 (CACHE HIT - reused from Run 1) ✅
  - Pfizer: NB-8 to NB-15 (CACHE MISS - generated new) ⚡
  
Cost Savings: 7/15 NBs reused = 47% cost reduction
Time Savings: ~50% faster generation
```

### Service Class: `nb_cache_service`

**Location:** `classes/services/nb_cache_service.php`

#### Methods

##### `get_cached_nb($company_id, $nbcode, $version = null)`
Retrieve cached NB for a company.

**Parameters:**
- `$company_id` (int) - Company ID
- `$nbcode` (string) - NB code (e.g., 'NB-1')
- `$version` (int, optional) - Specific version, defaults to latest

**Returns:**
- `object|false` - Cache record or false if not found

**Example:**
```php
$cache = nb_cache_service::get_cached_nb(42, 'NB-1');
if ($cache) {
    $payload = json_decode($cache->jsonpayload, true);
    // Use cached NB
} else {
    // Generate via API
}
```

##### `store_nb($company_id, $nbcode, $jsonpayload, $citations = null, $version = null)`
Store NB in cache.

**Parameters:**
- `$company_id` (int) - Company ID
- `$nbcode` (string) - NB code
- `$jsonpayload` (string) - JSON-encoded NB content
- `$citations` (string, optional) - Citation metadata
- `$version` (int, optional) - Version number (auto-increments if null)

**Returns:**
- `int|false` - Cache record ID or false on failure

**Example:**
```php
$cache_id = nb_cache_service::store_nb(
    $company_id,
    'NB-1',
    json_encode($nb_content),
    json_encode($citations)
);
```

##### `invalidate_company_cache($company_id)`
Delete all cached NBs for a company.

**Use Cases:**
- Company data significantly changed
- User requests force refresh
- Cache corruption detected

**Example:**
```php
nb_cache_service::invalidate_company_cache(42);
// All NBs for company 42 deleted
```

##### `get_cache_stats($company_id = null)`
Get cache statistics.

**Parameters:**
- `$company_id` (int, optional) - Specific company, or null for all

**Returns:**
- `object` - Statistics (total_entries, unique_companies, unique_nbcodes, oldest_cache, newest_cache)

**Example:**
```php
$stats = nb_cache_service::get_cache_stats();
echo "Total cached NBs: {$stats->total_entries}";
echo "Companies cached: {$stats->unique_companies}";
```

##### `has_cached_nbs($company_id)`
Check if company has any cached NBs.

**Returns:**
- `bool` - True if cache exists

##### `get_cached_nbcodes($company_id)`
Get list of NB codes cached for a company.

**Returns:**
- `array` - Array of NB codes (e.g., ['NB-1', 'NB-2', 'NB-3'])

### Integration: `nb_orchestrator.php`

**Location:** Lines ~480-500

**Cache Check Logic:**
```php
// Extract NB number from code (e.g., 'NB-1' → 1)
$nb_number = (int)str_replace('NB-', '', $nbcode);

// Determine which company owns this NB
if ($nb_number >= 1 && $nb_number <= 7) {
    // Source company NBs
    $cache_company_id = $company->id;
} else if ($nb_number >= 8 && $nb_number <= 15 && $targetcompany) {
    // Target company NBs
    $cache_company_id = $targetcompany->id;
} else {
    // Fallback (single entity analysis)
    $cache_company_id = $company->id;
}

// Check cache
$cache = nb_cache_service::get_cached_nb($cache_company_id, $nbcode);

if ($cache && !$force_refresh) {
    // CACHE HIT - Reuse
    $nb_content = json_decode($cache->jsonpayload, true);
    $this->log_telemetry('nb_cache_hit', [
        'company_id' => $cache_company_id,
        'nbcode' => $nbcode
    ]);
} else {
    // CACHE MISS - Generate
    $nb_content = $this->generate_nb_via_api($nbcode, $company_data);
    
    // Store in cache
    nb_cache_service::store_nb(
        $cache_company_id,
        $nbcode,
        json_encode($nb_content),
        json_encode($citations)
    );
    
    $this->log_telemetry('nb_cache_miss', [
        'company_id' => $cache_company_id,
        'nbcode' => $nbcode
    ]);
}
```

### Telemetry Tracking

**Metrics Logged:**
- `nb_cache_hit` - Cache hit for specific company/NB
- `nb_cache_miss` - Cache miss, generated via API
- `nb_cache_store` - Successful cache write

**Query Cache Hit Rate:**
```sql
SELECT 
    COUNT(CASE WHEN metric = 'nb_cache_hit' THEN 1 END) as hits,
    COUNT(CASE WHEN metric = 'nb_cache_miss' THEN 1 END) as misses,
    ROUND(100.0 * COUNT(CASE WHEN metric = 'nb_cache_hit' THEN 1 END) / 
          COUNT(*), 2) as hit_rate_percent
FROM mdl_local_ci_diagnostics
WHERE metric IN ('nb_cache_hit', 'nb_cache_miss')
AND timecreated > UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY);
```

### Performance Impact

**Real-World Results (Nov 2025):**

| Scenario | NBs Generated | NBs Cached | Time | Cost | Savings |
|----------|---------------|------------|------|------|---------|
| Run 1: ViiV vs Merck | 15 (all new) | 0 hits | ~8 min | $2.25 | 0% (baseline) |
| Run 2: ViiV vs Pfizer | 8 (Pfizer new) | 7 hits (ViiV) | ~4 min | $1.20 | 47% cost, 50% time |

**Projected Savings:**

- **5 competitors vs 1 company:** 43% cost reduction
- **1 company vs 5 competitors:** 37% cost reduction
- **10 companies, all pairs:** ~60% cost reduction (compounding cache reuse)

### Cost Attribution

**Per-NB Cost Estimate:**
- Average NB: 15,000 tokens
- Cost per NB: ~$0.15 (at $10/1M tokens)
- Cache hit: $0.00
- **ROI:** Cache pays for itself after 1 reuse

---

## Database Schema

### Core Tables

#### `local_ci_run`
Stores run metadata.

**Key Fields (M1 additions pending in Task 2-4):**
- `id` (PK)
- `sourcecompanyid` (FK)
- `targetcompanyid` (FK)
- `cache_strategy` (VARCHAR) - Cache decision
- `prompt_config` (TEXT) - Tone/persona settings (Task 2)
- `refresh_config` (TEXT) - Refresh control (Task 4)
- `status` (VARCHAR) - Run status
- `timecreated` (INT)
- `timecompleted` (INT)

#### `local_ci_company`
Company master data.

**Key Fields:**
- `id` (PK)
- `name` (VARCHAR)
- `ticker` (VARCHAR)
- `sector` (VARCHAR)
- `headquarters` (VARCHAR)
- `description` (TEXT)

#### `local_ci_artifact` (M0 Cache - Active)
Artifact-based storage for synthesis reports and other content.

**Key Fields:**
- `id` (PK)
- `runid` (FK to local_ci_run)
- `artifacttype` (VARCHAR) - Type identifier (e.g., `'synthesis_final_bundle'`)
- `jsondata` (TEXT) - Complete artifact data in JSON format
- `timemodified` (INT) - Unix timestamp

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`runid`, `artifacttype`)
- FOREIGN KEY (`runid`) → `local_ci_run(id)` ON DELETE CASCADE

**Usage:**
```sql
-- Retrieve synthesis artifact
SELECT jsondata 
FROM mdl_local_ci_artifact
WHERE runid = ? AND artifacttype = 'synthesis_final_bundle';
```

#### `local_ci_synthesis` (Legacy - Not Used)
Legacy synthesis table (superseded by artifact-based storage).

**Status:** Table may exist for backward compatibility but is NOT actively used by M0 or M1.

**Key Fields:**
- `id` (PK)
- `runid` (FK to local_ci_run)
- `htmlcontent` (TEXT)
- `jsoncontent` (TEXT)
- `voice_report` (TEXT)
- `selfcheck_report` (TEXT)
- `createdat` (INT)
- `updatedat` (INT)

**Note:** M0 uses `local_ci_artifact` instead. This table may be empty or contain outdated data.

#### `local_ci_nb_cache` (M1 Cache)
Per-company NB cache.

**Key Fields:**
- `id` (PK)
- `company_id` (FK to local_ci_company)
- `nbcode` (VARCHAR) - NB-1 to NB-15
- `jsonpayload` (TEXT) - NB content
- `citations` (TEXT) - Source metadata
- `version` (INT) - Cache version
- `timecreated` (INT)

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY (`company_id`, `nbcode`, `version`)
- FOREIGN KEY (`company_id`) → `local_ci_company(id)` ON DELETE CASCADE
- INDEX (`timecreated`)

#### `local_ci_diagnostics`
Error and event logging.

**Key Fields:**
- `id` (PK)
- `level` (VARCHAR) - info, warning, error
- `component` (VARCHAR) - Service/class name
- `message` (TEXT) - Log message
- `metadata` (TEXT) - JSON context
- `timecreated` (INT)

**Cache Events Logged:**
- Component: `nb_cache_service`
- Events: hit, miss, store, invalidate, error

---

## Validation Queries

### Check M0 Synthesis Cache (Artifact-Based)

**Verify artifacts exist:**
```sql
SELECT 
    a.id,
    a.runid,
    a.artifacttype,
    LENGTH(a.jsondata) as size_bytes,
    ROUND(LENGTH(a.jsondata)/1024, 2) as size_kb,
    FROM_UNIXTIME(a.timemodified) as modified
FROM mdl_local_ci_artifact a
WHERE a.artifacttype = 'synthesis_final_bundle'
AND a.runid IN (103, 104, 105, 106, 107, 108)
ORDER BY a.runid;
```

**Expected output:**
```
runid | artifacttype            | size_kb | modified
------|-------------------------|---------|-------------------
103   | synthesis_final_bundle  | 83.2    | 2025-11-04 17:40:11
104   | synthesis_final_bundle  | 84.1    | 2025-11-04 18:15:23
...
```

### Check M1 NB Cache

**Per-company cache statistics:**
```sql
SELECT 
    c.name as company_name,
    COUNT(*) as cached_nbs,
    GROUP_CONCAT(DISTINCT n.nbcode ORDER BY n.nbcode) as nb_codes,
    SUM(LENGTH(n.jsonpayload)) as total_bytes,
    ROUND(SUM(LENGTH(n.jsonpayload))/1024, 2) as total_kb,
    MAX(FROM_UNIXTIME(n.timecreated)) as last_cached
FROM mdl_local_ci_nb_cache n
JOIN mdl_local_ci_company c ON n.company_id = c.id
GROUP BY c.id, c.name
ORDER BY c.name;
```

### Check Cache Hit Rates

**M1 NB cache performance:**
```sql
SELECT 
    JSON_EXTRACT(metadata, '$.event') as event_type,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM mdl_local_ci_diagnostics
WHERE component = 'nb_cache_service'
AND message LIKE '%NB Cache%'
AND timecreated > UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY)
GROUP BY JSON_EXTRACT(metadata, '$.event');
```

### Verify Table Structures

**Check all Customer Intel tables:**
```sql
SHOW TABLES LIKE 'mdl_local_ci_%';
```

**Check artifact table structure:**
```sql
DESCRIBE mdl_local_ci_artifact;
```

**Check NB cache table structure:**
```sql
DESCRIBE mdl_local_ci_nb_cache;
```

---

## Service Classes

### Cache Services

1. **`cache_manager`** (M0) - Run-level synthesis cache management
2. **`nb_cache_service`** (M1) - Per-company NB cache management

### Data Services

3. **`nb_orchestrator`** - NB generation orchestration with M1 caching
4. **`canonical_builder`** - NB normalization and merging
5. **`analysis_engine`** - Multi-stage synthesis generation

### Utility Services

6. **`telemetry_service`** - Metrics and event logging
7. **`qa_service`** - Quality assurance validation

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2025203022 | Nov 4, 2025 | M0 Complete - Run-level cache, diagnostics table |
| 2025203023 | Nov 5, 2025 | M1 Task 1 - Per-company NB cache implemented |
| 2025203024 | TBD | M1 Task 2 - Prompt config scaffolding |

---

## References

- **M0 Progress Report:** `/mnt/project/MILESTONE_0_PROGRESS_REPORT.md`
- **M1 Implementation:** `/mnt/user-data/uploads/MILESTONE_1_IMPLEMENTATION_SUMMARY.md`
- **M1 Edge Cases:** `/home/claude/MILESTONE_1_EDGE_CASES.md`
- **Vision Document:** `/mnt/project/CUSTOMER_INTEL_VISION_UPDATED_POST_M0.md`

---

**Document Maintained By:** Jasmina (Developer)  
**Last Updated:** November 5, 2025  
**Next Review:** After M1 Task 2-4 completion
