@extends('admin.app')

@section('title', 'Orders & Captured Leads — Entrepreneurs Automation')

@section('content')
<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-shopping-bag-3-line text-brand-500 text-xl"></i>
                <span>Orders & Captured Leads</span>
                <span class="app-badge app-badge-primary">{{ $workspace->name }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-0.5">
                Automated customer orders from AI chat with 1-click single and bulk Steadfast, Pathao & RedX dispatch.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('workspace.courier.settings') }}" class="app-btn app-btn-secondary text-xs flex items-center gap-1.5 shadow-xs hover:border-brand-500">
                <i class="ri-truck-line text-brand-500"></i>
                <span>Courier Settings</span>
            </a>
            <a href="{{ route('orders.export', request()->all()) }}" class="app-btn app-btn-secondary text-xs flex items-center gap-1.5 shadow-xs">
                <i class="ri-file-download-line text-emerald-500"></i>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="alert p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-checkbox-circle-line text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-500 hover:text-emerald-400" data-bs-dismiss="alert">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert p-3 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-500 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-error-warning-line text-base"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="text-rose-500 hover:text-rose-400" data-bs-dismiss="alert">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
    @endif

    {{-- Filter Tabs & Search Bar --}}
    <div class="app-card p-3 space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            {{-- Status Tabs --}}
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar">
                @php
                    $tabs = [
                        'all'       => ['label' => 'All Orders', 'count' => $counts['all']],
                        'pending'   => ['label' => 'Pending', 'count' => $counts['pending']],
                        'confirmed' => ['label' => 'Confirmed', 'count' => $counts['confirmed']],
                        'shipped'   => ['label' => 'Shipped', 'count' => $counts['shipped']],
                        'delivered' => ['label' => 'Delivered', 'count' => $counts['delivered']],
                        'cancelled' => ['label' => 'Cancelled', 'count' => $counts['cancelled']],
                    ];
                @endphp

                @foreach($tabs as $key => $tab)
                    @php
                        $isActive = ($currentStatus === $key) || ($key === 'all' && empty($currentStatus));
                    @endphp
                    <a 
                        href="{{ route('orders.index', array_merge(request()->except(['status', 'page']), ['status' => $key])) }}" 
                        class="px-2.5 py-1.5 rounded-lg text-2xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 {{ $isActive ? 'bg-brand-500 text-white shadow-xs' : 'bg-light-canvas dark:bg-dark-canvas text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text border border-light-border dark:border-dark-border' }}"
                    >
                        <span>{{ $tab['label'] }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-3xs font-mono {{ $isActive ? 'bg-white/20 text-white' : 'bg-light-border dark:bg-dark-border text-light-text dark:text-dark-text' }}">
                            {{ $tab['count'] }}
                        </span>
                    </a>
                @endforeach
            </div>

            {{-- Search Input --}}
            <form method="GET" action="{{ route('orders.index') }}" class="flex items-center gap-2 flex-shrink-0">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="relative w-full sm:w-64">
                    <i class="ri-search-line absolute left-2.5 top-1/2 -translate-y-1/2 text-light-muted dark:text-dark-muted text-xs"></i>
                    <input 
                        type="text" 
                        name="q" 
                        value="{{ $searchQuery }}" 
                        placeholder="Search phone, name, order #..." 
                        class="app-input !pl-8 !py-1 text-2xs w-full"
                    >
                </div>
                <button type="submit" class="app-btn app-btn-secondary !h-7 !px-2 text-2xs">Filter</button>
                @if($searchQuery)
                    <a href="{{ route('orders.index', ['status' => $currentStatus]) }}" class="text-rose-500 text-2xs hover:underline">Clear</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Bulk Action Bar (Hidden until rows are selected) --}}
    <div id="bulkCourierActionBar" class="hidden p-3 rounded-xl border border-brand-500/30 bg-brand-500/10 backdrop-blur-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-md transition-all">
        <div class="flex items-center gap-2 text-xs font-semibold text-brand-600 dark:text-brand-400">
            <i class="ri-checkbox-circle-fill text-base"></i>
            <span><span id="selectedCountText" class="font-mono font-bold">0</span> Orders Selected for Dispatch</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5">
                <span class="text-2xs text-light-muted dark:text-dark-muted font-medium">Select Courier:</span>
                <select id="bulkCourierProvider" class="app-select !py-1 !h-8 text-2xs font-semibold">
                    <option value="steadfast">Steadfast Courier</option>
                    <option value="pathao">Pathao Courier</option>
                    <option value="redx">RedX Logistics</option>
                </select>
            </div>
            <button 
                type="button" 
                id="btnExecuteBulkDispatch" 
                class="app-btn app-btn-primary !h-8 !px-3 text-2xs shadow-xs" 
                onclick="executeBulkCourierDispatch()"
            >
                <i class="ri-rocket-line"></i>
                <span>Bulk Dispatch to Courier</span>
            </button>
            <button 
                type="button" 
                class="app-btn app-btn-secondary !h-8 !px-2 text-2xs" 
                onclick="deselectAllOrders()"
            >
                Cancel
            </button>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="app-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs" id="ordersTable">
                <thead class="bg-light-canvas/70 dark:bg-dark-canvas/70 border-b border-light-border dark:border-dark-border text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-semibold">
                    <tr>
                        <th class="py-2.5 px-3 w-10 text-center">
                            <input type="checkbox" id="selectAllCheckbox" class="app-checkbox" title="Select All">
                        </th>
                        <th class="py-2.5 px-3">Order / Channel</th>
                        <th class="py-2.5 px-3">Customer Details</th>
                        <th class="py-2.5 px-3">Delivery Address</th>
                        <th class="py-2.5 px-3">Product / Inquiry</th>
                        <th class="py-2.5 px-3">Amount</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-border dark:divide-dark-border text-light-text dark:text-dark-text">
                    @forelse($orders as $order)
                        <tr class="hover:bg-light-canvas/40 dark:hover:bg-dark-canvas/40 transition">
                            {{-- Checkbox --}}
                            <td class="py-3 px-3 text-center">
                                <input 
                                    type="checkbox" 
                                    class="order-row-checkbox app-checkbox" 
                                    value="{{ $order->id }}"
                                    data-dispatched="{{ $order->isDispatchedToCourier() ? '1' : '0' }}"
                                    onchange="updateBulkActionState()"
                                >
                            </td>

                            {{-- Order ID & Channel --}}
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="font-mono font-bold text-light-text dark:text-dark-text text-xs flex items-center gap-1.5">
                                    <span>{{ $order->order_number }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1 text-3xs text-light-muted dark:text-dark-muted">
                                    <span class="app-badge app-badge-secondary font-mono uppercase text-3xs">
                                        @if($order->channel === 'facebook')
                                            <i class="ri-facebook-circle-fill text-blue-500 mr-0.5"></i> Facebook
                                        @elseif($order->channel === 'whatsapp')
                                            <i class="ri-whatsapp-fill text-emerald-500 mr-0.5"></i> WhatsApp
                                        @elseif($order->channel === 'telegram')
                                            <i class="ri-telegram-fill text-sky-400 mr-0.5"></i> Telegram
                                        @elseif($order->channel === 'tiktok')
                                            <i class="ri-tiktok-fill text-pink-500 mr-0.5"></i> TikTok
                                        @else
                                            <i class="ri-global-line text-brand-500 mr-0.5"></i> Web Widget
                                        @endif
                                    </span>
                                    <span>{{ $order->captured_at ? $order->captured_at->diffForHumans() : '' }}</span>
                                </div>
                            </td>

                            {{-- Customer Info & Contact Actions --}}
                            <td class="py-3 px-3">
                                <div class="font-semibold text-light-text dark:text-dark-text">
                                    {{ $order->customer_name ?: 'Valued Customer' }}
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    @if($order->customer_phone)
                                        <a href="tel:{{ $order->customer_phone }}" class="text-2xs font-mono text-brand-500 hover:underline flex items-center gap-1">
                                            <i class="ri-phone-line"></i> {{ $order->customer_phone }}
                                        </a>
                                        @if($order->getWhatsAppUrl())
                                            <a href="{{ $order->getWhatsAppUrl() }}" target="_blank" class="w-5 h-5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500 hover:text-white flex items-center justify-center text-xs transition" title="Message on WhatsApp">
                                                <i class="ri-whatsapp-line"></i>
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-3xs text-light-muted dark:text-dark-muted italic">No phone extracted</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Delivery Address --}}
                            <td class="py-3 px-3 max-w-xs">
                                @if($order->delivery_address)
                                    <div class="text-2xs text-light-text dark:text-dark-text line-clamp-2" title="{{ $order->delivery_address }}">
                                        {{ $order->delivery_address }}
                                    </div>
                                    <button 
                                        type="button" 
                                        class="text-3xs text-brand-500 hover:underline mt-0.5 inline-flex items-center gap-0.5" 
                                        onclick="navigator.clipboard.writeText('{{ addslashes($order->delivery_address) }}'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy Address', 1500);"
                                    >
                                        <i class="ri-file-copy-line"></i> Copy Address
                                    </button>
                                @else
                                    <span class="text-3xs text-light-muted dark:text-dark-muted italic">Pending customer address</span>
                                @endif
                            </td>

                            {{-- Product Summary --}}
                            <td class="py-3 px-3 max-w-xs">
                                <div class="text-2xs font-medium text-light-text dark:text-dark-text line-clamp-2">
                                    {{ $order->product_summary ?: 'General Order' }}
                                </div>
                                @if($order->conversation_id)
                                    <a href="{{ route('conversations.show', $order->conversation_id) }}" class="text-3xs text-brand-500 hover:underline mt-0.5 inline-flex items-center gap-0.5">
                                        <i class="ri-chat-1-line"></i> View Conversation
                                    </a>
                                @endif
                            </td>

                            {{-- Amount --}}
                            <td class="py-3 px-3 whitespace-nowrap font-mono font-bold text-light-text dark:text-dark-text">
                                @if($order->order_amount)
                                    ৳ {{ number_format($order->order_amount, 2) }}
                                @else
                                    <span class="text-3xs text-light-muted dark:text-dark-muted font-normal italic">To be confirmed</span>
                                @endif
                            </td>

                            {{-- Status Badge & Courier Tracking Pill --}}
                            <td class="py-3 px-3 whitespace-nowrap space-y-1">
                                <span class="app-badge {{ $order->getStatusBadgeClass() }} uppercase text-3xs font-bold">
                                    {{ $order->status }}
                                </span>

                                @if($order->isDispatchedToCourier())
                                    <div class="mt-1">
                                        <a 
                                            href="{{ $order->getCourierTrackingUrl() ?: '#' }}" 
                                            target="_blank" 
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-3xs font-mono font-bold {{ $order->getCourierBadgeClass() }} hover:shadow-xs transition" 
                                            title="Click to track consignment"
                                        >
                                            <i class="ri-truck-fill"></i>
                                            <span>{{ ucfirst($order->courier_provider) }}: {{ $order->courier_tracking_code ?: $order->courier_consignment_id }}</span>
                                            <i class="ri-external-link-line text-3xs"></i>
                                        </a>
                                    </div>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Send to Courier Button (if not already dispatched) --}}
                                    @if(! $order->isDispatchedToCourier())
                                        <button 
                                            type="button" 
                                            class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 hover:border-emerald-500 hover:bg-emerald-500/10"
                                            onclick="openSingleCourierModal('{{ $order->id }}', '{{ $order->order_number }}', '{{ addslashes($order->customer_name ?? 'Valued Customer') }}', '{{ $order->getFormatted11DigitPhone() }}', '{{ addslashes($order->delivery_address ?? '') }}', '{{ (float)$order->order_amount }}')"
                                            title="Send to Courier (Steadfast / Pathao / RedX)"
                                        >
                                            <i class="ri-truck-line text-emerald-500"></i>
                                            <span>Send</span>
                                        </button>
                                    @endif

                                    {{-- Status Update Button --}}
                                    <button 
                                        type="button" 
                                        class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs inline-flex items-center gap-1"
                                        onclick="openOrderStatusModal('{{ $order->id }}', '{{ $order->order_number }}', '{{ $order->status }}', '{{ addslashes($order->admin_notes ?? '') }}')"
                                        title="Change Fulfillment Status"
                                    >
                                        <i class="ri-edit-line text-brand-500"></i>
                                        <span>Update</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-light-muted dark:text-dark-muted text-xs">
                                <i class="ri-shopping-bag-line text-3xl block mb-2 opacity-50"></i>
                                <strong>No orders or leads found.</strong>
                                <p class="text-2xs mt-1">When customers provide their phone number or address in live chats, the AI will capture them here automatically.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-3 border-t border-light-border dark:border-dark-border">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal 1: Single Order Send to Courier --}}
<div class="modal fade" id="singleCourierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-truck-line text-emerald-500"></i>
                    <span>Send Order #<span id="modalCourierOrderNumber"></span> to Courier</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form id="singleCourierForm" onsubmit="submitSingleCourier(event)">
                <input type="hidden" id="modalCourierOrderId">
                <div class="space-y-3.5">
                    <div>
                        <label for="singleCourierProvider" class="block text-2xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Choose Courier Service <span class="text-rose-500">*</span>
                        </label>
                        <select id="singleCourierProvider" class="app-select w-full text-xs font-semibold">
                            <option value="steadfast">Steadfast Courier</option>
                            <option value="pathao">Pathao Courier</option>
                            <option value="redx">RedX Logistics</option>
                        </select>
                    </div>

                    <div class="p-3 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2 text-2xs">
                        <div class="flex items-center justify-between">
                            <span class="text-light-muted dark:text-dark-muted font-medium">Customer:</span>
                            <span class="font-bold text-light-text dark:text-dark-text" id="modalCustomerName">—</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-light-muted dark:text-dark-muted font-medium">Phone Number:</span>
                            <span class="font-mono font-bold text-light-text dark:text-dark-text" id="modalCustomerPhone">—</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-light-muted dark:text-dark-muted font-medium">COD Collection Amount:</span>
                            <span class="font-mono font-bold text-emerald-500" id="modalCodAmount">৳ 0.00</span>
                        </div>
                        <div class="pt-1.5 border-t border-light-border dark:border-dark-border">
                            <span class="text-light-muted dark:text-dark-muted font-medium block mb-0.5">Delivery Address:</span>
                            <p class="text-light-text dark:text-dark-text leading-relaxed" id="modalCustomerAddress">—</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-light-border dark:border-dark-border mt-4">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitSingleCourier" class="app-btn app-btn-primary text-xs shadow-xs">
                        <i class="ri-send-plane-fill"></i>
                        <span>Confirm & Dispatch</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 2: Update Order Status --}}
<div class="modal fade" id="updateOrderStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-shopping-bag-3-line text-brand-500"></i>
                    <span>Update Order #<span id="modalOrderNumber"></span></span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form id="orderStatusForm" method="POST" action="">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="order_status_select" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Fulfillment Status <span class="text-rose-500">*</span>
                        </label>
                        <select name="status" id="order_status_select" class="app-select w-full text-xs font-semibold" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label for="admin_notes_input" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Fulfillment / Courier Notes (Internal)
                        </label>
                        <textarea 
                            name="admin_notes" 
                            id="admin_notes_input" 
                            rows="3" 
                            class="app-input w-full text-xs" 
                            placeholder="Add tracking number, delivery batch, or customer confirmation notes..."
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-light-border dark:border-dark-border mt-4">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs shadow-xs">
                        <i class="ri-check-line"></i> Save Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
<script>
    // ── Select All Checkbox Handler ──────────────────────────────────
    $('#selectAllCheckbox').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.order-row-checkbox').prop('checked', isChecked);
        updateBulkActionState();
    });

    function updateBulkActionState() {
        var selected = $('.order-row-checkbox:checked');
        var count = selected.length;
        $('#selectedCountText').text(count);

        if (count > 0) {
            $('#bulkCourierActionBar').removeClass('hidden');
        } else {
            $('#bulkCourierActionBar').addClass('hidden');
            $('#selectAllCheckbox').prop('checked', false);
        }
    }

    function deselectAllOrders() {
        $('.order-row-checkbox').prop('checked', false);
        $('#selectAllCheckbox').prop('checked', false);
        updateBulkActionState();
    }

    // ── Execute Bulk Courier Dispatch ────────────────────────────────
    function executeBulkCourierDispatch() {
        var selectedIds = [];
        $('.order-row-checkbox:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });

        if (selectedIds.length === 0) {
            swal("Notice", "Please select at least one order to dispatch.", "warning");
            return;
        }

        var provider = $('#bulkCourierProvider').val();
        var providerName = provider.charAt(0).toUpperCase() + provider.slice(1);

        swal({
            title: "Confirm Bulk Dispatch?",
            text: "Are you sure you want to send " + selectedIds.length + " orders directly to " + providerName + "?",
            icon: "info",
            buttons: ["Cancel", "Yes, Dispatch Now"],
        }).then(function(willSend) {
            if (!willSend) return;

            var $btn = $('#btnExecuteBulkDispatch');
            var origHtml = $btn.html();
            var csrf = $('meta[name="csrf-token"]').attr('content');

            $btn.prop('disabled', true).html('<i class="ri-loader-4-line animate-spin"></i> Dispatching...');

            $.ajax({
                url: "{{ route('orders.bulk-send-courier') }}",
                type: 'POST',
                data: {
                    _token: csrf,
                    order_ids: selectedIds,
                    provider: provider
                },
                success: function(res) {
                    $btn.prop('disabled', false).html(origHtml);
                    if (res.success) {
                        var msg = res.message || "Bulk dispatch successfully completed!";
                        swal("Bulk Dispatch Completed!", msg, "success").then(function() {
                            window.location.reload();
                        });
                    } else {
                        swal("Dispatch Issues", res.message || "Failed to dispatch selected orders.", "error");
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(origHtml);
                    var err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.';
                    swal("Bulk Dispatch Failed", err, "error");
                }
            });
        });
    }

    // ── Single Courier Dispatch Modal ────────────────────────────────
    function openSingleCourierModal(id, orderNumber, customerName, customerPhone, address, amount) {
        $('#modalCourierOrderId').val(id);
        $('#modalCourierOrderNumber').text(orderNumber);
        $('#modalCustomerName').text(customerName);
        $('#modalCustomerPhone').text(customerPhone);
        $('#modalCustomerAddress').text(address || 'No address provided');
        $('#modalCodAmount').text('৳ ' + parseFloat(amount || 0).toFixed(2));

        var modal = new bootstrap.Modal(document.getElementById('singleCourierModal'));
        modal.show();
    }

    function submitSingleCourier(e) {
        e.preventDefault();
        var orderId = $('#modalCourierOrderId').val();
        var provider = $('#singleCourierProvider').val();
        var $btn = $('#btnSubmitSingleCourier');
        var origHtml = $btn.html();
        var csrf = $('meta[name="csrf-token"]').attr('content');

        $btn.prop('disabled', true).html('<i class="ri-loader-4-line animate-spin"></i> Booking...');

        $.ajax({
            url: SITEURL + '/dashboard/orders/' + encodeURIComponent(orderId) + '/send-courier',
            type: 'POST',
            data: {
                _token: csrf,
                provider: provider
            },
            success: function(res) {
                $btn.prop('disabled', false).html(origHtml);
                bootstrap.Modal.getInstance(document.getElementById('singleCourierModal')).hide();

                if (res.success) {
                    swal("Consignment Booked!", res.message, "success").then(function() {
                        window.location.reload();
                    });
                } else {
                    swal("Booking Failed", res.message || "Failed to book consignment.", "error");
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(origHtml);
                var err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.';
                swal("Booking Error", err, "error");
            }
        });
    }

    // ── Order Status Modal ───────────────────────────────────────────
    function openOrderStatusModal(orderId, orderNumber, currentStatus, adminNotes) {
        $('#modalOrderNumber').text(orderNumber);
        $('#order_status_select').val(currentStatus);
        $('#admin_notes_input').val(adminNotes);

        var actionUrl = SITEURL + '/dashboard/orders/' + encodeURIComponent(orderId) + '/status';
        $('#orderStatusForm').attr('action', actionUrl);

        var modal = new bootstrap.Modal(document.getElementById('updateOrderStatusModal'));
        modal.show();
    }
</script>
@endpush
