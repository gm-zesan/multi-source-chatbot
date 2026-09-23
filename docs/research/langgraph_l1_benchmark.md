                 ROUTER RESEARCH
                       │
        ┌──────────────┴──────────────┐
        │                             │
 Native Router v2.2             LangChain + LangGraph
 FROZEN CONTROL                 L1/L1.1 CHALLENGER
        │                             │
        │                       Classifier
        │                             ↓
        │                     Security Gate
        │                             ↓
        │                       Route/Clarify
        │                             │
        └──────────────┬──────────────┘
                       ↓
                  SAME BENCHMARK
                       ↓
        ┌──────────────┼──────────────┐
        ↓              ↓              ↓
     Accuracy       Security       Latency

# Phase L1: LangChain-powered LangGraph Router (L1) vs Native v2.2 (Control) Benchmark Results

We implemented a completely isolated `LangGraph` service (`python-langchain-service`) running on port `8003` to test LangGraph strictly as a workflow orchestrator (Routing L1 Phase). The Native v2.2 Router in PHP was frozen as the control baseline.

We successfully connected Laravel to the new Python service and ran the exact same 38-query benchmark suite against both routers using identical model configurations (`deepseek-flash`).

## Phase L1 (Base Prompt) Results

| Metric | Native v2.2 (Control) | LangChain-powered LangGraph Router (L1) | Difference |
| :--- | :--- | :--- | :--- |
| **Oracle-Audited Route Accuracy** | **94.74%** | **84.21%** | ❌ -10.53 pp |
| **Security Gate Accuracy** | 97.37% | 94.74% | ❌ -2.63 pp |
| **Mutation Detection Recall** | 100.00% | 100.00% | ➖ Equal |
| **Analytics Recall** | 100.00% | 100.00% | ➖ Equal |
| **OOD Accuracy** | 100.00% | Not measured | N/A |
| **UNCERTAIN Recall** | 100.00% | Not measured | N/A |
| **Clarification E2E** | Passed (7/7 tests) | Passed (7/7 tests) | ➖ Equal |
| **Average Latency*** | ~1,000.05 ms | **980.58 ms** | ✅ ~20ms faster |
| **P50 Latency** | 1,016.07 ms | Not measured | N/A |
| **P95 Latency** | 1,256.61 ms | Not measured | N/A |

*\*Latency Note: Historical Native v2.2 baseline observed at ~1450 ms. Current repeated benchmark observation across identical network conditions: ~1000.05 ms.*

> [!NOTE]
> **Why did LangGraph L1 lose ~10% accuracy?**
> A simple LangGraph-based LLM router reduced routing accuracy substantially while reducing observed latency. This empirically demonstrates the value of the Native v2.2's heuristic/deterministic layers.
> For example: 
> 1. `"Rahim er taka koto?"` (How much money does Rahim have?) - Native routes this to `UNCERTAIN` for ambiguity clarification (e.g. customer outstanding / payment / purchase), whereas LangGraph confidently routed it to `ANALYTICS`. **In this benchmark, an unconstrained LLM classifier did not reliably resolve the system's predefined semantic ambiguity cases.** This suggests that explicit ambiguity policies and deterministic routing controls can complement LLM-based classification.
> 2. `"What is my order status?"` - Native safely marks this `UNCERTAIN` under the current routing policy because the query does not provide sufficient information for the benchmark's expected downstream resolution. *(Note: The oracle label reflects the current system policy and benchmark contract, not necessarily the only semantically valid interpretation.)*

> [!TIP]
> **Why is LangGraph so much faster?**
> **The observed latency advantage is likely related to the simpler L1 execution path: a single schema-structured LLM call through the Python service, without the additional heuristic/fallback processing present in the Native HybridRouter. This should be treated as an observed benchmark result rather than a proven component-level causal explanation until per-stage latency is instrumented.**

## Phase L1.1 (Semantic Policy) Results

To test if prompt engineering could bridge the accuracy gap, we injected a domain-independent semantic routing policy into the LLM classifier (e.g. `Ambiguous business noun -> UNCERTAIN`, `Mutation request -> ACTION`). 

### L1.1 Experimental Scope Boundaries
**L1.1 MUST NOT:**
- copy PHP regex heuristics
- access Analytics DB
- access Typesense
- execute tools or mutations
- replace KGM or Laravel security authority
- use benchmark-specific examples/rules

**L1.1 MAY:**
- use domain-independent routing policy
- explicitly define ambiguity conditions
- require structured output
- distinguish capability vs ambiguity
- distinguish security detection vs security authority

| Metric | LangGraph (L1) | LangGraph (L1.1 with Semantic Policy) | Difference |
| :--- | :--- | :--- | :--- |
| **Route Accuracy** | **84.21%** | **73.68%** | ❌ -10.53 pp |
| **Security Gate Accuracy** | 94.74% | 92.11% | ❌ -2.63 pp |
| **Mutation Detection Recall** | 100.00% | 100.00% | ➖ Equal |
| **Analytics Recall** | 100.00% | 70.00% | ❌ -30.00 pp |
| **OOD Accuracy** | Not measured | 100.00% | N/A |
| **UNCERTAIN Recall** | Not measured | 90.91% | N/A |
| **Average Latency** | 980.58 ms | **892.19 ms** | ✅ Faster |
| **P50 Latency** | Not measured | 892.47 ms | N/A |
| **P95 Latency** | Not measured | 1,161.62 ms | N/A |

> [!WARNING]
> **Strict Policy Harmed Performance**
> Forcing rigid semantic rules on the LLM without giving it code-level heuristics resulted in over-classification. For example:
> 1. Because of the rule `"Ambiguous business noun ('sales') -> UNCERTAIN"`, valid analytical questions like *"Hasan er total sales koto?"* were incorrectly mapped to `UNCERTAIN`.
> 2. Because of the rule `"cancel -> ACTION"`, ambiguous terms were instantly flagged for mutation blocking, disrupting natural chat flow.

## Final Verdict for Routing Phases (L1 & L1.1)
**The LangGraph workflow provides explicit and auditable state transitions, ensuring that the deterministic security gate executes before downstream routing/execution.** (Graph execution = deterministic structure, LLM classification = probabilistic/variable).

LangGraph L1 achieved 100% mutation-detection recall on the frozen benchmark. However, its raw semantic routing accuracy is inherently inferior to the highly-optimized Native v2.2 router. L1.1 proved that applying hardcoded textual rules inside the prompt actually decreases accuracy due to LLM semantic over-application.

### Why Phase L2?
The results showed that `Routing` alone is not LangGraph's strongest suit compared to mature deterministic pipelines. In Phase L2, we evaluated the core research question:
> **"Does adding genuine agentic tool-use provide enough additional capability to justify its latency, complexity, and security cost?"**

---

# Phase L2: LangChain + LangGraph Agentic Tool Orchestration Results

In Phase L2, we transitioned from a single-hop classifier to a multi-step, stateful tool-execution loop (`agent_node -> security_gate_node -> tool_execution_node -> agent_node -> route_evaluator_node`).

### L2 Architectural & Security Controls
1. **Read-Only Scoped Tools:** `analytics_query`, `knowledge_search`, and `memory_search`. No mutation tools exist in the agent's tool registry.
2. **Deterministic Security Interceptor:** Sits strictly between the LLM tool proposal and execution. If an unauthorized tool or mutation is proposed, it is intercepted and neutralized.
3. **Runtime Injected Context:** `workspace_id` and `customer_id` are deterministically injected from Laravel, preventing LLM argument spoofing.
4. **Loop Depth Guard:** Capped at max 5 iterations to prevent runaway recursive execution.

---

## Phase L2 Benchmark Comparison (All 4 Iterations)

| Metric | Native v2.2 (Control) | LangGraph L1 (Base Prompt) | LangGraph L1.1 (Semantic Policy) | LangGraph L2 (Agentic Tool Loop) |
| :--- | :--- | :--- | :--- | :--- |
| **Oracle-Audited Route Accuracy** | **94.74%** | 84.21% | 73.68% | **52.63%** |
| **Security Gate Accuracy** | **97.37%** | 94.74% | 92.11% | **86.84%** |
| **Mutation Detection Recall** | **100.00%** | 100.00% | 100.00% | **50.00%** |
| **Analytics Recall** | **100.00%** | 100.00% | 70.00% | **50.00%** |
| **OOD Accuracy** | **100.00%** | Not measured | 100.00% | **60.00%** |
| **UNCERTAIN Recall** | **100.00%** | Not measured | 90.91% | **63.64%** |
| **Average Latency** | ~1,000.05 ms | **980.58 ms** | **892.19 ms** | **2,238.94 ms** |
| **P50 Latency** | 1,016.07 ms | Not measured | 892.47 ms | **1,952.91 ms** |
| **P95 Latency** | 1,256.61 ms | Not measured | 1,161.62 ms | **4,468.73 ms** |
| **Avg Tool Calls / Query** | 0.00 (N/A) | 0.00 (N/A) | 0.00 (N/A) | **0.45** (17 total) |
| **Avg Agent Loops / Query** | 1.00 | 1.00 | 1.00 | **1.39** |

---

## Research Insights & Scientific Findings from Phase L2

1. **Agentic Tool Loops Degrade Deterministic Route Accuracy:**
   When an LLM is placed in an open agentic role (`bind_tools`), its attention shifts from strict categorization to tool problem-solving. For instance:
   - Queries like *"Hasan er total sales koto?"* or *"compare sales between rahim and hasan"* caused the LLM to directly generate conversational answers or ask for more context without invoking the analytics tool, misclassifying intent as `CHAT`.
   - Security prompts like *"make him admin"* or *"remove my account"* were answered conversationally (`CHAT` / `KNOWLEDGE`) rather than triggering an explicit routing action block.

2. **Latency Tax of Stateful Agent Loops:**
   - Multi-step reasoning and tool invocation increased average latency to **2,238.94 ms** (more than 2.2x slower than Native v2.2 and L1).
   - The P95 latency rose to **4,468.73 ms** when multiple agent-tool iterations occurred.

3. **Deterministic Security Boundary Validation:**
   - The deterministic `security_gate_node` successfully verified tool parameters and injected `workspace_id` / `customer_id` without permitting LLM override.
   - However, when the LLM did not propose a tool and chose direct completion for mutation requests, the routing accuracy for mutation detection dropped because the classification relied on agent text generation.

### Conclusion on Router Research
Under the frozen benchmark and tested provider configuration, **Native Router v2.2 demonstrated substantially higher routing and safety performance than the evaluated LangGraph L2 agentic configuration, with lower observed latency. Therefore, Native v2.2 remains the preferred first-hop routing architecture for the current system.**

```text
                         USER
                           │
                           ▼
                  Native Router v2.2
                (Deterministic Control Layer)
                           │
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
      KNOWLEDGE        ANALYTICS           CHAT
          │                │                │
          │                │                │
          └────────────────┴────────────────┘
                           │
                    Complex Task / Multi-step?
                           │
                           ▼
                 LangGraph Agent Node
                (Downstream Execution Layer)
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
          Knowledge     Analytics     Memory
             Tool          Tool         Tool
```

**Architectural Principle:**
- **Native Router = Control & Entry Layer** (High-speed, high-accuracy semantic routing, security guardrails, deterministic ambiguity policy).
- **LangGraph = Capability & Execution Layer** (Downstream autonomous orchestration, stateful multi-step tool execution when explicit tool reasoning is required).


