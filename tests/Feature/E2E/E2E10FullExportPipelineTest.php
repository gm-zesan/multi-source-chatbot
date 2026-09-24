<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E2E-10: Full Analytics Export Pipeline (CSV / XLSX / PDF)
 *
 * Pathway: User Requests Export -> ExportController -> ExportService -> Streamed Multi-Format File Generation with Bengali UTF-8 Integrity
 */
class E2E10FullExportPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Export Enterprise Ltd', 'slug' => 'export-enterprise']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name'         => 'Export Admin',
            'email'        => 'admin@exportenterprise.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_e2e_10_csv_export_generates_valid_stream_with_utf8_bom_and_bengali(): void
    {
        $payload = [
            'format'  => 'csv',
            'title'   => 'মাসিক বিক্রয় বিবরণী',
            'headers' => ['তারিখ', 'বিক্রয়কর্মী', 'পণ্য', 'মোট টাকা (৳)'],
            'rows'    => [
                ['২০২৬-০৯-২৪', 'হাসান', 'ল্যাপটপ প্রো ১৫', '৳৪,৫০,০০০.০০'],
                ['২০২৬-০৯-২৪', 'রাকিব', 'মেকানিক্যাল কিবোর্ড', '৳২৫,০০০.০০'],
            ],
            'summary' => ['সর্বমোট' => '৳৪,৭৫,০০০.০০'],
        ];

        $response = $this->actingAs($this->admin)->post('/dashboard/export/analytics', $payload);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $stream = $response->streamedContent();
        // UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $stream);
        $this->assertStringContainsString('তারিখ,বিক্রয়কর্মী,পণ্য,"মোট টাকা (৳)"', $stream);
        $this->assertStringContainsString('২০২৬-০৯-২৪,হাসান,"ল্যাপটপ প্রো ১৫","৳৪,৫০,০০০.০০"', $stream);
    }

    public function test_e2e_10_xlsx_export_generates_valid_openxml_stream(): void
    {
        $payload = [
            'format'  => 'xlsx',
            'title'   => 'Monthly Sales Report',
            'headers' => ['Date', 'Salesperson', 'Revenue'],
            'rows'    => [
                ['2026-09-24', 'Hasan', '৳450,000.00'],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/dashboard/export/analytics', $payload);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_e2e_10_pdf_export_generates_valid_pdf_document(): void
    {
        $payload = [
            'format'  => 'pdf',
            'title'   => 'Monthly PDF Report',
            'headers' => ['Metric', 'Value'],
            'rows'    => [
                ['Total Orders', '124'],
                ['Total Revenue', '580,000'],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/dashboard/export/analytics', $payload);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
