@extends('admin.app')
@section('title')
    Contact Messages
@endsection



@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
    <style>
        .readColor tbody tr {
            background-color: #eee !important;
        }

        .readColor tbody tr.readColorTr {
            background-color: #fff !important;
        }
    </style>
@endpush



@section('content')
    <div class="container-fluid my-3">
        <div class="row">
            <div class="col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Contact Messages</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('message') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Contact Message</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <div class="card-body" style="overflow-x: auto">
                        <table class="table dataTable w-100 readColor" id="data-table" style="min-width: 800px;">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 8%">SL NO</th>
                                    <th scope="col" style="width: 10%">Name</th>
                                    <th scope="col" style="width: 20%">Email</th>
                                    <th scope="col" style="width: 15%">Phone</th>
                                    <th scope="col" style="width: 17%">Subject</th>
                                    <th scope="col" style="width: 20%">Message</th>
                                    <th scope="col" style="width: 5%">Important</th>
                                    <th scope="col" style="width: 5%">Action</th>
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

    <!-- Modal -->
    <div class="modal fade" id="readMessage" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="readMessageLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="readMessageLabel"></h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                </div>
            </div>
        </div>
    </div>
@endsection


@push('custom-scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/message';
        var readStatusUrl = SITEURL + '/dashboard/message/read';
        var importantStatusUrl = SITEURL + '/dashboard/message/important';

        $(document).ready(function() {
            var table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                fixedHeader: true,
                pageLength: 20,
                lengthMenu: [20, 50, 100, 500],
                ajax: {
                    url: listUrl,
                    type: 'GET'
                },
                columns: [
                    { data: 'id', name: 'id', orderable: true },
                    { data: 'name', name: 'name', orderable: true },
                    { data: 'email', name: 'email', orderable: true },
                    { data: 'phone', name: 'phone', orderable: true },
                    { data: 'subject', name: 'subject', orderable: true },
                    { 
                        data: 'message', 
                        name: 'message', 
                        orderable: true, 
                        render: function(data) {
                            return data.length > 50 ? `${data.substr(0, 47)}...` : data;
                        }
                    },
                    { 
                        data: 'important_status', 
                        name: 'important_status', 
                        orderable: true, 
                        render: function(data) {
                            const iconClass = data.important_status == 1 
                                ? 'ri-star-s-fill' 
                                : 'ri-star-s-line';
                            return `
                                <div id="status-${data.id}" 
                                    class="important-status-change" 
                                    data-id="${data.id}" 
                                    data-status="${data.important_status}" 
                                    style="font-size: 20px; text-align: center; cursor: pointer;" 
                                    title="Toggle status">
                                    <i class="${iconClass}" style="color: #f5c60d;"></i>
                                </div>`;
                        }
                    },
                    { 
                        data: 'action-btn', 
                        orderable: false, 
                        render: function(data) {
                            return `
                                <div class="action-btn">
                                    <a class="btn btn-edit" data-bs-toggle="modal" onclick="modalRead(this, '${data.message}')" id="${data.id}">
                                        <i class="ri-book-read-line"></i>
                                    </a>
                                    <a href="message/delete/${data.id}" class="btn btn-delete">
                                        <i class="ri-delete-bin-2-line"></i>
                                    </a>
                                </div>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                rowCallback: function(row, data) {
                    if (data.read_status == 1) {
                        $(row).addClass('readColorTr');
                    }
                }
            });
        });



        $('body').on('click', '.important-status-change', function() {
            var id = $(this).data('id');
            var status = $(this).data('status');
            $('#status-' + id).html('<i class="ri-handbag-line"></i>');
            var name;
            if (status == 1) {
                status = 0;
                name = '<i class="ri-star-s-line" style="color: #f5c60d;"></i>';
            } else {
                status = 1;
                name = '<i class="ri-star-s-fill" style="color: #f5c60d;"></i>';
            }

            $.ajax({
                type: "GET",
                dataType: "json",
                url: importantStatusUrl,
                data: {
                    'important_status': status,
                    'id': id
                },
                success: function(data) {
                    $('#status-' + id).html(name);
                    $('#status-' + id).attr('data-status', status);
                    $('#data-table').DataTable().ajax.reload();
                }
            });
        });

        function modalRead(e, msg) {
            var id = $(e).closest('tr').find('td').eq(0).text();
            var name = $(e).closest('tr').find('td').eq(1).text();
            var phone = $(e).closest('tr').find('td').eq(2).text();
            var message = msg;
            // var message = $(e).closest('tr').find('td').eq(5).text();
            $.ajax({
                type: "GET",
                dataType: "json",
                url: readStatusUrl,
                data: {
                    'id': id
                },
                success: function(data) {
                    $(e).closest('tr').addClass('readColorTr');
                    $('#readMessageLabel').text('Message from "' + name + '" (' + phone + ')');
                    $('.modal-body').text(message);
                    $('#readMessage').modal('show');
                }
            });
        }
    </script>
@endpush
