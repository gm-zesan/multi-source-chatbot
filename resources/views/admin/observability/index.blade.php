@extends('admin.app')

@section('title', 'Observability & Live Telemetry')

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
@endpush

@section('content')
<div class="space-y-4">
    {{-- Unified Header Card --}}
    <div class="app-card table-card">
        <div class="app-card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-base font-semibold text-text-primary tracking-tight">Observability & Live Telemetry</h1>
                <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                    <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                    <span>/</span>
                    <span class="text-text-secondary font-medium">Observability</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <label for="autoRefreshSelect" class="text-xs text-text-muted whitespace-nowrap">Auto-Refresh:</label>
                    <select id="autoRefreshSelect" class="app-select w-20 text-xs" onchange="handleAutoRefreshChange(this.value)">
                        <option value="0">Off</option>
                        <option value="5">5s</option>
                        <option value="10" selected>10s</option>
                        <option value="30">30s</option>
                    </select>
                </div>

                <span class="app-badge bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-xs px-2.5 py-1" id="liveIndicator">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    Stream Active
                </span>

                <button class="app-btn app-btn-secondary" onclick="location.reload()">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    {{-- Top KPI Summary Cards (4 Columns) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {{-- KPI 1 --}}
        <div class="app-card p-3.5 flex items-center justify-between">
            <div>
                <span class="text-2xs font-semibold text-text-muted uppercase tracking-wider block">Total AI Replies</span>
                <span class="text-xl font-bold text-text-primary mt-1 block">{{ number_format($totalAiResponses) }}</span>
            </div>
            <div class="w-9 h-9 rounded-md bg-brand-primary/10 text-brand-primary flex items-center justify-center text-lg">
                <i class="ri-message-3-line"></i>
            </div>
        </div>

        {{-- KPI 2 --}}
        <div class="app-card p-3.5 flex items-center justify-between">
            <div>
                <span class="text-2xs font-semibold text-text-muted uppercase tracking-wider block">P50 Response Time</span>
                <span class="text-xl font-bold text-emerald-500 mt-1 block">
                    {{ $p50Latency }} <span class="text-xs font-normal text-text-muted">ms</span>
                </span>
            </div>
            <div class="w-9 h-9 rounded-md bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg">
                <i class="ri-speed-line"></i>
            </div>
        </div>

        {{-- KPI 3 --}}
        <div class="app-card p-3.5 flex items-center justify-between">
            <div>
                <span class="text-2xs font-semibold text-text-muted uppercase tracking-wider block">P95 Tail Latency</span>
                <span class="text-xl font-bold text-rose-500 mt-1 block">
                    {{ $p95Latency }} <span class="text-xs font-normal text-text-muted">ms</span>
                </span>
            </div>
            <div class="w-9 h-9 rounded-md bg-rose-500/10 text-rose-500 flex items-center justify-center text-lg">
                <i class="ri-timer-line"></i>
            </div>
        </div>

        {{-- KPI 4 --}}
        <div class="app-card p-3.5 flex items-center justify-between">
            <div>
                <span class="text-2xs font-semibold text-text-muted uppercase tracking-wider block">Active Conversations</span>
                <span class="text-xl font-bold text-text-primary mt-1 block">{{ number_format($activeConversationsCount) }}</span>
            </div>
            <div class="w-9 h-9 rounded-md bg-sky-500/10 text-sky-500 flex items-center justify-center text-lg">
                <i class="ri-chat-voice-line"></i>
            </div>
        </div>
    </div>

    {{-- Distribution Breakdown Cards (2 Columns) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- Routing Distribution --}}
        <div class="app-card">
            <div class="app-card-header">
                <div>
                    <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Intent Routing Distribution</h2>
                    <p class="text-2xs text-text-muted">Sampled across last {{ $sampleCount }} turns</p>
                </div>
                <span class="text-2xs text-text-muted font-medium">{{ array_sum($routeCounts) }} turns</span>
            </div>
            <div class="app-card-body space-y-3">
                @php $totalRoutes = max(1, array_sum($routeCounts)); @endphp

                <div>
                    <div class="flex justify-between text-2xs text-text-secondary mb-1">
                        <span><strong>KNOWLEDGE</strong> (Grounding & Search)</span>
                        <span>{{ $routeCounts['knowledge'] }} ({{ round(($routeCounts['knowledge'] / $totalRoutes) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-brand-primary rounded-full" style="width: {{ ($routeCounts['knowledge'] / $totalRoutes) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-2xs text-text-secondary mb-1">
                        <span><strong>CHAT</strong> (Conversational General)</span>
                        <span>{{ $routeCounts['chat'] }} ({{ round(($routeCounts['chat'] / $totalRoutes) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-purple-500 rounded-full" style="width: {{ ($routeCounts['chat'] / $totalRoutes) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-2xs text-text-secondary mb-1">
                        <span><strong>ACTION</strong> (Commerce Operations)</span>
                        <span>{{ $routeCounts['action'] }} ({{ round(($routeCounts['action'] / $totalRoutes) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ ($routeCounts['action'] / $totalRoutes) * 100 }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Gate Decisions Distribution --}}
        <div class="app-card">
            <div class="app-card-header">
                <div>
                    <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Semantic Answerability Gate Distribution</h2>
                    <p class="text-2xs text-text-muted">Safety verification before generating customer replies</p>
                </div>
                <span class="text-2xs text-text-muted font-medium">{{ array_sum($gateCounts) }} gates</span>
            </div>
            <div class="app-card-body space-y-3">
                @php $totalGates = max(1, array_sum($gateCounts)); @endphp

                <div>
                    <div class="flex justify-between text-2xs text-text-secondary mb-1">
                        <span class="text-emerald-500 font-medium">CONFIDENT (Grounded)</span>
                        <span>{{ $gateCounts['confident'] }} ({{ round(($gateCounts['confident'] / $totalGates) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ ($gateCounts['confident'] / $totalGates) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-2xs text-text-secondary mb-1">
                        <span class="text-amber-500 font-medium">AMBIGUOUS (Clarification Prompted)</span>
                        <span>{{ $gateCounts['ambiguous'] }} ({{ round(($gateCounts['ambiguous'] / $totalGates) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-amber-500 rounded-full" style="width: {{ ($gateCounts['ambiguous'] / $totalGates) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-2xs text-text-secondary mb-1">
                        <span class="text-rose-500 font-medium">UNANSWERABLE (Safely Refused)</span>
                        <span>{{ $gateCounts['unanswerable'] }} ({{ round(($gateCounts['unanswerable'] / $totalGates) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-rose-500 rounded-full" style="width: {{ ($gateCounts['unanswerable'] / $totalGates) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-2xs text-text-secondary mb-1">
                        <span class="text-text-muted">BYPASSED (Direct Intent Handler)</span>
                        <span>{{ $gateCounts['bypassed'] }} ({{ round(($gateCounts['bypassed'] / $totalGates) * 100, 1) }}%)</span>
                    </div>
                    <div class="w-full h-1.5 bg-surface-elevated rounded-full overflow-hidden">
                        <div class="h-full bg-slate-600 rounded-full" style="width: {{ ($gateCounts['bypassed'] / $totalGates) * 100 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Telemetry Traces Table --}}
    <div class="app-card table-card">
        <div class="app-card-header table-header">
            <div>
                <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Recent Turn Traces</h2>
                <p class="text-2xs text-text-muted">Diagnostic event records for outbound AI responses</p>
            </div>
            <span class="text-2xs text-text-muted">Showing last {{ $messages->count() }} interactions</span>
        </div>
        <div class="app-card-body p-0">
            <div class="overflow-x-auto p-4">
                <table class="w-full text-xs" id="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 90px;">Time</th>
                            <th style="width: 170px;">Customer / Channel</th>
                            <th>AI Outbound Response</th>
                            <th style="width: 100px;">Route</th>
                            <th style="width: 120px;">Gate Decision</th>
                            <th style="width: 90px;">Latency</th>
                            <th style="width: 60px;" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $index => $msg)
                            @php
                                $meta = $msg->metadata ?? [];
                                $route = strtolower((string) ($meta['route'] ?? $meta['router_type'] ?? 'knowledge'));
                                $gate = strtolower((string) ($meta['answerability_decision']['status'] ?? $meta['answerability'] ?? 'none'));
                                $latency = $meta['total_time_ms'] ?? $meta['routing_telemetry']['total_e2e_ms'] ?? null;
                            @endphp
                            <tr>
                                <td class="text-text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <span class="text-text-primary font-medium block">{{ $msg->created_at->format('H:i:s') }}</span>
                                    <span class="text-2xs text-text-muted block">{{ $msg->created_at->format('M d') }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('conversations.show', $msg->conversation_id) }}" class="text-brand-primary hover:underline font-medium block truncate max-w-[150px]">
                                        {{ $msg->conversation?->customer_name ?? '#' . substr((string) $msg->conversation_id, 0, 8) }}
                                    </a>
                                    <span class="text-2xs text-text-muted block truncate max-w-[150px]">
                                        {{ $msg->conversation?->channelAccount?->channel?->name ?? 'Direct Chat' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="max-w-md truncate text-text-secondary" title="{{ $msg->body }}">
                                        {{ $msg->body }}
                                    </div>
                                </td>
                                <td>
                                    @if ($route === 'knowledge')
                                        <span class="app-badge bg-brand-primary/10 text-brand-primary border border-brand-primary/20 uppercase text-2xs">knowledge</span>
                                    @elseif ($route === 'action')
                                        <span class="app-badge bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 uppercase text-2xs">action</span>
                                    @else
                                        <span class="app-badge bg-purple-500/10 text-purple-400 border border-purple-500/20 uppercase text-2xs">{{ $route }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($gate === 'confident')
                                        <span class="app-badge bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-2xs">
                                            <i class="ri-checkbox-circle-line"></i> Confident
                                        </span>
                                    @elseif ($gate === 'ambiguous')
                                        <span class="app-badge bg-amber-500/10 text-amber-500 border border-amber-500/20 text-2xs">
                                            <i class="ri-question-line"></i> Ambiguous
                                        </span>
                                    @elseif ($gate === 'unanswerable')
                                        <span class="app-badge bg-rose-500/10 text-rose-500 border border-rose-500/20 text-2xs">
                                            <i class="ri-forbid-line"></i> Refused
                                        </span>
                                    @else
                                        <span class="app-badge bg-surface-elevated text-text-muted border border-border text-2xs">Bypassed</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($latency)
                                        <span class="app-badge bg-surface-elevated text-text-secondary border border-border font-mono text-2xs">
                                            {{ round((float) $latency) }} ms
                                        </span>
                                    @else
                                        <span class="text-text-muted text-2xs">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button type="button" class="app-action-btn hover:text-brand-primary" title="Inspect Trace"
                                        onclick="inspectTrace({{ json_encode($msg->id) }}, {{ json_encode($meta) }}, {{ json_encode($msg->body) }})">
                                        <i class="ri-scan-line"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Trace Inspector Modal --}}
<div class="fixed inset-0 z-50 hidden bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4 modal" id="traceModal" tabindex="-1" aria-hidden="true">
    <div class="app-card w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden modal-dialog">
        <div class="app-card-header flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-cpu-line text-brand-primary"></i>
                <h3 class="text-xs font-semibold text-text-primary tracking-tight">Turn Diagnostic Trace (#<span id="modalMsgId">-</span>)</h3>
            </div>
            <button type="button" class="app-action-btn" data-bs-dismiss="modal" aria-label="Close">
                <i class="ri-close-line text-sm"></i>
            </button>
        </div>

        <div class="app-card-body overflow-y-auto space-y-4">
            {{-- 4 Metric Tiles Strip --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div class="p-2.5 rounded-md bg-surface-elevated border border-border">
                    <span class="text-2xs text-text-muted uppercase tracking-wider block mb-1">Route Intent</span>
                    <span class="app-badge text-2xs font-semibold" id="modalRoute">-</span>
                </div>
                <div class="p-2.5 rounded-md bg-surface-elevated border border-border">
                    <span class="text-2xs text-text-muted uppercase tracking-wider block mb-1">Gate Decision</span>
                    <span class="app-badge text-2xs font-semibold" id="modalGate">-</span>
                </div>
                <div class="p-2.5 rounded-md bg-surface-elevated border border-border">
                    <span class="text-2xs text-text-muted uppercase tracking-wider block mb-1">Provider & Model</span>
                    <span class="text-xs text-text-primary font-mono truncate block" id="modalProvider">-</span>
                </div>
                <div class="p-2.5 rounded-md bg-surface-elevated border border-border">
                    <span class="text-2xs text-text-muted uppercase tracking-wider block mb-1">Latency</span>
                    <span class="text-xs font-mono font-medium block" id="modalLatency">-</span>
                </div>
            </div>

            {{-- Navigation Tabs --}}
            <div class="flex items-center gap-2 border-b border-border pb-2">
                <button type="button" class="app-btn app-btn-secondary text-xs active" id="pill-overview-tab" data-bs-toggle="pill" data-bs-target="#pill-overview">
                    <i class="ri-dashboard-line"></i> Structured Overview
                </button>
                <button type="button" class="app-btn app-btn-secondary text-xs" id="pill-json-tab" data-bs-toggle="pill" data-bs-target="#pill-json">
                    <i class="ri-code-s-slash-line"></i> Raw Diagnostic JSON
                </button>
            </div>

            <div class="tab-content" id="traceModalTabsContent">
                {{-- Tab 1: Overview --}}
                <div class="tab-pane show active space-y-3" id="pill-overview">
                    {{-- Outbound AI Reply Card --}}
                    <div class="p-3 rounded-md bg-surface-elevated border border-border space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-2xs font-semibold text-text-muted uppercase tracking-wider">AI Generated Outbound Response</span>
                            <button type="button" class="app-action-btn text-xs px-2 h-6" id="btnCopyReply" onclick="copyModalReplyText()">
                                <i class="ri-file-copy-line"></i> <span id="copyReplyLabel">Copy</span>
                            </button>
                        </div>
                        <div class="text-xs text-text-primary leading-relaxed whitespace-pre-wrap font-sans" id="modalReplyText"></div>
                    </div>

                    {{-- Grounding & Knowledge Context --}}
                    <div id="modalGroundingSection" class="hidden p-3 rounded-md bg-surface-elevated border border-border space-y-2">
                        <span class="text-2xs font-semibold text-brand-primary uppercase tracking-wider flex items-center gap-1">
                            <i class="ri-book-read-line"></i> Knowledge Grounding & Citations
                        </span>
                        <div class="text-xs text-text-secondary leading-relaxed" id="modalGroundingContent"></div>
                    </div>

                    {{-- Vector Retrieval & Search Telemetry --}}
                    <div id="modalRetrievalSection" class="hidden p-3 rounded-md bg-surface-elevated border border-border space-y-2">
                        <span class="text-2xs font-semibold text-emerald-500 uppercase tracking-wider flex items-center gap-1">
                            <i class="ri-search-eye-line"></i> Vector Retrieval & Semantic Search Telemetry
                        </span>
                        <div class="text-xs text-text-secondary leading-relaxed" id="modalRetrievalContent"></div>
                    </div>
                </div>

                {{-- Tab 2: Raw JSON --}}
                <div class="tab-pane hidden space-y-2" id="pill-json">
                    <div class="flex items-center justify-between">
                        <span class="text-2xs text-text-muted font-mono">Format: JSON Payload</span>
                        <button type="button" class="app-action-btn text-xs px-2 h-6" id="btnCopyJson" onclick="copyModalJsonPayload()">
                            <i class="ri-file-copy-line"></i> <span id="copyJsonLabel">Copy JSON</span>
                        </button>
                    </div>
                    <pre class="p-3 rounded-md bg-canvas border border-border text-xs font-mono text-text-secondary overflow-x-auto max-h-72" id="modalJsonBox"></pre>
                </div>
            </div>
        </div>

        <div class="app-card-footer flex items-center justify-between">
            <button type="button" class="app-btn app-btn-secondary text-xs" onclick="copyModalJsonPayload()">
                <i class="ri-code-box-line"></i> Copy Full Payload
            </button>
            <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script>
        let currentReplyText = '';
        let currentJsonText = '';
        let autoRefreshTimer = null;

        function handleAutoRefreshChange(intervalSeconds) {
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
                autoRefreshTimer = null;
            }
            const sec = parseInt(intervalSeconds, 10);
            if (sec > 0) {
                localStorage.setItem('obs_auto_refresh', sec);
                autoRefreshTimer = setInterval(() => {
                    location.reload();
                }, sec * 1000);
            } else {
                localStorage.setItem('obs_auto_refresh', 0);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof $.fn.DataTable !== 'undefined') {
                $('#data-table').DataTable({
                    pageLength: 20,
                    order: [[0, 'asc']]
                });
            }

            // Restore auto-refresh preference
            const savedInterval = localStorage.getItem('obs_auto_refresh');
            if (savedInterval !== null) {
                const select = document.getElementById('autoRefreshSelect');
                if (select) {
                    select.value = savedInterval;
                    handleAutoRefreshChange(savedInterval);
                }
            } else {
                handleAutoRefreshChange(10);
            }
        });

        function inspectTrace(msgId, meta, replyText) {
            currentReplyText = replyText || '';
            currentJsonText = JSON.stringify(meta, null, 2);

            document.getElementById('modalMsgId').textContent = msgId;
            document.getElementById('modalReplyText').textContent = replyText || 'No reply text';

            // Route pill
            const route = String(meta.route || meta.router_type || 'knowledge').toLowerCase();
            const routeEl = document.getElementById('modalRoute');
            routeEl.textContent = route.toUpperCase();
            routeEl.className = 'app-badge text-2xs ' + (route === 'knowledge' ? 'bg-brand-primary/10 text-brand-primary border border-brand-primary/20' : (route === 'action' ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-purple-500/10 text-purple-400 border border-purple-500/20'));

            // Gate decision pill
            const gate = String(meta.answerability_decision ? meta.answerability_decision.status : (meta.answerability || 'bypassed')).toLowerCase();
            const gateEl = document.getElementById('modalGate');
            gateEl.textContent = gate.toUpperCase();
            gateEl.className = 'app-badge text-2xs ' + (gate === 'confident' ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : (gate === 'ambiguous' ? 'bg-amber-500/10 text-amber-500 border border-amber-500/20' : (gate === 'unanswerable' ? 'bg-rose-500/10 text-rose-500 border border-rose-500/20' : 'bg-surface-elevated text-text-muted border border-border')));

            // Provider & Model
            const provider = meta.provider || 'DeepSeek';
            const model = meta.model || 'deepseek-chat';
            const providerEl = document.getElementById('modalProvider');
            providerEl.textContent = `${provider} (${model})`;
            providerEl.title = `${provider} (${model})`;

            // Latency
            const latency = meta.total_time_ms ?? meta.routing_telemetry?.total_e2e_ms ?? null;
            const latencyEl = document.getElementById('modalLatency');
            if (latency) {
                const ms = Math.round(Number(latency));
                latencyEl.innerHTML = `<span class="${ms > 1500 ? 'text-rose-500' : (ms > 700 ? 'text-amber-500' : 'text-emerald-500')} font-semibold">${ms} ms</span>`;
            } else {
                latencyEl.textContent = '—';
            }

            // Grounding details
            const groundingSection = document.getElementById('modalGroundingSection');
            const groundingContent = document.getElementById('modalGroundingContent');
            const decision = meta.answerability_decision || {};
            const hits = meta.knowledge_hits || meta.retrieval_metadata || [];

            if (decision.status || (Array.isArray(hits) && hits.length > 0) || decision.target_title) {
                let html = '<div class="space-y-1.5 text-xs">';
                if (decision.target_title) {
                    html += `<div><strong>Matched Document:</strong> <span class="text-brand-primary">${escapeHtml(decision.target_title)}</span></div>`;
                }
                if (decision.best_score) {
                    html += `<div><strong>Confidence Score:</strong> <span class="app-badge bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-2xs">${Math.round(decision.best_score * 100)}%</span></div>`;
                }
                if (decision.reason) {
                    html += `<div><strong>Gate Evaluation:</strong> <span class="text-text-muted">${escapeHtml(decision.reason)}</span></div>`;
                }
                html += '</div>';
                groundingContent.innerHTML = html;
                groundingSection.classList.remove('hidden');
            } else {
                groundingSection.classList.add('hidden');
            }

            // Vector Retrieval Telemetry
            const retrievalSection = document.getElementById('modalRetrievalSection');
            const retrievalContent = document.getElementById('modalRetrievalContent');
            const retTel = meta.retrieval_sub_stages || meta.lexicon_telemetry || meta.routing_telemetry || {};

            if (retTel.final_score || retTel.total_retrieval_latency_ms || retTel.expanded_query || (Array.isArray(retTel.returned_faq_ids) && retTel.returned_faq_ids.length > 0)) {
                let html = '<div class="space-y-1.5 text-xs">';
                if (retTel.final_score) {
                    html += `<div><strong>Vector Search Score:</strong> <span class="app-badge bg-brand-primary/10 text-brand-primary border border-brand-primary/20 text-2xs">${(retTel.final_score * 100).toFixed(1)}%</span></div>`;
                }
                if (retTel.total_retrieval_latency_ms) {
                    html += `<div><strong>Engine Latency:</strong> <code class="text-text-primary bg-canvas px-1 py-0.5 rounded border border-border">${retTel.total_retrieval_latency_ms} ms</code></div>`;
                }
                if (retTel.expanded_query) {
                    html += `<div><strong>Query Signal:</strong> <code class="text-text-primary bg-canvas px-1 py-0.5 rounded border border-border">${escapeHtml(retTel.expanded_query)}</code></div>`;
                }
                if (Array.isArray(retTel.returned_faq_ids) && retTel.returned_faq_ids.length > 0) {
                    html += `<div><strong>Matched Doc IDs:</strong> ${retTel.returned_faq_ids.map(id => `<span class="app-badge bg-surface-elevated text-text-secondary border border-border text-2xs mr-1">#${escapeHtml(id.substring(0, 8))}</span>`).join('')}</div>`;
                }
                html += '</div>';
                retrievalContent.innerHTML = html;
                retrievalSection.classList.remove('hidden');
            } else {
                retrievalSection.classList.add('hidden');
            }

            // JSON box
            document.getElementById('modalJsonBox').textContent = currentJsonText;

            // Show modal using pure Tailwind modal bridge
            if (window.showTailwindModal) {
                window.showTailwindModal('#traceModal');
            } else {
                $('#traceModal').modal('show');
            }
        }

        function copyModalReplyText() {
            if (currentReplyText) {
                navigator.clipboard.writeText(currentReplyText).then(() => {
                    const label = document.getElementById('copyReplyLabel');
                    label.textContent = 'Copied!';
                    setTimeout(() => label.textContent = 'Copy', 1800);
                });
            }
        }

        function copyModalJsonPayload() {
            if (currentJsonText) {
                navigator.clipboard.writeText(currentJsonText).then(() => {
                    const label = document.getElementById('copyJsonLabel');
                    if (label) {
                        label.textContent = 'Copied!';
                        setTimeout(() => label.textContent = 'Copy JSON', 1800);
                    }
                });
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
@endpush
