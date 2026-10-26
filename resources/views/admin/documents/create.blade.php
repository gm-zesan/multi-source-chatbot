@extends('admin.app')
@section('title')
    Upload Analytics Data Source
@endsection

@push('custom-style')
<style>
    .dropzone-box {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        background-color: #f8fafc;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
    }

    .dropzone-box:hover, .dropzone-box.drag-over {
        border-color: #2563eb;
        background-color: #eff6ff;
    }

    .upload-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background-color: #dbeafe;
        display: inline-flex;
        align-items: center;
        justify-content: center;
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
            {{-- Analytics Data Source purpose --}}
            <input type="hidden" name="purpose" value="data_analytics">
            <input type="hidden" name="redirect_to" value="hub">

            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Upload Analytics Data Source</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('knowledge-base.index') }}">Knowledge Base</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Upload Data Source</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('knowledge-base.index', ['tab' => 'data_sources']) }}" class="add-new">
                                <i class="ri-list-ordered-2 me-1"></i>Data Sources List
                            </a>
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

                            {{-- Notice Banner pointing to FAQ/Policy creation --}}
                            <div class="alert alert-light border d-flex align-items-center justify-content-between p-3 mb-4 rounded-3" style="background: #f8fafc; border-color: #e2e8f0;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-2 rounded-circle bg-primary-subtle text-primary">
                                        <i class="ri-lightbulb-line fs-4"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark" style="font-size: 13.5px;">Looking to add FAQs, Store Policies, or Business Information?</strong>
                                        <span class="text-muted small">Store policies and Q&As are managed with live AI vector search embeddings under Knowledge Base.</span>
                                    </div>
                                </div>
                                <a href="{{ route('faqs.create') }}" class="btn btn-sm btn-outline-primary flex-shrink-0 ms-2">
                                    <i class="ri-add-line me-1"></i>Create FAQ / Policy
                                </a>
                            </div>

                            {{-- Title Field --}}
                            <div class="mb-3">
                                <label for="title" class="form-label custom-label">
                                    Data Source Name / Title <span class="text-muted small">(Optional, defaults to file name)</span>
                                </label>
                                <input type="text" class="form-control custom-input @error('title') is-invalid @enderror"
                                       name="title" id="title" value="{{ old('title') }}"
                                       placeholder="e.g. Q3 Sales Report, Product Inventory, Customer Orders">
                                @error('title')
                                    <div class="error_msg text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Spreadsheet Dropzone --}}
                            <div class="mb-3">
                                <label class="form-label custom-label mb-2">Select Spreadsheet <span class="text-danger">*</span></label>

                                {{-- Hidden Native File Input --}}
                                <input type="file" class="d-none @error('document') is-invalid @enderror"
                                       name="document" id="document" accept=".xlsx,.csv" required>

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
                                    All spreadsheets are strictly scoped to your private workspace. Data is stored safely and deduplicated using cryptographic SHA-256 hashes.
                                </p>
                            </div>

                            {{-- Info Box 2: Dynamic Engine --}}
                            <div class="mb-3 p-3" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                                <div class="d-flex align-items-center mb-1 text-success fw-bold small">
                                    <i class="ri-database-2-line me-1"></i> Isolated Virtual Database
                                </div>
                                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.4;">
                                    Spreadsheets are indexed into an isolated analytical database. Queries execute via safe, read-only SQL math operations to calculate revenue, averages, and charts without touching production databases.
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
                                        <a href="{{ route('knowledge-base.index', ['tab' => 'data_sources']) }}" class="btn cancel-button w-100 text-center">Cancel</a>
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
        });
    </script>
@endpush