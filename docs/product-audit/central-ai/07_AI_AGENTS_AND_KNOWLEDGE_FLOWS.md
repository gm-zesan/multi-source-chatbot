# Central AI: AI Agents & Knowledge Flows

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
        AGENT_SMART["3. Smart Lead Agent<br/>(Daily Batch Summarization)"]
        AGENT_CAPTURE["4. Auto Lead Capture<br/>(Background Extraction)"]
        AGENT_ENGAGE["5. AI Lead Engagement<br/>(Email Follow-Up Add-on)"]
        AGENT_SUPPORT["6. Support Ticket Agent<br/>(Issue Escalation)"]
    end

    subgraph SharedDataLayer ["Shared Platform Data Stores"]
        KB_STORE[("Knowledge Vector Index<br/>(8 Most Relevant Passages)")]
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
    VectorDB-->>Router: Return 8 most relevant passages
    Router->>PromptEng: Context Injection: System Prompt + Brand Persona + Chunks + Conversation History
    PromptEng->>LLM: Dispatch synthesized prompt with strict hallucination boundaries
    LLM-->>PromptEng: Generated answer: "Electronics carry a 1-year replacement warranty..."
    PromptEng->>Output: Attach source citation tags (Doc: Terms_2026.pdf, Page 4)
    Output-->>Customer: Render stream response in chat
```

### Retrieval Parameters
- **Chunk Size**: Approximately 400 characters `[DOCUMENTED]`.
- **Chunk Overlap**: ~80 characters `[DOCUMENTED]`.
- **Passages Retrieved**: 8 candidate passages (official specification) injected per prompt.
- **Similarity Threshold**: Internal vendor metric; exact cutoff unverified in public documentation.
- **Hallucination Prevention**: Strict system instruction: *"Only answer based on the provided context. If the information is not contained in the context, politely state that you do not have that information and suggest contacting support."* `[DOCUMENTED]`.

---

## 4. Architectural Comparison: Central AI vs `multi-source-chatbot`

| Architectural Layer | Central AI Platform | Our System (`multi-source-chatbot`) |
| :--- | :--- | :--- |
| **Agent Paradigm** | Specialized 6-Agent routing pipeline with sales and CRM handoffs `[OBSERVED]`. | Single conversational assistant with multi-source retrieval adapters `[OBSERVED]`. |
| **Tabular Data** | Flattened text chunks (`col: val`). Cannot perform dynamic math/SQL queries `[DOCUMENTED]`. | **SQLite per-file synthesis**: Dynamic SQL queries, aggregations, cross-tab joins `[OBSERVED]`. |
| **Vector Engine** | Cloud-hosted vector index (proprietary/managed) `[DOCUMENTED]`. | **Typesense Hybrid Search**: Dense vector + sparse BM25 text retrieval `[OBSERVED]`. |
| **Commerce Interconnect**| Native Offering entities, cart state, decoupled Live Checkout link, signed order webhooks, HMAC API `[DOCUMENTED]`. | Purely informational RAG (no transactional commerce engine) `[OBSERVED]`. |

---

## 5. Verification Status & Evidence
- **Documented**: Chunking parameters, similarity cutoffs, and agent roles verified in official platform specifications `[DOCUMENTED]`.
- **Observed**: Agent playground citation tags and settings verified at `/chat/chatbot/` `[OBSERVED]`.
