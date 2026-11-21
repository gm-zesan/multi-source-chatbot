@extends('admin.app')

@section('title')
    {{ Auth::user()->isSuperAdmin() ? 'Users & Access Control' : 'Team & Employees' }}
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
@endpush

@section('content')
<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-team-line text-brand-500"></i>
                <span>{{ Auth::user()->isSuperAdmin() ? 'Platform Users & Access Control' : 'Team & Employees' }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                @if(Auth::user()->isSuperAdmin())
                    Manage global platform users, merchant owners, and system permissions.
                @else
                    Manage your store managers, support agents, and workspace team members.
                @endif
            </p>
        </div>
        @if(Auth::user()->isSuperAdmin() || Auth::user()->isBusinessAdmin())
            <a href="{{ route('users.create') }}" class="app-btn app-btn-primary">
                <i class="ri-user-add-line text-sm"></i>
                <span>{{ Auth::user()->isSuperAdmin() ? 'Create User' : 'Add Employee' }}</span>
            </a>
        @endif
    </div>

    {{-- Data Table Card --}}
    <div class="app-card overflow-hidden">
        <div class="p-3 overflow-x-auto">
            <table class="app-table w-full" id="data-table">
                <thead>
                    <tr>
                        <th style="width: 60px;" class="no-sort">#</th>
                        <th style="width: 24%;">Name</th>
                        <th style="width: 24%;">Email</th>
                        <th style="width: 16%;">Phone</th>
                        @if(Auth::user()->isSuperAdmin())
                            <th style="width: 16%;">Workspace</th>
                        @endif
                        <th style="width: 14%;">Role</th>
                        <th style="width: 14%; text-align: right;" class="no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Assign Role Modal (Tailwind Native) --}}
<div class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" id="assignRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog w-full max-w-md app-card shadow-modal overflow-hidden transition-all duration-200">
        <form id="assignRoleForm" onsubmit="submitAssignRole(event)">
            @csrf
            <input type="hidden" id="modalUserId" name="user_id">
            <div class="app-card-header">
                <h3 class="text-xs font-bold text-light-text dark:text-dark-text flex items-center gap-1.5">
                    <i class="ri-shield-user-line text-brand-500"></i>
                    <span>Assign Team Role</span>
                </h3>
                <button type="button" class="text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-base"></i>
                </button>
            </div>
            <div class="app-card-body space-y-3">
                <div class="space-y-0.5">
                    <div class="text-2xs text-light-muted dark:text-dark-muted uppercase font-semibold">Employee Name</div>
                    <div class="text-xs font-bold text-light-text dark:text-dark-text" id="modalUserName">Loading...</div>
                </div>
                <div class="space-y-0.5">
                    <div class="text-2xs text-light-muted dark:text-dark-muted uppercase font-semibold">Email Address</div>
                    <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary" id="modalUserEmail">loading@example.com</div>
                </div>
                <div class="space-y-1 pt-1">
                    <label for="modalRoleSelect" class="text-2xs font-semibold text-light-text dark:text-dark-text">Select Allowed Role <span class="text-red-500">*</span></label>
                    <select class="app-select w-full" id="modalRoleSelect" name="role" required>
                        <option value="">-- Choose Role --</option>
                        @foreach($roles ?? [] as $r)
                            @php
                                $roleLabel = match($r) {
                                    \App\Enums\RoleEnum::ADMIN->value => 'Business Owner (Admin)',
                                    \App\Enums\RoleEnum::WORKSPACE_MANAGER->value => 'Store Manager',
                                    \App\Enums\RoleEnum::WORKSPACE_AGENT->value => 'Support Agent',
                                    \App\Enums\RoleEnum::PLATFORM_SUPPORT->value => 'Platform Support',
                                    \App\Enums\RoleEnum::SUPERADMIN->value => 'Super Admin',
                                    default => ucfirst($r),
                                };
                            @endphp
                            <option value="{{ $r }}">{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                    <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">
                        Employees only have access to their designated workspace and permissions.
                    </p>
                </div>
            </div>
            <div class="app-card-footer flex items-center justify-end gap-2">
                <button type="button" class="app-btn app-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="app-btn app-btn-primary" id="btnSaveRole">
                    <i class="ri-check-line"></i> Save Role
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('custom-scripts')
    @if (Session::has('success'))
        <script>
            swal("Success", "{{ Session::get('success') }}", "success", {
                timer: 1500,
                button: false,
            });
        </script>
    @endif
    
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/users';
        var isSuperAdmin = {{ Auth::user()->isSuperAdmin() ? 'true' : 'false' }};
        var table;

        $(document).ready(function() {
            var columns = [
                {
                    data: 'id',
                    name: 'id',
                    orderable: false,
                    searchable: false,
                    width: '60px',
                    render: function(data, type, row) {
                        return `<span class="text-2xs text-light-muted dark:text-dark-muted font-mono tabular-nums">#${data}</span>`;
                    }
                },
                {
                    data: 'name',
                    name: 'name',
                    orderable: true,
                    width: '24%'
                },
                {
                    data: 'email',
                    name: 'email',
                    orderable: true,
                    width: '24%'
                },
                {
                    data: 'phone',
                    name: 'phone',
                    orderable: true,
                    width: '16%',
                    render: function(data) {
                        return data ? htmlspecialchars(data) : '<span class="text-light-muted dark:text-dark-muted text-3xs font-mono">—</span>';
                    }
                }
            ];

            if (isSuperAdmin) {
                columns.push({
                    data: 'workspace',
                    name: 'workspace',
                    orderable: false,
                    width: '16%'
                });
            }

            columns.push(
                {
                    data: 'role',
                    name: 'role',
                    orderable: false,
                    width: '14%'
                },
                {
                    data: 'action-btn',
                    orderable: false,
                    width: '14%',
                    render: function(data) {
                        var btns = '';
                        btns += '<div class="flex items-center justify-end gap-1.5">';

                        if (data.is_self) {
                            btns += '<span class="text-3xs text-light-muted dark:text-dark-muted font-mono px-2 py-0.5 rounded bg-light-elevated dark:bg-dark-elevated">You</span>';
                        } else if (data.can_manage) {
                            // Assign Role button
                            btns += '<button type="button" class="app-action-btn bg-brand-500/15 text-brand-500 hover:bg-brand-500 hover:text-white" title="Change Role" onclick="openAssignRoleModal(\'' + data.id + '\', \'' + escapeHtml(data.name) + '\', \'' + escapeHtml(data.email) + '\', \'' + escapeHtml(data.role) + '\')"><i class="ri-shield-user-line"></i></button>';

                            // Edit button
                            btns += '<a href="' + SITEURL + '/dashboard/users/' + data.id + '/edit" title="Edit" class="app-action-btn bg-sky-500/15 text-sky-500 hover:bg-sky-500 hover:text-white"><i class="ri-edit-line"></i></a>';

                            // Delete button
                            btns += '<form action="' + SITEURL + '/dashboard/users/' + data.id + '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure you want to remove this employee?\');">' +
                                '@csrf' +
                                '@method('DELETE')' +
                                '<button type="submit" class="app-action-btn bg-red-500/15 text-red-500 hover:bg-red-500 hover:text-white" title="Remove"><i class="ri-delete-bin-2-line"></i></button>' +
                                '</form>';
                        } else {
                            btns += '<span class="text-3xs text-light-muted dark:text-dark-muted font-mono">—</span>';
                        }

                        btns += '</div>';
                        return btns;
                    }
                }
            );

            table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                fixedHeader: true,
                autoWidth: false,
                order: [[1, 'asc']],
                pageLength: 20,
                lengthMenu: [20, 50, 100],
                ajax: {
                    url: listUrl,
                    type: 'GET'
                },
                columns: columns
            });
        });

        function htmlspecialchars(str) {
            if (!str) return '';
            return $('<div>').text(str).html();
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function openAssignRoleModal(id, name, email, currentRole) {
            document.getElementById('modalUserId').value = id;
            document.getElementById('modalUserName').innerText = name;
            document.getElementById('modalUserEmail').innerText = email;
            document.getElementById('modalRoleSelect').value = currentRole;

            window.showTailwindModal('#assignRoleModal');
        }

        function submitAssignRole(event) {
            event.preventDefault();
            var userId = document.getElementById('modalUserId').value;
            var role = document.getElementById('modalRoleSelect').value;
            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var btn = document.getElementById('btnSaveRole');

            if (!role) {
                alert('Please select a role');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Saving...';

            fetch(SITEURL + '/dashboard/users/' + encodeURIComponent(userId) + '/assign-role', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ role: role })
            })
            .then(function(res) { 
                if (!res.ok) {
                    return res.json().then(function(json) { throw new Error(json.message || 'Server error ' + res.status); });
                }
                return res.json(); 
            })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-check-line"></i> Save Role';

                window.hideTailwindModal('#assignRoleModal');

                if (data.success) {
                    swal("Success", data.message || "Role updated successfully", "success", {
                        timer: 1500,
                        button: false,
                    });
                    if (table) {
                        table.ajax.reload(null, false);
                    }
                } else {
                    swal("Error", data.message || "Failed to update role", "error");
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-check-line"></i> Save Role';
                swal("Error", err.message || "Something went wrong. Please try again.", "error");
            });
        }
    </script>
@endpush
