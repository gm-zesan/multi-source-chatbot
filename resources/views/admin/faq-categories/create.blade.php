@extends('admin.app')
@section('title')
    Create FAQ Category
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('faq-categories.store') }}" method="POST" autocomplete="off">
            @csrf
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Create FAQ Category</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('faq-categories.index') }}">FAQ Categories</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Create Category</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('faq-categories.index') }}" class="add-new">Category List<i
                                    class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3">
                                    <label for="name" class="form-label custom-label">Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text"
                                        class="form-control custom-input @error('name') is-invalid @enderror" name="name"
                                    placeholder="e.g., Getting Started">
                                    @error('name')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label custom-label">Description</label>
                                    <textarea class="form-control custom-input @error('description') is-invalid @enderror" name="description"
                                        id="description" rows="4" placeholder="Optional description">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                            </div>

                            <div class="mb-3">
                                <label for="icon" class="form-label custom-label">Icon Class</label>
                                    <input type="text"
                                        class="form-control custom-input @error('icon') is-invalid @enderror" name="icon"
                                        id="icon" value="{{ old('icon') }}"
                                        placeholder="e.g., heroicon-o-rocket-launch">
                                    @error('icon')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                            </div>

                            <div class="mb-3">
                                <label for="order_column" class="form-label custom-label">Sort Order</label>
                                    <input type="number"
                                        class="form-control custom-input @error('order_column') is-invalid @enderror"
                                        name="order_column" id="order_column" value="{{ old('order_column', 0) }}"
                                        min="0">
                                    @error('order_column')
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

                <div class="col-md-4 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="table-title">Action</div>
                        </div>
                        <div class="custom-form card-body">
                            <button type="submit" class="btn submit-button w-100">Save Category
                                <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
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
