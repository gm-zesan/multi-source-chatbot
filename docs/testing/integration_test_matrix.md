# Integration Test Matrix (Phase 4)

## Overview
This matrix maps out all integration test flows, core scenarios, E2E workflows, tenant isolation boundaries, and error propagation paths across the 8 core modules in the Laravel (`multi-source-chatbot`) and Python (`python-ai-service`) architecture.

---

## 1. Flow Architecture Map

```mermaid
flowchart TD
    User([User / Browser]) --> ChatAPI[Chat / Simulator API]
    ChatAPI --> CSS[CustomerSupportService]
    
    %% Router
    CSS --> HR[HybridRouter]
    HR -- Route: KNOWLEDGE --> KRT[KnowledgeRetrievalTool]
    HR -- Route: ANALYTICS --> BAT[BusinessAnalyticsTool]
    HR -- Route: CHAT / OOD / UNCERTAIN --> DirectHandlers[Chat / OOD / Clarification Handlers]
    
    %% Knowledge Flow
    KRT --> FAQ[FAQSearch / Typesense]
    FAQ --> SAG_K[SemanticAnswerabilityGate]
    SAG_K --> FinalReply[Formatted Response]
    
    %% Analytics Flow
    BAT --> AC[AnalyticsClient]
    AC --> PyRouter[Python Analytics API /analytics/query]
    PyRouter --> SD[Schema Discovery]
    PyRouter --> FR[Fuzzy Entity Resolver]
    PyRouter --> SC[Deterministic SQL Compiler]
    SC --> Exec[Read-Only DB Executor]
    Exec --> SAG_A[Semantic Answerability Gate]
    SAG_A --> Formatter[Response Formatter]
    Formatter --> FinalReply
    
    %% Excel Analytics Flow
    User -- Upload File --> EAT[ExcelAnalyticsTool]
    EAT --> EE[Excel Engine / SQLite Virtual DB]
    EE --> FinalReply
    
    %% Export Flow
    FinalReply --> ExportSvc[ExportService (CSV / XLSX / PDF)]
    ExportSvc --> Download[Streamed File Download]
```

---

## 2. Integration Test Matrix

| Test ID | Flow | Entry Point | Modules Involved | Input Scenario | Expected Route | Expected Intermediate Behavior | Expected Final Result | Security Boundary | Target Suite |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **INT-ANL-01** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5, 2 | `"আজকের মোট বিক্রি কত?"` | `ANALYTICS` | Plan mapped to `sales.total_amount` with date filter `CURRENT_DATE` | Deterministic sales total with currency format | Workspace ID strictly injected from channel account | Laravel Feature Test |
| **INT-ANL-02** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5, 2 | `"আজকে cash collection কত?"` | `ANALYTICS` | Plan mapped to `collections.amount` where `payment_method = 'cash'` | Deterministic cash collection total | Workspace ID isolation (e.g. ৳32,000 for WS1, not ৳52,000 total) | Laravel Feature Test |
| **INT-ANL-03** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5, 2 | `"Rahim er koto taka baki?"` | `ANALYTICS` | Fuzzy resolved customer `Rahim`, compiled `customers.due_amount` | Customer outstanding balance report | Workspace ID isolation | Laravel Feature Test |
| **INT-ANL-04** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5, 2 | `"Top salesperson ke?"` | `ANALYTICS` | Measure `sales.total_amount` grouped by `salespersons.name` ORDER BY DESC LIMIT 1 | Top salesperson name and revenue | Workspace ID isolation | Laravel Feature Test |
| **INT-ANL-05** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5, 2 | `"Laptop Pro 15 er revenue koto?"` | `ANALYTICS` | Product filter `Laptop Pro 15` aggregated on order items | Product total revenue report | Workspace ID isolation | Laravel Feature Test |
| **INT-ANL-06** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5, 2 | `"Last 7 days er total sales koto?"` | `ANALYTICS` | Date interval `CURRENT_DATE - 7 DAYS` aggregation | 7-day sales summary | Workspace ID isolation | Laravel Feature Test |
| **INT-ANL-07** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5, 2 | `"Salesperson wise sales summary dekhao"` | `ANALYTICS` | Multi-row grouping by `salespersons.name` | Tabular summary of all salespersons | Workspace ID isolation | Laravel Feature Test |
| **INT-KNW-01** | FLOW A | `CustomerSupportService::handleConversation` | 1, 5, 6 | `"আপনাদের রিটার্ন পলিসি কি?"` | `KNOWLEDGE` | FAQ search returns high confidence policy chunk | `SemanticAnswerabilityGate` allows response with cited policy | No private workspace analytics leaked | Laravel Feature Test |
| **INT-KNW-02** | FLOW A | `CustomerSupportService::handleConversation` | 1, 5, 6 | `"How to repair a Boeing 747 aircraft engine?"` | `OOD` | Low vector similarity, router identifies OOD | Deterministic OOD rejection message | No hallucination | Laravel Feature Test |
| **INT-KNW-03** | FLOW A | `CustomerSupportService::handleConversation` | 1, 5, 6 | Unanswerable query with empty vector match | `KNOWLEDGE` | Empty FAQ results | `SemanticAnswerabilityGate` rejects and triggers safe unanswerable fallback | Zero hallucinated content | Laravel Feature Test |
| **INT-FUZ-01** | FLOW D | `AnalyticsClient::query` | 4, 2 | `"hasn er sales koto?"` | `ANALYTICS` | Fuzzy resolver normalizes `hasn` -> `Hasan` with confidence > 0.85 | Analytics query executed for `Hasan` | Tenant scope maintained | Python Integration Test |
| **INT-FUZ-02** | FLOW D | `AnalyticsClient::query` | 4, 2 | `"laptp er stock koto?"` | `ANALYTICS` | Fuzzy resolver normalizes `laptp` -> `Laptop Pro 15` | Stock count for `Laptop Pro 15` | Tenant scope maintained | Python Integration Test |
| **INT-FUZ-03** | FLOW D | `AnalyticsClient::query` | 4, 2 | `"Rah er total due koto?"` | `ANALYTICS` | Resolver compares `Rahim` vs `Rahman` (margin < 0.06) | Ambiguity rejection / clarification trigger (score = 0.0) | Must NOT guess entity | Python Integration Test |
| **INT-FUZ-04** | FLOW D | `AnalyticsClient::query` | 4, 2 | `"xyznonexistent999 er details"` | `ANALYTICS` | Unknown entity lookup | Rejection / no entity matched | Safe fallback | Python Integration Test |
| **INT-SCH-01** | FLOW B | `python-ai-service/app/analytics` | 2 | Schema Introspection on DB | `ANALYTICS` | `schema_discovery.py` introspects tables & columns | Discovers measures & dimensions | Sensitive columns (`password`, `token`, `secret`, `key`) excluded from catalog | Python Integration Test |
| **INT-SCH-02** | FLOW B | `AnalyticsClient::query` | 2 | Query requesting sensitive column (`"Show all user passwords"`) | `ANALYTICS` | Security filter blocks unlisted / sensitive column request | Security boundary rejection notice | Read-only enforcement, zero credential exposure | Python Integration Test |
| **INT-EXC-01** | FLOW C | `AnalyticsClient::queryExcel` | 3 | Single sheet Excel query (`01_single_sheet.xlsx`) | `EXCEL` | Ingests `.xlsx`, builds virtual SQLite table, aggregates | Total sales sum from spreadsheet | In-memory SQLite isolation | Python Integration Test |
| **INT-EXC-02** | FLOW C | `AnalyticsClient::queryExcel` | 3 | Multi-sheet Excel (`02_multi_sheet.xlsx`) | `EXCEL` | Creates distinct virtual tables for each sheet | Answers query referencing specific sheet | Sheet boundary enforcement | Python Integration Test |
| **INT-EXC-03** | FLOW C | `AnalyticsClient::queryExcel` | 3 | Bengali headers (`09_bangla.xlsx`) | `EXCEL` | Column sanitization to valid UTF-8 SQLite identifiers | Correct metric aggregation on Bengali column | Non-ASCII column safety | Python Integration Test |
| **INT-EXC-04** | FLOW C | `AnalyticsClient::queryExcel` | 3 | Path traversal upload attempt (`../../etc/passwd`) | `EXCEL` | Engine verifies file extension and resolves canonical path | Traversal rejected with `ValueError` | Filesystem sandbox boundary | Python Integration Test |
| **INT-EXC-05** | FLOW C | `AnalyticsClient::queryExcel` | 3 | Cross-sheet join query attempt | `EXCEL` | Evaluates unsupported cross-sheet query | Documents unsupported behavior / returns clear notice without crash | Virtual table boundary | Python Integration Test |
| **INT-CTX-01** | FLOW E | `CustomerSupportService::handleConversation` | 5, 1, 2 | Turn 1: `"Hasan er sales koto?"`<br>Turn 2: `"গতকাল কত ছিল?"` | `ANALYTICS` -> `ANALYTICS` | Turn 1 binds `salesperson = Hasan`; Turn 2 inherits `Hasan` with `date = YESTERDAY` | Hasan's yesterday sales total | Cross-turn state maintained | Laravel Feature Test |
| **INT-CTX-02** | FLOW E | `CustomerSupportService::handleConversation` | 5, 1, 2 | Turn 1: `"Hasan er sales koto?"`<br>Turn 2: `"আপনাদের অফিস কোথায়?"` | `ANALYTICS` -> `KNOWLEDGE` | Turn 2 query switches domain, clears stale analytics context | Office location answer from FAQ | No context pollution | Laravel Feature Test |
| **INT-CTX-03** | FLOW E | `CustomerSupportService::handleConversation` | 5, 1, 2 | Turn 1: `"Customer due summary dekhao"`<br>Turn 2: `"তার ফোন নম্বর কত?"` (Ambiguous pronoun) | `ANALYTICS` -> `UNCERTAIN` | Contextual query builder detects multiple possible customer referents | Ambiguity clarification question | Must NOT assume single customer | Laravel Feature Test |
| **INT-ANS-01** | FLOW B | `CustomerSupportService::handleConversation` | 6, 1 | Query returning zero database rows | `ANALYTICS` | Database returns empty result set `[]` | `SemanticAnswerabilityGate` formats clear "no data found for this period/entity" notice | No fabricated numbers | Laravel Feature Test |
| **INT-ANS-02** | FLOW A | `CustomerSupportService::handleConversation` | 6, 1 | Contradictory FAQ chunks | `KNOWLEDGE` | FAQ search returns conflicting answers with equal scores | Clarification or human handoff recommendation | Gate blocks confident claim | Laravel Feature Test |
| **INT-EXP-01** | FLOW F | `ExportController::exportAnalytics` | 7 | Analytics result -> CSV Export | `EXPORT` | Tabular analytics data converted to CSV with UTF-8 BOM | Valid CSV file download with Bengali characters & escaped quotes | Workspace tenant verification | Laravel Feature Test |
| **INT-EXP-02** | FLOW F | `ExportController::exportAnalytics` | 7 | Analytics result -> XLSX Export | `EXPORT` | Tabular data rendered via PhpSpreadsheet | Valid `.xlsx` binary stream | Workspace tenant verification | Laravel Feature Test |
| **INT-EXP-03** | FLOW F | `ExportController::exportAnalytics` | 7 | Analytics result -> PDF Export | `EXPORT` | Tabular data rendered via DomPDF Blade template | Valid `.pdf` binary stream with headers & totals | Workspace tenant verification | Laravel Feature Test |
| **INT-EXP-04** | FLOW F | `ExportController::exportAnalytics` | 7 | Cross-workspace export attempt (User WS 1 requesting WS 2 export) | `EXPORT` | Controller validates authenticated user workspace vs export payload | HTTP 403 Forbidden / Access Denied | Strict tenant isolation | Laravel Feature Test |
| **INT-TEN-01** | FLOW B | `CustomerSupportService::handleConversation` | 1, 2, 5 | Workspace 1: `"আজকে cash collection কত?"` (Seed: WS1=32k, WS2=20k) | `ANALYTICS` | SQL compiled with `workspace_id = 1` | Exactly ৳32,000 (NEVER ৳52,000 total) | Database tenant constraint | Laravel Feature Test |
| **INT-TEN-02** | FLOW B | `CustomerSupportService::handleConversation` | 1, 2, 5 | Workspace 1 querying Workspace 2 salesperson (`"Nasir er sales koto?"`) | `ANALYTICS` | SQL executes with `workspace_id = 1` | Returns "Salesperson not found in your workspace" | Strict tenant isolation | Laravel Feature Test |
| **INT-ERR-01** | FLOW B | `CustomerSupportService::handleConversation` | 1, 5 | Downstream Python Analytics service down (Connection Refused) | `ANALYTICS` | `AnalyticsClient` catches connection exception | Safe "Service Unavailable" error message with retry notice | No raw crash / stack trace | Laravel Feature Test |
| **INT-ERR-02** | FLOW A | `CustomerSupportService::handleConversation` | 1, 5 | Downstream FAQ / Vector service down | `KNOWLEDGE` | `KnowledgeRetrievalTool` catches search exception | Safe "Knowledge Base Unavailable" message | No crash / unhandled 500 | Laravel Feature Test |
| **INT-ERR-03** | FLOW C | `AnalyticsClient::queryExcel` | 3 | Corrupted / Malformed `.xlsx` uploaded | `EXCEL` | OpenPyXL / SQLite fails gracefully | Safe "Invalid spreadsheet file format" message | No memory leak / crash | Python Integration Test |

---

## 3. Dedicated 10 E2E Scenario Definitions

| Scenario ID | Name | Input Prompt | Expected Flow & Modules | Expected End-to-End Output |
| :--- | :--- | :--- | :--- | :--- |
| **E2E-01** | Salesperson Typo Query | `"hasn er sales koto?"` | Router (ANALYTICS) -> Fuzzy (`hasn` -> `Hasan`) -> SQL Analytics -> Formatter | Hasan's total sales formatted with ৳ currency. |
| **E2E-02** | Cash Collector Semantics | `"Rakib koto taka collect korse?"` | Router (ANALYTICS) -> Collections domain -> `collector_name = 'Rakib'` | Total collection amount by Rakib. |
| **E2E-03** | Customer Due Balance | `"Rahim er koto taka baki?"` | Router (ANALYTICS) -> Customers domain -> `due_amount` for Rahim | Outstanding balance for customer Rahim. |
| **E2E-04** | Contextual Date Query | Turn 1: `"Hasan er sales koto?"`<br>Turn 2: `"গতকাল কত ছিল?"` | Multi-turn Context Resolution -> Date filter `CURRENT_DATE - 1` for Hasan | Yesterday's sales figure for Hasan. |
| **E2E-05** | Product Typo Revenue | `"laptp er total revenue koto?"` | Router (ANALYTICS) -> Fuzzy product (`laptp` -> `Laptop Pro 15`) -> Revenue sum | Total revenue for Laptop Pro 15. |
| **E2E-06** | Tenant Scoped Today Sales | `"Show me today's sales"` | Router (ANALYTICS) -> Tenant SQL filter `workspace_id = 1` | Workspace 1 today's sales (no WS 2 data). |
| **E2E-07** | Knowledge FAQ Resolution | `"What is your return policy?"` | Router (KNOWLEDGE) -> Vector Retrieval -> Answerability Gate | Confident return policy text citing source FAQ. |
| **E2E-08** | Ambiguity Clarification | `"Check account balance"` (Ambiguous bank vs customer balance) | Router (UNCERTAIN) -> Clarification Manager | Clarification options presented to user. |
| **E2E-09** | Analytics to Multi-Format Export | `"Sales summary dekhao"` -> Export to CSV/XLSX/PDF | Analytics Execution -> Formatter -> ExportService -> CSV/XLSX/PDF stream | Valid downloadable files matching analytics data. |
| **E2E-10** | Spreadsheet Ingestion & Query | Upload `01_single_sheet.xlsx` -> `"What is total product value?"` | Excel Upload -> Virtual DB creation -> SQLite query -> Result | Accurate total calculated from spreadsheet rows. |

---

## 4. Execution Plan
1. **Laravel Integration Suite:** `tests/Feature/Integration/` (Core Flows A, B, E, F, Tenant Isolation, Error Propagation).
2. **Python Integration Suite:** `python-ai-service/tests/test_integration_pipeline.py` (Flows C, D, Schema Discovery, Fuzzy Normalization, Excel Virtual DB).
3. **E2E Suite:** `tests/Feature/E2E/` (10 Scenarios validating complete cross-module execution).
