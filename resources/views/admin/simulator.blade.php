@extends('admin.app')

@section('title')
    Chat Simulator & Infrastructure Pipeline Tester
@endsection

@push('custom-style')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="{{ asset('assets/css/simulator.css') }}" rel="stylesheet">
@endpush


@section('content')
    <div class="h-[calc(100vh-80px)] flex flex-col p-0">
        <!-- Simulator Main Layout: Full Width Interactive Chat Window -->
        <div class="chat-card w-full h-full flex flex-col bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border rounded-lg shadow-2xs overflow-hidden">
            <!-- Chat Header -->
            <div class="chat-header flex flex-wrap md:flex-nowrap items-center justify-between p-3 border-b border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface min-h-[58px] gap-2">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <div>
                        <h5 class="text-xs font-semibold text-light-text dark:text-dark-text flex items-center gap-1.5 mb-0">
                            <i class="ri-robot-2-line text-brand-500"></i> AI Assistant
                        </h5>
                        <small class="text-2xs text-light-muted dark:text-dark-muted">Interactive test environment</small>
                    </div>

                    @if(isset($allWorkspaces) && $allWorkspaces->isNotEmpty())
                        <div class="flex items-center gap-1 px-2 py-0.5 rounded-md border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs">
                            <i class="ri-building-4-line text-brand-500 text-xs"></i>
                            <select id="simulatorWorkspaceSelect" class="app-select !h-6.5 !py-0 !px-1 !text-2xs !bg-transparent !border-0 text-light-text dark:text-dark-text font-medium outline-none cursor-pointer" onchange="onSimulatorWorkspaceChange(this.value)">
                                @foreach($allWorkspaces as $ws)
                                    <option value="{{ $ws->id }}" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text" {{ (isset($workspaceId) && $workspaceId == $ws->id) ? 'selected' : '' }}>
                                        🏢 {{ $ws->name }} (#{{ $ws->id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @elseif(isset($currentWorkspace))
                        <span class="app-badge app-badge-secondary !text-2xs flex items-center gap-1">
                            <i class="ri-building-4-line text-brand-500"></i> {{ $currentWorkspace->name }}
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button"
                        class="app-btn app-btn-outline !h-7.5 !text-xs !border-rose-500/30 text-rose-500 hover:bg-rose-500/10"
                        onclick="clearSimulatorChat()">
                        <i class="ri-delete-bin-line text-xs"></i> Clear
                    </button>
                    <button type="button" class="app-btn app-btn-secondary !h-7.5 !text-xs"
                        data-bs-toggle="modal" data-bs-target="#usageWizardModal">
                        <i class="ri-money-dollar-circle-line text-amber-500 text-xs"></i> Usage & Cost
                    </button>
                    <button type="button" class="app-btn app-btn-secondary !h-7.5 !text-xs flex items-center gap-1.5"
                        id="openInspectorBtn" onclick="toggleDiagnosticsDrawer(true)" title="Inspect real-time pipeline trace & latencies">
                        <i class="ri-cpu-line text-brand-500 text-xs"></i>
                        <span class="hidden sm:inline">Pipeline Inspector</span>
                        <span class="app-badge app-badge-primary !text-2xs font-mono" id="headerLatencyBadge">0 ms</span>
                    </button>
                </div>
            </div>

            <!-- Messages area -->
            <div class="chat-messages flex-1 overflow-y-auto p-4 space-y-3 bg-light-canvas dark:bg-dark-canvas" id="chatMessages">
                <div class="message-bubble message-bot max-w-2xl bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border text-light-text dark:text-dark-text p-3 rounded-lg shadow-2xs text-xs">
                    👋 Hi! I am your AI Chatbot simulator. Type any question or message below.
                </div>
            </div>

            <!-- Input area -->
            <div class="chat-input-area p-3 border-t border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface">
                <!-- Multi-Source Selection & Active Spreadsheet Bar -->
                <div class="simulator-source-control mb-2 p-2 rounded-md border border-light-border dark:border-dark-border bg-light-elevated dark:bg-dark-elevated">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 flex-grow min-w-[260px]">
                            <label for="simulatorSourceSelect" class="text-2xs font-semibold text-light-text-secondary dark:text-dark-text-secondary flex items-center gap-1 shrink-0 mb-0">
                                <i class="ri-database-2-line text-brand-500"></i> Target Source:
                            </label>
                            <select id="simulatorSourceSelect" class="app-select !h-7.5 !text-xs flex-1 max-w-sm !bg-light-surface dark:!bg-dark-surface !border-light-border dark:!border-dark-border !text-light-text dark:!text-dark-text" onchange="onSourceSelectionChange(this.value)">
                                <option value="" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">🌐 Auto-detect Dataset (Typesense Smart Routing)</option>
                                @if(isset($availableDocuments) && $availableDocuments->isNotEmpty())
                                    <optgroup label="Workspace Spreadsheets (Generic Analytics)" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">
                                        @foreach($availableDocuments as $doc)
                                            <option value="{{ $doc->source_id ?: $doc->id }}" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text"
                                                {{ (!empty($activeSpreadsheet['file_id']) && ($activeSpreadsheet['file_id'] == $doc->source_id || $activeSpreadsheet['file_id'] == $doc->id)) ? 'selected' : '' }}>
                                                📊 {{ $doc->original_filename ?: $doc->title }} ({{ $doc->sheets_count ?: 1 }} {{ \Illuminate\Support\Str::plural('sheet', $doc->sheets_count ?: 1) }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <div id="activeSpreadsheetBar" class="{{ !empty($activeSpreadsheet['file_id']) ? '' : 'd-none' }}">
                                <div class="flex items-center gap-1.5">
                                    <span class="app-badge app-badge-success !text-2xs flex items-center gap-1">
                                        <i class="ri-file-excel-2-fill"></i> <span id="activeSpreadsheetName" class="truncate max-w-[120px]">{{ $activeSpreadsheet['filename'] ?? '' }}</span>
                                    </span>
                                    <span id="activeSpreadsheetAutoBadge" class="app-badge app-badge-primary !text-2xs {{ !empty($activeSpreadsheet['auto_resolved']) ? '' : 'd-none' }}" title="Auto-detected by Typesense">
                                        <i class="ri-sparkling-fill text-amber-400"></i> Auto
                                    </span>
                                    <span id="activeSpreadsheetSheets" class="app-badge app-badge-secondary !text-2xs">
                                        {{ count($activeSpreadsheet['sheets'] ?? []) }} sheets
                                    </span>
                                    <button type="button" class="text-rose-500 hover:text-rose-600 p-0.5" onclick="clearUploadedExcel()" title="Clear active document">
                                        <i class="ri-close-line text-sm"></i>
                                    </button>
                                </div>
                            </div>
                            <a href="{{ route('knowledge-base.index', ['tab' => 'data_sources']) }}" class="app-btn app-btn-secondary !h-7.5 !text-xs flex items-center gap-1" title="View Documents Catalog & Schema">
                                <i class="ri-file-list-3-line text-xs"></i>
                                <span class="hidden sm:inline">Catalog</span>
                            </a>
                        </div>
                    </div>
                </div>

                <form id="chatForm" onsubmit="handleSend(event); return false;" class="flex gap-2 items-center">
                    <input type="text" id="userInput" class="app-input flex-1 !h-10 !text-xs bg-light-surface dark:bg-dark-surface border-light-border dark:border-dark-border text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted"
                        placeholder="Type your message or ask spreadsheet questions..." autocomplete="off" required>
                    <button type="button" onclick="handleSend(event)" id="sendBtn"
                        class="app-btn app-btn-primary !h-10 px-4 !text-xs flex items-center gap-1 shrink-0">
                        <i class="ri-send-plane-fill text-xs"></i> <span>Send</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Pipeline Inspector Slide-Over Backdrop -->
        <div id="diagnosticsBackdrop"
            class="fixed inset-0 bg-black/50 backdrop-blur-[2px] z-40 hidden transition-opacity duration-200"
            onclick="toggleDiagnosticsDrawer(false)">
        </div>

        <!-- Pipeline Inspector Slide-Over Drawer -->
        <aside id="diagnosticsDrawer"
            class="diag-card fixed inset-y-0 right-0 w-full sm:w-[500px] lg:w-[540px] bg-light-surface dark:bg-dark-surface border-l border-light-border dark:border-dark-border z-50 flex flex-col shadow-2xl translate-x-full transition-transform duration-300 ease-in-out"
            aria-label="Pipeline Inspector">
            <!-- Drawer Header -->
            <div class="diag-header flex items-center justify-between px-4 py-3 border-b !border-light-border dark:!border-dark-border !bg-light-surface dark:!bg-dark-surface shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-md bg-brand-500/10 text-brand-500 flex items-center justify-center">
                        <i class="ri-cpu-line text-sm"></i>
                    </div>
                    <div>
                        <h6 class="text-xs font-semibold !text-light-text dark:!text-dark-text mb-0 flex items-center gap-1.5">
                            Pipeline Inspector
                        </h6>
                        <small class="text-2xs !text-light-muted dark:!text-dark-muted block">Real-time multi-turn infrastructure trace</small>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="app-badge app-badge-primary !text-2xs font-mono" id="totalTimeBadge">0 ms</span>
                    <button type="button" class="w-7 h-7 rounded-md flex items-center justify-center !text-light-muted hover:!text-light-text dark:!text-dark-muted dark:hover:!text-dark-text hover:bg-light-canvas dark:hover:bg-dark-canvas transition-colors"
                        onclick="toggleDiagnosticsDrawer(false)" title="Close Inspector (Esc)">
                        <i class="ri-close-line text-base"></i>
                    </button>
                </div>
            </div>

            <!-- Drawer Body -->
            <div class="diag-body flex-1 overflow-y-auto p-4 space-y-3 bg-light-canvas dark:bg-dark-canvas" id="diagBody">
                <!-- Turn Decision Trace (Real-Time Multi-Turn Inspector) -->
                <div class="diag-section app-card p-3 border border-brand-500/30 bg-light-surface dark:bg-dark-surface rounded-lg shadow-2xs" id="turnDecisionTraceCard">
                    <div class="flex items-center justify-between mb-2">
                        <h6 class="text-xs font-semibold text-brand-500 dark:text-brand-400 flex items-center gap-1.5 mb-0">
                            <i class="ri-git-commit-line text-xs"></i> Turn Decision Trace
                        </h6>
                        <span class="app-badge app-badge-primary !text-2xs" id="traceTurnBadge">Ready</span>
                    </div>

                    {{-- Turn History Navigation Pills --}}
                    <div id="turnHistoryNav" class="flex items-center gap-1.5 mb-2.5 overflow-x-auto py-1 scrollbar-none">
                        <span class="text-2xs text-light-muted dark:text-dark-muted">Send a message to view live turn trace</span>
                    </div>

                    {{-- Query Box --}}
                    <div class="p-2 mb-2.5 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border text-xs">
                        <span class="text-3xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted block mb-0.5">User Query</span>
                        <strong id="traceUserQuery" class="text-light-text dark:text-dark-text break-words font-medium">"Waiting for user message..."</strong>
                    </div>

                    {{-- Decision Key Metrics Matrix --}}
                    <div class="grid grid-cols-2 gap-2 mb-2.5 text-xs">
                        <div class="p-2 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border">
                            <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Route Decision</span>
                            <span id="traceRouteBadge" class="route-pill chat mt-1">IDLE</span>
                        </div>
                        <div class="p-2 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border">
                            <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Memory Decision</span>
                            <span id="traceMemoryBadge" class="app-badge app-badge-secondary !text-2xs mt-1">IDLE</span>
                        </div>
                        <div class="p-2 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border">
                            <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Contextual Signal</span>
                            <strong id="traceContextSignal" class="text-light-text dark:text-dark-text mt-1 block text-2xs truncate">NONE</strong>
                        </div>
                        <div class="p-2 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border">
                            <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Retrieval Summary</span>
                            <strong id="traceRetrievalSummary" class="text-light-text dark:text-dark-text mt-1 block text-2xs truncate">0 hits</strong>
                        </div>
                        <div class="p-2 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border">
                            <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Answerability Gate</span>
                            <span id="traceGateBadge" class="app-badge app-badge-secondary !text-2xs mt-1">IDLE</span>
                        </div>
                        <div class="p-2 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border">
                            <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Grounded Hit Count</span>
                            <strong id="traceGroundedCount" class="text-emerald-500 dark:text-emerald-400 mt-1 block text-2xs">0 Docs</strong>
                        </div>
                    </div>

                    {{-- LLM Generation & Latency Breakdown --}}
                    <div class="p-2.5 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border text-xs">
                        <div class="flex items-center justify-between mb-1.5 pb-1 border-b border-light-border dark:border-dark-border">
                            <span class="text-2xs text-light-muted dark:text-dark-muted">LLM Generation:</span>
                            <strong id="traceLlmStatus" class="text-light-text dark:text-dark-text text-2xs">Ready</strong>
                        </div>
                        <div class="grid grid-cols-4 gap-1 text-center py-1">
                            <div class="border-r border-light-border dark:border-dark-border pr-1">
                                <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Router</span>
                                <strong id="latRouter" class="text-light-text dark:text-dark-text text-2xs font-mono">0 ms</strong>
                            </div>
                            <div class="border-r border-light-border dark:border-dark-border pr-1">
                                <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Retrieval</span>
                                <strong id="latRetrieval" class="text-light-text dark:text-dark-text text-2xs font-mono">0 ms</strong>
                            </div>
                            <div class="border-r border-light-border dark:border-dark-border pr-1">
                                <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">LLM</span>
                                <strong id="latLlm" class="text-light-text dark:text-dark-text text-2xs font-mono">0 ms</strong>
                            </div>
                            <div>
                                <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Total E2E</span>
                                <strong id="latTotal" class="text-brand-500 dark:text-brand-400 text-2xs font-mono font-bold">0 ms</strong>
                            </div>
                        </div>

                        {{-- Granular Stage-Level Latency Tree --}}
                        <div class="mt-2 pt-2 border-t border-light-border dark:border-dark-border font-mono text-2xs text-light-text dark:text-dark-text space-y-1">
                            <div class="flex justify-between py-0.5">
                                <span class="text-light-muted dark:text-dark-muted">🧭 Router</span>
                                <strong id="latDetailedRouter">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-light-muted dark:text-dark-muted">🧠 Context Resolution</span>
                                <strong id="latDetailedContext">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-light-muted dark:text-dark-muted">💾 Memory Gate & Context</span>
                                <strong id="latDetailedMemory">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-light-muted dark:text-dark-muted">🔍 Knowledge Retrieval</span>
                                <strong id="latDetailedRetrieval" class="text-brand-500 dark:text-brand-400">0 ms</strong>
                            </div>
                            <div id="latRetrievalSubTree" class="pl-3 text-light-muted dark:text-dark-muted text-3xs space-y-0.5">
                                <div class="flex justify-between">
                                    <span>├─ Embedding</span>
                                    <span id="latEmbedding">0 ms</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>├─ Typesense Search</span>
                                    <span id="latTypesense">0 ms</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>└─ Reranker</span>
                                    <span id="latReranker">0 ms</span>
                                </div>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-light-muted dark:text-dark-muted">⚖️ Answerability Gate</span>
                                <strong id="latDetailedGate">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-light-muted dark:text-dark-muted">⚡ Live LLM Generation</span>
                                <strong id="latDetailedLlm" class="text-rose-500 dark:text-rose-400">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5 border-t border-light-border dark:border-dark-border mt-1 pt-1 font-semibold">
                                <span class="text-light-text dark:text-dark-text">⏱️ Total Pipeline (E2E)</span>
                                <strong id="latDetailedTotal" class="text-emerald-500 dark:text-emerald-400">0 ms</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 0: Hybrid Router Capability Decision -->
                <div class="diag-section app-card p-3 border border-purple-500/20 bg-light-surface dark:bg-dark-surface rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-purple-600 dark:text-purple-400 flex items-center gap-1">
                            <i class="ri-compass-3-line"></i> 0. Hybrid Router Capability
                        </span>
                        <span class="route-pill chat" id="routerBadge">Idle</span>
                    </div>
                    <div id="routerResults" class="space-y-1 text-2xs text-light-muted dark:text-dark-muted">
                        <div class="flex justify-between">
                            <span>Route: <strong id="routerRoute" class="text-light-text dark:text-dark-text">N/A</strong></span>
                            <span>Intent: <strong id="routerIntent" class="text-light-text dark:text-dark-text">None</strong></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Router Latency: <strong id="routerLatency" class="text-light-text dark:text-dark-text">0 ms</strong></span>
                            <span>Confidence: <strong id="routerConfidence" class="text-light-text dark:text-dark-text">0%</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Step 1: CRM Entity Extraction -->
                <div class="diag-section app-card p-3 border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-purple-500 flex items-center gap-1">
                            <i class="ri-contacts-book-2-line"></i> 1. Contact / CRM Extractor
                        </span>
                        <span class="app-badge app-badge-secondary !text-2xs" id="crmBadge">No Data</span>
                    </div>
                    <div id="crmResults" class="text-2xs text-light-muted dark:text-dark-muted">
                        <span>Send a message to view extracted emails/phones.</span>
                    </div>
                </div>

                <!-- Step 2: Python Embedding Service -->
                <div class="diag-section app-card p-3 border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-brand-500 flex items-center gap-1">
                            <i class="ri-code-s-slash-line"></i> 2. Python FastAPI Embedding
                        </span>
                        <span class="app-badge app-badge-secondary !text-2xs" id="pyBadge">Idle</span>
                    </div>
                    <div id="pyResults" class="space-y-1 text-2xs text-light-muted dark:text-dark-muted">
                        <div class="flex justify-between">
                            <span>Model: <strong id="pyModel" class="text-light-text dark:text-dark-text">paraphrase-multilingual-mpnet-base-v2</strong></span>
                            <span>Dims: <strong id="pyDims" class="text-light-text dark:text-dark-text">768</strong></span>
                        </div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-1 truncate font-mono" id="pyVectorSample">Vector: [ Waiting for request... ]</div>
                    </div>
                </div>

                <!-- Step 3: Typesense Hybrid Search -->
                <div class="diag-section app-card p-3 border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-cyan-500 flex items-center gap-1">
                            <i class="ri-search-eye-line"></i> 3. Typesense Search Engine
                        </span>
                        <span class="app-badge app-badge-secondary !text-2xs" id="tsBadge">Idle</span>
                    </div>
                    <div id="tsResults" class="space-y-1 text-2xs text-light-muted dark:text-dark-muted">
                        <div class="flex justify-between">
                            <span>Match Type: <strong id="tsMatchType" class="text-light-text dark:text-dark-text">N/A</strong></span>
                            <span>Matched FAQ ID: <strong id="tsFaqId" class="text-light-text dark:text-dark-text">None</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Scoring Breakdown & Confidence -->
                <div class="diag-section app-card p-3 border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-emerald-500 flex items-center gap-1">
                            <i class="ri-bar-chart-grouped-line"></i> 4. Confidence & Auto-Reply
                        </span>
                        <span class="app-badge app-badge-secondary !text-2xs" id="scoreBadge">0.0%</span>
                    </div>
                    <div id="scoreResults" class="space-y-2 text-2xs">
                        <div>
                            <div class="flex justify-between mb-1 text-light-muted dark:text-dark-muted">
                                <span>Keyword Score:</span>
                                <strong id="kwScoreText" class="text-light-text dark:text-dark-text">0%</strong>
                            </div>
                            <div class="h-1.5 w-full bg-light-border dark:bg-dark-border rounded-full overflow-hidden">
                                <div class="h-full bg-brand-500 transition-all duration-300" id="kwBar" style="width: 0%;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1 text-light-muted dark:text-dark-muted">
                                <span>Semantic Score:</span>
                                <strong id="semScoreText" class="text-light-text dark:text-dark-text">0%</strong>
                            </div>
                            <div class="h-1.5 w-full bg-light-border dark:bg-dark-border rounded-full overflow-hidden">
                                <div class="h-full bg-purple-500 transition-all duration-300" id="semBar" style="width: 0%;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1 text-light-muted dark:text-dark-muted">
                                <span>Final Confidence Score:</span>
                                <strong id="finalScoreText" class="text-emerald-500 font-bold">0%</strong>
                            </div>
                            <div class="h-1.5 w-full bg-light-border dark:bg-dark-border rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 transition-all duration-300" id="finalBar" style="width: 0%;"></div>
                            </div>
                            <div class="text-3xs text-light-muted dark:text-dark-muted mt-1 text-end">Threshold: 40.0%</div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Semantic Answerability & Safety Gate (Tier 4) -->
                <div class="diag-section app-card p-3 border border-emerald-500/20 bg-light-surface dark:bg-dark-surface rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <i class="ri-shield-check-line"></i> 5. Semantic Answerability Gate
                        </span>
                        <span class="app-badge app-badge-secondary !text-2xs" id="gateBadge">IDLE</span>
                    </div>
                    <div id="gateResults" class="space-y-1.5 text-2xs text-light-muted dark:text-dark-muted">
                        <div class="flex justify-between">
                            <span>Gate Decision: <strong id="gateStatusText" class="text-light-text dark:text-dark-text">N/A</strong></span>
                            <span>Confidence: <strong id="gateConfidenceText" class="text-light-text dark:text-dark-text">0.0%</strong></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Barrier Rule: <strong id="gateRuleText" class="text-light-text dark:text-dark-text">None</strong></span>
                            <span>Score Margin: <strong id="gateMarginText" class="text-light-text dark:text-dark-text">0.00</strong></span>
                        </div>
                        <div class="pt-1 border-t border-light-border dark:border-dark-border">
                            <span>Grounded Hits Authorized: <strong id="gateGroundedCount" class="text-emerald-500 font-semibold">0</strong></span>
                            <div id="gateGroundedDocs" class="mt-1 space-y-1"></div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Usage & Cost Wizard Modal -->
        <div class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" id="usageWizardModal" tabindex="-1" aria-labelledby="usageWizardLabel" aria-hidden="true" style="display: none;">

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
    <script src="{{ asset('assets/js/simulator.js') }}?v={{ filemtime(public_path('assets/js/simulator.js')) }}"></script>
@endpush