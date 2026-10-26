# 01. Product Requirements Document (PRD) — Multi-Tenant AI Customer Support SaaS Platform

> **Status**: CURRENTLY_VERIFIED  
> **Source of Truth**: Reverse-engineered from Laravel 12 application codebase, database migrations, Python microservices, and automated test suite.  
> **Last Updated**: October 2026  

---

## 1. Executive Summary & Vision

The **Multi-Tenant AI Customer Support SaaS Platform** is an enterprise-grade, omni-channel customer support and conversational AI solution designed for modern businesses and multi-brand enterprises. It enables businesses to deploy autonomous AI agents capable of answering product questions, resolving policy queries, querying complex structured business analytics (sales, dues, inventory from spreadsheets/catalogs), remembering returning customer preferences across sessions, and escalating sensitive actions with human-in-the-loop safeguards.

The platform provides strict multi-tenant isolation, comprehensive role-based access control (RBAC), an integrated **Unified Knowledge Base Hub**, and a deterministic **Semantic Routing Engine** preventing LLM hallucinations and unauthorized data leakage.

---

## 2. Target Personas & User Classes

| Persona | Description & Needs | Primary Interfaces | Permissions & Access Scope |
| :--- | :--- | :--- | :--- |
| **Superadmin (Platform Owner)** | Manages platform infrastructure, global workspaces, cross-workspace diagnostics, and global tenant onboarding. | Master Dashboard, Hub Workspace Switcher, Role/User Management, Simulator. | Global bypass; can inspect any workspace explicitly via filter; cannot silently insert records without workspace context. |
| **Tenant Admin / Support Manager** | Business owner or customer success lead. Manages their workspace's FAQs, policies, analytics documents, team roles, and monitors chat history. | Unified Knowledge Base Hub, Conversations Inbox, Observability Dashboard, Chat Simulator. | Strictly scoped to their authorized `workspace_id`. Governed by granular Spatie permissions (`faq.*`, `knowledge_document.*`, `conversation.*`). |
| **Customer Support Agent (Human)** | Handles escalated inquiries, reviews conversation logs, sends manual replies or triggers AI-assisted drafts. | Conversations Inbox, Chat Detail view (`/dashboard/conversations/{id}`). | `conversation.view`, `conversation.update`. Cannot modify system FAQs or document indexes without elevated permissions. |
| **End-User / Customer** | Shopper or client asking questions via Facebook Messenger or web chat widgets. | Facebook Messenger / Omni-channel webhooks, Public Chat Simulator. | External unauthenticated/authenticated end-users. Scoped to conversational session with identity resolution across PSID, email, and phone. |

---

## 3. Core Product Capabilities & Functional Requirements

### 3.1 Unified Knowledge Base Hub (KB Hub)
* **Canonical Hub Interface (`/dashboard/knowledge-base`)**: Consolidated interface replacing fragmented standalone pages with tab-based navigation:
  - **Tab 1: FAQs (`type_group=faq`)**: Q&A pairs for general inquiries.
  - **Tab 2: Policies (`type_group=policy`)**: Legal, return, shipping, and terms policies.
  - **Tab 3: Business Info (`type_group=business_info`)**: Company background, operating hours, branches, customer support desk details.
  - **Tab 4: Data Sources (`purpose=data_analytics`)**: Uploaded structured spreadsheets (CSV, XLSX, XLS) powering business intelligence queries.
* **Direct UI Reindexing (`POST /dashboard/knowledge-base/reindex`)**:
  - One-click reindex button enabling authorized admins to regenerate vector embeddings and sync active FAQs directly to Typesense without requiring CLI access or background queue worker dependencies.
* **Lifecycle & State Management**:
  - Full lifecycle tracking: `active`, `syncing`, `sync_failed`, `draft`, `archived`.
  - Concurrency locks preventing race conditions between queue workers and manual re-syncs.
  - Soft-delete and single-click restoration for FAQ and Document records.
* **Document Ingestion & Preview**:
  - Supports CSV, XLSX, XLS files with automated sheet discovery, header extraction, column type inference, and sample row previews (`/dashboard/documents/{id}/preview`).

### 3.2 Intent-Driven Multi-Source Routing (HybridRouter v2.2)
Queries are routed strictly based on **Semantic Intent**, never by database prefixes:
1. **`KNOWLEDGE`**: Product specifications, catalog queries, policies, shipping, and return FAQs. Executed via `KnowledgeSupportAgent` using Hybrid Typesense Retrieval + Relational Catalog.
2. **`ANALYTICS`**: Aggregated sales, revenues, dues, product performance, and salesperson reports. Executed via Python Analytics Service against SQLite/MySQL analytical tables.
3. **`CONVERSATIONAL`**: Greetings, chitchat, bot identity, and acknowledgments. Executed via `ConversationalSupportAgent` without costly retrieval calls.
4. **`ACTION`**: Imperative tasks requiring human confirmation (e.g., emailing a salesperson). Enforces a deterministic two-phase commit: Turn $N$ generates a SHA-256 fingerprint proposal; Turn $N+1$ executes only upon explicit user confirmation.
5. **`UNCERTAIN`**: Ambiguous queries. Returns interactive clarification chips allowing the customer to specify their exact intent.
6. **`OOD` (Out of Domain)**: Harmless rejection message for off-topic, political, or adversarial inquiries.

### 3.3 Long-Term Conversation Graph Memory
* Powered by `python-memory-service` (FastAPI + Neo4j Community + Graphiti).
* **Identity Resolution**: Unifies customer identities across external IDs (Facebook PSID), CRM contacts, emails, phones, and conversation fallbacks.
* **Semantic Memory Retrieval**: Extracts and stores user preferences (e.g., preferred color, budget, sizing), past purchases, and unresolved support issues.
* **Memory Relevance Gate**: Validates retrieved graph memories against current query intent before injecting them into the LLM context window to prevent distraction.

### 3.4 Interactive Chat Simulator & Developer Console
* Live debugging sandbox (`/dashboard/simulator`) for testing prompt engineering, memory injection, retrieval rankings, and intent routing in real time.
* Workspace switching and data source scoping directly from the simulator toolbar.
* Dynamic export of conversation sessions and analytics reports to PDF, CSV, and Excel.

---

## 4. Acceptance Criteria & Quality Attributes

| Area | Acceptance Criterion | Verification Method |
| :--- | :--- | :--- |
| **Tenant Isolation** | No user or query shall retrieve or mutate data belonging to another `workspace_id`. Superadmin creation must reject missing or invalid target workspaces. | Automated Feature Tests: `KnowledgeBaseHubTest`, `KnowledgeDocumentTenantIsolationTest`, `FAQTenantAndManagementTest`. |
| **Authorization Enforcement** | Endpoints and DataTables AJAX dispatchers must enforce resource-level permissions (`faq.view`, `knowledge_document.view`). Unauthorized calls return HTTP 403 Forbidden. | Feature Test: `KnowledgeBaseHubTest` (22 assertions verifying granular tab & JSON 403 gating). |
| **Retrieval Accuracy** | FAQ search must achieve high relevance through hybrid dense vector (768-d) + BM25 keyword matching with language-aware tokenization. | Automated Benchmarks: `StaticKnowledge200BenchmarkTest`, `benchmark_retrieval_v2.py`. |
| **Data Safety** | LLMs must never generate unconstrained raw SQL against production transactional tables. Analytics queries must pass through a strict semantic AST compiler. | Python Unit Tests: `test_security_resilience.py`, `SemanticValidator`. |
| **Resilience & Concurrency** | Race conditions between stale queue workers, deactivations, and reindexes must fail closed and never resurrect soft-deleted or inactive items in Typesense. | Automated Concurrency Tests: `FAQJobRaceConditionTest` (19 passed tests). |

---

## 5. System Limitations & Non-Goals

### 5.1 Current System Limitations
1. **Single Vector Store per Tenant**: Multi-tenancy in Typesense is logical (scoped via `workspace_id` filter in a single collection), not physical separate clusters per tenant.
2. **Synchronous Reindex Timeout**: UI reindexing operates synchronously over HTTP; large workspaces with >10,000 FAQs require CLI batching (`php artisan faq:reindex`) to prevent PHP request timeouts.
3. **Graph Memory Neo4j Dependency**: Neo4j Community edition does not support multi-database clustering; tenant isolation in Neo4j relies on node-level `workspace_id` properties.

### 5.2 Strict Non-Goals
* **No Direct Schema Alterations via Chat**: The AI assistant cannot execute DDL, migrations, or database mutations.
* **No Automated Financial Transactions**: The AI cannot process credit cards, initiate refunds, or finalize payments autonomously.
* **No Unconstrained Web Scraping**: The knowledge base ingests uploaded files and explicit admin entries; it does not crawl arbitrary public websites autonomously.
* **No Merging of FAQ and Analytics Tables**: FAQ text knowledge and structured relational/tabular datasets remain strictly partitioned by architectural design.
