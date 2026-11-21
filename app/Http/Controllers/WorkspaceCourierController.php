<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Courier\CourierManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkspaceCourierController extends Controller
{
    public function __construct(
        protected CourierManager $courierManager,
    ) {}

    /**
     * Show Courier Settings Page (Steadfast, Pathao, RedX).
     */
    public function settings(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user || (! $user->hasRole(RoleEnum::SUPERADMIN->value) && ! $user->hasRole(RoleEnum::ADMIN->value))) {
            abort(403, 'Unauthorized. Only workspace owners can manage courier integrations.');
        }

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);
        $settings  = $this->courierManager->getCourierSettings($workspace);
        $providers = $this->courierManager->getAvailableProviders();

        return view('admin.workspace.courier', [
            'workspace'    => $workspace,
            'settings'     => $settings,
            'providers'    => $providers,
            'isSuperAdmin' => $user->hasRole(RoleEnum::SUPERADMIN->value),
        ]);
    }

    /**
     * Save Courier Settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user || (! $user->hasRole(RoleEnum::SUPERADMIN->value) && ! $user->hasRole(RoleEnum::ADMIN->value))) {
            abort(403, 'Unauthorized. Only workspace owners can update courier settings.');
        }

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        $validated = $request->validate([
            'default_provider'          => 'required|string|in:steadfast,pathao,redx',
            'steadfast.api_key'         => 'nullable|string|max:255',
            'steadfast.secret_key'      => 'nullable|string|max:255',
            'steadfast.is_active'       => 'nullable|boolean',
            'pathao.client_id'          => 'nullable|string|max:255',
            'pathao.client_secret'      => 'nullable|string|max:255',
            'pathao.username'           => 'nullable|string|max:255',
            'pathao.password'           => 'nullable|string|max:255',
            'pathao.store_id'           => 'nullable|string|max:100',
            'pathao.is_active'          => 'nullable|boolean',
            'redx.token'                => 'nullable|string|max:1000',
            'redx.pickup_store_id'      => 'nullable|string|max:100',
            'redx.is_active'            => 'nullable|boolean',
        ]);

        $this->courierManager->saveCourierSettings($workspace, $validated);

        return redirect()->route('workspace.courier.settings', $user->hasRole(RoleEnum::SUPERADMIN->value) ? ['workspace_id' => $workspaceId] : [])
            ->with('success', 'Courier integration settings updated successfully!');
    }

    /**
     * Test / Ping Courier API Credentials.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $provider = (string) $request->input('provider', 'steadfast');
        $credentials = (array) $request->input('credentials', []);

        try {
            $driver = $this->courierManager->getDriver($provider);
            $result = $driver->ping($credentials);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Courier test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Dispatch a single order to courier.
     */
    public function sendSingle(Request $request, WorkspaceOrder $order): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        // Tenant Isolation
        if (! $user->hasRole(RoleEnum::SUPERADMIN->value) && $order->workspace_id !== $user->workspace_id) {
            abort(403, 'Unauthorized access to order outside your workspace.');
        }

        $provider = $request->input('provider');
        $result = $this->courierManager->dispatchOrder($order, $provider);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'       => $result->success,
                'message'       => $result->success
                    ? "Order {$order->order_number} successfully dispatched to {$result->provider}! (Tracking: {$result->trackingCode})"
                    : $result->errorMessage,
                'tracking_code' => $result->trackingCode,
                'tracking_url'  => $order->getCourierTrackingUrl(),
                'provider'      => $result->provider,
            ]);
        }

        if ($result->success) {
            return back()->with('success', "Order {$order->order_number} successfully booked on {$result->provider} (Tracking Code: {$result->trackingCode})");
        }

        return back()->with('error', "Failed to dispatch order to courier: {$result->errorMessage}");
    }

    /**
     * Bulk Dispatch multiple orders to courier.
     */
    public function bulkSend(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:workspace_orders,id',
            'provider'    => 'nullable|string|in:steadfast,pathao,redx',
        ]);

        $query = WorkspaceOrder::whereIn('id', $validated['order_ids']);
        if (! $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            $query->where('workspace_id', $user->workspace_id);
        }

        $orders = $query->get();
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible orders found for dispatch.',
            ], 422);
        }

        $summary = $this->courierManager->bulkDispatch($orders, $validated['provider'] ?? null);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => $summary['successful'] > 0,
                'summary' => $summary,
                'message' => "Bulk dispatch complete! {$summary['successful']} dispatched, {$summary['failed']} failed.",
            ]);
        }

        $msg = "Bulk Courier Dispatch Complete: {$summary['successful']} orders booked successfully.";
        if ($summary['failed'] > 0) {
            $msg .= " ({$summary['failed']} orders failed to book - check order details).";
        }

        return back()->with($summary['successful'] > 0 ? 'success' : 'error', $msg);
    }
}
