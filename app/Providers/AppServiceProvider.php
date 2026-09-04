<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RetrievalClient::class, function () {
            return new RetrievalClient(
                baseUrl: config('retrieval.base_url'),
                apiKey: config('retrieval.api_key'),
                timeout: (int) config('retrieval.timeout', 15),
                defaultTopK: (int) config('retrieval.top_k', 5),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin: Grant all permissions via Gate::before()
        // Follows Spatie's official recommendation:
        // https://spatie.be/docs/laravel-permission/v8/basic-usage/super-admin
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(RoleEnum::SUPERADMIN->value) ? true : null;
        });

        // Automatically track login time and IP address
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function (\Illuminate\Auth\Events\Login $event) {
            if ($event->user instanceof User) {
                $event->user->last_login_at = now();
                $event->user->last_login_ip = request()->ip();
                $event->user->saveQuietly();
            }
        });

        $this->validateConfiguration();
    }

    /**
     * Validate critical service configurations at boot.
     *
     * Logs warnings for issues — does NOT crash the application.
     */
    private function validateConfiguration(): void
    {
        $retrievalUrl = config('retrieval.base_url', '');
        if (empty($retrievalUrl)) {
            Log::warning('[Config] Python Retrieval Service URL is not configured');
        }
    }
}
