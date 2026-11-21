@extends('admin.app')
@section('title')
    Unanswered Questions | Knowledge Base
@endsection

@push('custom-style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <style>
        .question-text-cell {
            max-width: 420px;
            white-space: normal;
            word-break: break-word;
            font-weight: 500;
        }
    </style>
@endpush

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Unanswered Customer Queries</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <a href="{{ route('knowledge-base.index') }}" class="hover:text-text-primary transition-colors">Knowledge Base</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Unanswered Questions</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('knowledge-base.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-book-open-line"></i> View Knowledge Base
            </a>
            <a href="{{ route('faqs.create') }}" class="app-btn app-btn-primary">
                <i class="ri-add-line"></i> New FAQ
            </a>
        </div>
    </div>

    {{-- KPI Metric Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="app-card p-3">
            <span class="text-2xs font-medium text-text-muted uppercase tracking-wider block">Total Tracked</span>
            <span class="text-lg font-bold text-text-primary mt-1 block">{{ number_format($stats['total'] ?? 0) }}</span>
        </div>
        <div class="app-card p-3">
            <span class="text-2xs font-medium text-amber-500 uppercase tracking-wider block">Pending Review</span>
            <span class="text-lg font-bold text-amber-600 dark:text-amber-400 mt-1 block">{{ number_format($stats['pending'] ?? 0) }}</span>
        </div>
        <div class="app-card p-3">
            <span class="text-2xs font-medium text-emerald-500 uppercase tracking-wider block">Resolved / Converted</span>
            <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{{ number_format($stats['resolved'] ?? 0) }}</span>
        </div>
        <div class="app-card p-3">
            <span class="text-2xs font-medium text-text-muted uppercase tracking-wider block">Ignored</span>
            <span class="text-lg font-bold text-text-muted mt-1 block">{{ number_format($stats['ignored'] ?? 0) }}</span>
        </div>
    </div>

    {{-- Main Card --}}
    <div class="app-card table-card">
        <div class="app-card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Unanswered Inquiries Table</h2>
                <p class="text-2xs text-text-muted">Questions asked by customers that currently lack grounded answers in your knowledge base</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if (!empty($isSuperAdmin) && isset($workspaces) && count($workspaces) > 0)
                    <select id="filter-workspace" class="app-select w-44 text-xs" onchange="reloadTable()">
                        <option value="">All Workspaces</option>
                        @foreach ($workspaces as $ws)
                            <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select id="filter-status" class="app-select w-36 text-xs" onchange="reloadTable()">
                    <option value="">All Statuses</option>
                    <option value="pending" selected>Pending Only</option>
                    <option value="resolved">Resolved</option>
                    <option value="ignored">Ignored</option>
                </select>
            </div>
        </div>

        <div class="app-card-body p-0">
            <div class="overflow-x-auto p-3">
                <table class="app-table w-full" id="unanswered-table">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th style="width: 100px;">Frequency</th>
                            <th style="width: 110px;">Status</th>
                            <th style="width: 140px;">Recorded</th>
                            <th style="width: 180px;" class="no-sort text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Convert to FAQ Modal --}}
<div id="convert-faq-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 hidden">
    <div class="app-card w-full max-w-lg mx-4 p-5 space-y-4 shadow-xl border border-border">
        <div class="flex items-center justify-between pb-2 border-b border-border">
            <h3 class="text-sm font-semibold text-text-primary flex items-center gap-2">
                <i class="ri-lightbulb-line text-brand-primary"></i> Convert Question to Knowledge FAQ
            </h3>
            <button type="button" class="text-text-muted hover:text-text-primary" onclick="closeConvertModal()">
                <i class="ri-close-line text-lg"></i>
            </button>
        </div>

        <form id="convert-faq-form" method="POST" onsubmit="submitConvertToFaq(event)">
            @csrf
            <input type="hidden" id="convert-question-id" name="question_id">

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Customer Question</label>
                    <input type="text" id="convert-faq-question" name="question" class="app-input w-full text-xs" required>
                </div>

                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Official Grounded Answer <span class="text-rose-500">*</span></label>
                    <textarea id="convert-faq-answer" name="answer" rows="4" class="app-input w-full text-xs" placeholder="Type the accurate business answer that the AI will use to answer future inquiries..." required></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-text-secondary mb-1">Document Category</label>
                    <select name="document_type" id="convert-faq-document-type" class="app-select w-full text-xs">
                        <option value="faq">General FAQ</option>
                        <option value="return_policy">Return Policy</option>
                        <option value="delivery_policy">Delivery Policy</option>
                        <option value="warranty_policy">Warranty Policy</option>
                        <option value="exchange_policy">Exchange Policy</option>
                        <option value="contact">Contact Information</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="convert-faq-active" value="1" class="rounded border-border text-brand-primary focus:ring-brand-primary" checked>
                    <label for="convert-faq-active" class="text-xs font-medium text-text-primary cursor-pointer select-none">
                        Activate and index into retrieval engine immediately
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-border mt-4">
                <button type="button" class="app-btn app-btn-secondary text-xs" onclick="closeConvertModal()">Cancel</button>
                <button type="submit" id="btn-submit-convert" class="app-btn app-btn-primary text-xs">
                    <i class="ri-check-line"></i> Save as FAQ & Resolve
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('custom-script')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>
        let table;

        document.addEventListener('DOMContentLoaded', function () {
            table = $('#unanswered-table').DataTable({
                processing: true,
                serverSide: true,
                order: [[1, 'desc']],
                ajax: {
                    url: '{{ route("unanswered-questions.index") }}',
                    data: function (d) {
                        d.status = $('#filter-status').val();
                        if ($('#filter-workspace').length) {
                            d.workspace_id = $('#filter-workspace').val();
                        }
                    }
                },
                columns: [
                    {
                        data: 'original_question',
                        name: 'original_question',
                        render: function (data, type, row) {
                            return '<div class="question-text-cell">' + $('<div>').text(data).html() + '</div>';
                        }
                    },
                    { data: 'occurrence_count_badge', name: 'occurrence_count' },
                    { data: 'status_badge', name: 'status' },
                    { data: 'created_formatted', name: 'created_at' },
                    {
                        data: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-right',
                        render: function (data) {
                            const qId = data.id;
                            const safeQuestion = JSON.stringify(data.original_question || '');
                            let html = '<div class="flex items-center justify-end gap-1.5">';
                            
                            if (data.status !== 'resolved') {
                                html += `<button type="button" class="app-btn app-btn-secondary !h-7 !px-2 !text-2xs" onclick='openConvertModal(${qId}, ${safeQuestion})' title="1-Click Convert to FAQ">
                                    <i class="ri-lightbulb-line text-brand-primary"></i> Convert
                                </button>`;
                                html += `<button type="button" class="app-btn app-btn-secondary !h-7 !px-2 !text-2xs" onclick="updateStatus(${qId}, 'resolved')" title="Mark as Resolved">
                                    <i class="ri-check-line text-emerald-500"></i>
                                </button>`;
                            }

                            if (data.status === 'pending') {
                                html += `<button type="button" class="app-btn app-btn-secondary !h-7 !px-2 !text-2xs text-text-muted hover:text-rose-500" onclick="updateStatus(${qId}, 'ignored')" title="Ignore Question">
                                    <i class="ri-close-line"></i>
                                </button>`;
                            }

                            html += `<button type="button" class="app-btn app-btn-secondary !h-7 !px-2 !text-2xs text-rose-500 hover:bg-rose-500/10" onclick="deleteQuestion(${qId})" title="Delete">
                                <i class="ri-delete-bin-line"></i>
                            </button>`;
                            html += '</div>';
                            return html;
                        }
                    }
                ]
            });
        });

        function reloadTable() {
            table.ajax.reload();
        }

        function openConvertModal(id, originalQuestion) {
            document.getElementById('convert-question-id').value = id;
            document.getElementById('convert-faq-question').value = originalQuestion;
            document.getElementById('convert-faq-answer').value = '';
            document.getElementById('convert-faq-modal').classList.remove('hidden');
        }

        function closeConvertModal() {
            document.getElementById('convert-faq-modal').classList.add('hidden');
        }

        async function submitConvertToFaq(e) {
            e.preventDefault();
            const qId = document.getElementById('convert-question-id').value;
            const btn = document.getElementById('btn-submit-convert');
            btn.disabled = true;

            try {
                const res = await fetch(`/unanswered-questions/${qId}/convert-to-faq`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        question: document.getElementById('convert-faq-question').value,
                        answer: document.getElementById('convert-faq-answer').value,
                        category_id: document.getElementById('convert-faq-category') ? document.getElementById('convert-faq-category').value : null,
                        is_active: document.getElementById('convert-faq-active').checked
                    })
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    closeConvertModal();
                    reloadTable();
                } else {
                    alert(data.message || 'Failed to convert question to FAQ.');
                }
            } catch (err) {
                alert('An error occurred during submission.');
            } finally {
                btn.disabled = false;
            }
        }

        async function updateStatus(id, newStatus) {
            try {
                const res = await fetch(`/unanswered-questions/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                if (res.ok) {
                    reloadTable();
                }
            } catch (err) {
                console.error(err);
            }
        }

        async function deleteQuestion(id) {
            if (!confirm('Are you sure you want to delete this recorded question?')) return;
            try {
                const res = await fetch(`/unanswered-questions/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                if (res.ok) {
                    reloadTable();
                }
            } catch (err) {
                console.error(err);
            }
        }
    </script>
@endpush
