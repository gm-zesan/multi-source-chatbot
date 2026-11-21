@extends('admin.app')

@section('title')
    Web Chat Widget Studio
@endsection

@section('content')
    <div class="space-y-4">
        {{-- Header Banner --}}
        <div class="app-card p-3 sm:p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-2xs text-light-muted dark:text-dark-muted flex items-center gap-1.5">
                        <i class="ri-building-4-line text-brand-500"></i> Workspace: <strong
                            class="text-light-text dark:text-dark-text">{{ $workspace->name ?? 'Default Workspace' }}</strong>
                    </span>
                </div>
                <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text">
                    Storefront Web Chat Widget Studio
                </h1>
                <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                    Deploy your zero-latency, multi-lingual AI customer support assistant on any e-commerce storefront,
                    WordPress site, or custom web application.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('widget.demo') }}" target="_blank" class="app-btn app-btn-secondary">
                    <i class="ri-external-link-line text-xs"></i> Fullscreen
                </a>
                <button type="button" class="app-btn app-btn-primary" onclick="reloadPreview()">
                    <i class="ri-refresh-line text-xs"></i> Refresh
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Left Column: Embed & Settings --}}
            <div class="space-y-4">
                {{-- Embed Code Card --}}
                <div class="app-card overflow-hidden">
                    <div class="app-card-header">
                        <div class="flex items-center gap-2">
                            <i class="ri-code-s-slash-line text-brand-500 text-sm"></i>
                            <div>
                                <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">One-Line Embed Code
                                </h2>
                                <div class="text-2xs text-light-muted dark:text-dark-muted">Instant installation across all
                                    storefront platforms</div>
                            </div>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3">
                        {{-- Platform Tabs --}}
                        <div
                            class="flex items-center gap-1 border-b border-light-border dark:border-dark-border pb-2 overflow-x-auto">
                            <button type="button"
                                class="code-tab-btn px-2.5 py-1 rounded text-xs font-medium bg-brand-500/10 text-brand-500 font-semibold"
                                onclick="switchSnippetTab('html', this)">HTML / JS</button>
                            <button type="button"
                                class="code-tab-btn px-2.5 py-1 rounded text-xs font-medium text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text"
                                onclick="switchSnippetTab('wordpress', this)">WordPress</button>
                            <button type="button"
                                class="code-tab-btn px-2.5 py-1 rounded text-xs font-medium text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text"
                                onclick="switchSnippetTab('shopify', this)">Shopify</button>
                            <button type="button"
                                class="code-tab-btn px-2.5 py-1 rounded text-xs font-medium text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text"
                                onclick="switchSnippetTab('nextjs', this)">Next.js</button>
                        </div>

                        {{-- Snippet Box --}}
                        <div class="relative p-3 rounded bg-dark-canvas border border-dark-border text-slate-200">
                            <pre class="text-2xs font-mono whitespace-pre-wrap break-all pr-16 text-slate-300"
                                id="widgetEmbedCode">&lt;script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" data-title="{{ $workspace->name ?? 'Customer Support' }}" data-greeting="আসসালামু আলাইকুম! আপনাকে কীভাবে সাহায্য করতে পারি?" async&gt;&lt;/script&gt;</pre>
                            <button type="button" class="absolute top-2 right-2 app-btn app-btn-primary !h-6 !px-2 text-2xs"
                                onclick="copyEmbedCode(this)">
                                <i class="ri-file-copy-line"></i> Copy
                            </button>
                        </div>

                        <p class="text-2xs text-light-muted dark:text-dark-muted" id="snippetInstructionText">
                            Paste this single script tag right before the closing <code>&lt;/body&gt;</code> tag on any
                            HTML, WordPress theme, WooCommerce, or custom storefront.
                        </p>

                        <div
                            class="grid grid-cols-2 gap-2 text-2xs text-light-muted dark:text-dark-muted pt-2 border-t border-light-border dark:border-dark-border">
                            <div class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500 text-sm"></i>
                                Sub-second Response</div>
                            <div class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500 text-sm"></i>
                                Auto Session Memory</div>
                            <div class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500 text-sm"></i>
                                Zero CSS Conflicts</div>
                            <div class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500 text-sm"></i>
                                Mobile & Desktop Drawer</div>
                        </div>
                    </div>
                </div>

                {{-- Telemetry Metadata --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-shield-check-line text-brand-500"></i>
                            <span>Integration Identity & Telemetry</span>
                        </div>
                        <span class="app-badge app-badge-secondary">Driver #{{ $channelAccount->id ?? 1 }}</span>
                    </div>
                    <div class="app-card-body">
                        <div class="grid grid-cols-2 gap-2.5 text-xs">
                            <div
                                class="p-2.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                                <div class="text-2xs text-light-muted dark:text-dark-muted">Account Display Name</div>
                                <strong
                                    class="text-light-text dark:text-dark-text">{{ $channelAccount->name ?? 'Storefront Web Widget' }}</strong>
                            </div>
                            <div
                                class="p-2.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                                <div class="text-2xs text-light-muted dark:text-dark-muted">Channel Driver</div>
                                <span class="app-badge app-badge-primary">Website Driver</span>
                            </div>
                            <div
                                class="p-2.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                                <div class="text-2xs text-light-muted dark:text-dark-muted">Account External ID</div>
                                <code
                                    class="text-brand-500 font-mono text-2xs">{{ $channelAccount->external_id ?? 'widget_default' }}</code>
                            </div>
                            <div
                                class="p-2.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                                <div class="text-2xs text-light-muted dark:text-dark-muted">Connection Status</div>
                                <span class="app-badge app-badge-success"><i class="ri-checkbox-circle-fill text-xs mr-1"></i> Connected & Live</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Interactive Test Inquiries Card --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-sparkling-fill text-amber-500"></i>
                            <span>Test Inquiries in Sandbox</span>
                        </div>
                    </div>
                    <div class="app-card-body space-y-2">
                        <p class="text-2xs text-light-muted dark:text-dark-muted">
                            Click any prompt below to automatically open the chat drawer in the live preview and test:
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex items-center"
                                onclick="sendTestPrompt('apnara ki ki sale koren?')">
                                <i class="ri-shopping-bag-3-line text-brand-500 mr-1 text-xs"></i> apnara ki ki sale koren?
                            </button>
                            <button type="button" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex items-center"
                                onclick="sendTestPrompt('do you have mobile phones?')">
                                <i class="ri-smartphone-line text-sky-500 mr-1 text-xs"></i> do you have mobile phones?
                            </button>
                            <button type="button" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex items-center"
                                onclick="sendTestPrompt('ডেলিভারি চার্জ এবং সময় কত?')">
                                <i class="ri-truck-line text-emerald-500 mr-1 text-xs"></i> ডেলিভারি চার্জ কত?
                            </button>
                            <button type="button" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex items-center"
                                onclick="sendTestPrompt('আপনাদের শোরুমের ঠিকানা কোথায়?')">
                                <i class="ri-map-pin-line text-rose-500 mr-1 text-xs"></i> শোরুমের ঠিকানা কোথায়?
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Live Web Chat Widget Preview Frame --}}
            <div class="app-card overflow-hidden flex flex-col h-[700px]">
                <div class="app-card-header flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <i class="ri-macbook-line text-brand-500 text-sm"></i>
                        <div>
                            <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Live Web Chat Widget
                                Preview</h2>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Interactive storefront sandbox with
                                live widget</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div
                            class="inline-flex items-center p-0.5 rounded bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-2xs">
                            <button type="button"
                                class="px-2 py-0.5 rounded font-medium text-brand-500 bg-light-surface dark:bg-dark-surface shadow-xs transition-all"
                                id="btnViewDesktop" onclick="setDeviceView('desktop')" title="Desktop View">
                                <i class="ri-macbook-line mr-1"></i> Desktop
                            </button>
                            <button type="button"
                                class="px-2 py-0.5 rounded font-medium text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-all"
                                id="btnViewMobile" onclick="setDeviceView('mobile')" title="Mobile View">
                                <i class="ri-smartphone-line mr-1"></i> Mobile
                            </button>
                        </div>
                        <button type="button" class="app-btn app-btn-secondary !h-7 !px-2 text-2xs"
                            onclick="reloadPreview()" title="Reload Frame">
                            <i class="ri-refresh-line"></i>
                        </button>
                        <a href="{{ route('widget.demo') }}" target="_blank"
                            class="app-btn app-btn-secondary !h-7 !px-2 text-2xs" title="Open in new window">
                            <i class="ri-external-link-line"></i>
                        </a>
                    </div>
                </div>

                {{-- Storefront Iframe Container --}}
                <div class="flex-1 bg-light-canvas dark:bg-dark-canvas flex justify-center items-center overflow-hidden p-2.5"
                    id="viewportContainer">
                    <div id="frameWrapper"
                        class="w-full h-full rounded border border-light-border dark:border-dark-border overflow-hidden bg-light-surface dark:bg-dark-surface shadow-xs transition-all duration-200">
                        <iframe id="widgetPreviewFrame" src="{{ route('widget.demo') }}"
                            class="w-full h-full border-0 transition-all duration-200"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('custom-scripts')
    <script>
        const snippets = {
            html: '<script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" data-title="{{ $workspace->name ?? 'Customer Support' }}" data-greeting="আসসালামু আলাইকুম! আপনাকে কীভাবে সাহায্য করতে পারি?" async><\/script>',
            wordpress: '<!-- Add to footer.php before </body> -->\n<script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" data-title="{{ $workspace->name ?? 'Customer Support' }}" data-greeting="আসসালামু আলাইকুম! আপনাকে কীভাবে সাহায্য করতে পারি?" async><\/script>',
            shopify: '<!-- In theme.liquid before </body> -->\n<script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" data-title="{{ $workspace->name ?? 'Customer Support' }}" data-greeting="আসসালামু আলাইকুম! আপনাকে কীভাবে সাহায্য করতে পারি?" async><\/script>',
            nextjs: '// In pages/_app.js or app/layout.jsx\nimport Script from "next/script";\n\n<Script\n  src="{{ $widgetUrl }}"\n  data-endpoint="{{ $chatEndpoint }}"\n  data-account-id="{{ $channelAccount->id ?? 1 }}"\n  data-title="{{ $workspace->name ?? 'Customer Support' }}"\n  data-greeting="আসসালামু আলাইকুম! আপনাকে কীভাবে সাহায্য করতে পারি?"\n  strategy="afterInteractive"\n/>'
        };

        const instructions = {
            html: 'Paste this single script tag right before the closing <code>&lt;/body&gt;</code> tag on any HTML, WordPress theme, WooCommerce, or custom storefront.',
            wordpress: 'For WordPress, paste this in <strong>Appearance &gt; Theme File Editor &gt; footer.php</strong> right before <code>&lt;/body&gt;</code>.',
            shopify: 'In Shopify Admin, open <code>layout/theme.liquid</code>, and paste right above <code>&lt;/body&gt;</code>.',
            nextjs: 'In Next.js, import Next Script component with <code>strategy="afterInteractive"</code>.'
        };

        function switchSnippetTab(tabKey, btn) {
            document.querySelectorAll('.code-tab-btn').forEach(b => {
                b.classList.remove('bg-brand-500/10', 'text-brand-500', 'font-semibold');
                b.classList.add('text-light-text-secondary', 'dark:text-dark-text-secondary');
            });
            btn.classList.add('bg-brand-500/10', 'text-brand-500', 'font-semibold');
            btn.classList.remove('text-light-text-secondary', 'dark:text-dark-text-secondary');

            document.getElementById('widgetEmbedCode').textContent = snippets[tabKey] || snippets.html;
            document.getElementById('snippetInstructionText').innerHTML = instructions[tabKey] || instructions.html;
        }

        function copyEmbedCode(btn) {
            const code = document.getElementById('widgetEmbedCode').innerText;
            navigator.clipboard.writeText(code).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="ri-check-line"></i> Copied!';
                setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
            });
        }

        function reloadPreview() {
            const frame = document.getElementById('widgetPreviewFrame');
            if (frame) frame.src = frame.src;
        }

        function setDeviceView(mode) {
            const frameWrapper = document.getElementById('frameWrapper');
            const btnDesktop = document.getElementById('btnViewDesktop');
            const btnMobile = document.getElementById('btnViewMobile');

            if (mode === 'mobile') {
                if (frameWrapper) {
                    frameWrapper.style.maxWidth = '390px';
                    frameWrapper.style.height = '620px';
                    frameWrapper.classList.add('rounded-2xl', 'shadow-lg');
                }
                if (btnMobile) {
                    btnMobile.className = 'px-2 py-0.5 rounded font-medium text-brand-500 bg-light-surface dark:bg-dark-surface shadow-xs transition-all';
                }
                if (btnDesktop) {
                    btnDesktop.className = 'px-2 py-0.5 rounded font-medium text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-all';
                }
            } else {
                if (frameWrapper) {
                    frameWrapper.style.maxWidth = '100%';
                    frameWrapper.style.height = '100%';
                    frameWrapper.classList.remove('rounded-2xl', 'shadow-lg');
                }
                if (btnDesktop) {
                    btnDesktop.className = 'px-2 py-0.5 rounded font-medium text-brand-500 bg-light-surface dark:bg-dark-surface shadow-xs transition-all';
                }
                if (btnMobile) {
                    btnMobile.className = 'px-2 py-0.5 rounded font-medium text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-all';
                }
            }
        }

        function sendTestPrompt(text) {
            const frame = document.getElementById('widgetPreviewFrame');
            if (!frame) return;

            try {
                const iframeDoc = frame.contentDocument || frame.contentWindow.document;
                const bubble = iframeDoc.getElementById('agy-chat-bubble');
                const chatWin = iframeDoc.getElementById('agy-chat-window');

                if (chatWin && chatWin.style.display !== 'flex') {
                    if (bubble) bubble.click();
                }

                setTimeout(() => {
                    const input = iframeDoc.getElementById('agy-chat-input');
                    const sendBtn = iframeDoc.getElementById('agy-chat-send');
                    if (input && sendBtn) {
                        input.value = text;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        sendBtn.click();
                    }
                }, 250);
            } catch (e) {
                console.log('Frame interaction error:', e);
            }
        }
    </script>
@endpush