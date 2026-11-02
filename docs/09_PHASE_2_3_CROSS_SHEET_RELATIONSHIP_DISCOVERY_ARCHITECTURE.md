# Phase 2.3 Architecture Specification: Cross-Sheet Relationship Discovery Engine

> **Document ID**: `ARCH-SPEC-PHASE-2-3-001`  
> **Classification**: CORE ARCHITECTURE SPECIFICATION & DESIGN GATE  
> **Target Systems**: Laravel Backend (`multi-source-chatbot`) & Python AI Service (`python-ai-service`)  
> **Target Version**: Semantic Contract Version `2.3.0`  
> **Author**: Architecture & Security Governance Team  
> **Date**: 2026-11-02  
> **Status**: PROPOSED DESIGN GATE (PENDING REVIEW BEFORE IMPLEMENTATION)  

---

## 1. Executive Summary & Design Scope

In Phase 2.1, the ingestion engine established **Source-Column-Centric Dynamic Semantics**, allowing any single tabular dataset or spreadsheet sheet to be parsed losslessly with domain-agnostic semantic descriptors (`IDENTIFIER`, `MEASURE`, `CURRENCY`, `QUANTITY`, `DATE_TIME`, etc.).

In Phase 2.2, a **Centralized Authorization, Field-Level Privacy Matrix & PDP/PEP Engine** was deployed, mathematically isolating restricted attributes across six egress surfaces and isolating tenants.

However, real-world enterprise workbooks rarely contain a single isolated sheet. A workbook typically represents an entire relational schema spanning multiple sheets:
- **E-Commerce**: `Customers` $\leftrightarrow$ `Orders` $\leftrightarrow$ `Order Items` $\leftrightarrow$ `Products`
- **Healthcare**: `Patients` $\leftrightarrow$ `Appointments` $\leftrightarrow$ `Doctor Schedules` $\leftrightarrow$ `Prescriptions`
- **Human Resources**: `Employees` $\leftrightarrow$ `Departments` $\leftrightarrow$ `Payroll Runs` $\leftrightarrow$ `Timesheets`
- **Non-Profit / NGO**: `Donors` $\leftrightarrow$ `Campaigns` $\leftrightarrow$ `Donations` $\leftrightarrow$ `Beneficiaries`
- **Education**: `Students` $\leftrightarrow$ `Courses` $\leftrightarrow$ `Enrollments` $\leftrightarrow$ `Exam Grades`

### Primary Architectural Invariant
**Strict Domain-Agnosticism**: The system must NEVER hardcode domain lexicons, table names, or column patterns (e.g. `customer_id`, `patient_id`, `donor_id`, `id_col`). The discovery engine must identify candidate Primary Keys (PKs), Foreign Keys (FKs), and relationship cardinalities purely through **statistical distribution, structural containment, value inclusion dependencies (IND), and semantic role heuristics**.

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                      PHASE 2.3 RELATIONSHIP DISCOVERY PIPELINE                          │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                         │
│  Workbook Sheets (S₁, S₂, ..., Sₙ)                                                      │
│                           │                                                             │
│                           ▼                                                             │
│  [Stage 1: Intra-Sheet Candidate Key Profiler]                                          │
│     - Uniqueness Ratio = 1.0, Null Ratio = 0.0, Semantic Role = IDENTIFIER             │
│                           │                                                             │
│                           ▼                                                             │
│  [Stage 2: Cross-Sheet Inclusion Dependency (IND) & Value Overlap]                       │
│     - Set Intersection: |Values(FK) ∩ Values(PK)| / |Values(FK)| ≥ Threshold             │
│                           │                                                             │
│                           ▼                                                             │
│  [Stage 3: Physical & Semantic Alignment]                                               │
│     - Type Compatibility (INTEGER ↔ INTEGER, UUID ↔ UUID)                               │
│     - Header Distance & Entity Alignment                                                │
│                           │                                                             │
│                           ▼                                                             │
│  [Stage 4: Cardinality Inference Engine]                                                │
│     - 1:1 (Unique ↔ Unique), 1:N (Unique ↔ Non-Unique), N:M (Junction Tables)           │
│                           │                                                             │
│                           ▼                                                             │
│  [Stage 5: Confidence Scoring & Ambiguity Decision Gate]                                │
│     - High Confidence (≥ 0.85): Auto-Linked                                             │
│     - Medium Confidence (0.60 – 0.84): Ambiguous (Requires Owner Confirmation)         │
│     - Low Confidence (< 0.60): Discarded                                                │
│                                                                                         │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Mathematical Discovery Algorithm (5-Stage Pipeline)

### Stage 1: Intra-Sheet Candidate Key Profiling
For each sheet $S_i$ in the workbook, inspect all source columns $C_j \in S_i$ to evaluate candidate Primary Key (PK) viability:

$$\text{Viability}(C_j) = \text{UniquenessRatio}(C_j) \times (1.0 - \text{NullRatio}(C_j))$$

A column is designated as a **Candidate Primary Key (PK)** if:
1. $\text{NullRatio}(C_j) = 0.0$ (Primary keys must be strictly non-nullable).
2. $\text{UniquenessRatio}(C_j) \ge 0.99$ (Permits negligible row trailing anomalies in human-edited spreadsheets).
3. Prior boost: If Phase 2.1 tagged the column as `ColumnSemanticRole::IDENTIFIER`, key confidence score is weighted positively.

### Stage 2: Cross-Sheet Value Overlap & Inclusion Dependency (IND)
For every sheet pair $(S_A, S_B)$ where $C_A \in S_A$ is a Candidate PK and $C_B \in S_B$ is an arbitrary column:

1. **Physical Type Match**: The physical data types must match or be coercible:
   $$\text{TypeCompat}(C_A, C_B) = \begin{cases} 1.0 & \text{if } \text{Type}(C_A) = \text{Type}(C_B) \\ 0.8 & \text{if both are numeric or both are string} \\ 0.0 & \text{otherwise} \end{cases}$$
2. **Inclusion Dependency Ratio ($S_{\text{IND}}$)**:
   The proportion of distinct foreign values present in the primary key set:
   $$S_{\text{IND}}(C_B \subseteq C_A) = \frac{|\text{DistinctValues}(C_B) \cap \text{DistinctValues}(C_A)|}{|\text{DistinctValues}(C_B)|}$$
   - If $|\text{DistinctValues}(C_B)| = 0$, $S_{\text{IND}} = 0.0$.
   - A high inclusion ratio ($\ge 0.85$) is strong mathematical evidence that $C_B$ is a foreign key referencing $C_A$.

### Stage 3: Header Token & Entity Semantic Similarity
To distinguish genuine relationships from coincidental integer overlap (e.g. sequential IDs $1, 2, 3$):
1. **Header Token Similarity ($S_{\text{header}}$)**:
   - Normalize headers: `trim(strtolower(regex_replace('/[^a-zA-Z0-9]/', '_', header)))`.
   - Compute Jaro-Winkler distance and token containment between:
     - Header $C_B$ and Header $C_A$
     - Header $C_B$ and Sheet Name $S_A$ (e.g., sheet `Customers` and column `customer_id` or `client_code`).
2. **Entity Alignment ($S_{\text{entity}}$)**:
   - If Phase 2.1 assigned an `entity_type` to $S_A$ (e.g. `patient`), and $C_B$'s descriptor refers to the same entity, $S_{\text{entity}} = 1.0$.

### Stage 4: Cardinality Inference
Once a directional relationship $C_B \to C_A$ is identified:
- **One-to-Many ($1:N$)**: If $\text{UniquenessRatio}(C_A) \ge 0.99$ in $S_A$ and $\text{UniquenessRatio}(C_B) < 0.90$ in $S_B$.
  *(Standard parent-child relationship: One customer, multiple orders).*
- **One-to-One ($1:1$)**: If $\text{UniquenessRatio}(C_A) \ge 0.99$ and $\text{UniquenessRatio}(C_B) \ge 0.99$.
  *(Extended detail sheet: One order, one shipping detail).*
- **Many-to-Many ($N:M$)**: If sheet $S_B$ has multiple foreign keys referencing distinct tables and few measures, $S_B$ is classified as a **Junction Table** (e.g., `StudentCourses`, `OrderItems`).

### Stage 5: Multi-Factor Confidence Formulation
The total confidence score is a weighted linear combination:

$$\text{Confidence}(C_B \to C_A) = w_{\text{IND}} \cdot S_{\text{IND}} + w_{\text{header}} \cdot S_{\text{header}} + w_{\text{type}} \cdot S_{\text{type}} + w_{\text{entity}} \cdot S_{\text{entity}}$$

**Recommended Weights**:
- $w_{\text{IND}} = 0.50$ (Actual value containment is the strongest mathematical evidence)
- $w_{\text{header}} = 0.25$ (Naming alignment provides contextual corroboration)
- $w_{\text{entity}} = 0.15$ (Semantic entity consistency)
- $w_{\text{type}} = 0.10$ (Type compatibility precondition)

#### Decision Gate Thresholds

| Confidence Score | Classification | System Action |
|---|---|---|
| **$\text{Confidence} \ge 0.85$** | **CONFIRMED_HIGH** | Auto-staged as primary relationship; active in default query plan. |
| **$0.60 \le \text{Confidence} < 0.85$** | **AMBIGUOUS_MEDIUM** | Flagged for **Mandatory Workspace Owner Confirmation**; blocked from automated query plan until confirmed. |
| **$\text{Confidence} < 0.60$** | **REJECTED_LOW** | Discarded automatically; suppressed from user interface to avoid noise. |

---

## 3. Data Model & Schema Representation (Additive & Non-Breaking)

### 1. DTO Specification: `SpreadsheetRelationshipGraph`

Represented symmetrically in Laravel (`App\Services\Spreadsheet\DTOs\`) and Python (`app/analytics/semantic_schema.py`):

```json
{
  "graph_version": "2.3.0",
  "document_id": "9d8e7f6a-1234-4567-890a-bcdef1234567",
  "workspace_id": 1,
  "nodes": [
    {
      "sheet_name": "Customers",
      "primary_key": "customer_id",
      "row_count": 500,
      "entity_type": "client"
    },
    {
      "sheet_name": "Orders",
      "primary_key": "order_number",
      "row_count": 2500,
      "entity_type": "transaction"
    }
  ],
  "edges": [
    {
      "id": "rel_cust_orders_001",
      "source_sheet": "Orders",
      "source_column_id": "col_002",
      "source_header": "Client Code",
      "target_sheet": "Customers",
      "target_column_id": "col_000",
      "target_header": "Customer ID",
      "cardinality": "N:1",
      "confidence": 0.92,
      "inclusion_ratio": 0.98,
      "status": "confirmed",
      "confirmed_by": null,
      "confirmed_at": null
    }
  ],
  "ambiguities": []
}
```

### 2. Persistence Layer
- **No breaking database migrations required**.
- Persisted within `spreadsheet_import_jobs.detected_schema->relationships` JSON column in Laravel.
- Exported symmetrically in `python-ai-service` via `TableSemanticProfile.candidate_relationships`.

---

## 4. Security & Privacy Boundary Integration (PDP/PEP Boundary)

Connecting multiple sheets creates potential **lateral data leakage paths** if not strictly guarded by Phase 2.2 security invariants.

### 1. Strict Tenant Boundary Isolation
- Relationships may NEVER cross workspaces:
  $$\text{Workspace}(S_A) === \text{Workspace}(S_B) === \text{Context}->\text{workspaceId}$$
- Any candidate match referencing another workspace is rejected with `TenantScopeViolationException`.

### 2. Field-Level Classification Propagation & Join Isolation
Even when Sheet A and Sheet B have a confirmed $1:N$ relationship:
- Joining Sheet A with Sheet B in an analytics query **does not merge their authorization contexts**.
- If Sheet A is `PUBLIC_CATALOG` (Products) and Sheet B is `CONFIDENTIAL_FINANCIAL` (Procurement Invoices), querying `SELECT products.name, invoices.cost` requires explicit `analytics.view_margins` or `analytics.view_financial` permissions.
- The `AnalyticsAstAuthorizer` must inspect all joined table references and verify that the user's `ServerControlledContext` possesses authorization for *every* projected and referenced column across all joined sheets.

### 3. Customer RAG Multi-Sheet Boundary
- Customer channel queries (`purpose = customer_support`) are strictly restricted to tables containing `PUBLIC_CATALOG` attributes.
- If a related child sheet contains internal operational or workforce data (e.g. `Doctor Notes`, `Commission Slips`), the join edge is pruned from customer retrieval indexing.

---

## 5. Owner Confirmation Strategy & Ambiguity Resolution Workflow

### Workflow State Machine

```
  [Detected Candidates]
            │
            ├──────────────────────────────────────────┐
            ▼ (Confidence ≥ 0.85)                      ▼ (0.60 ≤ Confidence < 0.85)
     [CONFIRMED_HIGH]                          [AMBIGUOUS_MEDIUM]
            │                                          │
            │                                          ▼
            │                              [Pending Owner Confirmation]
            │                                          │
            │                        ┌─────────────────┴─────────────────┐
            │                        ▼ (Owner Approves)                  ▼ (Owner Rejects)
            └─────────────────► [ACTIVE RELATIONSHIP]            [REJECTED_PRUNED]
                                     │
                                     ▼
                      [Consumed by Phase 3 Virtual DB]
```

### API Endpoints for Confirmation
- `GET /documents/{document}/spreadsheet/relationships`: Lists all detected edges, confidence scores, and pending ambiguities.
- `POST /documents/{document}/spreadsheet/relationships/confirm`: Confirms, rejects, or manually overrides candidate foreign key mappings.
- **Generation Fencing**: Reuses Phase 2.0 `generation_id` locking to prevent asynchronous workers from overwriting manual owner confirmations.

---

## 6. Preparation & Handoff to Phase 3 (Structured Analytics Engine)

Once the `SpreadsheetRelationshipGraph` is established:
1. **Virtual Relational Schema**: The Python Virtual Database service can automatically construct an in-memory SQLite schema with actual `FOREIGN KEY (source_col) REFERENCES target_sheet(target_col)` constraints.
2. **Text-to-SQL JOIN Path Resolver**: When a natural language question involves metrics from multiple sheets (e.g. *"Show total revenue by customer city"*), the query compiler traverses the relationship graph using Dijkstra's shortest path algorithm to generate deterministic `LEFT JOIN` clauses.
3. **AST Query Validation**: `AnalyticsAstAuthorizer` verifies that all joins follow confirmed graph edges, blocking unauthorized Cartesian products (`CROSS JOIN`) or unapproved join paths.

---

## 7. Design Gate Acceptance Checklist

Before functional code implementation begins for Phase 2.3, the following gates must be confirmed:

- [x] Strict Domain-Agnosticism invariant defined (zero hardcoded column names).
- [x] Mathematical inclusion dependency (IND) and uniqueness profiling formulas established.
- [x] 5-factor confidence scoring formula and decision thresholds (0.85 / 0.60) calibrated.
- [x] Additive JSON schema representation specified without breaking migrations.
- [x] Integration with Phase 2.2 Security PDP/PEP and `AnalyticsAstAuthorizer` established.
- [x] Generation fencing and owner confirmation workflow aligned with Phase 2.0.
- [x] Testing strategy defined targeting disposable MySQL and SQLite test fixtures.

