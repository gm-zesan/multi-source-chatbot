<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Conversation;
use App\Models\Message;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExportService
{
    /**
     * Export structured tabular data to CSV, XLSX, or PDF.
     *
     * @param array{headers: array<string>, rows: array<array<mixed>>, summary?: array<string, mixed>, title?: string} $data
     * @param string $format 'csv' | 'xlsx' | 'pdf'
     * @param string $filename
     * @return Response|StreamedResponse
     */
    public function exportData(array $data, string $format = 'csv', string $filename = 'analytics-export'): Response|StreamedResponse
    {
        $headers = $data['headers'] ?? [];
        $rows = $data['rows'] ?? [];
        $title = $data['title'] ?? 'Business Analytics Report';
        $summary = $data['summary'] ?? [];

        return match (strtolower($format)) {
            'xlsx', 'excel' => $this->generateXlsx($headers, $rows, $title, $summary, $filename),
            'pdf'           => $this->generatePdf($headers, $rows, $title, $summary, $filename),
            default         => $this->generateCsv($headers, $rows, $filename),
        };
    }

    /**
     * Export conversation transcript to CSV, XLSX, or PDF.
     */
    public function exportConversation(Conversation $conversation, string $format = 'pdf'): Response|StreamedResponse
    {
        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        $title = 'Conversation Transcript - ' . ($conversation->customer_name ?? 'Customer #' . $conversation->id);
        $filename = 'conversation-' . $conversation->id . '-' . date('Ymd-His');

        $headers = ['Time', 'Sender', 'Type', 'Route', 'Message'];
        $rows = [];

        foreach ($messages as $msg) {
            $sender = $msg->direction === 'inbound' ? ($conversation->customer_name ?? 'Customer') : 'AI Assistant';
            $meta = $msg->response ?? [];
            $route = $meta['route'] ?? ($msg->direction === 'inbound' ? 'INBOUND' : 'REPLY');

            $rows[] = [
                $msg->created_at?->format('Y-m-d H:i:s') ?? '',
                $sender,
                strtoupper((string) $msg->type),
                strtoupper((string) $route),
                $msg->body ?? '',
            ];
        }

        $summary = [
            'Conversation ID' => $conversation->id,
            'Customer'        => $conversation->customer_name ?? 'N/A',
            'Channel'         => $conversation->channelAccount?->channel?->name ?? 'Web Chat',
            'Total Messages'  => $messages->count(),
            'Exported At'     => date('Y-m-d H:i:s'),
        ];

        return match (strtolower($format)) {
            'xlsx', 'excel' => $this->generateXlsx($headers, $rows, $title, $summary, $filename),
            'csv'           => $this->generateCsv($headers, $rows, $filename),
            default         => $this->generateConversationPdf($conversation, $messages, $title, $filename),
        };
    }

    /**
     * Generate UTF-8 CSV with BOM for universal Excel compatibility.
     */
    private function generateCsv(array $headers, array $rows, string $filename): StreamedResponse
    {
        $headersList = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if (!empty($headers)) {
                fputcsv($output, $headers);
            }

            foreach ($rows as $row) {
                $flatRow = array_map(function ($val) {
                    if (is_array($val) || is_object($val)) {
                        return json_encode($val, JSON_UNESCAPED_UNICODE);
                    }
                    return (string) $val;
                }, $row);
                fputcsv($output, $flatRow);
            }

            fclose($output);
        }, 200, $headersList);
    }

    /**
     * Generate styled Excel workbook using PhpSpreadsheet.
     */
    private function generateXlsx(array $headers, array $rows, string $title, array $summary, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Report');

        $currentRow = 1;

        // Title Row
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('2563EB'));
        $currentRow += 2;

        // Summary KPI Section (if provided)
        if (!empty($summary)) {
            foreach ($summary as $key => $val) {
                $sheet->setCellValue('A' . $currentRow, (string) $key);
                $sheet->setCellValue('B' . $currentRow, is_array($val) ? json_encode($val) : (string) $val);
                $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
                $currentRow++;
            }
            $currentRow++;
        }

        // Table Header Row
        $headerStartRow = $currentRow;
        $colIndex = 1;
        foreach ($headers as $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . $currentRow, (string) $header);
            $colIndex++;
        }

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($headers)));

        // Style Headers
        if (!empty($headers)) {
            $sheet->getStyle("A{$headerStartRow}:{$lastColLetter}{$headerStartRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E40AF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
            $sheet->getRowDimension($headerStartRow)->setRowHeight(24);
            $currentRow++;
        }

        // Table Rows
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $cellValue) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter . $currentRow, is_array($cellValue) ? json_encode($cellValue) : (string) $cellValue);
                $colIndex++;
            }
            $currentRow++;
        }

        // Auto-size columns
        for ($i = 1; $i <= max(1, count($headers)); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $headersList = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, $headersList);
    }

    /**
     * Generate styled PDF report.
     */
    private function generatePdf(array $headers, array $rows, string $title, array $summary, string $filename): Response
    {
        $html = view('exports.analytics_pdf', [
            'title'     => $title,
            'summary'   => $summary,
            'headers'   => $headers,
            'rows'      => $rows,
            'timestamp' => date('Y-m-d H:i:s'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }

    /**
     * Generate styled PDF for full conversation thread.
     */
    private function generateConversationPdf(Conversation $conversation, $messages, string $title, string $filename): Response
    {
        $html = view('exports.conversation_pdf', [
            'conversation' => $conversation,
            'messages'     => $messages,
            'title'        => $title,
            'timestamp'    => date('Y-m-d H:i:s'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }
}
