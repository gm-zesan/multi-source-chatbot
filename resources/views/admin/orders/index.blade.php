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
                Customer purchase inquiries and delivery details automatically extracted by AI across all channels.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('orders.export', request()->all()) }}" class="app-btn app-btn-secondary text-xs flex items-center gap-1.5 shadow-sm">
                <i class="ri-file-download-line text-emerald-500"></i>
                <span>Export Courier CSV</span>
            </a>
            <a href="{{ route('conversations.index') }}" class="app-btn app-btn-secondary text-xs">
                <i class="ri-message-3-line"></i> Inbox
            </a>
        </div>
    </div>

    {{-- Success Alert --}}
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

    {{-- Orders Table --}}
    <div class="app-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-light-canvas/70 dark:bg-dark-canvas/70 border-b border-light-border dark:border-dark-border text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-semibold">
                    <tr>
                        <th class="py-2.5 px-4">Order / Channel</th>
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
                            {{-- Order ID & Channel --}}
                            <td class="py-3 px-4 whitespace-nowrap">
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

                            {{-- Status Badge --}}
                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="app-badge {{ $order->getStatusBadgeClass() }} uppercase text-3xs font-bold">
                                    {{ $order->status }}
                                </span>
                            </td>

                            {{-- Actions / Change Status --}}
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <button 
                                    type="button" 
                                    class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs inline-flex items-center gap-1"
                                    onclick="openOrderStatusModal('{{ $order->id }}', '{{ $order->order_number }}', '{{ $order->status }}', '{{ addslashes($order->admin_notes ?? '') }}')"
                                >
                                    <i class="ri-edit-line text-brand-500"></i>
                                    <span>Update</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-light-muted dark:text-dark-muted text-xs">
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

{{-- Modal: Update Order Status --}}
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
                            <option value="pending">PENDING (New Customer Lead)</option>
                            <option value="confirmed">CONFIRMED (Call verified by staff)</option>
                            <option value="shipped">SHIPPED (Handed over to Courier)</option>
                            <option value="delivered">DELIVERED (Successfully Completed)</option>
                            <option value="cancelled">CANCELLED (Customer declined/invalid)</option>
                        </select>
                    </div>

                    <div>
                        <label for="modal_admin_notes" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Admin / Courier Notes <span class="text-light-muted text-3xs font-normal">(Optional)</span>
                        </label>
                        <textarea 
                            name="admin_notes" 
                            id="modal_admin_notes" 
                            rows="2" 
                            placeholder="e.g. Steadfast Tracking ID: SF10294, Customer requested evening delivery" 
                            class="app-textarea w-full text-xs"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5 shadow-md">
                        <i class="ri-check-line"></i>
                        <span>Save Status</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openOrderStatusModal(orderId, orderNumber, currentStatus, currentNotes) {
    const form = document.getElementById('orderStatusForm');
    const numEl = document.getElementById('modalOrderNumber');
    const selectEl = document.getElementById('order_status_select');
    const notesEl = document.getElementById('modal_admin_notes');

    if (form) {
        form.action = '/orders/' + orderId + '/status';
    }
    if (numEl) numEl.innerText = orderNumber;
    if (selectEl) selectEl.value = currentStatus;
    if (notesEl) notesEl.value = currentNotes || '';

    const modalEl = document.getElementById('updateOrderStatusModal');
    if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}
</script>
@endsection
