# AGENT.md — Engineering Standards & Operational Protocols

> **Mandatory Agent Protocol**: Every autonomous coding agent or developer working in this repository **MUST** read all six core architecture documents before modifying code:
> 1. [`/docs/01_PRD.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/01_PRD.md)
> 2. [`/docs/02_AppFlow.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/02_AppFlow.md)
> 3. [`/docs/03_UI_Spec.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/03_UI_Spec.md)
> 4. [`/docs/04_TRD.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/04_TRD.md)
> 5. [`/docs/05_Schema.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/05_Schema.md)
> 6. [`/docs/06_Plan.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/06_Plan.md)

---

## 1. Golden Rules of Engineering

1. **Current Implementation is the Source of Truth**: Existing documentation, comments, or external tickets are historical evidence. Always inspect the active PHP classes, Python endpoints, migrations, and automated tests before making assumptions.
2. **Zero-Implicit-Tenant Invariant**:
   - Every database query, model creation, or retrieval request MUST be strictly scoped to `workspace_id`.
   - Never default to workspace `1` or assume an implicit tenant ID.
   - For Superadmins, a valid explicit target `workspace_id` is mandatory when creating or filtering resources; failing to provide one must immediately fail closed with a validation error.
3. **Resource-Level Authorization**:
   - Never rely solely on high-level route group middleware. DataTables AJAX dispatchers and sub-actions must explicitly check resource permissions (`faq.view`, `knowledge_document.view`, etc.) and return HTTP 403 on failure.
4. **No Unsanctioned Schema Mutations**:
   - Never modify database schemas, create migrations, or run destructive artisan commands (`migrate:fresh`, `db:wipe`) unless explicitly commanded by the user.
5. **Continuous Plan Maintenance**:
   - After completing any implementation or refactoring task, update [`/docs/06_Plan.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/06_Plan.md) to reflect the new state, verification tests, and evidence.

---

## 2. Folder Responsibilities

| Directory | Architectural Responsibility |
| :--- | :--- |
| `app/Http/Controllers/` | Thin HTTP dispatchers; handles request validation, permission checks, and JSON/View returns. Delegates business logic to Services. |
| `app/Services/` | Core domain and application services (`AI/`, `FAQ/`, `Knowledge/`, `Analytics/`, `Memory/`). All multi-step workflows reside here. |
| `app/AI/Routing/` | Semantic intent routing (`HybridRouter.php`). Deterministic LLM JSON classification into the 6 capabilities. |
| `app/AI/Agents/` | Autonomous agent orchestrators (`KnowledgeSupportAgent`, `ConversationalSupportAgent`). |
| `app/Models/` | Eloquent ORM models with explicit relationship definitions, cast definitions, and tenant scopes. |
| `resources/views/admin/` | Blade templates adhering to the Modern Premium Enterprise design system. |
| `resources/scss/admin/` | Modular SCSS tokens, mixins, and component styling. |
| `tests/Feature/` & `tests/Unit/` | Comprehensive test suite enforcing tenant isolation, race condition safety, and API contracts. |

---

## 3. Database & Eloquent Rules

1. **No In-Memory Collection Overloads**: For DataTables and tabular queries, always pass the Eloquent / Query Builder directly (`DataTables::of($query)`). Never call `->get()` or load unbounded collections into PHP memory.
2. **Soft Deletes**: FAQs and Knowledge Documents utilize soft deletes (`SoftDeletes`). Ensure queries handle trashing semantics and restoration (`->withTrashed()`) where appropriate.
3. **Foreign Key Integrity**: Always enforce cascading and index constraints on `workspace_id` across all relational migrations.

---

## 4. Security & Safety Protocols

1. **AST-Enforced Analytics**: The Python analytics service must parse and validate queries using the AST compiler. Never pass raw user prompts directly to `DB::raw()` or unconstrained SQL engines.
2. **Two-Phase Action Safety**: Any action mutating real-world state (e.g. sending seller emails) requires a two-phase commit with SHA-256 fingerprint verification between turns.
3. **Typesense Index Concurrency**: Updates and deactivations must acquire per-record locks to avoid resurrecting deleted/inactive documents during in-flight queue jobs.

---

## 5. Testing & Verification Requirements

Before reporting any task complete:
1. Run the targeted feature and regression test suite:
   ```bash
   php -d memory_limit=512M artisan test tests/Feature/KnowledgeBaseHubTest.php tests/Feature/KnowledgeDocumentTenantIsolationTest.php tests/Feature/FAQTenantAndManagementTest.php tests/Feature/FAQJobRaceConditionTest.php
   ```
2. Verify that **all assertions pass with 0 failures**.
3. Check `git status` to ensure no accidental temporary files, credentials, or untracked test artifacts remain.