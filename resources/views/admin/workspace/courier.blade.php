@extends('admin.app')

@section('title', 'Courier Logistics & Fulfillment — Entrepreneurs Automation')

@section('content')
<div class="space-y-5">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-light-border dark:border-dark-border">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-truck-line text-brand-500 text-xl"></i>
                <span>Courier Logistics & Dispatch API</span>
                <span class="app-badge app-badge-primary">{{ $workspace->name }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-0.5">
                Connect your merchant accounts with Steadfast, Pathao, or RedX for 1-click single and bulk parcel booking.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('orders.index') }}" class="app-btn app-btn-secondary text-xs flex items-center gap-1.5">
                <i class="ri-shopping-bag-3-line text-brand-500"></i>
                <span>Orders Dashboard</span>
            </a>
            <a href="{{ route('workspace.settings.edit') }}" class="app-btn app-btn-secondary text-xs">
                <i class="ri-settings-4-line"></i> Store Settings
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="alert p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-checkbox-circle-line text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-500 hover:text-emerald-400" data-bs-dismiss="alert">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert p-3 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-500 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-error-warning-line text-base"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="text-rose-500 hover:text-rose-400" data-bs-dismiss="alert">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
    @endif

    <form method="POST" action="{{ route('workspace.courier.update') }}" class="space-y-6">
        @csrf
        @if($isSuperAdmin)
            <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">
        @endif

        {{-- Default Courier Selector Card --}}
        <div class="app-card p-4 sm:p-5 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-light-text dark:text-dark-text flex items-center gap-1.5">
                        <i class="ri-compass-3-line text-brand-500"></i>
                        <span>Default Active Courier</span>
                    </h2>
                    <p class="text-2xs text-light-muted dark:text-dark-muted">
                        This courier will be used automatically when dispatching single orders or bulk bookings.
                    </p>
                </div>
                <div class="w-full sm:w-64">
                    <select name="default_provider" class="app-select w-full text-xs font-semibold">
                        <option value="steadfast" {{ ($settings['default_provider'] ?? '') === 'steadfast' ? 'selected' : '' }}>Steadfast Courier (Recommended)</option>
                        <option value="pathao" {{ ($settings['default_provider'] ?? '') === 'pathao' ? 'selected' : '' }}>Pathao Courier</option>
                        <option value="redx" {{ ($settings['default_provider'] ?? '') === 'redx' ? 'selected' : '' }}>RedX Logistics</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Courier Providers Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- 1. Steadfast Courier --}}
            <div class="app-card p-5 space-y-4 flex flex-col justify-between border-t-2 {{ !empty($settings['steadfast']['api_key']) ? 'border-t-emerald-500' : 'border-t-slate-300 dark:border-t-slate-700' }}">
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg">
                                <i class="ri-truck-line"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-light-text dark:text-dark-text">Steadfast Courier</h3>
                                <span class="text-3xs font-mono {{ !empty($settings['steadfast']['api_key']) ? 'text-emerald-500' : 'text-light-muted dark:text-dark-muted' }}">
                                    {{ !empty($settings['steadfast']['api_key']) ? '● Configured' : '○ Not Configured' }}
                                </span>
                            </div>
                        </div>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="steadfast[is_active]" value="1" {{ !empty($settings['steadfast']['is_active']) ? 'checked' : '' }} class="app-checkbox">
                            <span class="text-2xs font-semibold text-light-text dark:text-dark-text">Active</span>
                        </label>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-2xs font-semibold text-light-text dark:text-dark-text mb-1">
                                Steadfast API Key <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="steadfast[api_key]" 
                                id="stdf_api_key"
                                value="{{ old('steadfast.api_key', $settings['steadfast']['api_key'] ?? '') }}" 
                                placeholder="e.g. stdf_live_xxxxxxxx" 
                                class="app-input w-full font-mono text-2xs"
                            >
                        </div>
                        <div>
                            <label class="block text-2xs font-semibold text-light-text dark:text-dark-text mb-1">
                                Steadfast Secret Key <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                name="steadfast[secret_key]" 
                                id="stdf_secret_key"
                                value="{{ old('steadfast.secret_key', $settings['steadfast']['secret_key'] ?? '') }}" 
                                placeholder="••••••••••••••••" 
                                class="app-input w-full font-mono text-2xs"
                            >
                        </div>
                        <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas text-3xs text-light-muted dark:text-dark-muted leading-relaxed">
                            <i class="ri-information-line text-emerald-500"></i> Get keys from your <strong>Steadfast Dashboard > Settings > API Information</strong>.
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-light-border dark:border-dark-border">
                    <button 
                        type="button" 
                        class="app-btn app-btn-secondary w-full justify-center text-2xs" 
                        onclick="testCourier('steadfast', { api_key: $('#stdf_api_key').val(), secret_key: $('#stdf_secret_key').val() }, this)"
                    >
                        <i class="ri-rfid-line"></i> Test Connection & Balance
                    </button>
                </div>
            </div>

            {{-- 2. Pathao Courier --}}
            <div class="app-card p-5 space-y-4 flex flex-col justify-between border-t-2 {{ !empty($settings['pathao']['client_id']) ? 'border-t-rose-500' : 'border-t-slate-300 dark:border-t-slate-700' }}">
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-500 flex items-center justify-center text-lg">
                                <i class="ri-e-bike-2-line"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-light-text dark:text-dark-text">Pathao Courier</h3>
                                <span class="text-3xs font-mono {{ !empty($settings['pathao']['client_id']) ? 'text-rose-500' : 'text-light-muted dark:text-dark-muted' }}">
                                    {{ !empty($settings['pathao']['client_id']) ? '● Configured' : '○ Not Configured' }}
                                </span>
                            </div>
                        </div>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="pathao[is_active]" value="1" {{ !empty($settings['pathao']['is_active']) ? 'checked' : '' }} class="app-checkbox">
                            <span class="text-2xs font-semibold text-light-text dark:text-dark-text">Active</span>
                        </label>
                    </div>

                    <div class="space-y-2.5">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-3xs font-semibold text-light-text dark:text-dark-text mb-0.5">Client ID</label>
                                <input type="text" name="pathao[client_id]" id="pathao_client_id" value="{{ old('pathao.client_id', $settings['pathao']['client_id'] ?? '') }}" class="app-input w-full font-mono text-3xs" placeholder="Client ID">
                            </div>
                            <div>
                                <label class="block text-3xs font-semibold text-light-text dark:text-dark-text mb-0.5">Client Secret</label>
                                <input type="password" name="pathao[client_secret]" id="pathao_client_secret" value="{{ old('pathao.client_secret', $settings['pathao']['client_secret'] ?? '') }}" class="app-input w-full font-mono text-3xs" placeholder="Client Secret">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-3xs font-semibold text-light-text dark:text-dark-text mb-0.5">Username / Email</label>
                                <input type="text" name="pathao[username]" id="pathao_username" value="{{ old('pathao.username', $settings['pathao']['username'] ?? '') }}" class="app-input w-full text-3xs" placeholder="merchant@email.com">
                            </div>
                            <div>
                                <label class="block text-3xs font-semibold text-light-text dark:text-dark-text mb-0.5">Password</label>
                                <input type="password" name="pathao[password]" id="pathao_password" value="{{ old('pathao.password', $settings['pathao']['password'] ?? '') }}" class="app-input w-full text-3xs" placeholder="••••••••">
                            </div>
                        </div>
                        <div>
                            <label class="block text-3xs font-semibold text-light-text dark:text-dark-text mb-0.5">Pickup Store ID</label>
                            <input type="text" name="pathao[store_id]" id="pathao_store_id" value="{{ old('pathao.store_id', $settings['pathao']['store_id'] ?? '') }}" class="app-input w-full font-mono text-3xs" placeholder="e.g. 12345">
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-light-border dark:border-dark-border">
                    <button 
                        type="button" 
                        class="app-btn app-btn-secondary w-full justify-center text-2xs" 
                        onclick="testCourier('pathao', { client_id: $('#pathao_client_id').val(), client_secret: $('#pathao_client_secret').val(), username: $('#pathao_username').val(), password: $('#pathao_password').val(), store_id: $('#pathao_store_id').val() }, this)"
                    >
                        <i class="ri-rfid-line"></i> Test Token & Stores
                    </button>
                </div>
            </div>

            {{-- 3. RedX Logistics --}}
            <div class="app-card p-5 space-y-4 flex flex-col justify-between border-t-2 {{ !empty($settings['redx']['token']) ? 'border-t-red-500' : 'border-t-slate-300 dark:border-t-slate-700' }}">
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-500 flex items-center justify-center text-lg">
                                <i class="ri-map-pin-range-line"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-light-text dark:text-dark-text">RedX Logistics</h3>
                                <span class="text-3xs font-mono {{ !empty($settings['redx']['token']) ? 'text-red-500' : 'text-light-muted dark:text-dark-muted' }}">
                                    {{ !empty($settings['redx']['token']) ? '● Configured' : '○ Not Configured' }}
                                </span>
                            </div>
                        </div>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="redx[is_active]" value="1" {{ !empty($settings['redx']['is_active']) ? 'checked' : '' }} class="app-checkbox">
                            <span class="text-2xs font-semibold text-light-text dark:text-dark-text">Active</span>
                        </label>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-2xs font-semibold text-light-text dark:text-dark-text mb-1">
                                RedX API Access Token <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                name="redx[token]" 
                                id="redx_token"
                                value="{{ old('redx.token', $settings['redx']['token'] ?? '') }}" 
                                placeholder="Bearer eyJhbGci..." 
                                class="app-input w-full font-mono text-2xs"
                            >
                        </div>
                        <div>
                            <label class="block text-2xs font-semibold text-light-text dark:text-dark-text mb-1">
                                Pickup Store ID <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="redx[pickup_store_id]" 
                                id="redx_store_id"
                                value="{{ old('redx.pickup_store_id', $settings['redx']['pickup_store_id'] ?? '') }}" 
                                placeholder="e.g. 54321" 
                                class="app-input w-full font-mono text-2xs"
                            >
                        </div>
                        <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas text-3xs text-light-muted dark:text-dark-muted leading-relaxed">
                            <i class="ri-information-line text-red-500"></i> Get your token & store ID from the <strong>RedX OpenAPI Merchant Portal</strong>.
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-light-border dark:border-dark-border">
                    <button 
                        type="button" 
                        class="app-btn app-btn-secondary w-full justify-center text-2xs" 
                        onclick="testCourier('redx', { token: $('#redx_token').val(), pickup_store_id: $('#redx_store_id').val() }, this)"
                    >
                        <i class="ri-rfid-line"></i> Test Token & Stores
                    </button>
                </div>
            </div>

        </div>

        {{-- Save Action Bar --}}
        <div class="app-card p-4 flex items-center justify-between">
            <span class="text-2xs text-light-muted dark:text-dark-muted">
                <i class="ri-shield-keyhole-line text-brand-500 mr-1"></i>
                All credentials are encrypted and scoped strictly to this workspace.
            </span>
            <button type="submit" class="app-btn app-btn-primary">
                <i class="ri-save-3-line"></i> Save Courier Settings
            </button>
        </div>
    </form>
</div>
@endsection

@push('custom-scripts')
<script>
    function testCourier(provider, credentials, btnEl) {
        var $btn = $(btnEl);
        var origHtml = $btn.html();
        var csrf = $('meta[name="csrf-token"]').attr('content');

        $btn.prop('disabled', true).html('<i class="ri-loader-4-line animate-spin"></i> Testing Connection...');

        $.ajax({
            url: "{{ route('workspace.courier.ping') }}",
            type: 'POST',
            data: {
                _token: csrf,
                provider: provider,
                credentials: credentials
            },
            success: function(res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    swal("Connection Successful!", res.message || "Courier API authenticated correctly.", "success");
                } else {
                    swal("Connection Failed", res.message || "Failed to authenticate.", "error");
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(origHtml);
                var err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.';
                swal("Test Failed", err, "error");
            }
        });
    }
</script>
@endpush
