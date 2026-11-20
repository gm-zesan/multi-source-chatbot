{{-- Floating Bulk Action Bar (Linear / Vercel style) --}}
<div id="appBulkActionBar" class="app-bulk-bar" role="region" aria-label="Bulk actions">
    <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-brand-500"></span>
        <span class="app-badge app-badge-primary font-mono !text-3xs" id="bulkSelectedCount">0 selected</span>
    </div>
    <div class="h-4 w-px bg-light-border dark:bg-dark-border"></div>
    <div class="flex items-center gap-1.5">
        <button type="button" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex items-center gap-1" id="bulkExportBtn" onclick="window.executeBulkExport()">
            <i class="ri-download-2-line text-xs"></i>
            <span>Export CSV</span>
        </button>
        <button type="button" class="app-btn app-btn-outline !h-7 !px-2.5 text-2xs !border-rose-500/30 text-rose-500 hover:bg-rose-500/10 flex items-center gap-1" id="bulkDeleteBtn" onclick="window.executeBulkDelete()">
            <i class="ri-delete-bin-line text-xs"></i>
            <span>Batch Delete</span>
        </button>
    </div>
    <button type="button" class="w-6 h-6 rounded-full flex items-center justify-center text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text hover:bg-light-canvas dark:hover:bg-dark-canvas transition-colors ml-1" onclick="window.clearBulkSelection()" title="Clear Selection">
        <i class="ri-close-line text-sm"></i>
    </button>
</div>
