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
use Illuminate\Support\Facades\Log;
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

    /**
     * Inbound Webhook: Steadfast Courier Delivery Status Callback.
     */
    public function handleSteadfastWebhook(Request $request): JsonResponse
    {
        $invoice = trim((string) ($request->input('invoice') ?? $request->input('order_id') ?? ''));
        $trackingCode = trim((string) ($request->input('tracking_code') ?? $request->input('consignment_id') ?? ''));
        $rawStatus = strtolower(trim((string) ($request->input('status') ?? '')));

        if ($invoice === '' && $trackingCode === '') {
            return response()->json(['success' => false, 'message' => 'Missing invoice or tracking identifier.'], 400);
        }

        $order = WorkspaceOrder::where(function ($q) use ($invoice, $trackingCode) {
            if ($invoice !== '') {
                $q->where('order_number', $invoice);
            }
            if ($trackingCode !== '') {
                $q->orWhere('courier_tracking_code', $trackingCode)
                  ->orWhere('courier_consignment_id', $trackingCode);
            }
        })->first();

        if (! $order) {
            Log::warning('[Steadfast Webhook] Order not found for incoming webhook', [
                'invoice'       => $invoice,
                'tracking_code' => $trackingCode,
                'status'        => $rawStatus,
            ]);
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $order->courier_status = $rawStatus;
        $order->status = match ($rawStatus) {
            'delivered'                              => 'delivered',
            'cancelled', 'cancelled_order', 'return' => 'cancelled',
            'in_review', 'pending', 'picked'         => 'shipped',
            default                                  => $order->status,
        };

        $timestamp = now()->format('d M Y, h:i A');
        $note = "\n[Steadfast Webhook: {$rawStatus} at {$timestamp}]";
        $order->admin_notes = ($order->admin_notes ?: '') . $note;
        $order->save();

        // BI Canonical Projection: Project courier status update into AnalyticsOrder & AnalyticsPayment
        try {
            app(\App\Services\Analytics\OrderAnalyticsProjectionService::class)->project($order);
        } catch (\Throwable $e) {
            Log::warning('[Steadfast Webhook] Failed to project to AnalyticsOrder: ' . $e->getMessage());
        }

        if ($order->status === 'delivered') {
            app(\App\Services\Retention\PostDeliveryFeedbackService::class)->dispatchFeedbackRequest($order);
        }

        Log::info('[Steadfast Webhook] Order status synchronized successfully', [
            'order'          => $order->order_number,
            'courier_status' => $rawStatus,
            'order_status'   => $order->status,
        ]);

        return response()->json([
            'success'        => true,
            'order_number'   => $order->order_number,
            'order_status'   => $order->status,
            'courier_status' => $order->courier_status,
        ]);
    }

    /**
     * Inbound Webhook: Pathao Courier Delivery Status Callback.
     */
    public function handlePathaoWebhook(Request $request): JsonResponse
    {
        $consignmentId = trim((string) ($request->input('consignment_id') ?? ''));
        $merchantOrderId = trim((string) ($request->input('merchant_order_id') ?? ''));
        $orderStatus = strtolower(trim((string) ($request->input('order_status') ?? '')));

        if ($consignmentId === '' && $merchantOrderId === '') {
            return response()->json(['success' => false, 'message' => 'Missing identifiers.'], 400);
        }

        $order = WorkspaceOrder::where(function ($q) use ($consignmentId, $merchantOrderId) {
            if ($merchantOrderId !== '') {
                $q->where('order_number', $merchantOrderId);
            }
            if ($consignmentId !== '') {
                $q->orWhere('courier_consignment_id', $consignmentId)
                  ->orWhere('courier_tracking_code', $consignmentId);
            }
        })->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $order->courier_status = $orderStatus;
        if (str_contains($orderStatus, 'delivered')) {
            $order->status = 'delivered';
        } elseif (str_contains($orderStatus, 'return') || str_contains($orderStatus, 'cancelled')) {
            $order->status = 'cancelled';
        }

        $timestamp = now()->format('d M Y, h:i A');
        $order->admin_notes = ($order->admin_notes ?: '') . "\n[Pathao Webhook: {$orderStatus} at {$timestamp}]";
        $order->save();

        // BI Canonical Projection: Project Pathao courier status update into AnalyticsOrder & AnalyticsPayment
        try {
            app(\App\Services\Analytics\OrderAnalyticsProjectionService::class)->project($order);
        } catch (\Throwable $e) {
            Log::warning('[Pathao Webhook] Failed to project to AnalyticsOrder: ' . $e->getMessage());
        }

        if ($order->status === 'delivered') {
            app(\App\Services\Retention\PostDeliveryFeedbackService::class)->dispatchFeedbackRequest($order);
        }

        return response()->json(['success' => true, 'order_number' => $order->order_number]);
    }

    /**
     * Sync courier status for a single order on-demand.
     */
    public function syncStatus(Request $request, WorkspaceOrder $order): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (! $user->hasRole(RoleEnum::SUPERADMIN->value) && $order->workspace_id !== $user->workspace_id) {
            abort(403, 'Unauthorized access to order.');
        }

        return response()->json([
            'success'        => true,
            'order_number'   => $order->order_number,
            'status'         => $order->status,
            'courier_status' => $order->courier_status ?: 'pending',
            'tracking_code'  => $order->courier_tracking_code ?: $order->courier_consignment_id,
            'tracking_url'   => $order->getCourierTrackingUrl(),
        ]);
    }
}
