# Entrepreneurs Automation — Development Roadmap
## পর্যায়ক্রমিক বাস্তবায়ন পরিকল্পনা ও অ্যাকশন প্ল্যান (Phased Engineering Roadmap)

**Document Type:** Phased Development Roadmap & Release Schedule  
**Target Focus:** 100% Focused on E-commerce / F-commerce SaaS MVP  
**Acceptance Criteria:** Every phase must pass strict automated test suites and real-world evidence.

---

## Roadmap Overview

```
Phase 1: E-commerce Core MVP (COMPLETED - 100%)
  ├── Knowledge & Catalog Grounding (Done)
  ├── Bilingual Sales & Support Pipeline (Done)
  ├── Order Capture & Steadfast/Pathao Courier Sync (Done)
  └── Multi-Tenant Billing & Public Web Experience (Done)
        │
Phase 2: Automated Retention & Owner Intelligence UI (COMPLETED - 100%)
  ├── Automated Abandoned Cart Recovery (Cron Queue & 24h Meta Safe)
  ├── Proactive Lead Follow-up Scheduler (Anti-Spam 1-turn Guarantee)
  └── Owner Virtual CFO Interactive Dashboard Chat UI (10 Tools Connected)
        │
Phase 3: Omnichannel Scale & Advanced Automation (COMPLETED - 100%)
  ├── WhatsApp Business Official Cloud API Live Connector (Done)
  ├── Customer Feedback & Post-Delivery CSAT Rating (Done)
  └── Shopify / WooCommerce 1-Click Catalog Bridge (Done)
```

---

## Phase 1: E-commerce Core MVP (ভিত্তিপ্রস্তর — সম্পন্ন)
*লক্ষ্য: একজন মার্চেন্ট যেন এক্সেল ফাইল আপলোড করে ফেসবুক মেসেঞ্জারে নির্ভুল সেলস ও কুরিয়ার বুকিং পেতে পারেন।*

- [x] **Product Catalog & Knowledge Ingestion:** মাল্টি-শিট এক্সেল ও সিএসভি পার্সিং, কলাম মেমোরি, ০% হ্যালুসিনেশন।
- [x] **Omnichannel Customer Inbox:** ফেসবুক মেসেঞ্জার ওয়েবহুক ও স্টোরফ্রন্ট লাইভ ওয়েব চ্যাট উইজেট।
- [x] **Autonomous Sales & Support Dialogues:** বাংলা, ইংরেজি ও বাংলিশ ভাষায় সাইজ, স্টক, দাম ও পলিসির উত্তর।
- [x] **Automatic Lead & Order Capture:** ফোন নম্বর ও ঠিকানা পেলেই অর্ডার ডেটাবেজে জমা ও ইনবক্স ট্যাগিং।
- [x] **Courier Logistics Integration:** Steadfast ও Pathao কুরিয়ারে ১-ক্লিক পার্সেল বুকিং।
- [x] **Public Marketing Website & Billing:** স্বচ্ছ বাইলিঙ্গুয়াল ওয়েবসাইট ও ৳০.৪০/মেসেজ প্রিপেইড ওয়ালেট লেজার।

---

## Phase 2: Retention Engine & Owner Control Center (সম্পন্ন — COMPLETED)
*লক্ষ্য: সেলস বৃদ্ধি, ড্রপ-আউট কাস্টমার রিকভারি এবং ওনার বিজনেস ইন্টেলিজেন্স ইন্টারফেস নিশ্চিত করা।*

- [x] **টাস্ক ২.১: Automated Abandoned Lead Recovery Scheduler**
  - লারাভেল কিউ (`retention` queue) ও প্রতি ১৫ মিনিটের শিডিউলার দ্বারা পরিচালিত (`retention:process-abandoned-leads`)।
  - মেটার ২৪ ঘণ্টার মেসেজিং পলিসি কঠোরভাবে মেনে চলে (২৩ ঘণ্টার হার্ড লিমিট ও অ্যান্টি-স্প্যাম গার্ড)।
  - কাস্টমার যদি অলরেডি অর্ডার করে ফেলে, তবে স্বয়ংক্রিয়ভাবে ফলো-আপ বাতিল হয়।
  - ইমপ্লিমেন্টেড ফাইলসমূহ: `app/Services/Retention/AbandonedLeadRecoveryService.php`, `app/Jobs/SendAbandonedLeadFollowUpJob.php`, `app/Console/Commands/ProcessAbandonedLeadFollowUpsCommand.php`।

- [x] **টাস্ক ২.২: Dedicated Owner AI Chat Console (Virtual CFO)**
  - অ্যাডমিন ড্যাশবোর্ডের নিচে ডানপাশে ফ্লোটিং "Ask Virtual CFO" ইন্টারেক্টিভ উইজেট।
  - ব্যাকএন্ডের `OwnerBusinessAgent` ও ১০টি গভর্ন্ড অ্যানালিটিক্যাল টুলের সাথে সরাসরি সংযুক্ত।
  - মার্চেন্ট বাংলায় জিজ্ঞেস করতে পারেন: *"আজকের নিট প্রফিট কত?", "কোন আইটেমটি স্টক-আউট হতে চলেছে?", "বাকি কত টাকা?"*
  - ইমপ্লিমেন্টেড ফাইলসমূহ: `app/Http/Controllers/OwnerAiChatController.php`, `resources/views/admin/partials/owner-ai-chat-widget.blade.php`।

---

## Phase 3: Omnichannel Ecosystem & Commerce Bridges (সম্পন্ন — COMPLETED)
*লক্ষ্য: মাল্টি-চ্যানেল স্কেলিং, আন্তর্জাতিক ব্র্যান্ড এবং শপিফাই/ওয়ার্ডপ্রেস স্টোরের সাথে লাইভ এআই সমন্বয়।*

- [x] **টাস্ক ৩.১: WhatsApp Business Official Cloud API Live Connector**
  - মেটা ক্লাউড এপিআই v20.0/v21.0 হ্যান্ডশেক ভেরিফিকেশন ও ওয়েববুক পার্সার।
  - টেক্সট, কুইক রিপ্লাই বাটন, লিস্ট রিপ্লাই, ইমেজ, অডিও, ভিডিও এবং ডকুমেন্ট অ্যাটাচমেন্ট সাপোর্ট।
  - মেটা ড্যাশবোর্ড ক্রেডেনশিয়ালস এনক্রিপ্টেড স্টোরেজ ও ডায়াগনস্টিক পিং হ্যান্ডশেক।
  - ইমপ্লিমেন্টেড ফাইলসমূহ: `app/Channels/WhatsApp/WhatsAppDriver.php`, `config/services.php`, `app/Http/Controllers/ChannelIntegrationController.php`, `tests/Feature/Channels/WhatsAppWebhookAndDeliveryTest.php`।

- [x] **টাস্ক ৩.২: Automated Post-Delivery Customer Feedback & CSAT Rating**
  - Steadfast বা Pathao কুরিয়ার থেকে পার্সেল `delivered` হওয়ার সাথে সাথে স্বয়ংক্রিয়ভাবে কাস্টমারকে ভদ্রতাপূর্ণ বাংলা/ইংরেজি ফিডব্যাক মেসেজ প্রেরণ।
  - ১-৫ রেটিং ডিজিট এবং ন্যাচারাল ল্যাঙ্গুয়েজ সেন্টিমেন্ট অ্যানালাইসিস (`অসাধারণ`, `ভালো`, `বাজে`, ইত্যাদি) স্বয়ংক্রিয়ভাবে ডিটেক্ট করে `OrderFeedback` টেবিলে CSAT রেকর্ড সংরক্ষণ।
  - স্বয়ংক্রিয় কৃতজ্ঞতা স্বীকারোক্তি মেসেজ ও ওয়ার্কস্পেস CSAT মেট্রিক্স অ্যানালিটিক্স।
  - ইমপ্লিমেন্টেড ফাইলসমূহ: `app/Services/Retention/PostDeliveryFeedbackService.php`, `app/Jobs/SendPostDeliveryFeedbackJob.php`, `app/Listeners/ProcessPostDeliveryFeedbackListener.php`, `database/migrations/2026_11_23_000001_create_order_feedbacks_table.php`, `tests/Feature/Commerce/PostDeliveryFeedbackTest.php`।

- [x] **টাস্ক ৩.৩: Shopify & WooCommerce 1-Click Catalog Bridge**
  - শপিফাই ও ওয়ার্ডপ্রেস/উকমার্স ওয়েববুক (`products/create`, `products/update`, `inventory_levels/update`) সিঙ্কিং।
  - অ্যাডমিন ড্যাশবোর্ডে ১-ক্লিক ব্যাকএন্ড রেস্ট এপিআই ইমপোর্ট ও রিয়েল-টাইম স্টক আপডেট।
  - লাইভ এআই নলেজ বেস ও ক্যাটালগ ক্যাশ বাস্টিং (`ws_{id}_all_products_v2`)।
  - ইমপ্লিমেন্টেড ফাইলসমূহ: `app/Services/Catalog/ExternalStoreCatalogBridgeService.php`, `app/Http/Controllers/StoreCatalogBridgeController.php`, `resources/views/admin/knowledge-base/index.blade.php`, `tests/Feature/Catalog/ExternalCatalogBridgeTest.php`।

---

## মানদণ্ড ও কোয়ালিটি রুলস (Engineering Commandments)

1. **কোনো অনুমানের স্থান নেই (Grounded Truth Only):** ক্যাটালগ বা ডেটাবেজে তথ্য না থাকলে এআই কখনো মনগড়া উত্তর দেবে না; সরাসরি হিউম্যান টেকওভারের অনুরোধ করবে।
2. **জিরো ডেটা ব্রিচ (Tenant Partitioning):** এক মার্চেন্টের বিক্রয় বা লাভ-ক্ষতির ডেটা অন্য কোনো মার্চেন্টের এআই সেশনে কখনো প্রবেশ করতে পারবে না।
3. **টেস্টিং বাধ্যবাধকতা:** প্রতিবার কোনো নতুন ফিচার যুক্ত হলে `php artisan test` চালিয়ে নিশ্চিত করতে হবে যে আগের কোনো টেস্ট বা রিগ্রেশন নষ্ট হয়নি।
