<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * Render the Executive AI & Business Dashboard.
     */
    public function dashboard(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $workspaceId = ($user && $user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user?->workspace_id ?: 1);

        $range = (string) $request->input('range', '14d');
        $metrics = $this->dashboard->metrics($workspaceId, $range);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status'  => 'success',
                'data'    => $metrics,
            ]);
        }

        $workspace = $user?->workspace ?? \App\Models\Workspace::find($workspaceId);

        // Compute Onboarding Progress Checklist
        $hasProfile = !empty($workspace?->support_phone) || !empty($workspace?->support_email) || !empty($workspace?->business_address);
        $hasKnowledge = ($workspace?->faqs()->count() ?? 0) >= 2 || ($workspace?->knowledgeDocuments()->count() ?? 0) > 0;
        $hasSimulator = ($workspace?->channelAccounts()->withCount('conversations')->get()->sum('conversations_count') ?? 0) > 0;
        
        $hasApiKey = false;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('api_keys')) {
                $hasApiKey = ($workspace?->apiKeys()->count() ?? 0) > 0;
            }
        } catch (\Throwable) {
            $hasApiKey = false;
        }

        $checklist = [
            [
                'id'          => 'profile',
                'title'       => 'Configure Business Profile',
                'description' => 'Set your support phone, business address, and operating hours.',
                'completed'   => $hasProfile,
                'action_url'  => route('workspace.settings.edit'),
                'action_text' => 'Edit Profile',
                'icon'        => 'ri-building-line',
            ],
            [
                'id'          => 'knowledge',
                'title'       => 'Train Your AI Knowledge Base',
                'description' => 'Add custom business FAQs or upload product/policy PDFs and spreadsheets.',
                'completed'   => $hasKnowledge,
                'action_url'  => route('knowledge-base.index'),
                'action_text' => 'Manage Knowledge',
                'icon'        => 'ri-book-open-line',
            ],
            [
                'id'          => 'simulator',
                'title'       => 'Test in AI Chat Simulator',
                'description' => 'Simulate customer conversations and verify AI responses in real-time.',
                'completed'   => $hasSimulator,
                'action_url'  => route('simulator.index'),
                'action_text' => 'Open Simulator',
                'icon'        => 'ri-robot-2-line',
            ],
            [
                'id'          => 'widget',
                'title'       => 'Deploy Storefront Web Widget',
                'description' => 'Copy the 1-line script tag to embed your live customer assistant on your website.',
                'completed'   => true, // Default widget is auto-provisioned
                'action_url'  => route('widget.manage'),
                'action_text' => 'View Widget Code',
                'icon'        => 'ri-code-s-slash-line',
            ],
            [
                'id'          => 'api',
                'title'       => 'Generate Developer API Key',
                'description' => 'Connect external mobile apps, Shopify/WooCommerce, or custom ERP systems.',
                'completed'   => $hasApiKey,
                'action_url'  => route('api-keys.index'),
                'action_text' => 'API Credentials',
                'icon'        => 'ri-terminal-box-line',
            ],
        ];

        $completedCount = count(array_filter($checklist, fn($item) => $item['completed']));
        $totalSteps = count($checklist);
        $progressPercent = (int) round(($completedCount / $totalSteps) * 100);
        $allCompleted = $completedCount === $totalSteps;

        return view('admin.dashboard', [
            'metrics'      => $metrics,
            'currentRange' => $range,
            'workspace'    => $workspace,
            'onboarding'   => [
                'steps'         => $checklist,
                'completed'     => $completedCount,
                'total'         => $totalSteps,
                'percent'       => $progressPercent,
                'all_completed' => $allCompleted,
            ],
        ]);
    }

    public function cacheClear()
    {
        Artisan::call('optimize:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        return back()->with('success', 'Cache Cleared Successfully');
    }

    public function changePassword()
    {
        return view('profile.change-password');
    }
}
