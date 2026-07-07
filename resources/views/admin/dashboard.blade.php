@extends('admin.app')

@section('title')
    Dashboard
@endsection


@push('custom-style')
   {{-- Datatable css  --}}
   <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
   <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
   <style>
    .card{
        border-radius: 10px;
    }
   </style>
@endpush

@section('content')
    <div class="container-fluid my-3">
        <div class="row">
            <div class="col-md-12">
                <div class="card p-3">
                    <h4>Welcome to Admin Dashboard</h4>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('custom-scripts')
    {{-- Data table --}}
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/semantic-ui/2.3.1/semantic.min.js" defer></script>



    {{-- Datatable Ajax Call --}}
    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/message';

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
                    { data: 'name', name: 'name', orderable: true },
                    { data: 'email', name: 'email', orderable: true },
                    { data: 'subject', name: 'subject', orderable: true },
                    { data: 'message', name: 'message', orderable: true },
                ],
                order: [[0, 'asc']]
            });
        });
    </script>


@endpush