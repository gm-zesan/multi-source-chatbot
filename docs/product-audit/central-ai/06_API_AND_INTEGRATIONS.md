# Central AI: API & Integrations Specification

This specification documents all public, server-to-server, and third-party integration interfaces identified in Central AI.

---

## 1. Authentication & Key Architecture

Central AI employs a strict two-tier credential architecture:

| Key Type | Prefix | Target Scope | Security Exposure | Origin Check |
| :--- | :--- | :--- | :--- | :--- |
| **Public Widget Key** | `ca_...` | Web chat widget client-side embedding | Public HTML script tags | Enforced via Whitelist |
| **Secret Server Key** | `cax_...`| Backend REST APIs, CRM sync, batch updates | Confidential backend code | Enforced via IP / Signature |

---

## 2. Knowledge Base Sync API (`/knowledge/api/docs/`)

- **Endpoint**: `POST https://centralai.daffodil-bd.com/knowledge/api/v1/sync/`
- **Authentication**: `X-Api-Key: cax_live_xxxxxxxxxxxxxxxxxxxx`
- **Content-Type**: `application/json`
- **Method**: Asynchronous (Non-blocking)

### Request Payload Schema
```json
{
  "sync_source": "external_erp",
  "documents": [
    {
      "external_id": "kb-doc-1092",
      "title": "Return & Exchange Policy 2026",
      "content": "Full text of policy...",
      "metadata": {
        "category": "legal",
        "last_updated": "2026-10-01T00:00:00Z"
      }
    }
  ],
  "reindex_immediately": true
}
```

### Response Schema (HTTP 202 Accepted)
```json
{
  "status": "accepted",
  "task_id": "tsk_89df2018a",
  "message": "Knowledge ingestion queued for processing",
  "estimated_processing_seconds": 12
}
```

---

## 3. Public Web Widget Chat API

- **Endpoint**: `POST https://centralai.daffodil-bd.com/api/v1/chat/message/`
- **Authentication**: `X-Widget-Key: ca_pub_xxxxxxxxxxxxxxxxxxxx`
- **Headers**:
  - `Origin: https://client-website.com` (Must match registered whitelist)
  - `Content-Type: application/json`

### Request Payload
```json
{
  "session_id": "sess_f0a8c291",
  "message": "Do you deliver to Chittagong?",
  "metadata": {
    "page_url": "https://client-website.com/products/view?id=4",
    "referrer": "https://google.com"
  }
}
```

### Streaming Response (Server-Sent Events)
```http
HTTP/1.1 200 OK
Content-Type: text/event-stream
Cache-Control: no-cache
Connection: keep-alive

data: {"token": "Yes"}
data: {"token": ", we"}
data: {"token": " deliver"}
data: {"token": " nationwide"}
data: {"token": " across Bangladesh."}
data: {"done": true, "citations": [{"doc_id": 14, "title": "Shipping Info"}]}
```

---

## 4. Odoo CRM XML-RPC Integration (`/leads/integrations/`)

Central AI communicates with Odoo ERP through Odoo's native XML-RPC or REST protocols `[OBSERVED]`:

### Integration Parameters
- `odoo_host`: URL of Odoo instance (e.g., `https://mycompany.odoo.com`).
- `odoo_db`: Target Odoo database name.
- `odoo_username`: API user email.
- `odoo_api_key`: Secret user API key or password.
- `target_model`: `crm.lead`

### Field Mapping Matrix

| Central AI Field | Odoo `crm.lead` Field | Description |
| :--- | :--- | :--- |
| `name` | `name` / `contact_name` | Name of prospective lead |
| `email` | `email_from` | Verified email address |
| `phone` | `phone` / `mobile` | Contact telephone number |
| `company` | `partner_name` | Prospect organization |
| `chat_summary` | `description` | AI-generated summary of chat needs |
| `qualification_score`| `priority` (1-3 stars) | Mapped from lead score (0-100) |
| `channel_source` | `medium_id` | Tagged as Web, Messenger, WhatsApp |

---

## 5. Commerce REST API (Scale Plan)

- **Endpoint**: `POST https://centralai.daffodil-bd.com/api/v1/commerce/orders/`
- **Security**: HMAC-SHA256 signature calculated over payload with secret merchant token.
- **Header**: `X-Signature: sha256=...`

```json
{
  "order_id": "ORD-9021",
  "customer": {
    "name": "Tanvir Ahmed",
    "phone": "+8801700000000",
    "email": "tanvir@example.com"
  },
  "items": [
    {"sku": "ERP-STARTER", "quantity": 1, "unit_price": 1800.00}
  ],
  "total_amount": 1800.00,
  "currency": "BDT",
  "payment_status": "PAID",
  "payment_method": "BKASH_MANUAL",
  "transaction_id": "8X92M01LK"
}
```

---

## 6. Integration Verification Register
- **Observed Interfaces**: Widget settings `/chat/widget/`, Odoo settings `/leads/integrations/`, and Knowledge API docs `/knowledge/api/docs/` `[OBSERVED]`.
- **Documented Protocols**: HMAC commerce signatures and SSE streaming verified in platform technical documentation `[DOCUMENTED]`.
