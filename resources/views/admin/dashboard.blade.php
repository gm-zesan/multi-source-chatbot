@extends('admin.app')

@section('title')
    Dashboard
@endsection

@push('custom-style')
    <style>
        .dashboard-stat-card {
            transition: transform 0.15s ease;
        }

        .dashboard-stat-card:hover {
            transform: translateY(-2px);
        }

        .dashboard-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .dashboard-stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .dashboard-summary-icon {
            font-size: 2rem;
            opacity: 0.25;
        }

        .queue-mini-card {
            background: #f8f9fc;
            border-radius: 8px;
            padding: 1rem;
            height: 100%;
        }

        .queue-mini-card .queue-label {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .queue-mini-card .queue-value {
            font-size: 1.25rem;
            font-weight: 600;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        {{-- Summary Cards --}}
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card table-card dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="dashboard-stat-value text-primary">
                                    {{ $metrics['conversation']['total_conversations'] }}</div>
                                <div class="dashboard-stat-label">Conversations</div>
                                <small class="text-muted">{{ $metrics['conversation']['open_conversations'] }} open</small>
                            </div>
                            <i class="ri-message-3-line dashboard-summary-icon text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card table-card dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="dashboard-stat-value text-success">
                                    {{ $metrics['conversation']['total_messages'] }}</div>
                                <div class="dashboard-stat-label">Messages</div>
                                <small class="text-muted">{{ $metrics['conversation']['today_messages'] }} today</small>
                            </div>
                            <i class="ri-chat-1-line dashboard-summary-icon text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card table-card dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="dashboard-stat-value text-info">{{ $metrics['faq']['total_faqs'] }}</div>
                                <div class="dashboard-stat-label">Knowledge Base</div>
                                <small class="text-muted">{{ $metrics['faq']['active_faqs'] }} active FAQs</small>
                            </div>
                            <i class="ri-book-open-line dashboard-summary-icon text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card table-card dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="dashboard-stat-value text-warning">{{ $metrics['unanswered']['pending'] }}</div>
                                <div class="dashboard-stat-label">Unanswered</div>
                                <small class="text-muted">{{ $metrics['unanswered']['total'] }} total questions</small>
                            </div>
                            <i class="ri-question-line dashboard-summary-icon text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Queue Worker Status --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">
                                <i class="ri-hard-drive-3-line me-1"></i> Queue Workers
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="queue-mini-card">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-semibold">Messenger</div>
                                            <div class="queue-label">Inbound messages</div>
                                        </div>
                                        <i class="ri-message-3-line fs-3 text-primary opacity-25"></i>
                                    </div>
                                    <div class="d-flex gap-4">
                                        <div>
                                            <div class="queue-value">{{ $metrics['queue']['pending']['messenger'] }}</div>
                                            <div class="queue-label">Pending</div>
                                        </div>
                                        <div>
                                            <div class="queue-value text-danger">
                                                {{ $metrics['queue']['failed']['messenger'] }}</div>
                                            <div class="queue-label text-danger">Failed</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="queue-mini-card">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-semibold">CRM</div>
                                            <div class="queue-label">Entity extraction</div>
                                        </div>
                                        <i class="ri-user-search-line fs-3 text-success opacity-25"></i>
                                    </div>
                                    <div class="d-flex gap-4">
                                        <div>
                                            <div class="queue-value">{{ $metrics['queue']['pending']['crm'] }}</div>
                                            <div class="queue-label">Pending</div>
                                        </div>
                                        <div>
                                            <div class="queue-value text-danger">{{ $metrics['queue']['failed']['crm'] }}
                                            </div>
                                            <div class="queue-label text-danger">Failed</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="queue-mini-card">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <div class="fw-semibold">FAQ Engine</div>
                                            <div class="queue-label">Auto reply</div>
                                        </div>
                                        <i class="ri-question-answer-line fs-3 text-info opacity-25"></i>
                                    </div>
                                    <div class="d-flex gap-4">
                                        <div>
                                            <div class="queue-value">{{ $metrics['queue']['pending']['faq'] }}</div>
                                            <div class="queue-label">Pending</div>
                                        </div>
                                        <div>
                                            <div class="queue-value text-danger">{{ $metrics['queue']['failed']['faq'] }}
                                            </div>
                                            <div class="queue-label text-danger">Failed</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="ri-timer-line me-1"></i>
                                Total pending: <strong>{{ $metrics['queue']['pending']['total'] }}</strong>
                                &nbsp;&middot;&nbsp;
                                <i class="ri-error-warning-line me-1"></i>
                                Total failed: <strong
                                    class="text-danger">{{ $metrics['queue']['failed']['total'] }}</strong>
                            </small>
                            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="ri-refresh-line me-1"></i>Refresh
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conversations & Messages --}}
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">
                                <i class="ri-chat-3-line me-1"></i> Conversations
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value text-primary">
                                    {{ $metrics['conversation']['total_conversations'] }}</div>
                                <div class="dashboard-stat-label">Total</div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value text-success">
                                    {{ $metrics['conversation']['open_conversations'] }}</div>
                                <div class="dashboard-stat-label">Open</div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value text-info">
                                    {{ $metrics['conversation']['inbound_messages'] }}</div>
                                <div class="dashboard-stat-label">Inbound</div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value text-secondary">
                                    {{ $metrics['conversation']['outbound_messages'] }}</div>
                                <div class="dashboard-stat-label">Outbound</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">
                                <i class="ri-bar-chart-2-line me-1"></i> Message Activity
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value">{{ $metrics['conversation']['total_messages'] }}</div>
                                <div class="dashboard-stat-label">All Messages</div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value text-primary">
                                    {{ $metrics['conversation']['today_messages'] }}</div>
                                <div class="dashboard-stat-label">Today</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Knowledge Base & Unanswered --}}
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">
                                <i class="ri-book-open-line me-1"></i> Knowledge Base
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value text-info">{{ $metrics['faq']['total_faqs'] }}</div>
                                <div class="dashboard-stat-label">Total FAQs</div>
                                <small class="text-muted">{{ $metrics['faq']['active_faqs'] }} active</small>
                            </div>
                            <div class="col-6 text-center">
                                <div class="dashboard-stat-value">{{ number_format($metrics['faq']['total_hits']) }}</div>
                                <div class="dashboard-stat-label">Answer Views</div>
                            </div>
                        </div>
                        @if ($metrics['faq']['top_faq'])
                            <div class="mt-3 p-3" style="background: #f8f9fc; border-radius: 8px;">
                                <small class="text-muted">
                                    <i class="ri-fire-line me-1 text-danger"></i>Most viewed:
                                </small>
                                <div class="mt-1 d-flex justify-content-between align-items-center">
                                    <span>{{ Str::limit($metrics['faq']['top_faq']->question, 60) }}</span>
                                    <span class="badge bg-info ms-2 flex-shrink-0">
                                        <i
                                            class="ri-eye-line me-1"></i>{{ number_format($metrics['faq']['top_faq']->hit_count) }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">
                                <i class="ri-question-line me-1"></i> Unanswered Questions
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-3 text-center">
                                <div class="dashboard-stat-value text-warning">{{ $metrics['unanswered']['pending'] }}
                                </div>
                                <div class="dashboard-stat-label">Pending</div>
                            </div>
                            <div class="col-3 text-center">
                                <div class="dashboard-stat-value text-info">{{ $metrics['unanswered']['reviewed'] }}</div>
                                <div class="dashboard-stat-label">Reviewed</div>
                            </div>
                            <div class="col-3 text-center">
                                <div class="dashboard-stat-value text-success">{{ $metrics['unanswered']['answered'] }}
                                </div>
                                <div class="dashboard-stat-label">Answered</div>
                            </div>
                            <div class="col-3 text-center">
                                <div class="dashboard-stat-value text-secondary">{{ $metrics['unanswered']['dismissed'] }}
                                </div>
                                <div class="dashboard-stat-label">Dismissed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('custom-scripts')
    @if (Session::has('success'))
        <script>
            swal("success", "{{ Session::get('success') }}", "success", {
                timer: 1000,
                button: false,
            });
        </script>
    @endif
@endpush
