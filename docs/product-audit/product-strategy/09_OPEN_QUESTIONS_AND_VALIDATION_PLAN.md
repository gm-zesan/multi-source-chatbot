# Open Questions & Non-Destructive Validation Plan

- **Document ID**: `STRAT-09`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Benchmark Target**: Central AI (`https://centralai.daffodil-bd.com/`)
- **Status**: Validation Framework & Open Inquiries

---

## 1. Executive Summary

Strategic planning requires acknowledging what remains uncertain. Prioritizing features based on unverified assumptions leads to wasted engineering capacity and misaligned products.

This document compiles the **critical unresolved questions, unverified competitor capabilities, API limitations, and economic risks**, paired with **safe, non-destructive validation protocols** that do not execute live payments, trigger external webhooks, or alter production data.

---

## 2. Inventory of Critical Open Questions

```
====================================================================================================
CATEGORY 1: Competitor Capabilities & Production Reality
====================================================================================================
```

### Q1.1: Is Central AI's Commerce API & UCP Live or Marketing Copy?
- **Uncertainty**: Central AI documents a "Universal Commerce Protocol (UCP)" and Headless Commerce APIs (`/chat/api/docs/`), but direct integration tests were not run during audit to preserve read-only constraints ([`BASE-16`](../central-ai/16_FINAL_CAPABILITY_BASELINE.md#mod-15-commerce-api-ucp)).
- **Why It Matters**: If competitor commerce APIs are rarely utilized by customers, building a heavy headless protocol for our platform would be premature over-engineering.
- **Safe Validation Method**:
  1. Inspect public documentation and community forums for third-party developer implementations.
  2. Interview 5 merchants currently using Central AI to ask if they use the API or standard web dashboard.

### Q1.2: What is the Real Accuracy of Central AI's Spreadsheet RAG?
- **Uncertainty**: Central AI accepts `.xlsx` uploads, but converts rows into text chunks. What is the real-world error rate when asking calculation questions?
- **Why It Matters**: Validating their failure mode empirically proves our marketing claim that our virtual SQLite engine is essential.
- **Safe Validation Method**:
  1. Prepare a standardized public benchmark dataset of 500 rows with known column totals.
  2. Upload to Central AI test drive environment and query 10 arithmetic questions (sums, averages, max).
  3. Record calculation error rate without performing any destructive operations.

---

```
====================================================================================================
CATEGORY 2: Customer Demand & Operational Requirements
====================================================================================================
```

### Q2.1: Do Merchants Want In-Chat Checkout or Simple Storefront Links?
- **Uncertainty**: Central AI invests heavily in dynamic checkout link generation in chat. Do Bangladeshi consumers actually complete purchases inside chat, or do they insist on browsing full web storefronts first?
- **Why It Matters**: Decides whether we should build a full in-chat checkout engine (`FEAT-CHK-01`) or simple deep-links to their existing WooCommerce / Shopify stores.
- **Safe Validation Method**:
  1. Conduct qualitative interviews with 10 e-commerce merchants selling on Facebook/Instagram.
  2. Measure current customer drop-off points between social chat and website checkout.

### Q2.2: Is a Native Product Catalog Necessary If Merchants Have Shopify/WooCommerce?
- **Uncertainty**: Will merchants maintain a separate product catalog in our dashboard, or will they demand automated syncing from Shopify, WooCommerce, or Excel?
- **Why It Matters**: Building catalog CRUD from scratch (`FEAT-CAT-01`) could be redundant if 90% of target merchants demand a Shopify sync plugin.
- **Safe Validation Method**:
  1. Survey 20 potential customers on what software they currently use to manage inventory.
  2. If > 70% use WooCommerce/Shopify, prioritize CMS catalog importers over manual catalog entry forms.

---

```
====================================================================================================
CATEGORY 3: Platform API Constraints & Regulatory Boundaries
====================================================================================================
```

### Q3.1: Meta App Review & WhatsApp Cloud API Approval Timelines
- **Uncertainty**: How long does Meta App Review take to approve `pages_messaging` and WhatsApp Business Account onboarding for our app credentials?
- **Why It Matters**: Without Meta approval, our self-serve social onboarding (`FEAT-SOC-01`) cannot be publicly utilized by non-developer tenants.
- **Safe Validation Method**:
  1. Submit a staging Meta App Review with test page credentials and screen recordings.
  2. Review Meta developer policies on automated chatbot messaging restrictions in Bangladesh.

### Q3.2: Automated Recurring Subscription Billing in Bangladesh
- **Uncertainty**: Can local payment gateways (bKash, SSLCommerz, Shurjopay) execute automated monthly recurring debits without user-initiated OTP prompts on each renewal?
- **Why It Matters**: If automated card/wallet rebilling is legally or technically blocked, SaaS subscription mechanics must incorporate manual renewal reminder workflows.
- **Safe Validation Method**:
  1. Consult official developer documentation for bKash Tokenized Checkout and SSLCommerz Subscription APIs.
  2. Confirm whether recurring rebilling requires special merchant licensing in Bangladesh.

---

## 3. Safe, Non-Destructive Validation Execution Protocol

```
+---------------------------------------------------------------------------------------------------+
| VALIDATION PROTOCOL: SPREADSHEET REASONING BENCHMARK                                             |
+---------------------------------------------------------------------------------------------------+
| Objective: Prove deterministic calculation superiority of our SQLite engine over vector RAG.     |
| Step 1: Create a public synthetic test spreadsheet: 500 sales rows, 5 product categories.        |
| Step 2: Formulate 10 specific aggregation questions:                                              |
|         - "What was the total revenue in category Apparel?"                                       |
|         - "What was the average order value in Chittagong?"                                       |
|         - "Which sales rep closed the most deals in May?"                                         |
| Step 3: Run queries on our platform via tests/Feature/Live/LiveSqlAnalyticsTest.php.              |
| Step 4: Run queries on competitor platform via test drive account.                                |
| Step 5: Document mathematical discrepancy percentage in an empirical evaluation register.        |
+---------------------------------------------------------------------------------------------------+
```

```
+---------------------------------------------------------------------------------------------------+
| VALIDATION PROTOCOL: CUSTOMER DISCOVERY INTERVIEW GUIDE                                           |
+---------------------------------------------------------------------------------------------------+
| Target Audience: 10 Bangladeshi retail / e-commerce business founders or support managers.        |
| Question 1: "How many customer inquiries do you receive daily on Messenger vs WhatsApp vs Web?"   |
| Question 2: "When an AI chatbot cannot answer, how does your team prefer to be notified?"         |
| Question 3: "Do you maintain operational reports in Excel, and what questions do you struggle to  |
|              answer quickly from those files?"                                                    |
| Question 4: "Would you pay a higher tier if the chatbot remembered returning customers' past      |
|              orders and preferences across visits?"                                               |
| Question 5: "What is your preferred payment method for software: Credit Card or bKash?"          |
+---------------------------------------------------------------------------------------------------+
```

---

## 4. Decision Gate Criteria

Before moving any initiative from `PROPOSED` to `ACCEPTED` in [`10_DECISION_REGISTER.md`](10_DECISION_REGISTER.md):
1. **Technical Viability**: Validated by automated feature tests without modifying production code.
2. **Customer Demand**: Minimum 5 customer interview confirmations of the specific pain point.
3. **Unit Economic Feasibility**: Projected gross margin exceeding 80% including LLM and infrastructure costs.
4. **Regulatory / API Clearance**: Verified compliance with Meta platform terms and local payment regulations.
