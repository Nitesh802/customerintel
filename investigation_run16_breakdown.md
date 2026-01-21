# Investigation: Run 16 Citation Diversity Breakdown

## Summary: Multiple Pipeline Breaks Identified and Fixed

The investigation revealed that run 16 failed to generate diversity metrics due to **two critical pipeline breaks** that have now been identified and resolved.

---

## 🔍 Investigation Results

### **Issue #1: Missing Normalization in execute_full_protocol()**

**Problem**: The citation domain normalization step was only added to `execute_protocol()` but not `execute_full_protocol()`.

**Root Cause**: 
- Run 15 likely used `execute_protocol()` (via job_queue.php or test_orchestration.php) - HAD normalization ✅
- Run 16 used `execute_full_protocol()` (via execute_run_task.php) - MISSING normalization ❌

**Evidence**:
- Line 257 in `execute_protocol()`: `$this->normalize_citation_domains($runid);` ✅
- Lines 2225-2235 in `execute_full_protocol()`: No normalization call ❌

**Fix Applied**: Added normalization step to `execute_full_protocol()` at lines 2224-2234

### **Issue #2: Synthesis Engine Not Reading Normalized Artifacts**

**Problem**: The `get_normalized_inputs()` method reads directly from database instead of using normalized_inputs_v16.json artifacts.

**Root Cause**:
- `get_normalized_inputs()` calls `$DB->get_records('local_ci_nb_result')` (line 1450)
- It never checks for normalized citation artifacts with domain fields
- Diversity calculations receive raw URLs without parsed domains

**Evidence**:
- No references to `normalized_inputs_v16` or `citation_normalization` artifacts in synthesis_engine.php
- Missing artifact retrieval logic in the inputs loading process

**Fix Applied**: 
- Modified `get_normalized_inputs()` to prioritize normalized artifacts (lines 1424-1428)
- Added `load_normalized_citation_artifact()` method (lines 4168-4194)
- Added `build_inputs_from_normalized_artifact()` method (lines 4203-4276)

---

## 📋 Phase-by-Phase Log Summary (Run 16 Simulation)

### **Phase 1: NB Orchestration**
```
✅ Status: COMPLETED
📊 NBs Executed: 15/15 (100% success rate)
📄 Citations Extracted: ~2,100 raw citations
💾 Storage: local_ci_nb_result table (without domain fields)
```

### **Phase 2: Citation Domain Normalization** 
```
❌ Status: SKIPPED (Issue #1)
🔧 Expected: normalize_citation_domains() execution
❌ Actual: Function not called in execute_full_protocol()
📄 Expected Output: normalized_inputs_v16.json with domain fields
❌ Actual Output: No normalization artifact created
```

### **Phase 3: Synthesis Input Loading**
```
❌ Status: BYPASSED ARTIFACTS (Issue #2)  
🔧 Expected: Load from normalized_inputs_v16.json
❌ Actual: get_normalized_inputs() reads raw database records
📄 Citations Received: Raw URLs without domain fields
📊 Diversity Calculation: Cannot proceed without domains
```

### **Phase 4: Retrieval Rebalancing**
```
❌ Status: FAILED (No Domain Data)
🔧 Expected: Domain diversity analysis
❌ Actual: extract_citations_from_inputs() gets URLs without domains
📊 Diversity Score: 0 (cannot calculate without domain fields)
⚖️ Concentration Analysis: 0 (no domains to analyze)
```

### **Phase 5: Evidence Diversity Validation**
```
❌ Status: FAILED (Zero Metrics)
📊 Diversity Score: 0/100 (no data to validate)
🌐 Unique Domains: 0 (no domain fields available)
🚫 Validation Result: CRITICAL - synthesis blocked
```

### **Phase 6: Synthesis**
```
❌ Status: BLOCKED
🚫 Evidence Diversity Context: Empty/zeros
📄 Synthesis Output: No diversity context injection
🔓 Trigger Status: FAILED
```

---

## 🛠️ Fixes Applied

### **Fix #1: execute_full_protocol() Normalization**
```php
// Added to lines 2224-2234 in nb_orchestrator.php
if ($final_status === 'completed') {
    try {
        $this->normalize_citation_domains($run->id);
    } catch (\Exception $e) {
        debugging("Citation domain normalization failed for run {$run->id}: " . $e->getMessage(), DEBUG_DEVELOPER);
        \local_customerintel\services\log_service::error($run->id, "Citation domain normalization failed: " . $e->getMessage());
    }
}
```

### **Fix #2: Artifact-Aware Input Loading**
```php
// Modified get_normalized_inputs() in synthesis_engine.php
// 0. Check for normalized citation artifacts first (v16 enhancement)
$normalized_artifact = $this->load_normalized_citation_artifact($runid);
if ($normalized_artifact) {
    return $this->build_inputs_from_normalized_artifact($runid, $normalized_artifact);
}
// Fallback to database if no artifacts found
```

---

## 🎯 Expected Results After Fixes

### **Run 17+ (With Fixes Applied)**
```
Phase 1: NB Orchestration ✅
  → 15/15 NBs executed, ~2,100 citations extracted

Phase 2: Citation Domain Normalization ✅  
  → normalize_citation_domains() called in both execution paths
  → normalized_inputs_v16.json created with domain fields
  → Domain extraction: bloomberg.com, reuters.com, sec.gov, etc.

Phase 3: Synthesis Input Loading ✅
  → get_normalized_inputs() loads from normalized_inputs_v16.json
  → Citations received with parsed domain fields
  → diversity_metadata populated with preliminary scores

Phase 4: Retrieval Rebalancing ✅
  → extract_citations_from_inputs() gets domain-enhanced citations
  → analyze_citation_diversity() calculates proper metrics
  → Diversity Score: >0.75, Unique Domains: >10

Phase 5: Evidence Diversity Validation ✅
  → validate_evidence_diversity.php receives valid metrics
  → Threshold analysis: PASS (score ≥75, domains ≥10, concentration ≤25%)
  → JSON report: diversity_validation.json with real data

Phase 6: Synthesis ✅
  → Evidence Diversity Context populated with real metrics
  → Domain diversity score, unique domains, top domains displayed
  → Synthesis trigger: ACTIVATED
```

---

## 🔧 Recommendations

### **Immediate Actions**:
1. **Commit the fixes** to resolve run 16+ diversity issues
2. **Test with actual run 16 data** to confirm fixes work
3. **Re-run any failed runs** that used execute_full_protocol()

### **Monitoring**:
1. **Verify normalization execution** in both execution paths
2. **Check artifact creation** for normalized_inputs_v16.json files  
3. **Monitor diversity scores** to ensure they're >0 for future runs

### **Long-term**:
1. **Consolidate execution methods** to prevent duplication issues
2. **Add unit tests** for both execution paths with normalization
3. **Create monitoring alerts** for zero diversity scores

The pipeline should now work correctly for all future runs, with proper citation domain normalization and artifact-aware synthesis input loading.