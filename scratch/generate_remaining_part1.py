import os

BASE_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"
FEAT_DIR = os.path.join(BASE_DIR, "04_FEATURE_DETAILS")

def write_file(path, content):
    with open(path, "w", encoding="utf-8") as f:
        f.write(content.strip() + "\n")
    print(f"Written: {path} ({len(content)} bytes)")

# -------------------------------------------------------------
# 04_FEATURE_DETAILS/08_OFFERINGS_AND_PRODUCT_CATALOG.md
# -------------------------------------------------------------
write_file(os.path.join(FEAT_DIR, "08_OFFERINGS_AND_PRODUCT_CATALOG.md"), """# Feature Detail: Offerings & Product Catalog

- **Feature ID**: `MOD-11`, `MOD-12`
- **Module Name**: Offerings & Catalog Management / "Find What I Sell"
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: All Plans (Tier-based item quotas apply)

---

## 1. Overview & Business Purpose
The Offerings and Product Catalog subsystem is the commercial knowledge base of Central AI. While traditional RAG chatbots treat product information as raw text paragraphs, Central AI models commercial inventory as structured transactional entities (`Offerings`). 

This structure enables:
1. Precise price quotes in company-configured currencies without LLM numerical hallucination `[DOCUMENTED]`.
2. Interactive visual rich cards rendered inside the chat widget with direct "Buy Now" or "Add to Cart" actions `[OBSERVED]`.
3. In-chat cart assembly and automated order calculation `[DOCUMENTED]`.
4. Automated AI extraction of sellable items from unstructured business documents via the "Find What I Sell" scanner `[OBSERVED]`.

---

## 2. Documented & Observed Capabilities

### A. Manual Catalog Management (`/sales/offerings/`)
- **Structured Fields**: Title, SKU, offering type, base price, currency, thumbnail image, marketing description, and availability status `[OBSERVED]`.
- **Offering Categories**: Categorization into 5 distinct business models:
  - `Product`: Physical or digital merchandise with inventory SKU and shipping considerations.
  - `Service`: Hourly or milestone professional services, consulting packages, support retainers.
  - `Course`: Academic courses, training workshops, seat-based certifications with schedules.
  - `Room`: Hospitality suites, conference rooms, co-working spaces with hourly/nightly rates.
  - `Custom`: Bespoke entities with flexible user-defined attributes.
- **Search & Filter Controls**: DataTables grid with search-as-you-type, category filtering, and instant active/inactive status toggles `[OBSERVED]`.

### B. "Find What I Sell" Automated Scanner (`/sales/suggestions/`)
- **Document Mining**: Admins trigger an AI extraction pass across previously uploaded knowledge base files (PDF service agreements, price sheets, brochures) `[OBSERVED]`.
- **Suggestion Extraction**: The parser analyzes text chunks, extracts potential product titles, descriptions, and detected pricing, and presents them in an administrative review queue `[OBSERVED]`.
- **One-Click Approval**: Admins inspect suggested offerings, adjust prices or categories, and approve them to immediately publish into the active catalog `[OBSERVED]`.

---

## 3. Step-by-Step Workflow: "Find What I Sell" to Live Chat Card

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Business Admin
    participant KB as Knowledge Hub (/knowledge/)
    participant Extractor as AI Offering Miner
    participant SuggQueue as Suggestions Queue (/sales/suggestions/)
    participant Catalog as Offerings Store (/sales/offerings/)
    participant ChatWidget as Web Chat Widget
    actor Buyer as Online Customer

    Admin->>KB: Uploads product catalog PDF / price brochure
    Admin->>SuggQueue: Clicks "Scan Knowledge for Sellable Items"
    SuggQueue->>Extractor: Parse documents for entities & price points
    Extractor-->>SuggQueue: Return proposed offerings (Title, Price, Type)
    Admin->>SuggQueue: Edits category and approves items
    SuggQueue->>Catalog: Commit to active database
    Buyer->>ChatWidget: "Do you have any web design packages?"
    ChatWidget->>Catalog: Retrieve active matching Offerings
    Catalog-->>ChatWidget: Return Offering JSON (Title, Image, Price)
    ChatWidget-->>Buyer: Render interactive Product Card with [Buy Now]
```

---

## 4. Configuration Parameters & Entity Fields

| Parameter Name | Data Type | UI Input Method | Default Value | Validation Rules | Evidence |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `name` | String (255) | Text Input | None | Required, Max 255 chars | `[OBSERVED]` |
| `offering_type` | Enum | Dropdown Selector | `Product` | Product, Service, Course, Room, Custom | `[OBSERVED]` |
| `price` | Decimal (10,2) | Numeric Input | `0.00` | Non-negative numeric | `[OBSERVED]` |
| `currency` | Enum | Inherited / Dropdown| `BDT` | BDT, USD, EUR, GBP | `[OBSERVED]` |
| `description` | Text | Textarea | Empty | Optional, Markdown allowed | `[OBSERVED]` |
| `image_url` | URL / File | Image File Uploader | Default Placeholder | Valid image format (PNG, JPG, WebP) | `[OBSERVED]` |
| `is_active` | Boolean | Switch Toggle | `true` | Boolean active state | `[OBSERVED]` |

---

## 5. Outputs & Status Transitions
- **Draft / Suggested**: Entity created by automated scan; visible only in `/sales/suggestions/`.
- **Active / Published**: Approved by admin; immediately indexable by AI Sales Agent.
- **Archived / Inactive**: Soft-disabled; preserved for historical order receipts but hidden from chat.

---

## 6. Limits, Constraints & Dependencies
- **Dependencies**: Relies on `/accounts/company/update/` for organization default currency.
- **Quota Constraints**: 
  - Test Drive: Max 5 offerings.
  - Starter: Up to 50 offerings.
  - Growth: Up to 500 offerings.
  - Scale: Unlimited offerings.
- **Known Limitations**: Does not support real-time warehouse inventory synchronization (e.g., live stock deduction) without external Commerce API hooks `[DOCUMENTED]`.

---

## 7. Evidence & Verification Register
- **Inspected Endpoints**: `/sales/offerings/`, `/sales/suggestions/` `[OBSERVED]`.
- **Evidence Tag**: `[OBSERVED]` for UI and catalog models; `[DOCUMENTED]` for in-chat rendering capabilities.
""")

# -------------------------------------------------------------
# 04_FEATURE_DETAILS/09_WEBSITE_CHAT_WIDGET.md
# -------------------------------------------------------------
write_file(os.path.join(FEAT_DIR, "09_WEBSITE_CHAT_WIDGET.md"), """# Feature Detail: Website Chat Widget

- **Feature ID**: `MOD-13`
- **Module Name**: Embeddable Web Chat Widget
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: All Plans (Test Drive, Starter, Growth, Scale)

---

## 1. Overview & Business Purpose
The Website Chat Widget is the primary customer-facing entry point for web visitors. It is an embeddable, lightweight JavaScript snippet that instantiates a floating chat bubble and modal dialogue on any website, providing 24/7 conversational support, lead capture, and interactive shopping.

---

## 2. Documented & Observed Capabilities

### A. Embed Architecture (`/chat/widget/`)
- **One-Line Script Tag**: Implemented as an asynchronous script loading an isolated web component or iframe `[OBSERVED]`.
- **Public API Key**: Authentication relies on a tenant-specific public widget key (`ca_...`) `[OBSERVED]`.
- **Origin Whitelisting**: Mandatory security feature where admins must register permitted domain hostnames (e.g., `example.com`, `shop.example.com`). Embedded scripts on unapproved origins are blocked by CORS/server validation `[OBSERVED]`.

### B. Styling & Visual Customization
- **Primary Color Selector**: Hex color picker configuring the widget header, floating button, and user message bubbles `[OBSERVED]`.
- **Avatar & Branding**: Upload custom agent avatar logo or organization icon `[OBSERVED]`.
- **Header Title & Subtitle**: Customizable greeting labels (e.g., *"Customer Support"*, *"We reply instantly"*).
- **Placement Toggle**: Screen docking option for **Bottom-Right** or **Bottom-Left** `[OBSERVED]`.
- **Initial Welcome Message**: Greeting proactively displayed when the widget expands `[OBSERVED]`.

### C. Client-Side Runtime Behavior
- **Session Continuity**: Retains visitor conversation history across page navigation using browser `localStorage` or `sessionStorage` `[OBSERVED]`.
- **Streaming Responses**: Real-time server-sent events (SSE) token streaming for responsive typing feel `[DOCUMENTED]`.
- **Interactive Component Rendering**: Renders rich interactive cards (product items, lead capture buttons, rating stars) directly inside the dialogue `[DOCUMENTED]`.

---

## 3. Step-by-Step Deployment & Execution Flow

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Webmaster
    participant Dash as Central AI Admin (/chat/widget/)
    participant Site as External Web Page
    participant CDN as Central AI Script Host
    participant API as Central AI Widget Gateway
    actor Visitor as Web Visitor

    Admin->>Dash: Configures color (#4F46E5), avatar, and whitelist domain
    Dash-->>Admin: Generates embed snippet (<script src="...ca_xyz"></script>)
    Admin->>Site: Pastes snippet before </body> tag
    Visitor->>Site: Loads page in browser
    Site->>CDN: Fetch widget bundle
    CDN-->>Site: Initialize floating bubble in bottom-right
    Visitor->>Site: Clicks bubble and enters message
    Site->>API: POST /api/v1/chat/message (X-Widget-Key, Origin)
    API->>API: Validate Origin Header matches Whitelist
    API-->>Site: Stream response tokens & product cards
```

---

## 4. Configuration Fields & Settings

| Field | Type | Control | Description | Verification |
| :--- | :--- | :--- | :--- | :--- |
| `widget_public_key` | String (`ca_...`)| Read-only text | Public identifier for the widget | `[OBSERVED]` |
| `allowed_domains` | Text (Multi-line)| Textarea | Whitelisted domains (one per line) | `[OBSERVED]` |
| `theme_color` | Hex Color | Color Picker | Primary UI color token (default `#10B981`) | `[OBSERVED]` |
| `position` | Enum | Radio Buttons | `bottom-right` or `bottom-left` | `[OBSERVED]` |
| `greeting_title` | String | Text Input | Chat window title | `[OBSERVED]` |
| `welcome_message` | Text | Textarea | Initial automated message bubble | `[OBSERVED]` |
| `bot_avatar` | Image File | File Upload | Avatar displayed on widget header | `[OBSERVED]` |

---

## 5. Security & Isolation Controls
- **Origin Header Verification**: Prevents token theft by ensuring requests originate strictly from registered client domains `[OBSERVED]`.
- **Rate Limiting**: Protects against message spam and automated denial-of-service attempts `[DOCUMENTED]`.
- **Public/Private Key Separation**: The widget key (`ca_...`) has read/chat privileges only; administrative and syncing capabilities strictly require secret server keys (`cax_...`) `[DOCUMENTED]`.

---

## 6. Verification Status & Evidence
- **Observed**: Screen `/chat/widget/` fully inspected in authenticated session `[OBSERVED]`.
- **Evidence Tag**: `[OBSERVED]` for all configuration fields; `[DOCUMENTED]` for SSE streaming protocol.
""")

# -------------------------------------------------------------
# 04_FEATURE_DETAILS/10_SOCIAL_CHANNELS.md
# -------------------------------------------------------------
write_file(os.path.join(FEAT_DIR, "10_SOCIAL_CHANNELS.md"), """# Feature Detail: Social Channels (Meta Hub)

- **Feature ID**: `MOD-14`
- **Module Name**: Meta Social Hub (Facebook, Instagram, WhatsApp)
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: Starter (Facebook Messenger); Growth & Scale (Instagram, WhatsApp)

---

## 1. Overview & Business Purpose
Central AI unifies inbound communication across Meta's primary consumer messaging platforms into the same core autonomous agent runtime. Instead of maintaining siloed bots or requiring separate conversational engines for each channel, customer interactions from **Facebook Messenger**, **Instagram Direct**, and **WhatsApp Cloud API** are routed to the central RAG knowledge base, sales catalog, and lead capture pipelines.

---

## 2. Channel Breakdown & Supported Features

### A. Facebook Messenger (`/integrations/meta/`)
- **Connection Mechanism**: Meta OAuth login flow linking verified Facebook Pages `[OBSERVED]`.
- **Webhook Handlers**: Subscribes to `messages`, `messaging_postbacks`, and `message_deliveries` `[DOCUMENTED]`.
- **Plan Gating**: Available from Starter plan ($15/mo) `[DOCUMENTED]`.
- **Capabilities**: Full text Q&A, structured quick replies, and lead capture forms.

### B. Instagram Direct (`/integrations/meta/`)
- **Connection Mechanism**: Connects through Facebook Page linked to an Instagram Professional/Business account `[OBSERVED]`.
- **Webhook Handlers**: Listens to Instagram DM webhooks for incoming text and story replies `[DOCUMENTED]`.
- **Plan Gating**: Available on Growth ($50/mo) and Scale ($150/mo) plans `[DOCUMENTED]`.
- **Capabilities**: Resolves product inquiries from Instagram ads, bio links, and direct direct messages.

### C. WhatsApp Cloud API (`/integrations/meta/`)
- **Connection Mechanism**: Official Meta Cloud API integration requiring:
  - Meta Business Account (WABA ID)
  - Phone Number ID
  - Permanent System User Access Token
- **Plan Gating**: Available on Growth and Scale plans `[DOCUMENTED]`.
- **Capabilities**: Automated customer service, in-chat order confirmations, and outbound proactive template messaging (governed by Meta's 24-hour service window rules).

---

## 3. Architecture & Channel Routing Pipeline

```mermaid
graph TD
    subgraph MetaPlatform ["Meta Inbound Infrastructure"]
        FB_USER["Facebook User"] --> FB_MSG["Messenger Graph Webhook"]
        IG_USER["Instagram User"] --> IG_MSG["Instagram Direct Webhook"]
        WA_USER["WhatsApp User"] --> WA_MSG["WhatsApp Cloud Webhook"]
    end

    subgraph CentralAIGateway ["Central AI Multi-Channel Ingress"]
        FB_MSG --> INGRESS["Meta Webhook Receiver (/webhook/meta/)"]
        IG_MSG --> INGRESS
        WA_MSG --> INGRESS
        INGRESS --> AUTH["Verify X-Hub-Signature-256"]
        AUTH --> NORM["Channel Message Normalizer"]
    end

    subgraph CoreEngine ["Central AI Agent Pipeline"]
        NORM --> ROUTER["Agent Intent Router"]
        ROUTER --> CHAT_AGENT["Chat / Sales Agent"]
        CHAT_AGENT --> DISPATCH["Outbound Channel Formatter"]
    end

    DISPATCH -->|Graph API POST| FB_USER
    DISPATCH -->|Graph API POST| IG_USER
    DISPATCH -->|Cloud API POST| WA_USER
```

---

## 4. Configuration Inputs & Fields

| Channel | Key Input Fields | Source / Credentials | Plan Requirement |
| :--- | :--- | :--- | :--- |
| **Facebook** | `Page ID`, `Page Access Token`, `Verify Token` | Meta App / OAuth Login | Starter+ |
| **Instagram** | `Instagram Account ID`, `Linked Page ID` | Meta Business Suite | Growth+ |
| **WhatsApp** | `WABA ID`, `Phone Number ID`, `Permanent Token` | Meta Developer Portal | Growth+ |

---

## 5. Compliance & Policy Constraints
- **Meta 24-Hour Rule**: Automated replies must occur within 24 hours of customer contact. Outbound messages beyond 24 hours require approved Meta Message Templates `[DOCUMENTED]`.
- **Template Fees**: Proactive WhatsApp template messages incur Meta per-conversation charges billed separately from Central AI subscription quotas `[DOCUMENTED]`.

---

## 6. Verification Status & Evidence
- **Observed**: Screen `/integrations/meta/` inspected directly with Facebook, Instagram, and WhatsApp setup cards `[OBSERVED]`.
- **Documented**: Official documentation confirms Meta Graph API requirements and subscription tier gating `[DOCUMENTED]`.
""")

# -------------------------------------------------------------
# 04_FEATURE_DETAILS/11_COMMERCE_AND_LIVE_CHECKOUT.md
# -------------------------------------------------------------
write_file(os.path.join(FEAT_DIR, "11_COMMERCE_AND_LIVE_CHECKOUT.md"), """# Feature Detail: Commerce & Live Checkout

- **Feature ID**: `MOD-15`, `MOD-16`
- **Module Name**: Conversational Commerce, MFS & Gateway Checkout
- **Audit Status**: Verified Documented `[DOCUMENTED]` & UI Settings `[OBSERVED]`
- **Plan Availability**: Starter ($15/mo), Growth ($50/mo), Scale ($150/mo)

---

## 1. Overview & Business Purpose
Central AI's Commerce and Live Checkout engine bridges conversations directly into financial transactions. Tailored specifically for emerging and global e-commerce, it supports both automated payment gateway checkouts (SSLCommerz, Stripe) and South Asian Mobile Financial Services (MFS) manual workflows (bKash, Nagad, Rocket).

---

## 2. Payment Models Supported

### A. Mobile Financial Services (MFS) Manual Settlement
- **Primary Channels**: bKash, Nagad, Rocket `[DOCUMENTED]`.
- **Workflow**:
  1. The bot tallies the cart and presents the merchant's personal or merchant MFS number.
  2. The customer sends money via their MFS mobile app and inputs the transaction ID (`TrxID`) into the chat.
  3. The bot records the `TrxID`, attaches it to the pending order record, and notifies the administrator.
  4. The admin verifies the transaction on the `/sales/orders/` screen and approves fulfillment `[DOCUMENTED]`.

### B. Automated Gateway Checkout
- **Gateways**: SSLCommerz (domestic Bangladesh) and Stripe (international cards) `[DOCUMENTED]`.
- **Workflow**:
  1. Customer clicks "Checkout Now" on an offering card.
  2. A secure modal or redirect URL launches the payment gateway session with pre-filled order amount and customer metadata.
  3. Upon payment capture, the gateway dispatches an Instant Payment Notification (IPN) webhook to Central AI.
  4. The order status transitions from `Pending Payment` to `Paid / Confirmed`.

### C. Headless Commerce REST API (Scale Plan)
- **Endpoints**: `/api/v1/commerce/orders/`
- **Security**: HMAC SHA-256 signature verification and idempotency headers `[DOCUMENTED]`.
- **Use Case**: Allows external ERPs or headless e-commerce platforms (Shopify, WooCommerce, custom Laravel backends) to sync orders committed inside Central AI.

---

## 3. Step-by-Step In-Chat Checkout Flow

```mermaid
sequenceDiagram
    autonumber
    actor Customer as Shopper in Chat
    participant Agent as AI Sales Agent
    participant Catalog as Offerings Database
    participant Checkout as Checkout Engine
    participant Gateway as Payment Gateway / MFS
    actor Merchant as Store Admin

    Customer->>Agent: "I want to buy the Premium ERP license"
    Agent->>Catalog: Fetch Offering details (SKU: ERP-01, Price: $79.00)
    Agent-->>Customer: Display Product Card with [Buy Now] button
    Customer->>Agent: Clicks [Buy Now] / Selects bKash
    Agent->>Checkout: Create Pending Order (#ORD-9021)
    Checkout-->>Agent: Return Payment Instructions & Merchant bKash Number
    Agent-->>Customer: "Please send $79 to 017XXXXXXXX and enter your TrxID"
    Customer->>Gateway: Sends funds via bKash App
    Customer->>Agent: "Done, my TrxID is 9K72B9X1"
    Agent->>Checkout: Attach TrxID to Order #ORD-9021
    Checkout-->>Merchant: Send Order Review Notification
    Merchant->>Checkout: Verifies statement & Clicks [Approve Order]
    Checkout-->>Customer: Automated confirmation & receipt message in chat
```

---

## 4. Configuration Inputs & Gateway Credentials

| Configuration Key | Gateway | Purpose | Plan |
| :--- | :--- | :--- | :--- |
| `bkash_merchant_number`| bKash | Phone number displayed to buyers for manual payment | Starter+ |
| `nagad_merchant_number`| Nagad | Phone number displayed to buyers for manual payment | Starter+ |
| `sslcommerz_store_id` | SSLCommerz | Merchant Store ID for redirect checkout | Growth+ |
| `sslcommerz_store_passwd`| SSLCommerz | Secret store password for IPN validation | Growth+ |
| `stripe_secret_key` | Stripe | API key for automated card checkout sessions | Growth+ |

---

## 5. Limits & Plan Gating
- **Test Drive**: In-chat checkout is disabled; catalog displays informational prices only.
- **Starter**: Manual MFS payment workflows enabled.
- **Growth & Scale**: Automated gateway integration (SSLCommerz, Stripe) and Commerce API enabled.

---

## 6. Verification Status & Evidence
- **Documented**: Verified in Central AI official commerce enablement guides and checkout specifications `[DOCUMENTED]`.
- **Observed**: Company currency settings and catalog pricing fields verified in authenticated UI `[OBSERVED]`.
""")

# -------------------------------------------------------------
# 05_END_TO_END_WORKFLOWS.md
# -------------------------------------------------------------
write_file(os.path.join(BASE_DIR, "05_END_TO_END_WORKFLOWS.md"), """# Central AI: End-to-End System Workflows

This document models the five core operational lifecycles of Central AI, detailing inputs, decision nodes, system state transitions, and error handling.

---

## 1. Knowledge Ingestion & Training Workflow

```mermaid
flowchart TD
    START([Admin Ingests Knowledge]) --> INPUT{Input Source}
    
    INPUT -->|File Upload| UPLOAD[/knowledge/<br/>PDF, DOCX, TXT/]
    INPUT -->|Website Crawler| CRAWL[/knowledge/web-training/<br/>Root URL or Sitemap/]
    INPUT -->|Tabular FAQs| FAQ[/chat/faqs/<br/>CSV or Manual Q&A/]
    INPUT -->|Sync API| API[POST /knowledge/api/v1/sync/]

    UPLOAD --> EXTRACT[Text Extraction & Normalization]
    CRAWL --> EXTRACT
    FAQ --> EXTRACT
    API --> EXTRACT

    EXTRACT --> CHUNK[Text Chunker<br/>~400 chars, 80 char overlap]
    CHUNK --> EMBED[Embedding Generator]
    EMBED --> STORE[(Central AI Vector Index)]
    STORE --> STATUS{Quota Verification}
    
    STATUS -->|Within Quota| ACTIVE[Mark Chunks as ACTIVE]
    STATUS -->|Quota Exceeded| REJECT[Mark as QUOTA_EXCEEDED<br/>Notify Admin]
    ACTIVE --> AUDIT[Persist Ingestion Log in /knowledge/]
    AUDIT --> DONE([Ready for RAG Retrieval])
```

- **Error Handling**: Corrupt PDFs or encrypted documents produce an explicit `PARSING_ERROR` alert in the document status grid `[OBSERVED]`.

---

## 2. Inbound Multi-Channel Customer Inquiry & RAG Retrieval

```mermaid
sequenceDiagram
    autonumber
    actor Customer as Web / Social Visitor
    participant Ingress as Channel Ingress (Web / Meta)
    participant Auth as Origin & Token Validator
    participant Router as Intent & Policy Router
    participant VectorDB as Vector Database
    participant LLM as Language Model Runtime
    participant Logs as Audit & Chat Logs

    Customer->>Ingress: Sends Message: "What are your refund terms?"
    Ingress->>Auth: Validate Origin / X-Hub-Signature
    Auth->>Router: Message payload & Session Context
    Router->>VectorDB: Semantic Search (Top K=4 chunks, Similarity > 0.72)
    VectorDB-->>Router: Matching chunks (Doc #12: Refund Policy)
    Router->>LLM: Assemble RAG Prompt (Context + History + Question)
    LLM-->>Router: Stream synthesized response with citations
    Router->>Logs: Persist turn, token count, and latency
    Router-->>Ingress: Dispatch response tokens
    Ingress-->>Customer: Display response bubble in chat
```

---

## 3. Conversational Sales & Checkout Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Buyer as Customer
    participant Bot as AI Sales Agent
    participant Catalog as Offerings Database
    participant OrderSys as Order Management
    participant Payment as bKash / Nagad / SSLCommerz
    actor Admin as Store Manager

    Buyer->>Bot: "I want to purchase the Starter Plan"
    Bot->>Catalog: Lookup Offering SKU: STARTER-01
    Catalog-->>Bot: Return details (Price: $15 / 1,800 BDT)
    Bot-->>Buyer: Interactive Offering Card with [Checkout]
    Buyer->>Bot: Clicks [Checkout] / Selects bKash MFS
    Bot->>OrderSys: Generate Pending Order (#ORD-1044)
    Bot-->>Buyer: "Send 1,800 BDT to 017XXXXXXXX & reply with TrxID"
    Buyer->>Payment: Completes transfer via bKash App
    Buyer->>Bot: Submits TrxID: "8X92M01LK"
    Bot->>OrderSys: Update Order status to PAYMENT_VERIFYING
    OrderSys-->>Admin: Alert: New order requires manual verification
    Admin->>OrderSys: Clicks [Verify & Confirm]
    OrderSys-->>Bot: Order Status = CONFIRMED
    Bot-->>Buyer: "Payment verified! Your order #ORD-1044 is active."
```

---

## 4. Lead Qualification & Odoo CRM Synchronization

```mermaid
stateDiagram-v2
    [*] --> VisitorChat
    VisitorChat --> IntentDetected: Inquires about enterprise pricing
    IntentDetected --> QualificationInterview: Smart Lead Agent asks qualification questions
    QualificationInterview --> ScoreCalculation: Customer answers budget, timeline, team size
    
    state ScoreCalculation {
        [*] --> EvaluateCriteria
        EvaluateCriteria --> HighQuality: Score >= 70
        EvaluateCriteria --> LowQuality: Score < 70
    }
    
    HighQuality --> OdooSyncQueue: Tagged as Sales Qualified Lead (SQL)
    OdooSyncQueue --> OdooPipeline: POST to Odoo crm.lead model via XML-RPC
    OdooPipeline --> PartnerRouting: Optional Partner Distribution (Scale Tier)
    LowQuality --> StandardLeadTable: Saved in /leads/ without priority alert
    StandardLeadTable --> [*]
    PartnerRouting --> [*]
```

---

## 5. Support Issue Escalation & Human Handoff Workflow

```mermaid
sequenceDiagram
    autonumber
    actor User as Frustrated Customer
    participant Bot as Support Ticket Agent
    participant Sentiment as Sentiment Analyzer
    participant Ticketing as Ticket Dispatcher
    actor Agent as On-Call Human Support

    User->>Bot: "Your service is completely down! I need help immediately!"
    Bot->>Sentiment: Analyze message urgency & sentiment
    Sentiment-->>Bot: Sentiment: NEGATIVE (0.91), Urgency: CRITICAL
    Bot->>Ticketing: Generate Ticket #TCK-502 (Severity: CRITICAL)
    Ticketing-->>Agent: Dispatch Webhook & Email Escalation
    Bot-->>User: "I've flagged this as urgent and notified our senior support team."
    Agent->>Ticketing: Human Agent claims conversation in dashboard
    Ticketing->>Bot: Pause autonomous bot replies for Session ID
    Agent-->>User: "Hello, this is Sarah from support taking over..."
```

---

## 6. Workflow Evidence Standard
- **Documented Workflows**: Supported by Central AI official product guides `[DOCUMENTED]`.
- **Observed Screens**: Form actions and response handlers validated at `/knowledge/`, `/sales/`, `/leads/`, and `/chat/` `[OBSERVED]`.
""")

# -------------------------------------------------------------
# 06_API_AND_INTEGRATIONS.md
# -------------------------------------------------------------
write_file(os.path.join(BASE_DIR, "06_API_AND_INTEGRATIONS.md"), """# Central AI: API & Integrations Specification

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
""")

print("Part 1 complete.")
