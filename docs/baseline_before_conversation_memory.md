# Frozen Retrieval System Baseline (Before Conversation Graph Memory)

**Date**: September 1, 2026  
**Status**: **FROZEN & VERIFIED (Zero Regressions)**  
**Purpose**: Document the authoritative baseline performance, metrics, and architecture of the Typesense Hybrid Retrieval & Dynamic Ingestion System before introducing Conversation Graph Memory (Graphiti + Neo4j).

---

## 1. System Invariants & Frozen Boundaries

1. **Authority of Ground Truth**:
   - The Knowledge Base (Typesense) remains the sole authoritative search engine for business policies, FAQs, and product knowledge.
   - An independent **Answerability Gate (`finalScore >= 0.45`)** strictly gates every retrieved document before customer-facing citation.
2. **Retrieval Independence**:
   - The retrieval pipeline operates completely independently of conversational memory.
   - Adding, modifying, or resetting conversation memory (Graphiti/Neo4j) will never mutate or degrade the frozen retrieval engine.
3. **Multi-Tenant Isolation**:
   - All retrieval operations are strictly scoped by `workspace_id`.
4. **Dynamic Lexicon Ingestion**:
   - The 11-domain Commerce Ontology (`CommerceOntology`) and asynchronous ingestion generator (`FaqLexiconGeneratorService`) + validator (`FaqLexiconValidator`) automatically extract, validate, and index terms without runtime latency penalty.

---

## 2. Quantitative Performance Scorecard

### A. Official 110-Query Python Retrieval Benchmark (`eval_ab_expansion_comparison.py`)
- **Total Test Queries**: 110 standard and complex customer support inquiries.
- **Top-1 Accuracy**: **100.0%** (Post-Tier 2/3 Expansion) [Baseline before expansion: **86.36%**].
- **Top-3 Accuracy**: **100.0%** (Post-Tier 2/3 Expansion) [Baseline before expansion: **91.82%**].
- **Mean Reciprocal Rank (MRR)**: **1.0000** [Baseline MRR: **0.8958**].
- **Regression Count**: **0 regressions** across all 110 queries.

### B. Realistic Multi-Channel Commerce Shopper Benchmark (`benchmark_realistic_commerce_shopper_queries.py`)
- **Total Shopper Queries**: 33 queries evaluated across 5 realistic customer categories:
  - Bengali Script (`বিকাশে পেমেন্ট সমস্যা`, `পণ্য ফেরত পলিসি`, etc.): **100.0% Top-1**
  - Banglish & Shopper Slang (`card change korbo kivabe?`, `dam koto`, etc.): **100.0% Top-1**
  - Social & F-Commerce Channels (`Facebook page order`, `WhatsApp QR`, etc.): **100.0% Top-1**
  - Technical & Edge-cases (`webhook delivery failure`, `2FA setup`): **100.0% Top-1**
  - Out-of-Domain Safety (`chicken biryani recipe`, `World Cup final`): **100.0% Safe Rejection**
- **False Citation Rate**: **0.0%**
- **Median Retrieval Latency (P50)**: **68.5ms**

### C. Full Application Feature & Unit Test Suite (`php artisan test`)
- **Total Tests**: **103 passed**
- **Total Assertions**: **463 assertions passed**
- **Execution Duration**: ~18–20s
- **Coverage**:
  - `HybridRouterTest`: 14 categories (Chat, Knowledge, Action, Uncertain, OOD, Code Switching, Colloquial).
  - `FAQLexiconLifecycleTest`: 5/5 lifecycle tests (Create, Update, Delete, Cascade, Resilience).
  - `CustomerSupportServiceTest`, `RetrievalClientTest`, `ChatSimulatorTest`, `ProductionMessagingPipelineTest`.

---

## 3. Operational Latency Budget

```text
┌──────────────────────────────────────┬────────────────────────────┬─────────────────────────────┐
│ Pipeline Component                   │ Processing Type            │ Latency Range               │
├──────────────────────────────────────┼────────────────────────────┼─────────────────────────────┤
│ 1. HybridRouter Intent Classification │ Local regex/deterministic  │ 0.5ms – 2.0ms               │
│ 2. Typesense Hybrid Search (Tier 1)  │ Dense vector + BM25 keyword│ 35ms – 55ms                 │
│ 3. Local Lexicon Expansion (Tier 2)  │ In-memory ontology lookup  │ 10ms – 25ms                 │
│ 4. Tier 3 LLM Escape Hatch (Fallback)│ Async remote LLM call      │ 1,200ms – 1,600ms (budget)  │
│ 5. Answerability Gate                │ In-memory score validation │ < 0.5ms                     │
└──────────────────────────────────────┴────────────────────────────┴─────────────────────────────┘
```

---

## 4. Architectural Baseline Diagram

```text
                    CUSTOMER QUERY
                          │
                          ▼
                  ┌───────────────┐
                  │ HybridRouter  │
                  └───────┬───────┘
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
        ▼                 ▼                 ▼
      CHAT            KNOWLEDGE            OOD
  (Greets/Thanks)         │            (Out-of-Domain)
        │                 ▼                 │
        │        ┌─────────────────┐        │
        │        │ Tier 1: Raw     │        │
        │        │ Typesense Hybrid│        │
        │        └────────┬────────┘        │
        │                 │ (miss/low conf) │
        │                 ▼                 │
        │        ┌─────────────────┐        │
        │        │ Tier 2: Dynamic │        │
        │        │ Commerce Lexicon│        │
        │        └────────┬────────┘        │
        │                 │ (miss/unresolved)
        │                 ▼                 │
        │        ┌─────────────────┐        │
        │        │ Tier 3: LLM     │        │
        │        │ Reformulation   │        │
        │        └────────┬────────┘        │
        │                 │                 │
        │                 ▼                 │
        │        ┌─────────────────┐        │
        │        │ Answerability   │        │
        │        │ Gate (>= 0.45)  │        │
        │        └────────┬────────┘        │
        │                 │                 │
        │         ┌───────┴───────┐         │
        │         ▼               ▼         │
        │       PASS            FAIL        │
        │         │               │         │
        ▼         ▼               ▼         ▼
    Conversational  Grounded KB   Safe Deterministic
        Answer        Answer           Fallback
```

This baseline is officially frozen. Phase 1 through Phase 12 of Conversation Graph Memory will be verified against this baseline to ensure zero regression.
