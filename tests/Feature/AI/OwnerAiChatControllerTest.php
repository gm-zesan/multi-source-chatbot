<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\AnalyticsOrder;
use App\Models\AnalyticsProduct;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerAiChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Demo E-commerce Store',
            'slug' => 'demo-store',
        ]);

        $this->owner = User::create([
            'name'         => 'Owner Zesan',
            'email'        => 'owner@example.com',
            'password'     => bcrypt('secret123'),
            'workspace_id' => $this->workspace->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_owner_ai_chat(): void
    {
        $response = $this->postJson(route('owner-ai.ask'), [
            'question' => 'আজকের সেলস কত?',
        ]);

        $response->assertStatus(401);
    }

    public function test_empty_question_fails_validation(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['question']);
    }

    public function test_suggestions_endpoint_returns_valid_prompt_chips(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('owner-ai.suggestions'));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonStructure([
                'status',
                'suggestions' => [
                    '*' => ['text', 'label'],
                ],
            ]);

        $suggestions = collect($response->json('suggestions'));
        $this->assertTrue($suggestions->contains('label', '📈 লাভ ও মার্জিন'));
        $this->assertTrue($suggestions->contains('label', '💬 মেসেজ ও ব্যালেন্স হিসাব'));
        $this->assertFalse($suggestions->contains('label', '📑 কাস্টমার ব্যালেন্স ও ডিউ'));
    }

    public function test_owner_can_inquire_about_todays_sales(): void
    {
        $customer = \App\Models\AnalyticsCustomer::create([
            'workspace_id'    => $this->workspace->id,
            'name'            => 'Customer One',
            'phone'           => '01811223344',
            'orders_count'    => 1,
            'total_spend'     => 5000.0,
            'current_balance' => 0.0,
        ]);

        $salesperson = \App\Models\AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Main Sales Rep',
            'employee_code' => 'EMP-01',
            'email'         => 'rep@example.com',
            'phone'         => '01711223344',
        ]);

        // Seed today's order
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $customer->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-101',
            'total_amount'   => 5000.0,
            'net_amount'     => 5000.0,
            'status'         => 'completed',
            'order_date'     => Carbon::now()->toDateString(),
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'আজকের মোট সেলস ও রেভিনিউ কত?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ])
            ->assertJsonStructure([
                'status',
                'answer',
                'execution_time',
            ]);

        $answer = $response->json('answer');
        $this->assertStringContainsString('আজকের বিক্রয় ও রেভিনিউ সারাংশ', $answer);
        $this->assertStringContainsString('BDT', $answer);
    }

    public function test_owner_can_inquire_about_inventory_stock(): void
    {
        AnalyticsProduct::create([
            'workspace_id'      => $this->workspace->id,
            'name'              => 'Silk Panjabi Navy Blue',
            'sku'               => 'PANJ-001',
            'stock_quantity'    => 2, // low stock
            'unit_price'        => 3500.0,
            'unit_cost'         => 2000.0,
            'reorder_threshold' => 5,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'কোন কোন আইটেমের স্টক কম রয়েছে?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $answer = $response->json('answer');
        $this->assertStringContainsString('Silk Panjabi Navy Blue', $answer);
        $this->assertStringContainsString('সতর্কতা', $answer);
    }

    public function test_owner_can_inquire_about_messages_and_costs_and_wallet_balance(): void
    {
        $quota = \App\Models\WorkspaceUsageQuota::currentForWorkspace($this->workspace->id);
        $quota->whatsapp_messages_sent = 120;
        $quota->messenger_messages_sent = 35;
        $quota->save();

        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'আমার কয়টা মেসেজ গেছে এবং কত টাকা খরচ হয়েছে?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $answer = $response->json('answer');
        $this->assertStringContainsString('মোট প্রেরিত মেসেজ', $answer);
        $this->assertStringContainsString('WhatsApp', $answer);
        $this->assertStringContainsString('মোট ব্যয় হয়েছে', $answer);
        $this->assertStringContainsString('অবশিষ্ট ওয়ালেট ব্যালেন্স', $answer);
    }

    public function test_virtual_cfo_resolves_multi_turn_context_and_anaphora_from_history(): void
    {
        AnalyticsProduct::create([
            'workspace_id'      => $this->workspace->id,
            'name'              => 'Premium Cotton Panjabi',
            'sku'               => 'COT-PANJ-01',
            'stock_quantity'    => 14,
            'unit_price'        => 2200.0,
            'unit_cost'         => 1200.0,
            'reorder_threshold' => 5,
        ]);

        // Simulated Turn 1: Bot mentioned "Premium Cotton Panjabi"
        $history = [
            [
                'role' => 'user',
                'text' => 'কোন প্রোডাক্টটি বেশি চলছে?',
            ],
            [
                'role' => 'assistant',
                'text' => "🔥 শীর্ষ বিক্রিত পণ্য:\n১. **Premium Cotton Panjabi** — ১৫ টি বিক্রি হয়েছে।",
            ],
        ];

        // Simulated Turn 2: User asks "এটার স্টক কত?" using anaphora
        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'এটার স্টক কত আছে?',
                'history'  => $history,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $answer = $response->json('answer');
        // Virtual CFO should have resolved "এটার" to "Premium Cotton Panjabi"
        $this->assertStringContainsString('Premium Cotton Panjabi', $answer);
        $this->assertStringContainsString('14', $answer);
        $this->assertStringContainsString('মজুদ পরিমাণ', $answer);
    }

    public function test_owner_can_inquire_about_profit_and_margins(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'আমার বিজনেসের নিট প্রফিট ও মার্জিন কত?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $answer = $response->json('answer');
        $this->assertStringContainsString('লাভ ও আর্থিক মার্জিন সারাংশ', $answer);
        $this->assertStringContainsString('গ্রস প্রফিট', $answer);
    }

    public function test_owner_can_ask_open_ended_business_strategy_without_error(): void
    {
        // Even if question does not match any keyword and LLM is offline, it never crashes
        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'আমার ব্যবসার সার্বিক বাণিজ্যিক উন্নতি এবং ভবিষ্যত পরিকল্পনা কেমন হওয়া উচিত?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $answer = $response->json('answer');
        $this->assertStringContainsString('ব্যবসার সার্বিক বাণিজ্যিক স্বাস্থ্য', $answer);
        $this->assertStringContainsString('কৌশলগত পরামর্শ', $answer);
    }

    public function test_asking_about_customer_due_clarifies_no_due_ledger_and_does_not_trigger_messaging_quota(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'গ্রাহকদের মোট বাকি বা ডিউ কত টাকা?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $answer = $response->json('answer');
        // Must NOT return the messaging quota answer
        $this->assertStringNotContainsString('আপনার ওয়ার্কস্পেসের মেসেজিং, খরচ ও ব্যালেন্স বিবরণী', $answer);
        $this->assertStringNotContainsString('WhatsApp (মেটা ফ্রি কোটা)', $answer);

        // Must clarify that customer due ledger does not apply in this business
        $this->assertStringContainsString('কাস্টমার বাকি বা বাকির খাতা (Customer Due Ledger) সক্রিয় নেই', $answer);
        $this->assertStringContainsString('ক্যাশ অন ডেলিভারি (COD)', $answer);
    }

    public function test_asking_about_customer_cohorts_returns_retention_metrics_without_dues(): void
    {
        \App\Models\AnalyticsCustomer::create([
            'workspace_id'    => $this->workspace->id,
            'name'            => 'Customer Alpha',
            'phone'           => '01811223399',
            'orders_count'    => 2,
            'total_spend'     => 4500.0,
            'current_balance' => 0.0,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson(route('owner-ai.ask'), [
                'question' => 'আমাদের নিয়মিত ও রিপিট কাস্টমার কেমন?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $answer = $response->json('answer');
        $this->assertStringContainsString('কাস্টমার অ্যানালিটিক্স ও রিটেনশন সারাংশ', $answer);
        $this->assertStringContainsString('রিপিট পারচেজ রেট', $answer);
        $this->assertStringNotContainsString('অপরিশোধিত বাকি', $answer);
    }
}
