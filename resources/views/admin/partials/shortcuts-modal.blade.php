{{-- Global Keyboard Shortcuts Cheat Sheet Modal (? or Shift + /) --}}
<div id="shortcutsCheatSheetModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-label="Keyboard Shortcuts">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" onclick="closeShortcutsModal()"></div>

    {{-- Floating Dialog --}}
    <div class="relative min-h-screen flex items-center justify-center p-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-lg rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl overflow-hidden flex flex-col transition-all">
            
            {{-- Header --}}
            <div class="p-3 sm:p-4 border-b border-light-border dark:border-dark-border flex items-center justify-between bg-light-canvas/50 dark:bg-dark-canvas/50">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-xs font-bold">
                        <i class="ri-keyboard-line"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-light-text dark:text-dark-text leading-tight">Keyboard Shortcuts</h3>
                        <p class="text-2xs text-light-muted dark:text-dark-muted">Power-user keybindings for mouse-free navigation</p>
                    </div>
                </div>
                <kbd class="px-1.5 py-0.5 rounded border border-light-border dark:border-dark-border text-2xs font-mono text-light-muted dark:text-dark-muted cursor-pointer" onclick="closeShortcutsModal()">ESC</kbd>
            </div>

            {{-- Shortcuts Grid --}}
            <div class="p-4 space-y-4 max-h-[420px] overflow-y-auto scrollbar text-xs">
                
                {{-- General Section --}}
                <div class="space-y-2">
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        General & Actions
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Command Palette</span>
                            <div class="flex items-center gap-0.5">
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">⌘</kbd>
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">K</kbd>
                            </div>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Toggle Sidebar Rail</span>
                            <kbd class="px-1.5 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">B</kbd>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Toggle Dark / Light</span>
                            <kbd class="px-1.5 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">T</kbd>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Shortcuts Cheat Sheet</span>
                            <kbd class="px-1.5 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">?</kbd>
                        </div>
                    </div>
                </div>

                {{-- Navigation Section --}}
                <div class="space-y-2 pt-2 border-t border-light-border dark:border-dark-border">
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        Page Jump Sequences (Press G then Key)
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Executive Dashboard</span>
                            <div class="flex items-center gap-0.5">
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">G</kbd>
                                <span class="text-2xs text-light-muted">then</span>
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">D</kbd>
                            </div>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Analytics & BI</span>
                            <div class="flex items-center gap-0.5">
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">G</kbd>
                                <span class="text-2xs text-light-muted">then</span>
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">A</kbd>
                            </div>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Conversations</span>
                            <div class="flex items-center gap-0.5">
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">G</kbd>
                                <span class="text-2xs text-light-muted">then</span>
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">C</kbd>
                            </div>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Simulator</span>
                            <div class="flex items-center gap-0.5">
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">G</kbd>
                                <span class="text-2xs text-light-muted">then</span>
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">S</kbd>
                            </div>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Knowledge Base</span>
                            <div class="flex items-center gap-0.5">
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">G</kbd>
                                <span class="text-2xs text-light-muted">then</span>
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">K</kbd>
                            </div>
                        </div>
                        <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 flex items-center justify-between">
                            <span class="text-light-text dark:text-dark-text">Users & Access</span>
                            <div class="flex items-center gap-0.5">
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">G</kbd>
                                <span class="text-2xs text-light-muted">then</span>
                                <kbd class="px-1 text-2xs font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">U</kbd>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-4 py-2.5 border-t border-light-border dark:border-dark-border bg-light-canvas/60 dark:bg-dark-canvas/60 flex items-center justify-between text-2xs text-light-muted dark:text-dark-muted">
                <span>Tip: Press <kbd class="px-1 font-mono rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">?</kbd> anywhere to reopen this cheatsheet</span>
                <span class="font-mono text-brand-500 font-semibold">Entrepreneurs AI</span>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const modal = document.getElementById('shortcutsCheatSheetModal');
        let pendingG = false;
        let gTimer = null;

        window.openShortcutsModal = function() {
            if (modal) modal.classList.remove('hidden');
        };

        window.closeShortcutsModal = function() {
            if (modal) modal.classList.add('hidden');
        };

        // Listen for keyboard hotkeys
        document.addEventListener('keydown', function(e) {
            // Do not intercept if user is typing inside an input, textarea, or contenteditable
            const tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) {
                return;
            }

            // Do not intercept if ⌘K command palette is open
            const cmdPalette = document.getElementById('commandPaletteModal');
            if (cmdPalette && !cmdPalette.classList.contains('hidden')) {
                return;
            }

            // '?' or 'Shift + /' opens shortcuts cheatsheet
            if (e.key === '?' || (e.shiftKey && e.key === '/')) {
                e.preventDefault();
                if (modal && !modal.classList.contains('hidden')) {
                    closeShortcutsModal();
                } else {
                    openShortcutsModal();
                }
                return;
            }

            // 'B' toggles sidebar rail
            if (e.key === 'b' || e.key === 'B') {
                e.preventDefault();
                if (typeof window.toggleSidebar === 'function') {
                    window.toggleSidebar();
                }
                return;
            }

            // 'T' toggles theme
            if (e.key === 't' || e.key === 'T') {
                e.preventDefault();
                if (typeof toggleAdminTheme === 'function') {
                    toggleAdminTheme();
                }
                return;
            }

            // 'G' sequence listener (G then D, G then A, etc.)
            if (e.key === 'g' || e.key === 'G') {
                pendingG = true;
                clearTimeout(gTimer);
                gTimer = setTimeout(() => { pendingG = false; }, 1200);
                return;
            }

            if (pendingG) {
                pendingG = false;
                clearTimeout(gTimer);
                const k = e.key.toLowerCase();
                const routes = {
                    'd': '{{ route('dashboard') }}',
                    'a': '{{ route('analytics.index') }}',
                    'c': '{{ route('conversations.index') }}',
                    's': '{{ route('simulator.index') }}',
                    'w': '{{ route('widget.manage') }}',
                    'k': '{{ route('knowledge-base.index') }}',
                    'u': '{{ route('users.index') }}',
                };

                if (routes[k]) {
                    e.preventDefault();
                    window.location.href = routes[k];
                    return;
                }
            }

            // ESC closes modal
            if (e.key === 'Escape') {
                if (modal && !modal.classList.contains('hidden')) {
                    closeShortcutsModal();
                }
            }
        });
    })();
</script>
