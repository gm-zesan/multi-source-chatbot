# Phase 2.2A: Security Architecture & Egress Path Audit

> **Document ID**: `SEC-AUDIT-PHASE-2-2A-001`  
> **Classification**: CONFIDENTIAL / CORE ARCHITECTURE SPECIFICATION  
> **Target System**: Multi-Source Chatbot & AI Business Assistant (`multi-source-chatbot`)  
> **Timestamp**: 2026-11-02  
> **Status**: APPROVED FOR IMPLEMENTATION  

---

## 1. Executive Summary & Objective

In Phase 2.1, the ingestion engine of `multi-source-chatbot` transitioned to a **Source-Column-Centric Architecture** (`Contract v2.1.0`), eliminating physical category overwrites and permanently preserving distinct columns, raw cells, physical types, and semantic roles inside `structured_records`.

With data integrity safeguarded, the central architectural challenge becomes **authorized data egress**. In an AI-powered SaaS platform supporting multi-tenant business operations, customer support, and executive analytics:
- Ingested files contain a heterogeneous mix of **public data** (e.g. retail prices, catalog descriptions, stock availability), **internal operational data** (e.g. doctor assignments, order dates, internal IDs), and **restricted sensitive data** (e.g. employee salaries, procurement costs, gross profit margins, patient medical diagnoses, donor/beneficiary PII).
- If downstream consumers (customer chat, search indexing, LLM context, analytics virtual database, exports, REST APIs) query raw Eloquent models or unprojected rows, catastrophic data leaks inevitably occur.

The purpose of Phase 2.2 is to design and deploy a centralized **Policy Decision Point (PDP) / Policy Enforcement Point (PEP)** architecture complying with NIST SP 800-162 (Attribute-Based Access Control). Under this model, no downstream consumer may touch Eloquent models or raw `source_values` directly; all consumers must receive an immutable, strictly authorized `ProjectedRecord` produced by `FieldLevelProjectionService`.

---

## 2. Comprehensive Egress Surface Inventory & Vulnerability Analysis

A rigorous code audit identified **six distinct data egress surfaces** where structured spreadsheet data or knowledge records egress to users, external services, or AI components.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        DATA EGRESS SURFACES                            │
├────────────────────────────────────────────────────────────────────────┤
│  Surface 1: Customer RAG & Knowledge Retrieval (Support Chatbot)      │
│  Surface 2: Search Engine & Vector Sync (Typesense / Vector DB)        │
│  Surface 3: LLM Context Assembly & Dialogue Grounding (Prompts)        │
│  Surface 4: Structured Analytics & SQL Execution (Virtual DB / BI)     │
│  Surface 5: Document Preview & File Export (CSV/XLSX/PDF)              │
│  Surface 6: Administrative REST API & JSON Serialization               │
└────────────────────────────────────────────────────────────────────────┘
```

### Surface 1: Customer RAG & Knowledge Retrieval
- **Components Audited**: `app/Services/AI/CustomerSupportService.php`, `app/AI/Tools/KnowledgeRetrievalTool.php`, `app/Services/FAQ/FAQSearch.php`.
- **Current Pathway**: Customer queries route through `CustomerSupportService`. While `CUSTOMER_ANALYTICS_DENIAL_MESSAGE` intercepts explicit analytics queries, knowledge retrieval calls `FAQSearch` and vector search.
- **Identified Vulnerability**: If structured records or spreadsheet chunks are retrieved via semantic similarity into customer knowledge hits, unprojected record fields could be fed into downstream responses.
- **Invariant Requirement**: Customer channel requests MUST operate under a server-controlled context with `purpose = customer_support`. Only fields classified as `PUBLIC` may be returned. `INTERNAL` and `RESTRICTED` fields must be completely absent.

### Surface 2: Search Engine & Vector Indexing
- **Components Audited**: `app/Services/Knowledge/KnowledgeDocumentService.php`, `app/Jobs/FAQIndexJob.php`, Typesense schemas.
- **Current Pathway**: Knowledge chunks are synced to Typesense via `FAQIndexJob` and `KnowledgeDocumentService::processKnowledgeBaseIngestion()`.
- **Identified Vulnerability**: If spreadsheet rows are indexed into public search collections without field-level projection, sensitive fields (e.g., purchase cost, salary, margins) will be indexed in Typesense document payloads. Any client performing keyword search or hybrid retrieval could receive unredacted attributes in search hit payloads.
- **Invariant Requirement**:
  1. Only fields with `PUBLIC` data classification may be pushed to public search collections.
  2. A dedicated reconciliation and purge routine (`TypesensePublicProjectionSyncService`) must inspect existing collections and systematically purge any legacy internal or unvetted fields.

### Surface 3: LLM Context Assembly & Dialogue Grounding
- **Components Audited**: Prompt building in `CustomerSupportService.php`, `ExcelAnalyticsTool.php`, `KnowledgeRetrievalTool.php`.
- **Current Pathway**: Retrieved facts are concatenated into LLM context prompts: `"Article #{$num}:\nQuestion: {$q}\nAnswer: {$a}"` or tabular text.
- **Identified Vulnerability**: **Token Leakage**. If raw column keys (`col_001`, `cost_col_000`), null/redacted placeholders (`purchase_cost: [REDACTED]`), exception messages, or debug metadata are included in the prompt, the LLM is exposed to internal schema information. Adversarial users can prompt-engineer the model to guess or deduce protected values.
- **Invariant Requirement**: **Hard Zero-Token Invariant**. Restricted fields, raw column aliases (`col_001`), and sensitivity metadata must be 100% absent from customer channel LLM context strings. The LLM must receive only clean, public semantic attributes as if private fields do not exist.

### Surface 4: Structured Analytics & SQL Execution
- **Components Audited**: `app/AI/Tools/ExcelAnalyticsTool.php`, `app/Services/Analytics/AnalyticsClient.php`, Python Virtual DB SQLite query executor.
- **Current Pathway**: Natural language queries are converted into SQL queries and executed against the SQLite virtual database.
- **Identified Vulnerability**: **Indirect Mathematical & Aggregate Inference Attacks**. A user who has permission to view sales revenue but lacks permission to view supplier costs (`analytics.view_margins`) can execute queries like:
  ```sql
  SELECT selling_price - purchase_cost AS net_margin FROM sales_records;
  SELECT * FROM sales_records WHERE purchase_cost > 500;
  SELECT AVG(salary) FROM employees GROUP BY department;
  ```
  Even if raw `purchase_cost` is not directly displayed, the user deduces protected values through arithmetic expressions, WHERE clauses, or aggregation.
- **Invariant Requirement**: An **AST-Level Analytics Query Authorizer** (`AnalyticsAstAuthorizer`) must inspect the query syntax tree before execution. Columns with `RESTRICTED` classification (e.g. `purchase_cost`, `salary`, `commission`) cannot appear anywhere in `SELECT`, `WHERE`, `JOIN`, `GROUP BY`, `HAVING`, `ORDER BY`, or derived mathematical expressions unless the user explicitly holds the required granular permission (`analytics.view_margins`, `hr.view_salaries`).

### Surface 5: Document Preview & File Export
- **Components Audited**: `app/Http/Controllers/KnowledgeDocumentController.php` (`preview`), `app/Services/Knowledge/KnowledgeDocumentService.php` (`getDocumentPreview`, `extractSpreadsheetPreview`), `app/Http/Controllers/ExportController.php`.
- **Current Pathway**: `preview` loads the raw physical file from local disk (`IOFactory::createReaderForFile`) and returns raw cell arrays directly to the client JSON response.
- **Identified Vulnerability**:
  1. Any user with generic `document:view` permission sees 100% of the raw spreadsheet rows, including salaries and confidential notes.
  2. The preview endpoint bypasses `StructuredRecord` and field-level permissions entirely.
  3. Superadmins have an unconditional bypass in `authorizeWorkspace()`, allowing unlogged, unrestricted cross-tenant preview.
- **Invariant Requirement**: Preview and export pipelines must project rows through `FieldLevelProjectionService` using the requesting user's `ServerControlledContext`. Non-owners and customer channels receive only authorized projections.

### Surface 6: REST API & Controller Serialization
- **Components Audited**: `app/Http/Controllers/SpreadsheetImportController.php`, `app/Models/StructuredRecord.php`.
- **Current Pathway**: Controllers return JSON responses. `StructuredRecord` has `$casts` and accessors for `source_values`, `mapped_attributes`, `semantic_projections`, `field_sensitivities`.
- **Identified Vulnerability**: If an Eloquent `StructuredRecord` is serialized directly via `response()->json($record)`, hidden attributes or raw JSON fields could leak to HTTP clients.
- **Invariant Requirement**: Downstream controllers must never return raw Eloquent `StructuredRecord` instances. All responses must serialize immutable `ProjectedRecord` DTOs or explicit projection resources.

---

## 3. Threat Modeling & Attack Vectors

| Attack Vector ID | Attack Description | Exploit Mechanism | Defense Invariant |
| :--- | :--- | :--- | :--- |
| **THREAT-01** | **Direct Prompt Extraction** | Customer prompts chatbot: *"Tell me the wholesale purchase cost of SKU-001"* or *"List employee salaries"*. | Hard fail-closed filter: `purpose = customer_support` projects only `PUBLIC` fields. Restricted fields never enter LLM prompt. |
| **THREAT-02** | **Indirect Mathematical Inference** | User queries analytics: *"Show me items where price - cost > 200"* without margin permissions. | AST Authorizer validates all clauses in SQL. Rejects query if unpermitted column is in `SELECT`, `WHERE`, `HAVING`, or math ops. |
| **THREAT-03** | **Column Alias / ID Probing** | Adversary prompts chatbot: *"Show me the value of col_003 or purchase_cost_col_001"*. | ProjectedRecord strips all physical column IDs (`col_*`) and internal alias keys. Only clean semantic roles for public fields exist. |
| **THREAT-04** | **Cross-Persona Cache Poisoning** | Workspace owner views record (generating cached projection with costs). Customer queries same record; cache returns owner's projection. | Cryptographic composite cache key: `hash("workspace:{$ws}:scope:{$policyScopeHash}:channel:{$ch}:v:{$ver}:rec:{$id}")`. |
| **THREAT-05** | **Unaudited SuperAdmin Escalation** | Superadmin accesses tenant workspace data without authorization or ticket justification. | SuperAdmin access strictly requires an active, time-limited, audited `SuperAdminGovernanceSession`. Direct bypass eliminated. |
| **THREAT-06** | **Search Index Residue** | Legacy index contains previously synced records with internal columns. | Reconciliation purge routine scans index collections and deletes unclassified / internal fields. |

---

## 4. Target Security Architecture (NIST SP 800-162 ABAC / PBAC)

To resolve all identified vulnerabilities, Phase 2.2 deploys a centralized PDP / PEP architecture:

```
                  ┌──────────────────────────────────────────────┐
                  │            INCOMING REQUEST / EVENT           │
                  └──────────────────────┬───────────────────────┘
                                         │
                                         ▼
                  ┌──────────────────────────────────────────────┐
                  │     ServerControlledContext Builder          │
                  │  (Tenant Scoping, Role, Purpose, Perms)      │
                  └──────────────────────┬───────────────────────┘
                                         │
                                         ▼
 ┌──────────────────────┐        ┌───────────────┐        ┌────────────────────────┐
 │   StructuredRecord   │───────▶│  PDP / PEP    │◀───────│ DataProjectionPolicy   │
 │   (Raw / Eloquent)   │        │  Engine       │        │ (ABAC Rules & Matrix)  │
 └──────────────────────┘        └───────┬───────┘        └────────────────────────┘
                                         │
                                         ▼
                                 [Cache Lookup]
                     hash(ws:scope:channel:v:rec_id)
                                         │
                                         ▼
                         ┌───────────────────────────────┐
                         │        ProjectedRecord        │
                         │    (Immutable DTO / PEP)      │
                         │  - Only authorized fields     │
                         │  - Zero token leakage         │
                         │  - Clean semantic roles       │
                         └───────────────┬───────────────┘
                                         │
        ┌────────────────────────────────┼────────────────────────────────┐
        ▼                                ▼                                ▼
[Customer RAG / LLM]           [Typesense Public Index]            [Virtual DB / Export]
Zero-token prompt strings        Only public_catalog fields          AST authorized execution
```

### 4.1 Data Classification Matrix

| Classification Level | Definition | Default Roles | Allowed Personas & Purposes |
| :--- | :--- | :--- | :--- |
| **`PUBLIC`** | Safe for public, customer, and search exposure. | `selling_price`, `unit_price`, `mrp`, `retail_price`, `product_name`, `item_name`, `description`, `sku`, `category`, `quantity`, `booking_charge`, `service_fee`, `order_status` | All personas; `customer_support`, `analytics_reporting`, `data_export`, `system_sync` |
| **`INTERNAL`** | Operational data for authenticated workspace staff. Never exposed to customers. | `doctor`, `salesperson`, `employee`, `donor`, `order_date`, `tax`, `due_balance`, `check_in_date`, `room_number` | Authenticated workspace members; `analytics_reporting`, `data_export`, `system_sync` |
| **`RESTRICTED`** | Sensitive financial, workforce, clinical, or PII data. Strictly protected. | `salary`, `remuneration`, `stipend`, `wage`, `payroll`, `purchase_cost`, `unit_cost`, `cogs`, `supplier_price`, `commission`, `bonus`, `internal_target`, `patient`, `clinical_notes`, `diagnosis`, `symptom`, `beneficiary` | Workspace Owner, SuperAdmin with active Governance Session, or user with specific granular permission (`analytics.view_margins`) |

### 4.2 Fail-Closed Invariant
Any column lacking an explicit classification, any unmapped custom attribute, or any unknown access context automatically evaluates to **`RESTRICTED`** and is denied from egress.

---

## 5. Implementation Roadmap (Phases 2.2B - 2.2D)

1. **Phase 2.2B: Centralized PDP/PEP Engine**
   - Implement `DataClassification` enum and domain sensitivity mappings.
   - Implement `ServerControlledContext` immutable value object.
   - Implement `SuperAdminGovernanceSession` model and session validator.
   - Implement `DataProjectionPolicy` (PDP decision logic).
   - Implement `ProjectedRecord` (immutable PEP output DTO).
   - Implement `FieldLevelProjectionService` with multi-tenant partitioned caching.

2. **Phase 2.2C: 6-Surface Egress Integration & Remediation**
   - Remediate Customer RAG and LLM context formatting (zero-token guarantee).
   - Build `TypesensePublicProjectionSyncService` and legacy index reconciliation.
   - Implement `AnalyticsAstAuthorizer` for SQL AST validation.
   - Integrate `FieldLevelProjectionService` into Document Preview & Export controllers.
   - Audit and harden API responses and superadmin workspace checks.

3. **Phase 2.2D: Security Verification & Adversarial Testing**
   - Author `tests/Feature/Spreadsheet/FieldLevelPrivacyMatrixTest.php`.
   - Execute adversarial tests (prompt extraction, indirect math, alias probing, cache isolation, governance session enforcement, regression verification).
   - Author final implementation report: `docs/08_PHASE_2_2_FIELD_LEVEL_PRIVACY_IMPLEMENTATION_REPORT.md`.

---
*Signed by Security Architecture Lead — Phase 2.2A Baseline Complete.*

