<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\AI\Agents\OwnerBusinessAgent;
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

        // Register BO-2 Governed Agent
        $this->app->singleton(OwnerBusinessAgent::class, function ($app) {
            return new OwnerBusinessAgent();
        });

        // Register Unified AI Decision Engine & Orchestration
        $this->app->singleton(\App\AI\Decision\Contracts\DecisionEngineInterface::class, \App\AI\Decision\UnifiedDecisionEngine::class);
        $this->app->singleton(\App\AI\Decision\ToolRegistry::class);
        $this->app->singleton(\App\AI\Decision\UnifiedOrchestrator::class);
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

        // Governed Agent Runtime: Clean up ambient context on request and queue lifecycle termination
        $this->app->terminating(function () {
            \App\AI\DTOs\AgentContext::clear();
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobProcessed::class, function () {
            \App\AI\DTOs\AgentContext::clear();
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobFailed::class, function () {
            \App\AI\DTOs\AgentContext::clear();
        });

        $this->validateConfiguration();
        $this->registerLegacyArchitectureAliases();
    }

    /**
     * Backward-compatibility class aliases for reorganized AI domain namespaces.
     */
    private function registerLegacyArchitectureAliases(): void
    {
        $aliases = [
            'App\AI\Services\Enums\AnswerabilityStatus' => \App\Enums\AI\AnswerabilityStatus::class,
            'App\AI\Services\DTOs\AnswerabilityDecision' => \App\AI\DTOs\AnswerabilityDecision::class,
            'App\AI\Services\DTOs\ContextualResolutionResult' => \App\AI\DTOs\ContextualResolutionResult::class,
            'App\AI\Services\DTOs\FollowUpDecision' => \App\AI\DTOs\FollowUpDecision::class,
            'App\AI\Services\DTOs\SellerEmailProposal' => \App\AI\DTOs\SellerEmailProposal::class,
            'App\AI\Services\Evaluation\GenerationFaithfulnessEvaluator' => \App\AI\Evaluation\GenerationFaithfulnessEvaluator::class,
            'App\AI\Services\ClarificationManager' => \App\AI\Context\ClarificationManager::class,
            'App\AI\Services\ContextualQueryBuilder' => \App\AI\Context\ContextualQueryBuilder::class,
            'App\AI\Services\SemanticAnswerabilityGate' => \App\AI\Guards\SemanticAnswerabilityGate::class,
            'App\AI\Services\ActionSafetyService' => \App\AI\Guards\ActionSafetyService::class,
            'App\AI\Services\FollowUpPolicyManager' => \App\AI\Guards\FollowUpPolicyManager::class,
            'App\AI\Services\CustomerSupportService' => \App\AI\Orchestration\CustomerSupportService::class,
            'App\AI\Services\SellerEmailService' => \App\AI\Orchestration\SellerEmailService::class,
        ];

        foreach ($aliases as $legacy => $modern) {
            if (!class_exists($legacy, false) && !interface_exists($legacy, false) && !enum_exists($legacy, false)) {
                class_alias($modern, $legacy);
            }
        }
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
