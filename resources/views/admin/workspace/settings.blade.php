@extends('admin.app')
@section('title')
    Workspace Settings
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('workspace.settings.update') }}" method="POST" autocomplete="off">
            @csrf
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Workspace Business Profile</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Workspace Settings</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                        <div class="card-body custom-form">
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <div class="row mb-3">
                                <div class="col-md-6 col-12">
                                    <label for="name" class="form-label custom-label">Business / Workspace Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control custom-input @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $workspace->name) }}" required>
                                    @error('name')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 col-12">
                                    <label for="currency" class="form-label custom-label">Default Currency <span class="text-danger">*</span></label>
                                    <select class="form-select custom-input @error('currency') is-invalid @enderror" name="currency" id="currency" required>
                                        <option value="BDT" {{ old('currency', $workspace->currency) === 'BDT' ? 'selected' : '' }}>BDT (৳) - Bangladeshi Taka</option>
                                        <option value="USD" {{ old('currency', $workspace->currency) === 'USD' ? 'selected' : '' }}>USD ($) - US Dollar</option>
                                        <option value="EUR" {{ old('currency', $workspace->currency) === 'EUR' ? 'selected' : '' }}>EUR (€) - Euro</option>
                                        <option value="GBP" {{ old('currency', $workspace->currency) === 'GBP' ? 'selected' : '' }}>GBP (£) - British Pound</option>
                                        <option value="INR" {{ old('currency', $workspace->currency) === 'INR' ? 'selected' : '' }}>INR (₹) - Indian Rupee</option>
                                    </select>
                                    @error('currency')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 col-12">
                                    <label for="support_phone" class="form-label custom-label">Customer Support Phone</label>
                                    <input type="text" class="form-control custom-input @error('support_phone') is-invalid @enderror" name="support_phone" id="support_phone" value="{{ old('support_phone', $workspace->support_phone) }}" placeholder="+8801XXXXXXXXX">
                                    @error('support_phone')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 col-12">
                                    <label for="support_email" class="form-label custom-label">Customer Support Email</label>
                                    <input type="email" class="form-control custom-input @error('support_email') is-invalid @enderror" name="support_email" id="support_email" value="{{ old('support_email', $workspace->support_email) }}" placeholder="support@yourbusiness.com">
                                    @error('support_email')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="business_address" class="form-label custom-label">Business Physical Address</label>
                                <textarea class="form-control custom-input @error('business_address') is-invalid @enderror" name="business_address" id="business_address" rows="3" placeholder="House, Road, Area, City, Country">{{ old('business_address', $workspace->business_address) }}</textarea>
                                @error('business_address')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 col-12">
                                    <label for="operating_hours_weekdays" class="form-label custom-label">Operating Hours (Weekdays)</label>
                                    <input type="text" class="form-control custom-input" name="operating_hours[weekdays]" id="operating_hours_weekdays" value="{{ old('operating_hours.weekdays', $workspace->operating_hours['weekdays'] ?? '09:00 AM - 08:00 PM') }}" placeholder="09:00 AM - 08:00 PM">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label for="operating_hours_weekend" class="form-label custom-label">Operating Hours (Weekends)</label>
                                    <input type="text" class="form-control custom-input" name="operating_hours[weekend]" id="operating_hours_weekend" value="{{ old('operating_hours.weekend', $workspace->operating_hours['weekend'] ?? '10:00 AM - 06:00 PM') }}" placeholder="10:00 AM - 06:00 PM">
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="ri-save-line me-1"></i> Save Workspace Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="card table-card mb-3">
                        <div class="card-header table-header">
                            <div class="table-title">Tenant Metadata</div>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted d-block">Workspace Slug</small>
                                <code>{{ $workspace->slug }}</code>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted d-block">Workspace ID</small>
                                <span class="badge bg-secondary">#{{ $workspace->id }}</span>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted d-block">Status</small>
                                @if($workspace->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                            <div>
                                <small class="text-muted d-block">Created At</small>
                                <span>{{ $workspace->created_at?->format('d M, Y h:i A') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
