@extends('admin.app')
@section('title', Auth::user()->isSuperAdmin() ? 'Create User' : 'Add Employee')

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-light-border dark:border-dark-border">
        <div>
            <h1 class="text-base font-semibold text-light-text dark:text-dark-text tracking-tight flex items-center gap-2">
                <i class="ri-user-add-line text-brand-500"></i>
                <span>{{ Auth::user()->isSuperAdmin() ? 'Create Platform User' : 'Add New Employee' }}</span>
            </h1>
            <div class="flex items-center gap-1.5 text-xs text-light-muted dark:text-dark-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">Dashboard</a>
                <span>/</span>
                <a href="{{ route('users.index') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">
                    {{ Auth::user()->isSuperAdmin() ? 'Users' : 'Team & Employees' }}
                </a>
                <span>/</span>
                <span class="text-light-text-secondary dark:text-dark-text-secondary font-medium">Add</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('users.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-arrow-left-line"></i> Back to List
            </a>
        </div>
    </div>

    <form action="{{ route('users.store') }}" method="POST" autocomplete="off">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Primary User Details --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <div>
                            <h2 class="text-xs font-semibold text-light-text dark:text-dark-text uppercase tracking-wider">Employee Information</h2>
                            <p class="text-2xs text-light-muted dark:text-dark-muted">Basic profile credentials and contact details</p>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3.5">
                        <div>
                            <label for="name" class="block text-xs font-medium text-light-text dark:text-dark-text mb-1">Full Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="app-input w-full @error('name') border-rose-500 @enderror" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. Tanvir Hasan" required>
                            @error('name')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="email" class="block text-xs font-medium text-light-text dark:text-dark-text mb-1">Email Address <span class="text-rose-500">*</span></label>
                                <input type="email" class="app-input w-full @error('email') border-rose-500 @enderror" name="email" id="email" value="{{ old('email') }}" placeholder="tanvir@company.com" required>
                                @error('email')
                                    <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="phone" class="block text-xs font-medium text-light-text dark:text-dark-text mb-1">Phone Number</label>
                                <input type="text" class="app-input w-full @error('phone') border-rose-500 @enderror" name="phone" id="phone" value="{{ old('phone') }}" placeholder="+8801700000000">
                                @error('phone')
                                    <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Role Selection --}}
                        <div>
                            <label for="role" class="block text-xs font-medium text-light-text dark:text-dark-text mb-1">Designated Role <span class="text-rose-500">*</span></label>
                            <select name="role" id="role" class="app-select w-full @error('role') border-rose-500 @enderror" required>
                                <option value="">-- Choose Role --</option>
                                @foreach($roles as $r)
                                    @php
                                        $rName = is_object($r) ? $r->name : $r;
                                        $label = match($rName) {
                                            \App\Enums\RoleEnum::WORKSPACE_MANAGER->value => 'Store Manager — Conversations, Orders & Knowledge Base',
                                            \App\Enums\RoleEnum::WORKSPACE_AGENT->value   => 'Support & Sales Agent — Live Chat & Customer Orders Only',
                                            \App\Enums\RoleEnum::ADMIN->value             => 'Business Owner (Admin)',
                                            \App\Enums\RoleEnum::PLATFORM_SUPPORT->value  => 'Platform Operations Support',
                                            default                                       => ucfirst($rName),
                                        };
                                    @endphp
                                    <option value="{{ $rName }}" {{ old('role') === $rName ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">
                                Store Managers can manage knowledge and view all chats. Support Agents can only chat with customers and take orders.
                            </p>
                            @error('role')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Direct Password Set (Option 1) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-light-border dark:border-dark-border">
                            <div>
                                <label for="password" class="block text-xs font-medium text-light-text dark:text-dark-text mb-1">Initial Password <span class="text-rose-500">*</span></label>
                                <input type="password" class="app-input w-full @error('password') border-rose-500 @enderror" name="password" id="password" placeholder="At least 8 characters" required>
                                @error('password')
                                    <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-xs font-medium text-light-text dark:text-dark-text mb-1">Confirm Password <span class="text-rose-500">*</span></label>
                                <input type="password" class="app-input w-full" name="password_confirmation" id="password_confirmation" placeholder="Repeat password" required>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="text-3xs text-light-muted dark:text-dark-muted">
                                    <i class="ri-information-line text-brand-500"></i> The employee will log in using this email and temporary password.
                                </span>
                            </div>
                        </div>

                        @if($isSuperAdmin)
                            <div class="pt-2 border-t border-light-border dark:border-dark-border">
                                <label for="workspace_name" class="block text-xs font-medium text-light-text dark:text-dark-text mb-1">New Workspace Name (Optional)</label>
                                <input type="text" class="app-input w-full" name="workspace_name" id="workspace_name" value="{{ old('workspace_name') }}" placeholder="Leave empty to assign to current workspace">
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right Sidebar Actions --}}
            <div class="space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-light-text dark:text-dark-text uppercase tracking-wider">Access Scope</h2>
                    </div>
                    <div class="app-card-body space-y-3 text-xs text-light-text-secondary dark:text-dark-text-secondary">
                        <div class="p-3 rounded-lg bg-light-surface/60 dark:bg-dark-surface/60 border border-light-border dark:border-dark-border space-y-2">
                            <div class="text-2xs uppercase tracking-wider font-semibold text-brand-500 font-mono">Workspace Boundary</div>
                            <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                                This user will be strictly confined to <strong>{{ Auth::user()->workspace->name ?? 'Current Workspace' }}</strong>. They will never have access to other tenants, billing channels, or platform administration.
                            </p>
                        </div>
                    </div>
                    <div class="app-card-footer space-y-2">
                        <button type="submit" class="app-btn app-btn-primary w-full justify-center">
                            <i class="ri-check-line"></i> Save Employee Account
                        </button>
                        <a href="{{ route('users.index') }}" class="app-btn app-btn-secondary w-full justify-center">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
