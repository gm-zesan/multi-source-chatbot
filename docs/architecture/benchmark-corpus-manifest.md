# Benchmark Evaluation Corpus Specification & Manifest (Phase 2B)

**Status:** Proposed Benchmark Specification — Awaiting Milestone 2B Fixture Generation  
**Target Repository:** `/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot`  
**Location of Future Fixtures:** `tests/Datasets/KnowledgeExtraction/` (synthetic fixtures to be generated in Milestone 2B)  
**Author:** Senior Python Document-Processing Engineer & QA Architect  
**Date:** October 2026  

---

## 1. Overview and Synthetic Generation Principles

To rigorously evaluate and calibrate document extraction, structural parsing, OCR, and quality validation across unstructured enterprise knowledge documents without using proprietary customer documents or exposing real credentials, this specification defines a **55-Document Synthetic Benchmark Corpus**.

### Synthetic Generation Principles:
1. **Zero Proprietary Data**: All content is synthetic, modeled on typical Bangladeshi and international e-commerce and retail customer support documentation (return policies, shipping schedules, product warranties, sizing charts, payment terms).
2. **Ground-Truth Pairing**: Every synthetic document has a corresponding machine-readable ground-truth specification:
   - Canonical text string (UTF-8 normalized with NFC).
   - Structural tree (headings, lists, and table grid matrix).
   - Expected entity tokens (monetary values `৳`, phone numbers, dates, emails).
   - Expected Decision Gate classification (`success`, `success_with_warnings`, `needs_review`, `failed`).
3. **Provisional Metric Thresholds**: All numeric targets (e.g., $\text{CAR} \ge 99.5\%$, $\text{TEDS} \ge 95\%$) are explicitly labeled as **PROVISIONAL** until empirically calibrated against this suite.

---

## 2. Evaluation Metrics Definition

### 2.1 Character Accuracy Rate (CAR) & Word Error Rate (WER)
- **Levenshtein Distance**: Evaluated between the extracted text stream ($E$) and canonical ground-truth text ($G$) after Unicode NFC normalization and whitespace collapse:
  $$\text{CAR} = 1 - \frac{\text{LevenshteinDistance}(E, G)}{\max(\text{Length}(G), 1)}$$
  $$\text{WER} = \frac{\text{WordSubstitutions} + \text{WordDeletions} + \text{WordInsertions}}{\text{TotalWordsInGroundTruth}}$$
- **Provisional Targets**:
  - Native Digital Formats (PDF, DOCX, TXT, CSV): $\text{CAR} \ge 99.5\%$ `[Provisional]`
  - High-Quality Scans (300 DPI, clean background): $\text{CAR} \ge 92.0\%$ `[Provisional]`
  - Degraded / Skewed Scans (150 DPI, $\pm 5^\circ$ tilt): $\text{CAR} \ge 82.0\%$ `[Provisional]`
  - Bengali Complex Conjuncts & Ligatures: $\text{CAR} \ge 90.0\%$ `[Provisional]`

### 2.2 Table Structure Similarity: Tree Edit Distance-based Similarity (TEDS)
- Measures the accuracy of reconstructed tables compared to ground-truth HTML/matrix structure (evaluating row, column, and cell spans):
  $$\text{TEDS} = 1 - \frac{\text{TreeEditDistance}(T_{\text{extracted}}, T_{\text{ground\_truth}})}{\max(|T_{\text{extracted}}|, |T_{\text{ground\_truth}}|)}$$
- **Provisional Targets**:
  - Ruled / Bordered Tables: $\text{TEDS} \ge 95.0\%$ `[Provisional]`
  - Borderless / Whitespace-Separated Tables: $\text{TEDS} \ge 85.0\%$ `[Provisional]`

### 2.3 Numeric & Entity Precision / Recall
- Exact token matching for critical business entities:
  - Currency amounts: `৳ 1,500`, `Tk. 250`, `$ 49.99`, `BDT 3,200`.
  - Phone numbers: `+88017XXXXXXXX`, `018XXXXXXXX`.
  - Dates: `2026-10-14`, `14/10/2026`, `১৪ অক্টোবর ২০২৬`.
  - Percentages: `15%`, `7.5%`, `২৫%`.
- **Provisional Target**: $100\%$ precision on native formats; $\ge 98.0\%$ precision on OCR.

### 2.4 Quality Gate Decision Accuracy
- Measures whether the Quality Decision Model correctly routes documents to `success`, `success_with_warnings`, `needs_review`, or `failed`:
  $$\text{Decision Accuracy} = \frac{\text{Correctly Routed Documents}}{\text{Total Evaluated Documents}}$$
- **Target**: $100\%$ on adversarial and severe failure cases.

---

## 3. Comprehensive 55-Document Manifest

The corpus is organized into 11 distinct evaluation categories (CORPUS-01 through CORPUS-11), with 5 documents per category:

| Document ID | Format | Language | Key Characteristics & Layout Challenge | Expected Decision Gate | Target CAR `[Provisional]` |
|---|---|---|---|---|---|
| **CORPUS-01: Native Digital PDF (English Standard)** | | | | | |
| `BENCH-PDF-EN-01` | `.pdf` | English | Standard single-column return policy; headings, bulleted lists, contact info. | `success` | $\ge 99.8\%$ |
| `BENCH-PDF-EN-02` | `.pdf` | English | Multi-page terms of service (5 pages); headers/footers on every page. | `success` | $\ge 99.8\%$ |
| `BENCH-PDF-EN-03` | `.pdf` | English | Shipping rate guide with simple bordered table (zones, weights, rates). | `success` | $\ge 99.5\%$ |
| `BENCH-PDF-EN-04` | `.pdf` | English | FAQ sheet with Q&A formatting and hyperlinks. | `success` | $\ge 99.8\%$ |
| `BENCH-PDF-EN-05` | `.pdf` | English | Warranty policy with bold keywords and numeric date constraints. | `success` | $\ge 99.8\%$ |
| **CORPUS-02: Native Digital PDF (Bangla Standard)** | | | | | |
| `BENCH-PDF-BN-01` | `.pdf` | Bangla | Kalpurush font; customer refund policy with standard conjuncts (যুক্তবর্ণ). | `success` | $\ge 99.5\%$ |
| `BENCH-PDF-BN-02` | `.pdf` | Bangla | SolaimanLipi font; delivery timeline and courier tracking guidelines. | `success` | $\ge 99.5\%$ |
| `BENCH-PDF-BN-03` | `.pdf` | Bangla | Bengali monetary terms (`৳`, টাকা), Khanda-Ta (`ৎ`), and Yaphala (`্য`). | `success` | $\ge 99.5\%$ |
| `BENCH-PDF-BN-04` | `.pdf` | Bangla | Multi-page FAQ document (3 pages) with Bengali numbered lists (১, ২, ৩). | `success` | $\ge 99.5\%$ |
| `BENCH-PDF-BN-05` | `.pdf` | Bangla | Exchange policy with ZWNJ separating compound words (`রাম্` + ZWNJ + `এর`). | `success` | $\ge 99.5\%$ |
| **CORPUS-03: Native Digital PDF (Bilingual & Banglish)** | | | | | |
| `BENCH-PDF-MIX-01` | `.pdf` | En + Bn | Side-by-side bilingual warranty document (English left, Bangla right). | `success` | $\ge 99.0\%$ |
| `BENCH-PDF-MIX-02` | `.pdf` | Banglish | Product FAQ written in phonetic Banglish ("Delivery charge koto?", "Return policy ki?"). | `success` | $\ge 99.5\%$ |
| `BENCH-PDF-MIX-03` | `.pdf` | En + Bn | Code-switched retail policy ("Bkash payment korle 10% discount paben"). | `success` | $\ge 99.2\%$ |
| `BENCH-PDF-MIX-04` | `.pdf` | En + Bn | Product catalogue with English model names and Bengali specifications. | `success` | $\ge 99.2\%$ |
| `BENCH-PDF-MIX-05` | `.pdf` | En + Bn | Legal disclaimers with English statutory terms and Bengali customer rights. | `success` | $\ge 99.0\%$ |
| **CORPUS-04: Multi-Column & Complex Layout PDF** | | | | | |
| `BENCH-PDF-COL-01` | `.pdf` | English | Two-column newsletter format; reading order must not interweave columns. | `success` | $\ge 98.5\%$ |
| `BENCH-PDF-COL-02` | `.pdf` | Bangla | Three-column retail product brochure in Bengali; column isolation required. | `success` | $\ge 98.0\%$ |
| `BENCH-PDF-COL-03` | `.pdf` | Mixed | Multi-column layout with center sidebar containing customer support contacts. | `success` | $\ge 98.0\%$ |
| `BENCH-PDF-COL-04` | `.pdf` | Mixed | Magazine-style flyer with embedded callout boxes and footnotes. | `success` | $\ge 97.5\%$ |
| `BENCH-PDF-COL-05` | `.pdf` | Mixed | Asymmetric layout (1/3 sidebar navigation, 2/3 main body text). | `success` | $\ge 98.0\%$ |
| **CORPUS-05: Complex Tabular PDF** | | | | | |
| `BENCH-PDF-TAB-01` | `.pdf` | English | Ruled bordered table with merged column headers (Zone, City, 1-Day, 2-Day). | `success` | $\ge 95.0\%$ TEDS |
| `BENCH-PDF-TAB-02` | `.pdf` | Bangla | Bengali delivery charge matrix with currency symbols (`৳`) and numeric ranges. | `success` | $\ge 95.0\%$ TEDS |
| `BENCH-PDF-TAB-03` | `.pdf` | Mixed | Borderless whitespace-separated financial fee schedule; cell alignment test. | `success_with_warnings` | $\ge 85.0\%$ TEDS |
| `BENCH-PDF-TAB-04` | `.pdf` | Mixed | Multi-page table spanning across Page 1 and Page 2 with repeated header row. | `success` | $\ge 92.0\%$ TEDS |
| `BENCH-PDF-TAB-05` | `.pdf` | Mixed | Complex table with wrapped multiline text inside individual cells. | `success` | $\ge 90.0\%$ TEDS |
| **CORPUS-06: 100% Scanned / Image-Only PDF (OCR)** | | | | | |
| `BENCH-SCAN-01` | `.pdf` | English | High-res scan (300 DPI, clean contrast); typed English return agreement. | `success` | $\ge 94.0\%$ |
| `BENCH-SCAN-02` | `.pdf` | Bangla | High-res scan (300 DPI, clean contrast); typed Bengali warranty terms. | `success` | $\ge 92.0\%$ |
| `BENCH-SCAN-03` | `.pdf` | En + Bn | Mixed English/Bengali scan (300 DPI); merchant terms and conditions. | `success` | $\ge 90.0\%$ |
| `BENCH-SCAN-04` | `.pdf` | Mixed | Moderate resolution (200 DPI); minor background noise and paper texture. | `success_with_warnings` | $\ge 85.0\%$ |
| `BENCH-SCAN-05` | `.pdf` | Mixed | Skewed scan (150 DPI, $4^\circ$ rotation); deskewing pre-processing test. | `success_with_warnings` | $\ge 82.0\%$ |
| **CORPUS-07: Mixed Digital & Scanned PDF** | | | | | |
| `BENCH-MIX-PAGE-01` | `.pdf` | English | Page 1: Native digital text; Page 2: Scanned signature authorization page. | `success` | $\ge 96.0\%$ |
| `BENCH-MIX-PAGE-02` | `.pdf` | Bangla | Page 1: Digital Bengali policy; Page 2: Scanned stamp and voucher. | `success` | $\ge 94.0\%$ |
| `BENCH-MIX-PAGE-03` | `.pdf` | Mixed | 4-page PDF: Pages 1 & 3 digital, Pages 2 & 4 scanned image attachments. | `success` | $\ge 93.0\%$ |
| `BENCH-MIX-PAGE-04` | `.pdf` | Mixed | Page with small digital header and large scanned flyer body image. | `success` | $\ge 88.0\%$ |
| `BENCH-MIX-PAGE-05` | `.pdf` | Mixed | Document with 1 blank page between digital content (empty page skip test). | `success_with_warnings` | $\ge 98.0\%$ |
| **CORPUS-08: DOCX Structured Documents** | | | | | |
| `BENCH-DOCX-01` | `.docx` | English | Standard document with Heading 1, Heading 2, Heading 3 hierarchy and lists. | `success` | $\ge 99.9\%$ |
| `BENCH-DOCX-02` | `.docx` | Bangla | Bengali FAQ document with nested bullet points and bold/italic styles. | `success` | $\ge 99.9\%$ |
| `BENCH-DOCX-03` | `.docx` | Mixed | Embedded native Word table with styled headers, borders, and currency cells. | `success` | $\ge 98.0\%$ TEDS |
| `BENCH-DOCX-04` | `.docx` | Mixed | Multi-section document with distinct running headers and footers. | `success` | $\ge 99.5\%$ |
| `BENCH-DOCX-05` | `.docx` | Mixed | Large policy manual (15 pages) with table of contents and internal links. | `success` | $\ge 99.5\%$ |
| **CORPUS-09: TXT Multilingual & Encodings** | | | | | |
| `BENCH-TXT-01` | `.txt` | English | Clean UTF-8 encoded product FAQs and support policies. | `success` | $100.0\%$ |
| `BENCH-TXT-02` | `.txt` | Bangla | Clean UTF-8 encoded Bengali customer service responses with diacritics. | `success` | $100.0\%$ |
| `BENCH-TXT-03` | `.txt` | Mixed | UTF-8 with Byte Order Mark (BOM `\xef\xbb\xbf`); BOM strip verification. | `success` | $100.0\%$ |
| `BENCH-TXT-04` | `.txt` | English | Windows-1252 (CP1252) encoding with smart quotes, em-dashes, and symbols. | `success` | $\ge 99.5\%$ |
| `BENCH-TXT-05` | `.txt` | Mixed | UTF-16 Little Endian encoded multilingual policy file. | `success` | $\ge 99.5\%$ |
| **CORPUS-10: CSV Delimiters & Dialect Probe** | | | | | |
| `BENCH-CSV-01` | `.csv` | Mixed | Standard comma-separated (`,`) product pricing catalogue with header. | `success` | $100.0\%$ |
| `BENCH-CSV-02` | `.csv` | Mixed | Tab-separated (`\t`) delivery zip code matrix with Bengali district names. | `success` | $100.0\%$ |
| `BENCH-CSV-03` | `.csv` | Mixed | Semicolon-separated (`;`) fee schedule with commas inside quoted numeric cells. | `success` | $100.0\%$ |
| `BENCH-CSV-04` | `.csv` | Mixed | CSV containing multiline quoted cells (e.g. detailed return descriptions). | `success` | $\ge 99.5\%$ |
| `BENCH-CSV-05` | `.csv` | Mixed | Jagged/Inconsistent CSV (Row 4 has 5 columns, Row 1-3 have 4 columns). | `needs_review` | N/A (Flagged) |
| **CORPUS-11: Adversarial, Fault-Injection & Malformed Documents** | | | | | |
| `BENCH-ADV-01` | `.pdf` | N/A | Password-protected / encrypted PDF (no password provided). | `failed` | N/A (Clean Fail) |
| `BENCH-ADV-02` | `.pdf` | N/A | 0-byte empty file with `.pdf` extension. | `failed` | N/A (Clean Fail) |
| `BENCH-ADV-03` | `.pdf` | N/A | Truncated PDF (first 2 KB of a 1 MB file, missing EOF trailer). | `failed` | N/A (Clean Fail) |
| `BENCH-ADV-04` | `.docx` | N/A | Malformed zip archive (corrupted XML structure / zip bomb signature). | `failed` | N/A (Clean Fail) |
| `BENCH-ADV-05` | `.txt` | N/A | Binary executable or garbage bytes with high Mojibake ratio ($> 50\%$). | `failed` | N/A (Clean Fail) |

---

## 4. Ground-Truth Data Structure Specification

For each benchmark document, a paired JSON file in `tests/Datasets/KnowledgeExtraction/ground_truth/{id}.json` defines the expected extraction:

```json
{
  "document_id": "BENCH-PDF-MIX-01",
  "filename": "BENCH-PDF-MIX-01.pdf",
  "format": "pdf",
  "category": "CORPUS-03",
  "languages": ["en", "bn"],
  "total_pages": 2,
  "expected_decision_gate": "success",
  "ground_truth": {
    "sections": [
      {
        "heading": "Warranty Terms and Conditions / ওয়ারেন্টি নীতিমালা",
        "level": 1,
        "page": 1,
        "paragraphs": [
          "All electronic products carry a 1-year official replacement warranty. সমস্ত ইলেকট্রনিক পণ্যে ১ বছরের অফিসিয়াল রিপ্লেসমেন্ট ওয়ারেন্টি প্রযোজ্য।"
        ]
      }
    ],
    "tables": [
      {
        "page": 1,
        "caption": "Warranty Periods",
        "headers": ["Product Category", "Warranty Duration", "Coverage"],
        "rows": [
          ["Smartphones", "1 Year", "Parts & Service"],
          ["Accessories", "6 Months", "Service Only"]
        ]
      }
    ],
    "entities": {
      "currencies": ["৳ 1,500", "Tk 250"],
      "phones": ["+8801700000000"],
      "dates": ["2026-10-14"]
    }
  },
  "provisional_thresholds": {
    "min_car": 0.99,
    "min_teds": 0.95,
    "max_wer": 0.02
  }
}
```

---

## 5. Execution Workflow in Milestone 2B

When Milestone 2B is executed:
1. Synthetic generator script (`tests/Datasets/KnowledgeExtraction/generate_benchmark_fixtures.py`) synthesizes all 55 documents into `tests/Datasets/KnowledgeExtraction/fixtures/`.
2. Ground-truth JSON manifests are generated simultaneously.
3. The benchmark evaluation runner executes each candidate parser (e.g. `pypdfium2` vs `PyMuPDF`, CAND-120 vs CAND-256) against the suite.
4. An automated report outputs exact CAR, WER, TEDS, latency, and peak memory for each category.
5. Operational thresholds in the Quality Validation Model are finalized based on the empirical distributions.
