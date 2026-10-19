@extends('admin.app')
@section('title')
    Upload Knowledge Document
@endsection

@push('custom-style')
<style>
    .purpose-selection-card {
        cursor: pointer;
        border-radius: 12px;
        transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        user-select: none;
    }
    .purpose-selection-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }
    .purpose-selection-card.selected-analytics {
        border: 2px solid #0d6efd !important;
        background: linear-gradient(180deg, #f0f7ff 0%, #ffffff 100%) !important;
        box-shadow: 0 6px 18px rgba(13, 110, 253, 0.12) !important;
    }
    .purpose-selection-card.selected-knowledge {
        border: 2px solid #198754 !important;
        background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%) !important;
        box-shadow: 0 6px 18px rgba(25, 135, 84, 0.12) !important;
    }
    .purpose-selection-card.unselected {
        border: 1.5px solid #e2e8f0 !important;
        background: #ffffff !important;
        opacity: 0.88;
    }
    .purpose-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .dropzone-box {
        cursor: pointer;
        border: 2px dashed #cbd5e1;
        background-color: #f8fafc;
        border-radius: 12px;
        transition: all 0.2s ease-in-out;
    }
    .dropzone-box:hover, .dropzone-box.drag-over {
        border-color: #0d6efd !important;
        background-color: #f0f7ff !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.08);
    }
    .upload-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background-color: #e0f2fe;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        transition: transform 0.2s ease-in-out;
    }
    .dropzone-box:hover .upload-icon-wrapper {
        transform: scale(1.08);
    }
    .file-preview-card {
        border: 1.5px solid #bbf7d0;
        background-color: #f0fdf4;
        border-radius: 12px;
        transition: all 0.2s ease;
    }
</style>
@endpush

@section('content')
    <div class="container-fluid">
        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" autocomplete="off" id="uploadDocumentForm">
            @csrf
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Add Knowledge & Data Source</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('documents.index') }}">Documents</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">New Entry</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('documents.index') }}" class="add-new">Document List<i class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            @if ($errors->any())
                                <div class="alert alert-danger mb-4">
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- 1. Mode / Purpose Selector --}}
                            <div class="mb-4">
                                <label class="form-label custom-label mb-2 fw-semibold">Select Ingestion Purpose <span class="text-danger">*</span></label>

                                {{-- Hidden Native Radios for Standard Form Submit --}}
                                <input type="radio" name="purpose" id="purpose_analytics" value="data_analytics" class="d-none" {{ in_array(old('purpose', 'data_analytics'), ['data_analytics', 'generic_analytics'], true) ? 'checked' : '' }}>
                                <input type="radio" name="purpose" id="purpose_knowledge" value="knowledge_base" class="d-none" {{ in_array(old('purpose'), ['knowledge_base', 'semantic_knowledge'], true) ? 'checked' : '' }}>

                                <div class="row g-3">
                                    {{-- Card 1: Data & Analytics --}}
                                    <div class="col-md-6 col-12">
                                        <div class="purpose-selection-card p-3 h-100 {{ in_array(old('purpose', 'data_analytics'), ['data_analytics', 'generic_analytics'], true) ? 'selected-analytics' : 'unselected' }}" 
                                             id="card_data_analytics" 
                                             onclick="setPurpose('data_analytics')">
                                            
                                            <div class="position-absolute top-0 end-0 m-3">
                                                <i class="ri-checkbox-circle-fill text-primary fs-5 {{ in_array(old('purpose', 'data_analytics'), ['data_analytics', 'generic_analytics'], true) ? '' : 'd-none' }}" id="check_data_analytics"></i>
                                                <i class="ri-checkbox-blank-circle-line text-muted opacity-50 fs-5 {{ in_array(old('purpose', 'data_analytics'), ['data_analytics', 'generic_analytics'], true) ? 'd-none' : '' }}" id="blank_data_analytics"></i>
                                            </div>

                                            <div class="d-flex align-items-start pe-4">
                                                <div class="purpose-icon-box bg-primary-subtle text-primary me-3">
                                                    <i class="ri-bar-chart-2-fill fs-4"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark fs-6 mb-1">Data & Analytics Report</div>
                                                    <p class="text-muted small mb-0 lh-base">
                                                        Upload spreadsheets (.xlsx, .csv) for sales, orders, or inventory. Powers SQL calculations, aggregations, and chat charts.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Card 2: FAQ & Knowledge Base --}}
                                    <div class="col-md-6 col-12">
                                        <div class="purpose-selection-card p-3 h-100 {{ in_array(old('purpose'), ['knowledge_base', 'semantic_knowledge'], true) ? 'selected-knowledge' : 'unselected' }}" 
                                             id="card_knowledge_base" 
                                             onclick="setPurpose('knowledge_base')">
                                            
                                            <div class="position-absolute top-0 end-0 m-3">
                                                <i class="ri-checkbox-circle-fill text-success fs-5 {{ in_array(old('purpose'), ['knowledge_base', 'semantic_knowledge'], true) ? '' : 'd-none' }}" id="check_knowledge_base"></i>
                                                <i class="ri-checkbox-blank-circle-line text-muted opacity-50 fs-5 {{ in_array(old('purpose'), ['knowledge_base', 'semantic_knowledge'], true) ? 'd-none' : '' }}" id="blank_knowledge_base"></i>
                                            </div>

                                            <div class="d-flex align-items-start pe-4">
                                                <div class="purpose-icon-box bg-success-subtle text-success me-3">
                                                    <i class="ri-book-read-fill fs-4"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark fs-6 mb-1">FAQ & Knowledge Base</div>
                                                    <p class="text-muted small mb-0 lh-base">
                                                        Enter guidelines, company policies, FAQs, or rules directly as text. No file upload required.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @error('purpose')
                                    <div class="error_msg text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr class="my-4 text-muted opacity-25">

                            {{-- 2. Title Field (Shared) --}}
                            <div class="mb-3">
                                <label for="title" class="form-label custom-label" id="lblTitle">
                                    Document Title <span class="text-muted small" id="lblTitleHint">(Optional for spreadsheets)</span>
                                </label>
                                <input type="text" class="form-control custom-input @error('title') is-invalid @enderror" name="title" id="title" value="{{ old('title') }}" placeholder="Enter document title or topic name">
                                @error('title')
                                    <div class="error_msg text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- 3A. Analytics Section: File Dropzone (Visible only for generic_analytics) --}}
                            <div id="analyticsSection" class="{{ old('purpose', 'generic_analytics') === 'generic_analytics' ? '' : 'd-none' }}">
                                <div class="mb-3">
                                    <label class="form-label custom-label mb-2">Select Spreadsheet <span class="text-danger">*</span></label>
                                    
                                    {{-- Hidden Native File Input --}}
                                    <input type="file" class="d-none @error('document') is-invalid @enderror"
                                        name="document" id="document" accept=".xlsx,.csv">

                                    {{-- Drag & Drop Dropzone --}}
                                    <div id="dropzoneArea" class="dropzone-box text-center p-4" onclick="$('#document').click();">
                                        <div class="upload-icon-wrapper mb-3">
                                            <i class="ri-upload-cloud-2-line fs-2 text-primary"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">
                                            Drag & drop your spreadsheet here, or <span class="text-primary text-decoration-underline" style="cursor: pointer;">Browse</span>
                                        </h6>
                                        <p class="text-muted small mb-3">Upload spreadsheet files up to 20 MB</p>
                                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="ri-file-excel-2-line me-1 text-success"></i>Microsoft Excel (.xlsx)</span>
                                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="ri-file-text-line me-1 text-primary"></i>CSV Delimited (.csv)</span>
                                        </div>
                                    </div>

                                    {{-- Live File Selected Preview Card --}}
                                    <div id="filePreviewCard" class="card file-preview-card mt-2 d-none shadow-sm">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center gap-3 text-truncate">
                                                    <div class="preview-file-icon p-2 bg-success text-white rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                        <i class="ri-file-excel-2-fill fs-4" id="previewIcon"></i>
                                                    </div>
                                                    <div class="text-truncate">
                                                        <div class="fw-bold text-dark text-truncate mb-0" id="previewFileName">spreadsheet.xlsx</div>
                                                        <div class="d-flex align-items-center gap-2 small text-muted mt-1">
                                                            <span id="previewFileSize">0 KB</span>
                                                            <span>•</span>
                                                            <span class="badge bg-success text-white" id="previewFileExt">XLSX</span>
                                                            <span class="text-success d-flex align-items-center"><i class="ri-checkbox-circle-fill me-1"></i>Ready to upload</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 ms-2 flex-shrink-0">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#document').click();" title="Change file">
                                                        <i class="ri-refresh-line me-1"></i>Change
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSelectedFile();" title="Remove file">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @error('document')
                                        <div class="error_msg text-danger mt-1 small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- 3B. Knowledge Base Section: Direct Text Area (Visible only for semantic_knowledge) --}}
                            <div id="knowledgeTextSection" class="{{ old('purpose') === 'semantic_knowledge' ? '' : 'd-none' }}">
                                <div class="alert alert-success bg-success-subtle border-success-subtle text-success-emphasis py-2 px-3 small mb-3">
                                    <i class="ri-information-line me-1"></i>
                                    <strong>Direct Text Mode:</strong> No file upload is needed. Enter your policy, guidelines, or FAQ content directly into the text field below.
                                </div>

                                <div class="mb-3">
                                    <label for="content" class="form-label custom-label">
                                        Knowledge / Policy Content <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control custom-input @error('content') is-invalid @enderror" 
                                              name="content" id="content" rows="9" 
                                              placeholder="Write or paste your guidelines, FAQ questions and answers, return policy, or instructions here. The AI will directly use this text to respond to customer inquiries.">{{ old('content') }}</textarea>
                                    <div class="form-text mt-1 text-muted small">
                                        Enter clear, formatted text. You can include bullet points, FAQs, or steps.
                                    </div>
                                    @error('content')
                                        <div class="error_msg text-danger mt-1 small">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Right Sidebar --}}
                <div class="col-md-4 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="table-title">System Guidance</div>
                        </div>
                        <div class="card-body custom-form">
                            {{-- Info Box 1: Storage --}}
                            <div class="mb-3 p-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                                <div class="d-flex align-items-center mb-1 text-primary fw-bold small">
                                    <i class="ri-shield-keyhole-line me-1"></i> Workspace Isolation
                                </div>
                                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.4;">
                                    All entries are strictly scoped to your private workspace. Data is stored safely and deduplicated using cryptographic SHA-256 hashes.
                                </p>
                            </div>

                            {{-- Info Box 2: Dynamic Engine --}}
                            <div class="mb-3 p-3" id="sideEngineNotice" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                                <div class="d-flex align-items-center mb-1 text-success fw-bold small">
                                    <i class="ri-database-2-line me-1" id="sideEngineIcon"></i> 
                                    <span id="sideEngineTitle">Isolated Virtual Database</span>
                                </div>
                                <p class="text-muted mb-0" id="sideEngineDesc" style="font-size: 11px; line-height: 1.4;">
                                    Spreadsheets are indexed into an isolated analytical database. Queries execute via safe, read-only SQL math operations without touching production databases.
                                </p>
                            </div>

                            <div class="form-actions mt-4">
                                <div class="row">
                                    <div class="col-7">
                                        <button type="submit" class="btn submit-button w-100" id="btnUploadSubmit">
                                            <span id="btnSubmitText">Upload & Register</span>
                                            <span class="ms-1 spinner-border spinner-border-sm d-none" id="uploadSpinner" role="status"></span>
                                        </button>
                                    </div>
                                    <div class="col-5">
                                        <a href="{{ route('documents.index') }}" class="btn cancel-button w-100 text-center">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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

    <script>
        function setPurpose(val) {
            if (val === 'data_analytics' || val === 'generic_analytics') {
                $('#purpose_analytics').prop('checked', true);
                $('#card_data_analytics').removeClass('unselected').addClass('selected-analytics');
                $('#card_knowledge_base').removeClass('selected-knowledge').addClass('unselected');
                $('#check_data_analytics').removeClass('d-none');
                $('#blank_data_analytics').addClass('d-none');
                $('#check_knowledge_base').addClass('d-none');
                $('#blank_knowledge_base').removeClass('d-none');

                // Toggle sections
                $('#analyticsSection').removeClass('d-none');
                $('#knowledgeTextSection').addClass('d-none');

                // Adjust requirements
                $('#document').prop('required', true);
                $('#content').prop('required', false);

                // Adjust labels & sidebar
                $('#lblTitle').html('Document Title <span class="text-muted small">(Optional for spreadsheets)</span>');
                $('#btnSubmitText').text('Upload & Register');
                $('#sideEngineTitle').text('Isolated Virtual Database');
                $('#sideEngineDesc').text('Spreadsheets are indexed into an isolated analytical database. Queries execute via safe, read-only SQL math operations without touching production databases.');
                $('#sideEngineIcon').removeClass('ri-book-open-line text-success').addClass('ri-database-2-line text-primary');
                $('#sideEngineNotice').css({'background-color': '#f0f7ff', 'border-color': '#bbf7d0'});
            } else {
                $('#purpose_knowledge').prop('checked', true);
                $('#card_knowledge_base').removeClass('unselected').addClass('selected-knowledge');
                $('#card_data_analytics').removeClass('selected-analytics').addClass('unselected');
                $('#check_knowledge_base').removeClass('d-none');
                $('#blank_knowledge_base').addClass('d-none');
                $('#check_data_analytics').addClass('d-none');
                $('#blank_data_analytics').removeClass('d-none');

                // Toggle sections
                $('#analyticsSection').addClass('d-none');
                $('#knowledgeTextSection').removeClass('d-none');

                // Adjust requirements
                $('#document').prop('required', false);
                $('#content').prop('required', true);

                // Adjust labels & sidebar
                $('#lblTitle').html('Article / Policy Title <span class="text-danger">*</span>');
                $('#btnSubmitText').text('Save Knowledge Article');
                $('#sideEngineTitle').text('Conversational AI Retrieval');
                $('#sideEngineDesc').text('Knowledge text is saved securely and indexed for conversational question answering by the AI assistant without requiring file management.');
                $('#sideEngineIcon').removeClass('ri-database-2-line text-primary').addClass('ri-book-open-line text-success');
                $('#sideEngineNotice').css({'background-color': '#f0fdf4', 'border-color': '#bbf7d0'});
            }
        }

        function formatBytes(bytes, decimals = 1) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function handleFileSelection(file) {
            if (!file) return;

            const ext = file.name.split('.').pop().toLowerCase();
            if (!['xlsx', 'csv'].includes(ext)) {
                if (typeof swal === 'function') {
                    swal("Invalid Format", "Only .xlsx and .csv spreadsheets are supported.", "error");
                } else {
                    alert('Only .xlsx and .csv spreadsheets are supported.');
                }
                removeSelectedFile();
                return;
            }

            if (file.size > 20 * 1024 * 1024) {
                if (typeof swal === 'function') {
                    swal("File Too Large", "Maximum upload size is 20 MB.", "warning");
                } else {
                    alert('Maximum upload size is 20 MB.');
                }
                removeSelectedFile();
                return;
            }

            $('#previewFileName').text(file.name);
            $('#previewFileSize').text(formatBytes(file.size));
            $('#previewFileExt').text(ext.toUpperCase());

            if (ext === 'csv') {
                $('#previewIcon').removeClass('ri-file-excel-2-fill').addClass('ri-file-text-fill');
            } else {
                $('#previewIcon').removeClass('ri-file-text-fill').addClass('ri-file-excel-2-fill');
            }

            // Auto-fill Title if empty
            var titleInput = $('#title');
            if (!titleInput.val().trim()) {
                var nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                titleInput.val(nameWithoutExt);
            }

            $('#dropzoneArea').addClass('d-none');
            $('#filePreviewCard').removeClass('d-none');
        }

        function removeSelectedFile() {
            $('#document').val('');
            $('#dropzoneArea').removeClass('d-none');
            $('#filePreviewCard').addClass('d-none');
        }

        $(document).ready(function () {
            const dropzone = $('#dropzoneArea');
            const fileInput = $('#document');

            fileInput.on('change', function () {
                if (this.files && this.files.length > 0) {
                    handleFileSelection(this.files[0]);
                }
            });

            dropzone.on('dragover dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.addClass('drag-over');
            });

            dropzone.on('dragleave dragend drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.removeClass('drag-over');
            });

            dropzone.on('drop', function (e) {
                const dt = e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    fileInput[0].files = dt.files;
                    handleFileSelection(dt.files[0]);
                }
            });

            $('#uploadDocumentForm').on('submit', function () {
                var btn = $('#btnUploadSubmit');
                btn.prop('disabled', true);
                $('#uploadSpinner').removeClass('d-none');
            });

            // Initialize mode on load (in case of old input)
            var currentPurpose = $('input[name="purpose"]:checked').val() || 'data_analytics';
            setPurpose(currentPurpose);
        });
    </script>
@endpush