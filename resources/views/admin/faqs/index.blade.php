@extends('admin.app')
@section('title')
    FAQ
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.semanticui.min.css">
    <style>
        .faq-question-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                            <div class="table-title">FAQ</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">FAQ</li>
                                </ol>
                            </nav>
                        </div>
                        @role(\App\Enums\RoleEnum::SUPERADMIN->value)
                            <a href="{{ route('faqs.create') }}" class="add-new">Create FAQ<i class="ms-1 ri-add-line"></i></a>
                        @endrole
                    </div>
                    <div class="card-body" style="overflow-x: auto">

                        {{-- <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label custom-label">Category</label>
                                <select id="category-filter" class="form-control custom-input single-select2">
                                    <option value="">All Categories</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label custom-label">Status</label>
                                <select id="status-filter" class="form-control custom-input single-select2">
                                    <option value="">All</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="trashed">Deleted</option>
                                </select>
                            </div>
                        </div> --}}

                        <table class="table dataTable w-100" id="data-table" style="min-width: 950px;">
                            <thead>
                                <tr>
                                    <th scope="col">SL NO</th>
                                    <th scope="col">Question</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Policy Type</th>
                                    <th scope="col">Commerce Domain</th>
                                    <th scope="col">AI Lexicon</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Hits</th>
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
        var listUrl = SITEURL + '/dashboard/faqs';

        function triggerResync(faqId) {
            swal({
                title: "Re-sync to Typesense?",
                text: "This will regenerate the commerce ontology lexicon and immediately sync vectors to Typesense.",
                icon: "info",
                buttons: ["Cancel", "Sync Now"],
            }).then((willSync) => {
                if (willSync) {
                    fetch(`/dashboard/faqs/${faqId}/resync`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        swal("Synced!", data.message || "FAQ synced successfully.", "success");
                        $('#data-table').DataTable().ajax.reload(null, false);
                    })
                    .catch(err => {
                        swal("Error", "Failed to dispatch sync job.", "error");
                    });
                }
            });
        }

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
                    type: 'GET',
                    data: function(d) {
                        d.category_id = $('#category-filter').val();
                        d.status = $('#status-filter').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'question',
                        name: 'question',
                        orderable: true,
                        render: function(data) {
                            return '<div class="faq-question-cell" title="' + $('<div>').text(data)
                                .html() + '">' + $('<div>').text(data).html() + '</div>';
                        }
                    },
                    {
                        data: 'category',
                        name: 'category',
                        orderable: true
                    },
                    {
                        data: 'document_type',
                        name: 'document_type',
                        orderable: true
                    },
                    {
                        data: 'commerce_domain',
                        name: 'commerce_domain',
                        orderable: false
                    },
                    {
                        data: 'lexicon_badge',
                        name: 'lexicon_badge',
                        orderable: false
                    },
                    {
                        data: 'priority',
                        name: 'priority',
                        orderable: true
                    },
                    {
                        data: 'hit_count',
                        name: 'hit_count',
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
                            btns += '<div class="action-btn d-flex align-items-center gap-1">';

                            btns += '<button type="button" class="btn btn-sm btn-outline-primary" style="padding: 3px 7px;" title="Re-sync & Regenerate Lexicon" onclick="triggerResync(\'' + data.id + '\')"><i class="ri-refresh-line"></i></button>';

                            btns += '<a href="' + SITEURL + '/dashboard/faqs/' + data.id +
                                '/edit" title="Edit" class="btn btn-edit"><i class="ri-edit-line"></i></a>';

                            if (!data.trashed) {
                                btns += '<form action="' + SITEURL + '/dashboard/faqs/' + data.id +
                                    '" method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure to delete this FAQ?\');">' +
                                    '@csrf' +
                                    '@method('DELETE')' +
                                    '<button type="submit" class="btn btn-delete"><i class="ri-delete-bin-2-line"></i></button>' +
                                    '</form>';
                            } else {
                                btns += '<form action="' + SITEURL + '/dashboard/faqs/' + data.id +
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

            $('#category-filter, #status-filter').on('change', function() {
                table.ajax.reload();
            });
        });
    </script>
@endpush
