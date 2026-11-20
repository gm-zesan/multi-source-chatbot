@extends('admin.app')

@section('title')
    Roles Management
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
                <span>Roles & Permissions</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Configure role designations, authorization boundaries, and access levels.
            </p>
        </div>
        @role(\App\Enums\RoleEnum::SUPERADMIN->value)
            <a href="{{ route('roles.create') }}" class="app-btn app-btn-primary">
                <i class="ri-add-line text-sm"></i>
                <span>New Role</span>
            </a>
        @endrole
    </div>

    {{-- Data Table Card --}}
    <div class="app-card overflow-hidden">
        <div class="p-3 overflow-x-auto">
            <table class="app-table w-full" id="data-table">
                <thead>
                    <tr>
                        <th style="width: 70px;" class="no-sort">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" class="app-checkbox app-select-all-checkbox" title="Select All">
                                <span>#</span>
                            </div>
                        </th>
                        <th style="width: 68%;">Role Name</th>
                        <th style="width: 20%; text-align: right;" class="no-sort">Action</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/roles';

        $(document).ready(function() {
            var table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                fixedHeader: true,
                autoWidth: false,
                order: [[1, 'asc']],
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
                        render: function(data) {
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
                        width: '68%'
                    },
                    {
                        data: 'action-btn',
                        orderable: false,
                        width: '20%',
                        render: function(data) {
                            var btns = '';
                            btns += '<div class="flex items-center justify-end gap-1.5">';
                            btns += '<a href="' + SITEURL + '/dashboard/roles/' + data.id +
                                '/edit" title="Edit" class="app-action-btn bg-sky-500/15 text-sky-500 hover:bg-sky-500 hover:text-white"><i class="ri-edit-line"></i></a>';
                            if (data.role == '{{ \App\Enums\RoleEnum::SUPERADMIN->value }}') {
                                btns += '<form action="' + SITEURL + '/dashboard/roles/' + data.id +
                                    '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this role?\');">' +
                                    '@csrf' +
                                    '@method('DELETE')' +
                                    '<button type="submit" class="app-action-btn bg-red-500/15 text-red-500 hover:bg-red-500 hover:text-white"><i class="ri-delete-bin-2-line"></i></button>' +
                                    '</form>';
                            }
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
    </script>
@endpush
