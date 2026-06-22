# Multi-Source Chatbot — Project Overview

This document describes the repository structure, current status, and responsibilities of the main files so a developer can quickly understand and continue work.

---

## Project Goal (long-term)
- Connect multiple heterogeneous sources (databases, text files, Excel) with identity-aware connections (e.g. 10 DBs, 10 TXT, 10 Excel) and query them via a single natural-language routing engine.
- Provide a routing engine that determines which source(s) to query for a request and formats a safe dynamic query for single or multi-source search.

Current focus: DB sources are working and the parser → planner → executor flow is implemented.

---

## How the system currently flows

User Query → `QueryParser` → Intent Object → `QueryPlanner` → Execution Plan → `QueryExecutor` (builds & runs query) → DB (`db_01` etc.) → Result Set → `ResponseFormatter` → JSON → Blade/UI

API endpoint: `POST /chat/send` (handled by `ChatController`) — accepts `message` and returns JSON (table or text response).

---

## Files & Responsibilities (current)

- `resources/views/chat.blade.php`
  - Frontend single-page chat UI. Handles sending `POST /chat/send` and rendering table or text results.
  - Now uses a full-width bottom input and a light theme.

- `app/Http/Controllers/ChatController.php`
  - Entry point for the chat API. Receives requests, calls `QueryParser`, logs query, builds a plan with `QueryPlanner` (or directly calls `QueryExecutor` in some variants), executes, and returns formatted response via `ResponseFormatter`.

- `app/Services/QueryParser.php`
  - Parses the raw user query into an intent object with keys: `action`, `table`, `source`, `limit`, `filters`, `columns`, `sort`.
  - Detects action keywords (show/list/get/etc.), top/limit, where clauses (supports multi-word and quoted values), column mentions (uses `RegistryService::getColumns()` as single source-of-truth), and order-by.

- `app/Services/QueryPlanner.php`
  - Converts an intent into a normalized execution plan (`planFromIntent()`), validates against registry tables, and builds an Eloquent/Query Builder instance (`build()`).
  - Validates table existence (`source_tables`) and allowed columns via the registry (`RegistryService` or `source_columns`). Expands `*` to allowed columns.
  - IMPORTANT: Planner only plans & builds queries — it does not execute them (separation of concerns).

- `app/Services/QueryExecutor.php`
  - Responsible for executing a plan built by the planner. Injects `QueryPlanner`, calls `planFromIntent()` and `build()` then executes `->get()`.
  - Handles exceptions from validation and logs warnings/errors. Returns empty collection on failure.

- `app/Services/RegistryService.php` (reference)
  - Central source-of-truth for registered tables/columns. Methods used: `getColumns($table)`.
  - The registry may use DB tables `source_tables` and `source_columns` (recommended). Ensure this service exists and is seeded.

- `app/Models/SourceTable.php`
  - Model representing `source_tables` (table aliases and source ids). The parser uses it to resolve aliases -> table_name and source id.

- `app/Services/ResponseFormatter.php` (reference)
  - Formats result sets into structured JSON for the front-end: types `table` or `text`.

- `database/migrations/` and `database/seeders/`
  - Migration(s) should include `source_tables` and `source_columns` for the registry. Seeders should populate allowed tables & columns for initial dev.

---

## Security & Validation
- The planner validates every table and column against the registry. Columns used in `SELECT`, `WHERE`, and `ORDER BY` are checked.
- If a requested column or table is not registered, the planner throws an `InvalidArgumentException`. The executor catches validation errors and returns an empty result while logging the attempt.
- Do NOT accept table or column names directly from user input without validation.

---

## Current Status (what's done)
- Parser implemented and detects actions, columns, limit/top, where (multi-word), sort.
- Planner implemented: builds query and validates columns via `RegistryService`.
- Executor implemented: executes plan and handles validation errors/logging.
- Front-end updated with a clean light UI and bottom fixed input.
- ChatController wired to use parser → planner → executor → formatter (logging to `ChatLog`).

---

## Next Steps (recommended roadmap)

Phase A — Complete registry & source wiring (short term):
- Create migrations for `source_tables` and `source_columns` and a `RegistryService` backed by them.
- Add seeders to populate the 1st DB (`db_01`) table metadata.
- Add a `Source` model / configuration to describe connections for multiple DBs (10), and file adapters for TXT/Excel.

Phase B — Multi-source routing & query formatting:
- Implement a routing engine that decides which source(s) to query for a given intent (by table, alias, or keywords). Support querying multiple DBs and merging results.
- Add per-source identity (connection name) and adapter layers for non-DB sources (text/excel): normalize results to the same column keys.
- Implement query translator for non-SQL sources (text search, CSV scanning, Excel parsing). Use `columns` and `filters` from the plan to perform searches.

Phase C — Scaling & safety (production):
- Add caching for frequent queries and registry lookups.
- Add rate-limiting, auditing of invalid attempts, and stricter error handling & monitoring.
- Add unit + integration tests for parser, planner, executor, and registry.

---

## How to run locally (quick)
- Ensure your `.env` has the `db_01` connection configured. Run migrations and seeders for `users`, `customers`, and registry tables.

Commands:
```bash
cp .env.example .env
# update .env with DB credentials
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Then open `http://127.0.0.1:8000/chat` and test queries like:
- `show customers`
- `show top 5 customers`
- `show customer email where id = 1`

---

## Suggested Deliverables for the next PR
- Migrations + seeders for `source_tables` and `source_columns`.
- `RegistryService` implementation using the registry DB tables.
- Adapters for TXT and Excel sources (readers + column mappers).
- Routing engine prototype (decides which source(s) to query).
- Unit tests for `QueryParser`, `QueryPlanner`, and `QueryExecutor`.

---

If you want, I can: create migrations + seeders for registry tables next, or add unit tests for the parser and planner. Which should I do first?
