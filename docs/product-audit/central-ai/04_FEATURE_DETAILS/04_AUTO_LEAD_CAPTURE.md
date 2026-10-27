# Feature Detail: Auto Lead Capture & Visitor Tracking

- **Feature ID**: `MOD-04`
- **Module Name**: Auto Lead Capture
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Routes**: `/lead-capture/dashboard/` (Dashboard), Client-Side Tracker API
- **Plan Availability**: Growth (500 leads/mo), Scale (2,000 leads/mo), Enterprise

---

## 1. Overview & Business Purpose
Auto Lead Capture bridges anonymous web browsing with identified CRM lead records. Rather than relying solely on active form submissions, it silently monitors visitor journey signals (pages visited, scroll depth, dwell time, form field focus) to calculate a live "Interest Score." When the visitor identifies themselves (e.g. typing an email in chat or submitting a form), the tracker binds their historical browsing journey to the new lead profile.

---

## 2. Live Dashboard Screen (`/lead-capture/dashboard/`)
- **Observed Title**: `Auto Lead Capture | ABC` [OBSERVED_LIVE]
- **Key Metrics**:
  - Active anonymous visitor count.
  - Converted leads counter.
  - High-intent visitor alert queue.
- **Journey Timeline**: Displays page-by-page dwell time for converted leads prior to identification.

---

## 3. Client-Side Visitor Tracker API

Inspected directly from `/chat/api/docs/` [OBSERVED_LIVE]:

| Method | Path | Parameter / Header | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/lead-capture/vid/` | `X-Site-Id: ca_...` | Generates a persistent visitor ID cookie. |
| `POST`| `/lead-capture/events/` | `X-Site-Id`, Event JSON | Tracks pageviews, scroll events, clicks. |
| `POST`| `/lead-capture/consent/`| `X-Site-Id`, Consent status | Records GDPR / local cookie consent state. |
| `POST`| `/lead-capture/identify/`| `X-Site-Id`, Email/Phone | Binds anonymous browsing history to identity. |

---

## 4. Verification Status & Evidence
- **Observed Live**: Screen `/lead-capture/dashboard/` inspected in authenticated session [OBSERVED_LIVE].
- **Live API Reference**: Tracker endpoints and error codes (`AUTH_REQUIRED`, `ORIGIN_NOT_ALLOWED`) verified in `/chat/api/docs/` [OBSERVED_LIVE].
- **Official Documentation**: Verified in `/docs/lead-capture/` [DOCUMENTED].
