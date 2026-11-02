# Universal Business Intelligence & AI RAG Chatbot
## Product Scope & Engineering Boundaries

**Document Type:** Product Scope / System Requirements  
**Status:** Proposed — Owner Approval Required (v1.3 with Final Lifecycle & Privacy Clarifications)  
**Purpose:** Define exactly what the system must do, what it must not do, and the boundaries within which all development must remain.

---

# 1. Product Vision

The system is a multi-tenant, domain-agnostic AI-powered Business Information, Workforce Operations, and Customer Support Platform.

A business owner provides their own business information, including:

- Business About / Description
- FAQ
- Policies and Terms
- Product or Service Information
- Employee, Salesman, and Workforce Records
- Excel and CSV files
- Business, Sales, and Performance Data

The system organizes, indexes, understands, and retrieves this information to provide intelligent, conversational answers to customers and business owners.

**Core principle:**

The system must work with the information supplied by each business. It must not depend on predefined business categories, product names, industry-specific dictionaries, or business-specific hardcoded mappings.

The system is not supposed to know a business in advance. It should understand the business from its supplied information.

---

# 2. Core Requirements

## REQ-01: Domain-Agnostic Business Support

The system must support different types of businesses without requiring application-code changes for each business.

Examples include:

- E-commerce: Clothing, Shoes, Watches, Electronics, Furniture, etc.
- Product-based businesses
- Service-based businesses
- Consulting and professional services
- Other business domains

These examples are illustrative, not a predefined list of supported categories.

### Requirements

1. No business-specific product names, categories, attributes, or industry assumptions in application logic.
2. No fixed product taxonomy that restricts the types of businesses a workspace can support.
3. Product and service attributes must come from business-provided data.
4. Search, retrieval, clarification, and answer generation must work from available business information.
5. Adding a new business domain must not require modifying domain-specific application code.

Generic language processing (such as Bengali morphology suffix stripping with minimum stem length guards, whitespace normalization, and punctuation cleaning), security validation, data validation, and configurable retrieval algorithms are permitted.

---

## REQ-02: Business Information & Workforce Input (Excel/CSV Dual-Path)

Business owners must be able to provide information through supported input methods.

### Supported file formats

Only:

- Excel: `.xlsx`, `.xls`
- CSV: `.csv`

PDF, DOCX, TXT, images, and other file formats are explicitly outside the current product scope.

### Dual-Path Ingestion Architecture

The ingestion engine must provide two distinct logical processing paths based on the nature and intended usage of the uploaded spreadsheet:

#### Path A: Knowledge Ingestion
For explanatory, customer-facing content such as:
- Business About / Overview
- Frequently Asked Questions (FAQ)
- Policies (Shipping, Return, Refund, Warranty, Privacy)
- Terms and Conditions
- Product and service descriptions

*Conceptual Ingestion Flow:*  
`File upload → Extraction → Validation → Text Normalization → Semantic Chunking → Embedding / Indexing → Typesense Retrieval → Customer RAG`

*Rules for Path A:*
1. Knowledge content must be extracted from the spreadsheet rows/cells and grounded strictly in owner-provided text.
2. Direct text extraction from `.xlsx` and `.xls` files for knowledge purpose must be supported using spreadsheet parsers without throwing binary format errors.
3. Customer-facing access must include only approved public information.
4. Structured numeric calculations (e.g. financial aggregations) must not rely on vector retrieval of text chunks.

#### Path B: Structured Business & Workforce Data Ingestion
For operational and transactional records such as:
- Products and services with structured attributes (SKU, title, price, cost, stock, dimensions)
- Orders and sales transactions
- Inventory and stock movements
- Employee, salesman, and workforce records (name, ID, designation, department, contact)
- Employee-wise sales attribution and customer handling logs
- Sales targets, achievements, and commission rates, where supplied

*Conceptual Ingestion Flow:*  
`File upload → Schema Discovery → Column Mapping → Owner Confirmation → Validation → Structured Storage / Virtual DB → Authorized Analytics`

*Rules for Path B:*
1. Structured data must remain queryable through appropriate structured mechanisms (SQL / Virtual Database Engine).
2. Typesense / vector search must not become the authoritative source for financial, numerical, or inventory calculations.
3. Column mappings must be workspace-specific, persistent, and reliably associated with the relevant import/schema.
4. Ambiguous mappings must require owner confirmation before final activation.
5. Unknown columns must not silently be assigned to incorrect canonical fields; they must be preserved in structured metadata.
6. Duplicate records, malformed rows, and missing identifiers must be caught and reported safely.
7. Imports must support validation, error reporting, retries, checkpointing, and idempotency.
8. Large-file processing must respect memory limits through streaming or chunked processing.

#### Mixed-Purpose Spreadsheets
When an uploaded spreadsheet contains multiple sheets or sections with both knowledge content (e.g. a "Policies" tab) and structured data (e.g. an "Inventory" tab):
1. The system must not silently assume a single ingestion purpose for the entire file.
2. The schema discovery engine must identify sheet-level structures and present the proposed routing to the business owner.
3. The owner must be able to review, split, and confirm the intended processing path per sheet before data activation.

---

## REQ-03: Customer-Facing RAG Chatbot

The customer chatbot must answer questions using information provided by the corresponding business.

### Supported information

- Business information
- FAQ
- Policies
- Product details
- Service details
- Prices and availability, where supplied
- Other approved customer-facing information

### Expected behavior

1. Understand customer questions in supported languages, including Bengali, English, and Banglish.
2. Retrieve relevant information strictly from the correct business workspace.
3. Use appropriate structured retrieval or semantic/hybrid knowledge retrieval.
4. Generate natural, understandable conversational responses.
5. Use retrieved business information as the factual basis of answers.
6. Avoid inventing prices, policies, product details, or service information.
7. If sufficient evidence is unavailable, clearly communicate that the information is not available using polite, human-centric messaging.
8. Ask clarification questions when the customer's question is ambiguous.
9. Maintain conversational context for relevant follow-up questions.

### Grounding principle

The business-provided information is the source of truth.

The AI must not replace missing business information with invented business facts or assumptions.

---

## REQ-04: Business Owner Assistant & Analytics (Customer vs Owner Separation)

Business owners must be able to retrieve and analyze their own business, sales, and employee workforce information.

### Examples of owner analytical inquiries

- Total sales overall or within a date range
- Order count and fulfillment status
- Employee-wise sales ("Rahim এই মাসে কত টাকার sales করেছে?")
- Top-performing employee or salesman ranking ("কোন employee সবচেয়ে বেশি বিক্রি করেছে?")
- Employee workload and order handling ("কোন salesman-এর কাছে কতগুলো order আছে?")
- Customer handling volume ("কে সবচেয়ে বেশি customer handle করেছে?")
- Performance trends and target achievement ("কার sales গত মাসের চেয়ে কমেছে?", "কার target achievement বেশি?")
- Multi-dimensional breakdowns: employee-wise, date-wise, and product/service-wise sales
- Product, service, customer, and lead statistics

### Strict Security & Channel Separation Rules

1. **Customer-Facing Channel Prohibition**: The customer chatbot across all public customer channels (Facebook Messenger, Instagram Messaging, WhatsApp, Web chat widget) must **NEVER** execute `RouteType::ANALYTICS` against internal business, financial, or workforce data.
2. **Independent Authorization**: Analytics authorization must be enforced independently of router classification. Router classification output is never an authorization decision.
3. **Graceful Customer Refusal**: Any customer-facing inquiry regarding internal revenue, profit margins, sales figures, employee performance, salary, commission, targets, or internal workforce records must receive a polite refusal:  
   *"আমি শুধুমাত্র আমাদের পণ্য ও কাস্টমার সার্ভিস সংক্রান্ত তথ্য দিতে পারি। অভ্যন্তরীণ ব্যবসায়িক হিসাব প্রকাশের অনুমতি নেই।"*  
   This denial must be enforced safely and predictably regardless of router classification or predicted intent.
4. **Owner Context Verification**: Owner analytics queries must require authenticated, verified business-owner session context.
5. **Manager Scoping**: Authorized managers may access only the employee records and analytics explicitly permitted by their assigned workspace permissions.
6. **Dual-Layer Server-Side Fail-Closed Enforcement**: Authorization must be enforced strictly server-side at both request ingress (channel boundary) and sensitive data execution boundaries (database/analytics execution layer). Hiding analytics options in the UI does not constitute security.
7. **No Financial Hallucination**: Vector search alone must not be treated as the authoritative calculation engine for financial or numerical analytics. Calculations must use validated structured aggregations.
8. **Employee Self-Service Prohibited**: Employee self-service portals, dashboards, or authentication flows are strictly out of scope unless separately approved by the business owner.

---

## REQ-05: Automatic Lead Extraction (Idempotency & Lifecycle)

The system must extract useful lead information from customer conversations without domain assumptions.

### Potential lead fields

- Customer name
- Phone number (normalized to standard mobile format, e.g. Bangladesh `01[3-9]\d{8}`)
- Email address
- Product or service interest
- Requirements / specifications
- Budget, if mentioned
- Purchase intent / urgency
- Lead status (`new`, `contacted`, `converted`, `discarded`)
- Conversation reference

### Requirements & Idempotency Rules

1. **Conversation-Linked Idempotency**: Repeated extraction from the same conversation must **NOT** create duplicate leads.
2. **Incremental Updates**: When additional details emerge later in the conversation (e.g. phone number provided in turn 1, budget specified in turn 4), the existing lead record must be updated incrementally.
3. **No Hallucinated Customer Details**: Missing information must remain `null` or unknown; the system must never invent customer contact details or intents.
4. **Explicit Conflict Handling**: If a customer provides conflicting information later in the chat (e.g. a different phone number), the change must be recorded explicitly with audit tracking rather than silently overwriting verified data.
5. **Workspace Association**: Every lead must be associated strictly with the originating workspace and conversation ID. Cross-workspace lead matching is prohibited.
6. **Concurrency Safety**: Lead extraction must run safely under retries and concurrent queue worker execution.
7. **Owner Management**: Business owners and authorized staff must be able to view, filter, and manage extracted leads through the owner dashboard. Customer channels have zero access to leads.
8. **CRM vs Lead Distinction**: Existing static CRM Contacts (address book records) and dynamically extracted conversation Leads must remain distinct, with clear traceability linking a lead to a contact where applicable.

---

## REQ-06: Permanent Conversation History (Clear Chat, Soft Delete & Memory Reset)

Conversation history must remain permanently available unless an authorized user explicitly clears or deletes it.

### Final Decision: Soft Delete Only

The product uses **Soft Delete as the only supported conversation deletion behavior**:

1. **Soft Delete Mechanism**: When an authorized user deletes a conversation, the system marks it as deleted using a soft-delete mechanism (recording `deleted_at` timestamp and `deleted_by` user identifier).
2. **Preservation of History**: The conversation and all its associated messages **remain stored in the database**. No database conversation messages are destroyed or purged.
3. **Immediate Visibility Removal**: Soft-deleted conversations must immediately disappear from standard chat lists, history sidebars, and customer-facing views.
4. **Authorized Recovery**: Authorized administrative or owner recovery functionality may restore a soft-deleted conversation back to active status.
5. **No Permanent Deletion or Automatic Purging**: No automatic permanent deletion, hard deletion, or configurable data purging policy will be implemented. Conversation history remains permanently preserved unless a future, separately approved requirement explicitly changes this policy.
6. **Tenant & Record Isolation**: Soft deletion must be strictly tenant-scoped and must not affect other workspaces. Deleting a conversation must **never** delete shared business knowledge, employee records, extracted leads, unrelated business data, or other conversations.
7. **Deletion Metadata**: System maintains audit metadata including `deleted_at`, `deleted_by`, and workspace identity.

### Three Distinct Operations Defined

The system strictly distinguishes between three separate operations that affect conversation data:

#### 1. Clear Chat
- Starts a fresh visible conversation experience or clears the active UI viewport for the user.
- **Does NOT delete** permanently stored conversation messages from the database.
- **Does NOT erase** historical audit logs.
- Resets the active session memory pointer so follow-up questions start clean without stale context.

#### 2. Soft Delete Conversation
- Requires an explicit, authenticated user action to delete or remove a conversation.
- Applies the soft-delete flag (`deleted_at`, `deleted_by`) while retaining 100% of the underlying messages in the database.
- Immediately removes the conversation from active UI views and customer-facing channels.
- Can be restored by authorized recovery procedures.
- Does not affect shared business knowledge, employee data, or unrelated records.

#### 3. Memory Reset (Lifecycle Boundary)
- **Memory Reset is NOT conversation deletion.**
- **What Memory Reset does**: Resets the relevant conversation's persistent conversational memory, such as graph-derived conversational state, topic state, resolved personal references, and personalization context (e.g. KGM memory nodes/edges for that session).
- **What Memory Reset does NOT do**:
  - Does **not** delete the original conversation history or raw messages from the database.
  - Does **not** delete business knowledge, FAQ, policies, products, services, structured business data, employee records, leads, or analytics data.
  - Does **not** modify another conversation's memory or another workspace's memory.
- **Confirmation Required**: Requires explicit user confirmation before execution.
- **Idempotency & Scoping**: Must be idempotent and strictly tenant-scoped.
- **Silent Reconstruction Prohibition**: After reset, the system must not silently reconstruct the reset conversational memory from deleted memory state. It may use conversation history again only through an explicitly defined and authorized future operation.
- **Frozen Architecture**: Existing KGM M1-M5 architecture remains frozen. This is a lifecycle boundary clarification, not approval for a memory redesign.

### History Persistence vs Memory Independence
Conversation history persistence (raw messages stored in MySQL `conversations` and `messages`) and conversational memory (graph representations in KGM / Neo4j) are separate responsibilities:
- Raw message history preserves exact chronological dialogue turns permanently.
- Conversational memory extracts compact semantic facts for prompt injection.
- Memory resets or failures must never destroy or corrupt the original message history.

---

## REQ-07: Employee & Business Workforce Information (Attribution, Disambiguation & Privacy)

The system must support storing, searching, mapping, and analyzing employee-related and workforce data supplied by the business owner.

### Supported employee & workforce records

- Employee name, ID, and designation
- Department or team assignment
- Salesman identifier and role
- Employee-wise sales and transaction records
- Order and customer handling assignment records
- Sales targets and achievement metrics, where supplied
- Commission, incentive, or performance records, where supplied
- Historical performance and tenure records

### Requirements, Attribution & Privacy Rules

1. **Optional & Schema-Dynamic**: Employee and workforce data must be optional and dynamically mapped. No rigid workforce schema is enforced on businesses without staff.
2. **Reliable Sales Attribution**: Employee-wise sales must be calculated **only** when sales records are reliably linked to an employee through a verified identifier (e.g. Employee ID or confirmed Salesman Code).
3. **No Unverified Name-Only Attribution**: Name-only fuzzy matching must never be treated as authoritative sales attribution.
4. **Disambiguation on Ambiguity**: If multiple employees share similar or identical names (e.g. two employees named "Rahim"):
   - The system must ask for clarification: *"আপনার প্রতিষ্ঠানে 'রহিম' নামে ২ জন কর্মী রয়েছেন (সেলস: রহিম ১, ডেলিভারি: রহিম ২)। আপনি কার তথ্য জানতে চাচ্ছেন?"*
   - It must never guess or aggregate arbitrarily.
5. **Preservation of Unattributed Sales**: Sales records lacking employee identifiers must remain explicitly categorized as "Unattributed Sales" rather than assigned to random employees. Total workspace sales must always equal attributed sales plus unattributed sales.
6. **No Invented Relationships**: The system must never invent employee records or performance data.
7. **Strict Customer Privacy & Denial**: Customers across all public channels (Facebook Messenger, Instagram Messaging, WhatsApp, Web chat widget) must never be granted access to employee personal details, designations, contact information, internal sales figures, commissions, salary, targets, or performance evaluations.
8. **Employee Self-Service Out of Scope**: Employee self-service portals, dashboards, mobile apps, or authentication flows remain strictly out of scope unless separately approved by the owner.
9. **Server-Side Authorization & Manager Scoping**: Business owners access their own workspace's authorized employee information and employee-wise analytics. Authorized managers access only employee records explicitly permitted by their assigned permissions. Enforced server-side at request ingress and data execution boundaries. No cross-workspace employee access is allowed.
10. **Identity Resolution**: Do not infer employee identity from ambiguous or fuzzy names. Must use stable employee IDs or owner-confirmed mappings.

---

# 3. Data Architecture Requirements

The system must distinguish between two major data categories.

## 3.1 Knowledge Content
Examples: About, FAQ, Policies, Terms, Product/service descriptions.  
Processed for text-based, keyword, and semantic vector retrieval (Typesense / MySQL FullText).

## 3.2 Structured Business & Workforce Data
Examples: Catalog with attributes, prices, stock, orders, sales transactions, employees, salesman metrics.  
Stored and queried through suitable structured data mechanisms (MySQL relational tables, Python Virtual DB Excel engine).

Spreadsheets must not automatically be dumped into unstructured text chunks. The ingestion engine determines purpose through dynamic schema discovery and owner confirmation.

## 3.3 Source of Truth & Traceability
Every retrieved answer or calculated analytics result must maintain provenance traceable to its underlying business data.

## 3.4 Data Provenance & Traceability Requirements
1. **Knowledge Provenance**: Preserve source document/file, sheet/section, chunk reference, and workspace ID.
2. **Analytics Provenance**: Preserve source spreadsheet/file, sheet name, record IDs, applied column mappings, date range, and calculation filters.
3. **No Fabricated Provenance**: The AI must not invent citations or source references.
4. **Access-Controlled Provenance**: Customer responses may cite only approved public sources (e.g. "From our Return Policy"). Owner analytics may expose internal file/row references.
5. **No Data Corruption on Re-import**: Re-importing or updating a spreadsheet must preserve historical attribution without corrupting previous records.

---

# 4. Access Control & Multi-Tenancy

The platform is strictly multi-tenant with role-based data partitioning.

### Customer Access
Customers access only approved customer-facing information (public business info, FAQs, policies, public product/service details).

**Strict Customer Denials:**
- Customers must NEVER access private employee records, designations, contact details, sales numbers, commissions, salary, targets, or performance evaluations (`AC-20`, `AC-38`).
- Customers must NEVER access owner-only sales analytics, revenue, profit, or other customers' conversations (`AC-14`, `AC-21`).
- Public channels (Facebook, WhatsApp, Instagram, Web Widget) are hard-blocked from executing `RouteType::ANALYTICS`.
- Customer-facing chatbot requests for employee sales or performance must be denied safely, regardless of router classification or predicted intent.

### Owner / Staff Access
- **Business Owner**: Full access to workspace settings, sales analytics, employee workforce performance, salesman metrics, leads, and internal records within their workspace.
- **Authorized Manager**: Scoped access to team/employee performance and operational data strictly as delegated by the owner's assigned permissions (`AC-39`).
- **Staff / Salesman**: Scoped access to assigned customer conversations and leads; future role-based self-service requires separate approval.
- **Employee Self-Service**: Out of scope. Employees do not have self-service access to workforce systems or analytics unless a separate scope document is approved (`AC-40`).

### Security Requirements
1. Enforce workspace isolation across every retrieval and analytics path. Cross-workspace employee or business data access is strictly prohibited (`AC-13`, `AC-32`).
2. Enforce role-based authorization server-side (fail-closed) at both request ingress and sensitive data execution boundaries. Client-side or UI filtering is never sufficient (`AC-21`, `AC-38`, `AC-39`).
3. Never trust workspace identity or role supplied directly by an untrusted customer.
4. Prevent unauthorized access to private business or employee records.
5. Ensure file imports, conversation soft-deletion, and background jobs preserve workspace ownership.
6. Fail safely when authorization or tenant context cannot be established.

---

# 5. RAG & AI Engineering Principles

1. Data-driven rather than domain-hardcoded.
2. Retrieval-grounded rather than model-memory-based business answers.
3. Structured queries for structured calculations.
4. Semantic/hybrid retrieval for knowledge content.
5. Evidence-aware answerability checks.
6. Appropriate clarification for ambiguous questions.
7. Conversation context must be relevant and permission-aware.
8. Model/provider must remain replaceable without changing core business logic.
9. No benchmark-specific answer injection into runtime response generation.
10. Mock tests and live AI tests must be reported separately.

Generic algorithms and configurable business metadata are allowed. Business-specific rules embedded in application code are prohibited.

---

# 6. Existing Architecture Preservation

Existing components must be audited before modification and preserved where they satisfy this scope:
- Laravel orchestration and business-logic control layer.
- HybridRouter v2.2 as the frozen production first-hop router (capabilities: CHAT, KNOWLEDGE, ANALYTICS, OOD, UNCERTAIN; ACTION remains deferred).
- Python retrieval and analytics services (including virtual DB Excel analytics engine).
- Typesense search infrastructure.
- Conversation and message persistence in MySQL.
- Conversation memory integration (KGM).
- Redis queue and concurrency safety (worker locks, fencing tokens).
- Workspace isolation and database safety guards.

Do not replace an existing component merely to introduce a new library or architecture. Do not remove security or reliability controls as part of domain-specific cleanup.

---

# 7. Non-Functional Requirements

- Secure multi-tenant operation
- Reliable file ingestion
- Safe background processing
- Retry and idempotency protection
- Retrieval accuracy
- Grounded responses
- Consistent conversation persistence
- Appropriate error handling
- Observable processing failures
- Scalable retrieval and processing
- Maintainable and testable architecture

---

# 8. Explicitly Out of Scope

Unless the owner approves a separate scope document, the following are not authorized:
1. Adding support for PDF, DOCX, TXT, images, or other file formats.
2. Building domain-specific business modules for clothing, shoes, watches, or any particular industry.
3. Adding fixed product dictionaries or industry-specific regex mappings.
4. Optimizing code solely to increase scores on the existing synthetic NovaCraft benchmark.
5. Adding benchmark-specific scenario handling.
6. Replacing the existing architecture without a demonstrated requirement.
7. Introducing unrelated AI agents or frameworks.
8. Adding new social media integrations or channel providers.
9. Building accounting, ERP, inventory, payment, or full CRM products beyond the explicitly defined requirements.
10. Implementing automatic business actions or transactions without a separately approved requirement.
11. Making production database changes or external API calls during audit/testing without explicit authorization.
12. Permanent database conversation purging, hard-deletion, or automatic data purging policies (Soft Delete is the only authorized conversation deletion mechanism).
13. Employee self-service portals, dashboards, mobile apps, profile self-management, or employee-facing authentication flows without separate owner approval.
14. Conversational memory architecture overhaul or redesign (KGM M1-M5 architecture remains frozen).

---

# 9. Acceptance Criteria

The system will be evaluated against the following 40 end-to-end requirements.

| ID | Acceptance test | Expected result | Relevant Condition |
|---|---|---|---|
| **AC-01** | Import a watch business Excel catalog | Data imports without watch-specific application code | REQ-01 |
| **AC-02** | Import a service business CSV | Service data imports without service-specific application code | REQ-01 |
| **AC-03** | Use different column names across workspaces | Dynamic mapping or owner confirmation works | REQ-02 |
| **AC-04** | Ask a customer about a supplied product/service | Correct business information is retrieved | REQ-03 |
| **AC-05** | Ask about a supplied policy or FAQ | Relevant knowledge is retrieved | REQ-03 |
| **AC-06** | Ask about unavailable information | No invented answer; polite graceful refusal | REQ-03 |
| **AC-07** | Ask a follow-up question | Relevant conversation context is maintained | REQ-03 |
| **AC-08** | Ask an owner for sales analysis | Correct structured calculation is returned | REQ-04 |
| **AC-09** | Ask for salesman performance | Correct authorized records are analyzed | REQ-04 |
| **AC-10** | Provide customer details in chat | Lead information is extracted and associated with the conversation | REQ-05 |
| **AC-11** | Continue an existing conversation | Previous messages remain available | REQ-06 |
| **AC-12** | Clear a conversation explicitly | The defined clear/delete behavior is executed | REQ-06 |
| **AC-13** | Attempt cross-workspace retrieval | Access is denied | Section 4 |
| **AC-14** | Customer asks for private business analytics | Private information is not disclosed | REQ-04 |
| **AC-15** | Run real AI acceptance tests | Real model execution is clearly distinguished from mocked tests | Section 5 |
| **AC-16** | Import employee workforce Excel/CSV file | Employee and salesman data dynamically mapped | REQ-07 |
| **AC-17** | Ask an owner for employee-wise sales calculation | Correct individual employee sales calculation returned | REQ-07 |
| **AC-18** | Ask an owner for top-performing salesman/employee | Correct ranking returned based on structured records | REQ-07 |
| **AC-19** | Ask an owner for employee historical performance | Correct date-scoped performance report returned | REQ-07 |
| **AC-20** | Customer requests employee private or sales data | Access is strictly denied with graceful refusal | REQ-07 |
| **AC-21** | Customer requests analytics via WhatsApp/Widget | RouteType::ANALYTICS is intercepted; graceful refusal issued; zero data leaked | P0 Security Barrier / REQ-04 |
| **AC-22** | Authenticated owner requests sales in dashboard | Analytics executed over structured DB / virtual engine; accurate numbers returned | REQ-04 Authorization |
| **AC-23** | Dual-path Excel/CSV ingestion | Knowledge content parsed & indexed in Typesense (Path A); structured data mapped to Virtual DB/SQL (Path B); mixed sheets flagged for owner confirmation | REQ-02 Dual-Path Ingestion |
| **AC-24** | Execute Clear Chat in customer UI | Active UI context cleared; session memory pointer reset; 100% of raw conversation history and messages remain intact in MySQL `messages` table | REQ-06 Clear Chat |
| **AC-25** | Execute Memory Reset | Active session memory context and graph-derived KGM references reset; raw conversation message history in MySQL remains completely preserved | REQ-06 Memory Reset |
| **AC-26** | Lead lifecycle idempotency | Customer provides incremental details across multiple turns in the same conversation; single lead record updated idempotently; zero duplicate leads created | REQ-05 Idempotency |
| **AC-27** | Incomplete / partial lead capture | Customer provides partial contact details (e.g. name only, or phone only); provided fields saved; missing fields remain null/empty; system never invents or hallucinates details | REQ-05 Partial Lead Capture |
| **AC-28** | Query sales for ambiguous employee name | Bot requests clarification between matching employees; zero unverified attribution | REQ-07 Disambiguation |
| **AC-29** | Query employee sales with unattributed rows | Unlinked sales remain explicitly unattributed; total workspace sales balance preserved | REQ-07 Attribution |
| **AC-30** | Trace provenance for knowledge or analytics answer | Source file, sheet name, row/chunk ID, and date filters verified without cross-tenant leak | Section 3.4 Provenance |
| **AC-31** | Owner analytics date range & query limits enforcement | Owner queries sales or employee performance with date filters and limit parameters; query filters are strictly enforced on structured aggregations | REQ-04 / REQ-07 Query Scoping |
| **AC-32** | Cross-tenant data isolation for knowledge & analytics | Inquire or retrieve source provenance, analytics, or knowledge across different workspaces; access is strictly partitioned by workspace ID with zero cross-tenant leak | Section 4 Multi-Tenancy |
| **AC-33** | Soft Delete follows retention & recovery rules | Conversation marked with soft-delete metadata (`deleted_at`, `deleted_by`), hidden from customer/chat lists; database records preserved; restorable by authorized recovery | REQ-06 Soft Delete Only |
| **AC-34** | Hard Purge attempt restriction | Permanent hard-deletion and automatic data purging are rejected/unsupported; system enforces Soft Delete only, preserving full database conversation history and audit records unless an explicitly documented legal/audit exception is separately approved | REQ-06 Soft Delete Only |
| **AC-35** | Conversation deletion isolates shared data | Soft deleting a conversation referencing products or policies preserves all shared knowledge base items, FAQs, employee records, leads, and unrelated conversations intact | REQ-06 Deletion Isolation |
| **AC-36** | Memory Reset silent reconstruction prohibition | Follow-up query in a memory-reset conversation does not silently rebuild memory state from stale/deleted graph memory; starts fresh without residual personalized state | REQ-06 Memory Lifecycle |
| **AC-37** | Memory Reset workspace & conversation isolation | Triggering Memory Reset on Conversation A in Workspace 1 has zero effect on Conversation B in Workspace 1 or any conversation in Workspace 2 | REQ-06 Memory Scoping |
| **AC-38** | Customer channel employee privacy denial | Customer inquiries regarding employee sales, salary, commission, targets, or performance are safely denied server-side, regardless of router intent prediction | REQ-04 / REQ-07 / Section 4 |
| **AC-39** | Server-side role enforcement for workforce data (RBAC) | Authorized manager accesses only scoped employee records permitted by assigned permissions; unauthorized manager or cross-tenant user is blocked server-side at data boundary | Section 4 / REQ-07 Server Authorization |
| **AC-40** | Employee self-service portal request blocked | Requests to employee self-service endpoints/portals are refused/blocked as out of scope pending separate future approval | Section 8 / REQ-07 Scope Boundary |

Passing an isolated unit test is not sufficient to claim an end-to-end requirement is complete.

---

# 10. Development Governance

This document defines the authorized product scope.

Every development task must:

1. Reference one or more requirement IDs from this document.
2. Explain why the change is necessary.
3. Identify the affected components.
4. Explain the expected behavior before implementation.
5. Preserve existing user changes.
6. Avoid unrelated refactoring.
7. Include relevant automated tests.
8. Include end-to-end verification where applicable.
9. Report limitations honestly.
10. Update documentation when an approved requirement changes.

### Strict restrictions

- Do not implement features outside this document.
- Do not perform speculative improvements.
- Do not create extra benchmark projects.
- Do not change production data during testing.
- Do not delete existing code before proving it conflicts with this scope.
- Do not claim completion based on mocks or synthetic fixtures.
- Do not modify frozen evaluation datasets.
- Do not use destructive Git operations or overwrite user changes.

If a necessary feature is not covered by this scope, stop that particular implementation and request owner approval.

---

# 11. Definition of Done

The product is considered aligned with this scope when:

1. It supports different business domains without domain-specific code changes (`REQ-01`, `AC-01`, `AC-02`).
2. Business-provided information, structured spreadsheets, and employee data can be imported, organized, and retrieved via dual-path ingestion (`REQ-02`, `REQ-07`, `AC-03`, `AC-16`, `AC-23`).
3. Customer answers are grounded strictly in the correct business information without invention or hallucination (`REQ-03`, `AC-04`, `AC-05`, `AC-06`, `AC-07`).
4. Owners can retrieve and analyze their authorized structured business and workforce data, while customer channels are strictly barred from internal analytics (`REQ-04`, `REQ-07`, `Section 4`, `AC-08`, `AC-09`, `AC-14`, `AC-17`, `AC-18`, `AC-19`, `AC-21`, `AC-22`, `AC-28`, `AC-29`, `AC-31`, `AC-38`, `AC-39`).
5. Customer leads can be extracted and managed idempotently without hallucinations (`REQ-05`, `AC-10`, `AC-26`, `AC-27`).
6. Conversation history is retained permanently, with clear separation between Clear Chat, Soft Delete, and Memory Reset (`REQ-06`, `AC-11`, `AC-12`, `AC-24`, `AC-25`, `AC-33`, `AC-34`, `AC-35`, `AC-36`, `AC-37`).
7. Tenant and role-based access controls are strictly verified, prohibiting customer access to private business and employee records, preventing cross-tenant leakage, and rejecting out-of-scope self-service (`Section 4`, `Section 3.4`, `AC-13`, `AC-20`, `AC-30`, `AC-32`, `AC-38`, `AC-39`, `AC-40`).
8. The 40 major acceptance tests (`AC-01` through `AC-40`) pass through real application paths.
9. Mocked, deterministic, and live AI test results are reported separately (`AC-15`).
10. No unresolved critical security or data-integrity issue remains.

---

**Final authority:** The business owner approves changes to this scope. Engineering agents must not expand the product scope independently.

**Primary objective:** Build a reliable, domain-agnostic, data-driven RAG chatbot, employee workforce intelligence, and business information assistant—not a collection of benchmark-specific features.
