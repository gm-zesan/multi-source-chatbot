@extends('admin.app')
@section('title')
    Create FAQ
@endsection

@section('content')
    <div class="container-fluid my-3">
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
                            <a href="{{ route('faqs.index') }}" class="add-new">FAQ List<i
                                    class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="question" class="form-label custom-label">Question <span
                                            class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control custom-input @error('question') is-invalid @enderror"
                                        name="question" id="question" value="{{ old('question') }}"
                                        placeholder="Enter the FAQ question">
                                    @error('question')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 mb-3">
                                    <label for="answer" class="form-label custom-label">Answer <span
                                            class="text-danger">*</span></label>
                                    <textarea class="form-control custom-input @error('answer') is-invalid @enderror" name="answer" id="answer"
                                        rows="8" placeholder="Enter the FAQ answer">{{ old('answer') }}</textarea>
                                    @error('answer')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="card table-card">
                                <div class="card-header table-header">
                                    <div class="table-title">Category & Settings</div>
                                </div>
                                <div class="card-body custom-form">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label custom-label">Category</label>
                                        <select name="category_id" id="category_id"
                                            class="form-control custom-input single-select2">
                                            <option value="">No Category</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}"
                                                    {{ old('category_id') == $category->id ? 'selected' : '' }}>
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
                                        <input type="number"
                                            class="form-control custom-input @error('priority') is-invalid @enderror"
                                            name="priority" id="priority" value="{{ old('priority', 0) }}" min="0">
                                        @error('priority')
                                            <div class="error_msg">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                                value="1" checked>
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card table-card">
                                <div class="card-header table-header">
                                    <div class="table-title">Action</div>
                                </div>
                                <div class="custom-form card-body">
                                    <button type="submit" class="btn submit-button w-100">Save FAQ
                                        <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                    </button>
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
