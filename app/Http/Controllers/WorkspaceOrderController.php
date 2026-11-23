<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceOrderController extends Controller
{
    /**
     * Show Orders & Captured Leads Hub.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        $query = WorkspaceOrder::where('workspace_id', $workspace->id);

        // Status counts for tab badges
        $counts = [
            'all'       => (clone $query)->count(),
            'pending'   => (clone $query)->where('status', 'pending')->count(),
            'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
            'shipped'   => (clone $query)->where('status', 'shipped')->count(),
            'delivered' => (clone $query)->where('status', 'delivered')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
        ];

        // Filter by status
        if ($request->filled('status') && in_array($request->input('status'), ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])) {
            $query->where('status', $request->input('status'));
        }

        // Search query
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('product_summary', 'like', "%{$q}%")
                    ->orWhere('delivery_address', 'like', "%{$q}%");
            });
        }

        $orders = $query->latest('captured_at')->paginate(15)->withQueryString();

        return view('admin.orders.index', [
            'workspace'     => $workspace,
            'orders'        => $orders,
            'counts'        => $counts,
            'currentStatus' => $request->input('status', 'all'),
            'searchQuery'   => $request->input('q', ''),
        ]);
    }

    /**
     * Update order delivery / fulfillment status.
     */
    public function updateStatus(Request $request, WorkspaceOrder $order): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole(RoleEnum::SUPERADMIN->value) && $order->workspace_id !== $user->workspace_id) {
            abort(403, 'Unauthorized access to order.');
        }

        $validated = $request->validate([
            'status'      => ['required', 'string', 'in:pending,confirmed,shipped,delivered,cancelled'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order->status = $validated['status'];
        if (isset($validated['admin_notes'])) {
            $order->admin_notes = $validated['admin_notes'];
        }
        $order->save();

        if ($order->status === 'delivered') {
            app(\App\Services\Retention\PostDeliveryFeedbackService::class)->dispatchFeedbackRequest($order);
        }

        return redirect()->back()->with('success', "Order #{$order->order_number} status updated to " . strtoupper($order->status) . ".");
    }

    /**
     * Export Orders to CSV for Steadfast / Pathao parcel upload.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: 1);

        $query = WorkspaceOrder::where('workspace_id', $workspaceId);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->latest('captured_at')->get();

        $filename = 'orders_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order ID',
                'Date',
                'Channel',
                'Customer Name',
                'Customer Phone',
                'Delivery Address',
                'Product Summary',
                'Order Amount',
                'Delivery Fee',
                'Status',
                'Customer Notes',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    $order->captured_at ? $order->captured_at->format('Y-m-d H:i') : '',
                    strtoupper($order->channel),
                    $order->customer_name,
                    $order->customer_phone,
                    $order->delivery_address,
                    $order->product_summary,
                    $order->order_amount,
                    $order->delivery_fee,
                    strtoupper($order->status),
                    $order->customer_notes,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
