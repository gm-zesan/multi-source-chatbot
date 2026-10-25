# Dynamic Business Knowledge Ingestion — Phase 1 Architecture & Implementation

**Status:** Frozen  
**Phase:** Phase 1 (Document Persistence, Secure Storage, Workspace Scoping & Admin Management)  
**Date:** October 2026  
**Target Repository:** `multi-source-chatbot`

---

## 1. Phase 1 Goals and Explicit Non-Goals

### Core Goals
- **Document Metadata Persistence**: Maintain a reliable audit trail of all uploaded business knowledge documents with UUID primary keys, workspace associations, audit timestamps, and soft deletes.
- **Secure Private File Storage**: Store uploaded files exclusively on the private local storage disk with server-generated UUID filenames, preventing directory traversal and public web access.
- **Upload Validation & Duplicate Prevention**: Strictly validate MIME types and sizes up to 10 MB, compute SHA-256 content hashes, and enforce concurrency-safe deduplication at the database engine level.
- **Workspace Isolation & RBAC**: Enforce strict workspace boundaries and granular Spatie permissions (`document.view`, `document.create`, `document.update`, `document.delete`), preserving existing superadmin global visibility patterns.
- **Admin Management UI**: Provide a responsive, Blade-based administration interface consistent with the FAQ management UI, including DataTables server-side pagination, soft deletion, and restoration.
- **Truthful Lifecycle Status**: Initialize uploaded documents with `lifecycle_status = 'pending_ingestion'` and `is_active = false`.

### Explicit Non-Goals (Reserved for Phase 2)
- **NO Document Parsing or Text Extraction**: No DOCX or CSV text extractors are invoked in Phase 1.
- **NO Document Chunking**: No text chunking, sliding windows, or tokenizers are executed.
- **NO Embeddings Generation**: No calls to embedding models (local or OpenAI/FastEmbed).
- **NO Typesense Indexing**: No records or vector collections are pushed to Typesense.
- **NO Background Ingestion Queues**: No ingestion jobs are dispatched upon upload.
- **NO Customer-Facing Retrieval**: Documents are completely invisible to `HybridRouter`, `KnowledgeSupportAgent`, FAQ retrieval, and customer chat conversations.

---

## 2. Database Schema and the `active_content_hash` Design

### Schema Overview (`knowledge_documents`)

| Column | Type | Constraints / Description |
|---|---|---|
| `id` | `uuid` | Primary Key (`HasUuids`) |
| `workspace_id` | `bigint unsigned` | Foreign Key (`workspaces.id`), `cascadeOnDelete` |
| `title` | `varchar(255)` | User-provided or extracted original base name |
| `original_filename` | `varchar(255)` | Original client-provided filename (display only) |
| `file_path` | `varchar(255)` | Storage path: `documents/{workspace_id}/{uuid}.{ext}` |
| `mime_type` | `varchar(128)` | Detected MIME type |
| `file_size` | `bigint unsigned` | File size in bytes |
| `content_hash` | `varchar(64)` | Immutable SHA-256 hash of physical file content |
| `active_content_hash` | `varchar(64) nullable` | SHA-256 hash when active; `NULL` when soft-deleted |
| `lifecycle_status` | `varchar(32)` | Enum: `pending_ingestion` (default), `indexing`, `active`, `failed` |
| `chunk_count` | `int unsigned` | Initialized to `0` |
| `version` | `int unsigned` | Initialized to `1` |
| `is_active` | `tinyint(1)` | Initialized to `0` (`false`) |
| `error_message` | `text nullable` | Error explanation if processing fails |
| `created_by` | `bigint unsigned nullable` | Foreign Key (`users.id`), `nullOnDelete` |
| `updated_by` | `bigint unsigned nullable` | Foreign Key (`users.id`), `nullOnDelete` |
| `created_at` / `updated_at` | `timestamp nullable` | Standard Laravel timestamps |
| `deleted_at` | `timestamp nullable` | Soft deletes timestamp |

### Indexes
- `UNIQUE (workspace_id, active_content_hash)` (Index: `knowledge_docs_active_hash_unique`)
- `INDEX (workspace_id, content_hash)`
- `INDEX (workspace_id, lifecycle_status)`
- `INDEX (workspace_id, is_active)`

### The Purpose of `active_content_hash`
In MySQL and standard SQL engines:
1. A unique index on `(workspace_id, content_hash)` prevents re-uploading identical content even after the previous document was soft-deleted (`deleted_at IS NOT NULL`), because MySQL treats non-null values as colliding.
2. Conversely, a composite unique index on `(workspace_id, content_hash, deleted_at)` fails to prevent duplicates for active records because SQL treats `NULL != NULL`, permitting unlimited active rows where `deleted_at IS NULL`.
3. **The Solution**: We introduce `active_content_hash`.
   - When a document is active (`deleted_at IS NULL`), `active_content_hash` equals `content_hash`. The unique index enforces that **at most one live document with this content can exist in this workspace**.
   - When a document is soft-deleted, `active_content_hash` is set to `NULL`. Since multiple `NULL`s do not collide in B-tree unique indexes, soft-deleted rows never block future uploads.

---

## 3. Upload Validation, Storage & Duplicate Prevention

### Validation Rules (`StoreKnowledgeDocumentRequest`)
- **Allowed Extensions**: `xlsx`, `csv`, `txt`.
- **Allowed MIME Types**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `text/plain`, `text/csv`, `application/csv`, `text/x-csv`.
- **Maximum File Size**: 20 MB.
- **Dual Validation**: Enforces both `mimes` and `mimetypes` rules so file extensions cannot be spoofed to bypass validation.

### Secure Storage Pipeline
1. **Hashing**: SHA-256 hash is computed directly from the uploaded file stream on temporary disk using `hash_file('sha256', ...)`.
2. **Fast Pre-check**: Fast query on `(workspace_id, active_content_hash)` avoids disk writes if an active duplicate already exists.
3. **Randomized File Naming**: Stored filename is generated using `(string) Str::uuid() . '.' . $extension` under `documents/{workspace_id}/`. Directory traversal (`../`) is impossible.
4. **Private Disk Isolation**: Stored strictly via `Storage::disk('local')->putFileAs(...)` under `storage/app/private/`. Files are never accessible via public HTTP URLs.
5. **Rollback & Safe Cleanup**: If database insertion fails or triggers a unique key conflict:
   - Only the freshly created UUID file for that request is purged via `safeDeleteFile()`.
   - Pre-existing files belonging to other documents are never deleted.
   - Cleanup exceptions are caught and logged, ensuring they never mask the primary database error.

---

## 4. Same-Workspace Duplicate Prevention & Cross-Workspace Isolation

- **Same-Workspace Duplicate Prevention**: Two identical documents cannot exist concurrently in the same workspace. Attempting to upload identical content triggers a validation error (HTTP 422 in JSON/AJAX, redirect with error session in Web UI), backed by the engine-level unique index.
- **Cross-Workspace Allowance**: The unique index is composite (`workspace_id, active_content_hash`). Workspace A and Workspace B can independently upload the identical file without collision.

---

## 5. Soft-Delete, Re-Upload, and Restore Conflict Policy

1. **Soft-Deletion**:
   - Setting `deleted_at` also nullifies `active_content_hash` inside a database transaction and via the model `deleting` hook.
   - The file remains on private storage for potential restoration or audit compliance.
2. **Re-Uploading After Deletion**:
   - Because the trashed document has `active_content_hash = NULL`, uploading the same content again succeeds immediately as a new document record with its own UUID and physical file.
3. **Restore Conflict Handling**:
   - If Document 1 was deleted, Document 2 was uploaded with the same content, and an admin attempts to restore Document 1:
   - `KnowledgeDocumentService::restore()` detects that an active document with that content hash already exists.
   - The restore request is rejected with a validation error: *"Cannot restore document because an active document with identical content already exists in this workspace."*
   - If Document 2 is subsequently deleted or trashed, Document 1 can be restored cleanly.

---

## 6. Authentication, Permissions & Workspace Scoping

### Granular Spatie Permissions
Registered in `App\Enums\Permissions\KnowledgeDocumentPermission`:
- `document.view`: Access list and view DataTables records.
- `document.create`: Access upload form and submit new documents.
- `document.update`: Restore soft-deleted documents.
- `document.delete`: Soft-delete documents.

Assigned to the `admin` role in `database/seeders/RoleSeeder.php`.

### Controller Authorization
[`KnowledgeDocumentController`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/KnowledgeDocumentController.php) applies defense-in-depth:
- Route middleware filters unauthorized requests.
- Every controller method explicitly verifies the required permission via `authorizeAction()`.
- `destroy()` and `restore()` verify workspace ownership (`$document->workspace_id === Auth::user()->workspace_id`).
- Foreign-workspace identifiers fail closed (403 on delete, 404 on restore) without leaking document metadata.
- Superadmins (`RoleEnum::SUPERADMIN`) bypass workspace restrictions.

---

## 7. Routes, Admin UI & Responsibilities

### Registered Routes (`routes/web.php`)
- `GET /dashboard/documents` (`documents.index`) — Document listing and DataTables AJAX endpoint.
- `GET /dashboard/documents/create` (`documents.create`) — Document upload form.
- `POST /dashboard/documents` (`documents.store`) — Validated document upload handler.
- `DELETE /dashboard/documents/{document}` (`documents.destroy`) — Soft delete handler.
- `POST /dashboard/documents/{id}/restore` (`documents.restore`) — Trashed document restore handler.

### Admin Blade Views
- `resources/views/admin/documents/index.blade.php`: Responsive DataTables table with columns: Index, Title, Original Filename, Size, MIME Badge, Date, Lifecycle Status Badge, and Actions (Trash / Restore).
- `resources/views/admin/documents/create.blade.php`: Drag-and-drop file upload interface with client-side file type and size validation.
- `resources/views/admin/partials/sidebar.blade.php`: Navigation item under Knowledge Management, visible to users with `document.view` permission.

---

## 8. File Inventory & Responsibilities

| File Path | Responsibility |
|---|---|
| `app/Enums/Permissions/KnowledgeDocumentPermission.php` | Defines `document.view`, `create`, `update`, `delete` permission enum. |
| `app/Enums/DocumentLifecycleStatus.php` | Defines lifecycle states: `pending_ingestion`, `indexing`, `active`, `failed` with UI badge helpers. |
| `app/Models/KnowledgeDocument.php` | Eloquent model with UUID keys, soft deletes, type casts, and `booted()` event hooks for `active_content_hash`. |
| `app/Http/Requests/StoreKnowledgeDocumentRequest.php` | Validates file presence, extensions (`xlsx,csv,txt`), MIME types, and 20 MB size cap. |
| `app/Services/Knowledge/KnowledgeDocumentService.php` | Business logic for hashing, storage, deduplication, DataTables query, deletion, and restoration. |
| `app/Http/Controllers/KnowledgeDocumentController.php` | HTTP layer orchestrating views, requests, service delegation, and RBAC authorization. |
| `database/migrations/2026_10_14_000001_create_knowledge_documents_table.php` | Base schema creating `knowledge_documents` table. |
| `database/migrations/2026_10_14_000002_add_active_content_hash_to_knowledge_documents_table.php` | Schema update adding `active_content_hash` and unique index. |
| `database/factories/KnowledgeDocumentFactory.php` | Testing factory with realistic test file payloads and lifecycle states. |
| `database/seeders/KnowledgeDocumentSeeder.php` | Seeds realistic business documents (`.xlsx`, `.csv`, `.txt`) with persisted private files. |
| `resources/views/admin/documents/index.blade.php` | DataTables server-side listing view. |
| `resources/views/admin/documents/create.blade.php` | File upload form view. |
| `tests/Feature/KnowledgeDocumentManagementTest.php` | 17 comprehensive feature tests covering validation, storage, deduplication, isolation, and permissions. |

---

## 9. Test Verification Results

All tests executed via PHPUnit on Laravel 12:

### 1. Document Management Feature Tests
```bash
php artisan test tests/Feature/KnowledgeDocumentManagementTest.php
```
- **Passed**: 17 tests, 111 assertions (Duration: 1.30s)
  - Authorized upload of text, xlsx, csv
  - Rejection of spoofed and unsupported types
  - Size cap rejection (>10 MB)
  - Randomized UUID file storage on private disk
  - Same-workspace duplicate rejection
  - Cross-workspace duplicate allowance
  - Workspace scoping on listing, delete, restore
  - Unauthenticated & unauthorized rejection
  - Cleanup of temporary files on database error
  - Truthful `pending_ingestion` lifecycle status and zero job dispatch
  - Superadmin cross-workspace management
  - Server-side DataTables AJAX response
  - Re-upload after soft-deletion
  - Restoration rejection on active conflict
  - Database-level unique constraint collision handling
  - Granular `document.view` vs `document.delete` authorization

### 2. FAQ Pipeline Regression Tests
```bash
php artisan test --filter=FAQ
```
- **Passed**: 34 tests, 198 assertions (Duration: 1.92s)

### 3. Business Source of Truth Integration Tests
```bash
php artisan test tests/Feature/BusinessSourceOfTruthIntegrationTest.php
```
- **Passed**: 11 tests, 59 assertions (Duration: 0.33s)

### 4. Git Diff Formatting
```bash
git diff --check
```
- **Status**: 0 errors.

---

## 10. Known Limitations & Phase 2 Prerequisites

### Production Migration Preflight Query
Before running `php artisan migrate` on an existing database (staging or production), operators must run:

```sql
SELECT workspace_id, content_hash, COUNT(*) AS active_duplicates
FROM knowledge_documents
WHERE deleted_at IS NULL
GROUP BY workspace_id, content_hash
HAVING COUNT(*) > 1;
```

If duplicate groups are returned, an operator must inspect and resolve them before adding the unique index.

### Concurrency Testing Limitation
Single-threaded PHP CLI tests confirm that database unique constraint violations trigger proper rollback and file cleanup. Multi-process concurrency load tests (using tools like `k6`) should be conducted in staging to evaluate database lock wait times under high contention.

### Phase 2 Prerequisites (Handoff Checklist)
1. **Extraction Service Integration**: Implement background jobs calling the Python retrieval service to extract text from TXT, CSV, and XLSX files.
2. **Chunking Pipeline**: Define chunk sizes and overlaps for extracted text.
3. **Embedding Generation & Typesense Sync**: Create the Typesense collection schema for business documents and batch push vector embeddings.
4. **Lifecycle Transitions**: Transition document status from `pending_ingestion` -> `indexing` -> `active` (or `failed`).
5. **Search & Agent Grounding**: Update `HybridRouter` to ground customer responses against active business document chunks alongside existing FAQs.
