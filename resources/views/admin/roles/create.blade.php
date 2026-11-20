@extends('admin.app')
@section('title', 'Create Role')

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Create Role</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <a href="{{ route('roles.index') }}" class="hover:text-text-primary transition-colors">Roles</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Create Role</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('roles.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-arrow-left-line"></i> Back to Roles
            </a>
        </div>
    </div>

    <form action="{{ route('roles.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Primary Details & Permissions --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- Role Meta Card --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <div>
                            <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Role Definition</h2>
                            <p class="text-2xs text-text-muted">Specify the unique system name and purpose of this security role</p>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3.5">
                        <div>
                            <label for="name" class="block text-xs font-medium text-text-secondary mb-1">Role Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="app-input w-full @error('name') border-rose-500 @enderror" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. content_moderator" required>
                            @error('name')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-xs font-medium text-text-secondary mb-1">Role Description</label>
                            <textarea class="app-textarea w-full text-xs rounded-md border border-border bg-surface text-text-primary p-2.5 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all @error('description') border-rose-500 @enderror" name="description" id="description" rows="3" placeholder="Describe the operational responsibilities and scope">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Permissions Matrix Card --}}
                <div class="app-card">
                    <div class="app-card-header flex items-center justify-between">
                        <div>
                            <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Assigned Capabilities & Permissions</h2>
                            <p class="text-2xs text-text-muted">Grant specific administrative access rights by functional module</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-1.5 text-2xs font-medium text-text-secondary cursor-pointer select-none">
                                <input type="checkbox" id="selectAllPermissionsMaster" class="rounded border-border text-brand-primary focus:ring-brand-primary" onclick="toggleAllPermissions(this)">
                                <span>Select All</span>
                            </label>
                        </div>
                    </div>
                    <div class="app-card-body space-y-4 divide-y divide-border">
                        @foreach ($modules as $module)
                            <div class="pt-3 first:pt-0">
                                <div class="flex items-center gap-2 mb-2">
                                    <input type="checkbox" name="module" id="{{ $module->module }}" class="rounded border-border text-brand-primary focus:ring-brand-primary" onclick="checkRoleModule(this)">
                                    <label for="{{ $module->module }}" class="text-xs font-semibold text-text-primary cursor-pointer uppercase tracking-wider">
                                        {{ $module->module }}
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 pl-6">
                                    @foreach ($permission as $value)
                                        @if($module->module == $value->module)
                                            <label class="flex items-center gap-2 text-xs text-text-secondary cursor-pointer hover:text-text-primary select-none py-1">
                                                <input type="checkbox" name="permission[]" class="rounded border-border text-brand-primary focus:ring-brand-primary inner-checkbox" id="check-{{ $value->id }}" value="{{ $value->id }}" data-module="{{ $module->module }}" onclick="innerCheckboxUncheck(this)">
                                                <span>{{ $value->display_name }}</span>
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right Sidebar Actions --}}
            <div class="space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Publish Role</h2>
                    </div>
                    <div class="app-card-body space-y-2">
                        <button type="submit" class="app-btn app-btn-primary w-full justify-center">
                            <i class="ri-check-line"></i> Save Role
                        </button>
                        <a href="{{ route('roles.index') }}" class="app-btn app-btn-secondary w-full justify-center">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('custom-scripts')
<script>
    function checkRoleModule(moduleCheckbox) {
        var moduleId = moduleCheckbox.id;
        var isChecked = moduleCheckbox.checked;

        var innerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
        innerCheckboxes.forEach(function(innerCheckbox) {
            innerCheckbox.checked = isChecked;
        });
        syncMasterCheckbox();
    }

    function innerCheckboxUncheck(innerCheckbox) {
        var moduleId = innerCheckbox.getAttribute('data-module');
        var moduleCheckbox = document.getElementById(moduleId);

        var allInnerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
        var allChecked = true;
        var anyChecked = false;
        allInnerCheckboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            } else {
                allChecked = false;
            }
        });

        if (moduleCheckbox) {
            moduleCheckbox.checked = allChecked;
            moduleCheckbox.indeterminate = !allChecked && anyChecked;
        }
        syncMasterCheckbox();
    }

    function toggleAllPermissions(masterCheckbox) {
        var isChecked = masterCheckbox.checked;
        document.querySelectorAll('input[name="module"]').forEach(function(m) {
            m.checked = isChecked;
            m.indeterminate = false;
        });
        document.querySelectorAll('input[name="permission[]"]').forEach(function(p) {
            p.checked = isChecked;
        });
    }

    function syncMasterCheckbox() {
        var master = document.getElementById('selectAllPermissionsMaster');
        if (!master) return;
        var allPerms = document.querySelectorAll('input[name="permission[]"]');
        if (allPerms.length === 0) return;
        var allChecked = true;
        var anyChecked = false;
        allPerms.forEach(function(p) {
            if (p.checked) anyChecked = true;
            else allChecked = false;
        });
        master.checked = allChecked;
        master.indeterminate = !allChecked && anyChecked;
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input[name="module"]').forEach(function(moduleCheckbox) {
            var moduleId = moduleCheckbox.id;
            var allInnerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
            if (allInnerCheckboxes.length > 0) {
                var allChecked = true;
                var anyChecked = false;
                allInnerCheckboxes.forEach(function(cb) {
                    if (cb.checked) anyChecked = true;
                    else allChecked = false;
                });
                moduleCheckbox.checked = allChecked;
                moduleCheckbox.indeterminate = !allChecked && anyChecked;
            }
        });
        syncMasterCheckbox();
    });
</script>
@endpush
