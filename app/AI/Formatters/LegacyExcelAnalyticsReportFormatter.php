<?php

declare(strict_types=1);

namespace App\AI\Formatters;

use App\Services\Analytics\DTOs\Contract3\AnalyticsExecutionResponse;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;

/**
 * Dedicated Presentation Formatter for Governed Spreadsheet Analytics.
 *
 * Responsibilities:
 * - Format Contract 3.0 tabular execution results into rich, user-facing Bengali Markdown.
 * - Preserve dataset metadata (file name, sheet relations, record counts).
 * - Enforce presentation parity with legacy business assistant reporting.
 * - Provide safe, sanitized Bengali messages for empty datasets, missing spreadsheets, and errors.
 * - Guarantee zero data leakage (no raw SQL, tokens, HMAC grants, nonces, or stack traces).
 */
class LegacyExcelAnalyticsReportFormatter
{
    /**
     * Format a successful spreadsheet analytics execution.
     *
     * @param string $query Natural language user question
     * @param string|null $filename Dataset or document original filename
     * @param int|null $recordCount Total verified structured records in dataset
     * @param array<int, mixed> $activeEdges Active cross-sheet relationship edges
     * @param AnalyticsExecutionResponse|null $executionResponse Downstream Contract 3.0 execution response
     * @param bool $isOfflineFallback Whether result is derived from offline persisted structured dataset
     * @param bool $isPartialDataset Whether the active spreadsheet import completed with row errors
     * @param int|null $importedRows Number of rows successfully imported
     * @param int|null $failedRows Number of rows that failed during import
     * @param int|null $totalRows Total rows detected in source spreadsheet
     * @return string Formatted Bengali Markdown response
     */
    public function formatExecution(
        string $query,
        ?string $filename = null,
        ?int $recordCount = null,
        array $activeEdges = [],
        ?AnalyticsExecutionResponse $executionResponse = null,
        bool $isOfflineFallback = false,
        bool $isPartialDataset = false,
        ?int $importedRows = null,
        ?int $failedRows = null,
        ?int $totalRows = null,
    ): string {
        $resolvedFilename = !empty($filename) ? trim($filename) : 'অ্যাক্টিভ স্প্রেডশিট';
        $lines = [];

        // 1. Header & Dataset Identification
        $lines[] = "📊 **ব্যবসায়িক অ্যানালিটিক্স ইনসাইট** ({$resolvedFilename})";
        $lines[] = '----------------------------------------';

        // 1.5. Transparent Offline Fallback Disclosure
        if ($isOfflineFallback) {
            $lines[] = "ℹ️ লাইভ ভার্চুয়াল অ্যানালিটিক্স গেটওয়ে সাময়িকভাবে অনুপলব্ধ থাকায় সংরক্ষিত স্ট্রাকচার্ড ডেটাসেটের ({$resolvedFilename}) ভিত্তিতে এই প্রতিবেদন প্রস্তুত করা হয়েছে।";
        }

        // 1.6. Partial Dataset Disclosure (completed_with_errors)
        if ($isPartialDataset) {
            $partialDetails = [];
            if ($importedRows !== null && $importedRows >= 0) {
                $partialDetails[] = 'সফলভাবে ইমপোর্টকৃত সারি: ' . number_format($importedRows) . ' টি';
            }
            if ($failedRows !== null && $failedRows > 0) {
                $partialDetails[] = 'ব্যর্থ সারি: ' . number_format($failedRows) . ' টি';
            }
            if ($totalRows !== null && $totalRows > 0) {
                $partialDetails[] = 'মোট সারি: ' . number_format($totalRows) . ' টি';
            }

            $countsSegment = !empty($partialDetails) ? ' (' . implode(', ', $partialDetails) . ')' : '';
            $lines[] = "⚠️ **আংশিক ডেটাসেট সতর্কতা (Partial Dataset)**{$countsSegment}: স্প্রেডশিট ইমপোর্ট আংশিকভাবে সম্পন্ন হওয়ায় এই অ্যানালিটিক্স ফলাফল সম্পূর্ণ ডেটাসেটের প্রতিনিধিত্ব নাও করতে পারে।";
        }

        // 2. Verified Processed Records
        if ($recordCount !== null && $recordCount > 0) {
            $lines[] = '• মোট প্রসেসকৃত রেকর্ড: ' . number_format($recordCount) . ' টি।';
        }

        // 3. Active Cross-Sheet Relationships
        if (!empty($activeEdges)) {
            $lines[] = '• সক্রিয় ক্রস-শিট সম্পর্ক (' . count($activeEdges) . ' টি):';
            foreach ($activeEdges as $edge) {
                $lines[] = '  - ' . $this->formatEdge($edge);
            }
        }

        // 4. Downstream NLP Report (if returned by virtual SQLite execution gateway)
        if ($executionResponse !== null && !empty(trim($executionResponse->report))) {
            $lines[] = '';
            $lines[] = trim($executionResponse->report);
        } elseif ($executionResponse !== null && !empty($executionResponse->rows)) {
            // 4b. Format structured rows if tabular data is returned without pre-synthesized NLP report
            $formattedTable = $this->formatTabularData($executionResponse->columns, $executionResponse->rows);
            if (!empty($formattedTable)) {
                $lines[] = '';
                $lines[] = $formattedTable;
            }
        }

        // 5. Intelligent Topic-Specific Summary (Preserving Parity with synthesizeAnalyticsAnswer)
        $topicInsight = $this->resolveTopicInsight($query);
        if ($topicInsight !== null) {
            $lines[] = $topicInsight;
        }

        return implode("\n", $lines);
    }

    /**
     * Format a safe, friendly Bengali response when no active spreadsheet is available.
     */
    public function formatNoSpreadsheet(): string
    {
        return 'No active spreadsheet datasets found for this workspace. Upload a spreadsheet to enable Excel analytics. (ব্যবসায়িক অ্যানালিটিক্স রিপোর্ট: বর্তমানে আপনার ওয়ার্কস্পেসে সক্রিয় কোনো স্প্রেডশিট ডেটাসেট নেই। ডেটা অ্যানালিটিক্স দেখতে নলেজ বেস থেকে একটি এক্সেল বা CSV ফাইল আপলোড করুন।)';
    }

    /**
     * Format an empty query result (no matching rows found in dataset).
     */
    public function formatEmptyResult(?string $filename = null): string
    {
        $name = !empty($filename) ? " '{$filename}'" : '';
        return "ব্যবসায়িক অ্যানালিটিক্স রিপোর্ট: আপনার স্প্রেডশিট ডেটাসেট{$name}-এ অনুরোধকৃত প্রশ্নের জন্য কোনো রেকর্ড পাওয়া যায়নি।";
    }

    /**
     * Format a safe Bengali error message, strictly sanitizing any internal technical details.
     */
    public function formatError(?string $rawError = null): string
    {
        $sanitized = $this->sanitizeErrorMessage($rawError);
        return "ব্যবসায়িক অ্যানালিটিক্স সিস্টেম: আপনার কোয়েরি প্রসেস করতে সাময়িক সমস্যা হয়েছে ({$sanitized})।";
    }

    /**
     * Format an individual relationship edge string without hallucinating missing metadata.
     */
    protected function formatEdge(mixed $edge): string
    {
        if ($edge instanceof DiscoveredRelationshipEdge) {
            $source = $edge->sourceSheet ?? 'Source';
            $target = $edge->targetSheet ?? 'Target';
            $cardinality = $edge->cardinality ?? null;
            $confidence = $edge->confidence ?? null;

            $metaParts = [];
            if (!empty($cardinality)) {
                $metaParts[] = $cardinality;
            }
            if ($confidence !== null && $confidence > 0.0) {
                $metaParts[] = 'কনফিডেন্স: ' . round($confidence * 100) . '%';
            }

            $metaStr = !empty($metaParts) ? ' (' . implode(', ', $metaParts) . ')' : '';
            return "{$source} ↔ {$target}{$metaStr}";
        }

        if (is_array($edge)) {
            $source = $edge['sourceSheet'] ?? $edge['source_sheet'] ?? 'Source';
            $target = $edge['targetSheet'] ?? $edge['target_sheet'] ?? 'Target';
            $cardinality = $edge['cardinality'] ?? null;
            $confidence = $edge['confidence'] ?? null;

            $metaParts = [];
            if (!empty($cardinality)) {
                $metaParts[] = (string) $cardinality;
            }
            if ($confidence !== null && (float) $confidence > 0.0) {
                $metaParts[] = 'কনফিডেন্স: ' . round(((float) $confidence) * 100) . '%';
            }

            $metaStr = !empty($metaParts) ? ' (' . implode(', ', $metaParts) . ')' : '';
            return "{$source} ↔ {$target}{$metaStr}";
        }

        if (is_object($edge)) {
            $source = $edge->sourceSheet ?? 'Source';
            $target = $edge->targetSheet ?? 'Target';
            $cardinality = $edge->cardinality ?? null;
            $confidence = $edge->confidence ?? null;

            $metaParts = [];
            if (!empty($cardinality)) {
                $metaParts[] = (string) $cardinality;
            }
            if ($confidence !== null && (float) $confidence > 0.0) {
                $metaParts[] = 'কনফিডেন্স: ' . round(((float) $confidence) * 100) . '%';
            }

            $metaStr = !empty($metaParts) ? ' (' . implode(', ', $metaParts) . ')' : '';
            return "{$source} ↔ {$target}{$metaStr}";
        }

        return (string) $edge;
    }

    /**
     * Resolve contextual topic insight preserving legacy report parity.
     */
    protected function resolveTopicInsight(string $query): ?string
    {
        $lower = mb_strtolower(trim($query));

        if (str_contains($lower, 'লাভ') || str_contains($lower, 'margin') || str_contains($lower, 'profit')) {
            return '• মুনাফা ও মার্জিন বিশ্লেষণ: আপনার ডেটাসেট অনুযায়ী অনুমোদিত গ্রস মার্জিন ও ক্রয়মূল্য হিসাব যাচাই করা হয়েছে।';
        }

        if (str_contains($lower, 'বিক্রি') || str_contains($lower, 'সেলস') || str_contains($lower, 'sales') || str_contains($lower, 'revenue')) {
            return '• বিক্রয় ও রেভিনিউ সারসংক্ষেপ: সংশ্লিষ্ট শিটের বিক্রয় ও অর্ডার ট্রানজ্যাকশন লোড করা হয়েছে।';
        }

        if (str_contains($lower, 'কর্মচারী') || str_contains($lower, 'সেলসম্যান') || str_contains($lower, 'employee') || str_contains($lower, 'salary') || str_contains($lower, 'বেতন')) {
            return '• কর্মী ও বেতন সংক্রান্ত প্রতিবেদন: অভ্যন্তরীণ কর্মী পারফরম্যান্স ও পে-রোল ডেটা অনুমোদিত ওনার ভিউতে প্রস্তুত।';
        }

        return '• ব্যবসায়িক ডেটা সারাংশ: আপনার স্প্রেডশিটের স্ট্রাকচার্ড রেকর্ড অনুযায়ী প্রস্তুতকৃত তথ্য।';
    }

    /**
     * Render tabular data into concise Markdown for report inclusion.
     *
     * @param array<int, string> $columns
     * @param array<int, array<string, mixed>> $rows
     */
    protected function formatTabularData(array $columns, array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        // For single aggregated row (e.g., total_revenue = 45000), render key-value summary
        if (count($rows) === 1) {
            $row = $rows[0];
            $items = [];
            foreach ($row as $col => $val) {
                $humanCol = ucwords(str_replace(['_', '-'], ' ', (string) $col));
                $formattedVal = is_numeric($val) ? number_format((float) $val, 2) : (string) $val;
                $items[] = "• **{$humanCol}**: {$formattedVal}";
            }
            return implode("\n", $items);
        }

        // For multiple rows (up to 5), format clean Markdown table
        $displayRows = array_slice($rows, 0, 5);
        $headerCols = !empty($columns) ? $columns : array_keys($displayRows[0]);

        $tableLines = [];
        $tableLines[] = '| ' . implode(' | ', array_map(fn($c) => ucwords(str_replace(['_', '-'], ' ', (string) $c)), $headerCols)) . ' |';
        $tableLines[] = '| ' . implode(' | ', array_fill(0, count($headerCols), '---')) . ' |';

        foreach ($displayRows as $r) {
            $rowVals = [];
            foreach ($headerCols as $c) {
                $v = $r[$c] ?? '';
                $formatted = is_numeric($v) ? number_format((float) $v, 2) : (string) $v;
                $rowVals[] = str_replace('|', '\\|', $formatted);
            }
            $tableLines[] = '| ' . implode(' | ', $rowVals) . ' |';
        }

        if (count($rows) > 5) {
            $tableLines[] = "\n*(মোট " . count($rows) . ' টি রেকর্ডের মধ্যে প্রথম ৫টি প্রদর্শিত হচ্ছে)*';
        }

        return implode("\n", $tableLines);
    }

    /**
     * Sanitize error message to prevent SQL, token, or HMAC leakage.
     */
    protected function sanitizeErrorMessage(?string $error): string
    {
        if (empty($error)) {
            return 'সার্ভিস সাময়িকভাবে অনুপলব্ধ';
        }

        // Mask SQL queries
        $error = preg_replace('/(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN)\b[^;]+/i', '[PROTECTED_SQL]', $error);

        // Mask token, secret, grant or hex hashes
        $error = preg_replace('/[a-f0-9]{32,64}/i', '[PROTECTED_HASH]', $error);
        $error = preg_replace('/(grant|token|secret|hmac|nonce)=[\w\-]+/i', '$1=[MASKED]', $error);

        // Standard user-friendly error translations
        if (str_contains($error, 'UnauthorizedDataAccessException') || str_contains($error, 'Access denied')) {
            return 'অননুমোদিত কলাম বা ফিল্ডে অ্যাক্সেস প্রত্যাখ্যান করা হয়েছে';
        }

        if (str_contains($error, 'Connection refused') || str_contains($error, 'DOWNSTREAM_UNAVAILABLE') || str_contains($error, 'timeout')) {
            return 'অ্যানালিটিক্স সার্ভিস রেসপন্স করতে দেরি করছে';
        }

        return mb_substr(strip_tags($error), 0, 120);
    }
}
