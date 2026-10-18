@extends('admin.app')
@section('title')
    Upload Knowledge Document
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" autocomplete="off">
            @csrf
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Upload Knowledge Document</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('documents.index') }}">Documents</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Upload</li>
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

                            <div class="mb-3">
                                <label for="title" class="form-label custom-label">Document Title <span class="text-muted small">(Optional)</span></label>
                                <input type="text" class="form-control custom-input @error('title') is-invalid @enderror" name="title" id="title" value="{{ old('title') }}" placeholder="Leave blank to use original filename">
                                @error('title')
                                    <div class="error_msg text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="document" class="form-label custom-label">Select File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control custom-input @error('document') is-invalid @enderror" name="document" id="document" accept=".xlsx,.csv" required>
                                <div class="form-text mt-1 text-muted small">
                                    Supported file formats: <strong>.xlsx, .csv</strong>. Maximum upload size: <strong>20 MB</strong>.
                                </div>
                                @error('document')
                                    <div class="error_msg text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="table-title">Storage & Lifecycle</div>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3 p-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <div class="d-flex align-items-center mb-1 text-primary fw-bold small">
                                    <i class="ri-shield-keyhole-line me-1"></i> Private Secure Storage
                                </div>
                                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.4;">
                                    Files are stored in private local storage with a random UUID identifier under your workspace directory. Content is deduplicated using cryptographic SHA-256 hashes.
                                </p>
                            </div>

                            <div class="mb-3 p-3" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;">
                                <div class="d-flex align-items-center mb-1 text-success fw-bold small">
                                    <i class="ri-database-2-line me-1"></i> Analytics Virtual Database
                                </div>
                                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.4;">
                                    Uploaded spreadsheets are automatically registered into an isolated analytical SQLite virtual database. All sheets and tabular columns become queryable through the chatbot.
                                </p>
                            </div>

                            <div class="form-actions">
                                <div class="row">
                                    <div class="col-6">
                                        <button type="submit" class="btn submit-button">Upload File
                                            <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="{{ route('documents.index') }}" class="btn cancel-button">Cancel</a>
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
@endpush
