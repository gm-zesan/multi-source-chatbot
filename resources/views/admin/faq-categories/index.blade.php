@extends('admin.app')
@section('title')
    FAQ Categories
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
                            <div class="table-title">FAQ Category</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">FAQ Category</li>
                                </ol>
                            </nav>
                        </div>
                        @role(\App\Enums\RoleEnum::SUPERADMIN->value)
                            <a href="{{ route('faq-categories.create') }}" class="add-new">Create Category<i
                                    class="ms-1 ri-add-line"></i></a>
                        @endrole
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100" id="data-table" style="min-width: 800px;">
                            <thead>
                                <tr>
                                    <th scope="col">SL NO</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Slug</th>
                                    <th scope="col">FAQs</th>
                                    <th scope="col">Order</th>
                                    <th scope="col">Status</th>
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
    @if (Session::has('success'))
        <script>
            swal("success", "{{ Session::get('success') }}", "success", {
                timer: 1000,
                button: false,
            });
        </script>
    @endif

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/faq-categories';

        $(document).ready(function() {
            var table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                fixedHeader: true,
                "pageLength": 20,
                "lengthMenu": [20, 50, 100, 500],
                ajax: {
                    url: listUrl,
                    type: 'GET'
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name',
                        name: 'name',
                        orderable: true
                    },
                    {
                        data: 'slug',
                        name: 'slug',
                        orderable: true
                    },
                    {
                        data: 'faqs_count',
                        name: 'faqs_count',
                        orderable: true
                    },
                    {
                        data: 'order_column',
                        name: 'order_column',
                        orderable: true
                    },
                    {
                        data: 'status_badge',
                        name: 'status_badge',
                        orderable: false
                    },
                    {
                        data: 'action-btn',
                        orderable: false,
                        render: function(data) {
                            var btns = '';
                            btns += '<div class="action-btn">';

                            btns += '<a href="' + SITEURL + '/dashboard/faq-categories/' + data.id +
                                '/edit" title="Edit" class="btn btn-edit"><i class="ri-edit-line"></i></a>';

                            if (!data.trashed) {
                                btns += '<form action="' + SITEURL + '/dashboard/faq-categories/' +
                                    data.id +
                                    '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this category?\');">' +
                                    '@csrf' +
                                    '@method('DELETE')' +
                                    '<button type="submit" class="btn btn-delete"><i class="ri-delete-bin-2-line"></i></button>' +
                                    '</form>';
                            } else {
                                btns += '<form action="' + SITEURL + '/dashboard/faq-categories/' +
                                    data.id +
                                    '/restore" method="POST" style="display: inline;">' +
                                    '@csrf' +
                                    '<button type="submit" class="btn btn-success"><i class="ri-refresh-line"></i></button>' +
                                    '</form>';
                            }

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
    </script>
@endpush
