@extends('admin.app')
@section('title')
    Knowledge Base
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
    <style>
        .kb-pill-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 4px;
            background: #f1f5f9;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .kb-pill-btn {
            border: none;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }

        .kb-pill-btn:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.6);
        }

        .kb-pill-btn.active {
            background: #ffffff;
            color: #0d6efd;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }

        .faq-question-cell {
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-title-cell {
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }

        .doc-filename-cell {
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: monospace;
            font-size: 12px;
            color: #64748b;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Knowledge Base</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Knowledge Base</li>
                                </ol>
                            </nav>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @can(\App\Enums\Permissions\FAQPermission::CREATE->value)
                                <a href="{{ route('faqs.create') }}" class="add-new">
                                    <i class="ri-add-line me-1"></i>New FAQ / Policy
                                </a>
                            @endcan
                            @can(\App\Enums\Permissions\KnowledgeDocumentPermission::CREATE->value)
                                <a href="{{ route('documents.create') }}" class="add-new"
                                    style="background: #2563eb; border-color: #2563eb; color: #fff;">
                                    <i class="ri-upload-cloud-2-line me-1"></i>Upload Data Source
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body">
                        {{-- Content Type Navigation Pills --}}
                        <div class="kb-pill-tabs">
                            <button type="button" class="kb-pill-btn {{ $activeTab === 'faqs' ? 'active' : '' }}"
                                id="tab-btn-faqs" onclick="switchTab('faqs')">
                                <i class="ri-question-answer-line me-1"></i>FAQs (Q&A)
                            </button>
                            <button type="button" class="kb-pill-btn {{ $activeTab === 'policies' ? 'active' : '' }}"
                                id="tab-btn-policies" onclick="switchTab('policies')">
                                <i class="ri-shield-check-line me-1"></i>Policies
                            </button>
                            <button type="button" class="kb-pill-btn {{ $activeTab === 'business_info' ? 'active' : '' }}"
                                id="tab-btn-business_info" onclick="switchTab('business_info')">
                                <i class="ri-building-line me-1"></i>Business Information
                            </button>
                            <button type="button" class="kb-pill-btn {{ $activeTab === 'data_sources' ? 'active' : '' }}"
                                id="tab-btn-data_sources" onclick="switchTab('data_sources')">
                                <i class="ri-bar-chart-2-line me-1"></i>Analytics Data Sources
                            </button>
                        </div>

                        {{-- Section 1: FAQ, Policies, and Business Information Table --}}
                        <div id="faq-table-container"
                            class="{{ in_array($activeTab, ['faqs', 'policies', 'business_info']) ? '' : 'd-none' }}">
                            <div class="row g-2 mb-3">
                                <div class="col-md-3 col-sm-6">
                                    <select id="faq-status-filter" class="form-select form-select-sm"
                                        onchange="reloadFaqTable()">
                                        <option value="">All Statuses</option>
                                        <option value="active">Active Only</option>
                                        <option value="inactive">Inactive / Draft</option>
                                        <option value="trashed">Deleted</option>
                                    </select>
                                </div>
                            </div>

                            <div style="overflow-x: auto;">
                                <table class="table dataTable w-100" id="faq-data-table" style="min-width: 850px;">
                                    <thead>
                                        <tr>
                                            <th scope="col">SL NO</th>
                                            <th scope="col">Question / Title</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Priority</th>
                                            <th scope="col">Hits</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Section 2: Analytics Data Sources Table --}}
                        <div id="doc-table-container"
                            class="{{ in_array($activeTab, ['data_sources', 'documents']) ? '' : 'd-none' }}">
                            <div class="row g-2 mb-3">
                                <div class="col-md-3 col-sm-6">
                                    <select id="doc-status-filter" class="form-select form-select-sm"
                                        onchange="reloadDocTable()">
                                        <option value="">All Statuses</option>
                                        <option value="active">Active</option>
                                        <option value="pending">Pending Ingestion</option>
                                        <option value="failed">Failed</option>
                                        <option value="trashed">Deleted</option>
                                    </select>
                                </div>
                            </div>

                            <div style="overflow-x: auto;">
                                <table class="table dataTable w-100" id="doc-data-table" style="min-width: 850px;">
                                    <thead>
                                        <tr>
                                            <th scope="col">SL NO</th>
                                            <th scope="col">Title</th>
                                            <th scope="col">Original Filename</th>
                                            <th scope="col">Size</th>
                                            <th scope="col">Sheets</th>
                                            <th scope="col">Uploaded At</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- Document Preview & Metadata Details Modal -->
        <div class="modal fade" id="documentDetailsModal" tabindex="-1" aria-labelledby="documentDetailsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom py-2 px-3">
                        <div class="d-flex align-items-center gap-2">
                            <i id="modalFileIcon" class="ri-file-search-line text-primary fs-3"></i>
                            <div>
                                <h5 class="modal-title fw-bold text-dark mb-0" id="documentDetailsModalLabel">Document
                                    Preview & Details</h5>
                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-0 font-monospace"
                                    id="modalDocFilename" style="font-size: 11px;"></span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3">
                        <!-- Nav Tabs: Preview vs Schema & Metadata -->
                        <ul class="nav nav-tabs nav-tabs-bordered mb-3" id="docModalTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-bold text-dark d-flex align-items-center gap-1 py-2 px-3"
                                    id="tab-btn-preview" data-bs-toggle="tab" data-bs-target="#paneFilePreview"
                                    type="button" role="tab" aria-controls="paneFilePreview" aria-selected="true">
                                    <i class="ri-file-list-3-line text-primary"></i> File Content & Data
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold text-muted d-flex align-items-center gap-1 py-2 px-3"
                                    id="tab-btn-schema" data-bs-toggle="tab" data-bs-target="#paneSchemaDetails"
                                    type="button" role="tab" aria-controls="paneSchemaDetails" aria-selected="false">
                                    <i class="ri-information-line text-info"></i> Schema & Metadata
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="docModalTabsContent">
                            <!-- TAB 1: File Content & Data Preview -->
                            <div class="tab-pane fade show active" id="paneFilePreview" role="tabpanel"
                                aria-labelledby="tab-btn-preview">
                                <!-- Loader -->
                                <div id="previewLoader" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <div class="text-muted small mt-2">Loading document content...</div>
                                </div>

                                <!-- Alert / Missing / Error state -->
                                <div id="previewAlertBox" class="alert alert-warning d-none mb-0">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ri-alert-line fs-4 text-warning"></i>
                                        <div>
                                            <strong id="previewAlertTitle">Notice:</strong>
                                            <div class="small mt-1" id="previewAlertMsg"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Spreadsheet Preview (XLSX, XLS, CSV) -->
                                <div id="previewSpreadsheetBox" class="d-none">
                                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-1 flex-wrap" id="previewSheetPills">
                                            <!-- Dynamic sheet buttons -->
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="input-group input-group-sm" style="width: 240px;">
                                                <span class="input-group-text bg-light"><i
                                                        class="ri-search-line"></i></span>
                                                <input type="text" id="previewFilterInput" class="form-control"
                                                    placeholder="Search in table..."
                                                    onkeyup="filterPreviewTable(this.value)">
                                            </div>
                                            <span class="badge bg-light text-secondary border px-2 py-1"
                                                id="previewRowCounter" style="font-size: 11px;">0 rows</span>
                                        </div>
                                    </div>
                                    <div id="previewSheetStatusBanner"
                                        class="alert alert-light border py-1 px-3 small mb-2 d-flex align-items-center text-dark"
                                        style="font-size: 12px; background-color: #f8fafc;">
                                        <!-- Dynamic sheet info -->
                                    </div>
                                    <div class="table-responsive border rounded bg-white shadow-sm"
                                        style="max-height: 480px; overflow-y: auto;">
                                        <table class="table table-sm table-striped table-hover mb-0 text-nowrap"
                                            id="previewTable" style="font-size: 12.5px;">
                                            <thead class="table-light sticky-top border-bottom" id="previewTableThead"
                                                style="z-index: 2;"></thead>
                                            <tbody id="previewTableTbody"></tbody>
                                        </table>
                                    </div>
                                    <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                                        <span><i class="ri-information-line me-1 text-primary"></i>Interactive data view
                                            directly extracted from storage.</span>
                                        <span id="previewTruncatedAlert"
                                            class="badge bg-warning-subtle text-warning border d-none">Preview capped at
                                            first 100 rows</span>
                                    </div>
                                </div>

                                <!-- Text / Markdown Preview -->
                                <div id="previewTextBox" class="d-none">
                                    <div class="p-3 bg-light border rounded font-monospace"
                                        style="max-height: 480px; overflow-y: auto; white-space: pre-wrap; font-size: 12.5px;"
                                        id="previewTextContent"></div>
                                </div>
                            </div>

                            <!-- TAB 2: Schema & Metadata -->
                            <div class="tab-pane fade" id="paneSchemaDetails" role="tabpanel"
                                aria-labelledby="tab-btn-schema">
                                <!-- Top Summary Card -->
                                <div class="card border bg-light mb-3">
                                    <div class="card-body p-3">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-sm-4">
                                                <div class="text-muted small">Title</div>
                                                <div class="fw-bold text-dark text-truncate" id="modalDocTitle">-</div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="text-muted small">Purpose</div>
                                                <div id="modalDocPurpose">-</div>
                                            </div>
                                            <div class="col-sm-2">
                                                <div class="text-muted small">Status</div>
                                                <div id="modalDocStatus">-</div>
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="text-muted small">Size / Total Rows</div>
                                                <div class="fw-semibold text-dark" id="modalDocRows">-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Purpose-specific Info Banner -->
                                <div id="modalPurposeContext" class="mb-3 d-none"></div>

                                <!-- Lifecycle Notice -->
                                <div id="modalLifecycleNotice" class="mb-3 d-none"></div>

                                <!-- Ingestion Error Alert (if failed) -->
                                <div id="modalErrorSection" class="alert alert-danger d-none mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="ri-error-warning-fill me-1"></i>
                                            <strong>Ingestion Failure Notice:</strong>
                                            <div class="small mt-1" id="modalErrorMsg"></div>
                                        </div>
                                        <div class="d-flex gap-2 ms-3">
                                            <form id="modalRetryForm" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="btn btn-sm btn-danger d-flex align-items-center gap-1">
                                                    <i class="ri-refresh-line"></i> Retry Ingestion
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sheets & Tables Breakdown -->
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark d-flex align-items-center gap-1 mb-2">
                                        <i class="ri-table-line text-primary"></i> Virtual Tables & Sheets
                                    </h6>
                                    <div id="modalSheetsList" class="d-flex flex-wrap gap-2"></div>
                                </div>

                                <!-- Schema Catalog Markdown / Text Preview -->
                                <div>
                                    <h6 class="fw-bold text-dark d-flex align-items-center gap-1 mb-2">
                                        <i class="ri-database-2-line text-info"></i> Column Schema Catalog
                                    </h6>
                                    <div class="p-3 bg-white border rounded shadow-sm"
                                        style="max-height: 250px; overflow-y: auto;">
                                        <pre id="modalSchemaSummary" class="mb-0 text-dark font-monospace"
                                            style="white-space: pre-wrap; font-size: 12px;"></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="modal-footer bg-light border-top d-flex justify-content-between align-items-center py-2 px-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-muted small" id="modalSourceIdText"></span>
                            <a href="#" id="modalDownloadBtn"
                                class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" target="_blank"
                                download>
                                <i class="ri-download-2-line"></i> Download Original File
                            </a>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('simulator.index') }}"
                                class="btn btn-sm btn-primary d-flex align-items-center gap-1" id="modalAskSimulatorBtn">
                                <i class="ri-chat-smile-2-line"></i> Ask in Simulator
                            </a>
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
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

        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

        <script type="text/javascript">
            let currentTab = '{{ $activeTab }}';
            let faqDataTable = null;
            let docDataTable = null;

            function switchTab(tabName) {
                currentTab = tabName;
                $('.kb-pill-btn').removeClass('active');
                $(`#tab-btn-${tabName}`).addClass('active');

                // Update URL without full reload
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url);

                if (['faqs', 'policies', 'business_info'].includes(tabName)) {
                    $('#faq-table-container').removeClass('d-none');
                    $('#doc-table-container').addClass('d-none');
                    reloadFaqTable();
                } else {
                    $('#faq-table-container').addClass('d-none');
                    $('#doc-table-container').removeClass('d-none');
                    reloadDocTable();
                }
            }

            function reloadFaqTable() {
                if (faqDataTable) {
                    faqDataTable.ajax.reload();
                }
            }

            function reloadDocTable() {
                if (docDataTable) {
                    docDataTable.ajax.reload();
                }
            }

            function triggerResync(faqId) {
                swal({
                    title: "Re-sync to Vector Engine?",
                    text: "This will regenerate embeddings and synchronize this item with Typesense for AI retrieval.",
                    icon: "info",
                    buttons: ["Cancel", "Sync Now"],
                }).then((willSync) => {
                    if (willSync) {
                        fetch(`/dashboard/faqs/${faqId}/resync`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            }
                        })
                            .then(res => res.json())
                            .then(data => {
                                swal("Synced!", data.message || "FAQ sync job dispatched.", "success");
                                reloadFaqTable();
                            })
                            .catch(err => {
                                swal("Error", "Failed to trigger re-sync.", "error");
                            });
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                // 1. Initialize FAQ & Policies DataTable
                faqDataTable = $('#faq-data-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route("knowledge-base.index") }}',
                        data: function (d) {
                            d.source = 'faqs';
                            if (currentTab === 'policies') {
                                d.type_group = 'policy';
                            } else if (currentTab === 'business_info') {
                                d.type_group = 'business_info';
                            } else if (currentTab === 'faqs') {
                                d.type_group = 'faq';
                            }
                            d.status = $('#faq-status-filter').val();
                        },
                        error: function (xhr, error, code) {
                            console.error('FAQ DataTable Error:', error, code, xhr);
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                        {
                            data: 'question',
                            name: 'question',
                            render: function (data) {
                                return `<div class="faq-question-cell" title="${data}">${data}</div>`;
                            }
                        },
                        { data: 'document_type', name: 'document_type' },
                        { data: 'priority', name: 'priority' },
                        { data: 'hit_count', name: 'hit_count' },
                        { data: 'status_badge', name: 'status_badge', orderable: false, searchable: false },
                        {
                            data: 'action-btn',
                            name: 'action-btn',
                            orderable: false,
                            searchable: false,
                            render: function (data) {
                                var id = data.id;
                                var trashed = data.trashed;
                                var hasFailed = data.has_failed;
                                var error = data.error;

                                var resyncBtn = '';
                                if (!trashed && hasFailed) {
                                    resyncBtn = `
                                            <button onclick="triggerResync('${id}')" class="btn btn-sm btn-outline-warning me-1" title="Retry Sync (${error || 'Failed'})">
                                                <i class="ri-refresh-line"></i> Retry
                                            </button>
                                        `;
                                } else if (!trashed) {
                                    resyncBtn = `
                                            <button onclick="triggerResync('${id}')" class="btn btn-sm btn-outline-info me-1" title="Re-sync Vector Index">
                                                <i class="ri-refresh-line"></i>
                                            </button>
                                        `;
                                }

                                if (trashed) {
                                    return `
                                            <div class="d-flex align-items-center">
                                                <form action="/dashboard/faqs/${id}/restore" method="POST" style="display:inline-block;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Restore FAQ">
                                                        <i class="ri-refresh-line"></i> Restore
                                                    </button>
                                                </form>
                                            </div>
                                        `;
                                }

                                return `
                                        <div class="d-flex align-items-center">
                                            ${resyncBtn}
                                            <a href="/dashboard/faqs/${id}/edit" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                                <i class="ri-pencil-line"></i>
                                            </a>
                                            <form action="/dashboard/faqs/${id}" method="POST" onsubmit="return confirm('Are you sure you want to delete this FAQ?');" style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                    `;
                            }
                        }
                    ]
                });

                // 2. Initialize Documents & Data Sources DataTable
                docDataTable = $('#doc-data-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route("knowledge-base.index") }}',
                        data: function (d) {
                            d.source = 'documents';
                            d.purpose = 'data_analytics';
                            d.status = $('#doc-status-filter').val();
                        },
                        error: function (xhr, error, code) {
                            console.error('Doc DataTable Error:', error, code, xhr);
                        }
                    },
                    columns: [
                        { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                        {
                            data: 'title',
                            name: 'title',
                            render: function (data, type, row) {
                                var id = (row && row['action-btn'] && row['action-btn'].id) ? row['action-btn'].id : '';
                                return `<a href="javascript:void(0)" class="doc-title-cell text-primary fw-semibold w-100 d-block btn-view-doc" data-id="${id}" title="${data || ''}">${data || 'Untitled'}</a>`;
                            }
                        },
                        {
                            data: 'original_filename',
                            name: 'original_filename',
                            render: function (data) {
                                return `<div class="doc-filename-cell text-muted" title="${data}">${data}</div>`;
                            }
                        },
                        { data: 'file_size', name: 'file_size' },
                        { data: 'sheets_count', name: 'sheets_count' },
                        { data: 'created_at', name: 'created_at' },
                        { data: 'status_badge', name: 'status_badge', orderable: false, searchable: false },
                        {
                            data: 'action-btn',
                            name: 'action-btn',
                            orderable: false,
                            searchable: false,
                            render: function (data) {
                                if (!data) return '';
                                var id = data.id;

                                // Cache record safely in memory to prevent newline / escaping breaks in HTML attributes
                                window.docRecordsCache = window.docRecordsCache || {};
                                window.docRecordsCache[id] = data;

                                if (data.trashed) {
                                    return `
                                            <div class="d-flex align-items-center">
                                                <form action="${SITEURL}/dashboard/documents/${id}/restore" method="POST" style="display:inline-block;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Restore Document">
                                                        <i class="ri-refresh-line"></i> Restore
                                                    </button>
                                                </form>
                                            </div>
                                        `;
                                }

                                var btns = '<div class="action-btn d-flex align-items-center gap-1">';

                                // 1. Details & Interactive Preview Modal Button
                                btns += `<button type="button" class="btn btn-sm btn-outline-info btn-view-doc me-1" data-id="${id}" title="View Document & Data"><i class="ri-eye-line"></i></button>`;

                                // 2. Ask in Simulator shortcut (when active)
                                if (data.is_active) {
                                    var targetId = data.source_id || data.id;
                                    btns += `<a href="${SITEURL}/dashboard/simulator?file_id=${encodeURIComponent(targetId)}" class="btn btn-sm btn-primary text-white me-1" title="Ask Questions in Simulator" style="padding: 2px 8px; font-size: 12px;"><i class="ri-chat-smile-2-line"></i></a>`;
                                }

                                // 3. Retry / Ingest Button (for pending or failed documents)
                                if ((data.has_failed || data.is_pending) && data.can_update) {
                                    var btnTitle = data.is_pending ? 'Ingest & Activate Now' : 'Retry Ingestion';
                                    var btnClass = data.is_pending ? 'btn-primary' : 'btn-warning';
                                    var btnIcon = data.is_pending ? 'ri-play-circle-line' : 'ri-refresh-line';
                                    btns += `<form action="${SITEURL}/dashboard/documents/${id}/retry" method="POST" style="display:inline-block;" class="me-1">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-${btnClass}" title="${btnTitle}">
                                                    <i class="${btnIcon}"></i>
                                                </button>
                                            </form>`;
                                }

                                // 4. Delete Button
                                if (data.can_delete) {
                                    btns += `<form action="${SITEURL}/dashboard/documents/${id}" method="POST" onsubmit="return confirm('Are you sure you want to delete this document?');" style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>`;
                                }

                                btns += '</div>';
                                return btns;
                            }
                        }
                    ]
                });

                // Delegate click on view buttons and title links to open the document details modal
                $(document).on('click', '.btn-view-doc', function (e) {
                    e.preventDefault();
                    var id = $(this).attr('data-id');
                    var doc = (window.docRecordsCache && window.docRecordsCache[id]) ? window.docRecordsCache[id] : null;
                    if (doc) {
                        openDocumentDetails(doc);
                    } else if (id) {
                        openDocumentDetails({ id: id, title: 'Document Details' });
                    }
                });
            });

            // Document Details & Interactive Preview Modal
            window.previewRowsCache = [];

            function openDocumentDetails(doc) {
                if (!doc) return;

                if (typeof doc === 'string' || typeof doc === 'number') {
                    var found = window.docRecordsCache ? window.docRecordsCache[doc] : null;
                    doc = found || { id: String(doc), title: 'Document Details' };
                }

                window.currentViewingDocId = doc.id;
                $('#modalDocTitle').text(doc.title || '-');
                $('#modalDocFilename').text(doc.filename || '');
                $('#modalDocRows').text((doc.file_size || '') + (doc.total_rows ? ' • ' + doc.total_rows + ' rows' : ''));
                $('#modalSourceIdText').text(doc.source_id ? 'Source ID: ' + doc.source_id : '');

                // Purpose context
                var purposeBadge = '<span class="badge bg-primary text-white"><i class="ri-bar-chart-2-line me-1"></i>Data & Analytics</span>';
                $('#modalPurposeContext').html('<div class="alert alert-primary bg-primary-subtle border-primary-subtle text-primary-emphasis py-2 px-3 small mb-0"><i class="ri-sparkling-fill me-1"></i><strong>Data & Analytics Report:</strong> Structured into an analytical SQLite database and indexed in Typesense for SQL aggregations, charts, and mathematical queries.</div>').removeClass('d-none');
                $('#modalDocPurpose').html(purposeBadge);

                // Lifecycle status
                var statusBadge = '';
                if (doc.trashed) {
                    statusBadge = '<span class="badge bg-secondary"><i class="ri-delete-bin-line me-1"></i>Deleted</span>';
                } else if (doc.has_failed) {
                    statusBadge = '<span class="badge bg-danger"><i class="ri-close-circle-line me-1"></i>Failed</span>';
                } else if (doc.is_indexing) {
                    statusBadge = '<span class="badge bg-info text-dark"><i class="ri-loader-4-line spin me-1"></i>Indexing in Progress</span>';
                } else if (doc.is_pending) {
                    statusBadge = '<span class="badge bg-secondary"><i class="ri-time-line me-1"></i>Pending Ingestion</span>';
                } else if (doc.is_active) {
                    statusBadge = '<span class="badge bg-success"><i class="ri-checkbox-circle-line me-1"></i>Active</span>';
                } else {
                    statusBadge = '<span class="badge bg-secondary">' + (doc.lifecycle_status || 'Unknown') + '</span>';
                }
                $('#modalDocStatus').html(statusBadge);

                // Error & Recovery / Pending state
                if (doc.has_failed) {
                    $('#modalErrorSection').removeClass('d-none');
                    $('#modalErrorMsg').text(doc.error || 'Ingestion failed during catalog indexing.');
                    if (doc.can_update) {
                        $('#modalRetryForm').removeClass('d-none').attr('action', SITEURL + '/dashboard/documents/' + doc.id + '/retry');
                        $('#modalRetryForm button').removeClass('btn-primary').addClass('btn-danger').html('<i class="ri-refresh-line"></i> Retry Ingestion');
                    } else {
                        $('#modalRetryForm').addClass('d-none');
                    }
                } else if (doc.is_pending) {
                    $('#modalErrorSection').removeClass('d-none');
                    $('#modalErrorMsg').html('<span class="text-warning fw-semibold">Pending Ingestion:</span> This document is waiting to be processed into the analytics and search engine.');
                    if (doc.can_update) {
                        $('#modalRetryForm').removeClass('d-none').attr('action', SITEURL + '/dashboard/documents/' + doc.id + '/retry');
                        $('#modalRetryForm button').removeClass('btn-danger').addClass('btn-primary').html('<i class="ri-play-circle-line"></i> Ingest & Activate Now');
                    } else {
                        $('#modalRetryForm').addClass('d-none');
                    }
                } else {
                    $('#modalErrorSection').addClass('d-none');
                }

                // Simulator button
                if (doc.is_active) {
                    var targetId = doc.source_id || doc.id;
                    $('#modalAskSimulatorBtn').removeClass('d-none').attr('href', SITEURL + '/dashboard/simulator?file_id=' + encodeURIComponent(targetId));
                } else {
                    $('#modalAskSimulatorBtn').addClass('d-none');
                }

                var sheetsContainer = $('#modalSheetsList');
                sheetsContainer.empty();
                if (doc.tables && typeof doc.tables === 'object' && !Array.isArray(doc.tables) && Object.keys(doc.tables).length > 0) {
                    Object.keys(doc.tables).forEach(function (tbl) {
                        var title = (doc.tables[tbl] && doc.tables[tbl].sheet_title) ? doc.tables[tbl].sheet_title : tbl;
                        sheetsContainer.append('<span class="badge bg-light text-dark border px-3 py-2"><i class="ri-table-line text-primary me-1"></i>' + $('<div>').text(title).html() + '</span>');
                    });
                } else if (doc.tables && Array.isArray(doc.tables) && doc.tables.length > 0) {
                    doc.tables.forEach(function (tbl) {
                        sheetsContainer.append('<span class="badge bg-light text-dark border px-3 py-2"><i class="ri-table-line text-primary me-1"></i>' + $('<div>').text(tbl).html() + '</span>');
                    });
                } else if (doc.sheets && Array.isArray(doc.sheets) && doc.sheets.length > 0) {
                    doc.sheets.forEach(function (s) {
                        sheetsContainer.append('<span class="badge bg-light text-dark border px-3 py-2"><i class="ri-file-table-line text-success me-1"></i>' + $('<div>').text(s).html() + '</span>');
                    });
                } else {
                    sheetsContainer.append('<span class="badge bg-light text-dark border px-3 py-2">1 Sheet</span>');
                }

                var summaryText = doc.schema_summary || 'No schema catalog recorded for this document.';
                $('#modalSchemaSummary').text(summaryText);

                // Update file icon in modal header
                var ext = (doc.filename || '').split('.').pop().toLowerCase();
                var iconClass = (ext === 'xlsx' || ext === 'xls' || ext === 'csv') ? 'ri-file-excel-2-fill text-success' : 'ri-file-text-line text-primary';
                $('#modalFileIcon').attr('class', iconClass + ' fs-3');

                // Download URL
                var downloadUrl = SITEURL + '/dashboard/documents/' + doc.id + '/preview?stream=1';
                $('#modalDownloadBtn').attr('href', downloadUrl).attr('download', doc.filename || 'document');

                // Reset modal states before loading
                $('#previewSpreadsheetBox').addClass('d-none');
                $('#previewTextBox').addClass('d-none');
                $('#previewAlertBox').addClass('d-none');
                $('#previewLoader').removeClass('d-none');
                $('#previewTableThead').empty();
                $('#previewTableTbody').empty();
                $('#previewSheetPills').empty();
                $('#previewSheetStatusBanner').empty();
                $('#previewTextContent').empty();
                window.previewRowsCache = [];

                // Reset tab to "File Content & Data"
                var previewTabTrigger = document.querySelector('#tab-btn-preview');
                if (previewTabTrigger) {
                    if (window.bootstrap && window.bootstrap.Tab) {
                        var tab = (typeof bootstrap.Tab.getOrCreateInstance === 'function')
                            ? bootstrap.Tab.getOrCreateInstance(previewTabTrigger)
                            : (bootstrap.Tab.getInstance(previewTabTrigger) || new bootstrap.Tab(previewTabTrigger));
                        tab.show();
                    } else if ($(previewTabTrigger).tab) {
                        $(previewTabTrigger).tab('show');
                    }
                }

                // Show modal
                var modalEl = document.getElementById('documentDetailsModal');
                if (modalEl) {
                    if (window.bootstrap && window.bootstrap.Modal) {
                        var modal = (typeof bootstrap.Modal.getOrCreateInstance === 'function')
                            ? bootstrap.Modal.getOrCreateInstance(modalEl)
                            : (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl));
                        modal.show();
                    } else if (typeof $(modalEl).modal === 'function') {
                        $(modalEl).modal('show');
                    }
                }

                // Trigger dynamic preview content fetch
                loadDocumentContent(doc.id, null);
            }

            function loadDocumentContent(docId, sheetName) {
                $('#previewLoader').removeClass('d-none');
                $('#previewSpreadsheetBox').addClass('d-none');
                $('#previewTextBox').addClass('d-none');
                $('#previewAlertBox').addClass('d-none');
                $('#previewFilterInput').val('');

                $.ajax({
                    url: SITEURL + '/dashboard/documents/' + docId + '/preview',
                    type: 'GET',
                    data: {
                        sheet: sheetName || undefined,
                        max_rows: 100
                    },
                    dataType: 'json',
                    success: function (res) {
                        $('#previewLoader').addClass('d-none');

                        if (!res || !res.success) {
                            $('#previewAlertTitle').text('Preview Notice');
                            $('#previewAlertMsg').text((res && res.message) ? res.message : 'Could not generate preview for this document.');
                            $('#previewAlertBox').removeClass('d-none');
                            return;
                        }

                        if (res.type === 'spreadsheet') {
                            renderSpreadsheetPreview(docId, res);
                        } else if (res.type === 'text') {
                            $('#previewTextContent').text(res.content || '');
                            $('#previewTextBox').removeClass('d-none');
                        } else {
                            $('#previewAlertTitle').text('Preview Info');
                            $('#previewAlertMsg').text(res.message || 'File preview is not available in tabular format. Please download using the button below.');
                            $('#previewAlertBox').removeClass('d-none');
                        }
                    },
                    error: function (xhr) {
                        $('#previewLoader').addClass('d-none');
                        var msg = 'Failed to load document preview.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.status === 403) {
                            msg = 'Unauthorized to access this document in your current workspace context.';
                        } else if (xhr.status === 404) {
                            msg = 'The physical file was not found on local storage.';
                        }
                        $('#previewAlertTitle').text('Preview Error (' + (xhr.status || 500) + ')');
                        $('#previewAlertMsg').text(msg);
                        $('#previewAlertBox').removeClass('d-none');
                    }
                });
            }

            function renderSpreadsheetPreview(docId, data) {
                // Sheet tabs / pills
                var sheetPills = $('#previewSheetPills');
                sheetPills.empty();
                if (data.sheets && data.sheets.length > 1) {
                    var container = $('<div class="d-flex align-items-center gap-1 flex-wrap"></div>');
                    container.append('<span class="text-muted small fw-bold me-1"><i class="ri-folder-open-line me-1 text-primary"></i>Sheets:</span>');
                    data.sheets.forEach(function (sheet) {
                        var isActive = sheet === data.active_sheet;
                        var btnClass = isActive ? 'btn-primary active text-white fw-bold shadow-sm' : 'btn-outline-secondary text-dark bg-white';
                        var btn = $('<button type="button" class="btn btn-sm py-1 px-3 d-flex align-items-center gap-1 ' + btnClass + '" style="font-size: 12px; border-radius: 6px;"></button>')
                            .html('<i class="ri-file-excel-line"></i> ' + $('<div>').text(sheet).html() + (isActive ? ' <span class="badge bg-white text-primary ms-1">' + (data.headers ? data.headers.length : 0) + ' cols</span>' : ''));
                        btn.on('click', function () {
                            loadDocumentContent(docId, sheet);
                        });
                        container.append(btn);
                    });
                    sheetPills.append(container);
                } else if (data.sheets && data.sheets.length === 1) {
                    sheetPills.append('<span class="badge bg-light text-dark border px-3 py-2 fw-semibold" style="font-size: 12px;"><i class="ri-file-excel-line text-success me-1"></i>Sheet: ' + $('<div>').text(data.sheets[0]).html() + ' (' + (data.headers ? data.headers.length : 0) + ' columns)</span>');
                }

                // Sync virtual tables & sheets
                if (data.sheets && Array.isArray(data.sheets) && data.sheets.length > 0) {
                    var modalSheets = $('#modalSheetsList');
                    modalSheets.empty();
                    data.sheets.forEach(function (s) {
                        modalSheets.append('<span class="badge bg-light text-dark border px-3 py-2"><i class="ri-file-table-line text-success me-1"></i>' + $('<div>').text(s).html() + '</span>');
                    });
                }

                // Headers
                var thead = $('#previewTableThead');
                thead.empty();
                var trHead = $('<tr></tr>');
                trHead.append('<th scope="col" class="bg-light text-muted border-end text-center" style="width: 45px;">#</th>');
                if (data.headers && data.headers.length > 0) {
                    data.headers.forEach(function (col) {
                        trHead.append('<th scope="col" class="bg-light fw-bold text-dark px-2 py-1">' + $('<div>').text(col).html() + '</th>');
                    });
                }
                thead.append(trHead);

                // Rows
                window.previewRowsCache = data.rows || [];
                renderPreviewRows(window.previewRowsCache);

                // Active Sheet & Column counter banner
                var colCount = data.headers ? data.headers.length : 0;
                var rowCountNum = (data.loaded_rows_count !== undefined ? data.loaded_rows_count : (data.rows ? data.rows.length : 0));
                var totalStr = data.total_rows ? ' of ' + data.total_rows : '';
                $('#previewSheetStatusBanner').html('<div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-1"><div><i class="ri-table-line text-primary me-1"></i>Active Sheet: <strong class="text-dark">' + $('<div>').text(data.active_sheet || 'Default').html() + '</strong> • <strong class="text-dark">' + colCount + ' columns</strong></div><div class="text-muted fw-semibold">' + rowCountNum + totalStr + ' rows</div></div>');
                $('#previewRowCounter').text(rowCountNum + totalStr + ' rows');

                if (data.is_truncated) {
                    $('#previewTruncatedAlert').removeClass('d-none');
                } else {
                    $('#previewTruncatedAlert').addClass('d-none');
                }

                $('#previewSpreadsheetBox').removeClass('d-none');
            }

            function renderPreviewRows(rows) {
                var tbody = $('#previewTableTbody');
                tbody.empty();

                if (!rows || rows.length === 0) {
                    tbody.append('<tr><td colspan="50" class="text-center text-muted py-4"><i class="ri-inbox-line fs-3 d-block mb-1"></i>No rows found in this sheet</td></tr>');
                    return;
                }

                rows.forEach(function (row, idx) {
                    var tr = $('<tr></tr>');
                    tr.append('<td class="text-muted small py-1 text-center border-end bg-light-subtle">' + (idx + 1) + '</td>');
                    row.forEach(function (cell) {
                        var safeCell = $('<div>').text(cell !== null && cell !== undefined ? cell : '').html();
                        tr.append('<td class="py-1 px-2">' + safeCell + '</td>');
                    });
                    tbody.append(tr);
                });
            }

            function filterPreviewTable(query) {
                if (!window.previewRowsCache) return;
                var q = (query || '').toLowerCase().trim();
                if (!q) {
                    renderPreviewRows(window.previewRowsCache);
                    $('#previewRowCounter').text(window.previewRowsCache.length + ' rows');
                    return;
                }

                var filtered = window.previewRowsCache.filter(function (row) {
                    return row.some(function (cell) {
                        return String(cell || '').toLowerCase().indexOf(q) !== -1;
                    });
                });

                renderPreviewRows(filtered);
                $('#previewRowCounter').text(filtered.length + ' matched');
            }
        </script>
    @endpush