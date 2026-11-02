<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet;

use App\Models\KnowledgeDocument;
use App\Services\Spreadsheet\DTOs\SourceColumnDescriptor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetInspectorService
{
    /**
     * Supported file extensions strictly limited per product scope (AC-01, REQ-02).
     */
    public const SUPPORTED_EXTENSIONS = ['xlsx', 'xls', 'csv'];

    /**
     * Supported MIME types.
     */
    public const SUPPORTED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain',
        'application/csv',
        'text/x-csv',
        'application/octet-stream',
    ];

    /**
     * Configurable safety limits.
     */
    public const DEFAULT_MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB
    public const DEFAULT_MAX_SHEETS = 15;
    public const DEFAULT_MAX_COLUMNS = 150;
    public const DEFAULT_MAX_ROWS_PER_SHEET = 100000;
    public const HEADER_CANDIDATE_SCAN_LIMIT = 15;

    public const CONTRACT_VERSION = '2.1.0';

    /**
     * Physical Data Type Vocabulary.
     */
    public const PHYSICAL_TYPE_STRING = 'string';
    public const PHYSICAL_TYPE_INTEGER = 'integer';
    public const PHYSICAL_TYPE_DECIMAL = 'decimal';
    public const PHYSICAL_TYPE_BOOLEAN = 'boolean';
    public const PHYSICAL_TYPE_DATE = 'date';
    public const PHYSICAL_TYPE_DATETIME = 'datetime';
    public const PHYSICAL_TYPE_TIME = 'time';
    public const PHYSICAL_TYPE_PERCENTAGE = 'percentage';
    public const PHYSICAL_TYPE_CURRENCY = 'currency';
    public const PHYSICAL_TYPE_IDENTIFIER = 'identifier';
    public const PHYSICAL_TYPE_UNKNOWN = 'unknown';

    /**
     * Sensitivity Classifications.
     */
    public const SENSITIVITY_PUBLIC = 'public';
    public const SENSITIVITY_INTERNAL = 'internal';
    public const SENSITIVITY_RESTRICTED = 'restricted';

    /**
     * Generic Semantic Mapping Categories.
     */
    public const CATEGORY_ENTITY_NAME = 'entity_name';
    public const CATEGORY_IDENTIFIER = 'identifier';
    public const CATEGORY_DESCRIPTION = 'description';
    public const CATEGORY_DATE_TIME = 'date_time';
    public const CATEGORY_AMOUNT = 'amount';
    public const CATEGORY_QUANTITY = 'quantity';
    public const CATEGORY_STATUS = 'status';
    public const CATEGORY_CATEGORY = 'category';
    public const CATEGORY_PERSON_REFERENCE = 'person_reference';
    public const CATEGORY_PERSON = self::CATEGORY_PERSON_REFERENCE;
    public const CATEGORY_CONTACT_INFO = 'contact_info';
    public const CATEGORY_CONTACT = self::CATEGORY_CONTACT_INFO;
    public const CATEGORY_CUSTOM_ATTRIBUTE = 'custom_attribute';

    /**
     * Validate an uploaded file against file format, signature, size, and corruption boundaries.
     *
     * @return array{valid: bool, error: ?string, extension: string, mime_type: string, file_size: int}
     */
    public function validateFile(string $absolutePath, string $originalFilename): array
    {
        if (! file_exists($absolutePath) || ! is_readable($absolutePath)) {
            return [
                'valid'     => false,
                'error'     => 'The uploaded file does not exist or is not readable on disk.',
                'extension' => '',
                'mime_type' => '',
                'file_size' => 0,
            ];
        }

        $fileSize = filesize($absolutePath) ?: 0;
        if ($fileSize === 0) {
            return [
                'valid'     => false,
                'error'     => 'The uploaded spreadsheet file is empty (0 bytes).',
                'extension' => '',
                'mime_type' => '',
                'file_size' => 0,
            ];
        }

        if ($fileSize > self::DEFAULT_MAX_FILE_SIZE_BYTES) {
            return [
                'valid'     => false,
                'error'     => sprintf('File size (%s MB) exceeds the maximum allowed limit of %s MB.', round($fileSize / (1024 * 1024), 2), self::DEFAULT_MAX_FILE_SIZE_BYTES / (1024 * 1024)),
                'extension' => '',
                'mime_type' => '',
                'file_size' => $fileSize,
            ];
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            return [
                'valid'     => false,
                'error'     => sprintf('Unsupported file extension (.%s). Strictly supported formats are: .xlsx, .xls, .csv', $extension),
                'extension' => $extension,
                'mime_type' => '',
                'file_size' => $fileSize,
            ];
        }

        // Magic byte signature validation
        $handle = fopen($absolutePath, 'rb');
        if (! $handle) {
            return [
                'valid'     => false,
                'error'     => 'Unable to read file headers for security verification.',
                'extension' => $extension,
                'mime_type' => '',
                'file_size' => $fileSize,
            ];
        }

        $headerBytes = fread($handle, 8);
        fclose($handle);

        if ($extension === 'xlsx') {
            // ZIP / OpenXML signature: PK\x03\x04 or PK\x05\x06 or PK\x07\x08
            if (! str_starts_with($headerBytes, "\x50\x4B\x03\x04") && ! str_starts_with($headerBytes, "\x50\x4B\x05\x06")) {
                return [
                    'valid'     => false,
                    'error'     => 'File extension is .xlsx but binary signature does not match a valid OpenXML/ZIP spreadsheet container (MIME spoofing detected).',
                    'extension' => $extension,
                    'mime_type' => 'invalid/corrupt-xlsx',
                    'file_size' => $fileSize,
                ];
            }
        } elseif ($extension === 'xls') {
            // Compound File Binary Format: \xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1
            if (! str_starts_with($headerBytes, "\xD0\xCF\x11\xE0")) {
                return [
                    'valid'     => false,
                    'error'     => 'File extension is .xls but binary signature does not match a valid legacy Microsoft Excel workbook.',
                    'extension' => $extension,
                    'mime_type' => 'invalid/corrupt-xls',
                    'file_size' => $fileSize,
                ];
            }
        } elseif ($extension === 'csv') {
            // CSV is plain text. Check encoding and ensure it's not binary executable.
            if (str_contains($headerBytes, "\x00\x00") || str_starts_with($headerBytes, "MZ")) {
                return [
                    'valid'     => false,
                    'error'     => 'File extension is .csv but binary executable or null bytes were detected.',
                    'extension' => $extension,
                    'mime_type' => 'invalid/binary-csv',
                    'file_size' => $fileSize,
                ];
            }
        }

        // Test parser compatibility to catch malformed XML, corrupt zips, or password-protected sheets
        try {
            if ($extension === 'csv') {
                $this->testCsvRead($absolutePath);
            } else {
                $reader = IOFactory::createReaderForFile($absolutePath);
                $reader->setReadDataOnly(true);
                $sheets = $reader->listWorksheetNames($absolutePath);
                if (empty($sheets)) {
                    return [
                        'valid'     => false,
                        'error'     => 'The spreadsheet contains no worksheets or valid data tables.',
                        'extension' => $extension,
                        'mime_type' => 'application/vnd.ms-excel',
                        'file_size' => $fileSize,
                    ];
                }
                if (count($sheets) > self::DEFAULT_MAX_SHEETS) {
                    return [
                        'valid'     => false,
                        'error'     => sprintf('Spreadsheet workbook contains %d sheets, exceeding the maximum allowed limit of %d.', count($sheets), self::DEFAULT_MAX_SHEETS),
                        'extension' => $extension,
                        'mime_type' => 'application/vnd.ms-excel',
                        'file_size' => $fileSize,
                    ];
                }
            }
        } catch (\Throwable $e) {
            return [
                'valid'     => false,
                'error'     => 'The spreadsheet file is corrupt, password-protected, or malformed: ' . $e->getMessage(),
                'extension' => $extension,
                'mime_type' => 'corrupt/unreadable',
                'file_size' => $fileSize,
            ];
        }

        return [
            'valid'     => true,
            'error'     => null,
            'extension' => $extension,
            'mime_type' => $extension === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size' => $fileSize,
        ];
    }

    /**
     * Inspect a spreadsheet and extract comprehensive structure, sheet list, header candidates,
     * column data types, and semantic mapping suggestions.
     *
     * @return array<string, mixed>
     */
    public function inspectWorkbook(string $absolutePath, string $originalFilename, ?string $targetSheet = null): array
    {
        $validation = $this->validateFile($absolutePath, $originalFilename);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'error'   => $validation['error'],
                'sheets'  => [],
            ];
        }

        $extension = $validation['extension'];

        if ($extension === 'csv') {
            return $this->inspectCsv($absolutePath, $originalFilename);
        }

        return $this->inspectExcel($absolutePath, $originalFilename, $targetSheet);
    }

    /**
     * Inspect CSV file.
     *
     * @return array<string, mixed>
     */
    private function inspectCsv(string $absolutePath, string $originalFilename): array
    {
        $rawRows = [];
        $encoding = $this->detectEncoding($absolutePath);

        $handle = fopen($absolutePath, 'r');
        if (! $handle) {
            return [
                'success' => false,
                'error'   => 'Failed to open CSV file for inspection.',
                'sheets'  => [],
            ];
        }

        $scanCount = 0;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false && $scanCount < 200) {
            // Normalize encoding to UTF-8
            $cleaned = [];
            foreach ($row as $cell) {
                $cellStr = is_string($cell) ? $cell : (string) ($cell ?? '');
                if ($encoding !== 'UTF-8' && $encoding !== 'ASCII') {
                    $cellStr = mb_convert_encoding($cellStr, 'UTF-8', $encoding);
                }
                $cleaned[] = trim($cellStr);
            }
            $rawRows[] = $cleaned;
            $scanCount++;
        }
        fclose($handle);

        $sheetInspection = $this->analyzeSheetData($rawRows, 'Default');

        return [
            'success'          => true,
            'filename'         => $originalFilename,
            'extension'        => 'csv',
            'sheets'           => ['Default'],
            'active_sheet'     => 'Default',
            'sheets_count'     => 1,
            'total_rows'       => $this->countCsvRows($absolutePath) - max(1, $sheetInspection['header_row_index'] + 1),
            'inspection'       => $sheetInspection,
        ];
    }

    /**
     * Inspect Excel (XLSX / XLS) file.
     *
     * @return array<string, mixed>
     */
    private function inspectExcel(string $absolutePath, string $originalFilename, ?string $targetSheet = null): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);

        $sheets = $reader->listWorksheetNames($absolutePath);
        if (empty($sheets)) {
            $sheets = ['Sheet1'];
        }

        $activeSheet = ($targetSheet !== null && in_array($targetSheet, $sheets, true))
            ? $targetSheet
            : $sheets[0];

        $reader->setLoadSheetsOnly([$activeSheet]);
        $spreadsheet = $reader->load($absolutePath);
        $worksheet = $spreadsheet->getSheetByName($activeSheet) ?? $spreadsheet->getActiveSheet();

        $highestRow = min((int) $worksheet->getHighestRow(), 200);
        $highestColumn = $worksheet->getHighestColumn();
        $totalEstimatedRows = (int) $worksheet->getHighestRow();

        $sheetData = $worksheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, true, true, false);

        $rawRows = [];
        foreach ($sheetData as $row) {
            $formattedRow = [];
            foreach ($row as $cell) {
                $formattedRow[] = trim(is_scalar($cell) ? (string) $cell : '');
            }
            $rawRows[] = $formattedRow;
        }

        $sheetInspection = $this->analyzeSheetData($rawRows, $activeSheet);

        return [
            'success'            => true,
            'filename'           => $originalFilename,
            'extension'          => strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION)),
            'sheets'             => $sheets,
            'active_sheet'       => $activeSheet,
            'sheets_count'       => count($sheets),
            'total_rows'         => max(0, $totalEstimatedRows - max(1, $sheetInspection['header_row_index'] + 1)),
            'inspection'         => $sheetInspection,
        ];
    }

    /**
     * Analyze raw 2D array of rows to identify candidate header rows, data types, and mapping proposals.
     *
     * @param array<int, array<int, string>> $rows
     * @return array<string, mixed>
     */
    public function analyzeSheetData(array $rows, string $sheetName = 'Sheet1'): array
    {
        if (empty($rows)) {
            return [
                'sheet_name'         => $sheetName,
                'header_row_index'   => 0,
                'is_ambiguous'       => false,
                'headers'            => [],
                'candidate_headers'  => [],
                'columns'            => [],
                'sample_rows'        => [],
                'detected_purpose'   => 'structured_data',
            ];
        }

        // 1. Candidate Header Row Evaluation (Scan first N rows)
        $candidates = $this->findCandidateHeaderRows($rows);
        $selectedCandidate = ! empty($candidates) ? $candidates[0] : ['index' => 0, 'confidence' => 0.5, 'headers' => []];
        $headerIndex = $selectedCandidate['index'];
        $headers = $selectedCandidate['headers'];

        // Determine ambiguity: if runner-up candidate is very close in confidence
        $isAmbiguous = false;
        if (count($candidates) > 1 && $candidates[0]['confidence'] < 0.85) {
            $margin = $candidates[0]['confidence'] - $candidates[1]['confidence'];
            if ($margin < 0.15) {
                $isAmbiguous = true;
            }
        }

        // 2. Extract Data Rows below Header
        $dataRows = array_slice($rows, $headerIndex + 1);

        // 3. Column Profiling & Semantic Mapping
        $columns = [];
        $sourceDescriptors = [];
        $sampleRows = [];

        foreach ($headers as $colIdx => $headerName) {
            $colValues = [];
            foreach ($dataRows as $r) {
                if (isset($r[$colIdx])) {
                    $colValues[] = $r[$colIdx];
                }
            }

            $profile = $this->profileColumn($headerName, $colValues, $colIdx);
            $mappingSuggestion = $this->suggestMapping($headerName, $profile);

            $sourceColId = 'col_' . str_pad((string) $colIdx, 3, '0', STR_PAD_LEFT);
            $normalizedHeader = Str::snake(Str::ascii($headerName));
            if ($normalizedHeader === '') {
                $normalizedHeader = "column_{$colIdx}";
            }

            $descriptor = new SourceColumnDescriptor(
                sourceColumnId: $sourceColId,
                originalHeader: $headerName,
                normalizedHeader: $normalizedHeader,
                sheetName: $sheetName,
                columnIndex: $colIdx,
                physicalType: $mappingSuggestion['physical_type'] ?? 'string',
                suggestedCategory: $mappingSuggestion['category'],
                semanticRole: $mappingSuggestion['semantic_role'],
                candidateRoles: $mappingSuggestion['candidate_roles'] ?? [],
                entityType: $mappingSuggestion['entity_type'] ?? 'unknown',
                unit: $profile['currency_symbol'] ?? ($mappingSuggestion['unit'] ?? null),
                sensitivity: $mappingSuggestion['sensitivity'] ?? 'restricted',
                confidence: $mappingSuggestion['confidence'],
                evidence: [$mappingSuggestion['evidence']],
                isAmbiguous: $mappingSuggestion['is_ambiguous'],
                mappingSource: 'inferred',
                ownerConfirmed: false,
            );

            $sourceDescriptors[$sourceColId] = $descriptor->toArray();

            $columns[] = [
                'source_column_id'   => $sourceColId,
                'index'              => $colIdx,
                'name'               => $headerName,
                'normalized_name'    => $normalizedHeader,
                'inferred_type'      => $profile['type'],
                'physical_type'      => $mappingSuggestion['physical_type'] ?? 'string',
                'currency_symbol'    => $profile['currency_symbol'],
                'non_empty_ratio'    => $profile['non_empty_ratio'],
                'sample_values'      => array_slice($profile['distinct_samples'], 0, 5),
                'suggested_category' => $mappingSuggestion['category'],
                'semantic_role'      => $mappingSuggestion['semantic_role'],
                'candidate_roles'    => $mappingSuggestion['candidate_roles'] ?? [],
                'entity_type'        => $mappingSuggestion['entity_type'] ?? 'unknown',
                'unit'               => $profile['currency_symbol'] ?? ($mappingSuggestion['unit'] ?? null),
                'sensitivity'        => $mappingSuggestion['sensitivity'] ?? 'restricted',
                'confidence'         => $mappingSuggestion['confidence'],
                'evidence'           => $mappingSuggestion['evidence'],
                'is_ambiguous'       => $mappingSuggestion['is_ambiguous'],
                'options'            => $mappingSuggestion['competing_categories'],
            ];
        }

        // Prepare first 5 non-empty sample rows for UI display
        $count = 0;
        foreach ($dataRows as $row) {
            $hasVal = false;
            foreach ($row as $c) {
                if ($c !== '') {
                    $hasVal = true;
                    break;
                }
            }
            if ($hasVal) {
                $sampleRow = [];
                foreach ($headers as $colIdx => $h) {
                    $raw = $row[$colIdx] ?? '';
                    $sampleRow[$h] = $this->sanitizeCellForDisplay($raw);
                }
                $sampleRows[] = $sampleRow;
                $count++;
                if ($count >= 5) {
                    break;
                }
            }
        }

        // Detect overall sheet purpose (Knowledge vs Structured Data)
        // Detect overall schema ambiguity from header selection or column confidence
        if (! $isAmbiguous) {
            foreach ($columns as $c) {
                if (! empty($c['is_ambiguous']) || (float) ($c['confidence'] ?? 0.0) < 0.65) {
                    $isAmbiguous = true;
                    break;
                }
            }
        }

        $detectedPurpose = $this->detectSheetPurpose($headers, $columns);

        return [
            'sheet_name'         => $sheetName,
            'header_row_index'   => $headerIndex,
            'is_ambiguous'       => $isAmbiguous,
            'headers'            => $headers,
            'candidate_headers'  => $candidates,
            'columns'            => $columns,
            'source_descriptors' => $sourceDescriptors,
            'sample_rows'        => $sampleRows,
            'detected_purpose'   => $detectedPurpose,
            'contract_version'   => self::CONTRACT_VERSION,
        ];
    }

    /**
     * Find candidate header rows by scoring rows in the first N lines.
     *
     * @param array<int, array<int, string>> $rows
     * @return array<int, array{index: int, headers: array<int, string>, confidence: float, reason: string}>
     */
    public function findCandidateHeaderRows(array $rows): array
    {
        $candidates = [];
        $maxScan = min(count($rows), self::HEADER_CANDIDATE_SCAN_LIMIT);

        for ($i = 0; $i < $maxScan; $i++) {
            $row = $rows[$i];
            $nonEmpty = array_values(array_filter($row, fn ($c) => trim((string) $c) !== ''));

            if (empty($nonEmpty)) {
                continue; // Skip entirely blank rows
            }

            // Criteria 1: Cell Density (Number of populated cells)
            $density = count($nonEmpty) / max(1, count($row));

            // Single merged banner or single cell on row is likely a Title, not column headers
            if (count($nonEmpty) === 1 && count($row) > 1) {
                continue;
            }

            // Criteria 2: Text / String quality (headers are names, not pure dates or floating numbers)
            $stringCount = 0;
            $numericCount = 0;
            $dateCount = 0;

            foreach ($nonEmpty as $val) {
                if ($this->isDateString($val)) {
                    $dateCount++;
                } elseif ($this->isNumericString($val)) {
                    $numericCount++;
                } else {
                    $stringCount++;
                }
            }

            $stringRatio = $stringCount / count($nonEmpty);

            // Criteria 3: Distinctness (Header names should be unique)
            $uniqueCount = count(array_unique(array_map('mb_strtolower', $nonEmpty)));
            $distinctRatio = $uniqueCount / count($nonEmpty);

            // Criteria 4: Lookahead Data Consistency (Rows following the header should contain values matching types)
            $lookaheadBonus = 0.0;
            if ($i + 1 < count($rows)) {
                $nextRow = array_values(array_filter($rows[$i + 1], fn ($c) => trim((string) $c) !== ''));
                if (! empty($nextRow)) {
                    $lookaheadBonus = 0.15;
                }
            }

            // Criteria 5: Keyword boost (Presence of common header terms: Name, Price, Date, Code, etc.)
            $headerKeywordMatches = 0;
            foreach ($nonEmpty as $col) {
                if ($this->isRecognizedHeaderTerm($col)) {
                    $headerKeywordMatches++;
                }
            }
            $keywordBonus = min(0.30, ($headerKeywordMatches / max(1, count($nonEmpty))) * 0.40);

            // Base Score Calculation
            $score = ($stringRatio * 0.40) + ($distinctRatio * 0.20) + ($density * 0.15) + $lookaheadBonus + $keywordBonus;

            // Penalty for pure numbers/dates in headers
            if ($numericCount > $stringCount) {
                $score -= 0.35;
            }
            if ($dateCount > 0) {
                $score -= 0.20;
            }

            // Normalize confidence 0.0 - 1.0
            $confidence = round(max(0.10, min(0.99, $score)), 4);

            // Build clean header names (guaranteeing non-empty unique names)
            $cleanHeaders = [];
            $seenNames = [];
            foreach ($row as $colIdx => $rawName) {
                $hName = trim((string) $rawName);
                if ($hName === '') {
                    $hName = 'Column_' . ($colIdx + 1);
                }
                if (isset($seenNames[$hName])) {
                    $seenNames[$hName]++;
                    $hName = "{$hName}_{$seenNames[$hName]}";
                } else {
                    $seenNames[$hName] = 0;
                }
                $cleanHeaders[] = $hName;
            }

            $reason = sprintf(
                'Density: %d%%, Text Ratio: %d%%, Distinctness: %d%%, Keywords: %d',
                round($density * 100),
                round($stringRatio * 100),
                round($distinctRatio * 100),
                $headerKeywordMatches
            );

            $candidates[] = [
                'index'      => $i,
                'headers'    => $cleanHeaders,
                'confidence' => $confidence,
                'reason'     => $reason,
            ];
        }

        // Sort candidates by confidence descending
        usort($candidates, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $candidates;
    }

    /**
     * Profile column values to infer underlying data type, presence of currency symbols, and statistics.
     *
     * @param array<int, string> $values
     * @return array<string, mixed>
     */
    public function profileColumn(string $header, array $values, int $index): array
    {
        $nonEmpty = array_values(array_filter($values, fn ($v) => trim((string) $v) !== ''));
        $totalCount = count($values);
        $nonEmptyCount = count($nonEmpty);
        $nonEmptyRatio = $totalCount > 0 ? round($nonEmptyCount / $totalCount, 4) : 0.0;

        if ($nonEmptyCount === 0) {
            return [
                'type'             => 'string',
                'currency_symbol'  => null,
                'non_empty_ratio'  => 0.0,
                'distinct_samples' => [],
            ];
        }

        $currencySymbols = ['৳', 'Tk', 'BDT', '$', 'USD', '€', 'EUR', '£', 'GBP', '₹', 'INR'];
        $currencyMatches = [];
        $numericCount = 0;
        $integerCount = 0;
        $dateCount = 0;
        $booleanCount = 0;
        $percentageCount = 0;
        $distinctSamples = [];

        foreach ($nonEmpty as $val) {
            $v = trim((string) $val);

            if (! in_array($v, $distinctSamples, true) && count($distinctSamples) < 5) {
                $distinctSamples[] = $v;
            }

            // Currency detection
            foreach ($currencySymbols as $sym) {
                if (stripos($v, $sym) !== false) {
                    $currencyMatches[$sym] = ($currencyMatches[$sym] ?? 0) + 1;
                    break;
                }
            }

            // Percentage
            if (str_ends_with($v, '%') && $this->isNumericString(substr($v, 0, -1))) {
                $percentageCount++;
                continue;
            }

            // Boolean
            if (in_array(strtolower($v), ['true', 'false', 'yes', 'no', '1', '0', 'হ্যাঁ', 'না'], true)) {
                $booleanCount++;
            }

            // Clean currency symbols and commas for numeric evaluation
            $cleanedNum = $this->cleanNumericString($v);
            if ($this->isNumericString($cleanedNum)) {
                $numericCount++;
                if (ctype_digit($cleanedNum) || (is_numeric($cleanedNum) && (int) $cleanedNum == (float) $cleanedNum)) {
                    $integerCount++;
                }
            } elseif ($this->isDateString($v)) {
                $dateCount++;
            }
        }

        $detectedCurrency = null;
        if (! empty($currencyMatches)) {
            arsort($currencyMatches);
            $detectedCurrency = array_key_first($currencyMatches);
        }

        // Determine dominant type
        $type = 'string';
        if ($percentageCount / $nonEmptyCount >= 0.70) {
            $type = 'percentage';
        } elseif ($detectedCurrency !== null || ($numericCount / $nonEmptyCount >= 0.80 && $this->isHeaderSuggestingAmount($header))) {
            $type = 'currency';
        } elseif ($numericCount / $nonEmptyCount >= 0.85) {
            $type = ($integerCount / $nonEmptyCount >= 0.85) ? 'integer' : 'float';
        } elseif ($dateCount / $nonEmptyCount >= 0.70) {
            $type = 'date';
        } elseif ($booleanCount / $nonEmptyCount >= 0.85) {
            $type = 'boolean';
        }

        return [
            'type'             => $type,
            'currency_symbol'  => $detectedCurrency,
            'non_empty_ratio'  => $nonEmptyRatio,
            'distinct_samples' => $distinctSamples,
        ];
    }

    /**
     * Suggest generic semantic category mapping for a column based on header text and data profile.
     *
     * @param array<string, mixed> $profile
     * @return array{category: string, confidence: float, evidence: string, is_ambiguous: bool, competing_categories: array<string>}
     */
    public function suggestMapping(string $header, array $profile): array
    {
        $rawH = mb_strtolower(trim($header));
        $hNorm = preg_replace('/[\s\-]+/u', '_', $rawH);
        $h = $rawH . ' ' . $hNorm;
        $type = $profile['type'] ?? 'string';

        $scores = [
            self::CATEGORY_ENTITY_NAME      => 0.0,
            self::CATEGORY_IDENTIFIER       => 0.0,
            self::CATEGORY_DESCRIPTION      => 0.0,
            self::CATEGORY_DATE_TIME        => 0.0,
            self::CATEGORY_AMOUNT           => 0.0,
            self::CATEGORY_QUANTITY         => 0.0,
            self::CATEGORY_STATUS           => 0.0,
            self::CATEGORY_CATEGORY         => 0.0,
            self::CATEGORY_PERSON_REFERENCE => 0.0,
            self::CATEGORY_CONTACT_INFO     => 0.0,
            self::CATEGORY_CUSTOM_ATTRIBUTE => 0.20, // Baseline floor
        ];

        // 1. Amount / Price / Financial
        if (preg_match('/\b(price|cost|rate|amount|fee|charge|charges|fare|rent|rental|salary|wage|total|subtotal|balance|due|commission|revenue|profit|turnover|net|gross)\b/i', $h)
            || preg_match('/(দাম|মূল্য|টাকা|ফি|খরচ|বেতন|কমিশন|ব্যয়|আয়|লাভ|মুনাফা|মোট|ভাড়া)/u', $h)) {
            $scores[self::CATEGORY_AMOUNT] += 0.65;
        }
        if ($type === 'currency') {
            $scores[self::CATEGORY_AMOUNT] += 0.30;
        } elseif ($type === 'float' || $type === 'integer') {
            $scores[self::CATEGORY_AMOUNT] += 0.15;
            $scores[self::CATEGORY_QUANTITY] += 0.15;
        }

        // 2. Quantity / Stock / Units
        if (preg_match('/\b(qty|quantity|stock|units|count|pieces|pcs|volume)\b/i', $h)
            || preg_match('/(স্টক|পরিমাণ|সংখ্যা|পিস|একক)/u', $h)) {
            $scores[self::CATEGORY_QUANTITY] += 0.70;
        }

        $personRegex = '/\b(salesman|salesperson|sales_person|salesrep|sales_rep|rep|employee|worker|staff|customer|client|patient|student|doctor|physician|surgeon|consultant|dr|agent|seller|buyer|person|representative|guest|visitor|donor|beneficiary|cleaner|janitor|driver|user)\b/i';
        $personBnRegex = '/(কর্মী|কর্মচারী|সেলসম্যান|বিক্রেতা|গ্রাহক|ক্রেতা|ডাক্তার|ডাঃ|চিকিৎসক|রোগী|পেশেন্ট|শিক্ষার্থী|প্রতিনিধি|মেহমান|অতিথি|দাতা|উপকারভোগী)/u';

        // 3. Entity Name / Title / Product
        if (preg_match('/\b(product|item|title|name|service|course|item_name|product_name|goods)\b/i', $h)
            || preg_match('/(পণ্যের\s*নাম|পণ্য|আইটেম|নাম|শিরোনাম|সেবা)/u', $h)) {
            if (! preg_match($personRegex, $h) && ! preg_match($personBnRegex, $h)) {
                $scores[self::CATEGORY_ENTITY_NAME] += 0.75;
            }
        }

        // 4. Identifier / SKU / Code / Invoice
        if (preg_match('/\b(sku|code|id|identifier|barcode|qr|uuid|order_id|invoice|roll|roll_no|trx_id|transaction_id|item_code|room_no|room_number|suite)\b/i', $h)
            || preg_match('/(কোড|আইডি|এসকেইউ|ইনভয়েস|অর্ডার\s*নম্বর|রোল|টোকেন|রুম\s*নম্বর)/u', $h)) {
            $scores[self::CATEGORY_IDENTIFIER] += 0.85;
        }

        // 5. Date / Time
        if (preg_match('/\b(date|time|timestamp|created_at|updated_at|day|month|year|dob|datetime)\b/i', $h)
            || preg_match('/(তারিখ|সময়|দিন|মাস|সাল|বছর)/u', $h)) {
            $scores[self::CATEGORY_DATE_TIME] += 0.70;
        }
        if ($type === 'date') {
            $scores[self::CATEGORY_DATE_TIME] += 0.30;
        }

        // 6. Person Reference (Employee, Salesman, Customer, Doctor, Guest, Donor, Beneficiary)
        if ((preg_match($personRegex, $h) || preg_match($personBnRegex, $h))
            && ! preg_match('/\b(id|code|identifier|uuid|barcode|qr|no|number)\b/i', $h)
            && ! preg_match('/(আইডি|কোড|নম্বর)/u', $h)) {
            $scores[self::CATEGORY_PERSON_REFERENCE] += 0.85;
        }

        // 7. Contact Information (Phone, Email, Address, Mobile)
        if (preg_match('/\b(phone|mobile|cell|tel|telephone|email|mail|address|location|city|country|zip|postcode)\b/i', $h)
            || preg_match('/(ফোন|মোবাইল|ইমেইল|ঠিকানা|শহর|জেলা)/u', $h)) {
            $scores[self::CATEGORY_CONTACT_INFO] += 0.80;
        }

        // 8. Description / Notes / Specs
        if (preg_match('/\b(desc|description|details|specs|specification|summary|notes|remarks|comment)\b/i', $h)
            || preg_match('/(বিবরণ|বর্ণনা|মন্তব্য|নোট|বিস্তারিত)/u', $h)) {
            $scores[self::CATEGORY_DESCRIPTION] += 0.75;
        }

        // 9. Status / Condition / State
        if (preg_match('/\b(status|state|condition|is_active|payment_status|delivery_status|order_status)\b/i', $h)
            || preg_match('/(স্ট্যাটাস|অবস্থা|ধরণ)/u', $h)) {
            $scores[self::CATEGORY_STATUS] += 0.75;
        }

        // 10. Category / Type / Department / Brand
        if (preg_match('/\b(category|group|type|department|dept|brand|genre|class|division)\b/i', $h)
            || preg_match('/(ক্যাটাগরি|বিভাগ|গ্রুপ|ব্র্যান্ড|শ্রেণী)/u', $h)) {
            $scores[self::CATEGORY_CATEGORY] += 0.75;
        }

        arsort($scores);
        $topCategory = array_key_first($scores);
        $topScore = $scores[$topCategory];

        // Normalization
        $finalConfidence = round(min(0.99, $topScore), 4);

        // Check if top is close to second
        $competing = [];
        $isAmbiguous = false;
        $scoresList = array_values($scores);
        $categoriesList = array_keys($scores);

        if (count($scoresList) > 1 && $scoresList[0] > 0.35) {
            if (($scoresList[0] - $scoresList[1]) < 0.15 && $scoresList[1] > 0.35) {
                $isAmbiguous = true;
                $competing = [$categoriesList[0], $categoriesList[1]];
            }
        }

        // If confidence is low, fallback to custom attribute
        if ($finalConfidence < 0.40) {
            $topCategory = self::CATEGORY_CUSTOM_ATTRIBUTE;
            $finalConfidence = 0.50;
            $isAmbiguous = false;
        }

        $evidence = sprintf('Header pattern & type [%s] scored %d%%', $type, round($finalConfidence * 100));

        $semanticDetails = $this->inferSemanticDetails($header, $profile, $topCategory, $finalConfidence);

        return [
            'category'             => $topCategory,
            'confidence'           => $finalConfidence,
            'evidence'             => $evidence,
            'is_ambiguous'         => $isAmbiguous || ($semanticDetails['is_ambiguous'] ?? false),
            'competing_categories' => $competing,
            'physical_type'        => $semanticDetails['physical_type'],
            'semantic_role'        => $semanticDetails['semantic_role'],
            'candidate_roles'      => $semanticDetails['candidate_roles'],
            'entity_type'          => $semanticDetails['entity_type'],
            'unit'                 => $semanticDetails['unit'],
            'sensitivity'          => $semanticDetails['sensitivity'],
        ];
    }

    /**
     * Infer detailed semantic role, entity type, sensitivity, and alternative candidate roles.
     *
     * @param array<string, mixed> $profile
     * @return array{
     *     physical_type: string,
     *     semantic_role: string,
     *     candidate_roles: array<array{role: string, confidence: float, entity: string, sensitivity: string}>,
     *     entity_type: string,
     *     unit: ?string,
     *     sensitivity: string,
     *     is_ambiguous: bool
     * }
     */
    public function inferSemanticDetails(string $header, array $profile, string $category, float $baseConfidence): array
    {
        $rawH = mb_strtolower(trim($header));
        $hNorm = preg_replace('/[\s\-]+/u', '_', $rawH);
        $h = $rawH . ' ' . $hNorm;
        $type = $profile['type'] ?? 'string';

        // 1. Map physical type
        $physicalType = match ($type) {
            'currency'   => self::PHYSICAL_TYPE_CURRENCY,
            'float'      => self::PHYSICAL_TYPE_DECIMAL,
            'integer'    => self::PHYSICAL_TYPE_INTEGER,
            'date'       => self::PHYSICAL_TYPE_DATE,
            'percentage' => self::PHYSICAL_TYPE_PERCENTAGE,
            'boolean'    => self::PHYSICAL_TYPE_BOOLEAN,
            default      => self::PHYSICAL_TYPE_STRING,
        };

        if ($category === self::CATEGORY_IDENTIFIER) {
            $physicalType = self::PHYSICAL_TYPE_IDENTIFIER;
        }

        $unit = $profile['currency_symbol'] ?? null;
        $semanticRole = 'custom_attribute';
        $entityType = 'unknown';
        $sensitivity = self::SENSITIVITY_RESTRICTED; // Fail-closed default
        $candidateRoles = [];
        $isAmbiguous = false;

        switch ($category) {
            case self::CATEGORY_AMOUNT:
                if (preg_match('/\b(buy|buying|purchase|procurement|cost|cogs|supplier_price)\b|কেনা\s*দাম|ক্রয়\s*মূল্য|ক্রয়\s*মূল্য|খরচ/ui', $h)) {
                    $semanticRole = 'purchase_cost';
                    $entityType = 'product';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                    $candidateRoles[] = ['role' => 'unit_cost', 'confidence' => 0.85, 'entity' => 'product', 'sensitivity' => self::SENSITIVITY_INTERNAL];
                    $candidateRoles[] = ['role' => 'line_total', 'confidence' => 0.40, 'entity' => 'order', 'sensitivity' => self::SENSITIVITY_INTERNAL];
                } elseif (preg_match('/\b(sell|selling|sale|unit_price|mrp|retail|price|tag_price|rate)\b|বিক্রয়\s*মূল্য|বিক্রয়\s*মূল্য|বিক্রয়\s*দাম|দাম|মূল্য/ui', $h)) {
                    $semanticRole = 'selling_price';
                    $entityType = 'product';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                    $candidateRoles[] = ['role' => 'line_total', 'confidence' => 0.50, 'entity' => 'order', 'sensitivity' => self::SENSITIVITY_INTERNAL];
                    $candidateRoles[] = ['role' => 'purchase_cost', 'confidence' => 0.35, 'entity' => 'product', 'sensitivity' => self::SENSITIVITY_INTERNAL];
                } elseif (preg_match('/\b(salary|wage|payroll|remuneration|stipend)\b|বেতন/ui', $h)) {
                    $semanticRole = 'salary';
                    $entityType = 'employee';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                    $candidateRoles[] = ['role' => 'commission', 'confidence' => 0.40, 'entity' => 'employee', 'sensitivity' => self::SENSITIVITY_RESTRICTED];
                } elseif (preg_match('/\b(commission|incentive|sales_cut)\b|কমিশন|ইনসেন্টিভ/ui', $h)) {
                    $semanticRole = 'commission';
                    $entityType = 'employee';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                    $candidateRoles[] = ['role' => 'salary', 'confidence' => 0.40, 'entity' => 'employee', 'sensitivity' => self::SENSITIVITY_RESTRICTED];
                } elseif (preg_match('/\b(discount|promo|reduction|rebate|waiver)\b|ছাড়|ডিসকাউন্ট/ui', $h)) {
                    $semanticRole = 'discount';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(tax|vat|gst|duty)\b|কর|ভ্যাট|ট্যাক্স/ui', $h)) {
                    $semanticRole = 'tax_amount';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(consultation|visit|doctor_fee)\b|ভিজিট\s*ফি|ডাক্তার\s*ফি/ui', $h)) {
                    $semanticRole = 'consultation_fee';
                    $entityType = 'medical_service';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(booking|room_charge|room_price|rental)\b|রুম\s*ভাড়া|ভাড়া/ui', $h) || preg_match('/room[\s_-]?(?:charge|price|rate|fee)/ui', $h)) {
                    $semanticRole = 'booking_charge';
                    $entityType = 'booking';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(service_charge|service_fee|fee|charge)\b|ফি/ui', $h) && ! preg_match('/delivery|shipping/ui', $h)) {
                    $semanticRole = 'service_fee';
                    $entityType = 'service';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(donation|pledge|contribution|grant)\b|অনুদান|চাঁদা/ui', $h)) {
                    $semanticRole = 'donation_amount';
                    $entityType = 'donation';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(due|balance|outstanding|unpaid|payable|receivable)\b|বাকি|বকেয়া|বকেয়া/ui', $h)) {
                    $semanticRole = 'due_balance';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(paid|received|collection|collected)\b|পরিশোধ|জমা/ui', $h)) {
                    $semanticRole = 'paid_amount';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(total|subtotal|grand_total|net_amount|invoice_total|amount)\b|মোট|সর্বমোট/ui', $h)) {
                    $semanticRole = 'line_total';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                    $candidateRoles[] = ['role' => 'selling_price', 'confidence' => 0.50, 'entity' => 'product', 'sensitivity' => self::SENSITIVITY_PUBLIC];
                    $candidateRoles[] = ['role' => 'purchase_cost', 'confidence' => 0.40, 'entity' => 'product', 'sensitivity' => self::SENSITIVITY_INTERNAL];
                } else {
                    $semanticRole = 'custom_amount';
                    $entityType = 'unknown';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                    $isAmbiguous = true;
                }
                break;

            case self::CATEGORY_PERSON:
            case self::CATEGORY_PERSON_REFERENCE:
                if (preg_match('/\b(doctor|physician|consultant|dr|surgeon)\b|ডাক্তার|ডাঃ|চিকিৎসক/ui', $h)) {
                    $semanticRole = 'doctor';
                    $entityType = 'medical_provider';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(patient|client_patient|case_patient)\b|রোগী|পেশেন্ট/ui', $h)) {
                    $semanticRole = 'patient';
                    $entityType = 'patient';
                    $sensitivity = self::SENSITIVITY_RESTRICTED; // Strictly restricted
                } elseif (preg_match('/\b(salesperson|salesman|sales_person|salesrep|sales_rep|rep)\b|বিক্রেতা|সেলসম্যান/ui', $h)) {
                    $semanticRole = 'salesperson';
                    $entityType = 'employee';
                    $sensitivity = self::SENSITIVITY_RESTRICTED; // Workforce restricted
                } elseif (preg_match('/\b(employee|staff|worker|officer|operator|engineer)\b|কর্মী|কর্মচারী|কর্মকর্তা/ui', $h)) {
                    $semanticRole = 'employee';
                    $entityType = 'employee';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } elseif (preg_match('/\b(customer|client|buyer|consumer|purchaser)\b|ক্রেতা|গ্রাহক/ui', $h)) {
                    $semanticRole = 'customer';
                    $entityType = 'customer';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(guest|visitor|traveler|occupant)\b|মেহমান|অতিথি/ui', $h)) {
                    $semanticRole = 'guest';
                    $entityType = 'customer';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(cleaner|housekeeping|janitor|maintenance)\b|ক্লিনার|হাউজকিপিং/ui', $h)) {
                    $semanticRole = 'facility_staff';
                    $entityType = 'employee';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } elseif (preg_match('/\b(donor|contributor|patron)\b|দাতা|দানকারী/ui', $h)) {
                    $semanticRole = 'donor';
                    $entityType = 'donor';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(beneficiary|recipient)\b|উপকারভোগী|গ্রহীতা/ui', $h)) {
                    $semanticRole = 'beneficiary';
                    $entityType = 'beneficiary';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } else {
                    $semanticRole = 'custom_person';
                    $entityType = 'person';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                    $isAmbiguous = true;
                }
                break;

            case self::CATEGORY_DATE_TIME:
                if (preg_match('/\b(appointment|visit_date|consultation_date)\b|অ্যাপয়েন্টমেন্ট|সাক্ষাৎ/ui', $h)) {
                    $semanticRole = 'appointment_date';
                    $entityType = 'appointment';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/(check[\s_-]?in|arrival)|চেক\s*ইন|আগমন/ui', $h)) {
                    $semanticRole = 'check_in_date';
                    $entityType = 'booking';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/(check[\s_-]?out|departure)|চেক\s*আউট|প্রস্থান/ui', $h)) {
                    $semanticRole = 'check_out_date';
                    $entityType = 'booking';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(delivery_date|shipping_date|dispatch_date)\b|ডেলিভারির\s*তারিখ/ui', $h)) {
                    $semanticRole = 'delivery_date';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(joining_date|hire_date|start_date)\b|যোগদানের\s*তারিখ/ui', $h)) {
                    $semanticRole = 'joining_date';
                    $entityType = 'employee';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } elseif (preg_match('/\b(birth_date|dob|birthday)\b|জন্ম\s*তারিখ/ui', $h)) {
                    $semanticRole = 'birth_date';
                    $entityType = 'person';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } elseif (preg_match('/\b(pledge_date|donation_date|received_date)\b|দানের\s*তারিখ/ui', $h)) {
                    $semanticRole = 'donation_date';
                    $entityType = 'donation';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(order_date|sale_date|transaction_date|invoice_date|date|time)\b|অর্ডারের\s*তারিখ|চালান\s*তারিখ|তারিখ|সময়/ui', $h)) {
                    $semanticRole = 'order_date';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } else {
                    $semanticRole = 'custom_date';
                    $entityType = 'unknown';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                }
                break;

            case self::CATEGORY_IDENTIFIER:
                if (preg_match('/\b(appointment_id|appt_id|visit_id)\b|অ্যাপয়েন্টমেন্ট\s*আইডি/ui', $h)) {
                    $semanticRole = 'appointment_id';
                    $entityType = 'appointment';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(booking_id|reservation_id|ticket_id)\b|বুকিং\s*আইডি/ui', $h)) {
                    $semanticRole = 'booking_id';
                    $entityType = 'booking';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(room|room_no|room_number|suite)\b|রুম|রুম\s*নম্বর/ui', $h)) {
                    $semanticRole = 'room_number';
                    $entityType = 'facility_room';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(patient_id|mrn|patient_no)\b|রোগী\s*আইডি/ui', $h)) {
                    $semanticRole = 'patient_id';
                    $entityType = 'patient';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } elseif (preg_match('/\b(employee_id|emp_id|staff_id|badge_no)\b|কর্মী\s*আইডি/ui', $h)) {
                    $semanticRole = 'employee_id';
                    $entityType = 'employee';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } elseif (preg_match('/\b(sku|barcode|item_code|product_code)\b|বারকোড|এসকেইউ|পণ্য\s*কোড/ui', $h)) {
                    $semanticRole = 'sku';
                    $entityType = 'product';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(donation_id|receipt_no|receipt)\b|রশিদ|রশিদ\s*নম্বর/ui', $h)) {
                    $semanticRole = 'donation_id';
                    $entityType = 'donation';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(donor_id|donor)\b/i', $h)) {
                    $semanticRole = 'donor_id';
                    $entityType = 'donor';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(order_id|invoice|trx_id|transaction_id)\b|অর্ডার\s*আইডি|ইনভয়েস/ui', $h)) {
                    $semanticRole = 'order_id';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } else {
                    $semanticRole = 'custom_identifier';
                    $entityType = 'unknown';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                }
                break;

            case self::CATEGORY_ENTITY_NAME:
                if (preg_match('/\b(service|course|treatment|procedure)\b|সেবা|কোর্স|ট্রিটমেন্ট/ui', $h)) {
                    $semanticRole = 'service_name';
                    $entityType = 'service';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } elseif (preg_match('/\b(project|campaign|initiative)\b|প্রকল্প|ক্যাম্পেইন/ui', $h)) {
                    $semanticRole = 'project_name';
                    $entityType = 'project';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                } else {
                    $semanticRole = 'product_name';
                    $entityType = 'product';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                }
                break;

            case self::CATEGORY_QUANTITY:
                if (preg_match('/\b(stock|inventory|in_stock|balance_qty)\b|স্টক|মজুদ/ui', $h)) {
                    $semanticRole = 'stock_quantity';
                    $entityType = 'product';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } else {
                    $semanticRole = 'order_quantity';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                }
                break;

            case self::CATEGORY_STATUS:
                if (preg_match('/\b(payment_status|paid_status)\b|পেমেন্ট\s*অবস্থা/ui', $h)) {
                    $semanticRole = 'payment_status';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } else {
                    $semanticRole = 'order_status';
                    $entityType = 'order';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                }
                break;

            case self::CATEGORY_DESCRIPTION:
                if (preg_match('/\b(diagnosis|symptom|clinical|treatment_notes)\b|রোগের\s*বিবরণ|লক্ষণ/ui', $h)) {
                    $semanticRole = 'clinical_notes';
                    $entityType = 'patient';
                    $sensitivity = self::SENSITIVITY_RESTRICTED;
                } else {
                    $semanticRole = 'description';
                    $entityType = 'unknown';
                    $sensitivity = self::SENSITIVITY_PUBLIC;
                }
                break;

            case self::CATEGORY_CONTACT:
            case self::CATEGORY_CONTACT_INFO:
                if (preg_match('/\b(phone|mobile|cell|tel)\b|ফোন|মোবাইল/ui', $h)) {
                    $semanticRole = 'phone_number';
                    $entityType = 'contact';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } elseif (preg_match('/\b(email|mail)\b|ইমেইল/ui', $h)) {
                    $semanticRole = 'email_address';
                    $entityType = 'contact';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                } else {
                    $semanticRole = 'address';
                    $entityType = 'contact';
                    $sensitivity = self::SENSITIVITY_INTERNAL;
                }
                break;

            case self::CATEGORY_CATEGORY:
                $semanticRole = 'category';
                $entityType = 'category';
                $sensitivity = self::SENSITIVITY_PUBLIC;
                $isAmbiguous = false;
                break;

            default:
                // Category custom attribute / unknown
                $snake = strtolower(trim(preg_replace('/[^\w]+/u', '_', $header), '_'));
                $semanticRole = $snake !== '' ? $snake : 'custom_attribute';
                $entityType = 'unknown';
                $sensitivity = self::SENSITIVITY_RESTRICTED;
                $isAmbiguous = ($baseConfidence < 0.65);
                break;
        }

        return [
            'physical_type'   => $physicalType,
            'semantic_role'   => $semanticRole,
            'candidate_roles' => $candidateRoles,
            'entity_type'     => $entityType,
            'unit'            => $unit,
            'sensitivity'     => $sensitivity,
            'is_ambiguous'    => $isAmbiguous,
        ];
    }

    /**
     * Determine if a sheet represents unstructured knowledge (FAQ/Policy) or structured business data.
     *
     * @param array<int, string> $headers
     * @param array<int, array<string, mixed>> $columns
     */
    public function detectSheetPurpose(array $headers, array $columns): string
    {
        $hJoined = mb_strtolower(implode(' ', $headers));

        // Knowledge indicators: question, answer, faq, policy, clause, terms, about
        if (preg_match('/\b(faq|question|answer|policy|terms|condition|clause|overview|guidelines)\b/i', $hJoined)
            || preg_match('/(প্রশ্ন|উত্তর|নীতিমালা|শর্তাবলী|এফএকিউ)/u', $hJoined)) {
            return 'knowledge_base';
        }

        // Structured indicators: price, amount, stock, qty, order_id, sku, code
        $hasStructuredCol = false;
        foreach ($columns as $c) {
            $cat = $c['suggested_category'] ?? '';
            if (in_array($cat, [self::CATEGORY_AMOUNT, self::CATEGORY_QUANTITY, self::CATEGORY_IDENTIFIER], true)) {
                $hasStructuredCol = true;
                break;
            }
        }

        if ($hasStructuredCol) {
            return 'structured_data';
        }

        return 'structured_data';
    }

    /**
     * Protect against CSV / Spreadsheet formula injection when displaying or exporting cell values.
     *
     * Invariants:
     * 1. Legitimate numbers (e.g. -500, -5.25, +500, Bengali -৫০০, -৫.২৫) are NEVER corrupted.
     * 2. Untrusted formulas (=, +, -, @, |, %) with leading spaces or control chars are escaped with a prepended single quote.
     */
    public function sanitizeCellForDisplay(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $str = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($str === '') {
            return '';
        }

        $trimmed = trim($str);

        // Normalize Bengali numerals to check numeric validity
        $bengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $numericCandidate = str_replace($bengaliDigits, $englishDigits, $trimmed);

        // 1. Direct signed numeric check (e.g. -500, -5.25, +500, 1200)
        if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', str_replace(',', '', $numericCandidate))) {
            return $str;
        }

        // 2. Accounting format in parentheses e.g. (500) or (500.25)
        if (preg_match('/^\(\s*\d+(?:\.\d+)?\s*\)$/', str_replace(',', '', $numericCandidate))) {
            return $str;
        }

        // 3. Signed currency formats: -$500, $-500, -৳২৫০, Tk. -500, ( $500.25 )
        $stripped = preg_replace('/^[+-]?\s*(?:tk\.?|bdt\.?|usd\.?|\$|৳|₹|€|£)\s*[+-]?/iu', '', $numericCandidate);
        $stripped = preg_replace('/\s*(?:tk\.?|bdt\.?|টাকা|৳)$/iu', '', $stripped);
        $stripped = trim(str_replace([',', '(', ')'], '', $stripped));
        if ($stripped !== '' && preg_match('/^[+-]?\d+(?:\.\d+)?$/', $stripped)) {
            return $str;
        }

        // 4. Formula Injection Escape: if first non-whitespace/control character is in [=, +, -, @, |, %]
        $cleanLeading = ltrim($str, " \t\r\n\0\x0B");
        if ($cleanLeading !== '') {
            $firstChar = $cleanLeading[0];
            if (in_array($firstChar, ['=', '+', '-', '@', '|', '%'], true)) {
                return "'" . $str;
            }
        }

        return $str;
    }

    private function isNumericString(string $val): bool
    {
        $v = trim($val);
        if ($v === '') {
            return false;
        }
        return is_numeric(str_replace(',', '', $v));
    }

    private function isDateString(string $val): bool
    {
        $v = trim($val);
        if (strlen($v) < 6 || is_numeric($v)) {
            return false;
        }
        $ts = strtotime($v);
        return $ts !== false && $ts > 0;
    }

    private function cleanNumericString(string $val): string
    {
        $v = trim($val);
        // Strip leading/trailing currency symbols and signs
        $v = preg_replace('/^[+-]?\s*(?:tk\.?|bdt\.?|usd\.?|\$|৳|₹|€|£)\s*/iu', '', $v);
        $v = preg_replace('/\s*(?:tk\.?|bdt\.?|টাকা|৳)$/iu', '', $v);
        // Accounting parentheses
        if (preg_match('/^\((.+)\)$/', $v, $m)) {
            $v = '-' . $m[1];
        }
        return str_replace(',', '', $v);
    }

    private function isHeaderSuggestingAmount(string $header): bool
    {
        $h = mb_strtolower($header);
        return (bool) (preg_match('/\b(price|cost|amount|fee|salary|total|due|bill|balance)\b/i', $h)
            || preg_match('/(দাম|মূল্য|টাকা|বেতন|ফি|খরচ)/u', $h));
    }

    private function isRecognizedHeaderTerm(string $val): bool
    {
        $v = mb_strtolower(trim($val));
        return (bool) (preg_match('/\b(name|item|product|price|sku|code|date|qty|quantity|amount|total|status|category|id|phone|email)\b/i', $v)
            || preg_match('/(নাম|পণ্য|দাম|মূল্য|কোড|তারিখ|পরিমাণ|স্টক|মোট|আইডি|ফোন)/u', $v));
    }

    private function detectEncoding(string $path): string
    {
        $content = file_get_contents($path, false, null, 0, 4096) ?: '';
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return 'UTF-8'; // UTF-8 with BOM
        }
        $enc = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        return $enc ?: 'UTF-8';
    }

    private function countCsvRows(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'r');
        if ($handle) {
            while (fgets($handle) !== false) {
                $count++;
            }
            fclose($handle);
        }
        return $count;
    }

    private function testCsvRead(string $path): void
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Cannot open CSV file.');
        }
        $row = fgetcsv($handle);
        fclose($handle);
        if ($row === false) {
            throw new \RuntimeException('CSV file is empty or unreadable.');
        }
    }
}
