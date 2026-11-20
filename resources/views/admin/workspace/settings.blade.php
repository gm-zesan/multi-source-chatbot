@extends('admin.app')

@section('title')
    Business Profile & Settings
@endsection

@push('custom-style')
<style>
    .settings-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }
    .form-section-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-2">
    {{-- Top Action & Breadcrumb Bar --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                @if(Auth::user()->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value))
                    <a href="{{ route('workspace.settings.edit') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 shadow-sm">
                        <i class="ri-arrow-left-line"></i> All Businesses
                    </a>
                @endif
                <h4 class="mb-0 text-dark fw-bold">Workspace Business Profile</h4>
                <span class="badge badge-soft-primary px-2.5 py-1 rounded-pill small fw-semibold">
                    {{ $workspace->name }}
                </span>
            </div>
            <p class="text-muted mb-0 small">
                Configure your business identity, customer support helpline, and store operating hours used by the AI chatbot.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('analytics.index', ['workspace_id' => $workspace->id]) }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="ri-bar-chart-2-line"></i> BI Analytics
            </a>
            <a href="{{ route('widget.manage', ['workspace_id' => $workspace->id]) }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="ri-message-3-line"></i> Chat Widget
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="ri-checkbox-circle-fill fs-5 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('workspace.settings.update') }}" method="POST" autocomplete="off">
        @csrf
        @if(Auth::user()->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value))
            <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">
        @endif

        <div class="row g-4">
            {{-- Left Main Form Column --}}
            <div class="col-xl-8 col-lg-7 col-12">
                <div class="card settings-card mb-4">
                    <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                        <div class="form-section-title mb-0">
                            <i class="ri-building-line text-primary fs-5"></i> Business Identity & Currency
                        </div>
                    </div>
                    <div class="card-body px-4 py-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6 col-12">
                                <label for="name" class="form-label text-dark fw-semibold small">Business / Workspace Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $workspace->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 col-12">
                                <label for="currency" class="form-label text-dark fw-semibold small">Default Currency <span class="text-danger">*</span></label>
                                <select class="form-select @error('currency') is-invalid @enderror" name="currency" id="currency" required>
                                    <option value="BDT" {{ old('currency', $workspace->currency) === 'BDT' ? 'selected' : '' }}>BDT (৳) - Bangladeshi Taka</option>
                                    <option value="USD" {{ old('currency', $workspace->currency) === 'USD' ? 'selected' : '' }}>USD ($) - US Dollar</option>
                                    <option value="EUR" {{ old('currency', $workspace->currency) === 'EUR' ? 'selected' : '' }}>EUR (€) - Euro</option>
                                    <option value="GBP" {{ old('currency', $workspace->currency) === 'GBP' ? 'selected' : '' }}>GBP (£) - British Pound</option>
                                    <option value="INR" {{ old('currency', $workspace->currency) === 'INR' ? 'selected' : '' }}>INR (₹) - Indian Rupee</option>
                                </select>
                                @error('currency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6 col-12">
                                <label for="support_phone" class="form-label text-dark fw-semibold small">Customer Support Phone</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="ri-phone-line"></i></span>
                                    <input type="text" class="form-control @error('support_phone') is-invalid @enderror" name="support_phone" id="support_phone" value="{{ old('support_phone', $workspace->support_phone) }}" placeholder="+8801XXXXXXXXX">
                                </div>
                                <small class="text-muted">Referenced by AI when customers ask for phone support.</small>
                                @error('support_phone')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 col-12">
                                <label for="support_email" class="form-label text-dark fw-semibold small">Customer Support Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="ri-mail-line"></i></span>
                                    <input type="email" class="form-control @error('support_email') is-invalid @enderror" name="support_email" id="support_email" value="{{ old('support_email', $workspace->support_email) }}" placeholder="support@yourbusiness.com">
                                </div>
                                <small class="text-muted">Referenced by AI when escalating support inquiries.</small>
                                @error('support_email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="business_address" class="form-label text-dark fw-semibold small">Business Physical Address / Store Location</label>
                            <textarea class="form-control @error('business_address') is-invalid @enderror" name="business_address" id="business_address" rows="3" placeholder="Plot/Building, Street, Area, City, Postal Code, Country">{{ old('business_address', $workspace->business_address) }}</textarea>
                            <small class="text-muted">Used by AI when customers ask where the showroom or office is located.</small>
                            @error('business_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Operating Hours Card --}}
                <div class="card settings-card mb-4">
                    <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                        <div class="form-section-title mb-0">
                            <i class="ri-time-line text-primary fs-5"></i> Store Operating Hours
                        </div>
                    </div>
                    <div class="card-body px-4 py-4">
                        <p class="text-muted small mb-3">
                            The AI chatbot informs customers whether your sales and support staff are currently available based on these operating hours.
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <label for="operating_hours_weekdays" class="form-label text-dark fw-semibold small">Weekdays Operating Hours</label>
                                <input type="text" class="form-control" name="operating_hours[weekdays]" id="operating_hours_weekdays" value="{{ old('operating_hours.weekdays', $workspace->operating_hours['weekdays'] ?? '09:00 AM - 08:00 PM') }}" placeholder="09:00 AM - 08:00 PM">
                            </div>
                            <div class="col-md-6 col-12">
                                <label for="operating_hours_weekend" class="form-label text-dark fw-semibold small">Weekends / Holidays</label>
                                <input type="text" class="form-control" name="operating_hours[weekend]" id="operating_hours_weekend" value="{{ old('operating_hours.weekend', $workspace->operating_hours['weekend'] ?? '10:00 AM - 06:00 PM') }}" placeholder="10:00 AM - 06:00 PM">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2 d-inline-flex align-items-center gap-2 shadow-sm">
                        <i class="ri-save-line fs-5"></i> Save Business Settings
                    </button>
                </div>
            </div>

            {{-- Right Info Sidebar Column --}}
            <div class="col-xl-4 col-lg-5 col-12">
                <div class="card settings-card mb-4">
                    <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                        <div class="form-section-title mb-0">
                            <i class="ri-shield-keyhole-line text-primary fs-5"></i> Tenant Governance
                        </div>
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="py-2 border-bottom">
                            <small class="text-muted d-block">Workspace Identifier</small>
                            <span class="badge badge-soft-secondary fw-bold px-2 py-1 mt-1">ID #{{ $workspace->id }}</span>
                        </div>
                        <div class="py-2 border-bottom">
                            <small class="text-muted d-block">Tenant Slug</small>
                            <code class="fw-semibold text-primary">{{ $workspace->slug }}</code>
                        </div>
                        <div class="py-2 border-bottom">
                            <small class="text-muted d-block">System Status</small>
                            @if($workspace->is_active)
                                <span class="badge badge-soft-success px-2 py-1 mt-1">Active & Operational</span>
                            @else
                                <span class="badge badge-soft-danger px-2 py-1 mt-1">Inactive</span>
                            @endif
                        </div>
                        <div class="py-2">
                            <small class="text-muted d-block">Onboarding Date</small>
                            <span class="text-dark small fw-medium">{{ $workspace->created_at?->format('d M, Y · h:i A') }}</span>
                        </div>
                    </div>
                </div>

                {{-- AI Grounding Notice Card --}}
                <div class="card settings-card bg-light border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 text-primary fw-bold mb-2">
                            <i class="ri-robot-2-line fs-5"></i> Grounded AI Assistant
                        </div>
                        <p class="text-muted small mb-0">
                            All values configured on this page serve as immutable source-of-truth context injected into the Unified Decision Engine (UDE) to prevent LLM hallucinations about phone numbers, operating schedules, or currency symbols.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
