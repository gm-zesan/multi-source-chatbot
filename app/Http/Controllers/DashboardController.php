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

        return view('admin.dashboard', [
            'metrics'      => $metrics,
            'currentRange' => $range,
            'workspace'    => $user?->workspace,
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
