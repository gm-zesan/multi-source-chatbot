# Frontend Unit Test Gaps & Architecture Analysis (Module 8)

## 1. Overview
The frontend chat interfaces in `resources/views/admin/simulator.blade.php` and `resources/views/chat.blade.php` use lightweight vanilla JavaScript with Chart.js and jQuery for interactive visual metrics, export buttons, and message streaming.

## 2. Deterministic JavaScript Functions Under Audit
The core algorithmic logic embedded in the frontend comprises:
1. `parseAnalyticsData(text)`:
   - Regex-based extraction of numeric metrics (e.g. `- **Sales Amount:** ৳420,000.00`).
   - Clean number parsing supporting currency symbols (`৳`, `$`), commas, decimals.
   - Title normalization from markdown headings.
2. `escapeHtml(str)` & `escapeJs(str)`:
   - XSS sanitization preventing code injection while preserving markdown characters.
3. `initChartJs(canvasId, parsedData, type)` / `switchChartType(chartId, newType, btn)`:
   - Chart.js instance creation, palette coloring, responsive configuration.
   - Dynamic instance cleanup (`window.chartJsInstances[canvasId].destroy()`) preventing duplicate canvas overlap or memory leaks.
4. `triggerExport(chartId, format)`:
   - JSON payload packaging with headers, rows, and summary, dispatched to `/dashboard/export/analytics`.

## 3. Test Gaps & Strategy
* **Unit Testing Strategy:** A dedicated Node/PHPUnit runner parses sample analytics markdown strings to verify `parseAnalyticsData` output structure, number precision, and title extraction.
* **Browser/E2E Testing (Phase 6):** Canvas rendering verification, Chart type toggle button DOM state updates, and export blob triggering will be validated during Phase 6 (End-to-End).
