@if(isset($dailySummary))
<div class="app-card overflow-hidden border border-purple-500/20 shadow-sm bg-gradient-to-br from-purple-500/5 via-brand-500/5 to-transparent">
    <div class="p-3.5 sm:p-4 border-b border-light-border dark:border-dark-border flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-sm shrink-0 shadow-xs">
                <i class="ri-sparkling-fill"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs sm:text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-1.5">
                        <span>AI Daily Executive Summary & Lead Insights</span>
                    </h2>
                    <span class="app-badge app-badge-primary !text-3xs uppercase tracking-wider">
                        {{ $dailySummary['date_formatted'] }}
                    </span>
                </div>
                <p class="text-2xs text-light-text-secondary dark:text-dark-text-secondary mt-0.5">
                    Real-time synthesis of customer inquiries, intent classification, and conversion signals.
                </p>
            </div>
        </div>

        {{-- Lead Distribution Quick Chips --}}
        <div class="flex items-center gap-1.5 flex-wrap">
            <a href="{{ route('conversations.index', ['tag' => 'hot_lead']) }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-2xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20 border border-rose-500/20 transition-colors" title="View Hot Leads">
                <span>🔥</span>
                <span>{{ $dailySummary['hot_leads_count'] }} Hot Leads</span>
            </a>
            <a href="{{ route('conversations.index', ['tag' => 'order_placed']) }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-2xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20 transition-colors" title="View Orders">
                <span>📦</span>
                <span>{{ $dailySummary['orders_placed_count'] }} Orders</span>
            </a>
            <a href="{{ route('conversations.index', ['tag' => 'price_inquiry']) }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-2xs font-semibold bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 border border-blue-500/20 transition-colors" title="View Price Inquiries">
                <span>💰</span>
                <span>{{ $dailySummary['price_inquiries_count'] }} Inquiries</span>
            </a>
            <a href="{{ route('conversations.index', ['tag' => 'complaint']) }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-2xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 border border-amber-500/20 transition-colors" title="View Complaints">
                <span>⚠️</span>
                <span>{{ $dailySummary['complaints_count'] }} Complaints</span>
            </a>
        </div>
    </div>

    <div class="p-3.5 sm:p-4 space-y-3">
        <div class="p-3 rounded-xl bg-light-surface/80 dark:bg-dark-surface/80 border border-light-border dark:border-dark-border">
            <h3 class="text-xs sm:text-sm font-semibold text-light-text dark:text-dark-text leading-snug">
                {{ $dailySummary['headline'] }}
            </h3>

            @if(!empty($dailySummary['bullets']))
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2.5 pt-2.5 border-t border-light-border dark:border-dark-border text-xs text-light-text-secondary dark:text-dark-text-secondary">
                    @foreach($dailySummary['bullets'] as $bullet)
                        <div class="flex items-center gap-2">
                            <span>{{ $bullet }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 p-2.5 rounded-lg bg-brand-500/10 border border-brand-500/20 text-xs">
            <div class="flex items-center gap-2 text-brand-700 dark:text-brand-300">
                <i class="ri-lightbulb-flash-line text-sm text-brand-500 shrink-0"></i>
                <span class="font-medium"><strong>AI Actionable Advice:</strong> {{ $dailySummary['recommendation'] }}</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('conversations.index', ['tag' => 'hot_lead']) }}" class="app-btn app-btn-primary !h-7 !px-2.5 !text-2xs whitespace-nowrap">
                    <i class="ri-chat-smile-2-line text-xs"></i>
                    <span>Open Hot Leads</span>
                </a>
                <a href="{{ route('orders.index') }}" class="app-btn app-btn-secondary !h-7 !px-2.5 !text-2xs whitespace-nowrap">
                    <i class="ri-shopping-bag-3-line text-xs"></i>
                    <span>Orders Hub</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endif
