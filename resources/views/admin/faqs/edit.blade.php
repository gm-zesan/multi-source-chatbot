@extends('admin.app')
@section('title', 'Edit FAQ')

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Edit FAQ / Knowledge Entry</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <a href="{{ route('knowledge-base.index', ['tab' => 'faqs']) }}" class="hover:text-text-primary transition-colors">Knowledge Base</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Edit FAQ</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('knowledge-base.index', ['tab' => 'faqs']) }}" class="app-btn app-btn-secondary">
                <i class="ri-arrow-left-line"></i> Back to Knowledge Base
            </a>
        </div>
    </div>

    <form action="{{ route('faqs.update', $faq->id) }}" method="POST" autocomplete="off">
        @csrf
        @method('PUT')
        <input type="hidden" name="redirect_to" value="hub">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Main Q&A Content --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <div>
                            <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Question & Answer Definition</h2>
                            <p class="text-2xs text-text-muted">Modify the query intent and factual response</p>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3.5">
                        <div>
                            <label for="question" class="block text-xs font-medium text-text-secondary mb-1">Question / Inquiry Intent <span class="text-rose-500">*</span></label>
                            <input type="text" class="app-input w-full @error('question') border-rose-500 @enderror" name="question" id="question" value="{{ old('question', $faq->question) }}" required>
                            @error('question')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="answer" class="block text-xs font-medium text-text-secondary">Official Grounded Answer <span class="text-rose-500">*</span></label>
                                <div class="text-2xs text-text-muted font-medium" id="answerWordCountBadge">
                                    <span id="answerWordCount">0</span> / 250 words
                                </div>
                            </div>
                            <textarea class="app-textarea w-full text-xs rounded-md border border-border bg-surface text-text-primary p-2.5 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all @error('answer') border-rose-500 @enderror" name="answer" id="answer" rows="7" required>{{ old('answer', $faq->answer) }}</textarea>
                            <p class="text-2xs text-text-muted mt-1 flex items-center gap-1">
                                <i class="ri-information-line text-brand-primary"></i> Optimal length: 1–2 paragraphs (Max 250 words). For extensive policy manuals, split into topical articles.
                            </p>
                            @error('answer')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- AI Vector Retrieval & Indexing Status Panel --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <div class="flex items-center gap-2">
                            <i class="ri-sparkling-fill text-brand-primary"></i>
                            <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Vector Indexing Telemetry</h2>
                        </div>
                        <div>
                            @php
                                $status = $faq->lifecycle_status ?? ($faq->is_active ? \App\Enums\FaqLifecycleStatus::ACTIVE : \App\Enums\FaqLifecycleStatus::DRAFT);
                            @endphp

                            @if ($status === \App\Enums\FaqLifecycleStatus::ACTIVE)
                                <span class="app-badge bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                    <i class="ri-checkbox-circle-line"></i> Active & Searchable
                                </span>
                            @elseif ($status === \App\Enums\FaqLifecycleStatus::SYNCING || $status === \App\Enums\FaqLifecycleStatus::VALIDATING)
                                <span class="app-badge bg-brand-primary/10 text-brand-primary border border-brand-primary/20">
                                    <i class="ri-refresh-line animate-spin"></i> Syncing to Typesense...
                                </span>
                            @elseif ($status === \App\Enums\FaqLifecycleStatus::SYNC_FAILED || $status === \App\Enums\FaqLifecycleStatus::VALIDATION_FAILED)
                                <span class="app-badge bg-rose-500/10 text-rose-500 border border-rose-500/20">
                                    <i class="ri-close-circle-line"></i> Sync Failed
                                </span>
                            @else
                                <span class="app-badge bg-surface-elevated text-text-muted border border-border">Draft</span>
                            @endif
                        </div>
                    </div>
                    <div class="app-card-body space-y-3">
                        @if ($faq->hasFailed())
                            <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded-md text-xs text-rose-400 flex items-center justify-between">
                                <div>
                                    <strong class="font-semibold">Vector Sync Issue:</strong> {{ $faq->sync_error ?? 'Embedding generation or Typesense vector sync encountered an issue.' }}
                                </div>
                                <button type="button" class="app-btn bg-rose-500 text-white text-xs px-2.5 py-1" onclick="triggerResync('{{ $faq->id }}')">
                                    <i class="ri-refresh-line"></i> Retry Now
                                </button>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-2.5 rounded-md bg-surface-elevated border border-border">
                                <span class="text-2xs text-text-muted font-semibold uppercase tracking-wider block mb-1">Embedding Model</span>
                                <span class="text-xs text-brand-primary font-mono font-medium truncate block">
                                    mpnet-base-v2
                                </span>
                            </div>
                            <div class="p-2.5 rounded-md bg-surface-elevated border border-border">
                                <span class="text-2xs text-text-muted font-semibold uppercase tracking-wider block mb-1">Vector Dimension</span>
                                <span class="text-xs text-text-primary font-medium block">
                                    768-D Dense Vectors
                                </span>
                            </div>
                            <div class="p-2.5 rounded-md bg-surface-elevated border border-border">
                                <span class="text-2xs text-text-muted font-semibold uppercase tracking-wider block mb-1">Retrieval Engine</span>
                                <span class="text-xs text-emerald-500 font-medium block">
                                    Typesense Hybrid
                                </span>
                            </div>
                        </div>

                        <p class="text-2xs text-text-muted">
                            <strong class="text-text-secondary">Automatic Vector Resync:</strong> Updating the Q&A text automatically re-computes dense vector embeddings in the background with zero retrieval downtime.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Sidebar Classification & Actions --}}
            <div class="space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Classification & Settings</h2>
                    </div>
                    <div class="app-card-body space-y-3.5">
                        <div>
                            <label for="document_type" class="block text-xs font-medium text-text-secondary mb-1">Document Category <span class="text-rose-500">*</span></label>
                            <select name="document_type" id="document_type" class="app-select w-full @error('document_type') border-rose-500 @enderror" required>
                                <option value="faq" {{ old('document_type', $faq->document_type) == 'faq' ? 'selected' : '' }}>General FAQ</option>
                                <option value="refund_policy" {{ old('document_type', $faq->document_type) == 'refund_policy' ? 'selected' : '' }}>Refund Policy</option>
                                <option value="return_policy" {{ old('document_type', $faq->document_type) == 'return_policy' ? 'selected' : '' }}>Return Policy</option>
                                <option value="exchange_policy" {{ old('document_type', $faq->document_type) == 'exchange_policy' ? 'selected' : '' }}>Exchange Policy</option>
                                <option value="delivery_policy" {{ old('document_type', $faq->document_type) == 'delivery_policy' ? 'selected' : '' }}>Delivery Policy</option>
                                <option value="payment_policy" {{ old('document_type', $faq->document_type) == 'payment_policy' ? 'selected' : '' }}>Payment Policy</option>
                                <option value="cancellation_policy" {{ old('document_type', $faq->document_type) == 'cancellation_policy' ? 'selected' : '' }}>Cancellation Policy</option>
                                <option value="warranty_policy" {{ old('document_type', $faq->document_type) == 'warranty_policy' ? 'selected' : '' }}>Warranty Policy</option>
                                <option value="terms" {{ old('document_type', $faq->document_type) == 'terms' ? 'selected' : '' }}>Terms & Conditions</option>
                                <option value="privacy_policy" {{ old('document_type', $faq->document_type) == 'privacy_policy' ? 'selected' : '' }}>Privacy Policy</option>
                                <option value="about_us" {{ old('document_type', $faq->document_type) == 'about_us' ? 'selected' : '' }}>About Us</option>
                                <option value="contact" {{ old('document_type', $faq->document_type) == 'contact' ? 'selected' : '' }}>Contact Information</option>
                                <option value="customer_support" {{ old('document_type', $faq->document_type) == 'customer_support' ? 'selected' : '' }}>Customer Support Desk</option>
                                <option value="social_media_policy" {{ old('document_type', $faq->document_type) == 'social_media_policy' ? 'selected' : '' }}>Social Media Policy</option>
                            </select>
                            @error('document_type')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="priority" class="block text-xs font-medium text-text-secondary mb-1">Index Priority</label>
                            <input type="number" class="app-input w-full @error('priority') border-rose-500 @enderror" name="priority" id="priority" value="{{ old('priority', $faq->priority) }}" min="0">
                            @error('priority')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-border text-brand-primary focus:ring-brand-primary" {{ old('is_active', $faq->is_active) ? 'checked' : '' }}>
                            <label for="is_active" class="text-xs font-medium text-text-primary select-none cursor-pointer">
                                Active & Searchable Immediately
                            </label>
                        </div>

                        <div class="p-2.5 rounded-md bg-surface-elevated border border-border space-y-1 text-2xs text-text-muted">
                            <div class="flex justify-between"><span>Hit Count:</span> <strong class="text-text-primary">{{ number_format($faq->hit_count) }}</strong></div>
                            <div class="flex justify-between"><span>Last Query Match:</span> <strong class="text-text-primary">{{ $faq->last_used_at?->diffForHumans() ?? 'Never' }}</strong></div>
                            <div class="flex justify-between"><span>Created:</span> <strong class="text-text-primary">{{ $faq->created_at?->format('M d, Y') }}</strong></div>
                        </div>

                        <div>
                            <button type="button" class="app-btn app-btn-secondary w-full justify-center text-xs" onclick="triggerResync('{{ $faq->id }}')">
                                <i class="ri-refresh-line text-brand-primary"></i> Re-sync Vector Search
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Update FAQ</h2>
                    </div>
                    <div class="app-card-body space-y-2">
                        <button type="submit" class="app-btn app-btn-primary w-full justify-center">
                            <i class="ri-check-line"></i> Update Changes
                        </button>
                        <a href="{{ route('knowledge-base.index', ['tab' => 'faqs']) }}" class="app-btn app-btn-secondary w-full justify-center">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('custom-scripts')
<script>
    function triggerResync(faqId) {
        swal({
            title: "Re-sync to Vector Engine?",
            text: "This will regenerate embeddings and synchronize this FAQ with Typesense for AI retrieval.",
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
                    swal("Synced!", data.message || "FAQ synced successfully.", "success")
                        .then(() => location.reload());
                })
                .catch(err => {
                    swal("Error", "Failed to dispatch sync job.", "error");
                });
            }
        });
    }

    function updateWordCount() {
        var val = document.getElementById('answer').value || '';
        var words = val.trim().split(/\s+/).filter(Boolean);
        var count = words.length;
        document.getElementById('answerWordCount').textContent = count.toLocaleString();
        var badge = document.getElementById('answerWordCountBadge');
        if (count > 250) {
            badge.className = 'text-2xs text-rose-500 font-bold';
        } else if (count > 200) {
            badge.className = 'text-2xs text-amber-500 font-semibold';
        } else {
            badge.className = 'text-2xs text-text-muted font-medium';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var answerEl = document.getElementById('answer');
        if (answerEl) {
            answerEl.addEventListener('input', updateWordCount);
            updateWordCount();
        }
    });
</script>
@endpush
