@extends('admin.app')

@section('title', 'Prepaid Wallet & Usage Billing — Entrepreneurs Automation')

@section('content')
<div class="space-y-5">
    {{-- Top Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-wallet-3-line text-brand-500 text-xl"></i>
                <span>Prepaid Wallet & Metered Usage</span>
                <span class="app-badge app-badge-primary">{{ $workspace->name }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-0.5">
                Pay only for what you use. Real-time ৳{{ number_format((float)$wallet->rate_per_message, 2) }} BDT per AI message deduction across all connected channels.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="app-btn app-btn-primary flex items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#rechargeWalletModal">
                <i class="ri-add-circle-line text-base"></i>
                <span>Recharge Wallet</span>
            </button>
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

    {{-- Low Balance / Frozen Warning Banner --}}
    @if($wallet->is_frozen || $wallet->balance < $wallet->rate_per_message)
        <div class="p-4 rounded-xl border-2 border-rose-500 bg-rose-500/10 text-rose-600 dark:text-rose-400 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-md">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg bg-rose-500 text-white flex items-center justify-center flex-shrink-0 text-lg">
                    <i class="ri-alert-fill"></i>
                </div>
                <div>
                    <strong class="text-xs font-bold block">Prepaid Balance Depleted — AI Messaging Paused</strong>
                    <p class="text-2xs text-rose-700 dark:text-rose-300 mt-0.5">
                        Your wallet balance is ৳{{ number_format($wallet->balance, 2) }}, which is below the minimum message cost (৳{{ number_format((float)$wallet->rate_per_message, 2) }}). Automated AI replies on Facebook, WhatsApp, Web Widget, and API are temporarily paused until recharged.
                    </p>
                </div>
            </div>
            <button type="button" class="app-btn app-btn-primary flex-shrink-0 !bg-rose-600 hover:!bg-rose-700 !text-white text-xs" data-bs-toggle="modal" data-bs-target="#rechargeWalletModal">
                <i class="ri-flashlight-line"></i> Recharge Now
            </button>
        </div>
    @elseif($wallet->isLowBalance())
        <div class="p-3.5 rounded-xl border border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <i class="ri-error-warning-fill text-lg flex-shrink-0"></i>
                <div class="text-2xs">
                    <strong>Low Balance Alert:</strong> Your balance is under ৳{{ number_format($wallet->low_balance_threshold, 2) }}. You have approximately <strong>{{ $remainingMessages }}</strong> AI messages left. Please recharge to avoid service disruption.
                </div>
            </div>
            <button type="button" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex-shrink-0" data-bs-toggle="modal" data-bs-target="#rechargeWalletModal">
                Quick Top-Up
            </button>
        </div>
    @endif

    {{-- Key Balance & Usage KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- 1. Current Available Balance --}}
        <div class="app-card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Prepaid Balance</div>
                    <div class="text-2xl font-black tracking-tight text-light-text dark:text-dark-text mt-1">
                        ৳ {{ number_format($wallet->balance, 2) }}
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="ri-wallet-3-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-3 mt-3 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="text-light-muted dark:text-dark-muted">Status:</span>
                @if($wallet->is_frozen)
                    <span class="app-badge app-badge-danger uppercase text-3xs font-bold">Frozen</span>
                @elseif($wallet->isLowBalance())
                    <span class="app-badge app-badge-warning uppercase text-3xs font-bold">Low Balance</span>
                @else
                    <span class="app-badge app-badge-success uppercase text-3xs font-bold">Active & Ready</span>
                @endif
            </div>
        </div>

        {{-- 2. Estimated Messages Left --}}
        <div class="app-card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Remaining AI Messages</div>
                    <div class="text-2xl font-black tracking-tight text-emerald-600 dark:text-emerald-400 mt-1">
                        {{ number_format($remainingMessages) }}
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="ri-chat-voice-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-3 mt-3 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="text-light-muted dark:text-dark-muted">Metered Rate:</span>
                <span class="font-mono font-semibold text-light-text dark:text-dark-text">৳{{ number_format((float)$wallet->rate_per_message, 2) }} / msg</span>
            </div>
        </div>

        {{-- 3. Messages Processed This Month --}}
        <div class="app-card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Messages This Month</div>
                    <div class="text-2xl font-black tracking-tight text-light-text dark:text-dark-text mt-1">
                        {{ number_format($messagesThisMonth) }}
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="ri-message-3-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-3 mt-3 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="text-light-muted dark:text-dark-muted">Channels:</span>
                <span class="text-light-muted dark:text-dark-muted">Omnichannel Live</span>
            </div>
        </div>

        {{-- 4. Spend This Month --}}
        <div class="app-card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Usage Spend (MTD)</div>
                    <div class="text-2xl font-black tracking-tight text-light-text dark:text-dark-text mt-1">
                        ৳ {{ number_format($totalSpentThisMonth, 2) }}
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="ri-line-chart-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-3 mt-3 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="text-light-muted dark:text-dark-muted">Currency:</span>
                <span class="font-mono font-semibold text-light-text dark:text-dark-text">{{ $wallet->currency }}</span>
            </div>
        </div>
    </div>

    {{-- Transparent Pricing Model Explanation Banner --}}
    <div class="app-card p-4 bg-gradient-to-r from-brand-500/10 via-light-surface dark:via-dark-surface to-light-surface dark:to-dark-surface border border-brand-500/20">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="app-badge app-badge-primary text-3xs font-mono uppercase">Prepaid Pricing Model</span>
                    <span class="text-2xs text-light-muted dark:text-dark-muted">Transparent Metered Billing</span>
                </div>
                <h3 class="text-xs font-bold text-light-text dark:text-dark-text">
                    Fixed ৳{{ number_format((float)$wallet->rate_per_message, 2) }} BDT per AI Message Response
                </h3>
                <p class="text-2xs text-light-text-secondary dark:text-dark-text-secondary max-w-3xl leading-relaxed">
                    No costly recurring commitments. You only pay <strong>৳{{ number_format((float)$wallet->rate_per_message, 2) }}</strong> whenever the AI agent formulates an intelligent answer for your customers on Facebook Messenger, WhatsApp Business, TikTok, Website Live Widget, or via Developer API.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                @php
                    $starterBonus = \App\Models\SubscriptionPlan::where('slug', 'starter-bonus')->value('price_monthly') ?? 50.00;
                    $bonusMsgs = floor($starterBonus / max(0.01, (float)$wallet->rate_per_message));
                @endphp
                <div class="p-2.5 px-3 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-center">
                    <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Starter Bonus</span>
                    <strong class="text-xs font-bold text-emerald-500">৳{{ number_format((float)$starterBonus, 2) }} Free</strong>
                    <span class="text-3xs text-light-muted dark:text-dark-muted block">({{ number_format($bonusMsgs) }} Messages)</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Recharge Preset Cards --}}
    <div class="app-card p-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-xs font-bold text-light-text dark:text-dark-text">Quick Recharge Packages</h3>
                <p class="text-3xs text-light-muted dark:text-dark-muted">Select a preset to instantly top-up your workspace balance.</p>
            </div>
            <span class="app-badge app-badge-secondary text-3xs font-mono">bKash / Nagad / Card</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach($rechargePresets as $preset)
                @php
                    $presetMsgs = (int) floor($preset / (float) $wallet->rate_per_message);
                @endphp
                <button 
                    type="button" 
                    onclick="openRechargeModalWithAmount({{ $preset }})"
                    class="p-3.5 rounded-xl border border-light-border dark:border-dark-border bg-light-canvas/60 dark:bg-dark-canvas/60 hover:border-brand-500/50 hover:bg-brand-500/5 transition flex flex-col items-center justify-center text-center group"
                >
                    <span class="text-base font-black text-light-text dark:text-dark-text group-hover:text-brand-500 transition">
                        ৳ {{ number_format($preset) }}
                    </span>
                    <span class="text-2xs text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5">
                        {{ number_format($presetMsgs) }} AI Messages
                    </span>
                    <span class="text-3xs text-light-muted dark:text-dark-muted mt-1 flex items-center gap-1">
                        <i class="ri-flashlight-line text-brand-500"></i> Top-up
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Transaction Ledger Table --}}
    <div class="app-card overflow-hidden">
        <div class="p-4 border-b border-light-border dark:border-dark-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-xs font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-history-line text-brand-500"></i>
                    <span>Wallet Transactions & Usage Ledger</span>
                </h3>
                <p class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">
                    Real-time itemized audit log of recharges, usage deductions, and credit adjustments.
                </p>
            </div>
            <div class="text-2xs text-light-muted dark:text-dark-muted font-mono">
                Showing {{ $transactions->total() }} Total Transactions
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-light-canvas/70 dark:bg-dark-canvas/70 border-b border-light-border dark:border-dark-border text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-semibold">
                    <tr>
                        <th class="py-2.5 px-4">Date & Time</th>
                        <th class="py-2.5 px-3">Type</th>
                        <th class="py-2.5 px-3">Category</th>
                        <th class="py-2.5 px-4">Description / Reference</th>
                        <th class="py-2.5 px-3 text-right">Amount</th>
                        <th class="py-2.5 px-4 text-right">Balance After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-border dark:divide-dark-border text-light-text dark:text-dark-text">
                    @forelse($transactions as $tx)
                        <tr class="hover:bg-light-canvas/40 dark:hover:bg-dark-canvas/40 transition">
                            <td class="py-3 px-4 font-mono text-2xs text-light-muted dark:text-dark-muted whitespace-nowrap">
                                {{ $tx->created_at->format('M d, Y h:i A') }}
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($tx->type === 'credit')
                                    <span class="app-badge app-badge-success text-3xs uppercase font-bold">
                                        <i class="ri-arrow-down-line mr-0.5"></i> Credit
                                    </span>
                                @else
                                    <span class="app-badge app-badge-danger text-3xs uppercase font-bold">
                                        <i class="ri-arrow-up-line mr-0.5"></i> Debit
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                <span class="app-badge app-badge-secondary text-3xs font-mono">
                                    {{ $tx->category }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-light-text dark:text-dark-text">{{ $tx->description }}</div>
                                @if($tx->reference_id)
                                    <div class="text-3xs font-mono text-light-muted dark:text-dark-muted">
                                        Ref: {{ $tx->reference_id }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-bold whitespace-nowrap {{ $tx->type === 'credit' ? 'text-emerald-500' : 'text-rose-500' }}">
                                {{ $tx->type === 'credit' ? '+' : '-' }} ৳ {{ number_format($tx->amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-light-text dark:text-dark-text whitespace-nowrap">
                                ৳ {{ number_format($tx->balance_after, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-light-muted dark:text-dark-muted text-xs">
                                <i class="ri-inbox-line text-2xl block mb-1"></i>
                                No transactions recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-3 border-t border-light-border dark:border-dark-border">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal: Recharge Prepaid Wallet --}}
<div class="modal fade" id="rechargeWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-wallet-3-line text-brand-500"></i>
                    <span>Recharge Prepaid Wallet</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form action="{{ route('billing.recharge') }}" method="POST">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">

                <div class="space-y-4">
                    {{-- Amount Input --}}
                    <div>
                        <label for="recharge_amount" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Recharge Amount (BDT) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-light-muted dark:text-dark-muted text-xs">৳</span>
                            <input 
                                type="number" 
                                id="recharge_amount" 
                                name="amount" 
                                min="50" 
                                max="100000" 
                                step="10" 
                                value="500" 
                                required 
                                class="app-input w-full pl-8 font-bold text-sm"
                                oninput="updateExpectedMessages(this.value)"
                            >
                        </div>
                        <div class="flex items-center justify-between text-3xs text-light-muted dark:text-dark-muted mt-1">
                            <span>Min: ৳50.00 BDT</span>
                            <span id="expectedMessagesLabel" class="text-emerald-500 font-semibold">≈ 1,250 AI Messages</span>
                        </div>
                    </div>

                    {{-- Quick Preset Buttons --}}
                    <div>
                        <div class="text-3xs uppercase font-semibold text-light-muted dark:text-dark-muted mb-1.5">Presets:</div>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button type="button" onclick="setModalAmount(500)" class="app-btn app-btn-secondary !h-7 !px-1 text-2xs">৳ 500</button>
                            <button type="button" onclick="setModalAmount(1000)" class="app-btn app-btn-secondary !h-7 !px-1 text-2xs">৳ 1,000</button>
                            <button type="button" onclick="setModalAmount(2000)" class="app-btn app-btn-secondary !h-7 !px-1 text-2xs">৳ 2,000</button>
                            <button type="button" onclick="setModalAmount(5000)" class="app-btn app-btn-secondary !h-7 !px-1 text-2xs">৳ 5,000</button>
                        </div>
                    </div>

                    {{-- Payment Gateway Selection --}}
                    <div>
                        <label class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1.5">
                            Payment Method <span class="text-rose-500">*</span>
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="cursor-pointer border border-light-border dark:border-dark-border rounded-xl p-2.5 flex flex-col items-center justify-center text-center hover:border-pink-500 transition has-[:checked]:border-pink-500 has-[:checked]:bg-pink-500/10">
                                <input type="radio" name="gateway" value="bkash" class="sr-only" checked>
                                <i class="ri-smartphone-line text-lg text-pink-600 mb-0.5"></i>
                                <span class="text-2xs font-bold text-light-text dark:text-dark-text">bKash</span>
                                <span class="text-3xs text-light-muted dark:text-dark-muted">Instant</span>
                            </label>

                            <label class="cursor-pointer border border-light-border dark:border-dark-border rounded-xl p-2.5 flex flex-col items-center justify-center text-center hover:border-orange-500 transition has-[:checked]:border-orange-500 has-[:checked]:bg-orange-500/10">
                                <input type="radio" name="gateway" value="nagad" class="sr-only">
                                <i class="ri-exchange-funds-line text-lg text-orange-600 mb-0.5"></i>
                                <span class="text-2xs font-bold text-light-text dark:text-dark-text">Nagad</span>
                                <span class="text-3xs text-light-muted dark:text-dark-muted">Instant</span>
                            </label>

                            <label class="cursor-pointer border border-light-border dark:border-dark-border rounded-xl p-2.5 flex flex-col items-center justify-center text-center hover:border-brand-500 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-500/10">
                                <input type="radio" name="gateway" value="card" class="sr-only">
                                <i class="ri-bank-card-line text-lg text-brand-500 mb-0.5"></i>
                                <span class="text-2xs font-bold text-light-text dark:text-dark-text">Card</span>
                                <span class="text-3xs text-light-muted dark:text-dark-muted">Visa/Master</span>
                            </label>
                        </div>
                    </div>

                    {{-- Transaction Reference (Optional) --}}
                    <div>
                        <label for="trx_id" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Transaction Reference / ID <span class="text-light-muted text-3xs font-normal">(Optional)</span>
                        </label>
                        <input 
                            type="text" 
                            id="trx_id" 
                            name="trx_id" 
                            placeholder="e.g. 9J23KL50" 
                            class="app-input w-full text-xs font-mono"
                        >
                        <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">
                            Leave blank for automated gateway checkout or auto-generated invoice reference.
                        </p>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5 shadow-md">
                        <i class="ri-check-line"></i>
                        <span>Confirm & Recharge</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setModalAmount(amount) {
    const input = document.getElementById('recharge_amount');
    if (input) {
        input.value = amount;
        updateExpectedMessages(amount);
    }
}

function updateExpectedMessages(val) {
    const amount = parseFloat(val) || 0;
    const rate = {{ (float) $wallet->rate_per_message }};
    const msgs = Math.floor(amount / rate);
    const label = document.getElementById('expectedMessagesLabel');
    if (label) {
        label.innerText = '≈ ' + msgs.toLocaleString() + ' AI Messages';
    }
}

function openRechargeModalWithAmount(amount) {
    setModalAmount(amount);
    const modalEl = document.getElementById('rechargeWalletModal');
    if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}
</script>
@endsection
