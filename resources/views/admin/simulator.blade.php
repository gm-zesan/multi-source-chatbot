@extends('admin.app')

@section('title')
    Chat Simulator & Infrastructure Pipeline Tester
@endsection

@push('custom-style')
<style>
    .simulator-container {
        height: calc(100vh - 140px);
        min-height: 600px;
    }
    
    .chat-card {
        display: flex;
        flex-direction: column;
        height: 100%;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        background: #ffffff;
        border: 1px solid #e5e7eb;
    }

    .chat-header {
        padding: 16px 20px;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #fafafa;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background: #f8fafc;
    }

    .message-bubble {
        max-width: 80%;
        padding: 12px 16px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.5;
        position: relative;
        animation: fadeIn 0.2s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-user {
        align-self: flex-end;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #ffffff;
        border-bottom-right-radius: 4px;
    }

    .message-bot {
        align-self: flex-start;
        background: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .message-meta {
        font-size: 11px;
        margin-top: 6px;
        opacity: 0.8;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chat-input-area {
        padding: 16px;
        border-top: 1px solid #f1f5f9;
        background: #ffffff;
        border-bottom-left-radius: 12px;
        border-bottom-right-radius: 12px;
    }

    .quick-prompts {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 10px;
        margin-bottom: 10px;
    }

    .quick-prompt-btn {
        font-size: 12px;
        padding: 6px 12px;
        border-radius: 20px;
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .quick-prompt-btn:hover {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .diag-card {
        height: 100%;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .diag-header {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        background: #0f172a;
        color: #ffffff;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    .diag-body {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .diag-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px;
    }

    .diag-title {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .status-badge {
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .status-ok { background: #dcfce7; color: #166534; }
    .status-degraded { background: #fef9c3; color: #854d0e; }
    .status-failed { background: #fee2e2; color: #991b1b; }
    .status-none { background: #f1f5f9; color: #64748b; }

    .score-progress {
        height: 8px;
        border-radius: 4px;
        background: #e2e8f0;
        overflow: hidden;
        margin-top: 4px;
    }

    .score-bar {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        transition: width 0.3s ease;
    }

    .entity-tag {
        display: inline-block;
        font-size: 12px;
        padding: 3px 8px;
        border-radius: 4px;
        background: #ede9fe;
        color: #5b21b6;
        margin-right: 4px;
        margin-bottom: 4px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
    <!-- Breadcrumb & Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1 text-dark font-weight-bold">
                <i class="ri-robot-2-line text-primary me-2"></i>Live Pipeline & Chat Simulator
            </h4>
            <p class="text-muted small mb-0">
                Test CRM Entity Extraction, Python Embeddings, and Typesense Hybrid Search locally without Facebook tokens.
            </p>
        </div>
        <div>
            <a href="{{ route('health') }}" target="_blank" class="btn btn-outline-info btn-sm me-2">
                <i class="ri-heart-pulse-line me-1"></i> Health Status
            </a>
            <a href="#" class="btn btn-outline-secondary btn-sm" onclick="window.location.reload();">
                <i class="ri-refresh-line me-1"></i> Clear Chat
            </a>
        </div>
    </div>

    <!-- Simulator Main Layout -->
    <div class="row simulator-container">
        <!-- Left: Interactive Chat Window -->
        <div class="col-lg-7 col-md-12 mb-3 mb-lg-0 h-100">
            <div class="chat-card">
                <div class="chat-header">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
                            <i class="ri-bot-line fs-5"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">AI Support Assistant</h6>
                            <span class="badge bg-success-subtle text-success p-1" style="font-size: 10px;">● Pipeline Active</span>
                        </div>
                    </div>
                    <small class="text-muted" id="chatTime">Ready</small>
                </div>

                <!-- Messages area -->
                <div class="chat-messages" id="chatMessages">
                    <div class="message-bubble message-bot">
                        👋 Hi! I am your AI Chatbot simulator. Type any question or message below.
                        <br><br>
                        <em>Tip: Try providing your email or phone number in your question to test the CRM contact extractor in real-time!</em>
                        <div class="message-meta text-muted">
                            <span>System Bot</span>
                        </div>
                    </div>
                </div>

                <!-- Input area -->
                <div class="chat-input-area">
                    <!-- Quick sample queries -->
                    <div class="quick-prompts">
                        <span class="text-muted small align-self-center me-1" style="font-size:11px;">Try:</span>
                        <button class="quick-prompt-btn" onclick="setQuery('What are your business hours?')">⏰ Opening Hours</button>
                        <button class="quick-prompt-btn" onclick="setQuery('Hi, my email is test@domain.com and phone is +8801700000000. How to reset password?')">👤 Contact + FAQ</button>
                        <button class="quick-prompt-btn" onclick="setQuery('Do you offer refunds or return policy?')">💸 Refund Policy</button>
                    </div>

                    <form id="chatForm" onsubmit="handleSend(event); return false;" class="d-flex gap-2">
                        <input type="text" id="userInput" class="form-control form-control-lg fs-6" placeholder="Type your message here..." autocomplete="off" required>
                        <button type="button" onclick="handleSend(event)" id="sendBtn" class="btn btn-primary px-4 d-flex align-items-center gap-1">
                            <i class="ri-send-plane-fill"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Live Pipeline Diagnostics -->
        <div class="col-lg-5 col-md-12 h-100">
            <div class="diag-card">
                <div class="diag-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0 text-white fw-bold"><i class="ri-cpu-line me-1"></i> Pipeline Inspector</h6>
                        <small class="text-white-50" style="font-size: 11px;">Real-time infrastructure trace</small>
                    </div>
                    <span class="status-badge status-none" id="totalTimeBadge">0 ms</span>
                </div>

                <div class="diag-body" id="diagBody">
                    <!-- Step 1: CRM Entity Extraction -->
                    <div class="diag-section">
                        <div class="diag-title text-purple">
                            <span><i class="ri-contacts-book-2-line me-1"></i> 1. Contact / CRM Extractor</span>
                            <span class="status-badge status-none" id="crmBadge">No Data</span>
                        </div>
                        <div id="crmResults">
                            <span class="text-muted small">Send a message to view extracted emails/phones.</span>
                        </div>
                    </div>

                    <!-- Step 2: Python Embedding Service -->
                    <div class="diag-section">
                        <div class="diag-title text-primary">
                            <span><i class="ri-code-s-slash-line me-1"></i> 2. Python FastAPI Embedding</span>
                            <span class="status-badge status-none" id="pyBadge">Idle</span>
                        </div>
                        <div id="pyResults">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Model: <strong id="pyModel">paraphrase-multilingual-mpnet-base-v2</strong></span>
                                <span>Dims: <strong id="pyDims">768</strong></span>
                            </div>
                            <div class="small text-muted mt-1" id="pyVectorSample">Vector: [ Waiting for request... ]</div>
                        </div>
                    </div>

                    <!-- Step 3: Typesense Hybrid Search -->
                    <div class="diag-section">
                        <div class="diag-title text-info">
                            <span><i class="ri-search-eye-line me-1"></i> 3. Typesense Search Engine</span>
                            <span class="status-badge status-none" id="tsBadge">Idle</span>
                        </div>
                        <div id="tsResults">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Match Type: <strong id="tsMatchType" class="text-dark">N/A</strong></span>
                                <span>Matched FAQ ID: <strong id="tsFaqId">None</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Scoring Breakdown & Confidence -->
                    <div class="diag-section">
                        <div class="diag-title text-success">
                            <span><i class="ri-bar-chart-grouped-line me-1"></i> 4. Confidence & Auto-Reply</span>
                            <span class="status-badge status-none" id="scoreBadge">0.0%</span>
                        </div>
                        <div id="scoreResults">
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span>Keyword Score:</span>
                                    <strong id="kwScoreText">0%</strong>
                                </div>
                                <div class="score-progress"><div class="score-bar" id="kwBar" style="width: 0%; background: #3b82f6;"></div></div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span>Semantic Score:</span>
                                    <strong id="semScoreText">0%</strong>
                                </div>
                                <div class="score-progress"><div class="score-bar" id="semBar" style="width: 0%; background: #8b5cf6;"></div></div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between small">
                                    <span>Final Confidence Score:</span>
                                    <strong id="finalScoreText" class="text-success">0%</strong>
                                </div>
                                <div class="score-progress"><div class="score-bar" id="finalBar" style="width: 0%; background: #10b981;"></div></div>
                                <div class="small text-muted mt-1 text-end">Threshold: 40.0%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
<script>
    function setQuery(text) {
        document.getElementById('userInput').value = text;
        document.getElementById('userInput').focus();
    }

    async function handleSend(e) {
        e.preventDefault();

        const input = document.getElementById('userInput');
        const sendBtn = document.getElementById('sendBtn');
        const text = input.value.trim();

        if (!text) return;

        // Append user message
        appendMessage(text, 'user');
        input.value = '';
        sendBtn.disabled = true;

        // Show typing indicator
        const typingId = appendTypingIndicator();

        try {
            const response = await fetch("{{ route('simulator.send') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({ message: text })
            });

            const data = await response.json();

            removeElement(typingId);
            sendBtn.disabled = false;

            if (data.success) {
                // Append bot reply
                appendMessage(data.reply, 'bot', data);
                // Update diagnostic panel
                updateDiagnostics(data);
            } else {
                appendMessage("❌ Error: " + (data.message || "Failed to process message"), 'bot');
            }
        } catch (err) {
            removeElement(typingId);
            sendBtn.disabled = false;
            appendMessage("❌ Request failed. Check server logs or connectivity.", 'bot');
            console.error(err);
        }
    }

    function appendMessage(content, sender, data = null) {
        const container = document.getElementById('chatMessages');
        const div = document.createElement('div');
        div.className = `message-bubble message-${sender}`;

        let metaHtml = '';
        if (sender === 'bot' && data) {
            const isAnswered = data.answered;
            const badgeClass = isAnswered ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning';
            const badgeText = isAnswered ? `Match (${data.confidence}%)` : `Fallback (${data.confidence}%)`;

            metaHtml = `
                <div class="message-meta">
                    <span class="badge ${badgeClass}">${badgeText}</span>
                    <span>${data.pipeline_diagnostics?.total_time_ms || 0} ms</span>
                    ${isAnswered && data.matched_faq ? `<span>FAQ: #${data.matched_faq.id.substring(0, 8)}...</span>` : ''}
                </div>
            `;
        }

        div.innerHTML = `<div>${escapeHtml(content)}</div>${metaHtml}`;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function appendTypingIndicator() {
        const container = document.getElementById('chatMessages');
        const div = document.createElement('div');
        const id = 'typing_' + Date.now();
        div.id = id;
        div.className = 'message-bubble message-bot text-muted small';
        div.innerHTML = `<i class="ri-loader-4-line ri-spin me-1"></i> Running pipeline through Python & Typesense...`;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
        return id;
    }

    function removeElement(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function updateDiagnostics(data) {
        const diag = data.pipeline_diagnostics;
        if (!diag) return;

        // Total time
        document.getElementById('totalTimeBadge').textContent = `${diag.total_time_ms} ms`;
        document.getElementById('totalTimeBadge').className = 'status-badge status-ok';

        // 1. CRM
        const crm = diag.crm_extracted;
        const crmBadge = document.getElementById('crmBadge');
        const crmResults = document.getElementById('crmResults');

        if (crm.has_data) {
            crmBadge.textContent = crm.db_saved ? `Extracted & Saved (Contact #${crm.contact_id})` : "Extracted";
            crmBadge.className = "status-badge status-ok";
            let tags = "";
            (crm.emails || []).forEach(e => tags += `<span class="entity-tag">✉ ${escapeHtml(e)}</span>`);
            (crm.phones || []).forEach(p => tags += `<span class="entity-tag">📞 ${escapeHtml(p)}</span>`);
            (crm.websites || []).forEach(w => tags += `<span class="entity-tag">🌐 ${escapeHtml(w)}</span>`);
            if (crm.nid) tags += `<span class="entity-tag">🪪 NID: ${escapeHtml(crm.nid)}</span>`;
            crmResults.innerHTML = tags;
        } else {
            crmBadge.textContent = "No Contact Data";
            crmBadge.className = "status-badge status-none";
            crmResults.innerHTML = `<span class="text-muted small">No emails or phone numbers found in input.</span>`;
        }

        // 2. Python
        const py = diag.python_service;
        const pyBadge = document.getElementById('pyBadge');
        if (py.status === 'ok') {
            pyBadge.textContent = `OK (${py.latency_ms} ms)`;
            pyBadge.className = 'status-badge status-ok';
            document.getElementById('pyModel').textContent = py.model;
            document.getElementById('pyDims').textContent = py.dimensions;
            document.getElementById('pyVectorSample').textContent = `Vector Sample: [${(py.vector_sample || []).join(', ')}...]`;
        } else {
            pyBadge.textContent = 'FAILED';
            pyBadge.className = 'status-badge status-failed';
            document.getElementById('pyVectorSample').textContent = `Error: ${py.error || 'Connection error'}`;
        }

        // 3. Typesense
        const ts = diag.typesense;
        const tsBadge = document.getElementById('tsBadge');
        if (ts.status === 'ok') {
            tsBadge.textContent = `OK (${ts.latency_ms} ms)`;
            tsBadge.className = 'status-badge status-ok';
        } else {
            tsBadge.textContent = ts.status.toUpperCase();
            tsBadge.className = 'status-badge status-failed';
        }

        document.getElementById('tsMatchType').textContent = (data.match_type || 'none').toUpperCase();
        document.getElementById('tsFaqId').textContent = data.matched_faq ? `#${data.matched_faq.id.substring(0, 8)}` : 'None';

        // 4. Scores
        const scores = diag.scores;
        const scoreBadge = document.getElementById('scoreBadge');
        scoreBadge.textContent = `${scores.final_confidence}%`;
        scoreBadge.className = data.answered ? 'status-badge status-ok' : 'status-badge status-degraded';

        document.getElementById('kwScoreText').textContent = `${scores.keyword_score}%`;
        document.getElementById('kwBar').style.width = `${scores.keyword_score}%`;

        document.getElementById('semScoreText').textContent = `${scores.semantic_score}%`;
        document.getElementById('semBar').style.width = `${scores.semantic_score}%`;

        document.getElementById('finalScoreText').textContent = `${scores.final_confidence}%`;
        document.getElementById('finalBar').style.width = `${scores.final_confidence}%`;
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>
@endpush
