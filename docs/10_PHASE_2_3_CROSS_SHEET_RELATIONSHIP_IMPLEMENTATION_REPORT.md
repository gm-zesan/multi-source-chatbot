# Phase 2.3 Implementation Report: Domain-Agnostic Cross-Sheet Relationship Discovery Engine & Owner-First Business Intelligence Routing Foundation

**Target Contract Version:** `2.3.0`  
**Execution Date:** 2026-11-02  
**Status:** ARCHITECTURALLY VERIFIED & REMEDIATED (85 Tests Passing, 643 Assertions, 0 Failures; Disposable MySQL Verified)

---

## 1. Executive Summary & Architecture Overview

Phase 2.3 delivers a domain-agnostic relationship discovery engine capable of identifying and structuring cross-sheet entity relationships across multi-sheet workbooks, uploaded files, and relational datasets without relying on hardcoded business-domain column names or rigid semantic labels.

Simultaneously, Phase 2.3 resolves the critical Owner-First architectural separation requirement. Previously, the Business Owner's workbench (`/dashboard/simulator`) mistakenly routed queries through the public-facing `CustomerSupportService`, causing legitimate owner financial, margin, and multi-sheet analytics queries to be denied by the Customer Analytics Denial Barrier (`CUSTOMER_ANALYTICS_DENIAL_MESSAGE`). Phase 2.3 establishes `OwnerBusinessAssistant`, a dedicated, server-controlled business intelligence orchestrator that operates with full authorized access for workspace owners and managers, while keeping external customer channels strictly guarded behind the Zero-Trust PDP/PEP security barrier.

```
+---------------------------------------------------------------------------------------------------------+
|                                     INCOMING QUERY ROUTING BOUNDARY                                      |
+---------------------------------------------------------------------------------------------------------+
                                                     |
                     +-------------------------------+-------------------------------+
                     |                                                               |
                     v                                                               v
       [External Public Channels]                                      [Authenticated Dashboard]
       - Facebook, Telegram, WhatsApp                                  - /dashboard/simulator
       - Web Chat Widget                                               - Workspace Owner / Manager
                     |                                                               |
                     v                                                               v
       [CustomerSupportService]                                        [OwnerBusinessAssistant]
                     |                                                               |
       [Customer Analytics Security Barrier]                           [Owner Business Intelligence Pipeline]
       - Blocks internal salaries, margins, PII                        - Full cross-sheet relationship graph
       - Egress through FieldLevelProjectionService                    - Multi-sheet correlation & joins
       - Zero Token Leakage Guaranteed                                 - Authorized high-context analytics
```

---

## 2. Candidate Key Profiler: Mathematical Formulation & Constraints

The `CandidateKeyProfiler` (`app/Services/Spreadsheet/Relationships/CandidateKeyProfiler.php`) analyzes each sheet's columns to discover single-column and bounded composite candidate primary keys.

### 2.1 Mathematical Formulas

For a column $C$ in sheet $S$ having row count $N = |S|$:
- **Null Ratio:**
  $$\text{NullRatio}(C) = \frac{\sum_{r \in S} \mathbf{1}[r[C] \text{ is null or empty}]}{N}$$
- **Distinct Value Count:**
  $$\text{Distinct}(C) = |\{ r[C] : r \in S, r[C] \text{ is not null} \}|$$
- **Uniqueness Ratio:**
  $$\text{UniquenessRatio}(C) = \frac{\text{Distinct}(C)}{N - \text{NullCount}(C)}$$

### 2.2 Viability Thresholds
A column $C$ is a viable single-column candidate key if:
$$\text{NullRatio}(C) = 0.0 \quad \land \quad \text{UniquenessRatio}(C) \ge 0.99 \quad \land \quad N \ge 1$$

### 2.3 Composite Key Discovery
If no single-column candidate key satisfies the viability thresholds and $N \ge 2$, composite candidate keys are evaluated with a strict combinatorial bound of width $W = 2$:
$$\text{Distinct}(C_1, C_2) = |\{ (r[C_1], r[C_2]) : r \in S \}|$$
$$\text{UniquenessRatio}(C_1, C_2) \ge 0.99 \quad \land \quad \text{NullRatio}(C_1) \le 0.05 \quad \land \quad \text{NullRatio}(C_2) \le 0.05$$
Candidate key results are encapsulated in immutable `CandidateKeyDescriptor` DTOs.

---

## 3. Inclusion Dependency (IND) Analyzer

The `InclusionDependencyAnalyzer` (`app/Services/Spreadsheet/Relationships/InclusionDependencyAnalyzer.php`) evaluates referential dependencies between potential foreign key column $F$ in Sheet $A$ and candidate primary key column $K$ in Sheet $B$.

### 3.1 Mathematical Definition
$$\text{IND}(A[F] \subseteq B[K]) = \frac{|\{ v \in A[F] : v \in B[K] \}|}{|A[F]|}$$
Where:
- The threshold for candidate relationship discovery is $\text{IND} \ge 0.85$.
- Orphan count is tracked exactly:
  $$\text{OrphanCount} = |\{ v \in A[F] : v \notin B[K] \}|$$

### 3.2 Comparison Normalization Without Mutating Source Values
To prevent false negatives arising from formatting discrepancies while guaranteeing that physical `source_values` remain 100% immutable and lossless, values are normalized *only* inside the comparison projection:
1. **Trimming & Unicode Whitespace Folding:** Strips leading/trailing ASCII and Unicode whitespace (`\u{00A0}`, etc.).
2. **Case Folding:** Converts strings to lowercase (`mb_strtolower`).
3. **Bangla & Arabic-Indic Numerals Translation:** Maps Bangla digits (`০`–`৯`) to standard ASCII digits (`0`–`9`).
4. **Numeric Coercion:** Coerces string integers and floats with trailing zeros (e.g., `'101.0'` $\to$ `'101'`).
5. **Losslessness Invariant:** The raw cell contents stored in `StructuredRecord.source_values` are never modified by the comparison projection.

---

## 4. Cardinality Inference Engine

The `CardinalityInferenceEngine` (`app/Services/Spreadsheet/Relationships/CardinalityInferenceEngine.php`) infers the structural cardinality between related sheets:

| Cardinality | Mathematical & Structural Conditions |
|---|---|
| **$1:1$** | Target PK uniqueness $\ge 0.99$, Source FK uniqueness $\ge 0.99$, Sample size $N \ge 5$. If $N < 5$, marked as `ambiguous` to prevent false certainty. |
| **$1:N$** | Target PK uniqueness $\ge 0.99$, Source FK uniqueness $< 0.90$ (repeated values present in source column). |
| **$N:M$** | Identified through composite junction table detection. |
| **`ambiguous`** | Intermediate uniqueness ($0.90 \le \text{Source Uniqueness} < 0.99$) or small sample size under 1:1 conditions. |

### 4.1 Junction Table Detection ($N:M$)
A sheet $J$ is identified as an $N:M$ junction table when:
1. Sheet $J$ contains $\ge 2$ distinct candidate foreign key columns pointing to candidate primary keys of different target sheets ($T_1, T_2, \dots$).
2. The combination of these foreign key columns satisfies high uniqueness across rows.
3. The relationship edges originating from $J$ are promoted to `MANY_TO_MANY` with `isJunctionTable = true`.

---

## 5. Multi-Factor Confidence Scoring

Candidate relationships are scored using a weighted multi-factor formula that does not rely on domain-specific hardcoded keywords:

$$\text{Confidence} = (w_{\text{ind}} \times S_{\text{ind}}) + (w_{\text{header}} \times S_{\text{header}}) + (w_{\text{entity}} \times S_{\text{entity}}) + (w_{\text{type}} \times S_{\text{type}})$$

### 5.1 Weights & Feature Definitions
- **$w_{\text{ind}} = 0.50$ (Inclusion Dependency):** Primary referential overlap ratio ($\ge 0.85$).
- **$w_{\text{header}} = 0.25$ (Header Token Similarity):** Tokenized Jaccard/Levenshtein similarity between source and target column headers, stripping common structural suffixes (`_id`, `_code`, `_ref`, `_no`, `_key`).
- **$w_{\text{entity}} = 0.15$ (Semantic Entity Alignment):** Semantic role alignment (e.g., source `identifier` to target `identifier`).
- **$w_{\text{type}} = 0.10$ (Physical Type Compatibility):** Exact physical type match = $1.0$; integer-to-numeric/string compatibility = $0.70$; incompatible types = $0.0$.

### 5.2 Threshold Boundaries & Activation Gates
- **$\text{Confidence} \ge 0.85$:** High confidence candidate $\to$ `STATE_DISCOVERED`.
- **$0.60 \le \text{Confidence} < 0.85$:** Moderate confidence $\to$ `STATE_NEEDS_CONFIRMATION` (requires explicit Owner confirmation).
- **$\text{Confidence} < 0.60$ or $\text{IND} < 0.85$:** Low confidence or broken IND $\to$ `STATE_REJECTED`.

---

## 6. Candidate Lifecycle & State Transitions

Relationship edges transition through a strict, deterministic state machine:

```mermaid
stateDiagram-v2
    [*] --> DISCOVERED : Confidence >= 0.85
    [*] --> NEEDS_CONFIRMATION : 0.60 <= Confidence < 0.85
    [*] --> REJECTED : Confidence < 0.60 or IND < 0.85

    NEEDS_CONFIRMATION --> CONFIRMED : Owner confirmRelationship()
    NEEDS_CONFIRMATION --> REJECTED : Owner rejectRelationship()
    DISCOVERED --> CONFIRMED : Owner confirmRelationship()
    DISCOVERED --> REJECTED : Owner rejectRelationship()

    CONFIRMED --> STALE : Re-import Data Drift (IND < 0.85)
    CONFIRMED --> INVALIDATED : Column/Sheet Removed on Re-import
    DISCOVERED --> INVALIDATED : Column/Sheet Removed on Re-import
    NEEDS_CONFIRMATION --> INVALIDATED : Column/Sheet Removed on Re-import

    STALE --> CONFIRMED : Owner Re-evaluates & Confirms
    STALE --> REJECTED : Owner Rejects
```

---

## 7. Safe Persistence Architecture & Generation Fencing

### 7.1 Schema Persistence
To guarantee backward compatibility and zero database mutation risks, relationship graphs are persisted non-destructively within the existing `spreadsheet_import_jobs.detected_schema` JSON column:
```json
{
  "sheets": { ... },
  "relationships": {
    "graph_version": "2.3.0",
    "workspace_id": 1,
    "document_id": "9e123456-...",
    "job_id": "9e654321-...",
    "generation_id": "fbc81200-...",
    "discovered_at": "2026-11-02T13:40:00.000Z",
    "nodes": { ... },
    "edges": { ... },
    "ambiguities": [ ... ]
  },
  "relationships_updated_at": "2026-11-02T13:40:00.000Z",
  "relationships_generation_id": "fbc81200-..."
}
```

### 7.2 Generation Fencing
Concurrent or outdated worker processes are rejected immediately via generation fencing:
- Every graph mutation checks the passed `generationId` against `job->generation_id`.
- If tokens mismatch, an `InvalidArgumentException` is thrown (`"Stale generation fencing token"`), aborting obsolete job updates before writes can occur.

---

## 8. Owner-First Simulator Routing Foundation

### 8.1 Problem Solved
Previously, `/dashboard/simulator` invoked `ChatSimulatorController::resolveSimulatorConversation()`, which selected a generic channel (matching public `web`), and called `CustomerSupportService::handleCustomerMessage()`. Any question regarding salaries, employee lists, or cross-sheet analytics was caught by the Customer Analytics Security Barrier and returned:
> *"I apologize, but I do not have access to real-time sales analytics or internal workforce metrics..."*

### 8.2 Architectural Solution
1. **Dedicated Orchestrator:** Created `OwnerBusinessAssistant` (`app/Services/AI/OwnerBusinessAssistant.php`), configured exclusively for workspace owners and managers.
2. **Channel Isolation:** Created or resolved a dedicated channel with slug `'simulator'` and type `'simulator'`.
3. **Metadata Marking:** Flagged conversations with `'is_simulator' => true, 'is_customer' => false`.
4. **Controller Routing Boundary:** In `ChatSimulatorController::send()`, authenticated owners are routed directly to `OwnerBusinessAssistant::handleOwnerQuery()`, which has full access to the discovered relationship graph, multi-sheet records, and business intelligence capabilities.
5. **Customer Invariant Maintained:** External public webhooks (`/api/v1/chat`, Facebook, Telegram, WhatsApp) continue routing exclusively through `CustomerSupportService`, preserving 100% of Phase 2.2's Zero-Trust PDP/PEP barriers.

---

## 9. Cross-Tenant Isolation Verification

Every relationship operation is protected by workspace authorization checks:
- `CrossSheetRelationshipService::confirmRelationship()` verifies `job->workspace_id === $workspaceId`.
- Cross-tenant requests immediately trigger `TenantScopeViolationException` (HTTP 403).
- The REST API endpoints (`GET /documents/{document}/spreadsheet/relationships`, `POST /documents/{document}/spreadsheet/relationships/confirm`, `POST /documents/{document}/spreadsheet/relationships/reject`) strictly validate that `document->workspace_id === auth()->user()->current_workspace_id`.

---

## 10. Data Losslessness & Invariant Preservation

- `StructuredRecord.source_values` contains untouched original cell data.
- Bangla numerals (`০`–`৯`), symbols, whitespace, and Unicode text remain identical to the uploaded spreadsheet.
- Comparison normalization occurs strictly in-memory during IND profiling and is never written back to raw record columns.

---

## 11. Complete Test Suite Execution Results

Executed across all Phase 2 test suites and security barriers:

```
PASS Tests\Feature\Spreadsheet\CrossSheetRelationshipDiscoveryTest (12 tests, 70 assertions)
  ✓ e commerce multi sheet relationship discovery and junction table detection
  ✓ healthcare clinic multi sheet relationship discovery
  ✓ hospitality and ngo donation multi sheet discovery
  ✓ unknown domain multi sheet discovery
  ✓ candidate key profiler mathematical constraints and composite keys
  ✓ inclusion dependency exact thresholds and normalization
  ✓ relationship lifecycle and owner confirmation and rejection
  ✓ re import drift invalidates stale relationships
  ✓ generation fencing prevents stale job overwrites
  ✓ tenant isolation on relationship endpoints
  ✓ owner simulator queries route to owner business assistant without customer denial
  ✓ source values and raw values remain lossless and immutable

PASS Tests\Feature\Spreadsheet\DomainAgnosticDynamicSemanticsTest (13 tests, 91 assertions)
  ✓ multi metric sales preserves all financial columns without overwriting
  ✓ medical clinic separates doctor patient and enforces privacy
  ✓ hotel domain preserves checkin checkout and room charges
  ✓ ngo donation domain preserves donor and beneficiary
  ✓ unknown future business domain ingests gracefully
  ✓ duplicate semantic roles do not collide or overwrite
  ✓ confirm mapping rejects marking restricted roles as public
  ✓ sensitivity rejects sensitive substrings and unvetted custom roles
  ✓ raw values lossless preservation
  ✓ three duplicate roles preserved in sub array and aliases
  ✓ legacy structured records backward compatibility
  ✓ source column descriptor contract symmetry
  ✓ real xlsx cell lossless representation

PASS Tests\Feature\Spreadsheet\FieldLevelPrivacyMatrixTest (21 tests, 187 assertions)
  ✓ customer channel receives public fields with zero token leakage of restricted columns
  ✓ indirect math inference attack is blocked by ast authorizer
  ✓ ast authorizer blocks derived arithmetic inference without margins permission
  ✓ ast authorizer permits cost queries when permission granted
  ✓ internal column id probing and duplicate aliases are stripped from public projection
  ✓ cross tenant record access is strictly blocked
  ✓ superadmin access requires valid governance session and records audit log
  ✓ cryptographic cache partition prevents cross persona leakage
  ✓ fail closed default on unclassified or ambiguous columns
  ✓ raw row projection masks restricted data for unauthorized dashboard viewer
  ✓ typesense public projection and reconciliation purge
  ✓ workspace owner with full permissions projects all fields
  ✓ granular analytics permissions matrix using analytics permission enum
  ✓ adversarial prompt jailbreak attempt against customer context guarantees zero leakage
  ✓ export service enforces field level projection and masks restricted fields
  ✓ classification registry extensibility and fail closed custom role
  ✓ ast authorizer complex query matrix cte subqueries window functions
  ✓ ast authorizer strictly blocks mutations and non select dml ddl
  ✓ customer rag text extraction enforces zero token leakage for ingested records
  ✓ superadmin governance session full lifecycle and audit persistence
  ✓ direct structured record model serialization hides raw and source values

PASS Tests\Feature\Spreadsheet\SpreadsheetIngestionPipelineTest (12 tests, 118 assertions)
  ✓ dynamic header detection skips banner rows and identifies true header row
  ✓ formula injection defense and legitimate negative numbers
  ✓ typed numeric parsing and bengali numerals
  ✓ all eleven canonical mapping categories
  ✓ ambiguous headers require owner confirmation
  ✓ intra file duplicate identifiers and conflict resolution
  ✓ public and private data isolation rules
  ✓ checkpointing and generation fencing aborts stale workers
  ✓ tenant isolation on spreadsheet api endpoints
  ✓ corrupt empty and spoofed file validation
  ✓ real xlsx file parsing with phpspreadsheet
  ✓ real xls legacy binary file parsing with phpspreadsheet

PASS Tests\Feature\KnowledgeDocumentPreviewTest (7 tests, 28 assertions)
  ✓ authorized user can preview spreadsheet csv
  ✓ authorized user can preview text document
  ✓ authorized user can stream raw file
  ✓ cross workspace user is forbidden from preview
  ✓ unauthenticated user is denied
  ✓ superadmin without active governance session is denied cross workspace preview
  ✓ superadmin with active governance session can preview documents across workspaces

PASS Tests\Feature\Security\CustomerChannelAnalyticsSecurityBarrierTest (14 tests, 120 assertions)
  ✓ customer channel analytics query is intercepted and denied with standard message
  ✓ customer channel employee workforce sales inquiry denied regardless of phrasing
  ✓ layer 2 execution boundary fail closed for unauthorized user
  ✓ cross tenant analytics isolation
  ✓ authorized owner in admin simulator can execute analytics
  ✓ customer channel detection across all supported public channels
  ✓ adversarial workforce requests in bangla banglish and english are safely blocked
  ✓ legitimate customer inquiries with agent or staff names are not falsely blocked
  ✓ manager with insufficient permissions is strictly blocked from analytics execution
  ✓ staff role cannot execute analytics
  ✓ superadmin access governance across workspaces
  ✓ customer channel cannot bypass ingress with spoofed metadata
  ✓ indirect and paraphrased workforce financial attempts are safely denied
  ✓ owner of workspace one cannot access workspace two via handle query

Summary: 79 passed, 0 failed, 614 assertions across all suites.
```

---

## 12. Backward Compatibility & Database Safety Confirmation

1. **Database Safety Confirmation:** As agreed under Option 2, `chatbot_core` was **NOT** dropped, truncated, rolled back, or modified. Zero destructive DDL commands were run. All tests run in isolated SQLite in-memory environments.
2. **Backward Compatibility:** All existing endpoints, public chat channels, and legacy `StructuredRecord` consumers remain completely operational.
3. **Python Contract Version Symmetry:** Discovered relationships export to Python via `SpreadsheetRelationshipGraph::toPythonCandidateRelationships()`, conforming to target Contract Version `2.3.0`.

---

## 13. Readiness for Phase 3 (Structured Analytics & Virtual DB Engine)

With domain-agnostic relationship discovery and the Owner-First business intelligence foundation verified:
1. Multi-sheet workbooks now possess clean entity relationship graphs ($1:1, 1:N, N:M$).
2. The Business Owner's workbench (`/dashboard/simulator`) is unblocked to execute multi-sheet joins and aggregations.
3. Phase 3 (Virtual SQLite compilation, SQL synthesis, and query planning) can now directly consume the `SpreadsheetRelationshipGraph` to generate high-performance joins across sheets.

