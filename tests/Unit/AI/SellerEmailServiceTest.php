<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Mail\SellerNotificationMail;
use App\Models\AnalyticsSalesperson;
use App\Models\Workspace;
use App\Services\AI\SellerEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SellerEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private SellerEmailService $service;
    private Workspace $workspace1;
    private Workspace $workspace2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SellerEmailService();

        $this->workspace1 = Workspace::create(['name' => 'Workspace 1', 'slug' => 'workspace-1']);
        $this->workspace2 = Workspace::create(['name' => 'Workspace 2', 'slug' => 'workspace-2']);
    }

    public function test_resolve_unique_seller_by_name(): void
    {
        $seller = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Abdur Rahim',
            'phone'         => '01711111111',
            'email'         => 'rahim@example.com',
            'employee_code' => 'SP-001',
            'is_active'     => true,
        ]);

        $result = $this->service->resolveSeller('Rahim', $this->workspace1->id);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame($seller->id, $result['seller']->id);
        $this->assertSame('rahim@example.com', $result['seller']->email);
    }

    public function test_resolve_multiple_matching_sellers_returns_ambiguous(): void
    {
        AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Rahim Uddin',
            'phone'         => '01711111111',
            'email'         => 'rahim1@example.com',
            'employee_code' => 'SP-001',
            'is_active'     => true,
        ]);

        AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Rahim Chowdhury',
            'phone'         => '01722222222',
            'email'         => 'rahim2@example.com',
            'employee_code' => 'SP-002',
            'is_active'     => true,
        ]);

        $result = $this->service->resolveSeller('Rahim', $this->workspace1->id);

        $this->assertSame('ambiguous', $result['status']);
        $this->assertCount(2, $result['matches']);
    }

    public function test_resolve_seller_not_found(): void
    {
        $result = $this->service->resolveSeller('NonExistentPerson', $this->workspace1->id);

        $this->assertSame('not_found', $result['status']);
    }

    public function test_resolve_inactive_seller(): void
    {
        AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Tareq Hasan',
            'phone'         => '01733333333',
            'email'         => 'tareq@example.com',
            'employee_code' => 'SP-003',
            'is_active'     => false,
        ]);

        $result = $this->service->resolveSeller('Tareq', $this->workspace1->id);

        $this->assertSame('inactive', $result['status']);
        $this->assertFalse($result['seller']->is_active);
    }

    public function test_resolve_cross_workspace_seller_is_isolated(): void
    {
        // Seller exists in workspace 2
        AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace2->id,
            'name'          => 'Karim Mia',
            'phone'         => '01744444444',
            'email'         => 'karim@workspace2.com',
            'employee_code' => 'SP-004',
            'is_active'     => true,
        ]);

        // Attempt resolving from workspace 1
        $result = $this->service->resolveSeller('Karim', $this->workspace1->id);

        $this->assertSame('not_found', $result['status']);
    }

    public function test_resolve_seller_without_email(): void
    {
        AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'No Email Seller',
            'phone'         => '01755555555',
            'email'         => null,
            'employee_code' => 'SP-005',
            'is_active'     => true,
        ]);

        $result = $this->service->resolveSeller('No Email Seller', $this->workspace1->id);

        $this->assertSame('missing_email', $result['status']);
    }

    public function test_resolve_by_explicit_email(): void
    {
        $seller = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Direct Email Seller',
            'phone'         => '01766666666',
            'email'         => 'direct@example.com',
            'employee_code' => 'SP-006',
            'is_active'     => true,
        ]);

        $result = $this->service->resolveSeller('direct@example.com', $this->workspace1->id);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame($seller->id, $result['seller']->id);
    }

    public function test_send_seller_email_successful_dispatch(): void
    {
        Mail::fake();

        $seller = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Jamil Hossain',
            'phone'         => '01777777777',
            'email'         => 'jamil@example.com',
            'employee_code' => 'SP-007',
            'is_active'     => true,
        ]);

        $subject = 'Order Confirmation #101';
        $message = 'Your customer paid the invoice.';
        $fingerprint = hash('sha256', "{$this->workspace1->id}|{$seller->id}|jamil@example.com|{$subject}|{$message}");

        $result = $this->service->sendSellerEmail(
            sellerId: $seller->id,
            subject: $subject,
            message: $message,
            workspaceId: $this->workspace1->id,
            expectedFingerprint: $fingerprint,
        );

        $this->assertTrue($result['success']);
        $this->assertSame('jamil@example.com', $result['recipient_email']);

        Mail::assertSent(SellerNotificationMail::class, function (SellerNotificationMail $mail) use ($seller, $subject, $message) {
            return $mail->hasTo('jamil@example.com') &&
                $mail->emailSubject === $subject &&
                $mail->emailMessage === $message &&
                $mail->sellerName === $seller->name;
        });
    }

    public function test_send_seller_email_fails_if_fingerprint_mismatched(): void
    {
        Mail::fake();

        $seller = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Jamil Hossain',
            'phone'         => '01777777777',
            'email'         => 'jamil@example.com',
            'employee_code' => 'SP-007',
            'is_active'     => true,
        ]);

        $result = $this->service->sendSellerEmail(
            sellerId: $seller->id,
            subject: 'Subject',
            message: 'Body',
            workspaceId: $this->workspace1->id,
            expectedFingerprint: 'tampered_invalid_fingerprint',
        );

        $this->assertFalse($result['success']);
        $this->assertSame('fingerprint_mismatch', $result['error_code']);
        Mail::assertNothingSent();
    }

    public function test_send_seller_email_cross_workspace_rejected(): void
    {
        Mail::fake();

        $seller = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace2->id,
            'name'          => 'Other Seller',
            'phone'         => '01788888888',
            'email'         => 'other@example.com',
            'employee_code' => 'SP-008',
            'is_active'     => true,
        ]);

        $result = $this->service->sendSellerEmail(
            sellerId: $seller->id,
            subject: 'Subject',
            message: 'Body',
            workspaceId: $this->workspace1->id, // Workspace mismatch
        );

        $this->assertFalse($result['success']);
        $this->assertSame('seller_not_found', $result['error_code']);
        Mail::assertNothingSent();
    }

    public function test_send_seller_email_inactive_seller_rejected(): void
    {
        Mail::fake();

        $seller = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace1->id,
            'name'          => 'Inactive Seller',
            'phone'         => '01799999999',
            'email'         => 'inactive@example.com',
            'employee_code' => 'SP-009',
            'is_active'     => false,
        ]);

        $result = $this->service->sendSellerEmail(
            sellerId: $seller->id,
            subject: 'Subject',
            message: 'Body',
            workspaceId: $this->workspace1->id,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('seller_inactive', $result['error_code']);
        Mail::assertNothingSent();
    }
}
