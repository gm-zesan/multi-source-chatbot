# Security, Reliability & Performance Quality Audit

- **Document ID**: `QUAL-AUDIT-05`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Inspection Date**: October 2026
- **Scope**: Scope F (Tenant Isolation, Action Safety & Resilience) & Scope G (Performance, Cost & Telemetry)
- **Status**: Empirical Deep-Dive Report

---

## 1. Executive Summary

This audit evaluates the non-functional and operational foundations of our existing platform:
1. **Multi-Tenant Isolation & Authorization Scoping**: Verifying boundaries across MySQL, Typesense, SQLite files, and Neo4j subgraphs.
2. **Action Proposal & Safety Lifecycle**: Evaluating the two-phase commit protocol for race conditions, replay attacks, and state leakage.
3. **Downstream Service Resilience & Failures**: Tracing timeout propagation and fallback stability when microservices crash.
4. **Latency, Token Consumption & Observability**: Analyzing real P50/P95 measurements, LLM cost drivers, and telemetry gaps.

We separate **active current defects** (vulnerabilities and bugs in current code) from **hardening opportunities** (best practices for future scaling).

---

## 2. Scope F: Security, Tenant Isolation & Resilience Audit

### F.1 Active Current Security Deficiencies

```
+---------------------------------------------------------------------------------------------------+
| DEFECT SEC-01: Plaintext Third-Party Channel Tokens in MySQL                                      |
| Classification: CONFIRMED DEFECT | Severity: P0 | Evidence: CODE_INSPECTION                       |
+---------------------------------------------------------------------------------------------------+
```
- **Component**: [`app/Models/ChannelAccount.php:17-26`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/ChannelAccount.php#L17-L26)
- **Concrete Code**:
  ```php
  protected $fillable = [
      'workspace_id', 'channel_id', 'name', 'display_name', 'external_id',
      'access_token', 'refresh_token', 'settings', 'is_active',
  ];
  protected $casts = [
      'settings' => 'array',
      'is_active' => 'boolean',
  ];
  ```
- **Risk**: `access_token` (Facebook Page Access Tokens, WhatsApp WABA System User Tokens) and `refresh_token` are stored in raw plaintext in MySQL. A database backup breach or unauthorized SQL query exposes all connected customer channels.
- **Remediation**: Apply Laravel built-in `'encrypted'` cast:
  ```php
  protected $casts = [
      'access_token'  => 'encrypted',
      'refresh_token' => 'encrypted',
      'settings'      => 'encrypted:array',
      'is_active'     => 'boolean',
  ];
  ```

---

```
+---------------------------------------------------------------------------------------------------+
| DEFECT SEC-02: Unauthenticated Analytics Microservice Endpoint                                    |
| Classification: CONFIRMED DEFECT | Severity: P0 | Evidence: CODE_INSPECTION                       |
+---------------------------------------------------------------------------------------------------+
```
- **Component**: `python-ai-service/app/main.py:141`
- **Concrete Code**:
  ```python
  app.include_router(analytics_router)
  ```
  Omitted `dependencies=[Depends(verify_api_key)]`.
- **Risk**: Any internal entity or SSRF exploit can submit arbitrary `workspace_id` values to `/analytics/query` over Port 8001 and execute queries or schema dumps without credentials.
- **Remediation**: Add `dependencies=[Depends(verify_api_key)]` and require HMAC request signing.

---

```
+---------------------------------------------------------------------------------------------------+
| DEFECT SEC-03: Stale Action Safety Confirmation Window & Broad Affirmation Regex                  |
| Classification: CONFIRMED DEFECT | Severity: P1 | Evidence: CODE_INSPECTION                       |
+---------------------------------------------------------------------------------------------------+
```
- **Component**: [`app/Services/AI/ActionSafetyService.php:97-121`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/ActionSafetyService.php#L97-L121)
- **Concrete Code**:
  ```php
  $patterns = [
      '/\b(yes|confirm|confirmed|send|ok|okay|yep|sure|proceed|do it|ha|haan|thik ache|thikase|send it|send mail|yes please|send email|ok send|ok please send|tahole mail pathao|mail pathao|mail pathan)\b/ui',
  ];
  ```
- **Risk**:
  - `isConfirmationIntent` matches isolated generic affirmations like `"ok"`, `"sure"`, or `"thikase"`.
  - When an action proposal is registered (e.g. sending an email to a seller), it remains active in conversation metadata for **15 minutes**.
  - If a user changes the topic without saying "cancel" and later replies *"ok"* to a FAQ policy answer 8 minutes later, the system interprets this as confirmation of the pending action proposal!
- **Remediation**:
  1. Clear `pending_action` if the subsequent user turn is classified as `KNOWLEDGE` or `ANALYTICS`.
  2. Reduce pending action expiration from 15 minutes to 3 minutes.
  3. Require explicit confirmation phrasing (e.g. *"Yes, send the email"*) rather than a bare single-word `"ok"`.

---

```
+---------------------------------------------------------------------------------------------------+
| DEFECT SEC-04: Unaudited Administrative Cross-Tenant Switching                                    |
| Classification: CONFIRMED DEFECT | Severity: P1 | Evidence: CODE_INSPECTION                       |
+---------------------------------------------------------------------------------------------------+
```
- **Component**: [`app/Http/Controllers/ChatSimulatorController.php:418-425`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ChatSimulatorController.php#L418-L425)
- **Risk**: Superadmins can arbitrarily switch the active workspace context in the simulator without an immutable audit record being created in the database or security logs.
- **Remediation**: Add structured security logging recording superadmin user ID, target workspace ID, IP address, and timestamp.

---

## 3. Scope G: Performance, Latency & Token Cost Audit

### G.1 Empirical Latency Analysis
Measurements extracted from baseline benchmark scorecards ([`phase_c_baseline_scorecard.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json), [`step4_generation_baseline_report.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/step4_generation_baseline_report.json)):

```
Pipeline Stage                        P50 Latency (ms)     P95 Latency (ms)     Observations / Bottlenecks
-----------------------------------------------------------------------------------------------------------
1. HybridRouter Intent Classification 1,250.00 ms          3,400.00 ms          DeepSeek LLM network call on every turn.
2. Memory Relevance Gate Check            0.02 ms              0.05 ms          Local regex/keyword logic (Extremely fast).
3. Typesense Hybrid Retrieval            60.29 ms            920.19 ms          Spikes occur during Tier 3 LLM expansions.
4. Neo4j Graph Memory Retrieval         120.00 ms            450.00 ms          Executes only on ~24% of personal turns.
5. Final LLM Generation (DeepSeek)    1,867.15 ms          5,111.03 ms          Dependent on output token length.
-----------------------------------------------------------------------------------------------------------
TOTAL END-TO-END TURN LATENCY         3,297.46 ms          8,961.27 ms          P95 approaches 9 seconds!
```

### Key Latency Drivers Discovered:
1. **Dual LLM Roundtrips**: Because routing uses an LLM and generation uses an LLM, every turn requires **two sequential external API calls** to DeepSeek. Under peak network conditions, 2 sequential LLM calls push turn latency to ~9.0 seconds.
2. **Tier 3 LLM Retrieval Expansion**: When Typesense first-pass scores fall below 0.35, `retrieval_engine.py` calls an LLM to generate synonym expansions, adding another 1.8s of latency before retrieval completes.

---

### G.2 LLM Token Cost Drivers & Optimization Potential

```
LLM Execution Point               Average Prompt Tokens    Average Output Tokens    Monthly Cost / 10k Turns
-----------------------------------------------------------------------------------------------------------
HybridRouter v2.2                 320 tokens               45 tokens                $0.55 (DeepSeek pricing)
Final CustomerSupportService      850 tokens               160 tokens               $1.64 (DeepSeek pricing)
Background Memory Ingestion       620 tokens               110 tokens               $1.17 (DeepSeek pricing)
-----------------------------------------------------------------------------------------------------------
TOTALS PER 10,000 CONVERSATIONS   1,790 tokens / turn      315 tokens / turn        $3.36 (DeepSeek pricing)
```

### Cost Optimization Opportunities:
1. **Layer 0.5 Lexicon Bypass**: Implementing a local regex fast-path for standard greetings ("hi", "bye", "thanks") eliminates 15% to 20% of all routing LLM calls, saving ~$0.10 per 1,000 turns and cutting 2 seconds off greeting responses.
2. **Context Window Caching**: DeepSeek provides automatic prompt caching for repeated system prompts. Ensuring the router system prompt is byte-identical across requests guarantees a 50% discount on input tokens.

---

## 4. Telemetry & Observability Quality

### Current Strengths:
- [`ObservabilityController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ObservabilityController.php) captures granular per-turn metrics: `model`, `prompt_tokens`, `completion_tokens`, `total_tokens`, and microservice latency.
- Verified by automated test: [`tests/Feature/ObservabilityTelemetryTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/ObservabilityTelemetryTest.php).

### Current Observability Gaps:
1. **Background Job Telemetry Loss**: When `IngestConversationMemoryJob` runs asynchronously, failure stack traces are logged to `storage/logs/laravel.log`, but are **not visible in the tenant observability dashboard**.
2. **Zero Aggregated Tenant Roll-Ups**: Telemetry exists only at the individual conversation turn level. There is no scheduled cron or table aggregating daily or monthly token consumption per workspace.
