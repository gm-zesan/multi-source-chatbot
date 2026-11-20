@extends('admin.app')

@section('title')
    Dashboard
@endsection

@push('custom-style')
<style>
    .dash-hero-card {
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 60%, #f0fdf4 100%);
        border: 1px solid #dbeafe;
        border-radius: 14px;
        padding: 1.5rem 1.75rem;
    }
    .stat-card-modern {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }
    .stat-card-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.03);
        border-color: #cbd5e1;
    }
    .stat-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .stat-val {
        font-size: 1.85rem;
        font-weight: 700;
        line-height: 1.2;
        color: #0f172a;
    }
    .stat-label-text {
        font-size: 0.82rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }
    .queue-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.15rem;
        transition: all 0.2s ease;
    }
    .queue-box:hover {
        background: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-2">
    {{-- Hero Welcome Banner --}}
    <div class="dash-hero-card mb-4 shadow-sm">
        <div class="row align-items-center g-3">
            <div class="col-lg-8 col-12">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge badge-soft-primary px-3 py-1.5 rounded-pill small fw-semibold">
                        <i class="ri-radar-line me-1"></i> System Operational
                    </span>
                    <span class="text-muted small">Multi-Tenant AI Business Platform</span>
                </div>
                <h4 class="fw-bold text-dark mb-1">
                    Welcome back, {{ Auth::user()->name }}! 👋
                </h4>
                <p class="text-muted small mb-0">
                    Real-time omnichannel customer support queue, automated FAQ vector engine, and conversational memory diagnostics.
                </p>
            </div>
            <div class="col-lg-4 col-12 text-lg-end">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="{{ route('analytics.index') }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="ri-bar-chart-2-line"></i> BI Analytics
                    </a>
                    <a href="{{ route('widget.manage') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="ri-message-3-line"></i> Chat Widget
                    </a>
                    <a href="{{ route('simulator.index') }}" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                        <i class="ri-robot-2-line"></i> Chat Simulator
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Top 4 KPI Stat Cards --}}
    <div class="row g-3 mb-4">
        {{-- Conversations Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label-text">Total Conversations</div>
                        <div class="stat-val mt-1">{{ number_format($metrics['conversation']['total_conversations']) }}</div>
                    </div>
                    <div class="icon-box-primary">
                        <i class="ri-message-3-line"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                    <span class="badge badge-soft-success">
                        <i class="ri-checkbox-circle-line me-0.5"></i> {{ $metrics['conversation']['open_conversations'] }} Open
                    </span>
                    <small class="text-muted">Active support threads</small>
                </div>
            </div>
        </div>

        {{-- Messages Activity Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label-text">All Messages</div>
                        <div class="stat-val mt-1">{{ number_format($metrics['conversation']['total_messages']) }}</div>
                    </div>
                    <div class="icon-box-success">
                        <i class="ri-chat-check-line"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                    <span class="badge badge-soft-primary">
                        +{{ $metrics['conversation']['today_messages'] }} today
                    </span>
                    <small class="text-muted">Inbound & outbound</small>
                </div>
            </div>
        </div>

        {{-- Knowledge Base Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label-text">Knowledge Base</div>
                        <div class="stat-val mt-1">{{ number_format($metrics['faq']['total_faqs']) }}</div>
                    </div>
                    <div class="icon-box-info">
                        <i class="ri-book-open-line"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                    <span class="badge badge-soft-info">
                        {{ $metrics['faq']['active_faqs'] }} Active FAQs
                    </span>
                    <small class="text-muted">{{ number_format($metrics['faq']['total_hits']) }} views</small>
                </div>
            </div>
        </div>

        {{-- Unanswered Inquiries Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="stat-label-text">Pending Questions</div>
                        <div class="stat-val mt-1 text-warning">{{ number_format($metrics['unanswered']['pending']) }}</div>
                    </div>
                    <div class="icon-box-warning">
                        <i class="ri-question-mark"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                    <span class="badge badge-soft-warning">
                        Requires Review
                    </span>
                    <small class="text-muted">{{ $metrics['unanswered']['total'] }} total logged</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Queue Workers Ingestion Health Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="icon-box-primary" style="width: 36px; height: 36px; font-size: 1.1rem;">
                    <i class="ri-hard-drive-3-line"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Background Queues & Real-Time Processing</h6>
                    <small class="text-muted">High-concurrency workers handling webhooks, CRM extraction, and vector index sync</small>
                </div>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="ri-refresh-line"></i> Refresh Queues
            </a>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4 col-12">
                    <div class="queue-box">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark small"><i class="ri-messenger-line text-primary me-1"></i> Messenger / Omnichannel</span>
                            <span class="badge badge-soft-primary small">Inbound Hook</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-end mt-3">
                            <div>
                                <small class="text-muted d-block">Pending Jobs</small>
                                <h4 class="fw-bold text-dark mb-0">{{ $metrics['queue']['pending']['messenger'] }}</h4>
                            </div>
                            <div class="text-end">
                                <small class="text-danger d-block">Failed Jobs</small>
                                <span class="fw-bold text-danger">{{ $metrics['queue']['failed']['messenger'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="queue-box">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark small"><i class="ri-user-search-line text-success me-1"></i> CRM Entity Extractor</span>
                            <span class="badge badge-soft-success small">Contact Sync</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-end mt-3">
                            <div>
                                <small class="text-muted d-block">Pending Jobs</small>
                                <h4 class="fw-bold text-dark mb-0">{{ $metrics['queue']['pending']['crm'] }}</h4>
                            </div>
                            <div class="text-end">
                                <small class="text-danger d-block">Failed Jobs</small>
                                <span class="fw-bold text-danger">{{ $metrics['queue']['failed']['crm'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="queue-box">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark small"><i class="ri-brain-line text-info me-1"></i> FAQ Vector Indexer</span>
                            <span class="badge badge-soft-info small">Typesense Sync</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-end mt-3">
                            <div>
                                <small class="text-muted d-block">Pending Jobs</small>
                                <h4 class="fw-bold text-dark mb-0">{{ $metrics['queue']['pending']['faq'] }}</h4>
                            </div>
                            <div class="text-end">
                                <small class="text-danger d-block">Failed Jobs</small>
                                <span class="fw-bold text-danger">{{ $metrics['queue']['failed']['faq'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top small text-muted">
                <div>
                    <i class="ri-check-line text-success me-1"></i> Overall Queue Status: 
                    <strong class="text-dark">{{ $metrics['queue']['pending']['total'] }} pending</strong>
                    &middot;
                    <span class="{{ $metrics['queue']['failed']['total'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                        {{ $metrics['queue']['failed']['total'] }} failed
                    </span>
                </div>
                <code>queues: faq,default,documents,memory,telemetry</code>
            </div>
        </div>
    </div>

    {{-- Bottom Two Columns: Knowledge Insights & Unanswered Funnel --}}
    <div class="row g-3">
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="ri-book-read-line me-1 text-primary"></i> Knowledge Base & Insights</h6>
                    <a href="{{ route('faqs.index') }}" class="btn btn-sm btn-link text-primary text-decoration-none p-0">Manage FAQs &rarr;</a>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-6 p-3 bg-light rounded-3">
                            <h3 class="fw-bold text-primary mb-0">{{ $metrics['faq']['total_faqs'] }}</h3>
                            <small class="text-muted">Total Knowledge Items</small>
                        </div>
                        <div class="col-6 p-3 bg-light rounded-3">
                            <h3 class="fw-bold text-info mb-0">{{ number_format($metrics['faq']['total_hits']) }}</h3>
                            <small class="text-muted">Customer Answer Views</small>
                        </div>
                    </div>
                    @if ($metrics['faq']['top_faq'])
                        <div class="p-3 rounded-3" style="background-color: #eff6ff; border: 1px solid #bfdbfe;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-primary fw-bold"><i class="ri-fire-fill text-danger me-1"></i> Most Frequently Asked FAQ</small>
                                <span class="badge bg-primary text-white rounded-pill px-2 py-0.5 small">{{ number_format($metrics['faq']['top_faq']->hit_count) }} views</span>
                            </div>
                            <div class="text-dark small fw-medium mt-1">
                                "{{ Str::limit($metrics['faq']['top_faq']->question, 85) }}"
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="ri-question-answer-line me-1 text-warning"></i> Unanswered Questions Funnel</h6>
                    <a href="{{ route('faqs.index') }}" class="btn btn-sm btn-link text-primary text-decoration-none p-0">Manage FAQs &rarr;</a>
                </div>
                <div class="card-body p-4">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-3">
                            <div class="p-3 rounded-3" style="background-color: #fffbeb; border: 1px solid #fde68a;">
                                <h4 class="fw-bold text-warning mb-0">{{ $metrics['unanswered']['pending'] }}</h4>
                                <small class="text-muted small">Pending</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-3 rounded-3" style="background-color: #f0fdfa; border: 1px solid #99f6e4;">
                                <h4 class="fw-bold text-info mb-0">{{ $metrics['unanswered']['reviewed'] }}</h4>
                                <small class="text-muted small">Reviewed</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-3 rounded-3" style="background-color: #ecfdf5; border: 1px solid #a7f3d0;">
                                <h4 class="fw-bold text-success mb-0">{{ $metrics['unanswered']['answered'] }}</h4>
                                <small class="text-muted small">Resolved</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-3 rounded-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                                <h4 class="fw-bold text-secondary mb-0">{{ $metrics['unanswered']['dismissed'] }}</h4>
                                <small class="text-muted small">Dismissed</small>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Questions logged when customer inquiries fall outside existing knowledge articles. Reviewing these helps expand your FAQ embeddings coverage.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
    @if (Session::has('success'))
        <script>
            swal("Success", "{{ Session::get('success') }}", "success", {
                timer: 1500,
                button: false,
            });
        </script>
    @endif
@endpush
