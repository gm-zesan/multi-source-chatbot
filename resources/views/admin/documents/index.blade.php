@extends('admin.app')
@section('title')
    Knowledge Documents
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
    <style>
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
                            <div class="table-title">Knowledge Documents</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Documents</li>
                                </ol>
                            </nav>
                        </div>
                        <a href="{{ route('documents.create') }}" class="add-new">Upload Document<i
                                class="ms-1 ri-upload-cloud-2-line"></i></a>
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100" id="data-table" style="min-width: 850px;">
                            <thead>
                                <tr>
                                    <th scope="col">SL NO</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Original Filename</th>
                                    <th scope="col">Purpose</th>
                                    <th scope="col">Size</th>
                                    <th scope="col">Sheets</th>
                                    <th scope="col">Uploaded At</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Schema & Metadata Details Modal -->
    <div class="modal fade" id="documentDetailsModal" tabindex="-1" aria-labelledby="documentDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-file-excel-2-fill text-success fs-4"></i>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="documentDetailsModalLabel">Document Schema &
                                Metadata</h5>
                            <small class="text-muted" id="modalDocFilename"></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
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

                    <!-- Lifecycle Notice (dynamic status feedback) -->
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
                                    <button type="submit" class="btn btn-sm btn-danger d-flex align-items-center gap-1">
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
                        <div class="p-3 bg-white border rounded" style="max-height: 280px; overflow-y: auto;">
                            <pre id="modalSchemaSummary" class="mb-0 text-dark font-monospace"
                                style="white-space: pre-wrap; font-size: 12px;"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between">
                    <span class="text-muted small" id="modalSourceIdText"></span>
                    <div class="d-flex gap-2">
                        <a href="{{ route('simulator.index') }}" class="btn btn-primary d-flex align-items-center gap-1"
                            id="modalAskSimulatorBtn">
                            <i class="ri-chat-smile-2-line"></i> Ask in Simulator
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

    @if (Session::has('error'))
        <script>
            swal("Error", "{{ Session::get('error') }}", "error", {
                timer: 3000,
                button: true,
            });
        </script>
    @endif

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/documents';

        function openDocumentDetails(doc) {
            if (!doc) return;

            window.currentViewingDocId = doc.id;
            $('#modalDocTitle').text(doc.title || '-');
            $('#modalDocFilename').text(doc.filename || '');
            $('#modalDocRows').text((doc.file_size || '') + (doc.total_rows ? ' • ' + doc.total_rows + ' rows' : ''));
            $('#modalSourceIdText').text(doc.source_id ? 'Source ID: ' + doc.source_id : '');

            // Purpose rendering & context alert
            var purposeBadge = '';
            var isAnalytics = doc.purpose === 'data_analytics' || doc.purpose === 'generic_analytics' || doc.is_data_analytics || doc.is_generic_analytics;
            if (isAnalytics) {
                purposeBadge = '<span class="badge bg-primary text-white"><i class="ri-bar-chart-2-line me-1"></i>Data & Analytics</span>';
                $('#modalPurposeContext').html('<div class="alert alert-primary bg-primary-subtle border-primary-subtle text-primary-emphasis py-2 px-3 small mb-0"><i class="ri-sparkling-fill me-1"></i><strong>Data & Analytics Report:</strong> Structured into an analytical SQLite database and indexed in Typesense for SQL aggregations, charts, and mathematical queries.</div>').removeClass('d-none');
            } else {
                purposeBadge = '<span class="badge bg-secondary text-white"><i class="ri-book-read-line me-1"></i>Knowledge Base</span>';
                $('#modalPurposeContext').html('<div class="alert alert-success bg-success-subtle border-success-subtle text-success-emphasis py-2 px-3 small mb-0"><i class="ri-book-open-line me-1"></i><strong>FAQ & Knowledge Base:</strong> Indexed for AI conversational answers and semantic document search.</div>').removeClass('d-none');
            }
            $('#modalDocPurpose').html(purposeBadge);

            // Lifecycle status rendering
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

            // Simulator button: valid if document is active
            if (doc.is_active) {
                var targetId = doc.source_id || doc.id;
                $('#modalAskSimulatorBtn').removeClass('d-none').attr('href', SITEURL + '/dashboard/simulator?file_id=' + encodeURIComponent(targetId));
            } else {
                $('#modalAskSimulatorBtn').addClass('d-none');
            }

            var sheetsContainer = $('#modalSheetsList');
            sheetsContainer.empty();
            if (doc.tables && Array.isArray(doc.tables) && doc.tables.length > 0) {
                doc.tables.forEach(function (tbl) {
                    sheetsContainer.append('<span class="badge bg-light text-dark border px-3 py-2"><i class="ri-table-line text-primary me-1"></i>' + tbl + '</span>');
                });
            } else if (doc.sheets && Array.isArray(doc.sheets) && doc.sheets.length > 0) {
                doc.sheets.forEach(function (s) {
                    sheetsContainer.append('<span class="badge bg-light text-dark border px-3 py-2"><i class="ri-file-table-line text-success me-1"></i>' + s + '</span>');
                });
            } else {
                sheetsContainer.append('<span class="badge bg-light text-dark border px-3 py-2">1 Sheet</span>');
            }

            var summaryText = doc.schema_summary || 'No schema catalog recorded for this document.';
            $('#modalSchemaSummary').text(summaryText);

            var modalEl = document.getElementById('documentDetailsModal');
            if (modalEl) {
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }
        }

        $(document).ready(function () {
            var table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                fixedHeader: true,
                "pageLength": 20,
                "lengthMenu": [20, 50, 100, 500],
                ajax: {
                    url: listUrl,
                    type: 'GET'
                },
                columns: [
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'title',
                        name: 'title',
                        orderable: true,
                        render: function (data) {
                            var safe = $('<div>').text(data || '').html();
                            return '<div class="doc-title-cell" title="' + safe + '">' + safe + '</div>';
                        }
                    },
                    {
                        data: 'original_filename',
                        name: 'original_filename',
                        orderable: true,
                        render: function (data) {
                            var safe = $('<div>').text(data || '').html();
                            return '<div class="doc-filename-cell" title="' + safe + '">' + safe + '</div>';
                        }
                    },
                    {
                        data: 'purpose_badge',
                        name: 'purpose_badge',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'file_size',
                        name: 'file_size',
                        orderable: true
                    },
                    {
                        data: 'sheets_count',
                        name: 'sheets_count',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        orderable: true
                    },
                    {
                        data: 'status_badge',
                        name: 'status_badge',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action-btn',
                        name: 'action-btn',
                        orderable: false,
                        searchable: false,
                        render: function (data) {
                            if (!data) return '';
                            var btns = '<div class="action-btn d-flex align-items-center gap-1">';

                            // 1. Schema & Details Button
                            var safeDataStr = JSON.stringify(data).replace(/"/g, '&quot;');
                            btns += '<button type="button" class="btn btn-sm btn-info text-white" title="View Schema & Details" onclick=\'openDocumentDetails(' + safeDataStr + ')\' style="padding: 2px 8px; font-size: 12px;"><i class="ri-file-list-3-line"></i></button>';

                            if (!data.trashed) {
                                // 2. "Ask in Simulator" shortcut (when active)
                                if (data.is_active) {
                                    var targetId = data.source_id || data.id;
                                    btns += '<a href="' + SITEURL + '/dashboard/simulator?file_id=' + encodeURIComponent(targetId) + '" class="btn btn-sm btn-primary text-white" title="Ask Questions in Simulator" style="padding: 2px 8px; font-size: 12px;"><i class="ri-chat-smile-2-line"></i></a>';
                                }

                                // 3. Ingest / Retry Button: For pending or failed documents
                                if ((data.has_failed || data.is_pending) && data.can_update) {
                                    var btnTitle = data.is_pending ? 'Process Ingestion Now' : 'Retry ingestion';
                                    var btnClass = data.is_pending ? 'btn-primary' : 'btn-warning';
                                    var btnIcon = data.is_pending ? 'ri-play-circle-line' : 'ri-refresh-line';
                                    btns += '<form action="' + SITEURL + '/dashboard/documents/' + data.id +
                                        '/retry" method="POST" style="display: inline;">' +
                                        '@csrf' +
                                        '<button type="submit" class="btn btn-sm ' + btnClass + '" title="' + btnTitle + '" style="padding: 2px 8px; font-size: 12px;"><i class="' + btnIcon + '"></i></button>' +
                                        '</form>';
                                }

                                // 4. Delete Button: Only when user has delete permission
                                if (data.can_delete) {
                                    btns += '<form action="' + SITEURL + '/dashboard/documents/' + data.id +
                                        '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this document?\');">' +
                                        '@csrf' +
                                        '@method('DELETE')' +
                                        '<button type="submit" class="btn btn-sm btn-delete" title="Move to trash"><i class="ri-delete-bin-2-line"></i></button>' +
                                        '</form>';
                                }
                            } else {
                                // Restore Button: Only when user has update permission
                                if (data.can_update) {
                                    btns += '<form action="' + SITEURL + '/dashboard/documents/' + data.id +
                                        '/restore" method="POST" style="display: inline;">' +
                                        '@csrf' +
                                        '<button type="submit" class="btn btn-sm btn-success" title="Restore document"><i class="ri-refresh-line"></i></button>' +
                                        '</form>';
                                }
                            }

                            btns += '</div>';
                            return btns;
                        }
                    }
                ],
                order: [
                    [0, 'asc']
                ]
            });
        });
    </script>
@endpush