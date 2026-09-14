# LangChain & LangGraph — Phase L1/L2 Research Summary (Executive Brief)

### Objective
To empirically evaluate **LangChain and LangGraph** as alternatives and complements to the existing deterministic AI routing architecture. The existing **Native Router v2.2** was kept frozen as the control baseline.

---

### Implementation Architecture

A dedicated Python service (`python-langchain-service` on port `8003`) was constructed:

* **LangChain**:
  - LLM integration (`ChatOpenAI` / Deepseek provider)
  - Structured schema definition
  - Tool binding (`bind_tools`) and execution interfaces
* **LangGraph**:
  - Stateful graph workflow orchestration
  - State transitions, condition branching, and agent loop execution
  - Security gate interception and loop depth limits

---

### Phase L1: Single-Hop Semantic Routing Experiment

L1 tested LangGraph strictly as a workflow routing classifier.

```text
User Query ──> LangChain LLM ──> LangGraph Node ──> Security Gate ──> Route / Clarification
```

| Metric | Native v2.2 (Control) | LangGraph L1 |
| :--- | :---: | :---: |
| **Route Accuracy** | **94.74%** | 84.21% |
| **Security Accuracy** | **97.37%** | 94.74% |
| **Analytics Recall** | **100%** | 100% |
| **Mutation Recall** | **100%** | 100% |
| **Average Latency** | ~1,000 ms | **~981 ms** |

**Finding:** LangGraph successfully implemented structured state transitions. However, the LLM classifier alone struggled with deterministic ambiguity rules (e.g. general business nouns) compared to Native v2.2.

---

### Phase L1.1: Semantic Policy Experiment

Injected domain-independent semantic prompt rules (e.g., `Ambiguous business noun -> UNCERTAIN`, `Mutation request -> ACTION`) to test if prompt engineering could close the gap.

* **Route Accuracy**: **73.68%** (-10.53 pp vs L1)
* **Analytics Recall**: **70.00%** (over-categorization of valid queries)

**Finding:** Adding rigid natural-language rules caused the LLM to over-apply ambiguity boundaries, empirically reducing routing accuracy.

---

### Phase L2: Stateful Agentic Tool Orchestration

L2 implemented genuine multi-step agent reasoning with read-only tools:
`analytics_query`, `knowledge_search`, `memory_search`.

```text
User ──> LangGraph Agent ──> Deterministic Security Gate ──> Tool Execution ──> Observation ──> Agent Final Response
```

**Security & Isolation Controls:**
- Tool allowlisting & read-only enforcement
- Deterministic runtime injection of `workspace_id` and `customer_id` (context spoofing protected)
- Argument sanitization and recursion depth limits (`MAX_LOOP_DEPTH = 5`)
- Strict error transparency (no fabricated fallback simulations)

#### L2 Results vs Native Control

| Metric | Native v2.2 (Control) | LangGraph L2 (Agentic Loop) |
| :--- | :---: | :---: |
| **Route Accuracy** | **94.74%** | 52.63% |
| **Security Accuracy** | **97.37%** | 86.84% |
| **Mutation Detection Recall** | **100%** | 50.00% |
| **Analytics Recall** | **100%** | 50.00% |
| **OOD Accuracy** | **100%** | 60.00% |
| **Average Latency** | **~1.00 s** | ~2.24 s |
| **P95 Latency** | **~1.26 s** | ~4.47 s |
| **Average Tool Calls / Query** | — | 0.45 (17 total) |
| **Average Agent Loops / Query** | 1.0 | 1.39 |

---

### Key Research Findings & Scientific Conclusion

1. **Routing vs Orchestration Tradeoff**:
   Giving the model tool autonomy causes it to focus on conversational problem-solving and information retrieval, which significantly degrades strict categorical classification accuracy.
2. **Latency Tax**:
   Multi-step tool reasoning more than doubles response latency (~2.24s avg, ~4.47s P95).
3. **Core Conclusion**:
   *Under the frozen benchmark and tested provider configuration, **Native Router v2.2 demonstrated substantially higher routing and safety performance than the evaluated LangGraph L2 configuration, with lower observed latency.***

---

### Final Architecture: Two-Layer Design

```text
                         USER QUERY
                             │
                             ▼
                    Native Router v2.2
               (CONTROL LAYER / FIRST-HOP)
                 Fast · Deterministic · 95% Acc
                             │
            ┌────────────────┼────────────────┐
            ▼                ▼                ▼
        KNOWLEDGE        ANALYTICS           CHAT
            │                │                │
            └────────────────┴────────────────┘
                             │
                   Complex Multi-Step Task?
                             │
                             ▼
                   LangGraph Agent Node
              (EXECUTION LAYER / DOWNSTREAM)
            Stateful Orchestration · Read-Only Tools
```

- **Native Router v2.2**: Remains the primary entry control layer for intent classification, ambiguity resolution, and security guardrails.
- **LangGraph**: Positioned as a downstream execution worker for complex multi-turn workflows where tool execution is genuinely required.
