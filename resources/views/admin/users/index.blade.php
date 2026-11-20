@extends('admin.app')
@section('title')
    Users
@endsection

@push('custom-style')
    {{-- Datatable css --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Users</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Users</li>
                                </ol>
                            </nav>
                        </div>
                        @role(\App\Enums\RoleEnum::SUPERADMIN->value)
                            <a href="{{ route('users.create') }}" class="add-new">Create User<i class="ms-1 ri-add-line"></i></a>
                        @endrole
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100" id="data-table" style="width: 100% !important;">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 8%;">SL NO</th>
                                    <th scope="col" style="width: 24%;">Name</th>
                                    <th scope="col" style="width: 24%;">Email</th>
                                    <th scope="col" style="width: 16%;">Phone No</th>
                                    <th scope="col" style="width: 14%;">Role</th>
                                    <th scope="col" style="width: 14%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Assign Role Modal --}}
    <div class="modal fade" id="assignRoleModal" tabindex="-1" aria-labelledby="assignRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="assignRoleModalLabel">
                        <i class="ri-shield-user-line text-primary"></i> Assign User Role
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="assignRoleForm" onsubmit="submitAssignRole(event)">
                    @csrf
                    <input type="hidden" id="modalUserId" name="user_id">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold d-block">User Name</label>
                            <div class="fw-bold text-dark fs-6" id="modalUserName">Loading...</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold d-block">User Email</label>
                            <div class="text-muted" id="modalUserEmail">loading@example.com</div>
                        </div>
                        <div class="mb-3">
                            <label for="modalRoleSelect" class="form-label text-dark fw-semibold small">Assign Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="modalRoleSelect" name="role" required>
                                <option value="">-- Choose Role --</option>
                                @foreach($roles ?? [] as $r)
                                    <option value="{{ $r }}">{{ ucfirst($r) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 px-4">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSaveRole">
                            <i class="ri-check-line me-1"></i> Save Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('custom-scripts')
    {{-- SweetAlert --}}
    @if (Session::has('success'))
        <script>
            swal("success", "{{ Session::get('success') }}", "success", {
                timer: 1000,
                button: false,
            });
        </script>
    @endif
    
    {{-- DataTables --}}
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

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
                        orderable: true,
                        width: '8%'
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
                            btns += '<div class="action-btn d-flex align-items-center">';

                            // Assign Role button (opens modal)
                            btns += '<button type="button" class="btn btn-assign-role" title="Assign Role" onclick="openAssignRoleModal(\'' + data.id + '\', \'' + escapeHtml(data.name) + '\', \'' + escapeHtml(data.email) + '\', \'' + escapeHtml(data.role) + '\')"><i class="ri-shield-user-line"></i></button>';

                            // Edit button
                            btns += '<a href="' + SITEURL + '/dashboard/users/' + data.id + '/edit" title="Edit" class="btn btn-edit"><i class="ri-edit-line"></i></a>';

                            // Delete button
                            btns += '<form action="' + SITEURL + '/dashboard/users/' + data.id + '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this user?\');">' +
                                '@csrf' +
                                '@method('DELETE')' +
                                '<button type="submit" class="btn btn-delete"><i class="ri-delete-bin-2-line"></i></button>' +
                                '</form>';

                            btns += '</div>';
                            return btns;
                        }
                    }
                ],
                order: [
                    [0, 'asc']
                ]
            });
        });

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

            var modal = new bootstrap.Modal(document.getElementById('assignRoleModal'));
            modal.show();
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
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';

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
                btn.innerHTML = '<i class="ri-check-line me-1"></i> Save Role';

                var modalEl = document.getElementById('assignRoleModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

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
                btn.innerHTML = '<i class="ri-check-line me-1"></i> Save Role';
                swal("Error", "Something went wrong. Please try again.", "error");
                console.error(err);
            });
        }
    </script>
@endpush
