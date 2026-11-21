<div class="sidebar-wrapper flex flex-col h-full overflow-hidden">
    <div class="search-box-wrapper h-14 min-h-[56px] max-h-[56px] flex items-center px-3 border-b border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shrink-0">
        <div class="search-box relative w-full">
            <i class="ri-search-line absolute left-2.5 top-1/2 -translate-y-1/2 text-light-muted dark:text-dark-muted text-xs"></i>
            <input
                type="text"
                class="app-input w-full !h-8 !pl-8 !pr-2 !text-xs bg-light-canvas dark:bg-dark-canvas border-light-border dark:border-dark-border text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted"
                placeholder="Search conversations...">
        </div>
    </div>

    {{-- AI Smart Tag Quick Filters --}}
    <div class="px-2.5 py-1.5 border-b border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 flex items-center gap-1 overflow-x-auto scrollbar-none shrink-0 text-3xs">
        <a href="{{ route('conversations.index', request()->except(['tag', 'page'])) }}"
           class="px-2 py-0.5 rounded-full whitespace-nowrap font-medium transition-colors {{ !request()->filled('tag') ? 'bg-brand-500 text-white' : 'bg-light-canvas dark:bg-dark-canvas text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text' }}">
            All
        </a>
        <a href="{{ route('conversations.index', array_merge(request()->except('page'), ['tag' => 'hot_lead'])) }}"
           class="px-2 py-0.5 rounded-full whitespace-nowrap font-medium transition-colors {{ request('tag') === 'hot_lead' ? 'bg-rose-500 text-white' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-500/20' }}">
            🔥 Hot Leads
        </a>
        <a href="{{ route('conversations.index', array_merge(request()->except('page'), ['tag' => 'order_placed'])) }}"
           class="px-2 py-0.5 rounded-full whitespace-nowrap font-medium transition-colors {{ request('tag') === 'order_placed' ? 'bg-emerald-500 text-white' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20' }}">
            📦 Orders
        </a>
        <a href="{{ route('conversations.index', array_merge(request()->except('page'), ['tag' => 'price_inquiry'])) }}"
           class="px-2 py-0.5 rounded-full whitespace-nowrap font-medium transition-colors {{ request('tag') === 'price_inquiry' ? 'bg-blue-500 text-white' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20' }}">
            💰 Prices
        </a>
        <a href="{{ route('conversations.index', array_merge(request()->except('page'), ['tag' => 'complaint'])) }}"
           class="px-2 py-0.5 rounded-full whitespace-nowrap font-medium transition-colors {{ request('tag') === 'complaint' ? 'bg-amber-500 text-white' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20' }}">
            ⚠️ Complaints
        </a>
    </div>

    <div class="conversation-list flex-1 overflow-y-auto scrollbar py-1">
        @include('admin.conversations.partials.conversation-list')
    </div>
</div>
