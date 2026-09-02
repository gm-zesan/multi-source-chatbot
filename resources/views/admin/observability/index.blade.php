@extends('admin.app')

@section('title')
    Observability & Telemetry Dashboard
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
    <style>
        .faq-question-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        }

        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .kpi-val {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .kpi-lbl {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dist-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
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
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .pill-knowledge {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .pill-chat {
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

        .pill-action {
            background: #ede9fe;
            color: #5b21b6;
            border: 1px solid #ddd6fe;
        }

        .pill-ood {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .pill-uncertain {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }

        .gate-confident {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .gate-ambiguous {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }

        .gate-unanswerable {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .gate-bypassed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .trace-json-box {
            background: #0f172a;
            color: #38bdf8;
            padding: 14px;
            border-radius: 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            max-height: 380px;
            overflow-y: auto;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        {{-- Header / Breadcrumb --}}
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="color: #0f172a;">
                    <i class="ri-pulse-line text-primary me-2"></i>Observability & Live Telemetry
                </h4>
                <p class="text-muted small mb-0">Production system traces, HybridRouter classification distributions, and
                    Tier 4 Answerability Gate decisions.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge"
                    style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 6px 12px; font-size: 12px;">
                    <i class="ri-radio-button-fill me-1 text-success"></i> Telemetry Live Active
                </span>
                <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                    <i class="ri-refresh-line me-1"></i> Refresh
                </button>
            </div>
        </div>

        {{-- Top KPI Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="ri-robot-2-line"></i>
                    </div>
                    <div>
                        <div class="kpi-lbl">Total AI Replies</div>
                        <div class="kpi-val">{{ number_format($totalAiResponses) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="ri-speed-up-line"></i>
                    </div>
                    <div>
                        <div class="kpi-lbl">P50 Response Time</div>
                        <div class="kpi-val">{{ $p50Latency }} <span
                                style="font-size: 14px; font-weight: 500; color: #64748b;">ms</span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #fef2f2; color: #dc2626;">
                        <i class="ri-timer-flash-line"></i>
                    </div>
                    <div>
                        <div class="kpi-lbl">P95 Tail Latency</div>
                        <div class="kpi-val">{{ $p95Latency }} <span
                                style="font-size: 14px; font-weight: 500; color: #64748b;">ms</span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="kpi-card d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background: #faf5ff; color: #7c3aed;">
                        <i class="ri-chat-3-line"></i>
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
            {{-- Routing Capabilities --}}
            <div class="col-lg-6 col-12">
                <div class="dist-card">
                    <div class="dist-header">
                        <div>
                            <h6 class="fw-bold mb-0"><i class="ri-compass-3-line text-primary me-1"></i> Intent Routing Distribution</h6>
                            <small class="text-muted">Sampled across last {{ $sampleCount }} turns</small>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary">{{ array_sum($routeCounts) }} turns</span>
                    </div>

                    @php
                        $totalRoutes = max(1, array_sum($routeCounts));
                    @endphp

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-primary">KNOWLEDGE</strong> (Grounding & Search)</span>
                            <span>{{ $routeCounts['knowledge'] }}
                                ({{ round(($routeCounts['knowledge'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary"
                                style="width: {{ ($routeCounts['knowledge'] / $totalRoutes) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong style="color: #4f46e5;">CHAT</strong> (Conversational & Greetings)</span>
                            <span>{{ $routeCounts['chat'] }}
                                ({{ round(($routeCounts['chat'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar"
                                style="width: {{ ($routeCounts['chat'] / $totalRoutes) * 100 }}%; background-color: #4f46e5;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong style="color: #7c3aed;">ACTION</strong> (Orders, Returns & Tickets)</span>
                            <span>{{ $routeCounts['action'] }}
                                ({{ round(($routeCounts['action'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar"
                                style="width: {{ ($routeCounts['action'] / $totalRoutes) * 100 }}%; background-color: #7c3aed;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-danger">OOD</strong> (Safe Out-of-Domain Abstention)</span>
                            <span>{{ $routeCounts['ood'] }}
                                ({{ round(($routeCounts['ood'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger"
                                style="width: {{ ($routeCounts['ood'] / $totalRoutes) * 100 }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-warning text-dark">UNCERTAIN</strong> (Dynamic Clarification)</span>
                            <span>{{ $routeCounts['uncertain'] }}
                                ({{ round(($routeCounts['uncertain'] / $totalRoutes) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning"
                                style="width: {{ ($routeCounts['uncertain'] / $totalRoutes) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Answerability Gate Decisions --}}
            <div class="col-lg-6 col-12">
                <div class="dist-card">
                    <div class="dist-header">
                        <div>
                            <h6 class="fw-bold mb-0"><i class="ri-shield-check-line text-success me-1"></i> Semantic Answerability Gate Distribution</h6>
                            <small class="text-muted">Tier 4 hallucination prevention outcomes</small>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary">{{ array_sum($gateCounts) }} gates</span>
                    </div>

                    @php
                        $totalGates = max(1, array_sum($gateCounts));
                    @endphp

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-success">CONFIDENT</strong> (Direct KB Grounded Attribution)</span>
                            <span>{{ $gateCounts['confident'] }}
                                ({{ round(($gateCounts['confident'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success"
                                style="width: {{ ($gateCounts['confident'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-warning text-dark">AMBIGUOUS</strong> (Clarification &
                                Multi-Option)</span>
                            <span>{{ $gateCounts['ambiguous'] }}
                                ({{ round(($gateCounts['ambiguous'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning"
                                style="width: {{ ($gateCounts['ambiguous'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-danger">UNANSWERABLE</strong> (OOD / Safety Block Fallback)</span>
                            <span>{{ $gateCounts['unanswerable'] }}
                                ({{ round(($gateCounts['unanswerable'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger"
                                style="width: {{ ($gateCounts['unanswerable'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><strong class="text-secondary">BYPASSED</strong> (Chat / Action Path)</span>
                            <span>{{ $gateCounts['bypassed'] }}
                                ({{ round(($gateCounts['bypassed'] / $totalGates) * 100, 1) }}%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-secondary"
                                style="width: {{ ($gateCounts['bypassed'] / $totalGates) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- EXACT FAQ TABLE DESIGN --}}
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Observability & Telemetry Traces</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Observability & Telemetry</li>
                                </ol>
                            </nav>
                        </div>
                        <span class="text-muted small">Real-time Turn Traces</span>
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100" id="data-table" style="min-width: 950px;">
                            <thead>
                                <tr>
                                    <th scope="col">SL NO</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Customer / Channel</th>
                                    <th scope="col">AI Outbound Response</th>
                                    <th scope="col">Route</th>
                                    <th scope="col">Answerability Gate</th>
                                    <th scope="col">Latency</th>
                                    <th scope="col">Action</th>
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
                                            <span class="text-dark fw-bold">{{ $msg->created_at->format('H:i:s') }}</span>
                                            <small class="text-muted d-block">{{ $msg->created_at->format('M d, Y') }}</small>
                                        </td>
                                        <td>
                                            <a class="h-auto w-auto justify-content-start"
                                                href="{{ route('conversations.show', $msg->conversation_id) }}"
                                                class="fw-bold text-decoration-none">
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
                                                <span class="trace-pill gate-bypassed">N/A</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($latency)
                                                <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 500;">
                                                    {{ round((float) $latency) }} ms
                                                </span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="action-btn d-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    style="padding: 3px 7px;" title="Inspect Trace"
                                                    onclick="inspectTrace({{ json_encode($msg->id) }}, {{ json_encode($meta) }}, {{ json_encode($msg->body) }})">
                                                    <i class="ri-scan-line"></i>
                                                </button>
                                            </div>
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
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h6 class="modal-title mb-0" id="traceModalLabel">
                        <i class="ri-file-search-line me-1 text-info"></i> Pipeline Telemetry Trace (<span
                            id="modalMsgId"></span>)
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Generated Outbound Text</label>
                        <div class="p-3"
                            style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;"
                            id="modalReplyText">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4 col-12">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Route Classifier</small>
                                <strong id="modalRoute" class="text-primary">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Answerability Decision</small>
                                <strong id="modalGate" class="text-success">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4 col-12">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Provider / Model</small>
                                <strong id="modalProvider" class="text-secondary">-</strong>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label text-muted small fw-bold text-uppercase">Full JSON Telemetry Trace</label>
                        <pre class="trace-json-box mb-0" id="modalJsonBox"></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('custom-scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

    <script type="text/javascript">
        $(document).ready(function () {
            $('#data-table').DataTable({
                responsive: true,
                fixedHeader: true,
                "pageLength": 20,
                "lengthMenu": [20, 50, 100, 200],
                order: [
                    [0, 'asc']
                ]
            });
        });

        function inspectTrace(msgId, meta, replyText) {
            document.getElementById('modalMsgId').textContent = '#' + msgId;
            document.getElementById('modalReplyText').textContent = replyText || 'No reply text';

            document.getElementById('modalRoute').textContent = (meta.route || meta.router_type || 'knowledge').toUpperCase();
            const gate = (meta.answerability_decision?.status || meta.answerability || 'N/A').toUpperCase();
            document.getElementById('modalGate').textContent = gate;

            const provider = meta.provider || 'deepseek';
            const model = meta.model || 'deepseek-chat';
            document.getElementById('modalProvider').textContent = `${provider} (${model})`;

            document.getElementById('modalJsonBox').textContent = JSON.stringify(meta, null, 2);

            const modal = new bootstrap.Modal(document.getElementById('traceModal'));
            modal.show();
        }
    </script>
@endpush