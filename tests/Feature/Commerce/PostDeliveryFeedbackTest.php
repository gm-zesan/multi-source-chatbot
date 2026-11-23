<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\RoleEnum;
use App\Events\IncomingMessageReceived;
use App\Jobs\SendPostDeliveryFeedbackJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OrderFeedback;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Retention\PostDeliveryFeedbackService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PostDeliveryFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private Channel $whatsappChannel;
    private ChannelAccount $whatsappAccount;
    private Conversation $conversation;
    private WorkspaceOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->workspace = Workspace::forceCreate([
            'id'            => 999111,
            'name'          => 'Apex Fashion Store',
            'slug'          => 'apex-fashion-store',
            'support_phone' => '01700000000',
            'is_active'     => true,
        ]);

        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->owner->assignRole(RoleEnum::ADMIN->value);

        $this->whatsappChannel = Channel::firstOrCreate(
            ['slug' => 'whatsapp'],
            ['name' => 'WhatsApp Business', 'is_active' => true]
        );

        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->whatsappChannel->id,
            'name'         => 'Official Sales Desk',
            'external_id'  => 'wa_phone_id_123',
            'access_token' => 'dummy_wa_token',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id'   => '01812345678',
            'customer_name'      => 'Tanvir Ahmed',
            'customer_phone'     => '01812345678',
            'last_direction'     => 'inbound',
        ]);

        $this->order = WorkspaceOrder::create([
            'workspace_id'           => $this->workspace->id,
            'conversation_id'        => $this->conversation->id,
            'order_number'           => 'ORD-FEEDBACK-01',
            'channel'                => 'whatsapp',
            'customer_name'          => 'Tanvir Ahmed',
            'customer_phone'         => '01812345678',
            'delivery_address'       => 'Mirpur 10, Dhaka',
            'product_summary'        => 'Premium Cotton Shirt (L)',
            'order_amount'           => 1850.00,
            'status'                 => 'shipped',
            'courier_provider'       => 'steadfast',
            'courier_consignment_id' => 'SF_CSAT_9988',
            'courier_tracking_code'  => 'TRK_CSAT_9988',
            'courier_status'         => 'in_review',
        ]);
    }

    public function test_steadfast_delivery_webhook_automatically_triggers_csat_feedback_job(): void
    {
        Queue::fake([SendPostDeliveryFeedbackJob::class]);

        $response = $this->postJson(route('api.courier.webhook.steadfast'), [
            'invoice'       => 'ORD-FEEDBACK-01',
            'tracking_code' => 'TRK_CSAT_9988',
            'status'        => 'delivered',
        ]);

        $response->assertOk();
        $this->order->refresh();
        $this->assertSame('delivered', $this->order->status);

        $feedback = OrderFeedback::where('workspace_order_id', $this->order->id)->first();
        $this->assertNotNull($feedback);
        $this->assertSame('requested', $feedback->status);
        $this->assertSame('Tanvir Ahmed', $feedback->customer_name);

        Queue::assertPushed(SendPostDeliveryFeedbackJob::class, function ($job) use ($feedback) {
            return $job->orderFeedbackId === $feedback->id;
        });
    }

    public function test_pathao_delivery_webhook_triggers_csat_feedback(): void
    {
        Queue::fake([SendPostDeliveryFeedbackJob::class]);

        $this->order->update([
            'courier_provider'       => 'pathao',
            'courier_consignment_id' => 'PATHAO_9911',
        ]);

        $response = $this->postJson(route('api.courier.webhook.pathao'), [
            'consignment_id'    => 'PATHAO_9911',
            'merchant_order_id' => 'ORD-FEEDBACK-01',
            'order_status'      => 'Delivered',
        ]);

        $response->assertOk();

        $feedback = OrderFeedback::where('workspace_order_id', $this->order->id)->first();
        $this->assertNotNull($feedback);
        $this->assertSame('requested', $feedback->status);
    }

    public function test_manual_order_status_update_to_delivered_triggers_csat(): void
    {
        Queue::fake([SendPostDeliveryFeedbackJob::class]);

        $response = $this->actingAs($this->owner)
            ->post(route('orders.update-status', $this->order), [
                'status' => 'delivered',
            ]);

        $response->assertSessionHas('success');

        $feedback = OrderFeedback::where('workspace_order_id', $this->order->id)->first();
        $this->assertNotNull($feedback);
        $this->assertSame('requested', $feedback->status);
    }

    public function test_feedback_service_sends_polite_bengali_csat_request(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/wa_phone_id_123/messages' => Http::response([
                'messages' => [['id' => 'wamid.CSAT_REQ_001']],
            ], 200),
        ]);

        $service = app(PostDeliveryFeedbackService::class);
        $feedback = OrderFeedback::create([
            'workspace_id'          => $this->workspace->id,
            'workspace_order_id'    => $this->order->id,
            'conversation_id'       => $this->conversation->id,
            'customer_name'         => 'Tanvir Ahmed',
            'customer_phone'        => '01812345678',
            'channel'               => 'whatsapp',
            'status'                => 'requested',
            'feedback_requested_at' => now(),
        ]);

        $success = $service->sendFeedbackRequest($feedback);
        $this->assertTrue($success);

        $msg = Message::where('conversation_id', $this->conversation->id)
            ->where('direction', 'outbound')
            ->latest()
            ->first();

        $this->assertNotNull($msg);
        $this->assertStringContainsString('সফলভাবে ডেলিভারি সম্পন্ন হয়েছে', $msg->body);
        $this->assertStringContainsString('১ থেকে ৫ এর মধ্যে রেটিং দিন', $msg->body);
    }

    public function test_incoming_customer_rating_digit_records_5_star_csat(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/wa_phone_id_123/messages' => Http::response(['success' => true], 200),
        ]);

        $feedback = OrderFeedback::create([
            'workspace_id'          => $this->workspace->id,
            'workspace_order_id'    => $this->order->id,
            'conversation_id'       => $this->conversation->id,
            'customer_name'         => 'Tanvir Ahmed',
            'customer_phone'        => '01812345678',
            'channel'               => 'whatsapp',
            'status'                => 'requested',
            'feedback_requested_at' => now(),
        ]);

        $service = app(PostDeliveryFeedbackService::class);
        $recorded = $service->recordFeedbackFromMessage($this->conversation, '5 star');

        $this->assertNotNull($recorded);
        $this->assertSame(5, $recorded->rating);
        $this->assertSame('5 star', $recorded->review_text);
        $this->assertSame('received', $recorded->status);
        $this->assertNotNull($recorded->feedback_received_at);

        // Verify thank you message was dispatched
        $thanksMsg = Message::where('conversation_id', $this->conversation->id)
            ->where('direction', 'outbound')
            ->latest()
            ->first();
        $this->assertNotNull($thanksMsg);
        $this->assertStringContainsString('ধন্যবাদ', $thanksMsg->body);
    }

    public function test_incoming_customer_bengali_sentiment_records_rating(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/wa_phone_id_123/messages' => Http::response(['success' => true], 200),
        ]);

        $feedback = OrderFeedback::create([
            'workspace_id'          => $this->workspace->id,
            'workspace_order_id'    => $this->order->id,
            'conversation_id'       => $this->conversation->id,
            'customer_name'         => 'Tanvir Ahmed',
            'customer_phone'        => '01812345678',
            'channel'               => 'whatsapp',
            'status'                => 'requested',
            'feedback_requested_at' => now(),
        ]);

        $service = app(PostDeliveryFeedbackService::class);

        // Positive sentiment
        $recorded = $service->recordFeedbackFromMessage($this->conversation, 'অনেক ভালো প্রডাক্ট, ডেলিভারিও দ্রুত পেয়েছি!');
        $this->assertNotNull($recorded);
        $this->assertSame(5, $recorded->rating);
        $this->assertSame('received', $recorded->status);
    }

    public function test_csat_stats_calculation(): void
    {
        $service = app(PostDeliveryFeedbackService::class);

        // Order 1: 5 star
        OrderFeedback::create([
            'workspace_id'          => $this->workspace->id,
            'workspace_order_id'    => $this->order->id,
            'status'                => 'received',
            'rating'                => 5,
            'feedback_requested_at' => now()->subHour(),
            'feedback_received_at'  => now(),
        ]);

        // Order 2: 4 star
        $order2 = WorkspaceOrder::create([
            'workspace_id'    => $this->workspace->id,
            'order_number'    => 'ORD-FEEDBACK-02',
            'status'          => 'delivered',
            'customer_name'   => 'Customer 2',
        ]);
        OrderFeedback::create([
            'workspace_id'          => $this->workspace->id,
            'workspace_order_id'    => $order2->id,
            'status'                => 'received',
            'rating'                => 4,
            'feedback_requested_at' => now()->subHour(),
            'feedback_received_at'  => now(),
        ]);

        // Order 3: pending requested
        $order3 = WorkspaceOrder::create([
            'workspace_id'    => $this->workspace->id,
            'order_number'    => 'ORD-FEEDBACK-03',
            'status'          => 'delivered',
            'customer_name'   => 'Customer 3',
        ]);
        OrderFeedback::create([
            'workspace_id'          => $this->workspace->id,
            'workspace_order_id'    => $order3->id,
            'status'                => 'requested',
            'feedback_requested_at' => now(),
        ]);

        $stats = $service->getCSATStats($this->workspace->id);

        $this->assertSame(3, $stats['total_requested']);
        $this->assertSame(2, $stats['total_received']);
        $this->assertSame(4.5, $stats['average_rating']);
        $this->assertSame(100.0, $stats['csat_percentage']);
        $this->assertSame(66.7, $stats['response_rate']);
    }
}
