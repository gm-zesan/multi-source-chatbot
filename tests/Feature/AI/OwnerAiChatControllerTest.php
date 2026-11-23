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
                'question' => 'আজকের সেলস ও রেভিনিউ কত?',
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
}
