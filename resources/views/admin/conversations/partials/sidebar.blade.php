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

    <div class="conversation-list flex-1 overflow-y-auto scrollbar py-1">
        @include('admin.conversations.partials.conversation-list')
    </div>
</div>
