@extends('admin.app')

@section('title')
    Web Chat Widget Studio
@endsection

@push('custom-style')
<style>
    .studio-hero-card {
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 50%, #f0fdf4 100%);
        border: 1px solid #dbeafe;
        border-radius: 14px;
        padding: 1.5rem 1.75rem;
    }
    .studio-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .studio-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.06);
    }
    .code-editor-box {
        background: #0f172a;
        border-radius: 10px;
        padding: 1.15rem;
        position: relative;
        border: 1px solid #1e293b;
    }
    .code-tab-btn {
        padding: 6px 14px;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: #64748b;
        transition: all 0.15s ease;
    }
    .code-tab-btn.active {
        background: #eff6ff;
        color: #2563eb;
    }
    .quick-test-chip {
        font-size: 0.82rem;
        font-weight: 500;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #334155;
        padding: 8px 14px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
        text-align: left;
    }
    .quick-test-chip:hover {
        background: #eff6ff;
        border-color: #93c5fd;
        color: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.1);
    }
    /* macOS Browser Frame Mockup */
    .browser-frame-mockup {
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.08), 0 4px 10px -2px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .browser-titlebar {
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .browser-dots {
        display: flex;
        gap: 6px;
    }
    .browser-dot {
        width: 11px;
        height: 11px;
        border-radius: 50%;
    }
    .browser-dot.red { background: #ff5f56; }
    .browser-dot.yellow { background: #ffbd2e; }
    .browser-dot.green { background: #27c93f; }
    .browser-address-bar {
        flex: 1;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 3px 12px;
        font-size: 11px;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 6px;
        max-width: 380px;
        margin: 0 auto;
    }
    .device-switcher-btn {
        padding: 4px 8px;
        font-size: 13px;
        border-radius: 6px;
        border: 1px solid transparent;
        background: transparent;
        color: #64748b;
        cursor: pointer;
    }
    .device-switcher-btn.active {
        background: #ffffff;
        border-color: #cbd5e1;
        color: #0f172a;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .preview-viewport-container {
        background: #e2e8f0;
        display: flex;
        justify-content: center;
        padding: 0;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    #widgetPreviewFrame {
        width: 100%;
        height: 640px;
        border: none;
        background: #ffffff;
        transition: width 0.3s ease;
    }
    #widgetPreviewFrame.mobile-view {
        width: 380px;
        box-shadow: 0 0 20px rgba(0,0,0,0.15);
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-2">
    {{-- Header Banner --}}
    <div class="studio-hero-card mb-4 shadow-sm">
        <div class="row align-items-center g-3">
            <div class="col-lg-8 col-12">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge badge-soft-primary px-3 py-1.5 rounded-pill small fw-semibold">
                        <i class="ri-code-box-line me-1"></i> Storefront Chat Studio
                    </span>
                    <span class="badge badge-soft-success px-3 py-1.5 rounded-pill small fw-semibold">
                        <i class="ri-checkbox-circle-fill me-1"></i> Driver Live
                    </span>
                    <span class="text-muted small">Workspace: <strong>{{ $workspace->name ?? 'Default Workspace' }}</strong></span>
                </div>
                <h4 class="fw-bold text-dark mb-1">
                    Storefront Web Chat Widget Studio
                </h4>
                <p class="text-muted small mb-0">
                    Deploy your zero-latency, multi-lingual AI customer support assistant on any e-commerce storefront, WordPress site, or custom web application.
                </p>
            </div>
            <div class="col-lg-4 col-12 text-lg-end d-flex gap-2 justify-content-lg-end">
                <a href="{{ route('widget.demo') }}" target="_blank" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
                    <i class="ri-external-link-line"></i> Fullscreen Preview
                </a>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1 shadow-sm" onclick="reloadPreview()">
                    <i class="ri-refresh-line"></i> Refresh Sandbox
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Studio Controls & Integration Snippet --}}
        <div class="col-xl-6 col-12">
            {{-- Quick Embed Snippet Card --}}
            <div class="card studio-card mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="icon-box-primary" style="width: 34px; height: 34px; font-size: 1.15rem;">
                            <i class="ri-code-s-slash-line"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">One-Line Embed Code</h6>
                            <small class="text-muted">Instant installation across all storefront platforms</small>
                        </div>
                    </div>
                    <span class="badge badge-soft-success px-2.5 py-1 rounded-pill small fw-semibold">
                        Production Ready
                    </span>
                </div>
                <div class="card-body p-4">
                    {{-- Platform Tabs --}}
                    <div class="d-flex gap-2 mb-3 border-bottom pb-2">
                        <button type="button" class="code-tab-btn active" onclick="switchSnippetTab('html', this)">HTML / JS Tag</button>
                        <button type="button" class="code-tab-btn" onclick="switchSnippetTab('wordpress', this)">WordPress / WooCommerce</button>
                        <button type="button" class="code-tab-btn" onclick="switchSnippetTab('shopify', this)">Shopify Liquid</button>
                        <button type="button" class="code-tab-btn" onclick="switchSnippetTab('nextjs', this)">Next.js / React</button>
                    </div>

                    {{-- Snippet Box --}}
                    <div class="code-editor-box mb-3">
                        <pre class="text-light mb-0 small" style="white-space: pre-wrap; word-break: break-all; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;" id="widgetEmbedCode">&lt;script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" async&gt;&lt;/script&gt;</pre>
                        <button type="button" class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2 shadow-sm" onclick="copyEmbedCode(this)">
                            <i class="ri-file-copy-line me-1"></i> Copy Code
                        </button>
                    </div>

                    <p class="text-muted small mb-3" id="snippetInstructionText">
                        Paste this single script tag right before the closing <code>&lt;/body&gt;</code> tag on any HTML, WordPress theme, WooCommerce, or custom storefront.
                    </p>

                    <div class="row g-2 text-muted small pt-2 border-top">
                        <div class="col-sm-6 col-12 d-flex align-items-center gap-2">
                            <i class="ri-check-line text-success fs-5"></i> Sub-second Synchronous Response
                        </div>
                        <div class="col-sm-6 col-12 d-flex align-items-center gap-2">
                            <i class="ri-check-line text-success fs-5"></i> Auto Session State Storage
                        </div>
                        <div class="col-sm-6 col-12 d-flex align-items-center gap-2">
                            <i class="ri-check-line text-success fs-5"></i> Zero External CSS Conflicts
                        </div>
                        <div class="col-sm-6 col-12 d-flex align-items-center gap-2">
                            <i class="ri-check-line text-success fs-5"></i> Mobile & Desktop Responsive Drawer
                        </div>
                    </div>
                </div>
            </div>

            {{-- Channel Account Metadata & Telemetry --}}
            <div class="card studio-card mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="ri-shield-check-line text-primary me-2"></i> Integration Telemetry & Identity</h6>
                    <span class="badge badge-soft-primary small">Driver #{{ $channelAccount->id ?? 1 }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block mb-1">Account Display Name</small>
                                <strong class="text-dark">{{ $channelAccount->name ?? 'Storefront Web Widget' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block mb-1">Channel Driver</small>
                                <span class="badge badge-soft-info px-2.5 py-1 rounded">Website Driver</span>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block mb-1">Account External ID</small>
                                <code class="fw-semibold text-primary">{{ $channelAccount->external_id ?? 'widget_default' }}</code>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="p-3 bg-light rounded-3">
                                <small class="text-muted d-block mb-1">Connection Telemetry</small>
                                <span class="badge badge-soft-success px-2.5 py-1 rounded">
                                    <i class="ri-checkbox-circle-fill me-1"></i> Connected & Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Interactive Test Inquiries Card --}}
            <div class="card studio-card">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="fw-bold mb-0 text-dark"><i class="ri-sparkling-fill text-warning me-2"></i> Try Inquiries in Live Sandbox</h6>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        Click any prompt below to automatically pop open the chat drawer in the live storefront on the right and submit the inquiry:
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="quick-test-chip" onclick="sendTestPrompt('apnara ki ki sale koren?')">
                            <span>🛍️</span> <span>apnara ki ki sale koren?</span>
                        </button>
                        <button type="button" class="quick-test-chip" onclick="sendTestPrompt('do you have mobile phones?')">
                            <span>📱</span> <span>do you have mobile phones?</span>
                        </button>
                        <button type="button" class="quick-test-chip" onclick="sendTestPrompt('ডেলিভারি চার্জ এবং সময় কত?')">
                            <span>🚚</span> <span>ডেলিভারি চার্জ এবং সময় কত?</span>
                        </button>
                        <button type="button" class="quick-test-chip" onclick="sendTestPrompt('আপনাদের শোরুমের ঠিকানা কোথায়?')">
                            <span>📍</span> <span>আপনাদের শোরুমের ঠিকানা কোথায়?</span>
                        </button>
                        <button type="button" class="quick-test-chip" onclick="sendTestPrompt('UltraBook Pro 14 ল্যাপটপটির দাম কত?')">
                            <span>💻</span> <span>UltraBook Pro 14 ল্যাপটপটির দাম কত?</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Simulated Storefront Device Frame Mockup --}}
        <div class="col-xl-6 col-12">
            <div class="browser-frame-mockup h-100 shadow">
                {{-- macOS Browser Titlebar --}}
                <div class="browser-titlebar">
                    <div class="browser-dots">
                        <span class="browser-dot red"></span>
                        <span class="browser-dot yellow"></span>
                        <span class="browser-dot green"></span>
                    </div>
                    <div class="browser-address-bar">
                        <i class="ri-lock-fill text-success" style="font-size: 11px;"></i>
                        <span>https://apex-storefront.com/catalog</span>
                    </div>
                    <div class="d-flex gap-1 align-items-center ms-auto">
                        <button type="button" class="device-switcher-btn active" id="btnViewDesktop" onclick="setDeviceView('desktop')" title="Desktop View">
                            <i class="ri-macbook-line"></i>
                        </button>
                        <button type="button" class="device-switcher-btn" id="btnViewMobile" onclick="setDeviceView('mobile')" title="Mobile View">
                            <i class="ri-smartphone-line"></i>
                        </button>
                        <button type="button" class="device-switcher-btn" onclick="reloadPreview()" title="Reload Frame">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                </div>

                {{-- Interactive Storefront Iframe --}}
                <div class="preview-viewport-container flex-grow-1" id="viewportContainer">
                    <iframe id="widgetPreviewFrame" src="{{ route('widget.demo') }}"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
<script>
    const snippets = {
        html: '<script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" async><\/script>',
        wordpress: '<!-- Add to header.php or functions.php wp_footer hook -->\n<script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" async><\/script>',
        shopify: '<!-- In Shopify Admin > Themes > Edit Code > theme.liquid (before </body>) -->\n<script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" async><\/script>',
        nextjs: '// In Next.js pages/_app.js or app/layout.jsx\nimport Script from "next/script";\n\n<Script\n  src="{{ $widgetUrl }}"\n  data-endpoint="{{ $chatEndpoint }}"\n  data-account-id="{{ $channelAccount->id ?? 1 }}"\n  strategy="afterInteractive"\n/>'
    };

    const instructions = {
        html: 'Paste this single script tag right before the closing <code>&lt;/body&gt;</code> tag on any HTML, WordPress theme, WooCommerce, or custom storefront.',
        wordpress: 'For WordPress, paste this in <strong>Appearance &gt; Theme File Editor &gt; footer.php</strong> right before <code>&lt;/body&gt;</code>, or use any Header & Footer Code Manager plugin.',
        shopify: 'In Shopify Admin, navigate to <strong>Online Store &gt; Themes &gt; Edit Code</strong>, open <code>layout/theme.liquid</code>, and paste right above <code>&lt;/body&gt;</code>.',
        nextjs: 'In Next.js (App Router or Pages Router), import the Next Script component and inject it into your root layout with <code>strategy="afterInteractive"</code>.'
    };

    function switchSnippetTab(tabKey, btn) {
        document.querySelectorAll('.code-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.getElementById('widgetEmbedCode').textContent = snippets[tabKey] || snippets.html;
        document.getElementById('snippetInstructionText').innerHTML = instructions[tabKey] || instructions.html;
    }

    function copyEmbedCode(btn) {
        const code = document.getElementById('widgetEmbedCode').innerText;
        navigator.clipboard.writeText(code).then(() => {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ri-check-line me-1"></i> Copied!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 2000);
        });
    }

    function reloadPreview() {
        const frame = document.getElementById('widgetPreviewFrame');
        if (frame) {
            frame.src = frame.src;
        }
    }

    function setDeviceView(mode) {
        const frame = document.getElementById('widgetPreviewFrame');
        const btnDesktop = document.getElementById('btnViewDesktop');
        const btnMobile = document.getElementById('btnViewMobile');

        if (mode === 'mobile') {
            frame.classList.add('mobile-view');
            btnMobile.classList.add('active');
            btnDesktop.classList.remove('active');
        } else {
            frame.classList.remove('mobile-view');
            btnDesktop.classList.add('active');
            btnMobile.classList.remove('active');
        }
    }

    function sendTestPrompt(text) {
        const frame = document.getElementById('widgetPreviewFrame');
        if (!frame) return;

        try {
            const iframeDoc = frame.contentDocument || frame.contentWindow.document;
            const bubble = iframeDoc.getElementById('agy-chat-bubble');
            const chatWin = iframeDoc.getElementById('agy-chat-window');

            // Open chat window if not visible
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
            console.log('Cross-origin restriction or frame loading:', e);
        }
    }
</script>
@endpush
