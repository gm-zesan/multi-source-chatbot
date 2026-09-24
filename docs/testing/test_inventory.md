# Test Inventory & Audit Matrix (Phase 2)

## Overview
This document catalogs the existing tests, identifies gaps, evaluates risk levels, and defines the deterministic test plan across all 8 core modules in the Laravel (`multi-source-chatbot`) and Python (`python-ai-service`) architecture.

---

## 1. Module Test Inventory

| Module | Existing Tests | Missing Tests | Existing Fixtures | Missing Fixtures | Risk Level | Recommended Coverage |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Module 1: Standard AI Tools** (`KnowledgeRetrievalTool`, `BusinessAnalyticsTool`, `ExcelAnalyticsTool`) | `KnowledgeRetrievalToolTest.php` (basic 4 tests) | - `BusinessAnalyticsToolTest`<br>- `ExcelAnalyticsToolTest`<br>- `execute()` vs `handle()` equivalence<br>- Input validation & boundary rejection<br>- Workspace override security tests<br>- Downstream service timeout & malformed JSON error handling | Mock `FAQSearch` | - Mock `AnalyticsClient` responses<br>- Malformed/timeout HTTP payload fixtures | **HIGH** | 100% of methods (`execute`, `handle`, `schema`, `description`), exception handling, and tenant security. |
| **Module 2: Dynamic Schema Discovery** (`schema_discovery.py`) | None | - Table, measure, dimension discovery<br>- Sensitive field exclusion (`password`, `token`, `secret`, `key`)<br>- Nullable & data type mapping<br>- In-memory cache TTL behavior<br>- Empty/malformed schema stability | Live MySQL `information_schema` | - Deterministic SQLite mock schema for isolated testing<br>- Sensitive column test tables | **MEDIUM** | Complete schema introspection, sensitive column exclusion, and caching. |
| **Module 3: Dynamic Multi-Sheet Excel Virtual DB** (`excel_engine.py`) | Basic upload test script | - 15 deterministic fixture tests (A to O)<br>- Multi-sheet SQLite virtual table mapping<br>- Aggregation, filtering, grouping, date handling<br>- Malformed/corrupted file rejection<br>- Path traversal security rejection | Single sample Excel file | - 15 dedicated `.xlsx` fixture files (single, multi-sheet, numeric, dates, missing, duplicates, empty, Bangla, English, mixed, special headers, large, nulls) | **HIGH** | All 15 spreadsheet edge-case categories, SQL translation, and security guards. |
| **Module 4: Fuzzy Entity Resolver** (`fuzzy_resolver.py`) | Ad-hoc query test | - Deterministic entity resolution suite<br>- Exact, case, typo, insertion, deletion, transposition<br>- Ambiguity detection (must NOT silently guess close candidates)<br>- Unknown entity rejection | Hardcoded demo entities | - Canonical entity fixture list (names, products, payment methods, ambiguous name pairs like `Rahim` vs `Rahman`) | **MEDIUM** | Precision, correct rejection of unknown entities, and mandatory ambiguity triggers. |
| **Module 5: Context Resolution & Hybrid Router** (`HybridRouter.php`, `CustomerSupportService.php`) | `M2ContextualResolutionContractTest.php`, `LiveHttpPipelineVerificationTest.php` | - Deterministic unit tests for all 5 active routes (`KNOWLEDGE`, `ANALYTICS`, `CHAT`, `OOD`, `UNCERTAIN`)<br>- Security rejection tests (Prompt injection, SQL injection, Workspace override attempts)<br>- Multi-turn context resolution & pronoun resolution | Historical chat seeds | - Deterministic route benchmark dataset without external LLM network dependency | **HIGH** | Route precision, security injection rejection, and context continuity. |
| **Module 6: Semantic Answerability Gate** (`SemanticAnswerabilityGate.php`) | `SemanticAnswerabilityGateTest.php` | - Extended tests for zero-row analytics<br>- Contradictory evidence<br>- Fabricated numeric claims guard<br>- Low-confidence threshold boundaries | Fake FAQ hits | - Deterministic contradictory hit fixtures & zero-row analytics payloads | **HIGH** | Hallucination prevention and confident/unanswerable/ambiguous decision rules. |
| **Module 7: Export Engine** (`ExportService.php`, `ExportController.php`) | None | - Unit tests for `ExportService` (CSV with UTF-8 BOM, XLSX with PhpSpreadsheet, PDF with DomPDF)<br>- Special characters, commas, quotes, newlines, Bangla Unicode<br>- Large datasets & null values<br>- Controller route access & validation | None | - Multi-lingual tabular datasets (English, Bengali, mixed, currency symbols `৳`, quotes) | **MEDIUM** | Streamed CSV formatting, XLSX generation, PDF rendering, and UTF-8 encoding. |
| **Module 8: Frontend UI & Charts** (`simulator.blade.php`, `chat.blade.php`) | `ChatSimulatorControllerTest.php` | - JavaScript unit test audit for regex metric parsing (`parseAnalyticsData`)<br>- Chart configuration generator<br>- Export URL builder & clipboard fallback<br>- XSS sanitization | Blade templates | - Sample markdown analytics reply strings with metrics and tables | **LOW** | Metric parsing correctness, XSS escaping, and canvas cleanup. |

---

## 2. Shared Fixtures & Testing Strategy
* **Unit Testing Rule:** Zero network or live external LLM dependencies in unit tests. All tests use deterministic fixtures or mocks.
* **PHPUnit Framework:** Laravel test runner using standard `tests/Unit/` and `tests/Feature/`.
* **Python Test Framework:** Python built-in `unittest` runner inside `/python-ai-service/tests/`.
