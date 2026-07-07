@extends('admin.app')
@section('title')
    Roles
@endsection


@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
@endpush


@section('content')
    <div class="container-fluid my-3">
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Role</div>
                            <nav aria-label="breadcrumb"> 
                                <ol class="breadcrumb mb-0"> 
                                    <li class="breadcrumb-item">
                                        <a href="{{route('dashboard')}}">Dashboard</a>
                                    </li> 
                                    <li class="breadcrumb-item active" aria-current="page">Role</li> 
                                </ol> 
                            </nav>
                        </div>
                        @if (Auth::user()->hasRole('superadmin'))
                            <a href="{{route('roles.create')}}" class="add-new">New Role<i class="ms-1 ri-add-line"></i></a>
                        @endif
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100" id="data-table" style="min-width: 800px;">
                            <thead>
                                <tr>
                                    <th scope="col">SL NO</th>
                                    <th scope="col">Role Name</th>
                                    <th scope="col">Action</th>
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
@endsection


@push('custom-scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/roles';

        $(document).ready( function () {
            var table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                fixedHeader: true,
                "pageLength": 20,
                "lengthMenu": [ 20, 50, 100, 500 ],
                ajax: {
                    url: listUrl,
                    type: 'GET'
                },
                columns: [
                    { data: 'id', name: 'id', orderable: true },
                    { data: 'name', name: 'name', orderable: true },
                    {
                        data: 'action-btn',
                        orderable: false,
                        render: function (data) {
                            var btns = '';
                            btns += '<div class="action-btn">';
                            btns += '<a href="' + SITEURL + '/dashboard/roles/' + data.id + '/edit"title="edit" class="btn btn-edit"><i class="ri-edit-line"></i></a>';
                            if(data.role == 'superadmin'){
                                btns += '<form action="' + SITEURL + '/dashboard/roles/' + data.id + '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this role?\');">' +
                                    '@csrf' +
                                    '@method("DELETE")' +
                                    '<button type="submit" class="btn btn-delete"><i class="ri-delete-bin-2-line"></i></button>' +
                                '</form>';
                            }
                            btns += '</div>';
                            return btns;
                        }
                    }
                ],
                order: [[0, 'asc']]
            });
        });
    </script>

    <script>
        $('.submit-button').click(function(){
            $(this).css('opacity', '1');
            $(this).find('.spinner-border').removeClass('d-none');
            $(this).attr('disabled', true);
            $(this).closest('form').submit();
        });
    </script>

    <script>
        function checkRoleModule(moduleCheckbox) {
            var moduleId = moduleCheckbox.id;
            var isChecked = moduleCheckbox.checked;

            var innerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
            innerCheckboxes.forEach(function(innerCheckbox) {
                innerCheckbox.checked = isChecked;
            });
        }

        function innerCheckboxUncheck(innerCheckbox) {
            var moduleId = innerCheckbox.getAttribute('data-module');
            var moduleCheckbox = document.getElementById(moduleId);

            var allInnerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
            var allChecked = true;
            allInnerCheckboxes.forEach(function(checkbox) {
                if (!checkbox.checked) {
                    allChecked = false;
                }
            });

            moduleCheckbox.checked = allChecked;
        }


        var modules = document.querySelectorAll('[data-module]');
        modules.forEach(function(module) {
            var moduleId = module.getAttribute('data-module');
            var moduleCheckbox = document.getElementById(moduleId);

            var allInnerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
            var allChecked = true;
            allInnerCheckboxes.forEach(function(checkbox) {
                if (!checkbox.checked) {
                    allChecked = false;
                }
            });

            moduleCheckbox.checked = allChecked;
        });
    </script>
@endpush
