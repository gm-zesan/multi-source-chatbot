@extends('admin.app')

@section('title')
    Lexicon & Vocabulary
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <style>
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 12px;
        }

        .dataTables_wrapper .dataTables_length select {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            width: auto !important;
            min-width: 65px !important;
            height: 32px !important;
            padding: 4px 8px !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            color: #1e293b !important;
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            margin: 0 4px !important;
        }

        .dataTables_wrapper .dataTables_filter input {
            height: 32px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            font-size: 13px !important;
        }

        .nav-tabs {
            border-bottom: 1px solid #e2e8f0;
            gap: 4px;
        }

        .nav-tabs .nav-link {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 10px 16px;
            border-radius: 0;
            background: transparent;
            transition: color 0.15s ease, border-color 0.15s ease;
        }

        .nav-tabs .nav-link:hover {
            color: #0f172a;
            border-bottom-color: #cbd5e1;
        }

        .nav-tabs .nav-link.active {
            color: #2563eb;
            border-bottom: 2px solid #2563eb;
            background: transparent;
            font-weight: 600;
        }

        .count-pill {
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #475569;
            font-weight: 500;
        }

        .nav-link.active .count-pill {
            background: #eff6ff;
            color: #2563eb;
        }

        .badge-subtle {
            font-size: 11px;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .badge-subtle-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-subtle-secondary {
            background-color: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .badge-subtle-primary {
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .badge-subtle-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .badge-subtle-warning {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .text-truncate-cell {
            max-width: 240px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        /* Remove outline and box-shadow on focus for all buttons, selects, and inputs */
        button:focus,
        button:focus-visible,
        button:active,
        select:focus,
        select:focus-visible,
        select:active,
        input:focus,
        input:focus-visible,
        input:active,
        textarea:focus,
        textarea:focus-visible,
        textarea:active,
        .btn:focus,
        .btn:focus-visible,
        .btn:active,
        .btn-check:focus+.btn,
        .form-select:focus,
        .form-select:focus-visible,
        .form-select:active,
        .form-control:focus,
        .form-control:focus-visible,
        .form-control:active,
        .dataTables_wrapper select:focus,
        .dataTables_wrapper input:focus,
        .nav-link:focus,
        .nav-link:focus-visible {
            outline: none !important;
            box-shadow: none !important;
        }

        /* Action Intent Rules Column Width Constraints */
        #table-action-mappings th:nth-child(1) {
            width: 130px !important;
        }

        #table-action-mappings th:nth-child(2) {
            width: 150px !important;
        }

        #table-action-mappings th:nth-child(5),
        #table-action-mappings td:nth-child(5) {
            width: 75px !important;
            text-align: center;
        }

        #table-action-mappings th:nth-child(6),
        #table-action-mappings td:nth-child(6) {
            width: 75px !important;
            text-align: right;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        {{-- Header Card --}}
        <div class="card table-card">
            <div class="card-header table-header d-flex align-items-center justify-content-between flex-wrap gap-3">
                {{-- Left: Title and Breadcrumb Hierarchy --}}
                <div class="d-flex flex-column gap-1">
                    <div class="table-title mb-0">Lexicon & Dynamic Vocabulary</div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0" style="font-size: 11.5px;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}" class="text-muted text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('faqs.index') }}" class="text-muted text-decoration-none">Knowledge
                                    Base</a>
                            </li>
                            <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">
                                Lexicons
                            </li>
                        </ol>
                    </nav>
                </div>

                {{-- Right: Scope Selector, Engine Status, Sync Action --}}
                <div class="d-flex align-items-center gap-2.5 flex-nowrap">
                    {{-- Workspace Filter (SuperAdmin) --}}
                    @if($isSuperAdmin && $availableWorkspaces->isNotEmpty())
                        <form method="GET" action="{{ route('lexicons.index') }}" class="d-flex align-items-center gap-2 mb-0">
                            <label for="workspace_select" class="small text-muted mb-0 fw-semibold text-nowrap"
                                style="font-size: 12px;">Scope:</label>
                            <select name="workspace_id" id="workspace_select" class="form-select form-select-sm"
                                onchange="this.form.submit()"
                                style="min-width: 160px; height: 34px; font-size: 12.5px; font-weight: 500; border-radius: 6px; border-color: #cbd5e1; background-color: #f8fafc; cursor: pointer;">
                                <option value="0" {{ $selectedWorkspaceId === 0 ? 'selected' : '' }}>Global Scope</option>
                                @foreach($availableWorkspaces as $ws)
                                    <option value="{{ $ws->id }}" {{ $selectedWorkspaceId === $ws->id ? 'selected' : '' }}>
                                        {{ $ws->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <span class="badge-subtle badge-subtle-secondary"
                            style="height: 32px; display: inline-flex; align-items: center; padding: 0 10px;">
                            {{ $selectedWorkspaceId === 0 ? 'Global Scope' : "Workspace #{$selectedWorkspaceId}" }}
                        </span>
                    @endif
                    {{-- Hot Reload Sync Button --}}
                    <form method="POST" action="{{ route('lexicons.sync') }}" class="d-inline mb-0 ms-2">
                        @csrf
                        <input type="hidden" name="workspace_id" value="{{ $selectedWorkspaceId }}">
                        <button type="submit"
                            class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5"
                            style="height: 34px; padding: 0 14px; font-size: 12px; font-weight: 600; border-radius: 6px; white-space: nowrap; transition: all 0.15s ease;">
                            <i class="ri-refresh-line" style="font-size: 14px;"></i>
                            <span>Sync to Engine</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Tab Navigation --}}
            <div class="card-body">
                <ul class="nav nav-tabs" id="lexiconTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="domain-tab" data-bs-toggle="tab" data-bs-target="#domain-pane"
                            type="button" role="tab">
                            <i class="ri-text me-1"></i> Domain Synonyms
                            <span class="count-pill ms-1">{{ $domainEntries->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="concepts-tab" data-bs-toggle="tab" data-bs-target="#concepts-pane"
                            type="button" role="tab">
                            <i class="ri-price-tag-3-line me-1"></i> Concept Patterns
                            <span class="count-pill ms-1">{{ $rawConceptPatterns->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="actions-tab" data-bs-toggle="tab" data-bs-target="#actions-pane"
                            type="button" role="tab">
                            <i class="ri-guide-line me-1"></i> Action Intent Rules
                            <span class="count-pill ms-1">{{ $actionMappings->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="policies-tab" data-bs-toggle="tab" data-bs-target="#policies-pane"
                            type="button" role="tab">
                            <i class="ri-shield-line me-1"></i> Policy Rules
                            <span class="count-pill ms-1">{{ $policyMappings->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="discovery-tab" data-bs-toggle="tab" data-bs-target="#discovery-pane"
                            type="button" role="tab">
                            <i class="ri-search-eye-line me-1"></i> Unmatched Queries
                            <span
                                class="count-pill ms-1">{{ $unansweredQuestions->count() + $lowConfidenceSearches->count() }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Tab Panes --}}
        <div class="card table-card">
            <div class="card-body">
                <div class="tab-content" id="lexiconTabsContent">

                    {{-- ========================================================== --}}
                    {{-- TAB 1: DOMAIN ENTRIES (SYNONYMS) --}}
                    {{-- ========================================================== --}}
                    <div class="tab-pane fade show active" id="domain-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Domain Synonyms & Expansions</h6>
                                <small class="text-muted">Exact phrase mapping automatically expanded into search
                                    queries.</small>
                            </div>
                            <button class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"
                                data-bs-toggle="modal" data-bs-target="#addDomainEntryModal">
                                <i class="ri-add-line"></i> Add Synonym
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table dataTable w-100" id="table-domain-entries">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;">Concept</th>
                                        <th>Trigger Phrase</th>
                                        <th>Expansion Terms</th>
                                        <th style="width: 90px;">Language</th>
                                        <th style="width: 70px;">Version</th>
                                        <th style="width: 90px;">Status</th>
                                        <th style="width: 80px;" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($domainEntries as $entry)
                                        <tr>
                                            <td>
                                                <span
                                                    class="badge-subtle badge-subtle-secondary">{{ $entry->concept_key }}</span>
                                            </td>
                                            <td><strong>{{ $entry->pattern }}</strong></td>
                                            <td><span class="text-primary">{{ $entry->expansion }}</span></td>
                                            <td><span class="text-muted small">{{ strtoupper($entry->language) }}</span></td>
                                            <td><span class="text-muted small">v{{ $entry->version }}</span></td>
                                            <td>
                                                <span
                                                    class="badge-subtle {{ $entry->status === 'ACTIVE' ? 'badge-subtle-success' : 'badge-subtle-secondary' }}">
                                                    {{ $entry->status }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <form action="{{ route('lexicons.domain-entries.destroy', $entry->id) }}"
                                                    method="POST" class="d-inline"
                                                    onsubmit="return confirm('Remove this synonym entry?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1"
                                                        title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ========================================================== --}}
                    {{-- TAB 2: CONCEPTS & GUARDS --}}
                    {{-- ========================================================== --}}
                    <div class="tab-pane fade" id="concepts-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Canonical Concept Patterns & Guardrails</h6>
                                <small class="text-muted">Phrases that trigger canonical concepts or prevent false positive
                                    matches.</small>
                            </div>
                            <button class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"
                                data-bs-toggle="modal" data-bs-target="#addConceptPatternModal">
                                <i class="ri-add-line"></i> Add Pattern
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table dataTable w-100" id="table-concept-patterns">
                                <thead>
                                    <tr>
                                        <th style="width: 160px;">Concept</th>
                                        <th style="width: 130px;">Type</th>
                                        <th>Phrase / Cue</th>
                                        <th style="width: 130px;">Target Document</th>
                                        <th style="width: 70px;">Version</th>
                                        <th style="width: 90px;">Status</th>
                                        <th style="width: 80px;" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rawConceptPatterns as $p)
                                        <tr>
                                            <td><strong>{{ $p->concept_key }}</strong></td>
                                            <td>
                                                @if($p->pattern_type === 'POSITIVE')
                                                    <span class="badge-subtle badge-subtle-success">Positive Cue</span>
                                                @elseif($p->pattern_type === 'NEGATIVE_GUARD')
                                                    <span class="badge-subtle badge-subtle-danger">Negative Guard</span>
                                                @else
                                                    <span class="badge-subtle badge-subtle-primary">Metadata</span>
                                                @endif
                                            </td>
                                            <td>{{ $p->phrase ?? '—' }}</td>
                                            <td>
                                                @if($p->target_doc_type)
                                                    <code>{{ $p->target_doc_type }}</code>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td><span class="text-muted small">v{{ $p->version }}</span></td>
                                            <td>
                                                <span
                                                    class="badge-subtle {{ $p->status === 'ACTIVE' ? 'badge-subtle-success' : 'badge-subtle-secondary' }}">
                                                    {{ $p->status }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <form action="{{ route('lexicons.concept-patterns.destroy', $p->id) }}"
                                                    method="POST" class="d-inline"
                                                    onsubmit="return confirm('Delete this pattern?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1"
                                                        title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ========================================================== --}}
                    {{-- TAB 3: ACTION INTENTS --}}
                    {{-- ========================================================== --}}
                    <div class="tab-pane fade" id="actions-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Action Intent Alignment Rules</h6>
                                <small class="text-muted">Tie-breaking signals to elevate specific action responses over
                                    general queries.</small>
                            </div>
                            <button class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"
                                data-bs-toggle="modal" data-bs-target="#addActionMappingModal">
                                <i class="ri-add-line"></i> Add Action Rule
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table dataTable w-100" id="table-action-mappings">
                                <thead>
                                    <tr>
                                        <th style="width: 130px;">Intent</th>
                                        <th style="width: 150px;">Action Keyword</th>
                                        <th>Boosted Target Phrase</th>
                                        <th>Demoted Penalty Phrase</th>
                                        <th style="width: 75px;" class="text-center">Version</th>
                                        <th style="width: 75px;" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($actionMappings as $action)
                                        <tr>
                                            <td><span
                                                    class="badge-subtle badge-subtle-primary">{{ $action->intent_name }}</span>
                                            </td>
                                            <td><strong>{{ $action->action_keyword }}</strong></td>
                                            <td><span class="text-success"><i
                                                        class="ri-arrow-up-line me-1"></i>{{ $action->target_phrase }}</span>
                                            </td>
                                            <td>
                                                @if($action->penalty_phrase)
                                                    <span class="text-danger"><i
                                                            class="ri-arrow-down-line me-1"></i>{{ $action->penalty_phrase }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center"><span
                                                    class="text-muted small">v{{ $action->version }}</span></td>
                                            <td class="text-end">
                                                <form action="{{ route('lexicons.action-mappings.destroy', $action->id) }}"
                                                    method="POST" class="d-inline"
                                                    onsubmit="return confirm('Delete this action mapping?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1"
                                                        title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ========================================================== --}}
                    {{-- TAB 4: POLICY INTENTS --}}
                    {{-- ========================================================== --}}
                    <div class="tab-pane fade" id="policies-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Policy Document Alignment Rules</h6>
                                <small class="text-muted">Boost specific document types when policy intent keywords are
                                    matched.</small>
                            </div>
                            <button class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"
                                data-bs-toggle="modal" data-bs-target="#addPolicyMappingModal">
                                <i class="ri-add-line"></i> Add Policy Rule
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table dataTable w-100" id="table-policy-mappings">
                                <thead>
                                    <tr>
                                        <th style="width: 150px;">Policy Name</th>
                                        <th>Cue Phrase</th>
                                        <th style="width: 160px;">Target Document</th>
                                        <th style="width: 70px;">Version</th>
                                        <th style="width: 90px;">Status</th>
                                        <th style="width: 80px;" class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($policyMappings as $pol)
                                        <tr>
                                            <td><strong>{{ $pol->policy_name }}</strong></td>
                                            <td><code>{{ $pol->cue_phrase }}</code></td>
                                            <td><span
                                                    class="badge-subtle badge-subtle-secondary">{{ $pol->target_doc_type }}</span>
                                            </td>
                                            <td><span class="text-muted small">v{{ $pol->version }}</span></td>
                                            <td>
                                                <span
                                                    class="badge-subtle {{ $pol->status === 'ACTIVE' ? 'badge-subtle-success' : 'badge-subtle-secondary' }}">
                                                    {{ $pol->status }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <form action="{{ route('lexicons.policy-mappings.destroy', $pol->id) }}"
                                                    method="POST" class="d-inline"
                                                    onsubmit="return confirm('Delete this policy mapping?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1"
                                                        title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ========================================================== --}}
                    {{-- TAB 5: UNMATCHED QUERIES & DISCOVERY --}}
                    {{-- ========================================================== --}}
                    <div class="tab-pane fade" id="discovery-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Unmatched Inquiries & Low-Confidence Searches</h6>
                                <small class="text-muted">Review queries that scored below threshold to identify missing
                                    synonyms.</small>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="border rounded p-3 bg-white h-100">
                                    <h6 class="fw-bold small text-muted text-uppercase mb-3">Unanswered Questions</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Question</th>
                                                    <th style="width: 70px;">Count</th>
                                                    <th style="width: 100px;" class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($unansweredQuestions as $uq)
                                                    <tr>
                                                        <td class="text-truncate-cell" title="{{ $uq->original_question }}">
                                                            {{ $uq->original_question }}
                                                        </td>
                                                        <td><span
                                                                class="badge-subtle badge-subtle-primary">{{ $uq->occurrence_count }}</span>
                                                        </td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                                                onclick="prefillSynonymModal('{{ addslashes($uq->original_question) }}')">
                                                                Add
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-3 small">No unanswered
                                                            questions recorded.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="border rounded p-3 bg-white h-100">
                                    <h6 class="fw-bold small text-muted text-uppercase mb-3">Low-Confidence Queries (&lt;
                                        0.60)</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Query</th>
                                                    <th style="width: 70px;">Score</th>
                                                    <th style="width: 100px;" class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($lowConfidenceSearches as $log)
                                                    <tr>
                                                        <td class="text-truncate-cell" title="{{ $log->customer_query }}">
                                                            {{ $log->customer_query }}
                                                        </td>
                                                        <td>
                                                            <span
                                                                class="badge-subtle {{ $log->final_score > 0.40 ? 'badge-subtle-warning' : 'badge-subtle-danger' }}">
                                                                {{ round($log->final_score * 100) }}%
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                                                onclick="prefillSynonymModal('{{ addslashes($log->customer_query) }}')">
                                                                Add
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-3 small">No
                                                            low-confidence queries recorded.</td>
                                                    </tr>
                                                @endforelse
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
    </div>

    {{-- MODALS --}}

    {{-- 1. Add Domain Entry Modal --}}
    <div class="modal fade" id="addDomainEntryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('lexicons.domain-entries.store') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $selectedWorkspaceId }}">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold mb-0">Add Domain Synonym</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Concept Key <span class="text-danger">*</span></label>
                        <input type="text" name="concept_key" id="modal_concept_key" class="form-control form-control-sm"
                            placeholder="e.g. PAYMENT_ISSUE, RETURN_POLICY" required>
                        <small class="text-muted">Uppercase concept grouping related phrases.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Trigger Phrase <span class="text-danger">*</span></label>
                        <input type="text" name="pattern" id="modal_pattern" class="form-control form-control-sm"
                            placeholder="Phrase written by user" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Expansion Terms <span class="text-danger">*</span></label>
                        <input type="text" name="expansion" id="modal_expansion" class="form-control form-control-sm"
                            placeholder="Terms appended into search query" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Language</label>
                        <select name="language" class="form-select form-select-sm">
                            <option value="bn">Bengali / Banglish (bn)</option>
                            <option value="en">English (en)</option>
                            <option value="code_mixed">Code-Mixed (code_mixed)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 2. Add Concept Pattern Modal --}}
    <div class="modal fade" id="addConceptPatternModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('lexicons.concept-patterns.store') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $selectedWorkspaceId }}">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold mb-0">Add Concept Pattern</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Concept Key <span class="text-danger">*</span></label>
                        <input type="text" name="concept_key" class="form-control form-control-sm"
                            placeholder="e.g. RETURN_POLICY, ORDER_TRACKING" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Pattern Type <span class="text-danger">*</span></label>
                        <select name="pattern_type" id="concept_pattern_type" class="form-select form-select-sm" required>
                            <option value="POSITIVE">Positive Cue (Trigger)</option>
                            <option value="NEGATIVE_GUARD">Negative Guard (Blocking)</option>
                            <option value="CONCEPT_META">Metadata (Target Doc Type)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="group_concept_phrase">
                        <label class="form-label small fw-medium">Phrase / Cue</label>
                        <input type="text" name="phrase" class="form-control form-control-sm"
                            placeholder="Trigger or blocking phrase">
                    </div>
                    <div class="mb-3" id="group_concept_target_doc">
                        <label class="form-label small fw-medium">Target Document Type</label>
                        <input type="text" name="target_doc_type" class="form-control form-control-sm"
                            placeholder="e.g. policy_return, faq">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Pattern</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 3. Add Action Mapping Modal --}}
    <div class="modal fade" id="addActionMappingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('lexicons.action-mappings.store') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $selectedWorkspaceId }}">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold mb-0">Add Action Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Intent Name <span class="text-danger">*</span></label>
                        <input type="text" name="intent_name" class="form-control form-control-sm"
                            placeholder="e.g. invoice, order_track" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Action Keyword <span class="text-danger">*</span></label>
                        <input type="text" name="action_keyword" class="form-control form-control-sm"
                            placeholder="e.g. download, track" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Boosted Target Phrase <span
                                class="text-danger">*</span></label>
                        <input type="text" name="target_phrase" class="form-control form-control-sm"
                            placeholder="Target FAQ title to boost" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Demoted Penalty Phrase</label>
                        <input type="text" name="penalty_phrase" class="form-control form-control-sm"
                            placeholder="Competing FAQ title to demote">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Rule</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 4. Add Policy Mapping Modal --}}
    <div class="modal fade" id="addPolicyMappingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('lexicons.policy-mappings.store') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $selectedWorkspaceId }}">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold mb-0">Add Policy Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Policy Name <span class="text-danger">*</span></label>
                        <input type="text" name="policy_name" class="form-control form-control-sm"
                            placeholder="e.g. return_policy" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Cue Phrase <span class="text-danger">*</span></label>
                        <input type="text" name="cue_phrase" class="form-control form-control-sm"
                            placeholder="Phrase triggering policy boost" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Target Document Type <span
                                class="text-danger">*</span></label>
                        <input type="text" name="target_doc_type" class="form-control form-control-sm"
                            placeholder="e.g. policy_return, policy_warranty" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Rule</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('custom-scripts')
    @if(Session::has('success'))
        <script>
            swal("Success", "{{ Session::get('success') }}", "success", { timer: 2000, button: false });
        </script>
    @endif
    @if(Session::has('error'))
        <script>
            swal("Error", "{{ Session::get('error') }}", "error", { timer: 3000, button: true });
        </script>
    @endif

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Relocate all modals to body so backdrop never overlaps modal content
            document.querySelectorAll('.modal').forEach(function (modalEl) {
                document.body.appendChild(modalEl);
            });

            var dtOptions = function (emptyMsg) {
                return {
                    pageLength: 15,
                    lengthMenu: [10, 15, 25, 50, 100],
                    language: {
                        emptyTable: emptyMsg,
                        lengthMenu: "Show _MENU_ entries",
                        search: "Search:",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Prev"
                        }
                    }
                };
            };

            if (typeof $.fn.DataTable !== 'undefined') {
                $('#table-domain-entries').DataTable(dtOptions("No domain synonyms configured for this scope."));
                $('#table-concept-patterns').DataTable(dtOptions("No concept patterns found for this scope."));
                $('#table-action-mappings').DataTable($.extend(true, {}, dtOptions("No action rules configured for this scope."), {
                    autoWidth: false,
                    columns: [
                        { width: "130px" },
                        { width: "150px" },
                        null,
                        null,
                        { width: "75px", className: "text-center" },
                        { width: "75px", className: "text-end", orderable: false }
                    ]
                }));
                $('#table-policy-mappings').DataTable(dtOptions("No policy rules configured for this scope."));
            }
        });

        function prefillSynonymModal(queryText) {
            document.getElementById('modal_pattern').value = queryText.toLowerCase().trim();
            document.getElementById('modal_expansion').value = queryText.toLowerCase().trim();

            var modalEl = document.getElementById('addDomainEntryModal');
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    </script>
@endpush