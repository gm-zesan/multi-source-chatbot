@extends('admin.app')

@section('title')
    Web Chat Widget & Storefront Integration
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="mb-1 text-dark fw-bold">Storefront Web Chat Widget</h4>
            <p class="text-muted mb-0 small">Embed multi-lingual AI customer support on any website or e-commerce storefront · Active Workspace: <span class="badge bg-primary">{{ $workspace->name ?? 'Default Workspace' }}</span></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('widget.demo') }}" target="_blank" class="btn btn-outline-primary d-flex align-items-center gap-1 shadow-sm">
                <i class="ri-external-link-line"></i> Fullscreen Preview
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Embed Snippet & Installation Instructions --}}
        <div class="col-xl-6 col-12">
            {{-- Quick Embed Snippet Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-2">
                                <i class="ri-code-s-slash-line fs-5"></i>
                            </div>
                            <h5 class="fw-bold mb-0 text-dark">One-Line Embed Code</h5>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">Ready for Production</span>
                    </div>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted small">
                        Copy and paste this single script tag right before the closing <code>&lt;/body&gt;</code> tag on any HTML, WordPress, WooCommerce, or Shopify theme:
                    </p>

                    <div class="position-relative bg-dark rounded-3 p-3 mb-3">
                        <pre class="text-light mb-0 small" style="white-space: pre-wrap; word-break: break-all;" id="widgetEmbedCode">&lt;script src="{{ $widgetUrl }}" data-endpoint="{{ $chatEndpoint }}" data-account-id="{{ $channelAccount->id ?? 1 }}" async&gt;&lt;/script&gt;</pre>
                        <button type="button" class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2" onclick="copyEmbedCode(this)">
                            <i class="ri-file-copy-line me-1"></i> Copy Code
                        </button>
                    </div>

                    <div class="row g-2 text-muted small">
                        <div class="col-6">
                            <i class="ri-check-line text-success"></i> Synchronous Sub-second Response
                        </div>
                        <div class="col-6">
                            <i class="ri-check-line text-success"></i> Auto Session State Storage
                        </div>
                        <div class="col-6">
                            <i class="ri-check-line text-success"></i> Zero External CSS Conflicts
                        </div>
                        <div class="col-6">
                            <i class="ri-check-line text-success"></i> Mobile-Optimized Bottom Sheet
                        </div>
                    </div>
                </div>
            </div>

            {{-- Channel Account Details --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold mb-0 text-dark">Connected Web Channel Account</h5>
                </div>
                <div class="card-body px-4 py-3">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted" style="width: 140px;">Account Name:</td>
                                    <td><strong>{{ $channelAccount->name ?? 'Storefront Web Widget' }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Channel Driver:</td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info">Website / Web Widget</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Account External ID:</td>
                                    <td><code>{{ $channelAccount->external_id ?? 'widget_default' }}</code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status:</td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success">
                                            <i class="ri-checkbox-circle-fill me-1"></i> Connected & Active
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Quick Test Inquiries Card --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold mb-0 text-dark">Try These Inquiries in Preview</h5>
                </div>
                <div class="card-body px-4 py-3">
                    <p class="text-muted small">Click any prompt to instantly test the bot's response in the live preview on the right:</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="sendTestPrompt('apnara ki ki sale koren?')">
                            🛍️ apnara ki ki sale koren?
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="sendTestPrompt('ami ekta phone kinte cai.')">
                            📱 ami ekta phone kinte cai.
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="sendTestPrompt('ডেলিভারি চার্জ কত?')">
                            🚚 ডেলিভারি চার্জ কত?
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="sendTestPrompt('apnader showroom kothay?')">
                            📍 apnader showroom kothay?
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Live Embedded Preview Frame --}}
        <div class="col-xl-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Live Storefront Sandbox</h5>
                        <small class="text-muted">Interactive live preview running on <code>/widget/chat.js</code></small>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="reloadPreview()">
                        <i class="ri-refresh-line"></i> Reload Preview
                    </button>
                </div>
                <div class="card-body p-0" style="min-height: 580px;">
                    <iframe id="widgetPreviewFrame" src="{{ route('widget.demo') }}" style="width: 100%; height: 580px; border: none; border-radius: 0 0 12px 12px;"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyEmbedCode(btn) {
    const code = document.getElementById('widgetEmbedCode').innerText;
    navigator.clipboard.writeText(code).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="ri-check-line me-1"></i> Copied!';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-primary');
        }, 2000);
    });
}

function reloadPreview() {
    const frame = document.getElementById('widgetPreviewFrame');
    frame.src = frame.src;
}

function sendTestPrompt(text) {
    const frame = document.getElementById('widgetPreviewFrame');
    try {
        const frameDoc = frame.contentDocument || frame.contentWindow.document;
        const bubble = frameDoc.getElementById('agy-chat-bubble');
        const win = frameDoc.getElementById('agy-chat-window');
        const input = frameDoc.getElementById('agy-chat-input');
        const form = frameDoc.getElementById('agy-chat-form');

        if (bubble && win && win.style.display !== 'flex') {
            bubble.click();
        }
        if (input && form) {
            input.value = text;
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    } catch (e) {
        console.warn('Frame interaction', e);
    }
}
</script>
@endsection
