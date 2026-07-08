<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\NLP\Embedding\EmbeddingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmbeddingService::class, function () {
            return new EmbeddingService(
                config: config('chatbot.embedding'),
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
    }
}
