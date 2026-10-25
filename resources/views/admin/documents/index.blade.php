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

    <!-- Document Preview & Metadata Details Modal -->
    <div class="modal fade" id="documentDetailsModal" tabindex="-1" aria-labelledby="documentDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom py-2 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i id="modalFileIcon" class="ri-file-search-line text-primary fs-3"></i>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="documentDetailsModalLabel">Document Preview & Details</h5>
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-0 font-monospace" id="modalDocFilename" style="font-size: 11px;"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <!-- Nav Tabs: Preview vs Schema & Metadata -->
                    <ul class="nav nav-tabs nav-tabs-bordered mb-3" id="docModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-dark d-flex align-items-center gap-1 py-2 px-3" id="tab-btn-preview" data-bs-toggle="tab" data-bs-target="#paneFilePreview" type="button" role="tab" aria-controls="paneFilePreview" aria-selected="true">
                                <i class="ri-file-list-3-line text-primary"></i> File Content & Data
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold text-muted d-flex align-items-center gap-1 py-2 px-3" id="tab-btn-schema" data-bs-toggle="tab" data-bs-target="#paneSchemaDetails" type="button" role="tab" aria-controls="paneSchemaDetails" aria-selected="false">
                                <i class="ri-information-line text-info"></i> Schema & Metadata
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="docModalTabsContent">
                        <!-- TAB 1: File Content & Data Preview -->
                        <div class="tab-pane fade show active" id="paneFilePreview" role="tabpanel" aria-labelledby="tab-btn-preview">
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
                                            <span class="input-group-text bg-light"><i class="ri-search-line"></i></span>
                                            <input type="text" id="previewFilterInput" class="form-control" placeholder="Search in table..." onkeyup="filterPreviewTable(this.value)">
                                        </div>
                                        <span class="badge bg-light text-secondary border px-2 py-1" id="previewRowCounter" style="font-size: 11px;">0 rows</span>
                                    </div>
                                </div>
                                <div id="previewSheetStatusBanner" class="alert alert-light border py-1 px-3 small mb-2 d-flex align-items-center text-dark" style="font-size: 12px; background-color: #f8fafc;">
                                    <!-- Dynamic sheet info -->
                                </div>
                                <div class="table-responsive border rounded bg-white shadow-sm" style="max-height: 480px; overflow-y: auto;">
                                    <table class="table table-sm table-striped table-hover mb-0 text-nowrap" id="previewTable" style="font-size: 12.5px;">
                                        <thead class="table-light sticky-top border-bottom" id="previewTableThead" style="z-index: 2;"></thead>
                                        <tbody id="previewTableTbody"></tbody>
                                    </table>
                                </div>
                                <div class="text-muted small mt-2 d-flex justify-content-between align-items-center">
                                    <span><i class="ri-information-line me-1 text-primary"></i>Interactive data view directly extracted from storage.</span>
                                    <span id="previewTruncatedAlert" class="badge bg-warning-subtle text-warning border d-none">Preview capped at first 100 rows</span>
                                </div>
                            </div>

                            <!-- Text / Markdown Preview -->
                            <div id="previewTextBox" class="d-none">
                                <div class="p-3 bg-light border rounded font-monospace" style="max-height: 480px; overflow-y: auto; white-space: pre-wrap; font-size: 12.5px;" id="previewTextContent"></div>
                            </div>
                        </div>

                        <!-- TAB 2: Schema & Metadata -->
                        <div class="tab-pane fade" id="paneSchemaDetails" role="tabpanel" aria-labelledby="tab-btn-schema">
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
                                <div class="p-3 bg-white border rounded shadow-sm" style="max-height: 250px; overflow-y: auto;">
                                    <pre id="modalSchemaSummary" class="mb-0 text-dark font-monospace"
                                        style="white-space: pre-wrap; font-size: 12px;"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top d-flex justify-content-between align-items-center py-2 px-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted small" id="modalSourceIdText"></span>
                        <a href="#" id="modalDownloadBtn" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" target="_blank" download>
                            <i class="ri-download-2-line"></i> Download Original File
                        </a>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('simulator.index') }}" class="btn btn-sm btn-primary d-flex align-items-center gap-1"
                            id="modalAskSimulatorBtn">
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

            // Set Download button URL
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
                var tab = new bootstrap.Tab(previewTabTrigger);
                tab.show();
            }

            // Show modal
            var modalEl = document.getElementById('documentDetailsModal');
            if (modalEl) {
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }

            // Trigger dynamic preview content fetch
            loadDocumentContent(doc.id, null);
        }

        window.previewRowsCache = [];

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

            // Dynamically synchronize Virtual Tables & Sheets breakdown with parsed sheets from disk
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
                            window.docRecordsCache = window.docRecordsCache || {};
                            window.docRecordsCache[data.id] = data;
                            btns += '<button type="button" class="btn btn-sm btn-info text-white btn-view-doc" data-id="' + data.id + '" title="View Document & Details" style="padding: 2px 8px; font-size: 12px;"><i class="ri-file-list-3-line"></i></button>';

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

            $(document).on('click', '.btn-view-doc', function (e) {
                e.preventDefault();
                var id = $(this).attr('data-id');
                var doc = (window.docRecordsCache && window.docRecordsCache[id]) ? window.docRecordsCache[id] : null;
                if (doc) {
                    openDocumentDetails(doc);
                }
            });
        });
    </script>
@endpush