# Context-Engineered Project Setup for Claude Code, Vercel, and Other AI Dev Tools

**Date:** 2025-10-17

This document defines the full Context Engineering + Vibe Coding protocol for safe, predictable, and high-quality AI development inside Vercel or any similar environment (Claude Code, Cursor, Windsurf, etc.).  
It is designed to prevent messy code generation, unwanted overwrites, hallucinated APIs, or unapproved commits while maintaining rapid iteration speed.

---

## 🧭 GLOBAL CONTEXT

You are an AI coding assistant operating inside a repository deployed on **Vercel**.  
The standard stack is **Next.js (App Router)**, **TypeScript**, **ESLint + Prettier**, **Zod**, **Vitest or Jest**, **Playwright (optional)**, and optionally **Prisma + SQLite/Postgres** or **Supabase**.

Your workflow must follow the **Context Engineering** model:
1. **Plan before coding**
2. **Implement in safe, test-backed slices**
3. **Never overwrite or delete without explicit approval**

---

## 🧩 ABSOLUTE SAFETY RULES

1. **WRITE LOCK until plan approval**
   - Read the repo and `/docs/initial.md` (or propose one if missing).
   - Generate a PRP (Product Requirements Prompt).
   - Produce a file-by-file plan and await explicit human `PROCEED` approval.

2. **NO COMMITS BY YOU**
   - Only propose commits; a human executes them.

3. **ALLOWED PATHS ONLY**
   - `/src`, `/app`, `/components`, `/lib`, `/styles`, `/public`, `/tests`, `/docs`, `/scripts`, `/prisma`, `/.claude`, `/.github`.

4. **NEVER DELETE USER FILES**
   - Always back up modified files with timestamp suffixes before replacing.

5. **NO SECRETS**
   - Use `.env.example` and document environment variables only.

6. **SCHEMA CHANGES THROUGH MIGRATIONS**
   - Use `prisma migrate` or approved migration process; never direct-destructive edits.

7. **API INTEGRITY**
   - Verify from docs; if uncertain, create typed adapters and TODO placeholders.

8. **TEST FIRST, THEN IMPLEMENT**
   - Write tests for any meaningful logic before implementation.

9. **REVERSIBLE FEATURES**
   - Use feature flags for experimental features.

10. **LIMITED REWRITES**
   - No multi-file edits >200 LOC without explicit design approval.

---

## 📁 RECOMMENDED DIRECTORY STRUCTURE

```
.claude/
  ├── rules.md
  ├── commands/
  │   ├── generate-prp.md
  │   └── execute-prp.md
docs/
  ├── initial.md
  ├── prp/
  ├── DECISIONS.md
  ├── SETUP.md
  ├── TESTING.md
src/ or app/
components/
lib/
styles/
public/
tests/
scripts/
prisma/
```

---

## 🧠 WORKFLOW OVERVIEW

### Phase 0 — Discovery
- Read repo tree and summarize current state.
- Identify conflicts and assumptions.
- Confirm stack (Next.js + TS + etc.).

### Phase 1 — Initial Feature Definition (`initial.md`)
- Propose `/docs/initial.md` if not present.
- Include: Goal, Non-Goals, References, APIs, Acceptance Criteria, Performance, Deployment notes.

### Phase 2 — Generate PRP (`/generate-prp path/to/initial.md`)
- Create detailed Product Requirements Prompt with:
  - Executive summary
  - Architecture overview
  - File plan (each file, purpose, path)
  - Data model and API contracts
  - Testing strategy
  - Performance & rollout plan
  - Risks and mitigations

### Phase 3 — Approval & Task Planning
- Present PRP summary + file operations + task list.
- Wait for `PROCEED`.

### Phase 4 — Safe Implementation
- Execute only approved slices.
- Provide unified diffs, test changes, and exact commands.
- Always link each change to a test and a proposed commit message.

---

## 🧱 VERCEL-SPECIFIC RULES

- Use Node.js runtime by default unless Edge required.
- Document env vars in `.env.example` and `/docs/SETUP.md`.
- Use `next.config.js` for allowed image domains.
- Require preview deploy review before production.
- Add health endpoints and error boundaries.
- Document caching/ISR revalidation rules.

---

## 🎯 CODE QUALITY

- TypeScript strict mode ON.
- ESLint + Prettier configured once; no churn.
- Validation via Zod.
- Small, typed components (server by default).
- A11y compliance enforced.

---

## 🧪 TESTING STANDARDS

- Unit + integration + e2e coverage.
- Include CI workflow (`.github/workflows/ci.yml`).
- Coverage thresholds documented in `/docs/TESTING.md`.

---

## 🗃️ DOCUMENTATION STANDARDS

- `/docs/SETUP.md` — local + Vercel setup.
- `/docs/DECISIONS.md` — architecture decisions.
- `/docs/TESTING.md` — test instructions.
- `/docs/CHANGELOG.md` — semantic version tracking.

---

## 🚦 APPROVAL CHECKLIST BEFORE ANY CODE CHANGE

1. Confirm path is allowlisted.
2. Show unified diff + backup plan.
3. List new/updated tests.
4. Provide local commands.
5. Provide proposed commit message.
6. Wait for `PROCEED`.

---

## 🧩 CUSTOM COMMAND FILES (for Claude Code)

**`/.claude/commands/generate-prp.md`**
```
Generate a Product Requirements Prompt (PRP) from an initial.md file.
Steps:
1. Read initial.md
2. Analyze examples/docs
3. Produce a PRP with architecture, files, risks, and tests.
Do NOT write files yet.
```

**`/.claude/commands/execute-prp.md`**
```
Execute an approved PRP file.
1. Load PRP and present full task list.
2. Request 'PROCEED' confirmation.
3. Implement one slice at a time with diffs, tests, and commit messages.
4. Stop after each slice for review.
```

---

## 🛡️ ANTI-HALLUCINATION PROTOCOL

1. Never assume API details — use mocks or adapters.
2. Reference only mainstream, documented libraries.
3. Prefer smaller, verified steps.
4. Always write tests for edge cases first.
5. Sanitize inputs and outputs.
6. Never call unknown endpoints.

---

## 🤝 HUMAN AGREEMENTS

- Stop after every plan/diff.
- Never self-approve or self-commit.
- Keep `/docs/DECISIONS.md` and `/docs/CHANGELOG.md` current.
- Propose semantic version increments for releases.

---

## 🧾 DEFAULT INITIAL.MD TEMPLATE

```md
# [Feature Name]

## Objective
Describe what this feature does and why it matters.

## Non-Goals
List what is *not* in scope.

## Stack/Baseline
Next.js + TypeScript + ESLint + Prettier + Vitest/Jest + Prisma/Supabase.

## References
List URLs, local example files, and any design docs.

## Data & APIs
Describe inputs, outputs, and contracts.

## Security/Compliance
List any relevant requirements.

## Acceptance Criteria
Given/When/Then statements.

## Performance & Observability
Define metrics, budgets, and logging.

## Deployment Notes
Include Vercel or hosting-specific configs.

## Risks
List assumptions and unknowns.
```

---

## 🧩 HOW TO START

1. Read repo and locate `/docs/initial.md`.
2. If missing, propose one.
3. Run `/generate-prp path/to/initial.md`.
4. Present PRP and await approval.
5. Execute incrementally with `/execute-prp path/to/prp.md`.

---

This document must remain **read-only and version-controlled**.  
Do not modify without explicit approval.
