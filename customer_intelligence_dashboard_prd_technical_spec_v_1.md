# Customer Intelligence Dashboard (local_customerintel)

Version: v1
Owner: Fused / Rubi Platform
Status: Draft for build — aligns with PRD & Technical Doc Evaluators

---

## 1) Problem Statement
Analysts and managers need a repeatable, automated pipeline to execute the full NB-1…NB-15 research protocol for any Customer Company and Target Company pair, render a TSX-style HTML report, and persist reusable company intelligence to reduce cost and latency for future comparisons.

---

## 2) Goals (v1)
- Run NB-1 → NB-15 end-to-end with strict extraction (low temperature) and JSON schemas.
- Replicate the section structure and UX flow of the attached TSX file as HTML pages within a Moodle **local_** plugin (no PDF in v1).
- Persist **company-level intelligence** so Customer intel can be reused across multiple Target comparisons without re-running raw collection.
- Allow manual curation of sources: add/remove uploads and URLs alongside auto-discovered sources.
- Store citations per finding wherever possible.
- Provide progress meter, saved company pickers, dashboard for new/saved reports.
- Implement versioning (snapshots) and stored diffs for runs.
- Include a dry‑run **cost estimator** and basic telemetry on tokens/cost/time.

---

## 3) Non-Goals (v1)
- PDF or DOCX exports (future phase).
- NotebookLM integration (self-contained experience only).
- Per-user API billing or user-managed keys (central plugin config only).
- Custom prompt authoring UI (fixed protocol prompts in v1).
- Advanced role gating (available to all roles in v1; granular capabilities later).

---

## 4) Users & Roles
- **Analyst / Manager / Admin** — all roles can use in v1.
- Later: capability flags for run, edit sources, view costs, export, administer settings.

---

## 5) Success Metrics
- P95 end-to-end run time for fresh Target + cached Customer ≤ **15 min** on standard corpus.
- ≥ **90%** of NB sections return valid, schema-conformant outputs without human edits.
- Reuse of Customer snapshot when adding a new Target yields **≥40%** token-cost reduction vs. two fresh runs.
- **≥80%** of findings include at least one citation (where sources exist).
- < **2%** job failures per 100 runs (excluding upstream API outages).

---

## 6) Scope
### In Scope
- Automated retrieval + analysis via LLMs with manual source curation.
- HTML output mirroring TSX component structure.
- Company and comparison persistence; saved pickers and reruns.
- Versioning (snapshots) + stored diffs (per NB).
- Cost estimator + basic telemetry.

### Out of Scope
- PDF export.
- Role-based redactions.
- Multi-tenant/billing features beyond centralized keys.
- Full analytics dashboards beyond basic run stats.

---

## 7) User Stories
1. As an analyst, I select **Customer** and **Target**, preview estimated cost, and **Run Intelligence** (NB-1..NB-15).
2. As an analyst, I **review sources** found automatically, remove any, and **upload** my own files before running.
3. As an analyst, I open a **saved report**, view **version history**, and see **changes since previous run**.
4. As a manager, I reuse **Customer intelligence** for multiple Target comparisons without duplicating cost/time.
5. As an admin, I configure **API keys**, domain allow/deny list, freshness window, and cost caps.

---

## 8) Functional Requirements
### 8.1 Company Repository
- Create/search/select companies (Customer/Target) with canonical metadata (name, ticker, sector, website, tags).
- Persist **CompanyIntel snapshots** per run (NB outputs + citations + derived tables).
- Configurable freshness window (days) to determine when to reuse vs. refresh.

### 8.2 Sources & Ingest
- Auto-discover sources via Search API (Perplexity) with allow/deny domain filters.
- Manual add/remove: file uploads (PDF/DOCX), URLs, or pasted text.
- Extract text, chunk content, compute hashes for dedupe; maintain citation registry with title/URL/date/excerpt hash.

### 8.3 NB Orchestration (NB-1..NB-15)
- Strict extraction prompts, temperature ≤ 0.2; JSON-only replies validated against per-NB schemas.
- Chunk-aware prompting with retrieval context; multi-pass repair on invalid JSON.
- Per-NB telemetry: duration, tokens, status, error (if any).

### 8.4 Report Assembly (TSX parity)
- Render sections and item blocks mirroring the TSX component (headings, prompts, responses).
- Progress meter (completed items / total prompts).
- Save/Open/Reset actions mapped to server state (no localStorage reliance).
- Pressure Profile remains narrative; keep hooks for future programmatic score.

### 8.5 Versioning & Diffs
- On run completion, persist **immutable snapshot** containing all NB payloads + citations + derived tables.
- Compute and store a **diff JSON** vs. the prior snapshot (per NB: added/removed/changed fields, citation changes).
- UI provides version selector and "Show changes since previous" toggle.

### 8.6 Reuse Logic / Caching
- Reuse latest **Customer snapshot** if within freshness window unless user chooses "Force refresh".
- For comparisons, assemble from **Customer snapshot (reused)** + **Target snapshot (new/reused)**.

### 8.7 Cost Estimator & Telemetry
- Estimate tokens & currency by provider pre-run; display to user.
- After run, record actuals and compute variance; log tokens/time/errors at NB level.
- Admin settings allow warn threshold and hard stop; user confirmation to exceed warn.

### 8.8 Security & Config
- Centralized encrypted storage for Perplexity + LLM keys (admin-only settings page).
- Server-side API calls only; keys never exposed to browser.
- Run audit log (who, when, companies, run id).

---

## 9) Acceptance Criteria (testable)
- **AC-1**: Running NB-1..NB-15 for (Customer=X, Target=Y) produces an HTML report with all TSX sections present and non-empty; each NB returns **valid JSON** per schema (server-validated).
- **AC-2**: Removing a source before run excludes it from retrieval/citations; citation appendix displays only approved sources.
- **AC-3**: Re-run with unchanged Customer and a new Target uses **≤60%** of tokens vs. two fully fresh runs (telemetry).
- **AC-4**: Version history shows ≥1 previous snapshot and a diff highlighting NB-level changes.
- **AC-5**: Cost estimator is shown pre-run; post-run actuals recorded; 80% of runs have estimator error within ±25%.
- **AC-6**: P95 end-to-end run time ≤ 15 minutes on standard corpus.

---

## 10) Decision Framework (recorded choices)
- **Plugin type**: `local_` for platform-wide access and non-course workflow.
- **Versioning**: Hybrid — immutable snapshots + stored per-NB diffs.
- **Self-contained**: No NotebookLM; plugin handles retrieval and analysis.
- **Prompting**: Strict extraction, JSON schemas, low temperature.
- **Providers**: Combo approach — Perplexity for discovery, LLM(s) for extraction/synthesis.
- **Reuse**: Company-level cache to reduce cost/time.

---

## 11) Architecture Overview
- Moodle local plugin `local_customerintel`.
- PHP services orchestrate retrieval, LLM calls, and assembly; long operations via background jobs.
- JS (AMD/ES) for interactive UI (sources curation, progress, version/diff toggles).
- Storage: Moodle DB tables + Moodle Files API for uploads.

### Key Services
- `CompanyService` — CRUD, metadata enrichment, freshness checks.
- `SourceService` — discovery (Perplexity), uploads, URL fetch, text extraction, chunking, citation registry, dedupe.
- `NBOrchestrator` — executes NB-1..NB-15 with schema validation & repair loops.
- `Assembler` — maps NB JSONs into TSX-style HTML structure.
- `VersioningService` — snapshots, diffs, history.
- `CostService` — estimate tokens/cost, capture actuals & variance.
- `JobQueue` — queued execution with retry/backoff.

### Pages
- `/local/customerintel/dashboard.php` — Companies, Saved Reports, New Report.
- `/local/customerintel/run.php` — Company pickers, sources panel, estimator, Run, progress.
- `/local/customerintel/report.php?id=REPORT_ID` — HTML report, citations, versions, diff toggle.
- `/local/customerintel/settings.php` — Admin keys, domains allow/deny, freshness window, cost caps.

---

## 12) Data Model (tables)
`mdl_local_ci_company`
- id (PK), name, ticker, type ENUM('customer','target','unknown'), website, sector, metadata JSON, created_at, updated_at

`mdl_local_ci_source`
- id, company_id (FK), type ENUM('url','file','manual_text'), title, url, file_id, published_at, added_by_userid, approved BOOL, rejected BOOL, hash, created_at

`mdl_local_ci_run`
- id, company_id (FK), initiated_by_userid, mode ENUM('full','partial'), reused_from_run_id, est_tokens, est_cost, started_at, finished_at, status ENUM('queued','running','succeeded','failed'), error JSON

`mdl_local_ci_nb_result`
- id, run_id (FK), nb_code ENUM('NB1'..'NB15'), json_payload JSON, citations JSON[], duration_ms, tokens_used, status

`mdl_local_ci_snapshot`
- id, company_id, run_id, snapshot_json JSON, created_at

`mdl_local_ci_diff`
- id, from_snapshot_id, to_snapshot_id, diff_json JSON, created_at

`mdl_local_ci_comparison`
- id, customer_company_id, target_company_id, base_customer_snapshot_id, target_snapshot_id, comparison_json JSON, created_at

`mdl_local_ci_settings`
- id, setting_key, setting_value (encrypted), updated_at

`mdl_local_ci_telemetry`
- id, run_id, metric_key, metric_value_num, payload JSON, created_at

---

## 13) NB Schemas (overview)
- Maintain one JSON schema per NB (required fields, types, enums). Examples:
  - **NB-1 Executive Pressure**: { commitments[], deadlines[], metrics[], quotes[], pressure_factors[], citations[] }
  - **NB-5 Margins & Cost**: { gross_margin_trend, operating_margin_trend, drivers[], initiatives[], citations[] }
  - **NB-14 Synthesis**: Narrative fields + structured hooks (primary_accountability, commitments_status[], dependencies[], constraints[])
  - **NB-15 Strategic Inflection**: categorical assessments (0–20 band hooks), narrative rationale, citations[]
- All schemas validated on receipt; auto-repair loop prompts model to re-emit valid JSON if invalid.

---

## 14) Retrieval & Chunking
- Perplexity Search API: query templates per NB and general company-level queries; respect allow/deny lists.
- For URLs: fetch + sanitize (readability mode), split into chunks (~1–2k tokens), store with hash.
- For uploads: PDF/DOCX text extraction; chunk and hash; link to company & run.
- Retrieval for prompts: assemble k-best chunks per NB with citation IDs.

---

## 15) Reuse & Freshness
- Freshness window (default 30 days) per company; override with "Force refresh".
- Comparison uses Customer latest snapshot (within window) + Target snapshot (within window or refreshed), then assembles combined comparison report.

---

## 16) Cost Estimator & Controls
- Estimator formula: (#NB × avg tokens per NB × provider price) + retrieval overhead (per k tokens of chunked text consulted).
- Display provider-specific estimates (e.g., Perplexity search calls + LLM tokens).
- Admin thresholds: **warn** (requires confirmation) and **hard stop** (blocks run).
- Telemetry calibrates estimator using historical variance.

---

## 17) Error Handling
- Network/API: exponential backoff, max attempts, circuit-breaker to prevent retry storms.
- Validation: if JSON invalid, re-ask model to output valid JSON; on repeated failure, mark NB failed with actionable message.
- Source errors: log and continue; present "missing sources" list post-run.
- Partial completion allowed; report marks incomplete NB blocks.

---

## 18) Security
- API keys stored in plugin settings (admin-only), encrypted at rest.
- All calls server-side; no keys in browser.
- Access logs for runs and settings changes.

---

## 19) UI/UX (TSX parity)
- **Dashboard**: New Report; Saved Reports table (Customer–Target, last run, versions, actions).
- **Run**: Customer and Target pickers (saved entries), Sources panel (Auto-found list with approve/reject; Upload; Add URL), Estimator widget, Run button, Progress meter for NB-1..NB-15.
- **Report**: TSX-style sections; per-block citations (expand for details); Version dropdown; "Show changes since previous" toggle.
- **Settings (Admin)**: API keys, allow/deny domains, freshness window, cost caps, JSON strictness toggle.

---

## 20) QA Test Plan (maps to AC)
1. Validate JSON schema conformance for each NB; inject malformed responses to confirm auto-repair then failure path.
2. Remove a source pre-run; confirm it never appears in citations and retrieval.
3. Run fresh Customer+Target; then add a second Target using same Customer; verify token reduction and telemetry capture.
4. Re-run for same company; verify snapshot + diff entries; UI shows changes.
5. Estimator vs. actuals within ±25% for synthetic corpora in 4/5 trials.
6. Load tests: confirm P95 runtime under 15 min on standard corpus; report partial completion behavior.

---

## 21) Implementation Checklist
- [ ] DB migrations for all tables (company, source, run, nb_result, snapshot, diff, comparison, settings, telemetry).
- [ ] Admin settings UI with encrypted key storage and validation.
- [ ] API clients: Perplexity (search), LLM provider(s) (JSON extraction), URL fetcher, file text extractor.
- [ ] SourceService: discovery, uploads, URL ingestion, chunking, dedupe, citation registry.
- [ ] NB schemas (15 files) + orchestrator with validation and repair loop.
- [ ] Assembler: TSX-style HTML renderer.
- [ ] VersioningService: snapshot creation, diff computation, UI integration.
- [ ] CostService: estimator, actuals, thresholds.
- [ ] UI pages: dashboard, run, report, settings (with AMD modules for interactions).
- [ ] Job queue integration with retry/backoff; progress updates.
- [ ] Logging/telemetry wiring; metrics dashboard (basic table/charts later).
- [ ] QA harness: schema validators, synthetic corpora, load scripts.

---

## 22) Deployment & Rollout
- Feature flag in plugin settings (enable/disable access).
- Stage in dev → staging → production with seeded test companies.
- Post-deploy watch: error rates, token spend, runtime metrics; adjust thresholds.

---

## 23) Open Questions (tracked)
- Provider mix specifics (model versions, pricing profiles) — configure defaults, allow override.
- Domain allow/deny seed lists — initial set to be provided by product.
- Exact TSX section IDs and prompts — confirm final mapping from the attached file; implement one-to-one labels.

---

## 24) Appendices
### 24.1 NB Schema Template (illustrative)
{
  "type": "object",
  "required": ["summary", "key_points", "citations"],
  "properties": {
    "summary": {"type": "string"},
    "key_points": {"type": "array", "items": {"type": "string"}},
    "metrics": {"type": "array", "items": {"type": "object", "properties": {"name": {"type": "string"}, "value": {"type": "string"}, "period": {"type": "string"}}}},
    "citations": {"type": "array", "items": {"type": "object", "required": ["source_id"], "properties": {"source_id": {"type": "integer"}, "quote": {"type": "string"}, "page": {"type": "string"}, "url": {"type": "string"}}}}
  }
}

### 24.2 Diff JSON Example (per NB)
{
  "nb_code": "NB5",
  "changed": {
    "gross_margin_trend": {"from": "↑", "to": "↓"}
  },
  "added": {
    "initiatives": ["SG&A reduction wave 2"]
  },
  "removed": {
    "initiatives": ["Inventory rationalization Q1"]
  },
  "citations": {
    "added": [1234],
    "removed": [1201]
  }
}

