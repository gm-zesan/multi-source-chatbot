<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionQuotaService
{
    /**
     * Get active subscription for a workspace.
     */
    public function getActiveSubscription(int $workspaceId): ?WorkspaceSubscription
    {
        return WorkspaceSubscription::with('plan')
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('starts_at')
            ->first();
    }

    /**
     * Determine if a workspace has quota to start a new conversation.
     */
    public function canCreateConversation(int $workspaceId): bool
    {
        $sub = $this->getActiveSubscription($workspaceId);

        // If no active subscription, check if free tier plan exists or fall back to default limit (100)
        $limit = $sub?->plan?->max_conversations_monthly ?? 100;

        // -1 represents unlimited
        if ($limit === -1) {
            return true;
        }

        $quota = WorkspaceUsageQuota::currentForWorkspace($workspaceId);

        return $quota->conversation_count < $limit;
    }

    /**
     * Record a new conversation for quota tracking.
     */
    public function recordConversationUsage(int $workspaceId): void
    {
        $quota = WorkspaceUsageQuota::currentForWorkspace($workspaceId);
        $quota->increment('conversation_count');
    }

    /**
     * Record an AI message processed for quota tracking.
     */
    public function recordAiMessageUsage(int $workspaceId): void
    {
        $quota = WorkspaceUsageQuota::currentForWorkspace($workspaceId);
        $quota->increment('ai_messages_count');
    }

    /**
     * Check if a workspace's current plan includes a specific feature.
     */
    public function hasFeature(int $workspaceId, string $feature): bool
    {
        $sub = $this->getActiveSubscription($workspaceId);
        if (! $sub || ! $sub->plan) {
            return false;
        }

        return $sub->plan->hasFeature($feature);
    }

    /**
     * Subscribe a workspace to a plan.
     */
    public function subscribe(
        Workspace $workspace,
        SubscriptionPlan $plan,
        string $billingCycle = 'monthly',
        string $gateway = 'manual',
        ?string $gatewaySubId = null
    ): WorkspaceSubscription {
        return DB::transaction(function () use ($workspace, $plan, $billingCycle, $gateway, $gatewaySubId) {
            // Cancel any previously active subscriptions
            WorkspaceSubscription::where('workspace_id', $workspace->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);

            $durationMonths = $billingCycle === 'yearly' ? 12 : 1;
            $endsAt = Carbon::now()->addMonths($durationMonths);

            return WorkspaceSubscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'payment_gateway' => $gateway,
                'gateway_subscription_id' => $gatewaySubId,
            ]);
        });
    }

    /**
     * Process incoming billing webhook (Stripe, bKash, SSLCommerz).
     */
    public function handleBillingWebhook(string $gateway, array $payload): array
    {
        Log::info("[SubscriptionQuotaService] Processing billing webhook from {$gateway}", ['payload' => $payload]);

        $workspaceId = (int) ($payload['workspace_id'] ?? 0);
        $planSlug = (string) ($payload['plan_slug'] ?? 'starter');
        $status = (string) ($payload['status'] ?? 'active');
        $transactionId = (string) ($payload['transaction_id'] ?? $payload['payment_id'] ?? '');

        if ($workspaceId <= 0) {
            return ['success' => false, 'error' => 'Missing workspace_id in webhook payload'];
        }

        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return ['success' => false, 'error' => "Workspace #{$workspaceId} not found"];
        }

        $plan = SubscriptionPlan::where('slug', $planSlug)->first();
        if (! $plan) {
            return ['success' => false, 'error' => "Plan '{$planSlug}' not found"];
        }

        $sub = $this->subscribe(
            workspace: $workspace,
            plan: $plan,
            billingCycle: (string) ($payload['billing_cycle'] ?? 'monthly'),
            gateway: $gateway,
            gatewaySubId: $transactionId
        );

        return [
            'success' => true,
            'subscription_id' => $sub->id,
            'status' => $sub->status,
            'workspace_id' => $workspace->id,
            'plan' => $plan->name,
        ];
    }
}
