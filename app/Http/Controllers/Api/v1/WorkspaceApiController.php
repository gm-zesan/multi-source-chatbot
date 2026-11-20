<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceApiController extends Controller
{
    /**
     * Get workspace metadata, active plan, and usage quota.
     */
    public function info(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        if (! $workspace) {
            return new JsonResponse(['success' => false, 'error' => 'Workspace context not found'], 400);
        }

        $subscription = WorkspaceSubscription::with('plan')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        $currentPeriod = Carbon::now()->format('Y-m');
        $quota = WorkspaceUsageQuota::where('workspace_id', $workspace->id)
            ->where('period_year_month', $currentPeriod)
            ->first();

        $maxConversations = $subscription?->plan?->max_conversations_monthly ?? 500;
        $usedConversations = $quota?->conversation_count ?? 0;
        $remaining = $maxConversations === -1 ? 'unlimited' : max(0, $maxConversations - $usedConversations);

        return new JsonResponse([
            'success' => true,
            'data'    => [
                'workspace' => [
                    'id'               => $workspace->id,
                    'name'             => $workspace->name,
                    'slug'             => $workspace->slug,
                    'currency'         => $workspace->currency,
                    'support_phone'    => $workspace->support_phone,
                    'support_email'    => $workspace->support_email,
                    'operating_hours'  => $workspace->operating_hours,
                ],
                'subscription' => [
                    'plan_name'     => $subscription?->plan?->name ?? 'Free Trial',
                    'status'        => $subscription?->status ?? 'active',
                    'billing_cycle' => $subscription?->billing_cycle ?? 'monthly',
                    'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
                    'ends_at'       => $subscription?->ends_at?->toIso8601String(),
                ],
                'usage_quota' => [
                    'period'             => $currentPeriod,
                    'used_conversations' => $usedConversations,
                    'max_conversations'  => $maxConversations === -1 ? 'unlimited' : $maxConversations,
                    'remaining'          => $remaining,
                ],
            ],
        ]);
    }
}
