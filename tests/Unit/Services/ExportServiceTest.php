<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Export\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ExportServiceTest extends TestCase
{
    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportService();
    }

    public function test_export_csv_generates_stream_with_utf8_bom_and_bangla(): void
    {
        $dataset = [
            'headers' => ['পণ্য নাম', 'পরিমাণ', 'মূল্য (৳)', 'মন্তব্য'],
            'rows' => [
                ['ল্যাপটপ প্রো ১৫', 2, '৳ ১,৫০,০০০', 'ডেলিভারি "জরুরি" ও,\nনতুন প্যাকিং'],
                ['মাউস RGB', 5, '৳ ৭,৫০০', 'নরমাল ডেলিভারি'],
            ],
        ];

        $response = $this->service->exportData($dataset, 'csv', 'test-bangla');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('test-bangla.csv', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        // Verify UTF-8 BOM
        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $this->assertStringStartsWith($bom, $csvContent);
        // Verify Bangla text and quotes preserved
        $this->assertStringContainsString('ল্যাপটপ প্রো ১৫', $csvContent);
        $this->assertStringContainsString('৳ ১,৫০,০০০', $csvContent);
        $this->assertStringContainsString('"ডেলিভারি ""জরুরি"" ও', $csvContent);
    }

    public function test_export_xlsx_generates_stream_with_correct_content_type(): void
    {
        $dataset = [
            'title' => 'Monthly Sales Report',
            'summary' => ['Total Sales' => '৳ 420,000', 'Total Orders' => 45],
            'headers' => ['Item', 'Amount', 'Status'],
            'rows' => [
                ['Electronics', 120000, 'Completed'],
                ['Fashion', 50000, 'Completed'],
            ],
        ];

        $response = $this->service->exportData($dataset, 'xlsx', 'monthly-report');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('monthly-report.xlsx', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $xlsxContent = ob_get_clean();

        // Valid zip/xlsx binary signature starts with PK
        $this->assertStringStartsWith('PK', $xlsxContent);
        $this->assertGreaterThan(1000, strlen($xlsxContent));
    }

    public function test_export_pdf_renders_dompdf_binary(): void
    {
        $dataset = [
            'title' => 'Executive Analytics Summary',
            'summary' => ['Total Collection' => '৳ 55,000'],
            'headers' => ['Method', 'Collection (৳)'],
            'rows' => [
                ['bKash', '৳ 55,000'],
            ],
        ];

        $response = $this->service->exportData($dataset, 'pdf', 'executive-summary');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('executive-summary.pdf', (string) $response->headers->get('Content-Disposition'));

        $pdfContent = $response->getContent();
        // Valid PDF binary header starts with %PDF-
        $this->assertStringStartsWith('%PDF-', $pdfContent);
        $this->assertGreaterThan(5000, strlen($pdfContent));
    }

    public function test_export_empty_dataset_handles_gracefully(): void
    {
        $dataset = [
            'headers' => [],
            'rows' => [],
        ];

        $response = $this->service->exportData($dataset, 'csv', 'empty-test');
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csvContent = ob_get_clean();

        $bom = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $this->assertSame($bom, $csvContent);
    }
}
