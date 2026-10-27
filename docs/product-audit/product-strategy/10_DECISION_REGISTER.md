# Strategic Product Decision Register

- **Document ID**: `STRAT-10`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Benchmark Target**: Central AI (`https://centralai.daffodil-bd.com/`)
- **Status**: Formal Decision Tracking Log
- **Default Baseline**: All initiatives start as `PROPOSED` or `NEEDS_VALIDATION`. None are marked `ACCEPTED` without explicit user sign-off.

---

## 1. Decision Status Definitions

- **`PROPOSED`**: Formally submitted recommendation backed by empirical audit findings. Awaiting executive review.
- **`NEEDS_VALIDATION`**: High-potential recommendation that requires customer discovery interviews, technical benchmarking, or API feasibility checks before adoption.
- **`ACCEPTED`**: Explicitly approved by stakeholders for engineering implementation.
- **`DEFERRED`**: Valid proposal postponed to a later roadmap phase due to resource constraints or prerequisites.
- **`REJECTED`**: Formally evaluated and discarded due to strategic misalignment, excessive cost, or negative ROI.

---

## 2. Formal Decision Register

| Decision ID | Proposal Title | Status | Strategic Rationale | Dependencies | Date Reviewed | Supporting Documentation |
| :---: | :--- | :---: | :--- | :--- | :---: | :--- |
| **`DEC-01`** | **Remediate Unauthenticated Microservice Endpoint (`OUR-SEC-02`)** | `PROPOSED` | Critical security vulnerability in [`python-ai-service/app/main.py`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/python-ai-service/app/main.py#L141). Allows unauthorized callers to execute arbitrary tenant queries over Port 8001. | None | October 2026 | [`06_CRITICAL_FINDINGS_VALIDATION.md`](../our-saas/06_CRITICAL_FINDINGS_VALIDATION.md#security-finding-2-unauthenticated-microservice-endpoint-on-python-ai-service) |
| **`DEC-02`** | **Encrypt Channel Account Access/Refresh Tokens (`OUR-SEC-03`)** | `PROPOSED` | High-risk credential exposure in [`app/Models/ChannelAccount.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/ChannelAccount.php#L17-L26). Third-party tokens are currently stored as plaintext in MySQL. | None | October 2026 | [`06_CRITICAL_FINDINGS_VALIDATION.md`](../our-saas/06_CRITICAL_FINDINGS_VALIDATION.md#security-finding-3-plaintext-storage-of-channel-account-access-and-refresh-tokens) |
| **`DEC-03`** | **Implement Public API Key & Origin Domain Whitelist (`FEAT-SEC-01`)** | `PROPOSED` | Non-negotiable security prerequisite before launching public embeddable chat widget. Prevents quota theft and origin spoofing. | Phase 0 Hardening | October 2026 | [`05_FUTURE_FEATURE_RECOMMENDATIONS.md`](05_FUTURE_FEATURE_RECOMMENDATIONS.md#recommendation-j1-comprehensive-tenant-security-hardening-our-sec-01-02-03-05-06) |
| **`DEC-04`** | **Build Standalone Client-Side Embeddable Web Chat Widget (`FEAT-WGT-01`)** | `PROPOSED` | Core commercial delivery channel. Currently, our SaaS only operates inside an internal backend simulator (`/simulator`). | `DEC-03` | October 2026 | [`05_FUTURE_FEATURE_RECOMMENDATIONS.md`](05_FUTURE_FEATURE_RECOMMENDATIONS.md#recommendation-g1-client-side-embeddable-web-chat-widget-bundle-feat-wgt-01) |
| **`DEC-05`** | **Expose Interactive Data Visualization for Spreadsheet Q&A (`FEAT-ANL-01`)** | `NEEDS_VALIDATION` | Elevates our verified technical differentiator (virtual SQLite analytics) into an executive-ready feature. Requires testing merchant preference for chart types. | `python-ai-service` | October 2026 | [`04_STRENGTHS_AND_DIFFERENTIATION.md`](04_STRENGTHS_AND_DIFFERENTIATION.md#differentiator-1-deterministic-spreadsheet--tabular-business-analytics) |
| **`DEC-06`** | **Develop Self-Serve Meta Omnichannel Onboarding UI (`FEAT-SOC-01`)** | `NEEDS_VALIDATION` | Closes usability gap with Central AI. High regional value, but requires validating Meta Developer App Review approval timelines. | Meta App Review | October 2026 | [`09_OPEN_QUESTIONS_AND_VALIDATION_PLAN.md`](09_OPEN_QUESTIONS_AND_VALIDATION_PLAN.md#q31-meta-app-review--whatsapp-cloud-api-approval-timelines) |
| **`DEC-07`** | **Implement Tiered Subscription Billing & Token Quotas (`FEAT-BILL-01`)** | `NEEDS_VALIDATION` | Commercial monetization milestone. Requires validating local recurring payment gateway APIs (bKash Tokenized vs Stripe). | Payment Gateway | October 2026 | [`08_BUSINESS_VALUE_AND_DIFFERENTIATION_STRATEGY.md`](08_BUSINESS_VALUE_AND_DIFFERENTIATION_STRATEGY.md#hyp-biz-02-bangladeshi-recurring-subscription-friction) |
| **`DEC-08`** | **Formalize Customer Support Ticket Lifecycles & SLAs (`FEAT-TKT-01`)** | `PROPOSED` | Closes support team management gap. Transitions conversations into manageable tickets with ownership and SLA tracking. | `FEAT-CHAT-01` | October 2026 | [`05_FUTURE_FEATURE_RECOMMENDATIONS.md`](05_FUTURE_FEATURE_RECOMMENDATIONS.md#recommendation-h1-dedicated-support-ticket-queue--sla-timer-feat-tkt-01) |
| **`DEC-09`** | **Build In-Chat Product Catalog & Recommendation Cards (`FEAT-CAT-01`)** | `NEEDS_VALIDATION` | High conversion potential, but must validate whether merchants prefer manual catalog entry or automated sync from WooCommerce/Shopify. | Typesense | October 2026 | [`09_OPEN_QUESTIONS_AND_VALIDATION_PLAN.md`](09_OPEN_QUESTIONS_AND_VALIDATION_PLAN.md#q22-is-a-native-product-catalog-necessary-if-merchants-have-shopifywoocommerce) |
| **`DEC-10`** | **In-Chat Live Dynamic Checkout Link Generation (`FEAT-CHK-01`)** | `NEEDS_VALIDATION` | High value in Central AI, but requires validating whether consumers actually pay in chat or prefer standard storefront checkouts. | `DEC-09` | October 2026 | [`08_BUSINESS_VALUE_AND_DIFFERENTIATION_STRATEGY.md`](08_BUSINESS_VALUE_AND_DIFFERENTIATION_STRATEGY.md#hyp-biz-03-in-chat-live-checkout-conversion) |
| **`DEC-11`** | **Automated Web Crawler & URL Training (`FEAT-KNOW-02`)** | `DEFERRED` | Valuable convenience feature, but file uploads and FAQ databases already provide sufficient knowledge ingestion for initial launch. | `FEAT-KNOW-01` | October 2026 | [`06_PRIORITIZED_PRODUCT_ROADMAP.md`](06_PRIORITIZED_PRODUCT_ROADMAP.md#phase-3-commerce-expansion--advanced-intelligence) |
| **`DEC-12`** | **Headless Commerce API / Universal Commerce Protocol (`FEAT-COMM-01`)** | `DEFERRED` | High architectural complexity with low immediate demand among target SME merchants in Bangladesh. | `DEC-09` | October 2026 | [`03_PRODUCT_GAP_AND_OPPORTUNITY_MATRIX.md`](03_PRODUCT_GAP_AND_OPPORTUNITY_MATRIX.md) |
| **`DEC-13`** | **Partner Lead Sharing Monetization Network (`FEAT-PART-01`)** | `REJECTED` | Out of scope for core SaaS value proposition; high regulatory and trust complexity with dubious early-stage value. | None | October 2026 | [`01_PRODUCT_VISION_AND_POSITIONING.md`](01_PRODUCT_VISION_AND_POSITIONING.md#6-what-to-build-vs-what-to-avoid) |
| **`DEC-14`** | **Native Inbound IMAP / Outbound SMTP Mailbox Hosting (`FEAT-MLX-01`)** | `REJECTED` | Massive ongoing infrastructure maintenance, spam reputation management, and server overhead with low competitive differentiation. | None | October 2026 | [`01_PRODUCT_VISION_AND_POSITIONING.md`](01_PRODUCT_VISION_AND_POSITIONING.md#6-what-to-build-vs-what-to-avoid) |

---

## 3. Review Cadence & Ownership

- **Next Strategic Review**: Prior to initiating Phase 1 development.
- **Decision Authority**: Product Owner / Technical Lead.
- **Criteria for Promotion**: Promotion from `PROPOSED` / `NEEDS_VALIDATION` to `ACCEPTED` requires explicit user confirmation and completion of the corresponding validation protocol defined in [`09_OPEN_QUESTIONS_AND_VALIDATION_PLAN.md`](09_OPEN_QUESTIONS_AND_VALIDATION_PLAN.md).
