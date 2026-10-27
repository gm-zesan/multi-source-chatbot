# Analytics & Ingestion Quality Audit

- **Document ID**: `QUAL-AUDIT-04`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Inspection Date**: October 2026
- **Scope**: Spreadsheet / Business Analytics (Scope D) & Knowledge Ingestion (Scope E)
- **Status**: Empirical Deep-Dive Report

---

## 1. Executive Summary

This audit evaluates the reliability, precision, resource limits, and failure handling of our two core data processing engines:
1. **Spreadsheet & Tabular Analytics Engine**: Dynamic SQLite database synthesis and SQL compilation (`excel_engine.py`, `SemanticValidator`, `compiler.py`).
2. **Knowledge Ingestion Pipeline**: File validation, structural parsing, and lifecycle state machines (`KnowledgeDocumentService.php`, `StoreKnowledgeDocumentRequest.php`).

While our SQL analytics engine is a major competitive differentiator—proven capable of executing exact arithmetic and multi-table joins without LLM hallucinations—it suffers from **unbounded worker memory risks, missing statement execution timeouts, and an unauthenticated endpoint**. Furthermore, the knowledge ingestion pipeline contains a **fundamental functional disconnect** where non-analytics documents are never indexed for semantic retrieval.

---

## 2. Scope D: Spreadsheet & Business Analytics Audit

### D.1 Architecture & Pipeline Trace
- Ingestion converts uploaded `.xlsx` or `.csv` files into an isolated virtual SQLite database: `ws_{workspace_id}_{file_hash}.sqlite` in `python-ai-service/app/analytics/`.
- Queries are routed via `ExcelAnalyticsTool.php` -> `AnalyticsClient.php` -> `python-ai-service/app/analytics/router.py`.
- In Python, `planner.py` translates the natural language query into a `SemanticQueryPlan`.
- `SemanticValidator.py` analyzes grain and checks for Cartesian fanout risks.
- `compiler.py` generates deterministic SQL and executes it against the local SQLite database.

### D.2 Critical Analytics Deficiencies & Fragilities

```
+---------------------------------------------------------------------------------------------------+
| FINDING ANL-01: Unauthenticated Analytics Endpoint on Python AI Microservice                      |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`python-ai-service/app/main.py:141`)               |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In `python-ai-service/app/main.py:141`, `app.include_router(analytics_router)` is mounted **without** `dependencies=[Depends(verify_api_key)]`.
- **Failure Impact**:
  - The `/analytics/query` endpoint accepts a raw JSON body containing `workspace_id`.
  - Any internal caller or attacker reaching Port 8001 (via SSRF or container network) can submit `{"workspace_id": 2, "query": "SELECT * FROM sales"}` and query or dump another tenant's SQLite databases without authentication.
- **Recommended Fix**: Add `dependencies=[Depends(verify_api_key)]` and require HMAC request signing with timestamp tolerance checks.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING ANL-02: Missing SQLite Query Timeouts & Memory Execution Bounds                           |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`excel_engine.py:104-140`)                         |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**:
  - In `excel_engine.py:104`, SQLite connection is established via `conn = sqlite3.connect(db_path)`.
  - No `timeout` or statement execution interrupt handler is attached.
  - In `excel_engine.py:112-135`, `pd.read_excel(xls, sheet_name=sheet)` reads all sheets and rows into memory without `nrows` limits.
- **Failure Impact**:
  - A complex query involving multiple nested subqueries or Cartesian joins across two 100,000-row tables will lock the SQLite process indefinitely, pinning the Python worker CPU at 100%.
  - Uploading an Excel file with 40 sheets and 200,000 rows exhausts server RAM, triggering an Out-Of-Memory (OOM) kill on the shared worker.
- **Recommended Fix**:
  1. Enforce `MAX_SHEETS = 10` and `MAX_ROWS_PER_SHEET = 100_000` during ingestion.
  2. Register `conn.set_progress_handler(timeout_handler, 1000)` to abort queries exceeding 15 seconds.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING ANL-03: Banglish Column Name Resolution Fragility in Schema Discovery                    |
| Status: PARTIALLY_CONFIRMED | Evidence: CODE_INSPECTION (`schema_discovery.py`) & EXISTING_TEST   |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**:
  - Spreadsheets uploaded by Bangladeshi merchants frequently use phonetic Banglish or mixed headers: `bikri_poriman` (sales amount), `bakir_taka` (due amount), `khoroch` (expense), `beton` (salary).
  - In `python-ai-service/app/analytics/fuzzy_resolver.py`, column matching relies on fuzzy ratio matching and an English semantic dictionary.
- **Failure Impact**:
  - If a merchant names a column `mot_bikri` and the user asks *"Hasan er total sales koto?"*, the planner occasionally fails to map `total sales` to `mot_bikri`, falling back to `UNCERTAIN` or picking an incorrect numeric column (e.g. `order_id`).
  - Verified in [`tests/Feature/AnalyticsSourceSelectionRegressionTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/AnalyticsSourceSelectionRegressionTest.php).
- **Recommended Fix**: Expand the semantic schema dictionary in `registries.py` with 150 common Bengali and Banglish e-commerce business terms.

---

## 3. Scope E: Knowledge Document Ingestion Audit

### E.1 Architecture & Lifecycle Trace
- Ingestion is handled by [`KnowledgeDocumentService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Knowledge/KnowledgeDocumentService.php) and validated by [`StoreKnowledgeDocumentRequest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Requests/StoreKnowledgeDocumentRequest.php).
- Supports file upload (`.xlsx`, `.csv`, `.zip`, `.txt`) and direct text entry (`storeText`).
- Implements engine-level SHA-256 deduplication (`active_content_hash`) per workspace.

### E.2 Critical Ingestion Deficiencies & Fragilities

```
+---------------------------------------------------------------------------------------------------+
| FINDING ING-01: Complete Architectural Disconnect for Non-Analytics Knowledge Documents           |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`KnowledgeDocumentService.php:420-427`)             |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**:
  - In [`KnowledgeDocumentService.php:420-427`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Knowledge/KnowledgeDocumentService.php#L420-L427):
    ```php
    if ($resolvedPurpose === \App\Enums\IngestionPurpose::DATA_ANALYTICS) {
        $this->processAnalyticsRegistration($document);
    } else {
        $document->update([
            'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
            'is_active'        => true,
        ]);
    }
    ```
- **Failure Impact**:
  - When a merchant uploads a PDF, DOCX, or text file under "Knowledge Base", the file is saved to disk and immediately marked `ACTIVE`.
  - **However, no chunking, embedding, or Typesense indexing is executed!**
  - The conversational AI tool ([`KnowledgeRetrievalTool.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Tools/KnowledgeRetrievalTool.php)) strictly searches the `faqs` table.
  - Result: Any document uploaded to the "Knowledge Base" tab is completely invisible to the chatbot!
- **Recommended Fix**:
  1. Create an asynchronous `IndexKnowledgeDocumentJob` (mirroring `FAQIndexJob`).
  2. Implement a PDF/text parser that splits documents into 500-token chunks with 50-token overlaps.
  3. Index document chunks into Typesense under a unified search collection.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING ING-02: Missing Outbox Processing & Reprocessing Fencing                                  |
| Status: CONFIRMED | Evidence: CODE_INSPECTION                                                     |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**:
  - If a spreadsheet registration fails during Python microservice downtime, the document is marked `lifecycle_status = FAILED` with an error message.
  - However, there is no transactional outbox table or scheduled retry daemon to re-attempt failed document ingestions automatically.
  - Ingestion retry requires manual administrative intervention via the UI or command line.
- **Failure Impact**: If `python-ai-service` experiences a 2-minute deployment reboot, all documents uploaded during that window are marked failed and stay permanently failed until manually re-uploaded.
- **Recommended Fix**: Implement a transactional outbox queue with exponential backoff retries (1m, 5m, 15m) for failed document registrations.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING ING-03: Stale Typesense Indexing on Soft-Delete or Update Race Conditions                 |
| Status: PARTIALLY_CONFIRMED | Evidence: CODE_INSPECTION (`FAQService.php:480-515`)                |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**:
  - When an FAQ is deleted, [`FAQService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/FAQ/FAQService.php) dispatches `deleteFaq()` to `RetrievalClient.php`.
  - If the network call to Typesense fails during the delete operation, MySQL soft-deletes the record, but **the chunk remains active in Typesense**!
  - Subsequent searches continue returning the deleted FAQ in Top-3 results.
- **Failure Impact**: Outdated, deleted, or incorrect company policies continue being quoted by the chatbot after deletion.
- **Recommended Fix**: Record a tombstone record in an outbox table whenever a delete call fails, and run a scheduled synchronization reconciliation command (`php artisan knowledge:reconcile-indices`).
