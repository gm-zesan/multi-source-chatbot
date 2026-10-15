@extends('admin.app')
@section('title')
    Knowledge Documents
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
    <style>
        .doc-title-cell {
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }
        .doc-filename-cell {
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: monospace;
            font-size: 12px;
            color: #64748b;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Knowledge Documents</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Documents</li>
                                </ol>
                            </nav>
                        </div>
                        <a href="{{ route('documents.create') }}" class="add-new">Upload Document<i class="ms-1 ri-upload-cloud-2-line"></i></a>
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100" id="data-table" style="min-width: 850px;">
                            <thead>
                                <tr>
                                    <th scope="col">SL NO</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Original Filename</th>
                                    <th scope="col">Size</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Uploaded At</th>
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
            swal("Success", "{{ Session::get('success') }}", "success", {
                timer: 1500,
                button: false,
            });
        </script>
    @endif

    @if (Session::has('error'))
        <script>
            swal("Error", "{{ Session::get('error') }}", "error", {
                timer: 3000,
                button: true,
            });
        </script>
    @endif

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/documents';

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
                columns: [
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'title',
                        name: 'title',
                        orderable: true,
                        render: function(data) {
                            var safe = $('<div>').text(data || '').html();
                            return '<div class="doc-title-cell" title="' + safe + '">' + safe + '</div>';
                        }
                    },
                    {
                        data: 'original_filename',
                        name: 'original_filename',
                        orderable: true,
                        render: function(data) {
                            var safe = $('<div>').text(data || '').html();
                            return '<div class="doc-filename-cell" title="' + safe + '">' + safe + '</div>';
                        }
                    },
                    {
                        data: 'file_size',
                        name: 'file_size',
                        orderable: true
                    },
                    {
                        data: 'mime_type',
                        name: 'mime_type',
                        orderable: true
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        orderable: true
                    },
                    {
                        data: 'status_badge',
                        name: 'status_badge',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action-btn',
                        name: 'action-btn',
                        orderable: false,
                        searchable: false,
                        render: function(data) {
                            if (!data) return '';
                            var btns = '<div class="action-btn d-flex align-items-center gap-1">';

                            if (!data.trashed) {
                                btns += '<form action="' + SITEURL + '/dashboard/documents/' + data.id +
                                    '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this document?\');">' +
                                    '@csrf' +
                                    '@method('DELETE')' +
                                    '<button type="submit" class="btn btn-delete" title="Move to trash"><i class="ri-delete-bin-2-line"></i></button>' +
                                    '</form>';
                            } else {
                                btns += '<form action="' + SITEURL + '/dashboard/documents/' + data.id +
                                    '/restore" method="POST" style="display: inline;">' +
                                    '@csrf' +
                                    '<button type="submit" class="btn btn-success" title="Restore document"><i class="ri-refresh-line"></i></button>' +
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
