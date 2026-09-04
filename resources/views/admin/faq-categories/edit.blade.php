@extends('admin.app')
@section('title')
    Edit FAQ Category
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('faq-categories.update', $category->id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Edit FAQ Category</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('faq-categories.index') }}">FAQ Categories</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Edit Category</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('faq-categories.index') }}" class="add-new">Category List<i class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            <div class="row">
                                <div class="col-md-6 col-12">
                                    <div class="mb-3">
                                        <label for="name" class="form-label custom-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control custom-input @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $category->name) }}" placeholder="e.g., Getting Started">
                                        @error('name')
                                            <div class="error_msg">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="mb-3">
                                        <label for="icon" class="form-label custom-label">Icon Class</label>
                                        <input type="text" class="form-control custom-input @error('icon') is-invalid @enderror" name="icon" id="icon" value="{{ old('icon', $category->icon) }}" placeholder="e.g., heroicon-o-rocket-launch">
                                        @error('icon')
                                            <div class="error_msg">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 col-12">
                                    <div class="mb-3">
                                        <label for="order_column" class="form-label custom-label">Sort Order</label>
                                        <input type="number" class="form-control custom-input @error('order_column') is-invalid @enderror" name="order_column" id="order_column" value="{{ old('order_column', $category->order_column) }}" min="0">
                                        @error('order_column')
                                            <div class="error_msg">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <div class="mb-3">
                                        <label class="form-label custom-label d-block">Status</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label custom-label">Description</label>
                                <textarea class="form-control custom-input @error('description') is-invalid @enderror" name="description" id="description" rows="4" placeholder="Optional description">{{ old('description', $category->description) }}</textarea>
                                @error('description')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-actions">
                                <div class="row">
                                    <div class="col-md-3 col-sm-6 col-12 mb-2 mb-md-0">
                                        <button type="submit" class="btn submit-button">Update Category
                                            <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                    </div>
                                    <div class="col-md-3 col-sm-6 col-12">
                                        <a href="{{ route('faq-categories.index') }}" class="btn cancel-button">Cancel</a>
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
