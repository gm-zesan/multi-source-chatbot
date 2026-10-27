# Central AI: Live Dashboard Screen Catalog

- **Inspection Date**: October 2026
- **Methodology**: Direct authenticated crawl of live platform screens [OBSERVED_LIVE].
- **Account State**: Active Test Drive Workspace (`zesan`, Company: `ABC`).

---

## 1. `/chat/dashboard/` — Main Platform Dashboard
- **Observed Title**: `Dashboard` [OBSERVED_LIVE]
- **Key Headings**: `Dashboard`
- **Data Grids & Tables**:
  - `Recent Leads Table`: Columns `Name/Email`, `Status`, `Source`, `Date`. Empty state: *"No recent leads found."*
  - `Recent Chats Table`: Columns `Contact`, `AI Score`, `Action`. Empty state: *"No recent chats found."*
- **Visual Analytics**: Interactive chart container (`height: 300px`) rendering conversational volume over time.

---

## 2. `/chat/chatbot/` — Interactive Agent Playground
- **Observed Title**: `Playground - Central AI` [OBSERVED_LIVE]
- **Purpose**: Real-time simulated chat dialogue for testing prompt responses, RAG retrieval citations, and offering cards before publishing live.
- **Observed Actions**: Reset session, inspect citation passages, view token count.

---

## 3. `/chat/chatbot/settings/` — Agents & Tools Configuration
- **Observed Title**: `Agents & Tools - Central AI` [OBSERVED_LIVE]
- **Key Headings**: `Agents & Tools`, `Chat Agent`, `Support Ticket Agent`, `Smart Lead Agent`
- **Live Form Controls (`POST /chat/chatbot/settings/`)**:
  - **Chat Agent**:
    - `prompt`: Multi-line textarea for system instructions and business persona.
  - **Support Ticket Agent**:
    - `support_ticket_enabled`: Checkbox toggle.
    - `support_ticket_auto_detect`: Checkbox toggle for automated frustration/issue detection.
    - `support_ticket_instructions`: Textarea guidance for ticket creation.
    - `support_ticket_mode`: Dropdown selector (2 modes).
    - `support_ticket_priority`: Dropdown selector (`low`, `medium`, `high`, `urgent`).
  - **Smart Lead Agent**:
    - `lead_agent_enabled`: Checkbox toggle.
    - `lead_agent_run_time`: Time input (default `03:00`) governing daily batch execution.
    - `lead_agent_allowed_categories`: Text input for comma-separated category taxonomy (up to 50 categories).
    - `lead_agent_timezone`: Dropdown selector with 433 global timezones (default `Asia/Dhaka`).

---

## 4. `/sales/` — AI Sales Agent Master Setup
- **Observed Title**: `AI Sales Agent | Central AI` [OBSERVED_LIVE]
- **Key Headings**: `Company type`, `What you offer`, `How customers complete`, `Connection to your system Advanced`, `Where the agent sells`, `Rules and payment`, `Publish`, `Usage this month`
- **Live Form Controls (`POST /sales/`)**:
  - **Step 1: Company Type**: Dropdown with 9 business classifications.
  - **Step 2: Offering Kinds**: Checkboxes and custom labels for 7 kinds:
    - `product` (`label_product`), `service` (`label_service`), `course_intake` (`label_course_intake`), `appointment` (`label_appointment`), `event_date` (`label_event_date`), `plan` (`label_plan`), `custom` (`label_custom`).
  - **Step 3: Completion Modes**: Radio selectors for each kind:
    - *Handled in Central AI* (in-chat order)
    - *Live checkout on your site* (`checkout_link_template` input: `{items}`, `{ref}`)
    - *Created in our system* (Commerce API connection: `profile_url`, `api_key`)
  - **Step 4: Rules & Payment**:
    - Checkboxes for payment methods: `cash_on_delivery`, `bkash_manual`, `nagad_manual`, `bank_transfer`, `payment_link`.
    - Multi-line text notes for each payment method (`payment_note_*`).
    - Custom policies: `tone`, `languages`, `currency`, `never_promise`, `handoff_triggers`, policy URLs (`refund_policy_url`, `cancellation_policy_url`, `delivery_policy_url`).

---

## 5. `/sales/offerings/new/` — Offering Editor
- **Observed Title**: `New offering | AI Sales Agent` [OBSERVED_LIVE]
- **Key Headings**: `What it is`, `Kind, price and availability`, `What the customer chooses`, `Options`, `Quantity per order`, `Intakes and seats`, `Slot rules`, `Party size`, `Opening hours`, `What to collect`
- **Live Form Fields (`POST /sales/offerings/new/`)**:
  - `name`: Text (Title)
  - `short_description`, `description`, `tags`: Text / Textarea
  - `sku`: Stock Keeping Unit
  - `external_ref`: Identifier on external merchant system
  - `image_url`: Thumbnail image URL
  - `external_url`: Direct page link
  - `price`: Numeric base price
  - `currency`: Currency code (BDT, USD, EUR, GBP)
  - `offering_kind`: Dropdown with 7 kinds
  - `availability_mode`: Dropdown with 5 modes
  - `fulfilment_kind`: Dropdown with 5 fulfillment options
  - `stock_quantity`, `track_inventory`: Inventory controls
  - `quote_required`: Checkbox
  - `is_active`: Checkbox active toggle

---

## 6. `/sales/suggestions/` — Needs Your Review ("Find What I Sell")
- **Observed Title**: `Needs your review | AI Sales Agent` [OBSERVED_LIVE]
- **Purpose**: AI suggestion review queue that extracts offerings from uploaded knowledge files.
- **Observed Controls**: Scan trigger button, suggested offering review table, edit price/kind modal, one-click approve action.

---

## 7. `/lead-capture/dashboard/` — Auto Lead Capture
- **Observed Title**: `Auto Lead Capture | ABC` [OBSERVED_LIVE]
- **Purpose**: Anonymous website visitor tracking dashboard. Visualizes interest scores, page view durations, and converted leads identified via widget interactions.

---

## 8. `/lead-engagement/settings/` — AI Lead Engagement Agent
- **Observed Title**: `AI Lead Engagement Agent` [OBSERVED_LIVE]
- **Purpose**: Autonomous email follow-up configuration.
- **Observed Controls**: Mailbox assignment selector, agent identity fields, follow-up cadence rules, operating mode toggle (`off`, `draft_only`, `autonomous`), and suppression list.

---

## 9. `/support/tickets/` — Support Tickets Desk
- **Observed Title**: `Support Tickets` [OBSERVED_LIVE]
- **Purpose**: In-app ticketing desk for issues escalated from live chats.
- **Columns**: `Ticket ID`, `Customer Email`, `Subject`, `Priority` (`low`, `medium`, `high`, `urgent`), `Status` (`open`, `in_progress`, `resolved`, `closed`), `Created Date`.
- **Observed Actions**: Create Ticket (`/support/tickets/create/`), Filter by Priority, Reply via Mailbox.

---

## 10. `/chat/widget/` — Website Chat Widget
- **Observed Title**: `Widget Integration | ABC` [OBSERVED_LIVE]
- **Live Controls**:
  - Snippet copy container (`<script src=".../widget/embed.js" data-key="ca_..."></script>`).
  - Allowed domains textarea (origin restriction whitelist).
  - Theme hex color picker, avatar logo upload, custom greeting title, and position toggle (bottom-right / bottom-left).

---

## 11. `/integrations/meta/` — Social Channels Meta Hub
- **Observed Title**: `Social Channels - Central AI` [OBSERVED_LIVE]
- **Observed Component Cards**:
  - **Facebook Messenger Card**: Page ID, Meta OAuth connect button.
  - **Instagram Direct Card**: Instagram Professional account linking status.
  - **WhatsApp Cloud API Card**: WABA ID, Phone Number ID, Permanent System User Token.

---

## 12. `/leads/integrations/` — Lead Integrations & Odoo CRM
- **Observed Title**: `Lead integrations | Central AI` [OBSERVED_LIVE]
- **Live Form Controls**:
  - `odoo_host`: URL of Odoo ERP instance.
  - `odoo_db`: Target Odoo database name.
  - `odoo_username`: API user email.
  - `odoo_api_key`: Secret API key or password.
  - Field mapping dropdowns to Odoo `crm.lead` model.

---

## 13. `/accounts/team/` — Team Management
- **Observed Title**: `Team Management` [OBSERVED_LIVE]
- **Data Table**: Columns `User`, `Email`, `Role`, `Joined`, `Actions`. Active user: `zesan @zesan` (Role: `Admin`).
- **Invite Form**: Email input, Role dropdown (`Admin`, `Operator`), Granular access scopes checkboxes.

---

## 14. `/accounts/security/api-keys/` — Security & API Keys
- **Observed Title**: `API Keys - Central AI` [OBSERVED_LIVE]
- **Purpose**: Key generation and domain whitelist management.
- **Controls**: Widget public key display (`ca_...`), External API secret key display (`cax_...`), Regenerate key button, Allowed domain addition form.

---

## 15. `/billing/usage/` — Usage Dashboard
- **Observed Title**: `Usage Dashboard` [OBSERVED_LIVE]
- **Active Plan Badge**: `Test Drive`
- **Observed Metered Units**:
  - Monthly message consumption progress bar.
  - Knowledge records storage meter (300 record limit).
  - Dashboard users quota (1 user limit).
  - Upgrade CTA linking to `/billing/checkout/starter/`, `/growth/`, `/scale/`.

---

## 16. `/chat/api/docs/` — Tenant-Facing API Reference
- **Observed Title**: `API Reference - Central AI` [OBSERVED_LIVE]
- **Live Content**: Contains 19 reference specification tables covering Chat API (`/chat/api/v1/`), Knowledge Sync API (`/knowledge/api/v1/sync/`), Lead Conversion API (`/api/v1/leads/conversions/`), UCP Commerce API (`catalog.search`, `quote.calculate`, `commit.create`), RFC 9421 signature verification, and idempotency error codes.
