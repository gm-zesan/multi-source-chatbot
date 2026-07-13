@extends('admin.app')
@section('title')
    Edit FAQ
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('faqs.update', $faq->id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Edit FAQ</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('faqs.index') }}">FAQs</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Edit FAQ</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('faqs.index') }}" class="add-new">FAQ List<i
                                    class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3">
                                    <label for="question" class="form-label custom-label">Question <span
                                            class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control custom-input @error('question') is-invalid @enderror"
                                        name="question" id="question" value="{{ old('question', $faq->question) }}"
                                        placeholder="Enter the FAQ question">
                                    @error('question')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                            </div>

                            <div class="mb-3">
                                <label for="answer" class="form-label custom-label">Answer <span
                                            class="text-danger">*</span></label>
                                    <textarea class="form-control custom-input @error('answer') is-invalid @enderror" name="answer" id="answer"
                                        rows="8" placeholder="Enter the FAQ answer">{{ old('answer', $faq->answer) }}</textarea>
                                    @error('answer')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="mb-4">
                        <div>
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
                                                    {{ old('category_id', $faq->category_id) == $category->id ? 'selected' : '' }}>
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
                                            name="priority" id="priority" value="{{ old('priority', $faq->priority) }}"
                                            min="0">
                                        @error('priority')
                                            <div class="error_msg">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                                value="1" {{ old('is_active', $faq->is_active) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                    </div>

                                    <div class="mt-3 p-3 bg-light rounded">
                                        <small class="text-muted d-block">Hit Count:
                                            <strong>{{ number_format($faq->hit_count) }}</strong></small>
                                        <small class="text-muted d-block">Last Used:
                                            <strong>{{ $faq->last_used_at?->diffForHumans() ?? 'Never' }}</strong></small>
                                        <small class="text-muted d-block">Created:
                                            <strong>{{ $faq->created_at?->format('M d, Y') }}</strong></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="card table-card">
                                <div class="card-header table-header">
                                    <div class="table-title">Action</div>
                                </div>
                                <div class="custom-form card-body">
                                    <button type="submit" class="btn submit-button w-100">Update FAQ
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
