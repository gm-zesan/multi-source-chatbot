@extends('admin.app')
@section('title', 'Contact Messages')

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
@endpush

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Contact Messages</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Contact Messages</span>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="app-card overflow-hidden">
        <div class="app-card-header">
            <div>
                <h2 class="text-xs font-semibold text-light-text dark:text-dark-text uppercase tracking-wider">Inbound Customer Inquiries</h2>
                <p class="text-2xs text-light-muted dark:text-dark-muted">Messages received through public website contact forms</p>
            </div>
        </div>

        <div class="app-card-body p-0">
            <div class="overflow-x-auto p-3">
                <table class="app-table w-full" id="data-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;" class="no-sort">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" class="app-checkbox app-select-all-checkbox" title="Select All">
                                    <span>#</span>
                                </div>
                            </th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th style="width: 70px;" class="text-center">Priority</th>
                            <th style="width: 80px;" class="no-sort text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Message Read Modal -->
<div class="fixed inset-0 z-50 hidden bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4 modal" id="readMessage" tabindex="-1">
    <div class="app-card w-full max-w-lg shadow-modal modal-dialog overflow-hidden">
        <div class="app-card-header flex items-center justify-between">
            <h3 class="text-xs font-semibold text-light-text dark:text-dark-text tracking-tight truncate" id="readMessageLabel">Message Details</h3>
            <button type="button" class="text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text" data-bs-dismiss="modal" aria-label="Close">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
        <div class="app-card-body max-h-[60vh] overflow-y-auto">
            <div class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed whitespace-pre-wrap modal-body" id="readMessageBody">
            </div>
        </div>
        <div class="app-card-footer flex justify-end">
            <button type="button" class="app-btn app-btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js" defer></script>

    <script type="text/javascript">
        var listUrl = SITEURL + '/dashboard/message';
        var readStatusUrl = SITEURL + '/dashboard/message/read';
        var importantStatusUrl = SITEURL + '/dashboard/message/important';
        var table;

        function filterMessagesByStatus(el, status) {
            document.querySelectorAll('[data-msg-chip]').forEach(function(chip) {
                chip.classList.remove('active');
            });
            if (el) el.classList.add('active');
            if (table) {
                table.column(6).search(status || '').draw();
            }
        }

        $(document).ready(function() {
            table = $('#data-table').DataTable({
                processing: true,
                responsive: true,
                serverSide: true,
                order: [[1, 'desc']],
                pageLength: 20,
                lengthMenu: [20, 50, 100],
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
                            const isStarred = data.important_status == 1;
                            const iconClass = isStarred ? 'ri-star-s-fill text-amber-400' : 'ri-star-s-line text-text-muted hover:text-amber-400';
                            return `
                                <div id="status-${data.id}" 
                                    class="important-status-change flex justify-center cursor-pointer text-base" 
                                    data-id="${data.id}" 
                                    data-status="${data.important_status}" 
                                    title="Toggle importance">
                                    <i class="${iconClass}"></i>
                                </div>`;
                        }
                    },
                    { 
                        data: 'action-btn', 
                        orderable: false, 
                        render: function(data) {
                            return `
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" class="app-action-btn bg-brand-500/15 text-brand-500 hover:bg-brand-500 hover:text-white" onclick="modalRead(this, '${escapeHtml(data.message)}')" id="${data.id}" title="Read Message">
                                        <i class="ri-book-read-line"></i>
                                    </button>
                                    <a href="message/delete/${data.id}" class="app-action-btn bg-red-500/15 text-red-500 hover:bg-red-500 hover:text-white" title="Delete Message" onclick="return confirm('Delete this message?')">
                                        <i class="ri-delete-bin-2-line"></i>
                                    </a>
                                </div>`;
                        }
                    }
                ],
                order: [[0, 'desc']]
            });
        });

        $('body').on('click', '.important-status-change', function() {
            var id = $(this).data('id');
            var status = $(this).data('status');
            var newStatus = status == 1 ? 0 : 1;

            $.ajax({
                type: "GET",
                dataType: "json",
                url: importantStatusUrl,
                data: {
                    'important_status': newStatus,
                    'id': id
                },
                success: function(data) {
                    $('#data-table').DataTable().ajax.reload(null, false);
                }
            });
        });

        function modalRead(e, msg) {
            var row = $(e).closest('tr');
            var id = row.find('td').eq(0).text().trim();
            var name = row.find('td').eq(1).text().trim();
            var phone = row.find('td').eq(3).text().trim();

            $.ajax({
                type: "GET",
                dataType: "json",
                url: readStatusUrl,
                data: { 'id': id },
                success: function(data) {
                    $('#readMessageLabel').text('Message from ' + name + (phone ? ' (' + phone + ')' : ''));
                    $('#readMessageBody').text(msg);
                    if (window.showTailwindModal) {
                        window.showTailwindModal('#readMessage');
                    } else {
                        $('#readMessage').modal('show');
                    }
                }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
    </script>
@endpush
