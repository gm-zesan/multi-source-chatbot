# Security, Tenant Isolation & Architectural Vulnerability Audit

- **Document ID**: `OUR-SEC-03`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Audit Date**: October 2026
- **Scope**: Multi-Tenant Data Isolation, Credential Lifecycle, Webhook Security, and Architectural Boundaries

---

## 1. Multi-Tenant Isolation Architecture: Current Posture

Our platform implements a **shared-database, column-scoped multi-tenant architecture**. Every core operational entity in MySQL carries a foreign key to the `workspaces` table (`workspace_id`):

```
+-------------------------------------------------------------------------------------------------+
|                                    Tenant Boundary: Workspace                                   |
|                                         (id, name, slug)                                        |
+------------------------------------------------+------------------------------------------------+
                                                 |
         +---------------------------------------+---------------------------------------+
         |                                       |                                       |
         v                                       v                                       v
+------------------+                    +------------------+                    +------------------+
|  faqs (MySQL)    |                    | knowledge_docs   |                    | conversations    |
|  workspace_id    |                    | workspace_id     |                    | workspace_id     |
+--------+---------+                    +--------+---------+                    +--------+---------+
         |                                       |                                       |
         v                                       v                                       v
+------------------+                    +------------------+                    +------------------+
| Typesense Filter |                    | SQLite Virtual DB|                    | Neo4j Memory     |
| workspace_id:=1  |                    | /storage/.../1/  |                    | user_id, org_id  |
+------------------+                    +------------------+                    +------------------+
```

### Verified Multi-Tenant Strengths
1. **Automated Tenant Scoping in Eloquent**:
   - Verified by automated tests: `tests/Feature/Integration/TenantIsolationIntegrationTest.php`, `tests/Feature/KnowledgeDocumentTenantIsolationTest.php`, `tests/Feature/FAQTenantAndManagementTest.php`, and `tests/Feature/ConversationControllerTenantTest.php`.
   - Cross-workspace queries return 404 or empty sets when accessed by unauthorized users.
2. **Search Index Filtering (Typesense)**:
   - Queries sent via `RetrievalClient.php` enforce `filter_by: "workspace_id:={$workspaceId}"`. Documents from Workspace B cannot leak into Workspace A search results.
3. **Virtual Database File Isolation**:
   - `python-ai-service` stores uploaded spreadsheets and generated SQLite databases in workspace-isolated directory trees (`storage/app/analytics/{workspace_id}/{file_id}.db`).
4. **Action Safety Two-Phase Commit**:
   - `ActionSafetyService` prevents unauthorized action dispatch by fingerprinting proposals with SHA-256 and validating that target entities (e.g. salespeople) belong strictly to the authenticated `workspace_id`.

---

## 2. Identified Security, Isolation & Reliability Gaps

> [!NOTE]
> Detailed code tracing, non-destructive validation test steps, and verified mitigations for each finding are documented in [`06_CRITICAL_FINDINGS_VALIDATION.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/our-saas/06_CRITICAL_FINDINGS_VALIDATION.md).

Despite strong database-level isolation, our comparative evaluation against Central AI reveals the following security and isolation posture findings:

```
+---------------------------------------------------------------------------------------------------+
| SECURITY GAP 1: Absence of Tenant API Key Infrastructure & Origin Whitelisting                   |
| Status: PARTIALLY_CONFIRMED | Evidence: MISSING (API Keys) / CODE_PRESENT (Session Auth)           |
+---------------------------------------------------------------------------------------------------+
```
- **Vulnerability Description**: Central AI implements a dual-key model (`ca_...` public widget key vs `cax_...` secret server key) combined with a mandatory **Allowed Domains Whitelist** (`/accounts/security/api-keys/`). An empty domain list blocks all requests.
- **Our Current State**: Our application has **no tenant-facing API key model** in [`routes/api.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/routes/api.php). Web routes in [`routes/web.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/routes/web.php#L69-L95) rely on session cookies (`auth`).
- **Validation Assessment**: Confirmed as an architectural blocker for public widget deployment, but not an active unauthenticated vulnerability because chat routes require session authentication.
- **Remediation**:
  - Implement an `api_keys` table storing `public_key` (`pk_live_...`) and hashed `secret_key` (`sk_live_...`).
  - Create an `allowed_domains` table linked to workspaces.
  - Implement `ValidateOriginAndKey` middleware enforcing `Origin` header matching against whitelisted hostnames.

```
+---------------------------------------------------------------------------------------------------+
| SECURITY GAP 2: Static Shared Credentials & Unauthenticated Microservice Endpoint                 |
| Status: CONFIRMED | Evidence: CODE_PRESENT                                                        |
+---------------------------------------------------------------------------------------------------+
```
- **Vulnerability Description**: Communication between Laravel and the Python microservices relies on static header tokens configured in `.env`.
- **Our Current State**: In [`python-ai-service/app/main.py`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/python-ai-service/app/main.py#L141), `app.include_router(analytics_router)` is mounted **without** `dependencies=[Depends(verify_api_key)]`. Any caller reaching Port 8001 can query `/analytics/query` with arbitrary `workspace_id` parameters without an API key or signature.
- **Threat Vector**: Internal lateral movement or SSRF can read or query tenant SQLite databases without credentials.
- **Remediation**:
  - Add `dependencies=[Depends(verify_api_key)]` to the analytics router in `main.py`.
  - Implement HMAC-SHA256 request signing embedding `workspace_id` and an expiration timestamp.

```
+---------------------------------------------------------------------------------------------------+
| SECURITY GAP 3: Storage of Plaintext Third-Party Channel & Mailbox Credentials                    |
| Status: CONFIRMED | Evidence: CODE_PRESENT                                                        |
+---------------------------------------------------------------------------------------------------+
```
- **Vulnerability Description**: Storing Meta tokens (Facebook Page Access Tokens, WhatsApp WABA System User Tokens) and channel credentials in plaintext in MySQL creates exposure in case of backup compromise or SQL injection.
- **Our Current State**: In [`app/Models/ChannelAccount.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/ChannelAccount.php#L17-L26), `access_token` and `refresh_token` are not defined in `$casts` as `'encrypted'`. They are stored as raw plaintext in MySQL.
- **Remediation**:
  - Add `'access_token' => 'encrypted'`, `'refresh_token' => 'encrypted'`, and `'settings' => 'encrypted:array'` to `$casts` in [`app/Models/ChannelAccount.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/ChannelAccount.php#L23-L26).

```
+---------------------------------------------------------------------------------------------------+
| SECURITY GAP 4: Lack of Webhook HMAC Signature Verification for External Events                   |
| Status: CONFIRMED (Missing Capability) / NOT_APPLICABLE (Active Exploit) | Evidence: CODE_PRESENT |
+---------------------------------------------------------------------------------------------------+
```
- **Vulnerability Description**: Central AI secures its order webhooks with `Central-AI-Signature: t=..., v1=...` (HMAC SHA-256 computed using a per-tenant secret key) and enforces replay protection via timestamp tolerance.
- **Our Current State**: In [`app/Http/Controllers/WebhookController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/WebhookController.php#L20-L75), only Meta Messenger callbacks are handled (`verifyWebhook` verifies `hub.verify_token`). No order webhook route exists, so no active order spoofing vulnerability is present.
- **Remediation**:
  - When commercial checkout callbacks are added, require `X-Signature-256` HMAC validation and timestamp tolerance checks (< 300s).

```
+---------------------------------------------------------------------------------------------------+
| SECURITY GAP 5: Superadmin Cross-Tenant Switching Without Audit Logging                          |
| Status: CONFIRMED | Evidence: CODE_PRESENT                                                        |
+---------------------------------------------------------------------------------------------------+
```
- **Vulnerability Description**: In [`app/Http/Controllers/ChatSimulatorController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ChatSimulatorController.php#L418-L425), superadmins can arbitrarily switch the active workspace via `?workspace_id=...` or session state.
- **Our Current State**: The switch updates `session(['active_simulator_workspace_id' => $requestedWsId])` immediately without writing a log record or firing a security audit event.
- **Remediation**:
  - Implement structured audit logging (`Log::info` / `SecurityAuditLog`) recording superadmin user ID, target workspace ID, timestamp, and IP address.

```
+---------------------------------------------------------------------------------------------------+
| SECURITY GAP 6: Unbounded File Ingestion & Virtual Database Resource Exhaustion                  |
| Status: PARTIALLY_CONFIRMED | Evidence: CODE_PRESENT                                              |
+---------------------------------------------------------------------------------------------------+
```
- **Vulnerability Description**: Unbounded multi-sheet spreadsheets could exhaust memory or CPU during automated SQLite generation.
- **Our Current State**:
  - In Laravel ([`StoreKnowledgeDocumentRequest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Requests/StoreKnowledgeDocumentRequest.php#L85, #L180-L220)), upload size is capped at 20MB, and ZIP bomb checks enforce a 50MB decompressed limit, 1,000 file cap, and 100:1 ratio limit.
  - In [`python-ai-service/app/analytics/excel_engine.py`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/python-ai-service/app/analytics/excel_engine.py#L112-L138), the Python service processes all sheets and rows without a sheet count limit (`MAX_SHEETS`) or row count limit (`MAX_ROWS_PER_SHEET`), and without SQLite query timeouts.
- **Remediation**:
  - In `excel_engine.py`, enforce `MAX_SHEETS = 10`, `MAX_ROWS_PER_SHEET = 100_000`, and set SQLite query execution timeouts (15s).

---

## 3. Summary of Security Remediation Priorities

| Priority Tier | Finding ID | Vulnerability / Gap | Validation Status | Immediate Remediation Action | Complexity |
| :---: | :---: | :--- | :---: | :--- | :---: |
| **P0 (Critical)** | `OUR-SEC-02` | Unauthenticated Microservice Endpoint | `CONFIRMED` | Add `Depends(verify_api_key)` to `analytics_router` in `main.py`. | Low |
| **P0 (Critical)** | `OUR-SEC-03` | Unencrypted Channel Account Tokens | `CONFIRMED` | Add `'encrypted'` casting in `ChannelAccount.php`. | Low |
| **P1 (High)** | `OUR-SEC-01` | Missing Public Widget Key & Origin Guard | `PARTIALLY_CONFIRMED` | Create `api_keys` / `allowed_domains` before widget launch. | Medium |
| **P1 (High)** | `OUR-SEC-05` | Superadmin Workspace Impersonation Audit | `CONFIRMED` | Add immutable audit log for administrative workspace switching. | Low |
| **P2 (Medium)** | `OUR-SEC-06` | Ingestion Sheet/Row Bounds on Python Worker | `PARTIALLY_CONFIRMED` | Enforce sheet/row limits and SQLite query timeout in Python. | Low |
| **P2 (Medium)** | `OUR-SEC-04` | Order Webhook Signature Verification | `CONFIRMED` (Arch) | Enforce HMAC SHA-256 signature verification when order API is built. | Medium |

