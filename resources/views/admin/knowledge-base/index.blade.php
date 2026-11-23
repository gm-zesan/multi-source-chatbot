@extends('admin.app')
@section('title')
    Knowledge Base
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <style>
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
            font-size: 11px;
            color: var(--color-text-muted);
        }
    </style>
@endpush

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Knowledge Base</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Knowledge Base</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @can(\App\Enums\Permissions\FAQPermission::UPDATE->value)
                <button type="button" class="app-btn app-btn-secondary" id="btn-reindex-all" onclick="triggerReindexAll()">
                    <i class="ri-refresh-line" id="reindex-btn-icon"></i>
                    <span id="reindex-btn-text">Reindex Knowledge Base</span>
                </button>
            @endcan
            @can(\App\Enums\Permissions\FAQPermission::CREATE->value)
                <a href="{{ route('faqs.create') }}" class="app-btn app-btn-secondary">
                    <i class="ri-add-line"></i> New FAQ / Policy
                </a>
            @endcan
            @can(\App\Enums\Permissions\KnowledgeDocumentPermission::CREATE->value)
                <a href="{{ route('documents.create') }}" class="app-btn app-btn-primary">
                    <i class="ri-upload-cloud-2-line"></i> Upload Data Source
                </a>
            @endcan
        </div>
    </div>

    {{-- Content Type Navigation Pills --}}
    <div class="flex items-center gap-1 p-1 bg-surface border border-border rounded-lg overflow-x-auto w-fit">
        @if ($canViewFaqs)
            <button type="button" class="kb-pill-btn app-btn {{ $activeTab === 'faqs' ? 'app-btn-primary' : 'app-btn-secondary' }} text-xs" id="tab-btn-faqs" onclick="switchTab('faqs')">
                <i class="ri-question-answer-line"></i> FAQs (Q&A)
            </button>
            <button type="button" class="kb-pill-btn app-btn {{ $activeTab === 'policies' ? 'app-btn-primary' : 'app-btn-secondary' }} text-xs" id="tab-btn-policies" onclick="switchTab('policies')">
                <i class="ri-shield-check-line"></i> Policies
            </button>
            <button type="button" class="kb-pill-btn app-btn {{ $activeTab === 'business_info' ? 'app-btn-primary' : 'app-btn-secondary' }} text-xs" id="tab-btn-business_info" onclick="switchTab('business_info')">
                <i class="ri-building-line"></i> Business Information
            </button>
        @endif
        @if ($canViewDocs)
            <button type="button" class="kb-pill-btn app-btn {{ $activeTab === 'data_sources' ? 'app-btn-primary' : 'app-btn-secondary' }} text-xs" id="tab-btn-data_sources" onclick="switchTab('data_sources')">
                <i class="ri-bar-chart-2-line"></i> Analytics Data Sources
            </button>
            <button type="button" class="kb-pill-btn app-btn {{ $activeTab === 'product_catalog' ? 'app-btn-primary' : 'app-btn-secondary' }} text-xs" id="tab-btn-product_catalog" onclick="switchTab('product_catalog')">
                <i class="ri-store-2-line text-emerald-500"></i> Product Catalog & Live Stock
            </button>
        @endif
        @can(\App\Enums\Permissions\UnansweredQuestionPermission::VIEW->value)
            <a href="{{ route('unanswered-questions.index') }}" class="kb-pill-btn app-btn app-btn-secondary text-xs">
                <i class="ri-question-mark"></i> Unanswered Queries
            </a>
        @endcan
    </div>

    {{-- Section 1: FAQ, Policies, and Business Information Table --}}
    <div id="faq-table-container" class="{{ in_array($activeTab, ['faqs', 'policies', 'business_info']) ? '' : 'hidden' }}">
        <div class="app-card table-card">
            <div class="app-card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Semantic Knowledge Items</h2>
                    <p class="text-2xs text-text-muted">Grounded FAQs, operational policies, and storefront information</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if (!empty($isSuperAdmin) && isset($workspaces) && count($workspaces) > 0)
                        <select id="faq-workspace-filter" class="app-select hub-workspace-select w-44 text-xs" onchange="reloadTablesOnWorkspaceChange(this.value)">
                            <option value="">All Workspaces</option>
                            @foreach ($workspaces as $ws)
                                <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select id="faq-status-filter" class="app-select w-36 text-xs" onchange="reloadFaqTable()">
                        <option value="">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive / Draft</option>
                        <option value="trashed">Deleted</option>
                    </select>
                </div>
            </div>
            <div class="app-card-body p-0">
                <div class="overflow-x-auto p-3">
                    <table class="app-table w-full" id="faq-data-table">
                        <thead>
                            <tr>
                                <th style="width: 70px;" class="no-sort">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" class="app-checkbox app-select-all-checkbox" title="Select All">
                                        <span>#</span>
                                    </div>
                                </th>
                                <th>Question / Title</th>
                                <th style="width: 140px;">Type</th>
                                <th style="width: 70px;">Priority</th>
                                <th style="width: 60px;">Hits</th>
                                <th style="width: 130px;">Status</th>
                                <th style="width: 90px;" class="no-sort text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($canViewDocs)
    {{-- Section 2: Analytics Data Sources Table --}}
    <div id="doc-table-container" class="{{ in_array($activeTab, ['data_sources', 'documents']) ? '' : 'hidden' }}">
        <div class="app-card table-card overflow-hidden">
            <div class="app-card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xs font-semibold text-light-text dark:text-dark-text uppercase tracking-wider">Analytics Data Sources</h2>
                    <p class="text-2xs text-light-muted dark:text-dark-muted">Spreadsheets registered for automated multi-source SQL reporting</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if (!empty($isSuperAdmin) && isset($workspaces) && count($workspaces) > 0)
                        <select id="doc-workspace-filter" class="app-select hub-workspace-select w-44 text-xs" onchange="reloadTablesOnWorkspaceChange(this.value)">
                            <option value="">All Workspaces</option>
                            @foreach ($workspaces as $ws)
                                <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select id="doc-status-filter" class="app-select w-36 text-xs" onchange="reloadDocTable()">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending Ingestion</option>
                        <option value="failed">Failed</option>
                        <option value="trashed">Deleted</option>
                    </select>
                </div>
            </div>
            <div class="app-card-body p-0">
                <div class="overflow-x-auto p-3">
                    <table class="app-table w-full" id="doc-data-table">
                        <thead>
                            <tr>
                                <th style="width: 70px;" class="no-sort">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" class="app-checkbox app-select-all-checkbox" title="Select All">
                                        <span>#</span>
                                    </div>
                                </th>
                                <th>Title</th>
                                <th>Original Filename</th>
                                <th style="width: 80px;">Size</th>
                                <th style="width: 70px;">Sheets</th>
                                <th style="width: 100px;">Uploaded At</th>
                                <th style="width: 120px;">Status</th>
                                <th style="width: 120px;" class="no-sort text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 3: Live Store Product Catalog & Inventory Quick Stock Toggle --}}
    <div id="product-catalog-container" class="{{ $activeTab === 'product_catalog' ? '' : 'hidden' }}">
        <div class="app-card table-card">
            <div class="app-card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xs font-semibold text-light-text dark:text-dark-text uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ri-store-2-line text-emerald-500"></i> Store Products & Live Stock
                    </h2>
                    <p class="text-2xs text-light-muted dark:text-dark-muted">Real-time inventory control. Toggle products In/Out of Stock instantly without re-uploading spreadsheets.</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="flex items-center gap-2 mr-3 px-2.5 py-1 bg-surface-hover rounded-md border border-border">
                        <span class="text-2xs text-text-muted">Total: <strong id="product-stat-total" class="text-text-primary">0</strong></span>
                        <span class="text-border">|</span>
                        <span class="text-2xs text-emerald-600 dark:text-emerald-400 font-medium">In Stock: <strong id="product-stat-instock">0</strong></span>
                        <span class="text-border">|</span>
                        <span class="text-2xs text-rose-600 dark:text-rose-400 font-medium">Out of Stock: <strong id="product-stat-outofstock">0</strong></span>
                    </div>
                    <button type="button" class="app-btn app-btn-secondary text-2xs flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#externalStoreSyncModal">
                        <i class="ri-refresh-line text-brand-500"></i>
                        <span>Shopify / WooCommerce Sync</span>
                    </button>
                    <select id="product-status-filter" class="app-select w-36 text-xs" onchange="reloadProductTable()">
                        <option value="all">All Products</option>
                        <option value="in_stock">In Stock Only</option>
                        <option value="out_of_stock">Out of Stock Only</option>
                    </select>
                </div>
            </div>
            <div class="app-card-body p-0">
                <div class="overflow-x-auto p-3">
                    <table class="app-table w-full" id="product-catalog-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Product Name & Source</th>
                                <th style="width: 110px;">Category</th>
                                <th style="width: 110px;">Unit Price</th>
                                <th>Specifications & Dynamic Attributes</th>
                                <th style="width: 90px;">Stock</th>
                                <th style="width: 170px;" class="no-sort text-center">Quick Stock Toggle</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: 1-Click Shopify / WooCommerce Catalog Sync --}}
    <div class="modal fade" id="externalStoreSyncModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
                <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                    <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                        <i class="ri-store-2-fill text-brand-500"></i>
                        <span>1-Click External Catalog Bridge</span>
                    </h3>
                    <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                        <i class="ri-close-line text-lg"></i>
                    </button>
                </div>

                <form action="{{ route('integrations.catalog-sync') }}" method="POST">
                    @csrf
                    <div class="space-y-3.5 text-xs">
                        <div>
                            <label class="block font-semibold text-light-text dark:text-dark-text mb-1">
                                Store Platform <span class="text-rose-500">*</span>
                            </label>
                            <select name="platform" id="sync_platform" class="app-select w-full text-xs" onchange="toggleStoreCredentials(this.value)">
                                <option value="shopify">Shopify Store</option>
                                <option value="woocommerce">WooCommerce Store</option>
                            </select>
                        </div>

                        {{-- Shopify Fields --}}
                        <div id="shopify_fields" class="space-y-3">
                            <div>
                                <label class="block font-semibold text-light-text dark:text-dark-text mb-1">
                                    Store Domain <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="credentials[shop_domain]" placeholder="e.g. apex-brand.myshopify.com" class="app-input w-full text-xs font-mono">
                            </div>
                            <div>
                                <label class="block font-semibold text-light-text dark:text-dark-text mb-1">
                                    Admin API Access Token <span class="text-rose-500">*</span>
                                </label>
                                <input type="password" name="credentials[access_token]" placeholder="shpat_..." class="app-input w-full text-xs font-mono">
                            </div>
                        </div>

                        {{-- WooCommerce Fields --}}
                        <div id="woocommerce_fields" class="space-y-3 hidden">
                            <div>
                                <label class="block font-semibold text-light-text dark:text-dark-text mb-1">
                                    Store URL <span class="text-rose-500">*</span>
                                </label>
                                <input type="url" name="credentials[store_url]" placeholder="https://myfashionstore.com" class="app-input w-full text-xs font-mono">
                            </div>
                            <div>
                                <label class="block font-semibold text-light-text dark:text-dark-text mb-1">
                                    Consumer Key <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="credentials[consumer_key]" placeholder="ck_..." class="app-input w-full text-xs font-mono">
                            </div>
                            <div>
                                <label class="block font-semibold text-light-text dark:text-dark-text mb-1">
                                    Consumer Secret <span class="text-rose-500">*</span>
                                </label>
                                <input type="password" name="credentials[consumer_secret]" placeholder="cs_..." class="app-input w-full text-xs font-mono">
                            </div>
                        </div>

                        <div class="p-2.5 rounded-lg bg-light-canvas/70 dark:bg-dark-canvas/70 border border-light-border dark:border-dark-border text-3xs space-y-1">
                            <div class="font-semibold text-light-text dark:text-dark-text">Automated Real-Time Webhooks:</div>
                            <div>Shopify: <code class="text-brand-500 select-all">{{ url('/api/v1/integrations/shopify/webhook') }}</code></div>
                            <div>WooCommerce: <code class="text-brand-500 select-all">{{ url('/api/v1/integrations/woocommerce/webhook') }}</code></div>
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                        <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5">
                            <i class="ri-refresh-line"></i>
                            <span>Sync Products & Stock Now</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
        <!-- Document Preview & Metadata Details Modal -->
        <div class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs overflow-y-auto" id="documentDetailsModal" tabindex="-1" aria-labelledby="documentDetailsModalLabel" aria-hidden="true" style="display: none;">
            <div class="modal-dialog w-full max-w-5xl app-card shadow-modal overflow-hidden transition-all duration-200 my-auto">
                <div class="modal-content border-0 bg-transparent flex flex-col max-h-[90vh]">
                    <div class="app-card-header flex items-center justify-between border-b border-light-border dark:border-dark-border px-4 py-3 bg-light-surface dark:bg-dark-surface">
                        <div class="flex items-center gap-2">
                            <i id="modalFileIcon" class="ri-file-search-line text-brand-500 text-lg"></i>
                            <div>
                                <h5 class="text-xs font-semibold text-light-text dark:text-dark-text mb-0" id="documentDetailsModalLabel">Document Preview & Details</h5>
                                <div class="flex items-center gap-1 flex-wrap mt-0.5">
                                    <span class="app-badge app-badge-secondary font-mono text-2xs" id="modalDocFilename"></span>
                                    <span class="app-badge app-badge-primary text-2xs d-none" id="modalDocWorkspace"></span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text text-base p-1" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="modal-body p-4 bg-light-surface dark:bg-dark-surface overflow-y-auto">
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
                                    <div class="overflow-x-auto border border-light-border dark:border-dark-border rounded-md bg-light-surface dark:bg-dark-surface"
                                        style="max-height: 480px; overflow-y: auto;">
                                        <table class="app-table w-full text-nowrap"
                                            id="previewTable">
                                            <thead class="sticky-top" id="previewTableThead"
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
                    <div class="app-card-footer flex justify-between items-center py-2 px-3">
                        <div class="flex items-center gap-3">
                            <span class="text-light-muted dark:text-dark-muted text-2xs" id="modalSourceIdText"></span>
                            <a href="#" id="modalDownloadBtn"
                                class="app-btn app-btn-secondary !h-7 !text-2xs flex items-center gap-1" target="_blank"
                                download>
                                <i class="ri-download-2-line"></i> Download Original File
                            </a>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('simulator.index') }}"
                                class="app-btn app-btn-primary !h-7 !text-2xs flex items-center gap-1" id="modalAskSimulatorBtn">
                                <i class="ri-chat-smile-2-line"></i> Ask in Simulator
                            </a>
                            <button type="button" class="app-btn app-btn-secondary !h-7 !text-2xs" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spreadsheet Column Mapping Confirmation Modal (Phase 2) -->
        <div class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs overflow-y-auto" id="spreadsheetMappingModal" tabindex="-1" aria-labelledby="spreadsheetMappingModalLabel" aria-hidden="true" data-bs-backdrop="static" style="display: none;">
            <div class="modal-dialog w-full max-w-5xl app-card shadow-modal overflow-hidden transition-all duration-200 my-auto">
                <div class="modal-content border-0 bg-transparent flex flex-col max-h-[90vh]">
                    <div class="app-card-header flex items-center justify-between border-b border-light-border dark:border-dark-border px-4 py-3 bg-light-surface dark:bg-dark-surface">
                        <div class="flex items-center gap-2">
                            <i class="ri-table-line text-amber-500 text-lg"></i>
                            <div>
                                <h5 class="text-xs font-semibold text-light-text dark:text-dark-text mb-0" id="spreadsheetMappingModalLabel">Confirm Spreadsheet Schema & Column Mappings</h5>
                                <div class="text-light-muted dark:text-dark-muted text-2xs" id="mappingModalSubtitle">Configure header rows, verify inferred column categories, and resolve ambiguities.</div>
                            </div>
                        </div>
                        <button type="button" class="text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text text-base p-1" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="modal-body p-4 bg-light-surface dark:bg-dark-surface overflow-y-auto">
                        <div id="mappingModalLoader" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="text-muted small mt-2">Inspecting spreadsheet structure...</div>
                        </div>
                        <div id="mappingModalContent" class="d-none">
                            <!-- Ambiguity Warning Banner -->
                            <div class="alert alert-warning border-warning d-flex align-items-start gap-2 mb-3">
                                <i class="ri-alert-line fs-5 text-warning flex-shrink-0 mt-1"></i>
                                <div>
                                    <strong>Owner Confirmation Required:</strong>
                                    <div class="small">This spreadsheet contains title banners, non-standard headers, or ambiguous column types. Review the header row and column mappings below before ingesting. All unmapped columns will be safely preserved in custom attributes with zero data loss.</div>
                                </div>
                            </div>

                            <!-- Header Selection & Settings Row -->
                            <div class="card border mb-3">
                                <div class="card-body p-3 bg-light">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-dark small mb-1">
                                                <i class="ri-file-list-line me-1 text-primary"></i>Detected Header Row
                                            </label>
                                            <select class="form-select form-select-sm" id="mappingHeaderRowSelect">
                                                <!-- Populated dynamically with candidate headers -->
                                            </select>
                                            <div class="form-text text-muted" style="font-size: 11px;">Candidate header rows discovered by density, keyword scoring, and lookahead consistency.</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold text-dark small mb-1">
                                                <i class="ri-git-merge-line me-1 text-info"></i>Conflict Mode
                                            </label>
                                            <select class="form-select form-select-sm" id="mappingConflictModeSelect">
                                                <option value="skip" selected>Skip Existing (Safe)</option>
                                                <option value="overwrite">Overwrite By Identifier</option>
                                                <option value="append">Append All Rows</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold text-dark small mb-1">
                                                <i class="ri-pages-line me-1 text-success"></i>Worksheet
                                            </label>
                                            <select class="form-select form-select-sm" id="mappingSheetSelect">
                                                <!-- Populated dynamically with workbook sheets -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Column Mapping Table -->
                            <h6 class="font-semibold text-xs text-light-text dark:text-dark-text mb-2 flex items-center justify-between">
                                <span class="flex items-center gap-1.5"><i class="ri-layout-column-line text-brand-500"></i>Column Categorization & Projections</span>
                                <span class="app-badge app-badge-secondary !text-2xs font-mono" id="mappingColumnCountBadge">0 columns</span>
                            </h6>
                            <div class="overflow-x-auto border border-light-border dark:border-dark-border rounded-md mb-3" style="max-height: 380px;">
                                <table class="app-table w-full align-middle mb-0" id="mappingColumnsTable">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 25%;">Spreadsheet Column</th>
                                            <th style="width: 15%;">Inferred Type</th>
                                            <th style="width: 25%;">Sample Values</th>
                                            <th style="width: 15%;">Confidence</th>
                                            <th style="width: 20%;">Semantic Mapping</th>
                                        </tr>
                                    </thead>
                                    <tbody id="mappingColumnsTableBody">
                                        <!-- Rows injected dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="app-card-footer flex justify-between items-center py-2 px-4">
                        <button type="button" class="app-btn app-btn-secondary !h-7 !text-2xs" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="app-btn app-btn-primary !h-7 !text-2xs flex items-center gap-1" id="btnConfirmAndIngest">
                            <i class="ri-check-line"></i> Confirm & Start Ingestion
                        </button>
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
            let productDataTable = null;

            function switchTab(tabName) {
                currentTab = tabName;
                $('.kb-pill-btn').removeClass('active app-btn-primary').addClass('app-btn-secondary');
                $(`#tab-btn-${tabName}`).addClass('active app-btn-primary').removeClass('app-btn-secondary');

                // Update URL without full reload
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url);

                if (['faqs', 'policies', 'business_info'].includes(tabName)) {
                    $('#faq-table-container').removeClass('hidden d-none');
                    $('#doc-table-container').addClass('hidden d-none');
                    $('#product-catalog-container').addClass('hidden d-none');
                    reloadFaqTable();
                } else if (tabName === 'product_catalog') {
                    $('#faq-table-container').addClass('hidden d-none');
                    $('#doc-table-container').addClass('hidden d-none');
                    $('#product-catalog-container').removeClass('hidden d-none');
                    initOrReloadProductTable();
                } else {
                    $('#faq-table-container').addClass('hidden d-none');
                    $('#doc-table-container').removeClass('hidden d-none');
                    $('#product-catalog-container').addClass('hidden d-none');
                    reloadDocTable();
                }
            }

            function reloadProductTable() {
                if (productDataTable) {
                    productDataTable.ajax.reload(null, false);
                }
            }

            function initOrReloadProductTable() {
                if (productDataTable) {
                    productDataTable.ajax.reload(null, false);
                    return;
                }

                productDataTable = $('#product-catalog-table').DataTable({
                    processing: true,
                    serverSide: false,
                    order: [[1, 'asc']],
                    ajax: {
                        url: '{{ route("knowledge-base.products.data") }}',
                        data: function (d) {
                            d.status_filter = $('#product-status-filter').val();
                        },
                        dataSrc: function (json) {
                            if (json.stats) {
                                $('#product-stat-total').text(json.stats.total || 0);
                                $('#product-stat-instock').text(json.stats.in_stock || 0);
                                $('#product-stat-outofstock').text(json.stats.out_of_stock || 0);
                            }
                            return json.data || [];
                        },
                        error: function (xhr, error, code) {
                            console.error('Product Catalog DataTable Error:', error, code, xhr);
                        }
                    },
                    columns: [
                        {
                            data: null,
                            orderable: false,
                            render: function (data, type, row, meta) {
                                return `<span class="text-xs text-text-muted font-mono">${meta.row + 1}</span>`;
                            }
                        },
                        {
                            data: 'name',
                            render: function (data, type, row) {
                                return `
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs font-semibold text-text-primary">${$('<div>').text(data).html()}</span>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="app-badge app-badge-secondary font-mono text-3xs">${$('<div>').text(row.source_document).html()}</span>
                                            <span class="text-3xs text-text-muted">${row.created_at || ''}</span>
                                        </div>
                                    </div>
                                `;
                            }
                        },
                        {
                            data: 'category',
                            render: function (data) {
                                return `<span class="app-badge app-badge-secondary text-2xs">${$('<div>').text(data || 'General').html()}</span>`;
                            }
                        },
                        {
                            data: 'price_formatted',
                            render: function (data) {
                                return `<span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 font-mono">${$('<div>').text(data).html()}</span>`;
                            }
                        },
                        {
                            data: 'specs',
                            render: function (data) {
                                return data ? `<span class="text-2xs text-text-secondary">${$('<div>').text(data).html()}</span>` : `<span class="text-3xs text-text-muted">—</span>`;
                            }
                        },
                        {
                            data: 'stock',
                            render: function (data, type, row) {
                                const isZero = !row.is_in_stock || data === 0;
                                const badgeClass = isZero ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-slate-100 dark:bg-slate-800 text-text-primary border border-border';
                                return `<span class="app-badge ${badgeClass} text-xs font-mono font-semibold">${data} pcs</span>`;
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            className: 'text-center',
                            render: function (data, type, row) {
                                const inStock = row.is_in_stock;
                                const btnClass = inStock
                                    ? 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-xs'
                                    : 'bg-rose-500 hover:bg-rose-600 text-white shadow-xs';
                                const icon = inStock ? 'ri-checkbox-circle-line' : 'ri-close-circle-line';
                                const label = inStock ? 'In Stock (Click to Disable)' : 'Out of Stock (Click to Enable)';

                                return `
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" class="quick-stock-toggle-btn app-btn ${btnClass} !h-7 !px-2.5 !text-2xs flex items-center gap-1 transition-all" data-id="${row.id}" title="${label}">
                                            <i class="${icon}"></i>
                                            <span>${inStock ? 'In Stock' : 'Stock Out'}</span>
                                        </button>
                                    </div>
                                `;
                            }
                        }
                    ]
                });
            }

            window.selectFaqStatusFacet = function(el, status) {
                document.querySelectorAll('[data-faq-chip]').forEach(function(c) { c.classList.remove('active'); });
                if (el) el.classList.add('active');
                $('#faq-status-filter').val(status);
                reloadFaqTable();
            };

            window.selectDocStatusFacet = function(el, status) {
                document.querySelectorAll('[data-doc-chip]').forEach(function(c) { c.classList.remove('active'); });
                if (el) el.classList.add('active');
                $('#doc-status-filter').val(status);
                reloadDocTable();
            };

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

            function reloadTablesOnWorkspaceChange(val) {
                $('.hub-workspace-select').val(val);
                reloadFaqTable();
                reloadDocTable();
            }

            function triggerResync(faqId) {
                swal({
                    title: "Re-sync to Vector Engine?",
                    text: "This will regenerate embeddings and synchronize this item with Typesense for AI retrieval.",
                    icon: "info",
                    buttons: ["Cancel", "Sync Now"],
                }).then((willSync) => {
                    if (willSync) {
                        fetch(`${SITEURL}/dashboard/faqs/${faqId}/resync`, {
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

            function triggerReindexAll() {
                var selectedWorkspace = $('#faq-workspace-filter').length ? $('#faq-workspace-filter').val() : '';
                var confirmMsg = selectedWorkspace 
                    ? "This will regenerate vector embeddings and re-sync all active FAQs in the selected workspace directly to Typesense."
                    : "This will regenerate vector embeddings and re-sync all active FAQs in the knowledge base directly to Typesense.";

                swal({
                    title: "Reindex Knowledge Base?",
                    text: confirmMsg,
                    icon: "info",
                    buttons: {
                        cancel: "Cancel",
                        confirm: {
                            text: "Yes, Reindex Now",
                            value: true,
                            className: "btn-primary",
                        }
                    },
                    dangerMode: false,
                }).then((willReindex) => {
                    if (willReindex) {
                        var btn = $('#btn-reindex-all');
                        var icon = $('#reindex-btn-icon');
                        var text = $('#reindex-btn-text');

                        btn.prop('disabled', true);
                        icon.addClass('ri-spin');
                        text.text('Reindexing...');

                        fetch('{{ route("knowledge-base.reindex") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                workspace_id: selectedWorkspace
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            btn.prop('disabled', false);
                            icon.removeClass('ri-spin');
                            text.text('Reindex Knowledge Base');

                            if (data.success) {
                                swal("Reindex Complete!", data.message || `Successfully synced ${data.synced} FAQs.`, "success");
                                reloadFaqTable();
                            } else {
                                swal("Reindex Failed", data.message || "Failed to complete reindexing.", "error");
                            }
                        })
                        .catch(err => {
                            btn.prop('disabled', false);
                            icon.removeClass('ri-spin');
                            text.text('Reindex Knowledge Base');
                            swal("Error", "Network or server error during re-index.", "error");
                        });
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                @if ($canViewFaqs)
                // 1. Initialize FAQ & Policies DataTable
                faqDataTable = $('#faq-data-table').DataTable({
                    processing: true,
                    serverSide: true,
                    order: [[1, 'asc']],
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
                            d.workspace_id = $('#faq-workspace-filter').length ? $('#faq-workspace-filter').val() : '';
                        },
                        error: function (xhr, error, code) {
                            console.error('FAQ DataTable Error:', error, code, xhr);
                        }
                    },
                    columns: [
                        {
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                var id = (row && row['action-btn'] && row['action-btn'].id) ? row['action-btn'].id : data;
                                return `<div class="flex items-center gap-2">
                                    <input type="checkbox" class="app-checkbox app-row-checkbox" data-id="${id}" value="${id}">
                                    <span class="text-2xs text-light-muted dark:text-dark-muted font-mono">${data}</span>
                                </div>`;
                            }
                        },
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
                                            <button onclick="triggerResync('${id}')" class="app-action-btn bg-amber-500/15 text-amber-500 hover:bg-amber-500 hover:text-white" title="Retry Sync (${error || 'Failed'})">
                                                <i class="ri-refresh-line"></i>
                                            </button>
                                        `;
                                } else if (!trashed) {
                                    resyncBtn = `
                                            <button onclick="triggerResync('${id}')" class="app-action-btn bg-brand-500/15 text-brand-500 hover:bg-brand-500 hover:text-white" title="Re-sync Vector Index">
                                                <i class="ri-refresh-line"></i>
                                            </button>
                                        `;
                                }

                                if (trashed) {
                                    return `
                                            <div class="flex items-center justify-end gap-1.5">
                                                <form action="${SITEURL}/dashboard/faqs/${id}/restore" method="POST" style="display:inline-block;">
                                                    @csrf
                                                    <button type="submit" class="app-action-btn bg-emerald-500/15 text-emerald-500 hover:bg-emerald-500 hover:text-white" title="Restore FAQ">
                                                        <i class="ri-refresh-line"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        `;
                                }

                                return `
                                        <div class="flex items-center justify-end gap-1.5">
                                            ${resyncBtn}
                                            <a href="${SITEURL}/dashboard/faqs/${id}/edit" class="app-action-btn bg-sky-500/15 text-sky-500 hover:bg-sky-500 hover:text-white" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="${SITEURL}/dashboard/faqs/${id}" method="POST" onsubmit="return confirm('Are you sure you want to delete this FAQ?');" style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="app-action-btn bg-red-500/15 text-red-500 hover:bg-red-500 hover:text-white" title="Delete">
                                                    <i class="ri-delete-bin-2-line"></i>
                                                </button>
                                            </form>
                                        </div>
                                    `;
                            }
                        }
                    ]
                });
                @endif

                @if ($canViewDocs)
                // 2. Initialize Documents & Data Sources DataTable
                docDataTable = $('#doc-data-table').DataTable({
                    processing: true,
                    serverSide: true,
                    order: [[1, 'asc']],
                    ajax: {
                        url: '{{ route("knowledge-base.index") }}',
                        data: function (d) {
                            d.source = 'documents';
                            d.purpose = 'data_analytics';
                            d.status = $('#doc-status-filter').val();
                            d.workspace_id = $('#doc-workspace-filter').length ? $('#doc-workspace-filter').val() : '';
                        },
                        error: function (xhr, error, code) {
                            console.error('Doc DataTable Error:', error, code, xhr);
                        }
                    },
                    columns: [
                        {
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                var id = (row && row['action-btn'] && row['action-btn'].id) ? row['action-btn'].id : data;
                                return `<div class="flex items-center gap-2">
                                    <input type="checkbox" class="app-checkbox app-row-checkbox" data-id="${id}" value="${id}">
                                    <span class="text-2xs text-light-muted dark:text-dark-muted font-mono">${data}</span>
                                </div>`;
                            }
                        },
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
                                            <div class="flex items-center justify-end gap-1.5">
                                                <form action="${SITEURL}/dashboard/documents/${id}/restore" method="POST" style="display:inline-block;">
                                                    @csrf
                                                    <button type="submit" class="app-action-btn bg-emerald-500/15 text-emerald-500 hover:bg-emerald-500 hover:text-white" title="Restore Document">
                                                        <i class="ri-refresh-line"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        `;
                                }

                                var btns = '<div class="flex items-center justify-end gap-1.5">';

                                // 1. Details & Interactive Preview Modal Button
                                btns += `<button type="button" class="app-action-btn bg-brand-500/15 text-brand-500 hover:bg-brand-500 hover:text-white btn-view-doc" data-id="${id}" title="View Document & Data"><i class="ri-eye-line"></i></button>`;

                                // 2. Ask in Simulator shortcut (when active)
                                if (data.is_active) {
                                    var targetId = data.source_id || data.id;
                                    btns += `<a href="${SITEURL}/dashboard/simulator?file_id=${encodeURIComponent(targetId)}" class="app-action-btn bg-emerald-500/15 text-emerald-500 hover:bg-emerald-500 hover:text-white" title="Ask Questions in Simulator"><i class="ri-chat-smile-2-line"></i></a>`;
                                }

                                // 2.5 Review & Confirm Mapping (for spreadsheets requiring owner mapping)
                                if (data.requires_mapping && data.can_update) {
                                    btns += `<button type="button" class="app-action-btn bg-amber-500/15 text-amber-500 hover:bg-amber-500 hover:text-white btn-review-mapping" data-id="${id}" title="Review & Confirm Column Mapping"><i class="ri-table-line"></i></button>`;
                                }

                                // 3. Retry / Ingest Button (for pending or failed documents)
                                if ((data.has_failed || data.is_pending) && data.can_update) {
                                    var btnTitle = data.is_pending ? 'Ingest & Activate Now' : 'Retry Ingestion';
                                    var btnClass = data.is_pending ? 'bg-brand-500/15 text-brand-500 hover:bg-brand-500' : 'bg-amber-500/15 text-amber-500 hover:bg-amber-500';
                                    var btnIcon = data.is_pending ? 'ri-play-circle-line' : 'ri-refresh-line';
                                    btns += `<form action="${SITEURL}/dashboard/documents/${id}/retry" method="POST" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="app-action-btn ${btnClass} hover:text-white" title="${btnTitle}">
                                                    <i class="${btnIcon}"></i>
                                                </button>
                                            </form>`;
                                }

                                // 4. Delete Button
                                if (data.can_delete) {
                                    btns += `<form action="${SITEURL}/dashboard/documents/${id}" method="POST" onsubmit="return confirm('Are you sure you want to delete this document?');" style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="app-action-btn bg-red-500/15 text-red-500 hover:bg-red-500 hover:text-white" title="Delete">
                                                    <i class="ri-delete-bin-2-line"></i>
                                                </button>
                                            </form>`;
                                }

                                btns += '</div>';
                                return btns;
                            }
                        }
                    ]
                });
                window.knowledgeDocumentsTable = docDataTable;
                @endif

                if (currentTab === 'product_catalog') {
                    initOrReloadProductTable();
                }

                // Quick Stock Toggle Click Handler
                $(document).on('click', '.quick-stock-toggle-btn', function (e) {
                    e.preventDefault();
                    var $btn = $(this);
                    var productId = $btn.attr('data-id');
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Updating...');

                    $.ajax({
                        url: `${SITEURL}/dashboard/knowledge-base/products/${productId}/toggle-stock`,
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Accept': 'application/json'
                        },
                        success: function (res) {
                            $btn.prop('disabled', false);
                            if (res.success) {
                                reloadProductTable();
                            } else {
                                alert(res.message || 'Failed to update product stock.');
                            }
                        },
                        error: function (xhr) {
                            $btn.prop('disabled', false);
                            alert(xhr.responseJSON?.message || 'Error updating product stock.');
                            reloadProductTable();
                        }
                    });
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
                if (doc.workspace_name) {
                    $('#modalDocWorkspace').text(doc.workspace_name).removeClass('d-none');
                } else {
                    $('#modalDocWorkspace').text('').addClass('d-none');
                }
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
                } else if (doc.requires_mapping) {
                    statusBadge = '<span class="badge bg-warning text-dark"><i class="ri-table-line me-1"></i>Requires Mapping</span>';
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
                } else if (doc.requires_mapping) {
                    $('#modalErrorSection').removeClass('d-none');
                    $('#modalErrorMsg').html('<span class="text-warning fw-bold">Ambiguous Schema Detected:</span> Header row or column semantic mappings require owner confirmation before ingestion.');
                    if (doc.can_update) {
                        $('#modalRetryForm').removeClass('d-none').attr('action', 'javascript:void(0)');
                        $('#modalRetryForm button').removeClass('btn-danger btn-primary').addClass('btn-warning text-dark').html('<i class="ri-table-line me-1"></i> Review & Confirm Mapping').off('click').on('click', function(ev) {
                            ev.preventDefault();
                            $('#documentDetailsModal').modal('hide');
                            openSpreadsheetMappingModal(doc.id);
                        });
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
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
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
                    var cells = Array.isArray(row) ? row : Object.values(row || {});
                    cells.forEach(function (cell) {
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
                    var cells = Array.isArray(row) ? row : Object.values(row || {});
                    return cells.some(function (cell) {
                        return String(cell || '').toLowerCase().indexOf(q) !== -1;
                    });
                });

                renderPreviewRows(filtered);
                $('#previewRowCounter').text(filtered.length + ' matched');
            }

            // Spreadsheet Mapping Review Modal Logic (Phase 2)
            window.activeMappingDocId = null;
            window.activeInspectionData = null;

            $(document).on('click', '.btn-review-mapping', function (e) {
                e.preventDefault();
                var id = $(this).attr('data-id');
                openSpreadsheetMappingModal(id);
            });

            function openSpreadsheetMappingModal(docId) {
                window.activeMappingDocId = docId;
                $('#mappingModalLoader').removeClass('d-none');
                $('#mappingModalContent').addClass('d-none');
                $('#btnConfirmAndIngest').prop('disabled', true);
                $('#spreadsheetMappingModal').modal('show');

                $.ajax({
                    url: `${SITEURL}/dashboard/documents/${docId}/spreadsheet/suggestions`,
                    type: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function (res) {
                        $('#mappingModalLoader').addClass('d-none');
                        if (!res.success) {
                            alert(res.message || 'Failed to load suggestions.');
                            $('#spreadsheetMappingModal').modal('hide');
                            return;
                        }
                        window.activeInspectionData = res;
                        renderMappingModalContent(res);
                        $('#mappingModalContent').removeClass('d-none');
                        $('#btnConfirmAndIngest').prop('disabled', false);
                    },
                    error: function (xhr) {
                        $('#mappingModalLoader').addClass('d-none');
                        alert(xhr.responseJSON?.message || 'Error loading spreadsheet details.');
                        $('#spreadsheetMappingModal').modal('hide');
                    }
                });
            }

            function renderMappingModalContent(data) {
                // Populate Header Row Select
                var headerSelect = $('#mappingHeaderRowSelect');
                headerSelect.empty();
                var candidates = data.candidate_headers || [];
                var activeHeaderIdx = data.header_row_index !== undefined ? data.header_row_index : 0;

                if (candidates.length === 0) {
                    headerSelect.append(`<option value="0" selected>Row 1 (Default / Single Header Row)</option>`);
                } else {
                    candidates.forEach(function (c) {
                        var scorePct = Math.round((c.score || 0) * 100);
                        var sampleCols = (c.candidate_headers || []).slice(0, 4).join(', ');
                        var label = `Row ${c.row_index + 1} (Score: ${scorePct}%) — [${sampleCols}]`;
                        var isSel = (c.row_index === activeHeaderIdx) ? 'selected' : '';
                        headerSelect.append(`<option value="${c.row_index}" ${isSel}>${$('<div>').text(label).html()}</option>`);
                    });
                }

                // Populate Columns
                var tbody = $('#mappingColumnsTableBody');
                tbody.empty();
                var columns = data.columns || [];
                $('#mappingColumnCountBadge').text(`${columns.length} columns`);

                var categories = data.available_categories || {};
                var existingMapping = data.existing_mapping || {};

                columns.forEach(function (col) {
                    var colName = col.name;
                    var inferredType = col.inferred_type || 'string';
                    var samples = (col.samples || []).slice(0, 3).join(', ');
                    var confidence = Math.round((col.confidence || 0) * 100);
                    var isAmbiguous = col.is_ambiguous;
                    var badgeClass = isAmbiguous || confidence < 65 ? 'bg-warning text-dark' : 'bg-success text-white';
                    var assigned = existingMapping[colName] || col.suggested_category || 'custom_attribute';

                    var selectHtml = `<select class="form-select form-select-sm col-category-select" data-col-name="${$('<div>').text(colName).html()}">`;
                    Object.keys(categories).forEach(function (catKey) {
                        var isSelected = (catKey === assigned) ? 'selected' : '';
                        selectHtml += `<option value="${catKey}" ${isSelected}>${categories[catKey]}</option>`;
                    });
                    selectHtml += '</select>';

                    var rowHtml = `
                        <tr>
                            <td class="fw-semibold text-dark">${$('<div>').text(colName).html()}</td>
                            <td><span class="badge bg-light text-dark border font-monospace">${inferredType}</span></td>
                            <td class="small text-muted text-truncate" style="max-width: 200px;" title="${$('<div>').text(samples).html()}">${$('<div>').text(samples || '-').html()}</td>
                            <td><span class="badge ${badgeClass}" style="font-size: 11px;">${confidence}%</span></td>
                            <td>${selectHtml}</td>
                        </tr>
                    `;
                    tbody.append(rowHtml);
                });
            }

            // Confirm Mapping Click Handler
            $(document).on('click', '#btnConfirmAndIngest', function () {
                if (!window.activeMappingDocId) return;

                var headerRowIndex = parseInt($('#mappingHeaderRowSelect').val(), 10) || 0;
                var conflictMode = $('#mappingConflictModeSelect').val() || 'skip';

                var mappings = {};
                $('.col-category-select').each(function () {
                    var colName = $(this).attr('data-col-name');
                    var category = $(this).val();
                    mappings[colName] = category;
                });

                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Queuing Ingestion...');

                $.ajax({
                    url: `${SITEURL}/dashboard/documents/${window.activeMappingDocId}/spreadsheet/confirm`,
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: {
                        header_row_index: headerRowIndex,
                        conflict_mode: conflictMode,
                        mappings: mappings
                    },
                    success: function (res) {
                        $('#spreadsheetMappingModal').modal('hide');
                        $btn.prop('disabled', false).html('<i class="ri-check-line"></i> Confirm & Start Ingestion');
                        if (window.knowledgeDocumentsTable) {
                            window.knowledgeDocumentsTable.ajax.reload(null, false);
                        }
                        if (typeof swal !== 'undefined') {
                            swal("Success", "Mapping confirmed! Spreadsheet ingestion has been queued.", "success");
                        } else {
                            alert("Mapping confirmed! Spreadsheet ingestion has been queued.");
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html('<i class="ri-check-line"></i> Confirm & Start Ingestion');
                        var msg = xhr.responseJSON?.message || 'Failed to confirm mapping.';
                        alert(msg);
                    }
                });
            });

            window.toggleStoreCredentials = function(platform) {
                if (platform === 'shopify') {
                    $('#shopify_fields').removeClass('hidden');
                    $('#woocommerce_fields').addClass('hidden');
                } else {
                    $('#shopify_fields').addClass('hidden');
                    $('#woocommerce_fields').removeClass('hidden');
                }
            };
        </script>
    @endpush