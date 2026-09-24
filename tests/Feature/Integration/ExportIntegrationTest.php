<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private Workspace $workspace1;
    private Workspace $workspace2;
    private Conversation $conv1;
    private Conversation $conv2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace1 = Workspace::create(['name' => 'Workspace Alpha', 'slug' => 'ws-alpha']);
        $this->workspace2 = Workspace::create(['name' => 'Workspace Beta', 'slug' => 'ws-beta']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user1 = User::factory()->create([
            'name'         => 'Alpha User',
            'email'        => 'user1@alpha.com',
            'workspace_id' => $this->workspace1->id,
        ]);
        $this->user1->assignRole('admin');

        $this->user2 = User::factory()->create([
            'name'         => 'Beta User',
            'email'        => 'user2@beta.com',
            'workspace_id' => $this->workspace2->id,
        ]);
        $this->user2->assignRole('admin');

        $channel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]);

        $acc1 = ChannelAccount::create([
            'workspace_id' => $this->workspace1->id,
            'channel_id'   => $channel->id,
            'name'         => 'Alpha Account',
            'external_id'  => 'acc_alpha',
            'access_token' => 'token_alpha',
            'is_active'    => true,
        ]);

        $acc2 = ChannelAccount::create([
            'workspace_id' => $this->workspace2->id,
            'channel_id'   => $channel->id,
            'name'         => 'Beta Account',
            'external_id'  => 'acc_beta',
            'access_token' => 'token_beta',
            'is_active'    => true,
        ]);

        $this->conv1 = Conversation::create([
            'channel_account_id' => $acc1->id,
            'external_user_id'   => 'user_alpha_export',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->conv2 = Conversation::create([
            'channel_account_id' => $acc2->id,
            'external_user_id'   => 'user_beta_export',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id' => $this->conv1->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Alpha secret message',
        ]);
    }

    public function test_analytics_export_to_csv_with_utf8_bom_and_bengali_characters(): void
    {
        $response = $this->actingAs($this->user1)->post('/dashboard/export/analytics', [
            'format'  => 'csv',
            'title'   => 'দৈনিক বিক্রয় রিপোর্ট',
            'headers' => ['পণ্য', 'পরিমাণ', 'মূল্য (৳)'],
            'rows'    => [
                ['ল্যাপটপ প্রো ১৫', '২', '৳৪,৫০,০০০.০০'],
                ['মেকানিক্যাল কিবোর্ড, RGB', '৫', '৳২৫,০০০.০০'],
            ],
            'summary' => ['মোট বিক্রয়' => '৳৪,৭৫,০০০.০০'],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        // Check for UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('পণ্য,পরিমাণ,"মূল্য (৳)"', $content);
        $this->assertStringContainsString('ল্যাপটপ প্রো ১৫', $content);
        $this->assertStringContainsString('"মেকানিক্যাল কিবোর্ড, RGB"', $content);
    }

    public function test_analytics_export_to_xlsx_generates_valid_stream(): void
    {
        $response = $this->actingAs($this->user1)->post('/dashboard/export/analytics', [
            'format'  => 'xlsx',
            'title'   => 'Sales Summary',
            'headers' => ['Product', 'Amount'],
            'rows'    => [
                ['Laptop Pro 15', '৳450,000.00'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_analytics_export_to_pdf_generates_valid_pdf_stream(): void
    {
        $response = $this->actingAs($this->user1)->post('/dashboard/export/analytics', [
            'format'  => 'pdf',
            'title'   => 'Sales Report PDF',
            'headers' => ['Product', 'Amount'],
            'rows'    => [
                ['Laptop Pro 15', '450,000'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_user_can_export_their_own_workspace_conversation(): void
    {
        $response = $this->actingAs($this->user1)->get("/dashboard/export/conversation/{$this->conv1->id}/pdf");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
