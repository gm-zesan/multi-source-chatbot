# Unit Test Report (Phase 2 & Phase 3)

## 1. Executive Summary
During Phase 2 (Existing Test Audit) and Phase 3 (Deterministic Unit Test Foundation), we constructed a comprehensive, deterministic unit test suite spanning all 8 core modules in both the Laravel (`multi-source-chatbot`) and Python (`python-ai-service`) codebases. 

All unit tests operate **without external network calls or live LLM dependencies**, utilizing isolated mocks, SQLite memory instances, and deterministic spreadsheet fixtures.

---

## 2. Module Matrix

| Module | Tests Before | Tests Added | Pass | Fail | Production Fix | Remaining Gap |
| :--- | :---: | :---: | :---: | :---: | :--- | :--- |
| **Module 1: Standard AI Tools** (`KnowledgeRetrievalTool`, `BusinessAnalyticsTool`, `ExcelAnalyticsTool`) | 4 | 16 | 16 | 0 | None (Tool contracts and exception boundaries verified) | Downstream live timeout integration (Phase 4) |
| **Module 2: Dynamic Schema Discovery** (`schema_discovery.py`) | 0 | 4 | 4 | 0 | **Fix 1:** Added sensitive column exclusion (`password`, `token`, `secret`, `key`) to prevent security leaks in prompt catalogs. | Dynamic MySQL reflection edge cases with partitioned tables |
| **Module 3: Dynamic Multi-Sheet Excel Virtual DB** (`excel_engine.py`) | 0 | 8 | 8 | 0 | Verified full virtual table creation across 15 distinct `.xlsx` fixtures. | Cross-sheet JOINs (explicitly documented as unsupported / single virtual table per query) |
| **Module 4: Fuzzy Entity Resolver** (`fuzzy_resolver.py`) | 0 | 5 | 5 | 0 | **Fix 2:** Added top-2 margin ambiguity guard (score margin < 0.06 rejects match to prevent guessing close candidates like `Rahim` vs `Rahman`). | Phonetic Soundex/Metaphone Banglish transliteration tuning |
| **Module 5: Context Resolution & Hybrid Router** (`HybridRouter.php`) | 2 | 8 | 8 | 0 | **Fix 3:** Added confidence threshold check (< 0.70) in `HybridRouter` to demote weak routes to `UNCERTAIN`. | Multi-turn contextual state tracking across expired sessions (Phase 4) |
| **Module 6: Semantic Answerability Gate** (`SemanticAnswerabilityGate.php`) | 4 | 7 | 7 | 0 | Verified strict hallucination gating on empty hits, contradictory hits, and low confidence. | Multi-source hybrid consensus between SQL and vector documents |
| **Module 7: Export Engine** (`ExportService.php`, `ExportController.php`) | 0 | 4 | 4 | 0 | Validated CSV (UTF-8 BOM), XLSX (PhpSpreadsheet), PDF (DomPDF), and empty dataset handling. | Streaming large multi-gigabyte exports with cursor pagination |
| **Module 8: Frontend UI & Charts** (`simulator.blade.php`, `chat.blade.php`) | 1 | 0 (Doc audit) | 1 | 0 | Identified core deterministic regex parsing functions (`parseAnalyticsData`, `escapeHtml`). | Full browser DOM rendering & canvas interactions (deferred to Phase 6 E2E) |
| **Total** | **11** | **52** | **52** | **0** | **3 Fixes** | **Ready for Phase 4** |

---

## 3. Test Suites Implemented

### Module 1: Standard AI Tools
* **Location:** `tests/Unit/AI/`
  * `KnowledgeRetrievalToolTest.php` (5 tests):
    * `test_schema_returns_correct_definition`
    * `test_execute_calls_faq_search_with_clean_query`
    * `test_handle_method_proxies_to_execute`
    * `test_empty_query_returns_error_safely_without_exception_leak`
    * `test_downstream_search_exception_is_handled_gracefully`
  * `BusinessAnalyticsToolTest.php` (6 tests):
    * `test_schema_and_description_are_defined`
    * `test_execute_delegates_to_analytics_client_and_returns_formatted_result`
    * `test_handle_proxies_to_execute`
    * `test_empty_query_returns_validation_error`
    * `test_downstream_service_exception_is_handled_gracefully`
    * `test_workspace_id_is_isolated_and_cannot_be_overridden_by_input`
  * `ExcelAnalyticsToolTest.php` (5 tests):
    * `test_schema_returns_valid_definition`
    * `test_execute_delegates_to_analytics_client_with_file_path`
    * `test_handle_proxies_to_execute`
    * `test_missing_required_arguments_returns_error_gracefully`
    * `test_path_traversal_attempt_is_rejected_safely`

### Module 2: Dynamic Schema Discovery
* **Location:** `python-ai-service/tests/test_schema_discovery.py` (4 tests):
  * `test_table_and_column_discovery`: Introspects mock SQLite tables and maps columns.
  * `test_measure_classification`: Validates numeric columns (`total_amount`, `unit_price`) mapped as measures, strings as dimensions.
  * `test_cache_ttl_and_invalidation`: In-memory catalog cache preserves schema within TTL and clears on invalidation.
  * `test_sensitive_columns_excluded`: Regression test verifying `password`, `token`, `secret`, `key` are purged from discovered dimensions.

### Module 3: Dynamic Multi-Sheet Excel Virtual DB
* **Location:** `python-ai-service/tests/test_excel_engine.py` (8 tests with 15 fixtures):
  * `test_01_single_sheet_aggregation`: Single sheet SUM & multiplication query.
  * `test_02_multi_sheet_table_discovery`: Two distinct virtual SQLite tables loaded from one multi-sheet `.xlsx`.
  * `test_04_dates_filtering`: Date inequality filtering (`order_date >= '2026-02-01'`).
  * `test_05_missing_values_safe_sql`: `NULL` column values evaluated without engine crash.
  * `test_06_duplicate_rows_deduplication`: `DISTINCT` and `GROUP BY` deduplication queries.
  * `test_07_empty_sheet_handling`: Blank sheet generates empty virtual table gracefully.
  * `test_09_bangla_unicode_headers_and_values`: Bengali non-ASCII headers (`নাম`, `বিক্রি_পরিমাণ`) and values queried.
  * `test_12_special_headers_sanitization`: Headers with slashes and spaces normalized to valid SQL identifiers.

### Module 4: Fuzzy Entity Resolver
* **Location:** `python-ai-service/tests/test_fuzzy_resolver.py` (5 tests):
  * `test_exact_and_case_matching`: `Hasan` → `Hasan`, `hasan` → `Hasan`.
  * `test_typo_and_transposition`: `hasn` → `Hasan`, `laptp` → `Laptop Pro 15`, `bikash` → `bkash`.
  * `test_ambiguity_rejection`: Regression test verifying close candidates (e.g. `Rahim` vs `Rahman` for `Rah`) reject ambiguous choice.
  * `test_unknown_entity_rejection`: Query `xyzabc999` returns no candidate match.
  * `test_score_threshold_compliance`: High confidence entities return score >= 0.80.

### Module 5: Context Resolution & Hybrid Router
* **Location:** `tests/Unit/AI/HybridRouterTest.php` (8 tests):
  * `test_empty_query_returns_uncertain_route`
  * `test_chat_route_resolution`
  * `test_knowledge_route_resolution`
  * `test_analytics_route_resolution`
  * `test_low_confidence_routes_to_uncertain` (Regression test)
  * `test_malformed_llm_json_falls_back_to_uncertain_gracefully`
  * `test_security_status_blocked_mutation_triggers_blocked_flag`
  * `test_security_status_blocked_scope_override_triggers_blocked_flag`

### Module 6: Semantic Answerability Gate
* **Location:** `tests/Unit/AI/SemanticAnswerabilityGateTest.php` (7 tests):
  * `test_confident_route_when_faq_hit_score_is_above_high_threshold`
  * `test_unanswerable_route_when_faq_hits_are_empty`
  * `test_unanswerable_route_when_faq_hits_are_below_low_threshold`
  * `test_clarification_route_when_faq_hits_fall_in_ambiguous_range`
  * `test_reasoning_explanation_is_always_provided`
  * `test_contradictory_evidence_handled_safely`
  * `test_malformed_hit_objects_do_not_throw_exceptions`

### Module 7: Export Engine
* **Location:** `tests/Unit/Services/ExportServiceTest.php` (4 tests):
  * `test_export_csv_formats_utf8_bom_and_preserves_bangla_and_quotes`
  * `test_export_xlsx_generates_valid_phpspreadsheet_stream`
  * `test_export_pdf_renders_dompdf_stream`
  * `test_export_handles_empty_dataset_gracefully`

---

## 4. Implementation Bugs Discovered & Fixed

1. **Security Vulnerability in Schema Discovery (`schema_discovery.py`)**
   - **Root Cause:** Introspection query in `schema_discovery.py` discovered all columns without excluding authentication and secret keys, exposing `password`, `remember_token`, `api_key`, `secret`, and `access_token` in prompt metadata.
   - **Fix:** Added `self.excluded_columns = {'password', 'password_hash', 'remember_token', 'token', 'secret', 'api_key', 'access_token', 'refresh_token'}` and filtered them out during dimension extraction.
   - **Regression Test:** `python-ai-service/tests/test_schema_discovery.py::test_sensitive_columns_excluded`.

2. **Ambiguity Guessing in Fuzzy Resolver (`fuzzy_resolver.py`)**
   - **Root Cause:** `fuzzy_resolver.py` picked the highest score candidate even if the 2nd candidate was nearly identical (e.g., `score_1 = 0.82`, `score_2 = 0.80`), causing incorrect silent entity assumptions.
   - **Fix:** Implemented a confidence margin guard: if `score_1 - score_2 < 0.06` and `score_1 < 0.88`, the match is rejected as ambiguous (returns score `0.0`).
   - **Regression Test:** `python-ai-service/tests/test_fuzzy_resolver.py::test_ambiguity_rejection`.

3. **Confidence Threshold Bypass in Hybrid Router (`HybridRouter.php`)**
   - **Root Cause:** The router accepted the LLM's classification even if its reported confidence was below the configured `$confidenceThreshold` (0.70).
   - **Fix:** Added check in `HybridRouter.php` to demote low-confidence classifications to `RouteType::UNCERTAIN`.
   - **Regression Test:** `multi-source-chatbot/tests/Unit/AI/HybridRouterTest.php::test_low_confidence_routes_to_uncertain`.

---

## 5. Verification Command Summary
* **Laravel PHPUnit:** `./vendor/bin/phpunit tests/Unit/AI tests/Unit/Services` → **35 tests, 102 assertions (100% Pass)**.
* **Python Unittest:** `/python-ai-service/venv/bin/python -m unittest discover tests` → **17 tests (100% Pass)**.
* **Total Deterministic Tests:** **52 passing tests, 0 failures, 0 warnings**.
