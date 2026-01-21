# /execute-prp Command

**Purpose:**  
Execute an approved Product Requirements Prompt (PRP) safely, one slice at a time.

---

## ✅ Function

Implements PRP tasks incrementally, with tests and review checkpoints.

---

## 🧠 Rules and Behavior

1. **Argument:**  
   - One parameter: path to PRP file  
     Example: `/execute-prp docs/prp/feature-name.md`

2. **Execution Flow:**  
   - Read PRP and summarize task list  
   - Request `PROCEED` approval  
   - For each slice:  
     1. Restate the task  
     2. Show unified diff  
     3. Propose tests + commands  
     4. Suggest commit message  
     5. Stop and wait for review

3. **Outputs:**  
   - Updated source files (allowlist only)  
   - Updated `/docs/CHANGELOG.md` & `/docs/DECISIONS.md`  
   - Tests added/updated

---

## ⚠️ Safety Rules

- Never self-approve or commit.  
- Always back up modified files.  
- All behavior must be tested.  
- Stop and clarify if uncertain.

---

## 📋 Example

```
/execute-prp docs/prp/feature-name.md
```
