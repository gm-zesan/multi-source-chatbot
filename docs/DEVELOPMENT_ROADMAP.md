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
Phase 3: Omnichannel Scale & Advanced Automation (NEXT MILESTONE)
  ├── WhatsApp Business Official Cloud API Live Connector
  ├── Customer Feedback & Post-Delivery CSAT Rating
  └── Shopify / WooCommerce 1-Click Catalog Bridge
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

## Phase 3: Omnichannel Ecosystem & Commerce Bridges (পরবর্তী ধাপ)
*লক্ষ্য: বাংলাদেশের বাইরে বা বড় মার্চেন্টদের জন্য একাধিক প্ল্যাটফর্ম সংযোগ।*

- [ ] **WhatsApp Cloud API Integration:** মেটা বিজনেস ম্যানেজারের ক্লাউড এপিআই দিয়ে হোয়াটসঅ্যাপে স্বয়ংক্রিয় সেলস ও অর্ডার কনফার্মেশন।
- [ ] **Post-Purchase Delivery Feedback (CSAT):** পার্সেল কাস্টমারের হাতে পৌঁছার পর স্বয়ংক্রিয়ভাবে মেসেঞ্জারে সন্তুষ্টি রেটিং নেওয়া।
- [ ] **Shopify & WooCommerce Webhook Bridge:** সরাসরি শপিফাই বা ওয়ার্ডপ্রেস স্টোর থেকে পণ্য ক্যাটালগ ও স্টক স্বয়ংক্রিয়ভাবে এআই-তে সিঙ্ক।

---

## মানদণ্ড ও কোয়ালিটি রুলস (Engineering Commandments)

1. **কোনো অনুমানের স্থান নেই (Grounded Truth Only):** ক্যাটালগ বা ডেটাবেজে তথ্য না থাকলে এআই কখনো মনগড়া উত্তর দেবে না; সরাসরি হিউম্যান টেকওভারের অনুরোধ করবে।
2. **জিরো ডেটা ব্রিচ (Tenant Partitioning):** এক মার্চেন্টের বিক্রয় বা লাভ-ক্ষতির ডেটা অন্য কোনো মার্চেন্টের এআই সেশনে কখনো প্রবেশ করতে পারবে না।
3. **টেস্টিং বাধ্যবাধকতা:** প্রতিবার কোনো নতুন ফিচার যুক্ত হলে `php artisan test` চালিয়ে নিশ্চিত করতে হবে যে আগের কোনো টেস্ট বা রিগ্রেশন নষ্ট হয়নি।
