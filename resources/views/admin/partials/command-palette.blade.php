{{-- Raycast / Linear-Style Floating Command Palette (⌘K Spotlight) --}}
<div id="commandPaletteModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-label="Command Palette">
    {{-- Backdrop with soft blur --}}
    <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" onclick="closeCommandPalette()"></div>

    {{-- Palette Floating Dialog --}}
    <div class="relative min-h-screen flex items-start justify-center pt-16 sm:pt-24 px-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-xl rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl overflow-hidden flex flex-col transition-all transform scale-100">
            
            {{-- Search Header --}}
            <div class="p-3 border-b border-light-border dark:border-dark-border flex items-center gap-2.5 bg-light-canvas/50 dark:bg-dark-canvas/50">
                <i class="ri-search-2-line text-sm text-brand-500 flex-shrink-0"></i>
                <input type="text" id="commandPaletteInput" class="w-full bg-transparent border-0 p-0 text-sm text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:ring-0 focus:outline-none" placeholder="Type a command or search pages..." autocomplete="off">
                <kbd class="px-1.5 py-0.5 rounded border border-light-border dark:border-dark-border text-2xs font-mono text-light-muted dark:text-dark-muted flex-shrink-0 cursor-pointer" onclick="closeCommandPalette()">ESC</kbd>
            </div>

            {{-- Command List Container --}}
            <div class="max-h-[360px] overflow-y-auto scrollbar p-2 space-y-3" id="commandListContainer">
                
                {{-- Group: Navigation --}}
                <div class="command-group space-y-0.5">
                    <div class="px-2.5 py-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        Navigation
                    </div>
                    <a href="{{ route('dashboard') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="dashboard home metrics executive overview">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-dashboard-3-line text-brand-500 text-sm"></i>
                            <span>Executive Dashboard</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">G D</kbd>
                    </a>
                    <a href="{{ route('analytics.index') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="analytics bi business intelligence sales revenue inventory">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-bar-chart-2-line text-emerald-500 text-sm"></i>
                            <span>Business Intelligence & Analytics</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">G A</kbd>
                    </a>
                    <a href="{{ route('conversations.index') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="conversations chat inbox threads customer support">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-message-3-line text-sky-500 text-sm"></i>
                            <span>Omni-Channel Conversations</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">G C</kbd>
                    </a>
                    <a href="{{ route('simulator.index') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="simulator chat bot test playground pipeline">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-robot-2-line text-purple-500 text-sm"></i>
                            <span>Chat Simulator & Diagnostics</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">G S</kbd>
                    </a>
                    <a href="{{ route('widget.manage') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="widget embed web chat script code storefront">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-code-s-slash-line text-amber-500 text-sm"></i>
                            <span>Web Chat Widget Studio</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">G W</kbd>
                    </a>
                    <a href="{{ route('knowledge-base.index') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="knowledge base faqs documents questions answers rag typesense">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-book-open-line text-teal-500 text-sm"></i>
                            <span>Knowledge Base & FAQs</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">G K</kbd>
                    </a>
                    <a href="{{ route('users.index') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="users team access members accounts">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-user-settings-line text-indigo-500 text-sm"></i>
                            <span>Users & Team Members</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">G U</kbd>
                    </a>
                </div>

                {{-- Group: Quick Actions --}}
                <div class="command-group space-y-0.5 pt-2 border-t border-light-border dark:border-dark-border">
                    <div class="px-2.5 py-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        Actions
                    </div>
                    <button type="button" onclick="toggleAdminTheme(); closeCommandPalette();" class="palette-item w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="toggle theme dark light mode switch color">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-sun-moon-line text-amber-400 text-sm"></i>
                            <span>Toggle Dark / Light Theme</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">T</kbd>
                    </button>
                    <a href="{{ route('cache-clear') }}" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="clear cache flush optimize artisan">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-sparkling-2-line text-cyan-500 text-sm"></i>
                            <span>Flush Application Cache</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">C</kbd>
                    </a>
                    <a href="{{ route('home') }}" target="_blank" class="palette-item flex items-center justify-between px-2.5 py-2 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" data-keywords="visit storefront customer view shop live">
                        <span class="flex items-center gap-2.5">
                            <i class="ri-external-link-line text-rose-500 text-sm"></i>
                            <span>Open Storefront in New Tab</span>
                        </span>
                        <kbd class="text-2xs text-light-muted dark:text-dark-muted font-mono">⇧ S</kbd>
                    </a>
                </div>

                {{-- Empty state when search has no matches --}}
                <div id="paletteEmptyState" class="hidden py-8 text-center text-xs text-light-muted dark:text-dark-muted">
                    <i class="ri-search-eye-line text-2xl opacity-40 block mb-1"></i>
                    No commands matching your query.
                </div>
            </div>

            {{-- Footer Keyboard Guide --}}
            <div class="px-3 py-2 border-t border-light-border dark:border-dark-border bg-light-canvas/60 dark:bg-dark-canvas/60 flex items-center justify-between text-2xs text-light-muted dark:text-dark-muted">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><kbd class="px-1 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">↑</kbd><kbd class="px-1 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">↓</kbd> to navigate</span>
                    <span class="flex items-center gap-1"><kbd class="px-1.5 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">↵</kbd> to select</span>
                    <span class="flex items-center gap-1"><kbd class="px-1.5 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">esc</kbd> to close</span>
                </div>
                <span class="font-mono text-brand-500 font-semibold">Entrepreneurs AI</span>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const modal = document.getElementById('commandPaletteModal');
        const input = document.getElementById('commandPaletteInput');
        const emptyState = document.getElementById('paletteEmptyState');
        let selectedIndex = 0;

        window.openCommandPalette = function() {
            if (!modal) return;
            modal.classList.remove('hidden');
            if (input) {
                input.value = '';
                input.focus();
                filterCommands('');
            }
        };

        window.closeCommandPalette = function() {
            if (!modal) return;
            modal.classList.add('hidden');
        };

        // Filter commands in real time
        function filterCommands(query) {
            const q = (query || '').toLowerCase().trim();
            const items = document.querySelectorAll('.palette-item');
            let visibleCount = 0;

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                const keywords = (item.getAttribute('data-keywords') || '').toLowerCase();
                const matches = text.includes(q) || keywords.includes(q);
                item.style.display = matches ? 'flex' : 'none';
                if (matches) visibleCount++;
            });

            // Toggle group headings
            document.querySelectorAll('.command-group').forEach(grp => {
                const visibleInGroup = grp.querySelectorAll('.palette-item:not([style*="display: none"])').length;
                grp.style.display = visibleInGroup > 0 ? 'block' : 'none';
            });

            if (emptyState) {
                emptyState.classList.toggle('hidden', visibleCount > 0);
            }

            selectedIndex = 0;
            updateSelection();
        }

        function getVisibleItems() {
            return Array.from(document.querySelectorAll('.palette-item:not([style*="display: none"])'));
        }

        function updateSelection() {
            const visible = getVisibleItems();
            visible.forEach((el, idx) => {
                if (idx === selectedIndex) {
                    el.classList.add('bg-brand-500/10', 'text-brand-500');
                    el.classList.remove('text-light-text', 'dark:text-dark-text');
                    el.scrollIntoView({ block: 'nearest' });
                } else {
                    el.classList.remove('bg-brand-500/10', 'text-brand-500');
                    el.classList.add('text-light-text', 'dark:text-dark-text');
                }
            });
        }

        if (input) {
            input.addEventListener('input', function(e) {
                filterCommands(e.target.value);
            });

            input.addEventListener('keydown', function(e) {
                const visible = getVisibleItems();
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (visible.length > 0) {
                        selectedIndex = (selectedIndex + 1) % visible.length;
                        updateSelection();
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (visible.length > 0) {
                        selectedIndex = (selectedIndex - 1 + visible.length) % visible.length;
                        updateSelection();
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (visible[selectedIndex]) {
                        visible[selectedIndex].click();
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeCommandPalette();
                }
            });
        }

        // Global Shortcut: ⌘K or Ctrl+K opens Command Palette
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (modal && !modal.classList.contains('hidden')) {
                    closeCommandPalette();
                } else {
                    openCommandPalette();
                }
            } else if (e.key === 'Escape') {
                if (modal && !modal.classList.contains('hidden')) {
                    closeCommandPalette();
                }
            }
        });
    })();
</script>
