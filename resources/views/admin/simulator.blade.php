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
            height: calc(100% - 60px);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
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
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        /* ── Beautiful Formatted Message Typography & Markdown ── */
        .msg-content {
            font-size: 14px;
            line-height: 1.65;
            word-break: break-word;
        }

        .msg-content strong {
            font-weight: 650;
            color: inherit;
        }

        .message-bot .msg-content strong {
            color: #0f172a;
        }

        .msg-heading {
            font-weight: 700;
            font-size: 14.5px;
            margin-top: 10px;
            margin-bottom: 6px;
            color: #0f172a;
            letter-spacing: -0.2px;
        }

        .message-user .msg-heading {
            color: #ffffff;
        }

        .msg-paragraph {
            margin-bottom: 8px;
        }

        .msg-paragraph:last-child {
            margin-bottom: 0;
        }

        .msg-list-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 4px;
            padding-left: 2px;
            line-height: 1.55;
        }

        .msg-list-item .bullet {
            margin-right: 8px;
            color: #3b82f6;
            font-weight: bold;
            font-size: 15px;
            line-height: 1.3;
            user-select: none;
        }

        .message-user .msg-list-item .bullet {
            color: #dbeafe;
        }

        .msg-list-item .num {
            margin-right: 6px;
            font-weight: 650;
            color: #2563eb;
            font-size: 13.5px;
            min-width: 20px;
        }

        .message-user .msg-list-item .num {
            color: #bfdbfe;
        }

        .msg-code {
            background-color: #f1f5f9;
            color: #0f172a;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12.5px;
            border: 1px solid #e2e8f0;
        }

        .msg-code-block {
            background-color: #0f172a;
            color: #f8fafc;
            padding: 10px 14px;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12.5px;
            overflow-x: auto;
            margin: 8px 0;
            line-height: 1.45;
            border: 1px solid #1e293b;
        }

        .msg-code-block code {
            color: #f8fafc;
            background: transparent;
            padding: 0;
            border: 0;
            font-family: inherit;
        }

        .msg-list-item .list-text {
            flex: 1;
        }

        .message-user .msg-code {
            background-color: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .msg-divider {
            border: 0;
            height: 1px;
            background: #e2e8f0;
            margin: 10px 0;
        }

        .message-user .msg-divider {
            background: rgba(255, 255, 255, 0.25);
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
            height: calc(100% - 60px);
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
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

        .status-ok {
            background: #dcfce7;
            color: #166534;
        }

        .status-degraded {
            background: #fef9c3;
            color: #854d0e;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-none {
            background: #f1f5f9;
            color: #64748b;
        }

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

        /* ── Route Badges & Cards ── */
        .route-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .route-pill.chat {
            background: #ede9fe;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
        }

        .route-pill.knowledge {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .route-pill.ood {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .route-pill.uncertain {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .route-pill.action {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .suggestions-container {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .suggestion-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 2px;
        }

        .suggestion-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            background: #ffffff;
            border: 1.5px solid #d97706;
            color: #92400e;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            text-align: left;
            box-shadow: 0 1px 3px rgba(217, 119, 6, 0.08);
        }

        .suggestion-chip:hover {
            background: #fef3c7;
            color: #78350f;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(217, 119, 6, 0.15);
        }

        .sources-container {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .source-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 6px;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .handoff-alert-card {
            margin-top: 8px;
            padding: 10px 14px;
            border-radius: 8px;
            background: #f0f9ff;
            border: 1.5px solid #7dd3fc;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .handoff-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #0284c7;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
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
                    Test CRM Entity Extraction, Python Embeddings, and Typesense Hybrid Search locally without Facebook
                    tokens.
                </p>
            </div>
            <div>
                <a href="{{ route('health') }}" target="_blank" class="btn btn-outline-info btn-sm me-2">
                    <i class="ri-heart-pulse-line me-1"></i> Health Status
                </a>
                <a href="#" class="btn btn-outline-secondary btn-sm" onclick="clearSimulatorChat(); return false;">
                    <i class="ri-refresh-line me-1"></i> Clear Chat
                </a>
            </div>
        </div>

        <!-- Simulator Main Layout -->
        <div class="row simulator-container">
            <!-- Left: Interactive Chat Window -->
            <div class="col-lg-7 col-md-12 mb-3 mb-lg-0 h-100">
                <div class="chat-card">

                    <!-- Messages area -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="message-bubble message-bot">
                            👋 Hi! I am your AI Chatbot simulator. Type any question or message below.
                            <br><br>
                            <em>Tip: Try providing your email or phone number in your question to test the CRM contact
                                extractor in real-time!</em>
                            <div class="message-meta text-muted">
                                <span>System Bot</span>
                            </div>
                        </div>
                    </div>

                    <!-- Input area -->
                    <div class="chat-input-area">
                        <form id="chatForm" onsubmit="handleSend(event); return false;" class="d-flex gap-2">
                            <input type="text" id="userInput" class="form-control form-control-lg fs-6"
                                placeholder="Type your message here..." autocomplete="off" required>
                            <button type="button" onclick="handleSend(event)" id="sendBtn"
                                class="btn btn-primary px-4 d-flex align-items-center gap-1">
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
                        <!-- Step 0: Hybrid Router Capability Decision -->
                        <div class="diag-section" style="background: #faf5ff; border-color: #e9d5ff;">
                            <div class="diag-title" style="color: #7e22ce;">
                                <span><i class="ri-compass-3-line me-1"></i> 0. Hybrid Router Capability</span>
                                <span class="route-pill chat" id="routerBadge">Idle</span>
                            </div>
                            <div id="routerResults">
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Route: <strong id="routerRoute" class="text-dark">N/A</strong></span>
                                    <span>Intent: <strong id="routerIntent" class="text-dark">None</strong></span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mt-1">
                                    <span>Router Latency: <strong id="routerLatency">0 ms</strong></span>
                                    <span>Confidence: <strong id="routerConfidence">0%</strong></span>
                                </div>
                            </div>
                        </div>

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
                                <div class="small text-muted mt-1" id="pyVectorSample">Vector: [ Waiting for request... ]
                                </div>
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
                                    <div class="score-progress">
                                        <div class="score-bar" id="kwBar" style="width: 0%; background: #3b82f6;"></div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small">
                                        <span>Semantic Score:</span>
                                        <strong id="semScoreText">0%</strong>
                                    </div>
                                    <div class="score-progress">
                                        <div class="score-bar" id="semBar" style="width: 0%; background: #8b5cf6;"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between small">
                                        <span>Final Confidence Score:</span>
                                        <strong id="finalScoreText" class="text-success">0%</strong>
                                    </div>
                                    <div class="score-progress">
                                        <div class="score-bar" id="finalBar" style="width: 0%; background: #10b981;"></div>
                                    </div>
                                    <div class="small text-muted mt-1 text-end">Threshold: 40.0%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 5: Semantic Answerability & Safety Gate (Tier 4) -->
                        <div class="diag-section" style="background: #f0fdf4; border-color: #bbf7d0;">
                            <div class="diag-title" style="color: #166534;">
                                <span><i class="ri-shield-check-line me-1"></i> 5. Semantic Answerability Gate</span>
                                <span class="status-badge status-none" id="gateBadge">IDLE</span>
                            </div>
                            <div id="gateResults">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Gate Decision: <strong id="gateStatusText" class="text-dark">N/A</strong></span>
                                    <span>Confidence: <strong id="gateConfidenceText">0.0%</strong></span>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Barrier Rule: <strong id="gateRuleText" class="text-dark">None</strong></span>
                                    <span>Score Margin: <strong id="gateMarginText">0.00</strong></span>
                                </div>
                                <div class="small text-muted mt-2">
                                    <span>Grounded Hits Authorized: <strong id="gateGroundedCount" class="text-success">0</strong></span>
                                    <div id="gateGroundedDocs" class="mt-1"></div>
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

        function setQueryAndSend(text) {
            document.getElementById('userInput').value = text;
            const fakeEvent = { preventDefault: () => { } };
            handleSend(fakeEvent);
        }

        async function handleSend(e) {
            if (e && e.preventDefault) e.preventDefault();

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

                // ── RAW LLM RESPONSE & COMPLETE PAYLOAD DEBUG LOG ──
                console.group("%c🤖 [Conversational AI] Raw Response & Pipeline Telemetry", "background: #2563eb; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: bold;");
                console.log("%cQuery:", "font-weight: bold; color: #0284c7;", text);
                console.log("%cRoute:", "font-weight: bold; color: #16a34a;", data.route);
                console.log("%cRaw LLM Response Payload:", "font-weight: bold; color: #7c3aed;", data.raw_llm_response);
                console.log("%cGenerated Reply Text:", "font-weight: bold; color: #059669;", data.reply);
                console.log("%cMatched FAQ:", "font-weight: bold; color: #d97706;", data.matched_faq);
                console.log("%cConfidence Score:", "font-weight: bold; color: #ea580c;", `${data.confidence}%`);
                console.log("%cComplete JSON Payload:", "font-weight: bold; color: #475569;", data);
                console.groupEnd();

                removeElement(typingId);
                sendBtn.disabled = false;

                if (data.success) {
                    // Append bot reply with route-aware cards
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

            let headerBadgeHtml = '';
            let extraCardsHtml = '';
            let metaHtml = '';

            if (sender === 'bot' && data) {
                const route = (data.route || 'knowledge').toLowerCase();

                // 1. Route badge in header
                if (route === 'chat') {
                    headerBadgeHtml = `<div class="mb-2"><span class="route-pill chat"><i class="ri-chat-smile-2-line"></i> Conversational</span></div>`;
                } else if (route === 'knowledge') {
                    headerBadgeHtml = `<div class="mb-2"><span class="route-pill knowledge"><i class="ri-book-open-line"></i> Grounded KB Answer</span></div>`;
                } else if (route === 'ood') {
                    headerBadgeHtml = `<div class="mb-2"><span class="route-pill ood"><i class="ri-shield-cross-line"></i> Out-of-Domain Scope</span></div>`;
                } else if (route === 'uncertain') {
                    headerBadgeHtml = `<div class="mb-2"><span class="route-pill uncertain"><i class="ri-question-line"></i> Clarification Needed</span></div>`;
                } else if (route === 'action' || data.is_handoff) {
                    headerBadgeHtml = `<div class="mb-2"><span class="route-pill action"><i class="ri-user-shared-line"></i> Support Specialist Transfer</span></div>`;
                }

                // 2. UNCERTAIN Interactive Clickable Suggestions
                if (route === 'uncertain' && Array.isArray(data.suggestions) && data.suggestions.length > 0) {
                    const chipsHtml = data.suggestions.map(s => `
                                        <button type="button" class="suggestion-chip" onclick="setQueryAndSend('${escapeJs(s)}')">
                                            <i class="ri-arrow-right-s-line text-warning"></i> ${escapeHtml(s)}
                                        </button>
                                    `).join('');

                    extraCardsHtml += `
                                        <div class="suggestions-container">
                                            <span class="suggestion-label"><i class="ri-lightbulb-line text-warning me-1"></i> Did you mean (Click to select):</span>
                                            ${chipsHtml}
                                        </div>
                                    `;
                }

                // 3. KNOWLEDGE Grounded Citations & Sources
                if (route === 'knowledge' && Array.isArray(data.sources) && data.sources.length > 0) {
                    const sourceChips = data.sources.map(src => `
                                        <span class="source-chip" title="Score: ${src.score}%">
                                            <i class="ri-checkbox-circle-fill text-success"></i> ${escapeHtml(src.question)}
                                        </span>
                                    `).join('');

                    extraCardsHtml += `
                                        <div class="sources-container">
                                            <span class="text-muted small fw-bold"><i class="ri-shield-check-line text-success me-1"></i> Grounded from FAQ:</span>
                                            ${sourceChips}
                                        </div>
                                    `;
                }

                // 4. ACTION / 3x UNCERTAIN Safe Human Handoff Notice Card
                if (data.is_handoff || route === 'action') {
                    extraCardsHtml += `
                                        <div class="handoff-alert-card">
                                            <div class="handoff-icon"><i class="ri-customer-service-2-line"></i></div>
                                            <div>
                                                <strong class="d-block text-dark small" style="font-size:12px;">Human Support Request Registered</strong>
                                                <small class="text-muted">A customer support specialist will review your request shortly.</small>
                                            </div>
                                        </div>
                                    `;
                }

                // Footer metadata
                metaHtml = `
                    <div class="message-meta">
                        <span>${data.pipeline_diagnostics?.total_time_ms || 0} ms</span>
                        <span>Route: <strong>${route.toUpperCase()}</strong></span>
                    </div>
                `;
            }

            const formattedBodyHtml = renderFormattedMessage(content);
            div.innerHTML = `${headerBadgeHtml}${formattedBodyHtml}${extraCardsHtml}${metaHtml}`;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }

        /**
         * Safely and losslessly format Markdown, Bold, Code, Lists, and Paragraphs
         * GUARANTEE: Never drops, skips, or truncates any word, line, or content from LLM response.
         */
        function renderFormattedMessage(rawContent) {
            if (rawContent === null || rawContent === undefined) return '';

            // 1. Escape basic HTML entities first to prevent XSS without losing characters
            let text = escapeHtml(String(rawContent));

            // 2. Fenced code blocks (```code```)
            text = text.replace(/```([\s\S]*?)```/g, (match, p1) => {
                return `<pre class="msg-code-block"><code>${p1.trim()}</code></pre>`;
            });

            // 3. Headings (###, ##, #)
            text = text.replace(/^###\s+(.*?)$/gm, '<div class="msg-heading">$1</div>');
            text = text.replace(/^##\s+(.*?)$/gm, '<div class="msg-heading">$1</div>');
            text = text.replace(/^#\s+(.*?)$/gm, '<div class="msg-heading">$1</div>');

            // 4. Horizontal rule / divider (--- or ***)
            text = text.replace(/^---+$|^\*\*\*+$/gm, '<hr class="msg-divider">');

            // 5. Bold text (**bold** or __bold__)
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/__(.*?)__/g, '<strong>$1</strong>');

            // 6. Inline code (`code`)
            text = text.replace(/`([^`\n]+)`/g, '<code class="msg-code">$1</code>');

            // 7. Numbered list items (e.g. 1. Step or 1) Step)
            text = text.replace(/^(\d+)[\.\)]\s+(.*?)$/gm, '<div class="msg-list-item"><span class="num">$1.</span><span class="list-text">$2</span></div>');

            // 8. Bullet list items (e.g. - item, * item, • item)
            text = text.replace(/^[\-\*•]\s+(.*?)$/gm, '<div class="msg-list-item"><span class="bullet">•</span><span class="list-text">$1</span></div>');

            // 9. Paragraphs & Line Breaks (Preserve all content and text)
            const paragraphs = text.split(/\n\n+/);
            const formattedParagraphs = paragraphs.map(p => {
                const trimmed = p.trim();
                if (!trimmed) return '';
                if (trimmed.includes('<div class="msg-list-item"') ||
                    trimmed.includes('<div class="msg-heading"') ||
                    trimmed.includes('<hr class="msg-divider"') ||
                    trimmed.includes('<pre class="msg-code-block"')) {
                    return trimmed.replace(/\n(?=<\/?(div|hr|pre|strong|span|code))/g, '').replace(/\n/g, '<br>');
                }
                return `<div class="msg-paragraph">${trimmed.replace(/\n/g, '<br>')}</div>`;
            });

            return `<div class="msg-content">${formattedParagraphs.join('')}</div>`;
        }

        function appendTypingIndicator() {
            const container = document.getElementById('chatMessages');
            const div = document.createElement('div');
            const id = 'typing_' + Date.now();
            div.id = id;
            div.className = 'message-bubble message-bot text-muted small';
            div.innerHTML = `<i class="ri-loader-4-line ri-spin me-1"></i> Processing query through HybridRouter & AI Agent...`;
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

            // 0. Hybrid Router
            const telemetry = diag.routing_telemetry || {};
            const route = (data.route || 'knowledge').toLowerCase();
            const routerBadge = document.getElementById('routerBadge');
            routerBadge.textContent = route.toUpperCase();
            routerBadge.className = `route-pill ${route}`;
            document.getElementById('routerRoute').textContent = route.toUpperCase();
            document.getElementById('routerIntent').textContent = telemetry.intent || 'direct_match';
            document.getElementById('routerLatency').textContent = `${telemetry.router_latency_ms || 0} ms`;
            document.getElementById('routerConfidence').textContent = `${Math.round((telemetry.confidence || 1.0) * 100)}%`;

            // 1. CRM
            const crm = diag.crm_extracted;
            const crmBadge = document.getElementById('crmBadge');
            const crmResults = document.getElementById('crmResults');

            if (crm && crm.has_data) {
                crmBadge.textContent = crm.db_saved ? `Saved (#${crm.contact_id})` : "Extracted";
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
            if (py && py.status === 'ok') {
                pyBadge.textContent = `OK (${py.latency_ms} ms)`;
                pyBadge.className = 'status-badge status-ok';
                document.getElementById('pyModel').textContent = py.model;
                document.getElementById('pyDims').textContent = py.dimensions;
                document.getElementById('pyVectorSample').textContent = `Vector Sample: [${(py.vector_sample || []).join(', ')}...]`;
            } else {
                pyBadge.textContent = 'BYPASSED / IDLE';
                pyBadge.className = 'status-badge status-none';
                document.getElementById('pyVectorSample').textContent = (route === 'chat' || route === 'ood') ? '0 Embedding calls (Bypassed by HybridRouter)' : 'Idle';
            }

            // 3. Typesense
            const ts = diag.typesense;
            const tsBadge = document.getElementById('tsBadge');
            if (ts && ts.status === 'ok') {
                tsBadge.textContent = `OK (${ts.latency_ms} ms)`;
                tsBadge.className = 'status-badge status-ok';
            } else {
                tsBadge.textContent = (route === 'chat' || route === 'ood') ? 'BYPASSED' : (ts?.status || 'IDLE').toUpperCase();
                tsBadge.className = (route === 'chat' || route === 'ood') ? 'status-badge status-none' : 'status-badge status-failed';
            }

            document.getElementById('tsMatchType').textContent = (data.match_type || 'none').toUpperCase();
            document.getElementById('tsFaqId').textContent = data.matched_faq ? `#${data.matched_faq.id.substring(0, 8)}` : 'None';

            // 4. Scores
            const scores = diag.scores;
            const scoreBadge = document.getElementById('scoreBadge');
            if (scores) {
                scoreBadge.textContent = `${scores.final_confidence}%`;
                scoreBadge.className = data.answered ? 'status-badge status-ok' : 'status-badge status-degraded';

                document.getElementById('kwScoreText').textContent = `${scores.keyword_score}%`;
                document.getElementById('kwBar').style.width = `${scores.keyword_score}%`;

                document.getElementById('semScoreText').textContent = `${scores.semantic_score}%`;
                document.getElementById('semBar').style.width = `${scores.semantic_score}%`;

                document.getElementById('finalScoreText').textContent = `${scores.final_confidence}%`;
                document.getElementById('finalBar').style.width = `${scores.final_confidence}%`;
            }

            // 5. Semantic Answerability Gate
            const gate = diag.answerability_decision;
            const gateBadge = document.getElementById('gateBadge');
            const gateStatusText = document.getElementById('gateStatusText');
            const gateConfidenceText = document.getElementById('gateConfidenceText');
            const gateRuleText = document.getElementById('gateRuleText');
            const gateMarginText = document.getElementById('gateMarginText');
            const gateGroundedCount = document.getElementById('gateGroundedCount');
            const gateGroundedDocs = document.getElementById('gateGroundedDocs');

            if (gate) {
                const status = (gate.status || 'unanswerable').toUpperCase();
                gateBadge.textContent = status;
                if (status === 'CONFIDENT') {
                    gateBadge.className = 'status-badge status-ok';
                    gateStatusText.className = 'text-success fw-bold';
                } else if (status === 'AMBIGUOUS') {
                    gateBadge.className = 'status-badge status-degraded';
                    gateStatusText.className = 'text-warning fw-bold';
                } else {
                    gateBadge.className = 'status-badge status-failed';
                    gateStatusText.className = 'text-danger fw-bold';
                }
                gateStatusText.textContent = status;
                gateConfidenceText.textContent = `${Math.round((gate.confidence_score || 0) * 100)}%`;

                const reasons = gate.reasons || {};
                gateRuleText.textContent = reasons.rule || (status === 'CONFIDENT' ? 'evidence_sufficient' : 'none');
                const margin = reasons.margin !== undefined ? reasons.margin.toFixed(4) : 'N/A';
                gateMarginText.textContent = margin;

                gateGroundedCount.textContent = gate.grounded_count || 0;

                // Render document badges
                let docHtml = '';
                const sources = data.sources || [];
                if (sources.length > 0) {
                    sources.forEach(s => {
                        docHtml += `<div class="d-flex justify-content-between align-items-center p-1 px-2 mb-1" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 11px;">
                            <span class="text-truncate" style="max-width: 220px;"><strong>[${escapeHtml(s.category)}]</strong> ${escapeHtml(s.question)}</span>
                            <span class="badge bg-success-subtle text-success">${s.score}%</span>
                        </div>`;
                    });
                } else {
                    docHtml = '<span class="text-muted small">Zero ungrounded documents passed to LLM (Safe fallback).</span>';
                }
                gateGroundedDocs.innerHTML = docHtml;
            } else {
                gateBadge.textContent = (route === 'chat' || route === 'action') ? 'BYPASSED' : 'IDLE';
                gateBadge.className = 'status-badge status-none';
                gateStatusText.textContent = (route === 'chat' || route === 'action') ? 'Bypassed (Chat/Action)' : 'Idle';
                gateConfidenceText.textContent = 'N/A';
                gateRuleText.textContent = 'None';
                gateMarginText.textContent = 'N/A';
                gateGroundedCount.textContent = '0';
                gateGroundedDocs.innerHTML = '<span class="text-muted small">No knowledge documents retrieved for this turn.</span>';
            }
        }

        async function clearSimulatorChat() {
            try {
                await fetch("{{ route('simulator.clear') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    }
                });
            } catch (e) {
                console.error("Failed to clear chat session:", e);
            }
            window.location.reload();
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function escapeJs(str) {
            return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
        }
    </script>
@endpush