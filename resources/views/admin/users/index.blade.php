@extends('admin.app')

@section('title')
    Users Management
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
                <span>Users & Access Control</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Manage tenant user accounts, assigned permissions, and role delegations.
            </p>
        </div>
        @role(\App\Enums\RoleEnum::SUPERADMIN->value)
            <a href="{{ route('users.create') }}" class="app-btn app-btn-primary">
                <i class="ri-add-line text-sm"></i>
                <span>Create User</span>
            </a>
        @endrole
    </div>

    {{-- Data Table Card --}}
    <div class="app-card overflow-hidden">
        <div class="p-3 overflow-x-auto">
            <table class="app-table w-full" id="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" class="app-checkbox app-select-all-checkbox" title="Select All">
                                <span>#</span>
                            </div>
                        </th>
                        <th style="width: 24%;">Name</th>
                        <th style="width: 24%;">Email</th>
                        <th style="width: 16%;">Phone No</th>
                        <th style="width: 14%;">Role</th>
                        <th style="width: 14%; text-align: right;">Action</th>
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
                    <span>Assign User Role</span>
                </h3>
                <button type="button" class="text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-base"></i>
                </button>
            </div>
            <div class="app-card-body space-y-3">
                <div class="space-y-0.5">
                    <div class="text-2xs text-light-muted dark:text-dark-muted uppercase font-semibold">User Name</div>
                    <div class="text-xs font-bold text-light-text dark:text-dark-text" id="modalUserName">Loading...</div>
                </div>
                <div class="space-y-0.5">
                    <div class="text-2xs text-light-muted dark:text-dark-muted uppercase font-semibold">Email Address</div>
                    <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary" id="modalUserEmail">loading@example.com</div>
                </div>
                <div class="space-y-1 pt-1">
                    <label for="modalRoleSelect" class="text-2xs font-semibold text-light-text dark:text-dark-text">Choose Role <span class="text-red-500">*</span></label>
                    <select class="app-select w-full" id="modalRoleSelect" name="role" required>
                        <option value="">-- Choose Role --</option>
                        @foreach($roles ?? [] as $r)
                            <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                        @endforeach
                    </select>
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
            swal("success", "{{ Session::get('success') }}", "success", {
                timer: 1000,
                button: false,
            });
        </script>
    @endif
    
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/users';
        var table;

        $(document).ready(function() {
            table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                fixedHeader: true,
                autoWidth: false,
                "pageLength": 20,
                "lengthMenu": [20, 50, 100, 500],
                ajax: {
                    url: listUrl,
                    type: 'GET'
                },
                columns: [
                    {
                        data: 'id',
                        name: 'id',
                        orderable: false,
                        searchable: false,
                        width: '70px',
                        render: function(data, type, row) {
                            return `<div class="flex items-center gap-2">
                                <input type="checkbox" class="app-checkbox app-row-checkbox" data-id="${data}" value="${data}">
                                <span class="text-2xs text-light-muted dark:text-dark-muted font-mono tabular-nums">${data}</span>
                            </div>`;
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
                        width: '16%'
                    },
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

                            // Assign Role button
                            btns += '<button type="button" class="app-action-btn bg-brand-500/15 text-brand-500 hover:bg-brand-500 hover:text-white" title="Assign Role" onclick="openAssignRoleModal(\'' + data.id + '\', \'' + escapeHtml(data.name) + '\', \'' + escapeHtml(data.email) + '\', \'' + escapeHtml(data.role) + '\')"><i class="ri-shield-user-line"></i></button>';

                            // Edit button
                            btns += '<a href="' + SITEURL + '/dashboard/users/' + data.id + '/edit" title="Edit" class="app-action-btn bg-sky-500/15 text-sky-500 hover:bg-sky-500 hover:text-white"><i class="ri-edit-line"></i></a>';

                            // Delete button
                            btns += '<form action="' + SITEURL + '/dashboard/users/' + data.id + '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this user?\');">' +
                                '@csrf' +
                                '@method('DELETE')' +
                                '<button type="submit" class="app-action-btn bg-red-500/15 text-red-500 hover:bg-red-500 hover:text-white"><i class="ri-delete-bin-2-line"></i></button>' +
                                '</form>';

                            btns += '</div>';
                            return btns;
                        }
                    }
                ],
                order: [
                    [1, 'asc']
                ]
            });
        });

        function filterUsersByRole(el, role) {
            document.querySelectorAll('[data-user-role-chip]').forEach(function(chip) {
                chip.classList.remove('active');
            });
            if (el) el.classList.add('active');
            if (table) {
                table.column(4).search(role || '').draw();
            }
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
            .then(function(res) { return res.json(); })
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
                swal("Error", "Something went wrong. Please try again.", "error");
                console.error(err);
            });
        }
    </script>
@endpush
