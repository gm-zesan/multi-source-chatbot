<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\Search\TypesenseService;
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
        $this->app->singleton(EmbeddingService::class, function () {
            return new EmbeddingService(
                config: config('embedding'),
            );
        });

        $this->app->singleton(TypesenseService::class, function () {
            return new TypesenseService(
                config: config('typesense'),
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

        $this->validateConfiguration();
    }

    /**
     * Validate critical service configurations at boot.
     *
     * Logs warnings for issues — does NOT crash the application.
     */
    private function validateConfiguration(): void
    {
        // Embedding dimensions check
        $configuredDim = (int) config('embedding.dimensions', 0);
        if ($configuredDim !== 768) {
            Log::warning('[Config] Embedding dimensions mismatch', [
                'configured' => $configuredDim,
                'expected'   => 768,
                'model'      => config('embedding.model', 'unknown'),
                'hint'       => 'Set CHATBOT_EMBEDDING_DIMENSIONS=768 in .env for paraphrase-multilingual-mpnet-base-v2',
            ]);
        }

        // Embedding service URL validation
        $embeddingUrl = config('embedding.base_url', '');
        if (empty($embeddingUrl)) {
            Log::warning('[Config] Embedding service URL is not configured');
        }

        // Typesense configuration validation
        $typesenseHost = config('typesense.host', '');
        $typesenseKey = config('typesense.api_key', '');
        if (empty($typesenseHost)) {
            Log::warning('[Config] Typesense host is not configured');
        }
        if (empty($typesenseKey)) {
            Log::warning('[Config] Typesense API key is not configured');
        }
    }
}
