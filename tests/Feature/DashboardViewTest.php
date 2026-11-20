<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_executive_dashboard_without_errors(): void
    {
        $role = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        $workspace = Workspace::create([
            'name'      => 'Aura Lifestyle',
            'slug'      => 'aura-lifestyle',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'email'        => 'admin@aura.test',
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Executive Overview');
        $response->assertSee('Aura Lifestyle');
        $response->assertSee('chartConversationActivity');
        $response->assertSee('chartChannelDistribution');
        $response->assertDontSee('ParseError');
    }

    public function test_dashboard_ajax_returns_activity_range_json(): void
    {
        $role = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        $workspace = Workspace::create([
            'name'      => 'Aura Lifestyle',
            'slug'      => 'aura-lifestyle',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'workspace_id' => $workspace->id,
            'email'        => 'admin2@aura.test',
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($user)->getJson(route('dashboard', ['range' => '7d']));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonStructure([
            'status',
            'data' => [
                'overview',
                'activity',
                'channels',
                'ai_performance',
            ],
        ]);
    }
}
