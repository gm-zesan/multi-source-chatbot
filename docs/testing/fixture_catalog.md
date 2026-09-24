f# Fixture Catalog (Phase 2 & 3)

## 1. Excel Engine Deterministic Fixtures (`python-ai-service/tests/fixtures/excel/`)

| Filename | Description | Characteristics Tested |
| :--- | :--- | :--- |
| `01_single_sheet.xlsx` | Single sheet 'Products' table | Basic table creation, numeric multiplication & SUM |
| `02_multi_sheet.xlsx` | Two sheets: 'Sales' and 'Employees' | Multiple distinct virtual SQLite tables in same DB |
| `03_numeric_data.xlsx` | Floats, discounts, amounts | Floating point math, decimal precision |
| `04_dates.xlsx` | `order_date`, `delivery_date` | Date range filtering (`>= '2026-02-01'`) |
| `05_missing_values.xlsx` | Null names, missing phone numbers | `NULL` handling in columns, safe SQL COALESCE |
| `06_duplicate_rows.xlsx` | Repeated category rows | Grouping, `DISTINCT`, deduplication |
| `07_empty_sheet.xlsx` | Blank workbook sheet | Empty table handling without crash |
| `08_empty_columns.xlsx` | Columns with all nulls | All-null column type inference |
| `09_bangla.xlsx` | Bengali headers ('নাম', 'বিক্রি_পরিমাণ') | UTF-8 non-ASCII column & value ingestion |
| `10_english.xlsx` | Order numbers, statuses | String filtering and status counting |
| `11_mixed_language.xlsx` | Bilingual products ('Laptop Pro 15 (ল্যাপটপ)') | Mixed script Unicode integrity |
| `12_special_headers.xlsx` | Headers with spaces, slashes (`Product Name / Description`) | Column name sanitization to snake_case |
| `13_special_characters.xlsx` | `#`, `@`, `%`, `&` in headers | SQL injection / syntax safety in identifiers |
| `14_null_values.xlsx` | Sparse dataframe | `pd.isna` to SQLite `NULL` mapping |
| `15_large_fixture.xlsx` | 100 rows with SKUs and revenues | Large volume ingestion and multi-row aggregations |

---

## 2. Fuzzy Entity Resolver Fixture (`python-ai-service/tests/test_fuzzy_resolver.py`)

| Canonical Category | Entity Names / Values |
| :--- | :--- |
| **Salespersons** | `Hasan`, `Rakib`, `Tarek`, `Mehedi`, `Rahim`, `Rafiq`, `Karim` |
| **Customers** | `Alice Walker`, `Rahman Enterprise`, `Rahin Traders` |
| **Products** | `Laptop Pro 15`, `Laptop Air`, `Mechanical Keyboard RGB` |
| **Categories** | `Electronics`, `Accessories` |
| **Payment Methods** | `cash`, `bank`, `bkash`, `nagad`, `card`, `rocket` |
| **Statuses** | `completed`, `pending`, `cancelled` |

---

## 3. Router & Answerability Gate Fixtures (`multi-source-chatbot/tests/Unit/AI/`)

| Test Suite | Fixture Types |
| :--- | :--- |
| `HybridRouterTest.php` | Mock LLM JSON objects for 5 routes + security status payloads (`allowed`, `blocked_mutation`, `blocked_scope_override`) |
| `SemanticAnswerabilityGateTest.php` | Fake `FAQSearchResult` objects with scores ranging from 0.20 (unanswerable) to 0.95 (confident) |
| `ExportServiceTest.php` | Bilingual test matrices containing Bengali Unicode, quotes, commas, newlines, and currency symbols |
