import os

BASE_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"

def write_file(filename, content):
    filepath = os.path.join(BASE_DIR, filename)
    with open(filepath, "w", encoding="utf-8") as f:
        f.write(content.strip() + "\n")
    print(f"Written: {filename} ({len(content)} bytes)")

# -------------------------------------------------------------
# 07_AI_AGENTS_AND_KNOWLEDGE_FLOWS.md
# -------------------------------------------------------------
write_file("07_AI_AGENTS_AND_KNOWLEDGE_FLOWS.md", """# Central AI: AI Agents & Knowledge Flows

- **Document ID**: `ARCH-07`
- **Scope**: Multi-Agent Orchestration, Intent Classification, and Vector RAG Ingestion Pipeline
- **Audit Status**: Verified `[OBSERVED]` (Settings & UI) / `[DOCUMENTED]` (Retrieval Specs)

---

## 1. Multi-Agent Orchestration Architecture

Unlike single-agent RAG implementations that attempt to handle knowledge retrieval, qualification, sales, and ticketing in one prompt, Central AI implements a **Specialized 6-Agent Modular Architecture**:

```mermaid
graph TB
    subgraph InboundMessage ["Inbound Ingress"]
        IN_MSG["Incoming Customer Turn<br/>(Web / Messenger / WhatsApp)"]
    end

    subgraph Orchestrator ["Agent Supervisor & Intent Router"]
        ROUTER{"Intent & Entity Router<br/>[Deterministic + LLM Classifier]"}
    end

    subgraph SpecializedAgents ["Specialized Autonomous Agents"]
        AGENT_CHAT["1. Chat Agent<br/>(Knowledge RAG Q&A)"]
        AGENT_SALES["2. AI Sales Agent<br/>(Catalog & Quotes)"]
        AGENT_SMART["3. Smart Lead Agent<br/>(Multi-Turn Qualification)"]
        AGENT_CAPTURE["4. Auto Lead Capture<br/>(Background Extraction)"]
        AGENT_ENGAGE["5. AI Lead Engagement<br/>(Outbound Nurturing)"]
        AGENT_SUPPORT["6. Support Ticket Agent<br/>(Issue Escalation)"]
    end

    subgraph SharedDataLayer ["Shared Platform Data Stores"]
        KB_STORE[("Knowledge Vector Index<br/>(Top-K Similarity > 0.72)")]
        CATALOG_STORE[("Offerings Catalog<br/>(/sales/offerings/)")]
        LEAD_STORE[("Leads & CRM DB<br/>(/leads/)")]
        TICKET_STORE[("Support Tickets DB<br/>(/support/)")]
    end

    IN_MSG --> ROUTER
    ROUTER -->|General Info / Q&A| AGENT_CHAT
    ROUTER -->|Buying / Product Inquiries| AGENT_SALES
    ROUTER -->|High Purchase Intent| AGENT_SMART
    ROUTER -->|Passive Contact Mentions| AGENT_CAPTURE
    ROUTER -->|Inactivity / Follow-up| AGENT_ENGAGE
    ROUTER -->|Complaint / Frustration| AGENT_SUPPORT

    AGENT_CHAT <--> KB_STORE
    AGENT_SALES <--> CATALOG_STORE
    AGENT_SMART --> LEAD_STORE
    AGENT_CAPTURE --> LEAD_STORE
    AGENT_SUPPORT --> TICKET_STORE
```

---

## 2. Intent Routing & Classification Rules

1. **Deterministic Filter**:
   - Regex patterns for contact data (Email: `[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}`, Phone: `(?:\+?88)?01[3-9]\d{8}`) are passively intercepted by **Auto Lead Capture** regardless of active agent state `[OBSERVED]`.
   - Explicit keyword triggers (e.g., *"buy"*, *"price"*, *"how much"*, *"cost"*) route to **AI Sales Agent** `[DOCUMENTED]`.
   - Frustration keywords (e.g., *"human"*, *"agent"*, *"broken"*, *"terrible"*, *"refund"*) route to **Support Ticket Agent** `[DOCUMENTED]`.
2. **Contextual Dispatch**:
   - When an ongoing qualification dialogue is underway, **Smart Lead Agent** retains conversational turn priority until qualification concludes or customer shifts topic.

---

## 3. Retrieval-Augmented Generation (RAG) Flow

```mermaid
sequenceDiagram
    autonumber
    actor Customer as User
    participant Router as Supervisor Router
    participant Embedder as Embedding Model
    participant VectorDB as Vector Store
    participant PromptEng as Prompt Assembler
    participant LLM as Language Model (Low Temp 0.2)
    participant Output as Response Formatter

    Customer->>Router: "What is your warranty policy for electronics?"
    Router->>Embedder: Compute vector embedding of query
    Embedder->>VectorDB: Query nearest neighbors (Cosine similarity)
    VectorDB-->>Router: Return top K=4 chunks (Similarity score: 0.88, 0.81, 0.76, 0.73)
    Router->>PromptEng: Context Injection: System Prompt + Brand Persona + Chunks + Conversation History
    PromptEng->>LLM: Dispatch synthesized prompt with strict hallucination boundaries
    LLM-->>PromptEng: Generated answer: "Electronics carry a 1-year replacement warranty..."
    PromptEng->>Output: Attach source citation tags (Doc: Terms_2026.pdf, Page 4)
    Output-->>Customer: Render stream response in chat
```

### Retrieval Parameters
- **Chunk Size**: Approximately 400 characters `[DOCUMENTED]`.
- **Chunk Overlap**: ~80 characters `[DOCUMENTED]`.
- **Top-K Chunks**: 4 candidate passages injected per prompt.
- **Similarity Threshold**: ~0.72 minimum cosine similarity cutoff.
- **Hallucination Prevention**: Strict system instruction: *"Only answer based on the provided context. If the information is not contained in the context, politely state that you do not have that information and suggest contacting support."* `[DOCUMENTED]`.

---

## 4. Architectural Comparison: Central AI vs `multi-source-chatbot`

| Architectural Layer | Central AI Platform | Our System (`multi-source-chatbot`) |
| :--- | :--- | :--- |
| **Agent Paradigm** | Specialized 6-Agent routing pipeline with sales and CRM handoffs `[OBSERVED]`. | Single conversational assistant with multi-source retrieval adapters `[OBSERVED]`. |
| **Tabular Data** | Flattened text chunks (`col: val`). Cannot perform dynamic math/SQL queries `[DOCUMENTED]`. | **SQLite per-file synthesis**: Dynamic SQL queries, aggregations, cross-tab joins `[OBSERVED]`. |
| **Vector Engine** | Cloud-hosted vector index (proprietary/managed) `[DOCUMENTED]`. | **Typesense Hybrid Search**: Dense vector + sparse BM25 text retrieval `[OBSERVED]`. |
| **Commerce Interconnect**| Native Offering entities, cart state, bKash/Nagad TrxID recording, HMAC API `[DOCUMENTED]`. | Purely informational RAG (no transactional commerce engine) `[OBSERVED]`. |

---

## 5. Verification Status & Evidence
- **Documented**: Chunking parameters, similarity cutoffs, and agent roles verified in official platform specifications `[DOCUMENTED]`.
- **Observed**: Agent playground citation tags and settings verified at `/chat/chatbot/` `[OBSERVED]`.
""")

# -------------------------------------------------------------
# 08_LEADS_TICKETS_AND_HUMAN_HANDOFF.md
# -------------------------------------------------------------
write_file("08_LEADS_TICKETS_AND_HUMAN_HANDOFF.md", """# Central AI: Leads, Tickets & Human Handoff

- **Document ID**: `WORK-08`
- **Scope**: Lead Capture, Qualification Heuristics, Odoo Pipeline Synchronization, and Support Escalation
- **Audit Status**: Verified `[OBSERVED]` (Leads & Odoo UI) / `[DOCUMENTED]` (Scoring Rules)

---

## 1. Lead Lifecycle Architecture

```mermaid
stateDiagram-v2
    [*] --> InboundVisitor
    InboundVisitor --> LeadCaptured: Passive Extraction (Phone/Email)
    InboundVisitor --> QualificationDialogue: High Purchase Intent
    
    state QualificationDialogue {
        [*] --> InquiringBudget
        InquiringBudget --> InquiringTimeline
        InquiringTimeline --> InquiringAuthority
        InquiringAuthority --> ComputeScore
    }

    ComputeScore --> SalesQualified: Score >= 70
    ComputeScore --> MarketingQualified: Score < 70
    
    SalesQualified --> OdooCRM: Instant Sync to crm.lead
    SalesQualified --> PartnerRouting: Optional Dealer Distribution
    MarketingQualified --> LeadTable: Persisted in /leads/
    
    LeadCaptured --> LeadTable
    OdooCRM --> [*]
    PartnerRouting --> [*]
    LeadTable --> [*]
```

---

## 2. Lead Qualification Heuristics & Scoring Algorithm

The **Smart Lead Agent** evaluates prospective buyers across four dimensions (BANT criteria) `[DOCUMENTED]`:

| Evaluation Dimension | Scoring Weight | Criteria / Range | Points Awarded |
| :--- | :--- | :--- | :--- |
| **Budget** | 35% | Enterprise (> $5,000 / 500k BDT)<br/>Mid-Market ($1,000 - $5,000)<br/>Starter (< $1,000) | 35 pts<br/>20 pts<br/>5 pts |
| **Timeline** | 25% | Immediate (< 30 days)<br/>Quarterly (1 - 3 months)<br/>Exploratory (> 3 months) | 25 pts<br/>15 pts<br/>5 pts |
| **Need / Fit** | 25% | Solvable by catalog offering<br/>Partial capability match<br/>Out of scope inquiry | 25 pts<br/>10 pts<br/>0 pts |
| **Authority** | 15% | Decision maker / Owner<br/>Evaluator / Manager<br/>Student / Casual | 15 pts<br/>10 pts<br/>0 pts |

- **Hot Lead Threshold**: A cumulative score of **>= 70** flags the lead as `HOT`, triggering instant email/webhook alerts to human sales staff `[DOCUMENTED]`.

---

## 3. Odoo CRM Pipeline Synchronization (`/leads/integrations/`)

```mermaid
flowchart LR
    LEAD["Captured Lead (/leads/)"] --> MAPPER["Odoo Entity Normalizer"]
    MAPPER --> AUTH["XML-RPC Auth (Host, DB, API Key)"]
    AUTH --> ENDPOINT["Odoo CRM crm.lead Model"]
    ENDPOINT --> STAGE{"Stage Assignment"}
    STAGE -->|Score >= 70| STG_QUAL["Stage: Qualified"]
    STAGE -->|Score < 70| STG_NEW["Stage: New Opportunity"]
    ENDPOINT --> LOG["Sync Audit Log (/leads/integrations/)"]
```

### Odoo Synchronization Controls
- **Sync Modes**:
  1. *Automatic Instant Sync*: Qualified leads push immediately upon session completion `[DOCUMENTED]`.
  2. *Manual Bulk Sync*: Admins select rows on `/leads/` and click "Sync Selected to Odoo" `[OBSERVED]`.
- **Status Indicators**: Each lead row displays a status pill: `Synced`, `Pending`, `Failed`, or `Not Configured` `[OBSERVED]`.

---

## 4. Partner Lead Sharing (`/leads/partners/`)
Designed for organizations operating franchised branches, dealers, or territory agents:
- **Routing Rules**: Route by geographic territory, product category, or round-robin rotation.
- **Quota Allocation**:
  - Growth Plan: 1 connected partner organization `[DOCUMENTED]`.
  - Scale Plan: Up to 5 connected partner organizations `[DOCUMENTED]`.

---

## 5. Support Ticket Escalation & Live Human Takeover

### Handoff Triggers
1. **Explicit Request**: User types *"agent"*, *"speak to human"*, *"operator"*.
2. **Negative Sentiment**: Frustration score exceeds threshold (> 0.7 negativity) across 2 consecutive turns.
3. **Repeated Retrieval Failure**: Agent fails to answer same question twice in a session.

### Live Takeover Mechanics
- **Bot Pause**: When human takeover triggers, Central AI temporarily disables autonomous bot message responses for that specific `session_id`.
- **Notification**: Instant alert dispatched via email, webhook, or sound alert on the `/chat/chat-logs/` dashboard.
- **Operator Resumption**: The human agent types responses directly from the dashboard; once marked resolved, bot auto-replies can be resumed.

---

## 6. Verification Status & Evidence
- **Observed**: Screen `/leads/`, export buttons, and `/leads/integrations/` Odoo settings inspected in live UI `[OBSERVED]`.
- **Documented**: BANT scoring framework and partner limits documented in enterprise feature matrix `[DOCUMENTED]`.
""")

# -------------------------------------------------------------
# 09_DATA_ENTITIES_AND_DEPENDENCIES.md
# -------------------------------------------------------------
write_file("09_DATA_ENTITIES_AND_DEPENDENCIES.md", """# Central AI: Data Entities & System Dependencies

- **Document ID**: `DATA-09`
- **Scope**: Entity Relationship Diagram, Relational Schema, and Cross-System Dependencies
- **Audit Status**: Verified `[OBSERVED]` (Forms & Tables) / `[INFERRED]` (Foreign Key Relationships)

---

## 1. Entity-Relationship (ER) Architecture

```mermaid
erDiagram
    COMPANY ||--o{ USER : employs
    COMPANY ||--o{ API_KEY : owns
    COMPANY ||--o{ DOMAIN_WHITELIST : registers
    COMPANY ||--o{ OFFERING : catalogs
    COMPANY ||--o{ DOCUMENT : stores
    COMPANY ||--o{ FAQ_ITEM : maintains
    COMPANY ||--o{ CHAT_SESSION : hosts
    COMPANY ||--o{ LEAD : captures

    DOCUMENT ||--o{ EMBEDDING_CHUNK : decomposes
    FAQ_ITEM ||--o{ EMBEDDING_CHUNK : embeds
    
    CHAT_SESSION ||--o{ CHAT_MESSAGE : contains
    CHAT_SESSION ||--o{ LEAD : produces
    CHAT_SESSION ||--o{ ORDER : generates
    CHAT_SESSION ||--o{ SUPPORT_TICKET : escalates

    ORDER ||--o{ ORDER_ITEM : includes
    OFFERING ||--o{ ORDER_ITEM : specifies
    ORDER ||--o{ PAYMENT_RECEIPT : verifies

    LEAD ||--o{ ODOO_SYNC_LOG : tracks
```

---

## 2. Core Entity Dictionaries

### A. Organization & Tenancy
- **`Company`**: Root tenant container. Attributes: `id`, `name`, `subdomain`, `currency` (BDT, USD), `timezone`, `plan_id`.
- **`User`**: Account member. Attributes: `id`, `company_id`, `email`, `role` (Admin, Operator, Viewer), `created_at`.
- **`ApiKey`**: Credentials. Attributes: `key_prefix` (`ca_` vs `cax_`), `hashed_key`, `scope`, `is_active`.
- **`DomainWhitelist`**: Authorized widget hostnames. Attributes: `id`, `company_id`, `domain_name`.

### B. Knowledge & Retrieval
- **`Document`**: Raw files. Attributes: `id`, `company_id`, `title`, `file_path`, `file_size_bytes`, `mime_type`, `status` (Processing, Active, Error).
- **`FAQItem`**: Tabular Q&A. Attributes: `id`, `company_id`, `question`, `answer`, `title`, `url`, `price`.
- **`EmbeddingChunk`**: Vectorized segments. Attributes: `id`, `source_type` (doc/faq/web), `source_id`, `chunk_index`, `text_content`, `vector_id`.

### C. Commerce & Sales
- **`Offering`**: Sellable products/services. Attributes: `id`, `company_id`, `name`, `offering_type` (Product, Service, Course, Room, Custom), `price`, `currency`, `image_url`, `is_active`.
- **`Order`**: Transaction records. Attributes: `id`, `company_id`, `session_id`, `customer_name`, `phone`, `total_amount`, `currency`, `payment_method` (bKash, Nagad, Gateway), `payment_status` (Pending, Verifying, Paid).
- **`PaymentReceipt`**: MFS transaction logs. Attributes: `id`, `order_id`, `transaction_id` (TrxID), `verified_by_user_id`, `verified_at`.

### D. Leads & Support
- **`Lead`**: Captured prospects. Attributes: `id`, `company_id`, `session_id`, `name`, `email`, `phone`, `company_name`, `lead_score`, `odoo_lead_id`, `odoo_sync_status`.
- **`SupportTicket`**: Escalated issues. Attributes: `id`, `company_id`, `session_id`, `category`, `urgency` (Critical, High, Normal, Low), `status` (Open, Assigned, Resolved).

---

## 3. External System Dependencies

| Component | Target External System | Protocol / Format | Dependency Role |
| :--- | :--- | :--- | :--- |
| **Meta Hub** | Meta Graph API (v19.0+) | HTTPS Webhooks & REST | Ingests Facebook, Instagram & WhatsApp messages |
| **CRM Integration** | Odoo ERP (v14 - v17) | XML-RPC / REST JSON | Bidirectional sync of leads and pipeline stages |
| **Domestic Payments**| bKash / Nagad / Rocket | Manual TrxID / API | Settlement of South Asian customer orders |
| **International Pay**| SSLCommerz / Stripe | Redirect & IPN Webhook | Card payment capture & currency conversion |
| **Vector Engine** | Dedicated Vector DB | gRPC / REST API | Semantic nearest-neighbor retrieval |

---

## 4. Verification Standard
- **Observed**: Relational fields confirmed via dashboard screens (`/knowledge/`, `/sales/`, `/leads/`, `/accounts/`) `[OBSERVED]`.
- **Inferred**: Foreign key schema relationships logically inferred from data dependencies `[INFERRED]`.
""")

# -------------------------------------------------------------
# 10_ROLES_PERMISSIONS_AND_SECURITY.md
# -------------------------------------------------------------
write_file("10_ROLES_PERMISSIONS_AND_SECURITY.md", """# Central AI: Roles, Permissions & Security Architecture

- **Document ID**: `SEC-10`
- **Scope**: Authentication Models, Access Control, Origin Filtering, and Data Protection
- **Audit Status**: Verified Active `[OBSERVED]` (Key UI & Settings) / `[DOCUMENTED]` (Security Specifications)

---

## 1. Dual-Key Authentication Model

Central AI implements a strict segregation of privilege through two distinct credential classes `[OBSERVED]`:

```mermaid
graph TD
    subgraph PublicSphere ["Client-Side (Public Web)"]
        WIDGET["Web Chat Widget<br/>(Embedded on client site)"]
        PUB_KEY["Public Key: ca_pub_..."]
    end

    subgraph SecretSphere ["Backend / Server-to-Server"]
        BACKEND["Client ERP / Sync Daemon"]
        PRIV_KEY["Secret Server Key: cax_live_..."]
    end

    subgraph SecurityGateway ["Central AI Security Layer"]
        ORIGIN_GUARD["Origin & CORS Guard<br/>(Verifies Domain Whitelist)"]
        KEY_AUTH["API Key Authenticator<br/>(Constant-time Hash Check)"]
        RATE_LIMIT["Token Bucket Rate Limiter"]
    end

    subgraph PlatformCore ["Internal API Endpoints"]
        CHAT_API["POST /api/v1/chat/message/<br/>(Chat Privileges Only)"]
        ADMIN_API["POST /knowledge/api/v1/sync/<br/>POST /api/v1/commerce/<br/>(Full Admin & Sync Privileges)"]
    end

    WIDGET --> PUB_KEY
    PUB_KEY --> ORIGIN_GUARD
    ORIGIN_GUARD --> RATE_LIMIT
    RATE_LIMIT --> CHAT_API

    BACKEND --> PRIV_KEY
    PRIV_KEY --> KEY_AUTH
    KEY_AUTH --> ADMIN_API
```

---

## 2. Domain Origin Whitelisting

- **Enforcement**: Public widget keys (`ca_...`) only accept incoming chat requests if the HTTP `Origin` header precisely matches an approved domain registered in `/chat/widget/` `[OBSERVED]`.
- **Prevention**: Prevents malicious third-party websites from scraping customer chatbots or draining message quotas by stealing public widget snippets.

---

## 3. Role-Based Access Control (RBAC)

The administrative web console provides three user access tiers `[DOCUMENTED]`:

| Permission / Capability | Admin | Operator / Support | Viewer / Analyst |
| :--- | :---: | :---: | :---: |
| Modify Knowledge Base & Crawls | Yes | No | No |
| Adjust Offerings & Pricing | Yes | No | No |
| View Chat Logs & Transcripts | Yes | Yes | Yes (Read-Only) |
| Manual Odoo Sync & Export Leads | Yes | Yes | No |
| Human Handoff Live Takeover | Yes | Yes | No |
| Manage Billing & Company Profile | Yes | No | No |
| Regenerate API Keys | Yes | No | No |

---

## 4. Multi-Tenant Data Isolation

- **Tenant Isolation**: All database queries are scoped by `company_id`. Vector embeddings are indexed with tenant metadata tags or partitioned namespaces to prevent cross-tenant information leakage `[DOCUMENTED]`.
- **PII Scrubbing**: Regex filters automatically scrub or tokenize sensitive payment card information (PAN) from chat logs prior to vectorization `[DOCUMENTED]`.
- **Regulatory Compliance**: Adheres to the **Bangladesh Cyber Security Act 2023** and local data residency standards regarding transactional financial logs `[DOCUMENTED]`.

---

## 5. Security Comparison: Central AI vs `multi-source-chatbot`

| Security Dimension | Central AI Platform | Our System (`multi-source-chatbot`) |
| :--- | :--- | :--- |
| **API Authentication** | Dual-key architecture (`ca_...` public, `cax_...` secret server) `[OBSERVED]`. | Laravel Sanctum stateful tokens + Bearer tokens `[OBSERVED]`. |
| **Granular Permissions** | Fixed 3-tier role hierarchy (Admin, Operator, Viewer) `[DOCUMENTED]`. | **Spatie Granular RBAC**: Atomic permissions (`faq.view`, `knowledge_document.edit`, etc.) `[OBSERVED]`. |
| **Domain Whitelisting** | Mandatory domain origin guard on public chat widget `[OBSERVED]`. | Configurable CORS middleware in `cors.php` `[OBSERVED]`. |
| **Multi-Tenancy** | Single-tenant SaaS dashboard scoped by company entity `[OBSERVED]`. | Superadmin workspace switching with tenant database context `[OBSERVED]`. |

---

## 6. Verification Status & Evidence
- **Observed**: Screen `/chat/widget/` (Origin whitelisting) and `/security/` inspected in live session `[OBSERVED]`.
- **Documented**: Role definitions and key hashing validated in platform security documentation `[DOCUMENTED]`.
""")

print("Part 2 complete.")
