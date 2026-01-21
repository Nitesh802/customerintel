# /generate-prp Command

**Purpose:**  
Generate a complete Product Requirements Prompt (PRP) from an `initial.md` file before any coding begins.

---

## ✅ Function

This command analyzes an `initial.md` specification and produces a detailed PRP that defines *exactly* what will be implemented — including architecture, file paths, models, dependencies, and risk mitigations.

---

## 🧠 Rules and Behavior

1. **Read-only phase:**  
   Do *not* modify, delete, or create files yet.

2. **Argument:**  
   - One parameter: path to `initial.md`  
     Example: `/generate-prp docs/initial.md`

3. **Output:**  
   - Creates `/docs/prp/[feature-name].md` with full PRP plan.

4. **PRP Must Include:**  
   - Executive Summary  
   - Architecture Overview  
   - File Plan (each file + purpose)  
   - APIs & Data Models  
   - Testing Strategy  
   - Performance Targets  
   - Rollout Plan  
   - Risks, Unknowns, and Mitigation  
   - References

5. **Approval Workflow:**  
   Present PRP summary → wait for human approval → only then proceed.

---

## ⚠️ Warnings

- Do not hallucinate APIs — cite docs or stub with TODO.  
- Do not generate code in this step.  
- Flag missing examples or unclear instructions.

---

## 📋 Example

```
/generate-prp docs/initial.md
```
