@extends('admin.app')

@section('title')
    Observability & Live Telemetry
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
    <style>
        .faq-question-cell {
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
        }

        .kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .kpi-val {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .kpi-lbl {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .dist-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            height: 100%;
        }

        .dist-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .trace-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .pill-knowledge {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .pill-chat {
            background: #f5f3ff;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
        }

        .pill-action {
            background: #f0fdfa;
            color: #0f766e;
            border: 1px solid #99f6e4;
        }

        .pill-ood {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .pill-uncertain {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .gate-confident {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .gate-ambiguous {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .gate-unanswerable {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .gate-bypassed {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        /* Modal Sub-Styles */
        #traceModal .modal-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
        }

        #traceModal .nav-pills .nav-link {
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 6px;
            color: #64748b;
            background: transparent;
            transition: all 0.15s ease;
        }

        .modal-telemetry .nav-pills .nav-link.active {
            background-color: #0f172a;
            color: #ffffff;
        }

        .metric-tile {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
        }

        .metric-tile-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .response-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
        }

        .grounding-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
        }

        .trace-json-box {
            background: #090d16;
            color: #38bdf8;
            padding: 16px;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.6;
            max-height: 420px;
            overflow: auto;
            border: 1px solid #1e293b;
        }

        .modal {
            z-index: 1065 !important;
        }

        .modal-backdrop {
            z-index: 1055 !important;
        }

        .modal-dialog {
            z-index: 1070 !important;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        {{-- Unified Header Card --}}
        <div class="card table-card mb-4">
            <div class="card-header table-header">
                <div class="title-with-breadcrumb">
                    <div class="table-title">Observability & Live Telemetry</div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Observability</li>
                        </ol>
                    </nav>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="badge"
                        style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 6px 12px; font-weight: 500; font-size: 12px;">
                        <i class="ri-pulse-line me-1"></i> Stream Active
                    </span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                        <i class="ri-refresh-line me-1"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        {{-- Top KPI Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="ri-message-3-line"></i>
                    </div>
                    <div>
                        <div class="kpi-lbl">Total AI Replies</div>
                        <div class="kpi-val">{{ number_format($totalAiResponses) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #f0fdf4; color: #16a34a;">
                        <i class="ri-speed-line"></i>
                    </div>
                    <div>
                        <div class="kpi-lbl">P50 Response Time</div>
                        <div class="kpi-val">{{ $p50Latency }} <span
                                style="font-size: 13px; font-weight: 500; color: #64748b;">ms</span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #fef2f2; color: #dc2626;">
                        <i class="ri-timer-line"></i>
                    </div>
                    <div>
                        <div class="kpi-lbl">P95 Tail Latency</div>
                        <div class="kpi-val">{{ $p95Latency }} <span
                                style="font-size: 13px; font-weight: 500; color: #64748b;">ms</span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #f5f3ff; color: #7c3aed;">
                        <i class="ri-chat-voice-line"></i>
                    </div>
                    <div>
                        <div class="kpi-lbl">Active Conversations</div>
                        <div class="kpi-val">{{ number_format($activeConversationsCount) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Distribution Breakdown Cards --}}
        <div class="row g-3 mb-4">
            {{-- Routing Distribution --}}
            <div class="col-lg-6 col-12">
                <div class="dist-card">
                    <div class="dist-header">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Intent Routing Distribution</h6>
                            <small class="text-muted">Sampled across last {{ $sampleCount }} turns</small>
                        </div>
                        <span class="text-muted small fw-medium">{{ array_sum($routeCounts) }} turns</span>
                    </div>

                    @php
                        $totalRoutes = max(1, array_sum($routeCounts));
                    @endphp

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>KNOWLEDGE</strong> (Grounding & Search)</span>
                            <span class="text-muted">{{ $routeCounts['knowledge'] }}
                                ({{ round(($routeCounts['knowledge'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-primary"
                                style="width: {{ ($routeCounts['knowledge'] / $totalRoutes) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>CHAT</strong> (Conversational)</span>
                            <span class="text-muted">{{ $routeCounts['chat'] }}
                                ({{ round(($routeCounts['chat'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar"
                                style="width: {{ ($routeCounts['chat'] / $totalRoutes) * 100 }}%; background-color: #6d28d9;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>ACTION</strong> (Orders, Returns & Tickets)</span>
                            <span class="text-muted">{{ $routeCounts['action'] }}
                                ({{ round(($routeCounts['action'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar"
                                style="width: {{ ($routeCounts['action'] / $totalRoutes) * 100 }}%; background-color: #0f766e;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>OOD</strong> (Out-of-Domain)</span>
                            <span class="text-muted">{{ $routeCounts['ood'] }}
                                ({{ round(($routeCounts['ood'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger"
                                style="width: {{ ($routeCounts['ood'] / $totalRoutes) * 100 }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>UNCERTAIN</strong> (Clarification)</span>
                            <span class="text-muted">{{ $routeCounts['uncertain'] }}
                                ({{ round(($routeCounts['uncertain'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-warning"
                                style="width: {{ ($routeCounts['uncertain'] / $totalRoutes) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Gate Decisions --}}
            <div class="col-lg-6 col-12">
                <div class="dist-card">
                    <div class="dist-header">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">Semantic Answerability Gate Distribution</h6>
                            <small class="text-muted">Grounded confidence verification outcomes</small>
                        </div>
                        <span class="text-muted small fw-medium">{{ array_sum($gateCounts) }} gates</span>
                    </div>

                    @php
                        $totalGates = max(1, array_sum($gateCounts));
                    @endphp

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>CONFIDENT</strong> (Direct Grounded Match)</span>
                            <span class="text-muted">{{ $gateCounts['confident'] }}
                                ({{ round(($gateCounts['confident'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-success"
                                style="width: {{ ($gateCounts['confident'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>AMBIGUOUS</strong> (Multiple Options)</span>
                            <span class="text-muted">{{ $gateCounts['ambiguous'] }}
                                ({{ round(($gateCounts['ambiguous'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-warning"
                                style="width: {{ ($gateCounts['ambiguous'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>UNANSWERABLE</strong> (Fallback)</span>
                            <span class="text-muted">{{ $gateCounts['unanswerable'] }}
                                ({{ round(($gateCounts['unanswerable'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-danger"
                                style="width: {{ ($gateCounts['unanswerable'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong>BYPASSED</strong> (Direct Chat / Action)</span>
                            <span class="text-muted">{{ $gateCounts['bypassed'] }}
                                ({{ round(($gateCounts['bypassed'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-secondary"
                                style="width: {{ ($gateCounts['bypassed'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Telemetry Traces Table --}}
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Recent Turn Traces</div>
                        </div>
                        <span class="text-muted small">Live Inbound/Outbound Exchanges</span>
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100" id="data-table" style="min-width: 950px;">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 60px;">#</th>
                                    <th scope="col" style="width: 100px;">Time</th>
                                    <th scope="col" style="width: 180px;">Customer / Channel</th>
                                    <th scope="col">AI Outbound Response</th>
                                    <th scope="col" style="width: 110px;">Route</th>
                                    <th scope="col" style="width: 120px;">Gate Decision</th>
                                    <th scope="col" style="width: 90px;">Latency</th>
                                    <th scope="col" style="width: 70px;" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($messages as $index => $msg)
                                    @php
                                        $meta = $msg->metadata ?? [];
                                        $route = strtolower($meta['route'] ?? $meta['router_type'] ?? 'knowledge');
                                        $gate = strtolower($meta['answerability_decision']['status'] ?? $meta['answerability'] ?? 'none');
                                        $latency = $meta['total_time_ms'] ?? $meta['routing_telemetry']['total_e2e_ms'] ?? null;
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <span class="text-dark fw-medium">{{ $msg->created_at->format('H:i:s') }}</span>
                                            <small class="text-muted d-block">{{ $msg->created_at->format('M d') }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('conversations.show', $msg->conversation_id) }}"
                                                class="fw-medium text-decoration-none h-auto w-auto justify-content-start">
                                                {{ $msg->conversation?->customer_name ?? '#' . substr($msg->conversation_id, 0, 8) }}
                                            </a>
                                            <small
                                                class="text-muted d-block">{{ $msg->conversation?->channelAccount?->channel?->name ?? 'Direct Chat' }}</small>
                                        </td>
                                        <td>
                                            <div class="faq-question-cell" title="{{ $msg->body }}">
                                                {{ $msg->body }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="trace-pill pill-{{ $route }}">{{ $route }}</span>
                                        </td>
                                        <td>
                                            @if ($gate === 'confident')
                                                <span class="trace-pill gate-confident"><i
                                                        class="ri-checkbox-circle-line me-1"></i>Confident</span>
                                            @elseif ($gate === 'ambiguous')
                                                <span class="trace-pill gate-ambiguous"><i
                                                        class="ri-question-line me-1"></i>Ambiguous</span>
                                            @elseif ($gate === 'unanswerable')
                                                <span class="trace-pill gate-unanswerable"><i
                                                        class="ri-forbid-line me-1"></i>Unanswerable</span>
                                            @else
                                                <span class="trace-pill gate-bypassed">Bypassed</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($latency)
                                                <span class="badge-subtle badge-subtle-secondary">
                                                    {{ round((float) $latency) }} ms
                                                </span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary p-1"
                                                title="Inspect Trace"
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
    </div>

    {{-- Trace Inspector Modal --}}
    <div class="modal fade" id="traceModal" tabindex="-1" aria-labelledby="traceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div
                            style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                            <i class="ri-pulse-line fs-5"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="modal-title fw-bold mb-0 text-dark" id="traceModalLabel">Turn Diagnostic
                                    Telemetry</h6>
                                <span class="badge"
                                    style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-family: monospace; font-size: 11px;">#<span
                                        id="modalMsgId">-</span></span>
                            </div>
                            <small class="text-muted">Live pipeline routing, answerability verification, and token
                                metrics</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    {{-- 4 Metric Tiles Strip --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-3 col-6">
                            <div class="metric-tile">
                                <div class="metric-tile-label">Route Intent</div>
                                <div id="modalRouteContainer">
                                    <span class="trace-pill pill-knowledge" id="modalRoute">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="metric-tile">
                                <div class="metric-tile-label">Gate Decision</div>
                                <div id="modalGateContainer">
                                    <span class="trace-pill gate-confident" id="modalGate">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="metric-tile">
                                <div class="metric-tile-label">Provider & Model</div>
                                <strong id="modalProvider" class="text-dark small d-block text-truncate" title="">-</strong>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="metric-tile">
                                <div class="metric-tile-label">Execution Latency</div>
                                <strong id="modalLatency" class="text-dark small d-block">-</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Navigation Tabs --}}
                    <ul class="nav nav-pills mb-3" id="traceModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pill-overview-tab" data-bs-toggle="pill"
                                data-bs-target="#pill-overview" type="button" role="tab">
                                <i class="ri-dashboard-line me-1"></i> Structured Overview
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pill-json-tab" data-bs-toggle="pill" data-bs-target="#pill-json"
                                type="button" role="tab">
                                <i class="ri-code-s-slash-line me-1"></i> Raw Diagnostic JSON
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="traceModalTabsContent">
                        {{-- Tab 1: Overview --}}
                        <div class="tab-pane fade show active" id="pill-overview" role="tabpanel">
                            {{-- Outbound AI Reply Card --}}
                            <div class="response-card mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-semibold text-uppercase text-muted"
                                        style="letter-spacing: 0.5px;">AI Generated Outbound Response</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                        id="btnCopyReply" onclick="copyModalReplyText()">
                                        <i class="ri-file-copy-line me-1"></i> <span id="copyReplyLabel">Copy</span>
                                    </button>
                                </div>
                                <div id="modalReplyText" class="text-dark small"
                                    style="white-space: pre-wrap; line-height: 1.6;"></div>
                            </div>

                            {{-- Grounding & Knowledge Context --}}
                            <div id="modalGroundingSection" class="grounding-card mb-3 d-none">
                                <div class="small fw-semibold text-uppercase text-muted mb-2"
                                    style="letter-spacing: 0.5px;">
                                    <i class="ri-book-read-line me-1 text-primary"></i> Knowledge Grounding & Citations
                                </div>
                                <div id="modalGroundingContent" class="small text-dark"></div>
                            </div>

                            {{-- Linguistic & Lexicon Signals --}}
                            <div id="modalLexiconSection" class="grounding-card d-none">
                                <div class="small fw-semibold text-uppercase text-muted mb-2"
                                    style="letter-spacing: 0.5px;">
                                    <i class="ri-text me-1 text-success"></i> Linguistic & Lexicon Telemetry
                                </div>
                                <div id="modalLexiconContent" class="small text-dark"></div>
                            </div>
                        </div>

                        {{-- Tab 2: Raw JSON --}}
                        <div class="tab-pane fade" id="pill-json" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-muted font-monospace">Payload Format: JSON</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnCopyJson"
                                    onclick="copyModalJsonPayload()">
                                    <i class="ri-file-copy-line me-1"></i> <span id="copyJsonLabel">Copy JSON</span>
                                </button>
                            </div>
                            <pre class="trace-json-box mb-0" id="modalJsonBox"></pre>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-2 px-4 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyModalJsonPayload()">
                        <i class="ri-code-box-line me-1"></i> Copy Full Payload
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('custom-scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script>
        let currentReplyText = '';
        let currentJsonText = '';

        document.addEventListener('DOMContentLoaded', function () {
            // Relocate modal to body
            var traceModalEl = document.getElementById('traceModal');
            if (traceModalEl && traceModalEl.parentElement !== document.body) {
                document.body.appendChild(traceModalEl);
            }

            if (typeof $.fn.DataTable !== 'undefined') {
                $('#data-table').DataTable({
                    pageLength: 20,
                    order: [[0, 'asc']]
                });
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
            routeEl.className = 'trace-pill pill-' + route;

            // Gate decision pill
            const gate = String(meta.answerability_decision ? meta.answerability_decision.status : (meta.answerability || 'bypassed')).toLowerCase();
            const gateEl = document.getElementById('modalGate');
            gateEl.textContent = gate.toUpperCase();
            gateEl.className = 'trace-pill gate-' + gate;

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
                latencyEl.innerHTML = `<span class="${ms > 1500 ? 'text-danger' : (ms > 700 ? 'text-warning' : 'text-success')}">${ms} ms</span>`;
            } else {
                latencyEl.textContent = '—';
            }

            // Grounding details
            const groundingSection = document.getElementById('modalGroundingSection');
            const groundingContent = document.getElementById('modalGroundingContent');
            const decision = meta.answerability_decision || {};
            const hits = meta.knowledge_hits || meta.retrieval_metadata || [];

            if (decision.status || (Array.isArray(hits) && hits.length > 0) || decision.target_title) {
                let html = '<div class="d-flex flex-column gap-2">';
                if (decision.target_title) {
                    html += `<div><strong>Matched Document:</strong> <span class="text-primary">${escapeHtml(decision.target_title)}</span></div>`;
                }
                if (decision.best_score) {
                    html += `<div><strong>Confidence Score:</strong> <span class="badge bg-secondary">${Math.round(decision.best_score * 100)}%</span></div>`;
                }
                if (decision.reason) {
                    html += `<div><strong>Gate Reason:</strong> <span class="text-muted">${escapeHtml(decision.reason)}</span></div>`;
                }
                html += '</div>';
                groundingContent.innerHTML = html;
                groundingSection.classList.remove('d-none');
            } else {
                groundingSection.classList.add('d-none');
            }

            // Lexicon details
            const lexiconSection = document.getElementById('modalLexiconSection');
            const lexiconContent = document.getElementById('modalLexiconContent');
            const lexTel = meta.lexicon_telemetry || meta.linguistic_telemetry || {};

            if (lexTel.canonical_concepts || lexTel.expansion_triggered || lexTel.reranker_reason) {
                let html = '<div class="d-flex flex-column gap-2">';
                if (Array.isArray(lexTel.canonical_concepts) && lexTel.canonical_concepts.length > 0) {
                    html += '<div><strong>Canonical Concepts:</strong> ';
                    lexTel.canonical_concepts.forEach(c => {
                        html += `<span class="badge bg-light text-dark border me-1">${escapeHtml(c)}</span>`;
                    });
                    html += '</div>';
                }
                if (lexTel.expanded_query) {
                    html += `<div><strong>Expanded Query:</strong> <code>${escapeHtml(lexTel.expanded_query)}</code></div>`;
                }
                if (lexTel.reranker_reason) {
                    html += `<div><strong>Reranker Decision:</strong> <span class="text-info">${escapeHtml(lexTel.reranker_reason)}</span></div>`;
                }
                html += '</div>';
                lexiconContent.innerHTML = html;
                lexiconSection.classList.remove('d-none');
            } else {
                lexiconSection.classList.add('d-none');
            }

            // JSON box
            document.getElementById('modalJsonBox').textContent = currentJsonText;

            // Reset tab to overview
            const firstTab = document.getElementById('pill-overview-tab');
            if (firstTab && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(firstTab).show();
            }

            // Show modal
            var modalEl = document.getElementById('traceModal');
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
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