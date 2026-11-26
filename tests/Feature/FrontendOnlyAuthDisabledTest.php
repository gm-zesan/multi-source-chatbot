<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendOnlyAuthDisabledTest extends TestCase
{
    use RefreshDatabase;

    private function disablePublicAuth(): void
    {
        config([
            'auth.login_enabled' => false,
            'auth.registration_enabled' => false,
        ]);
    }

    public function test_login_and_registration_endpoints_are_blocked_when_disabled(): void
    {
        $this->disablePublicAuth();

        $user = User::factory()->create();

        // GET /login gracefully redirects to home; POST /login returns 404
        $this->get('/login')->assertRedirect(route('home'));
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertNotFound();
        $this->assertGuest();

        // GET /register gracefully redirects to contact; POST /register returns 404
        $this->get('/register')->assertRedirect(route('contact'));
        $this->post('/register', [
            'name' => 'Blocked User',
            'business_name' => 'Blocked Co',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_social_auth_and_password_reset_endpoints_return_404_when_login_disabled(): void
    {
        $this->disablePublicAuth();

        $this->get('/auth/google/redirect')->assertNotFound();
        $this->get('/auth/facebook/redirect')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'user@example.com'])->assertNotFound();
        $this->get('/reset-password/sample-token')->assertNotFound();
        $this->post('/reset-password', [
            'token' => 'sample-token',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
    }

    public function test_public_pages_hide_login_and_register_links_when_auth_disabled(): void
    {
        $this->disablePublicAuth();

        SubscriptionPlan::create([
            'name' => 'Starter Plan',
            'slug' => 'starter-bonus',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'cta_text' => 'Create Free Account',
            'cta_url' => '/register',
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        $publicRoutes = [
            'home',
            'platform',
            'knowledge-engine',
            'unified-inbox',
            'business-intelligence',
            'automation',
            'integrations',
            'pricing',
        ];

        $loginUrl = route('login');
        $registerUrl = route('register');

        foreach ($publicRoutes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertOk();
            $response->assertDontSee("href=\"{$loginUrl}\"", false);
            $response->assertDontSee("href=\"{$registerUrl}\"", false);
            $response->assertDontSee('href="' . url('/register') . '"', false);
        }

        // Pricing page should still expose Contact Sales CTA instead of /register
        $pricingResponse = $this->get(route('pricing'));
        $pricingResponse->assertOk();
        $pricingResponse->assertSee('href="' . route('contact') . '"', false);
    }

    public function test_login_page_hides_registration_link_in_pilot_mode(): void
    {
        config([
            'auth.login_enabled' => true,
            'auth.registration_enabled' => false,
        ]);

        $loginResponse = $this->get('/login');
        $loginResponse->assertOk();
        $loginResponse->assertDontSee(route('register'), false);
        $loginResponse->assertDontSee('Create an account', false);

        $this->get('/register')->assertRedirect(route('contact'));
    }
}
