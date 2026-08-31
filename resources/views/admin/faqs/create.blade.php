@extends('admin.app')
@section('title')
    Create FAQ
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('faqs.store') }}" method="POST" autocomplete="off">
            @csrf
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Create FAQ</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('faqs.index') }}">FAQs</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Create FAQ</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('faqs.index') }}" class="add-new">FAQ List<i class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3">
                                <label for="question" class="form-label custom-label">Question <span class="text-danger">*</span></label>
                                <input type="text" class="form-control custom-input @error('question') is-invalid @enderror" name="question" id="question" value="{{ old('question') }}" placeholder="Enter the FAQ question">
                                @error('question')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="answer" class="form-label custom-label">Answer <span class="text-danger">*</span></label>
                                <textarea class="form-control custom-input @error('answer') is-invalid @enderror" name="answer" id="answer" rows="8" placeholder="Enter the FAQ answer">{{ old('answer') }}</textarea>
                                @error('answer')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="table-title">Category & Settings</div>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3">
                                <label for="category_id" class="form-label custom-label">Category</label>
                                <select name="category_id" id="category_id" class="form-control custom-input single-select2">
                                    <option value="">No Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="priority" class="form-label custom-label">Priority</label>
                                <input type="number" class="form-control custom-input @error('priority') is-invalid @enderror" name="priority" id="priority" value="{{ old('priority', 0) }}" min="0">
                                @error('priority')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label custom-label d-block">Status</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>

                            <div class="mb-3 p-3" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;">
                                <div class="d-flex align-items-center mb-1 text-success fw-bold small">
                                    <i class="ri-sparkling-fill me-1"></i> Automatic AI Lexicon Ingestion
                                </div>
                                <p class="text-muted mb-0" style="font-size: 11px; line-height: 1.4;">
                                    Upon saving, our background pipeline will automatically map this FAQ into the 11-domain Commerce Ontology and extract validated Bengali, Banglish, and F-Commerce aliases for instant retrieval.
                                </p>
                            </div>

                            <div class="form-actions">
                                <div class="row">
                                    <div class="col-6">
                                        <button type="submit" class="btn submit-button">Save
                                            <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="{{ route('faqs.index') }}" class="btn cancel-button">Cancel</a>
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
            swal("success", "{{ Session::get('success') }}", "success", {
                timer: 1000,
                button: false,
            });
        </script>
    @endif
@endpush
