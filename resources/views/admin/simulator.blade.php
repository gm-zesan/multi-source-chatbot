@extends('admin.app')

@section('title')
    Chat Simulator & Infrastructure Pipeline Tester
@endsection

@push('custom-style')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="{{ asset('assets/css/simulator.css') }}?v={{ file_exists(public_path('assets/css/simulator.css')) ? filemtime(public_path('assets/css/simulator.css')) : '2.0' }}" rel="stylesheet">
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
                                        {{ $ws->name }} (#{{ $ws->id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @elseif(isset($currentWorkspace))
                        <span class="app-badge app-badge-secondary !text-2xs flex items-center gap-1">
                            <i class="ri-building-4-line text-brand-500"></i> {{ $currentWorkspace->name }}
                        </span>
                    @endif

                    {{-- Superadmin Agent Mode Testing Selector --}}
                    <div class="flex items-center gap-1 px-2 py-0.5 rounded-md border border-indigo-500/30 bg-indigo-500/10 text-xs">
                        <i class="ri-user-star-line text-indigo-500 text-xs"></i>
                        <span class="text-3xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider hidden sm:inline">Testing Agent:</span>
                        <select id="simulatorAgentSelect" class="app-select !h-6.5 !py-0 !px-1 !text-2xs !bg-transparent !border-0 text-light-text dark:text-dark-text font-bold outline-none cursor-pointer" title="Select which AI Agent to test">
                            <option value="auto" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">🔀 Auto-Orchestrator (All)</option>
                            <option value="sales" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">🛍️ AI Sales Agent (Catalog & Orders)</option>
                            <option value="support" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">🎧 AI Support Agent (FAQs & Tracking)</option>
                            <option value="cfo" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">💼 AI Virtual CFO (10 Analytics Tools)</option>
                            <option value="retention" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">🔄 AI Retention Agent (Follow-up)</option>
                        </select>
                    </div>
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
                    Hello! I am your AI Chatbot simulator. Type any question or message below to test real-time responses.
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
                            <select id="simulatorSourceSelect" class="app-select !h-7.5 !text-xs flex-1 max-w-sm !bg-light-surface dark:!bg-dark-surface !border-light-border dark:!border-dark-border !text-light-text dark:text-dark-text" onchange="onSourceSelectionChange(this.value)">
                                <option value="" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">Auto-detect Dataset (Typesense Smart Routing)</option>
                                @if(isset($availableDocuments) && $availableDocuments->isNotEmpty())
                                    <optgroup label="Workspace Spreadsheets (Generic Analytics)" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text">
                                        @foreach($availableDocuments as $doc)
                                            <option value="{{ $doc->source_id ?: $doc->id }}" class="bg-light-surface dark:bg-dark-surface text-light-text dark:text-dark-text"
                                                {{ (!empty($activeSpreadsheet['file_id']) && ($activeSpreadsheet['file_id'] == $doc->source_id || $activeSpreadsheet['file_id'] == $doc->id)) ? 'selected' : '' }}>
                                                {{ $doc->original_filename ?: $doc->title }} ({{ $doc->sheets_count ?: 1 }} {{ \Illuminate\Support\Str::plural('sheet', $doc->sheets_count ?: 1) }})
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
            <div class="diag-header flex items-center justify-between px-4 py-3 border-b border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-md bg-brand-500/10 text-brand-500 flex items-center justify-center shrink-0">
                        <i class="ri-cpu-line text-sm"></i>
                    </div>
                    <div>
                        <h6 class="text-xs font-semibold text-light-text dark:text-dark-text mb-0 flex items-center gap-1.5 leading-tight">
                            Pipeline Inspector
                        </h6>
                        <small class="text-2xs text-light-muted dark:text-dark-muted block">Real-time multi-turn infrastructure trace</small>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="app-badge app-badge-primary !text-2xs font-mono" id="totalTimeBadge">0 ms</span>
                    <button type="button" class="w-7 h-7 rounded-md flex items-center justify-center text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text hover:bg-light-canvas dark:hover:bg-dark-canvas transition-colors"
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
                        <div class="p-2 rounded-md bg-light-elevated dark:bg-dark-elevated border border-light-border dark:border-dark-border col-span-2">
                            <span class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted block">Active Agent Tested</span>
                            <span id="traceAgentBadge" class="app-badge app-badge-primary !text-2xs mt-1">🔀 Auto-Orchestrator</span>
                        </div>
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
                            <div class="flex justify-between py-0.5 items-center">
                                <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5"><i class="ri-compass-3-line text-brand-500"></i> Router</span>
                                <strong id="latDetailedRouter">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5 items-center">
                                <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5"><i class="ri-brain-line text-brand-500"></i> Context Resolution</span>
                                <strong id="latDetailedContext">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5 items-center">
                                <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5"><i class="ri-hard-drive-2-line text-brand-500"></i> Memory Gate & Context</span>
                                <strong id="latDetailedMemory">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5 items-center">
                                <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5"><i class="ri-search-eye-line text-brand-500"></i> Knowledge Retrieval</span>
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
                            <div class="flex justify-between py-0.5 items-center">
                                <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5"><i class="ri-scales-3-line text-brand-500"></i> Answerability Gate</span>
                                <strong id="latDetailedGate">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5 items-center">
                                <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5"><i class="ri-flashlight-line text-brand-500"></i> Live LLM Generation</span>
                                <strong id="latDetailedLlm" class="text-rose-500 dark:text-rose-400">0 ms</strong>
                            </div>
                            <div class="flex justify-between py-0.5 border-t border-light-border dark:border-dark-border mt-1 pt-1 font-semibold items-center">
                                <span class="text-light-text dark:text-dark-text flex items-center gap-1.5"><i class="ri-timer-flash-line text-emerald-500"></i> Total Pipeline (E2E)</span>
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

        <!-- Usage & Cost Wizard Modal (Tailwind Midnight Enterprise Pro) -->
        <div class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs overflow-y-auto" id="usageWizardModal" tabindex="-1" aria-labelledby="usageWizardLabel" aria-hidden="true" style="display: none;">
            <div class="modal-dialog w-full max-w-4xl app-card shadow-modal overflow-hidden transition-all duration-200 my-auto">
                <div class="modal-content border-0 bg-transparent flex flex-col max-h-[90vh]">
                    <!-- Header -->
                    <div class="app-card-header flex items-center justify-between border-b border-light-border dark:border-dark-border px-4 py-3 bg-light-surface dark:bg-dark-surface shrink-0">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-md bg-brand-500/10 text-brand-500 flex items-center justify-center shrink-0">
                                <i class="ri-funds-line text-base"></i>
                            </div>
                            <div>
                                <h5 class="text-xs font-semibold text-light-text dark:text-dark-text mb-0 flex items-center gap-1.5" id="usageWizardLabel">
                                    LLM Telemetry & Billing
                                </h5>
                                <small class="text-2xs text-light-muted dark:text-dark-muted block">Track session token consumption, latency, and estimated billing</small>
                            </div>
                        </div>
                        <button type="button" class="w-7 h-7 rounded-md flex items-center justify-center text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text hover:bg-light-canvas dark:hover:bg-dark-canvas transition-colors" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ri-close-line text-base"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="app-card-body p-4 overflow-y-auto space-y-4 bg-light-surface dark:bg-dark-surface">
                        <!-- Top Cost Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <!-- 1. Pricing Rates Panel -->
                            <div class="p-3.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex flex-col justify-between">
                                <div class="text-2xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted mb-2 flex items-center gap-1">
                                    <i class="ri-price-tag-3-line text-brand-500"></i> Pricing Setup (Peak)
                                </div>
                                <div class="space-y-1.5 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-light-muted dark:text-dark-muted flex items-center gap-1">
                                            <i class="ri-arrow-down-circle-line text-emerald-500"></i> Input Rate:
                                        </span>
                                        <strong class="text-light-text dark:text-dark-text font-mono tabular-nums">$0.30 <span class="text-2xs font-normal text-light-muted dark:text-dark-muted">/ 1M</span></strong>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-light-muted dark:text-dark-muted flex items-center gap-1">
                                            <i class="ri-arrow-up-circle-line text-rose-500"></i> Output Rate:
                                        </span>
                                        <strong class="text-light-text dark:text-dark-text font-mono tabular-nums">$1.20 <span class="text-2xs font-normal text-light-muted dark:text-dark-muted">/ 1M</span></strong>
                                    </div>
                                    <div class="flex items-center justify-between pt-1 border-t border-light-border dark:border-dark-border">
                                        <span class="text-light-muted dark:text-dark-muted flex items-center gap-1">
                                            <i class="ri-exchange-dollar-line text-brand-500"></i> USD / BDT:
                                        </span>
                                        <strong class="text-brand-500 font-mono tabular-nums">1 USD = ৳130</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Total Session Cost Panel -->
                            <div class="p-3.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex flex-col justify-between">
                                <div class="text-2xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted flex items-center gap-1">
                                    <i class="ri-wallet-3-line text-emerald-500"></i> Total Session Cost
                                </div>
                                <div class="my-2">
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        <span class="text-xl font-bold font-mono tracking-tight text-light-text dark:text-dark-text tabular-nums" id="wizTotalCost">$0.000000</span>
                                        <span class="app-badge app-badge-success !text-xs font-mono font-bold tabular-nums" id="wizTotalCostBdt">৳0.0000</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-2xs text-light-muted dark:text-dark-muted font-mono">
                                    <span><span id="wizTotalRequests" class="text-light-text dark:text-dark-text font-semibold">0</span> requests</span>
                                    <span>·</span>
                                    <span><span id="wizAvgTokens" class="text-light-text dark:text-dark-text font-semibold">0</span> avg tokens</span>
                                </div>
                            </div>

                            <!-- 3. Cost Breakdown Panel -->
                            <div class="p-3.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex flex-col justify-between">
                                <div class="text-2xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted mb-2 flex items-center gap-1">
                                    <i class="ri-pie-chart-line text-amber-500"></i> Cost Distribution
                                </div>
                                <div class="space-y-2 text-xs">
                                    <div class="p-2 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border flex items-center justify-between">
                                        <span class="text-light-muted dark:text-dark-muted flex items-center gap-1">
                                            <i class="ri-arrow-down-line text-emerald-500"></i> Input Cost
                                        </span>
                                        <strong id="wizInputCost" class="font-mono text-light-text dark:text-dark-text tabular-nums">$0.00000</strong>
                                    </div>
                                    <div class="p-2 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border flex items-center justify-between">
                                        <span class="text-light-muted dark:text-dark-muted flex items-center gap-1">
                                            <i class="ri-arrow-up-line text-rose-500"></i> Output Cost
                                        </span>
                                        <strong id="wizOutputCost" class="font-mono text-light-text dark:text-dark-text tabular-nums">$0.00000</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4 Stat Metric Cards Strip -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded bg-brand-500/10 text-brand-500 flex items-center justify-center shrink-0">
                                    <i class="ri-message-3-line text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-3xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted">Requests</div>
                                    <div class="text-sm font-bold font-mono text-light-text dark:text-dark-text tabular-nums" id="wizTotalRequests_stat">0</div>
                                </div>
                            </div>
                            <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0">
                                    <i class="ri-speed-up-line text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-3xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted">Tokens / Query</div>
                                    <div class="text-sm font-bold font-mono text-light-text dark:text-dark-text tabular-nums" id="wizAvgTokens_stat">0</div>
                                </div>
                            </div>
                            <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded bg-emerald-500/10 text-emerald-500 flex items-center justify-center shrink-0">
                                    <i class="ri-arrow-down-line text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-3xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted">Input Tokens</div>
                                    <div class="text-sm font-bold font-mono text-light-text dark:text-dark-text tabular-nums" id="wizTotalInputTokens">0</div>
                                </div>
                            </div>
                            <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded bg-purple-500/10 text-purple-500 flex items-center justify-center shrink-0">
                                    <i class="ri-arrow-up-line text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-3xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted">Output Tokens</div>
                                    <div class="text-sm font-bold font-mono text-light-text dark:text-dark-text tabular-nums" id="wizTotalOutputTokens">0</div>
                                </div>
                            </div>
                        </div>

                        <!-- Request Breakdown DataTable Container -->
                        <div class="app-card overflow-hidden">
                            <div class="app-card-header flex items-center justify-between border-b border-light-border dark:border-dark-border px-3 py-2 bg-light-surface-elevated/40 dark:bg-dark-elevated/40">
                                <div class="flex items-center gap-1.5">
                                    <i class="ri-list-check-2 text-brand-500 text-xs"></i>
                                    <span class="text-xs font-semibold text-light-text dark:text-dark-text">Request Breakdown</span>
                                </div>
                                <div class="text-2xs text-light-muted dark:text-dark-muted font-mono flex items-center gap-1">
                                    <span>Avg.</span>
                                    <strong id="wizAvgCost" class="text-light-text dark:text-dark-text tabular-nums">$0.00</strong>
                                    <span>/ req</span>
                                </div>
                            </div>
                            <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                                <table class="app-table w-full">
                                    <thead>
                                        <tr>
                                            <th style="width: 45px;">#</th>
                                            <th>Model</th>
                                            <th class="text-right">Input</th>
                                            <th class="text-right">Output</th>
                                            <th class="text-right">Total</th>
                                            <th class="text-right">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody id="wizBreakdownTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center text-light-muted dark:text-dark-muted py-6 text-xs">
                                                <i class="ri-inbox-line text-2xl opacity-40 block mb-1"></i>
                                                No requests logged yet.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="app-card-footer flex items-center justify-end px-4 py-3 border-t border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shrink-0">
                        <button type="button" class="app-btn app-btn-secondary !h-7.5 !text-xs" data-bs-dismiss="modal">Close</button>
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