@extends('admin.app')

@section('title')
    Chat Simulator & Infrastructure Pipeline Tester
@endsection

@push('custom-style')
    <style>
        .simulator-container {
            height: calc(100vh - 100px);
        }

        .chat-card {
            display: flex;
            flex-direction: column;
            height: 100%;
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
            height: 100%;
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

        .route-pill.analytics {
            background: #ede9fe;
            color: #7c3aed;
            border: 1px solid #c4b5fd;
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
    <div class="container-fluid">
        <!-- Simulator Main Layout -->
        <div class="row simulator-container">
            <!-- Left: Interactive Chat Window -->
            <div class="col-lg-7 col-md-12 mb-3 mb-lg-0 h-100">
                <div class="chat-card">
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div>
                            <h5 class="mb-0 fw-bold d-flex align-items-center gap-2" style="color: #0f172a;">
                                <i class="ri-robot-2-line text-primary"></i> AI Assistant
                            </h5>
                            <small class="text-muted" style="font-size: 11px;">Interactive test environment</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button"
                                class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 shadow-sm px-3"
                                onclick="clearSimulatorChat()" style="font-size: 13px; border-radius: 6px;">
                                <i class="ri-delete-bin-line"></i> Clear
                            </button>
                            <button type="button" class="btn btn-sm btn-dark d-flex align-items-center gap-1 shadow-sm px-3"
                                data-bs-toggle="modal" data-bs-target="#usageWizardModal"
                                style="font-size: 13px; border-radius: 6px;">
                                <i class="ri-money-dollar-circle-line text-warning"></i> Usage & Cost
                            </button>
                        </div>
                    </div>

                    <!-- Messages area -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="message-bubble message-bot">
                            👋 Hi! I am your AI Chatbot simulator. Type any question or message below.
                        </div>
                        @if(isset($messages) && $messages->isNotEmpty())
                            @foreach($messages as $msg)
                                <div class="message-bubble {{ $msg->direction === 'inbound' ? 'message-user' : 'message-bot' }}">
                                    {!! nl2br(e($msg->body)) !!}
                                </div>
                            @endforeach
                        @endif
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
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-badge status-none" id="totalTimeBadge">0 ms</span>
                        </div>
                    </div>

                    <div class="diag-body" id="diagBody">
                        <!-- Turn Decision Trace (Real-Time Multi-Turn Inspector) -->
                        <div class="diag-section mb-3" id="turnDecisionTraceCard"
                            style="background: #ffffff; border: 2px solid #3b82f6; border-radius: 10px; padding: 16px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.08);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="fw-bold mb-0 text-primary d-flex align-items-center" style="font-size: 14px;">
                                    <i class="ri-git-commit-line me-1"></i> Turn Decision Trace
                                </h6>
                                <span class="badge bg-primary" id="traceTurnBadge">Ready</span>
                            </div>

                            {{-- Turn History Navigation Pills --}}
                            <div id="turnHistoryNav" class="d-flex align-items-center gap-1 mb-2 overflow-auto py-1"
                                style="max-width: 100%;">
                                <span class="text-muted small" style="font-size: 11px;">Send a message to view live turn
                                    trace</span>
                            </div>

                            {{-- Query Box --}}
                            <div class="p-2 mb-3 rounded"
                                style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 13px;">
                                <span class="text-muted small fw-bold d-block text-uppercase" style="font-size: 10px;">User
                                    Query</span>
                                <strong id="traceUserQuery" class="text-dark">"Waiting for user message..."</strong>
                            </div>

                            {{-- Decision Key Metrics Matrix --}}
                            <div class="row g-2 mb-3" style="font-size: 12px;">
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="text-muted d-block small" style="font-size: 10px;">Route
                                            Decision</span>
                                        <span id="traceRouteBadge" class="route-pill chat mt-1">IDLE</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="text-muted d-block small" style="font-size: 10px;">Memory
                                            Decision</span>
                                        <span id="traceMemoryBadge" class="badge bg-secondary mt-1">IDLE</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="text-muted d-block small" style="font-size: 10px;">Contextual
                                            Signal</span>
                                        <strong id="traceContextSignal" class="text-dark mt-1 d-block"
                                            style="font-size: 11px;">NONE</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="text-muted d-block small" style="font-size: 10px;">Retrieval
                                            Summary</span>
                                        <strong id="traceRetrievalSummary" class="text-dark mt-1 d-block"
                                            style="font-size: 11px;">0 hits</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="text-muted d-block small" style="font-size: 10px;">Answerability
                                            Gate</span>
                                        <span id="traceGateBadge" class="badge bg-secondary mt-1">IDLE</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="text-muted d-block small" style="font-size: 10px;">Grounded Hit
                                            Count</span>
                                        <strong id="traceGroundedCount" class="text-success mt-1 d-block"
                                            style="font-size: 11px;">0 Docs</strong>
                                    </div>
                                </div>
                            </div>


                            {{-- LLM Generation & Latency Breakdown --}}
                            <div class="p-2 rounded"
                                style="background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12px;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted small">LLM Generation:</span>
                                    <strong id="traceLlmStatus" class="text-dark small">Ready</strong>
                                </div>
                                <hr class="my-1" style="border-color: #cbd5e1;">
                                <div class="row g-1 text-center small mt-1">
                                    <div class="col-3 border-end">
                                        <span class="text-muted d-block" style="font-size: 10px;">Router</span>
                                        <strong id="latRouter">0 ms</strong>
                                    </div>
                                    <div class="col-3 border-end">
                                        <span class="text-muted d-block" style="font-size: 10px;">Retrieval</span>
                                        <strong id="latRetrieval">0 ms</strong>
                                    </div>
                                    <div class="col-3 border-end">
                                        <span class="text-muted d-block" style="font-size: 10px;">LLM</span>
                                        <strong id="latLlm">0 ms</strong>
                                    </div>
                                    <div class="col-3">
                                        <span class="text-muted d-block" style="font-size: 10px;">Total E2E</span>
                                        <strong id="latTotal" class="text-primary">0 ms</strong>
                                    </div>
                                </div>

                                {{-- Granular Stage-Level Latency Tree --}}
                                <div class="mt-2 pt-2 border-top"
                                    style="border-color: #cbd5e1 !important; font-family: monospace; font-size: 11px;">
                                    <div class="d-flex justify-content-between py-0.5">
                                        <span>🧭 Router</span>
                                        <strong id="latDetailedRouter">0 ms</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-0.5">
                                        <span>🧠 Context Resolution</span>
                                        <strong id="latDetailedContext">0 ms</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-0.5">
                                        <span>💾 Memory Gate & Context</span>
                                        <strong id="latDetailedMemory">0 ms</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-0.5">
                                        <span>🔍 Knowledge Retrieval</span>
                                        <strong id="latDetailedRetrieval" class="text-primary">0 ms</strong>
                                    </div>
                                    <div id="latRetrievalSubTree"
                                        style="padding-left: 14px; color: #64748b; font-size: 10.5px;">

                                        <div class="d-flex justify-content-between">
                                            <span>├─ Embedding</span>
                                            <span id="latEmbedding">0 ms</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>├─ Typesense Search</span>
                                            <span id="latTypesense">0 ms</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>└─ Reranker</span>
                                            <span id="latReranker">0 ms</span>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between py-0.5">
                                        <span>⚖️ Answerability Gate</span>
                                        <strong id="latDetailedGate">0 ms</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-0.5">
                                        <span>⚡ Live LLM Generation</span>
                                        <strong id="latDetailedLlm" class="text-danger">0 ms</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-0.5 border-top mt-1 pt-1"
                                        style="border-color: #cbd5e1 !important;">
                                        <span class="fw-bold text-dark">⏱️ Total Pipeline (E2E)</span>
                                        <strong id="latDetailedTotal" class="text-success fw-bold">0 ms</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                    <span>Grounded Hits Authorized: <strong id="gateGroundedCount"
                                            class="text-success">0</strong></span>
                                    <div id="gateGroundedDocs" class="mt-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Usage & Cost Wizard Modal -->
        <div class="modal fade" id="usageWizardModal" tabindex="-1" aria-labelledby="usageWizardLabel" aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered modal-xl usage-wizard-dialog">
                <div class="modal-content usage-wizard-modal p-0 border-0">

                    <!-- HEADER -->
                    <div class="usage-wizard-header">
                        <div class="d-flex align-items-center gap-3">

                            <div class="usage-wizard-icon">
                                <i class="ri-bar-chart-box-line"></i>
                            </div>

                            <div>
                                <h5 class="mb-0 text-white fw-bold" id="usageWizardLabel">
                                    LLM Telemetry & Billing
                                </h5>

                                <div class="usage-wizard-subtitle">
                                    Track token usage, request cost and billing
                                </div>
                            </div>

                        </div>

                        <button type="button" class="usage-wizard-close" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>


                    <!-- BODY -->
                    <div class="usage-wizard-body">

                        <!-- TOP COST SUMMARY -->
                        <div class="usage-cost-card">

                            <div class="usage-cost-main">

                                <div class="usage-label">
                                    TOTAL SESSION COST
                                </div>

                                <div class="usage-total-cost">
                                    <span id="wizTotalCost">$0.000000</span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 ms-2 align-middle" id="wizTotalCostBdt" style="font-weight: 700; font-size: 16px !important;">৳0.0000</span>
                                </div>

                                <div class="usage-cost-meta">
                                    <span>
                                        <i class="ri-pulse-line"></i>
                                        <span id="wizTotalRequests">0</span> requests
                                    </span>

                                    <span class="usage-dot"></span>

                                    <span>
                                        <span id="wizAvgTokens">0</span> avg tokens/request
                                    </span>
                                </div>

                            </div>


                            <div class="usage-cost-breakdown">

                                <div class="usage-cost-row input">
                                    <div>
                                        <i class="ri-arrow-down-line"></i>
                                        <span>Input Cost</span>
                                    </div>

                                    <strong id="wizInputCost">
                                        $0.00000
                                    </strong>
                                </div>


                                <div class="usage-cost-row output">
                                    <div>
                                        <i class="ri-arrow-up-line"></i>
                                        <span>Output Cost</span>
                                    </div>

                                    <strong id="wizOutputCost">
                                        $0.00000
                                    </strong>
                                </div>

                            </div>

                        </div>


                        <!-- STATS -->
                        <div class="usage-stats-grid">

                            <div class="usage-stat-card">
                                <div class="usage-stat-icon blue">
                                    <i class="ri-message-3-line"></i>
                                </div>

                                <div>
                                    <div class="usage-stat-label">
                                        REQUESTS
                                    </div>

                                    <div class="usage-stat-value" id="wizTotalRequests_stat">
                                        0
                                    </div>
                                </div>
                            </div>


                            <div class="usage-stat-card">
                                <div class="usage-stat-icon amber">
                                    <i class="ri-speed-up-line"></i>
                                </div>

                                <div>
                                    <div class="usage-stat-label">
                                        TOKENS / QUERY
                                    </div>

                                    <div class="usage-stat-value" id="wizAvgTokens_stat">
                                        0
                                    </div>
                                </div>
                            </div>


                            <div class="usage-stat-card">
                                <div class="usage-stat-icon green">
                                    <i class="ri-arrow-down-line"></i>
                                </div>

                                <div>
                                    <div class="usage-stat-label">
                                        INPUT TOKENS
                                    </div>

                                    <div class="usage-stat-value" id="wizTotalInputTokens">
                                        0
                                    </div>
                                </div>
                            </div>


                            <div class="usage-stat-card">
                                <div class="usage-stat-icon purple">
                                    <i class="ri-arrow-up-line"></i>
                                </div>

                                <div>
                                    <div class="usage-stat-label">
                                        OUTPUT TOKENS
                                    </div>

                                    <div class="usage-stat-value" id="wizTotalOutputTokens">
                                        0
                                    </div>
                                </div>
                            </div>

                        </div>


                        <!-- MAIN GRID -->
                        <div class="usage-main-grid">

                            <!-- REQUEST BREAKDOWN (FULL WIDTH) -->
                            <div class="usage-panel usage-request-panel">

                                <div class="usage-panel-header">

                                    <div>
                                        <div class="usage-panel-title">
                                            <i class="ri-list-check-2"></i>
                                            Request Breakdown
                                        </div>

                                        <div class="usage-panel-description">
                                            Individual LLM usage and cost
                                        </div>
                                    </div>

                                    <div class="usage-average-badge">
                                        Avg.
                                        <strong id="wizAvgCost">$0.00</strong>
                                        / req
                                    </div>

                                </div>


                                <div class="usage-table-wrapper">

                                    <table class="usage-table">

                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>MODEL</th>
                                                <th class="text-end">
                                                    INPUT
                                                </th>
                                                <th class="text-end">
                                                    OUTPUT
                                                </th>
                                                <th class="text-end">
                                                    TOTAL
                                                </th>
                                                <th class="text-end">
                                                    COST
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody id="wizBreakdownTableBody">

                                            <tr>
                                                <td colspan="6">

                                                    <div class="usage-empty-state">

                                                        <div class="usage-empty-icon">
                                                            <i class="ri-inbox-line"></i>
                                                        </div>

                                                        <div class="usage-empty-title">
                                                            No requests logged yet
                                                        </div>

                                                        <div class="usage-empty-text">
                                                            LLM request usage will appear here.
                                                        </div>

                                                    </div>

                                                </td>
                                            </tr>

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>
            </div>
        </div>

        <style>
            /* =========================================================
                       LLM USAGE WIZARD
                       ========================================================= */

            .usage-wizard-dialog {
                max-width: 1120px;
                background: transparent;
            }

            .usage-wizard-modal {
                border: 0 !important;
                border-radius: 18px !important;
                overflow: hidden !important;
                background: #f8fafc !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow:
                    0 30px 80px rgba(15, 23, 42, 0.25),
                    0 10px 30px rgba(15, 23, 42, 0.12);
            }


            /* ---------------------------------------------------------
                       HEADER
                       --------------------------------------------------------- */

            .usage-wizard-header {
                min-height: 76px;
                padding: 16px 22px !important;
                margin: 0 !important;

                display: flex;
                align-items: center;
                justify-content: space-between;

                background:
                    linear-gradient(135deg,
                        #0f172a 0%,
                        #172554 100%);

                border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            }

            .usage-wizard-icon {
                width: 42px;
                height: 42px;

                display: flex;
                align-items: center;
                justify-content: center;

                border-radius: 10px;

                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.12);

                color: #38bdf8;
                font-size: 21px;
            }

            .usage-wizard-subtitle {
                margin-top: 3px;
                font-size: 11px;
                color: #94a3b8;
            }

            .usage-wizard-close {
                width: 34px;
                height: 34px;

                display: flex;
                align-items: center;
                justify-content: center;

                border: 0;
                border-radius: 8px;

                background: rgba(255, 255, 255, 0.06);

                color: #cbd5e1;
                font-size: 19px;

                transition: 0.2s ease;
            }

            .usage-wizard-close:hover {
                background: rgba(255, 255, 255, 0.12);
                color: #fff;
            }


            /* ---------------------------------------------------------
                       BODY
                       --------------------------------------------------------- */

            .usage-wizard-body {
                padding: 20px;
                background: #f8fafc;

                max-height: calc(100vh - 150px);
                overflow-y: auto;
            }


            /* ---------------------------------------------------------
                       COST SUMMARY
                       --------------------------------------------------------- */

            .usage-cost-card {
                display: grid;
                grid-template-columns: 1.2fr 1fr;

                background: #ffffff;

                border: 1px solid #e2e8f0;
                border-radius: 14px;

                overflow: hidden;

                box-shadow:
                    0 2px 6px rgba(15, 23, 42, 0.04);
            }

            .usage-cost-main {
                padding: 22px 24px;
            }

            .usage-label {
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 1.2px;

                color: #64748b;
            }

            .usage-total-cost {
                margin-top: 4px;

                font-size: 38px;
                line-height: 1.1;
                font-weight: 800;

                color: #2563eb;
            }

            .usage-cost-meta {
                margin-top: 9px;

                display: flex;
                align-items: center;
                gap: 8px;

                font-size: 11px;
                color: #64748b;
            }

            .usage-cost-meta i {
                color: #3b82f6;
            }

            .usage-dot {
                width: 3px;
                height: 3px;

                border-radius: 50%;
                background: #94a3b8;
            }


            /* COST BREAKDOWN */

            .usage-cost-breakdown {
                padding: 18px 20px;

                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 9px;

                background: #f8fafc;

                border-left: 1px solid #e2e8f0;
            }

            .usage-cost-row {
                display: flex;
                align-items: center;
                justify-content: space-between;

                padding: 10px 12px;

                border-radius: 9px;

                font-size: 12px;
            }

            .usage-cost-row>div {
                display: flex;
                align-items: center;
                gap: 7px;
            }

            .usage-cost-row.input {
                color: #15803d;
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
            }

            .usage-cost-row.output {
                color: #be123c;
                background: #fff1f2;
                border: 1px solid #fecdd3;
            }


            /* ---------------------------------------------------------
                       STATS
                       --------------------------------------------------------- */

            .usage-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);

                gap: 12px;

                margin-top: 14px;
            }

            .usage-stat-card {
                min-height: 82px;

                display: flex;
                align-items: center;
                gap: 12px;

                padding: 14px;

                background: #ffffff;

                border: 1px solid #e2e8f0;
                border-radius: 12px;

                box-shadow:
                    0 1px 3px rgba(15, 23, 42, 0.03);

                transition: 0.2s ease;
            }

            .usage-stat-card:hover {
                transform: translateY(-1px);

                border-color: #cbd5e1;

                box-shadow:
                    0 5px 15px rgba(15, 23, 42, 0.06);
            }

            .usage-stat-icon {
                width: 34px;
                height: 34px;

                flex: 0 0 34px;

                display: flex;
                align-items: center;
                justify-content: center;

                border-radius: 9px;

                font-size: 16px;
            }

            .usage-stat-icon.blue {
                color: #2563eb;
                background: #eff6ff;
            }

            .usage-stat-icon.amber {
                color: #d97706;
                background: #fffbeb;
            }

            .usage-stat-icon.green {
                color: #16a34a;
                background: #f0fdf4;
            }

            .usage-stat-icon.purple {
                color: #7c3aed;
                background: #f5f3ff;
            }

            .usage-stat-label {
                font-size: 9px;
                font-weight: 700;
                letter-spacing: .8px;
                color: #94a3b8;
            }

            .usage-stat-value {
                margin-top: 2px;

                font-size: 20px;
                line-height: 1.2;

                font-weight: 750;
                color: #0f172a;
            }


            /* ---------------------------------------------------------
                       MAIN GRID
                       --------------------------------------------------------- */

            .usage-main-grid {
                display: grid;
                grid-template-columns: 1fr;

                gap: 14px;

                margin-top: 14px;
            }

            .usage-panel {
                background: #ffffff;

                border: 1px solid #e2e8f0;
                border-radius: 12px;

                overflow: hidden;

                box-shadow:
                    0 1px 3px rgba(15, 23, 42, 0.03);
            }

            .usage-panel-header {
                min-height: 62px;

                display: flex;
                align-items: center;
                justify-content: space-between;

                padding: 12px 15px;

                border-bottom: 1px solid #e2e8f0;
            }

            .usage-panel-title {
                display: flex;
                align-items: center;
                gap: 7px;

                font-size: 11px;
                font-weight: 750;

                color: #334155;

                letter-spacing: .7px;
                text-transform: uppercase;
            }

            .usage-panel-title i {
                color: #64748b;
            }

            .usage-panel-description {
                margin-top: 3px;

                font-size: 10px;
                color: #94a3b8;
            }


            /* ---------------------------------------------------------
                       PRICING
                       --------------------------------------------------------- */

            .usage-panel-body {
                padding: 15px;
            }

            .usage-field {
                margin-bottom: 16px;
            }

            .usage-field:last-of-type {
                margin-bottom: 14px;
            }

            .usage-field-header {
                display: flex;
                justify-content: space-between;
                align-items: center;

                margin-bottom: 6px;
            }

            .usage-field-header label {
                margin: 0;

                font-size: 11px;
                font-weight: 600;

                color: #475569;
            }

            .usage-currency {
                padding: 3px 7px;

                border-radius: 5px;

                background: #f1f5f9;
                border: 1px solid #e2e8f0;

                font-size: 9px;
                font-weight: 700;

                color: #64748b;
            }

            .usage-input {
                height: 36px;

                display: flex;
                align-items: center;

                border: 1px solid #cbd5e1;
                border-radius: 8px;

                background: #ffffff;

                overflow: hidden;

                transition: .2s ease;
            }

            .usage-input:focus-within {
                border-color: #60a5fa;

                box-shadow:
                    0 0 0 3px rgba(59, 130, 246, .08);
            }

            .usage-input i {
                padding-left: 10px;

                color: #94a3b8;
            }

            .usage-input input {
                width: 100%;
                height: 100%;

                padding: 0 10px;

                border: 0;
                outline: 0;

                background: transparent;

                font-size: 12px;
                font-weight: 600;

                color: #334155;
            }

            .usage-pricing-note {
                display: flex;
                align-items: flex-start;
                gap: 7px;

                padding: 9px;

                border-radius: 8px;

                background: #f8fafc;
                border: 1px solid #e2e8f0;

                font-size: 9px;
                line-height: 1.5;

                color: #64748b;
            }

            .usage-pricing-note i {
                color: #3b82f6;
                font-size: 13px;
            }


            /* ---------------------------------------------------------
                       REQUEST BREAKDOWN
                       --------------------------------------------------------- */

            .usage-average-badge {
                padding: 5px 9px;

                border-radius: 20px;

                background: #fff1f2;
                border: 1px solid #fecdd3;

                color: #e11d48;

                font-size: 9px;
                white-space: nowrap;
            }

            .usage-average-badge strong {
                font-weight: 750;
            }

            .usage-table-wrapper {
                max-height: 240px;

                overflow: auto;
            }

            .usage-table {
                width: 100%;
                margin: 0;

                border-collapse: collapse;

                font-size: 11px;
            }

            .usage-table thead {
                position: sticky;
                top: 0;
                z-index: 2;

                background: #f8fafc;
            }

            .usage-table th {
                padding: 9px 10px;

                border-bottom: 1px solid #e2e8f0;

                color: #64748b;

                font-size: 9px;
                font-weight: 750;

                letter-spacing: .5px;
            }

            .usage-table td {
                padding: 9px 10px;

                border-bottom: 1px solid #f1f5f9;

                color: #475569;
            }

            .usage-table tbody tr:hover {
                background: #f8fafc;
            }


            /* ---------------------------------------------------------
                       EMPTY STATE
                       --------------------------------------------------------- */

            .usage-empty-state {
                min-height: 150px;

                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;

                color: #94a3b8;
            }

            .usage-empty-icon {
                width: 40px;
                height: 40px;

                display: flex;
                align-items: center;
                justify-content: center;

                margin-bottom: 8px;

                border-radius: 10px;

                background: #f1f5f9;

                color: #94a3b8;

                font-size: 18px;
            }

            .usage-empty-title {
                font-size: 11px;
                font-weight: 650;
                color: #64748b;
            }

            .usage-empty-text {
                margin-top: 3px;

                font-size: 10px;
                color: #94a3b8;
            }


            /* ---------------------------------------------------------
                       SCROLLBAR
                       --------------------------------------------------------- */

            .usage-wizard-body::-webkit-scrollbar,
            .usage-table-wrapper::-webkit-scrollbar {
                width: 5px;
                height: 5px;
            }

            .usage-wizard-body::-webkit-scrollbar-thumb,
            .usage-table-wrapper::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 10px;
            }

            .usage-wizard-body::-webkit-scrollbar-track,
            .usage-table-wrapper::-webkit-scrollbar-track {
                background: transparent;
            }


            /* ---------------------------------------------------------
                       RESPONSIVE
                       --------------------------------------------------------- */

            @media (max-width: 900px) {

                .usage-cost-card {
                    grid-template-columns: 1fr;
                }

                .usage-cost-breakdown {
                    border-left: 0;
                    border-top: 1px solid #e2e8f0;
                }

                .usage-stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }

                .usage-main-grid {
                    grid-template-columns: 1fr;
                }

            }

            @media (max-width: 576px) {

                .usage-wizard-body {
                    padding: 12px;
                }

                .usage-wizard-header {
                    padding: 14px;
                }

                .usage-total-cost {
                    font-size: 32px;
                }

                .usage-stats-grid {
                    grid-template-columns: 1fr;
                }

            }
        </style>

    </div>
@endsection

@push('custom-scripts')
    <script>
        // ── LLM Usage & Cost Wizard State ──
        let llmUsageHistory = @json($llmUsageHistory ?? []);

        function renderUsageWizard() {
            // Hardcoded Peak Hour Rates & Fixed 130 BDT/$ Exchange Rate
            const inputPricePerM = 0.30;
            const outputPricePerM = 1.20;
            const bdtRate = 130;

            let totalRequests = llmUsageHistory.length;
            let totalInputTokens = 0;
            let totalOutputTokens = 0;
            let totalCost = 0;
            let inputCost = 0;
            let outputCost = 0;

            const tbody = document.getElementById('wizBreakdownTableBody');
            tbody.innerHTML = '';

            if (totalRequests === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="ri-inbox-line fs-3 opacity-25 d-block mb-1"></i>No requests logged yet.</td></tr>';
            }

            llmUsageHistory.forEach((req, index) => {
                let reqInputTokens = req.prompt_tokens || 0;
                let reqOutputTokens = req.completion_tokens || 0;
                let reqTotalTokens = req.total_tokens || (reqInputTokens + reqOutputTokens);

                totalInputTokens += reqInputTokens;
                totalOutputTokens += reqOutputTokens;

                let reqInputCost = (reqInputTokens / 1000000) * inputPricePerM;
                let reqOutputCost = (reqOutputTokens / 1000000) * outputPricePerM;
                let reqCost = reqInputCost + reqOutputCost;
                let reqCostBdt = reqCost * bdtRate;

                inputCost += reqInputCost;
                outputCost += reqOutputCost;
                totalCost += reqCost;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                                <td class="ps-3">${index + 1}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary border">${req.model || 'deepseek-flash'}</span></td>
                                <td class="text-end text-dark fw-medium">${reqInputTokens.toLocaleString()}</td>
                                <td class="text-end text-primary fw-medium">${reqOutputTokens.toLocaleString()}</td>
                                <td class="text-end text-muted fw-medium">${reqTotalTokens.toLocaleString()}</td>
                                <td class="text-end pe-3">
                                    <span class="fw-bold text-success">$${reqCost.toFixed(6)}</span>
                                    <span class="badge bg-light text-dark border ms-1" style="font-size: 11px;">৳${reqCostBdt.toFixed(4)}</span>
                                </td>
                            `;
                tbody.appendChild(tr);
            });

            document.getElementById('wizTotalRequests').innerText = totalRequests.toLocaleString();
            if (document.getElementById('wizTotalRequests_stat')) document.getElementById('wizTotalRequests_stat').innerText = totalRequests.toLocaleString();
            document.getElementById('wizTotalInputTokens').innerText = totalInputTokens.toLocaleString();
            document.getElementById('wizTotalOutputTokens').innerText = totalOutputTokens.toLocaleString();

            let avgTokens = totalRequests > 0 ? Math.round((totalInputTokens + totalOutputTokens) / totalRequests) : 0;
            document.getElementById('wizAvgTokens').innerText = avgTokens.toLocaleString();
            if (document.getElementById('wizAvgTokens_stat')) document.getElementById('wizAvgTokens_stat').innerText = avgTokens.toLocaleString();

            let totalCostBdt = totalCost * bdtRate;
            document.getElementById('wizTotalCost').innerText = `$${totalCost.toFixed(6)}`;
            if (document.getElementById('wizTotalCostBdt')) {
                document.getElementById('wizTotalCostBdt').innerText = `৳${totalCostBdt.toFixed(4)}`;
            }

            document.getElementById('wizInputCost').innerText = `$${inputCost.toFixed(6)} (৳${(inputCost * bdtRate).toFixed(4)})`;
            document.getElementById('wizOutputCost').innerText = `$${outputCost.toFixed(6)} (৳${(outputCost * bdtRate).toFixed(4)})`;

            let avgCost = totalRequests > 0 ? (totalCost / totalRequests) : 0;
            let avgCostBdt = avgCost * bdtRate;
            document.getElementById('wizAvgCost').innerHTML = `$${avgCost.toFixed(6)} <span class="ms-1 opacity-75">(৳${avgCostBdt.toFixed(4)})</span>`;
        }

        function setQuery(text) {
            document.getElementById('userInput').value = text;
            document.getElementById('userInput').focus();
        }

        window.onload = function () {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Restore Usage History
            if (llmUsageHistory.length > 0) {
                renderUsageWizard();
            }
        };

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

                // ── Update LLM Usage Wizard (Only if actual paid LLM tokens used) ──
                if (data.decision_trace && data.decision_trace.llm_generation) {
                    const llmGen = data.decision_trace.llm_generation;
                    const tokens = (llmGen.prompt_tokens || 0) + (llmGen.completion_tokens || 0);
                    if (tokens > 0) {
                        llmUsageHistory.push(llmGen);
                        renderUsageWizard();
                    }
                }

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
                    // Update turn decision trace
                    recordTurnDecisionTrace(data);
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
                } else if (route === 'analytics') {
                    headerBadgeHtml = `<div class="mb-2"><span class="route-pill analytics"><i class="ri-bar-chart-box-line"></i> Business Analytics</span></div>`;
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

            // 8.5 Blockquotes (> Quote)
            text = text.replace(/^&gt;\s+(.*?)$/gm, '<blockquote class="border-start border-3 border-primary ps-2 text-muted small my-1" style="margin-left: 0;">$1</blockquote>');

            // 8.6. Markdown Tables (| Col 1 | Col 2 |)
            text = text.replace(/(?:^\|[^\n]+\|\r?\n(?:\|[^\n]+\|\r?\n?)+)/gm, (tableMatch) => {
                const lines = tableMatch.trim().split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
                if (lines.length < 2) return tableMatch;

                let tableHtml = '<div class="table-responsive my-2"><table class="table table-sm table-bordered table-striped mb-0" style="font-size: 12.5px; background: #ffffff;">';
                let isHeader = true;

                for (let i = 0; i < lines.length; i++) {
                    const line = lines[i];
                    if (/^\|[\s\-:|]+\|$/.test(line)) {
                        continue;
                    }

                    const cells = line.split('|').slice(1, -1).map(c => c.trim());

                    if (isHeader) {
                        tableHtml += '<thead class="table-light"><tr>';
                        cells.forEach(c => {
                            tableHtml += `<th class="py-1 px-2 text-nowrap fw-bold">${c}</th>`;
                        });
                        tableHtml += '</tr></thead><tbody>';
                        isHeader = false;
                    } else {
                        tableHtml += '<tr>';
                        cells.forEach(c => {
                            tableHtml += `<td class="py-1 px-2">${c}</td>`;
                        });
                        tableHtml += '</tr>';
                    }
                }

                tableHtml += '</tbody></table></div>';
                return tableHtml;
            });

            // 9. Paragraphs & Line Breaks (Preserve all content and text)
            const paragraphs = text.split(/\n\n+/);
            const formattedParagraphs = paragraphs.map(p => {
                const trimmed = p.trim();
                if (!trimmed) return '';
                if (trimmed.includes('<div class="msg-list-item"') ||
                    trimmed.includes('<div class="msg-heading"') ||
                    trimmed.includes('<hr class="msg-divider"') ||
                    trimmed.includes('<blockquote') ||
                    trimmed.includes('<div class="table-responsive') ||
                    trimmed.includes('<pre class="msg-code-block"')) {
                    return trimmed.replace(/\n(?=<\/?(div|hr|pre|strong|span|code|blockquote|table|thead|tbody|tr|th|td))/g, '').replace(/\n/g, '<br>');
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

        let turnHistory = [];
        let currentTurnIndex = -1;

        function recordTurnDecisionTrace(data) {
            const trace = data.decision_trace;
            if (!trace) return;

            turnHistory.push({
                turnNumber: turnHistory.length + 1,
                trace: trace,
                diagnostics: data.pipeline_diagnostics,
                raw: data,
            });

            currentTurnIndex = turnHistory.length - 1;
            renderTurnHistoryPills();
            renderDecisionTrace(trace, turnHistory.length, true);
        }

        function renderTurnHistoryPills() {
            const nav = document.getElementById('turnHistoryNav');
            if (!nav || turnHistory.length === 0) return;

            nav.innerHTML = turnHistory.map((t, idx) => {
                const isActive = (idx === currentTurnIndex);
                const btnClass = isActive ? 'btn-primary text-white shadow-sm' : 'btn-outline-secondary';
                return `<button type="button" class="btn btn-xs ${btnClass} py-0 px-2" style="font-size: 11px; border-radius: 12px; white-space: nowrap;" onclick="selectTurn(${idx})">
                                                                    Turn #${t.turnNumber}
                                                                </button>`;
            }).join('');
        }

        function selectTurn(index) {
            if (index >= 0 && index < turnHistory.length) {
                currentTurnIndex = index;
                renderTurnHistoryPills();
                const item = turnHistory[index];
                renderDecisionTrace(item.trace, item.turnNumber, index === turnHistory.length - 1);
                if (item.raw) {
                    updateDiagnostics(item.raw);
                }
            }
        }

        function renderDecisionTrace(trace, turnNum, isLatest = false) {
            const badge = document.getElementById('traceTurnBadge');
            badge.textContent = `Turn #${turnNum}${isLatest ? ' (Latest)' : ''}`;
            badge.className = isLatest ? 'badge bg-primary' : 'badge bg-secondary';

            document.getElementById('traceUserQuery').textContent = `"${trace.query || ''}"`;

            // Route badge
            const routeBadge = document.getElementById('traceRouteBadge');
            const route = (trace.route || 'KNOWLEDGE').toLowerCase();
            routeBadge.textContent = `${trace.route} (${trace.route_confidence || 100}%)`;
            routeBadge.className = `route-pill ${route}`;

            // Memory decision
            const memBadge = document.getElementById('traceMemoryBadge');
            const memUsed = (trace.memory_decision === 'USED');
            memBadge.textContent = trace.memory_decision || 'BYPASSED';
            memBadge.className = memUsed ? 'badge bg-success' : 'badge bg-secondary';
            if (trace.memory_preview) {
                memBadge.title = trace.memory_preview;
            } else {
                memBadge.removeAttribute('title');
            }

            // Contextual signal
            document.getElementById('traceContextSignal').textContent = trace.contextual_signal || 'NONE';

            // Retrieval summary
            const ret = trace.retrieval_summary || {};
            const hits = ret.hits_count || 0;
            const topScore = ret.top_score || 0;
            const docType = ret.top_doc_type ? ` [${ret.top_doc_type}]` : '';
            document.getElementById('traceRetrievalSummary').textContent = `${hits} hits (${topScore}%)${docType}`;

            // Answerability Gate
            const gateBadge = document.getElementById('traceGateBadge');
            const gateStatus = (trace.answerability_status || 'BYPASSED').toUpperCase();
            gateBadge.textContent = gateStatus;
            if (gateStatus === 'CONFIDENT') {
                gateBadge.className = 'badge bg-success';
            } else if (gateStatus === 'AMBIGUOUS') {
                gateBadge.className = 'badge bg-warning text-dark';
            } else if (gateStatus === 'UNANSWERABLE') {
                gateBadge.className = 'badge bg-danger';
            } else {
                gateBadge.className = 'badge bg-secondary';
            }

            // Grounded hit count
            document.getElementById('traceGroundedCount').textContent = `${trace.grounded_hit_count || 0} Docs`;

            // LLM generation
            const llm = trace.llm_generation || {};
            const provider = llm.provider || 'DeepSeek';
            const model = llm.model || 'deepseek-flash';
            const status = llm.status || 'GENERATED';
            document.getElementById('traceLlmStatus').textContent = `${provider} (${model}) — ${status}`;

            // Latencies
            const lat = trace.latency_breakdown || {};
            const sub = lat.retrieval_sub_stages || {};

            // High-level summary tiles
            document.getElementById('latRouter').textContent = `${lat.router_ms || 0} ms`;
            document.getElementById('latRetrieval').textContent = `${lat.knowledge_retrieval_ms ?? lat.retrieval_ms ?? 0} ms`;
            document.getElementById('latLlm').textContent = `${lat.llm_generation_ms ?? lat.llm_ms ?? 0} ms${lat.ttft_ms ? ` [TTFT: ${lat.ttft_ms} ms]` : ''}`;
            document.getElementById('latTotal').textContent = `${lat.total_ms ?? lat.total_e2e_ms ?? 0} ms`;

            // Granular Stage-Level Latency Tree
            document.getElementById('latDetailedRouter').textContent = `${lat.router_ms || 0} ms`;
            document.getElementById('latDetailedContext').textContent = `${lat.context_resolution_ms || 0} ms`;
            document.getElementById('latDetailedMemory').textContent = `${lat.memory_retrieval_ms || 0} ms`;
            document.getElementById('latDetailedRetrieval').textContent = `${lat.knowledge_retrieval_ms ?? lat.retrieval_ms ?? 0} ms`;


            document.getElementById('latEmbedding').textContent = `${sub.embedding_ms || 0} ms`;
            document.getElementById('latTypesense').textContent = `${sub.typesense_ms || 0} ms`;
            document.getElementById('latReranker').textContent = `${sub.rerank_ms || 0} ms`;

            document.getElementById('latDetailedGate').textContent = `${lat.answerability_ms || 0} ms`;
            document.getElementById('latDetailedLlm').textContent = `${lat.llm_generation_ms ?? lat.llm_ms ?? 0} ms${lat.ttft_ms ? ` (TTFT: ${lat.ttft_ms} ms)` : ''}`;
            document.getElementById('latDetailedTotal').textContent = `${lat.total_e2e_ms ?? lat.total_ms ?? 0} ms`;


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