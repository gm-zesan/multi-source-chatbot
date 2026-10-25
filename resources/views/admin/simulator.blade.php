@extends('admin.app')

@section('title')
    Chat Simulator & Infrastructure Pipeline Tester
@endsection

@push('custom-style')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="{{ asset('assets/css/simulator.css') }}" rel="stylesheet">
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
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h5 class="mb-0 fw-bold d-flex align-items-center gap-2" style="color: #0f172a;">
                                    <i class="ri-robot-2-line text-primary"></i> AI Assistant
                                </h5>
                                <small class="text-muted" style="font-size: 11px;">Interactive test environment</small>
                            </div>

                            @if(isset($allWorkspaces) && $allWorkspaces->isNotEmpty())
                                <div class="d-flex align-items-center gap-1 bg-white border border-primary-subtle px-2 py-1 rounded-2 shadow-sm" style="font-size: 12px;">
                                    <i class="ri-building-4-line text-primary fs-6"></i>
                                    <label for="simulatorWorkspaceSelect" class="small fw-bold text-muted mb-0 d-none d-md-inline">Workspace:</label>
                                    <select id="simulatorWorkspaceSelect" class="form-select form-select-sm border-0 bg-transparent py-0 ps-1 pe-4 fw-semibold text-primary shadow-none" style="font-size: 12px; cursor: pointer; min-width: 150px;" onchange="onSimulatorWorkspaceChange(this.value)">
                                        @foreach($allWorkspaces as $ws)
                                            <option value="{{ $ws->id }}" {{ (isset($workspaceId) && $workspaceId == $ws->id) ? 'selected' : '' }}>
                                                🏢 {{ $ws->name }} (#{{ $ws->id }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif(isset($currentWorkspace))
                                <span class="badge bg-light text-secondary border d-flex align-items-center gap-1 py-1 px-2" style="font-size: 11px;">
                                    <i class="ri-building-4-line text-primary"></i> {{ $currentWorkspace->name }}
                                </span>
                            @endif
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
                    </div>

                    <!-- Input area -->
                    <div class="chat-input-area">
                        <!-- Multi-Source Selection & Active Spreadsheet Bar -->
                        <div class="simulator-source-control mb-2 p-2 rounded-3 bg-light border">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2 flex-grow-1" style="min-width: 280px;">
                                    <label for="simulatorSourceSelect" class="small fw-bold text-muted mb-0 d-flex align-items-center gap-1 text-nowrap">
                                        <i class="ri-database-2-line text-primary"></i> Target Source:
                                    </label>
                                    <select id="simulatorSourceSelect" class="form-select form-select-sm border bg-white shadow-none" style="font-size: 13px; max-width: 320px;" onchange="onSourceSelectionChange(this.value)">
                                        <option value="">🌐 Auto-detect Dataset (Typesense Smart Routing)</option>
                                        @if(isset($availableDocuments) && $availableDocuments->isNotEmpty())
                                            <optgroup label="Workspace Spreadsheets (Generic Analytics)">
                                                @foreach($availableDocuments as $doc)
                                                    <option value="{{ $doc->source_id ?: $doc->id }}" 
                                                        {{ (!empty($activeSpreadsheet['file_id']) && ($activeSpreadsheet['file_id'] == $doc->source_id || $activeSpreadsheet['file_id'] == $doc->id)) ? 'selected' : '' }}>
                                                        📊 {{ $doc->original_filename ?: $doc->title }} ({{ $doc->sheets_count ?: 1 }} {{ \Illuminate\Support\Str::plural('sheet', $doc->sheets_count ?: 1) }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <div id="activeSpreadsheetBar" class="{{ !empty($activeSpreadsheet['file_id']) ? '' : 'd-none' }}">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle d-flex align-items-center gap-1 py-1 px-2">
                                                <i class="ri-file-excel-2-fill"></i> <span id="activeSpreadsheetName" class="text-truncate" style="max-width: 140px;">{{ $activeSpreadsheet['filename'] ?? '' }}</span>
                                            </span>
                                            <span id="activeSpreadsheetAutoBadge" class="badge bg-primary text-white py-1 px-2 {{ !empty($activeSpreadsheet['auto_resolved']) ? '' : 'd-none' }}" title="Auto-detected by Typesense">
                                                <i class="ri-sparkling-fill text-warning me-1"></i> Auto
                                            </span>
                                            <span id="activeSpreadsheetSheets" class="badge bg-secondary-subtle text-secondary small py-1 px-2">
                                                {{ count($activeSpreadsheet['sheets'] ?? []) }} sheets
                                            </span>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 rounded-circle" onclick="clearUploadedExcel()" title="Clear active document">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <a href="{{ route('documents.index') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 px-2 py-1" style="font-size: 12px;" title="View Documents Catalog & Schema">
                                        <i class="ri-file-list-3-line"></i>
                                        <span class="d-none d-sm-inline">Catalog</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <form id="chatForm" onsubmit="handleSend(event); return false;" class="d-flex gap-2 align-items-center">
                            <input type="text" id="userInput" class="form-control form-control-lg fs-6"
                                placeholder="Type your message or ask spreadsheet questions..." autocomplete="off" required>
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

                            <!-- PRICING & RATE INFO (LEFT SIDE) -->
                            <div class="usage-cost-rates">
                                <div class="usage-label">PRICING SETUP (PEAK)</div>
                                <div class="usage-rate-item mt-2">
                                    <span class="text-muted"><i
                                            class="ri-arrow-down-circle-line text-success me-1"></i>Input Rate:</span>
                                    <strong class="text-dark">$0.30 <small class="text-muted fw-normal">/
                                            1M</small></strong>
                                </div>
                                <div class="usage-rate-item mt-1">
                                    <span class="text-muted"><i class="ri-arrow-up-circle-line text-danger me-1"></i>Output
                                        Rate:</span>
                                    <strong class="text-dark">$1.20 <small class="text-muted fw-normal">/
                                            1M</small></strong>
                                </div>
                                <div class="usage-rate-item mt-1">
                                    <span class="text-muted"><i class="ri-exchange-dollar-line text-primary me-1"></i>USD /
                                        BDT:</span>
                                    <strong class="text-primary">1 USD = ৳130</strong>
                                </div>
                            </div>

                            <div class="usage-cost-main">

                                <div class="usage-label">
                                    TOTAL SESSION COST
                                </div>

                                <div class="usage-total-cost">
                                    <span id="wizTotalCost">$0.000000</span>
                                    <span
                                        class="badge bg-success-subtle text-success border border-success-subtle fs-6 ms-2 align-middle"
                                        id="wizTotalCostBdt"
                                        style="font-weight: 700; font-size: 16px !important;">৳0.0000</span>
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
    </div>
@endsection

@push('custom-scripts')
    <script>
        window.SIMULATOR_CONFIG = {
            routes: {
                selectSource: @json(route('simulator.select_source')),
                clearExcel: @json(route('simulator.clear_excel')),
                send: @json(route('simulator.send')),
                clear: @json(route('simulator.clear')),
                exportAnalytics: @json(route('export.analytics')),
                switchWorkspace: @json(route('simulator.switch_workspace')),
            },
            workspaceId: @json($workspaceId ?? 1),
            csrfToken: @json(csrf_token()),
            llmUsageHistory: @json($llmUsageHistory ?? []),
            storedMessages: @json($storedMessages ?? []),
        };

        function onSimulatorWorkspaceChange(newWsId) {
            if (!newWsId) return;
            const url = new URL(window.location.href);
            url.searchParams.set('workspace_id', newWsId);
            url.searchParams.delete('file_id');
            window.location.href = url.toString();
        }
    </script>
    <script src="{{ asset('assets/js/simulator.js') }}"></script>
@endpush