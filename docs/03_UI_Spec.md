# 03. UI Specification & Design System (UI_Spec)

> **Status**: CURRENTLY_VERIFIED  
> **Source of Truth**: Reverse-engineered from `resources/scss/admin/` (`_variables.scss`, `_tables.scss`, `_buttons.scss`, `_badges.scss`, `_layout.scss`) and Blade templates.  
> **Last Updated**: October 2026  

---

## 1. Design Philosophy & Aesthetic Foundation

The administrative interface employs a **Modern Premium Enterprise** design language characterized by:
* Clean, content-focused white/gray surfaces with subtle slate borders.
* Vibrant accent colors for status clarity and action hierarchy.
* Micro-animations on interactive elements (hover lifts, spin animations on loaders).
* High-density, scan-friendly DataTables with strict column alignments and custom status badges.
* Resilient responsiveness ensuring complex multi-column tables and simulator split-views work on tablets and mobile screens.

---

## 2. Design Tokens & Variables

Derived directly from `resources/scss/admin/_variables.scss`:

### 2.1 Color Palette
| Category | Token | Hex Value | Primary Usage |
| :--- | :--- | :--- | :--- |
| **Primary** | `$primary` | `#3b82f6` | Brand accent, primary buttons, active tab indicators, focus rings |
| | `$primary-dark` | `#1d4ed8` | Button hover and active states |
| | `$primary-light` | `#dbeafe` | Light badge backgrounds, selected row highlights |
| **Success** | `$success` | `#10b981` | Active status badges, sync complete alerts, positive actions |
| | `$success-light` | `#d1fae5` | Soft green background for active status pills |
| **Danger** | `$danger` | `#ef4444` | Delete buttons, sync failed badges, error alerts |
| | `$danger-light` | `#fee2e2` | Soft red background for failed or rejected status |
| **Warning** | `$warning` | `#f59e0b` | Syncing/processing spinners, draft status, confirmation dialogs |
| | `$warning-light` | `#fef3c7` | Soft amber background for in-flight items |
| **Info** | `$info` | `#06b6d4` | Informational callouts, reindex prompts |
| | `$info-light` | `#cffafe` | Light cyan backgrounds |
| **Neutrals** | `$white` | `#ffffff` | Card surfaces, modal containers |
| | `$gray-50` | `#fafafa` | Main page background |
| | `$gray-100` | `#f5f5f5` | Subtle container background |
| | `$gray-200` | `#eeeeee` | Dividers, table borders |
| | `$gray-500` | `#6b7280` | Secondary text, helper labels |
| | `$gray-700` | `#374151` | Body text, table data cells |
| | `$gray-900` | `#111827` | Headings, prominent titles |

### 2.2 Typography
* **Font Family**: Modern sans-serif stack: `'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif`.
* **Font Sizes**:
  - `Display / Page Title`: `24px` (`1.5rem`), font-weight: `700`, line-height: `1.2`.
  - `Card Header / Section Heading`: `18px` (`1.125rem`), font-weight: `600`.
  - `Body / Standard UI`: `14px` (`0.875rem`), font-weight: `400` or `500`.
  - `Small / Badge / Meta`: `12px` (`0.75rem`), font-weight: `500` or `600`.

### 2.3 Elevation & Shadows
* `$box-shadow-sm`: `0 1px 2px 0 rgba(0, 0, 0, 0.05)`
* `$box-shadow`: `0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)` (Used on cards and tables)
* `$box-shadow-lg`: `0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)` (Used on dropdowns and modals)

### 2.4 Border Radius Scale
* `$border-radius-sm`: `4px`
* `$border-radius`: `8px` (Standard card and input border radius)
* `$border-radius-lg`: `12px`
* `$border-radius-full`: `9999px` (Pills, circular avatars, and action chips)

---

## 3. UI Component Specifications

### 3.1 DataTables & Table Containers
* **Class**: `.table.dataTable` inside `.table-container`.
* **X-Axis Overflow Invariant**:
  ```scss
  .dataTables_wrapper {
      width: 100% !important;
      overflow-x: visible !important; // Keeps Search, Entries, and Pagination pinned
  }
  table.dataTable {
      display: block !important;
      width: 100% !important;
      overflow-x: auto !important; // Scrolls only data columns horizontally on overflow
      -webkit-overflow-scrolling: touch;
      border-collapse: collapse;
  }
  ```
* **Header Style**: Light gray background (`$gray-100`), uppercase micro-typography (`11px`), letter-spacing: `0.05em`, color: `$gray-500`.
* **Row Hover**: Smooth transition to `$primary-lighter` on hover (`transition: background 0.15s ease`).

### 3.2 Status Badges & Document Type Pills
* **Lifecycle Badges**:
  - `Active`: Green pill (`bg-success-light text-success`).
  - `Syncing`: Amber pill with spinning icon (`bg-warning-light text-warning`).
  - `Sync Failed`: Red pill (`bg-danger-light text-danger`).
  - `Draft`: Gray pill (`bg-gray-100 text-gray-700`).
* **Document Type Badges**:
  - `faq`: Blue pill (`bg-primary-light text-primary`).
  - `policy`: Purple pill (`bg-purple-100 text-purple-700`).
  - `company_info` / `customer_support`: Teal/Cyan pill (`bg-cyan-100 text-cyan-800`).
  - `data_analytics`: Emerald pill (`bg-emerald-100 text-emerald-800`).

### 3.3 Buttons & Actions
* **`.btn-primary`**: Solid blue background with white text, 8px border radius, subtle hover lift (`transform: translateY(-1px)`).
* **`.btn-outline-primary`**: 1px primary border with transparent background; fills on hover.
* **`.add-new`**: High-prominence CTA button with primary gradient or solid accent, placed top-right of table headers.
* **Loading State**: Buttons accept `.ri-spin` on icon children and `:disabled` attribute to prevent double submission during asynchronous operations (e.g., Reindexing).

### 3.4 Modals & Alerts (SweetAlert)
* **SweetAlert Integration (`swal`)**:
  - Destructive confirmations (delete, bulk delete) use `dangerMode: true`.
  - Non-destructive operational confirmations (Reindex Knowledge Base) use `icon: "info"`, `dangerMode: false`, and `.btn-primary` styling on the confirm button.

---

## 4. Responsive Breakpoints & Mobile Adaptation

| Breakpoint | Width Range | Layout Adaptation |
| :--- | :--- | :--- |
| **Mobile (`xs`)** | `< 576px` | Sidebar collapses to off-canvas drawer. Hub action bar stacks vertically. DataTables scrolls horizontally inside viewport. Simulator chat fills 100% screen. |
| **Tablet (`sm`, `md`)** | `576px – 991px` | Sidebar collapsed by default with hamburger toggle. Tables maintain full width. Simulator splits to single-column with tabbed telemetry. |
| **Desktop (`lg`, `xl`)** | `992px – 1399px` | Full fixed sidebar. Tab pills align horizontally. Tables render full standard width without truncation. |
| **Wide Desktop (`xxl`)** | `≥ 1400px` | Maximum container constraints prevent excessive horizontal stretching; optimal reading line-length maintained. |
