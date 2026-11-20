@extends('admin.app')
@section('title', 'Create FAQ')

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Create FAQ / Knowledge Entry</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <a href="{{ route('knowledge-base.index', ['tab' => 'faqs']) }}" class="hover:text-text-primary transition-colors">Knowledge Base</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Create FAQ</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('knowledge-base.index', ['tab' => 'faqs']) }}" class="app-btn app-btn-secondary">
                <i class="ri-arrow-left-line"></i> Back to Knowledge Base
            </a>
        </div>
    </div>

    <form action="{{ route('faqs.store') }}" method="POST" autocomplete="off">
        @csrf
        <input type="hidden" name="redirect_to" value="hub">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Main Q&A Content --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <div>
                            <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Question & Answer Definition</h2>
                            <p class="text-2xs text-text-muted">Direct questions and answers indexed into vector search embeddings</p>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3.5">
                        <div>
                            <label for="question" class="block text-xs font-medium text-text-secondary mb-1">Question / Inquiry Intent <span class="text-rose-500">*</span></label>
                            <input type="text" class="app-input w-full @error('question') border-rose-500 @enderror" name="question" id="question" value="{{ old('question') }}" placeholder="e.g. What is your return and refund window?" required>
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
                            <textarea class="app-textarea w-full text-xs rounded-md border border-border bg-surface text-text-primary p-2.5 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all @error('answer') border-rose-500 @enderror" name="answer" id="answer" rows="7" placeholder="Enter clear, concise response (1-2 paragraphs). Avoid ambiguous assertions..." required>{{ old('answer') }}</textarea>
                            <p class="text-2xs text-text-muted mt-1 flex items-center gap-1">
                                <i class="ri-information-line text-brand-primary"></i> Optimal length: 1–2 paragraphs (Max 250 words). For extensive policy manuals, split into topical articles.
                            </p>
                            @error('answer')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- AI Retrieval Optimization Tip --}}
                <div class="app-card border-brand-primary/20 bg-brand-primary/5">
                    <div class="app-card-body space-y-1.5">
                        <div class="flex items-center gap-1.5 text-xs font-semibold text-brand-primary">
                            <i class="ri-sparkling-fill"></i> Vector Semantic Search Optimization
                        </div>
                        <p class="text-2xs text-text-secondary leading-relaxed">
                            Content is automatically vectorized using <strong>paraphrase-multilingual-mpnet-base-v2</strong>. Write factual, affirmative answers. Negative assertions about unrelated products are discouraged to maintain clean retrieval semantics.
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
                        @if (!empty($isSuperAdmin) && isset($workspaces) && count($workspaces) > 0)
                            <div>
                                <label for="workspace_id" class="block text-xs font-medium text-text-secondary mb-1">Target Workspace <span class="text-rose-500">*</span></label>
                                <select name="workspace_id" id="workspace_id" class="app-select w-full @error('workspace_id') border-rose-500 @enderror" required>
                                    <option value="">Select Workspace</option>
                                    @foreach ($workspaces as $ws)
                                        <option value="{{ $ws->id }}" {{ old('workspace_id') == $ws->id ? 'selected' : '' }}>
                                            {{ $ws->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('workspace_id')
                                    <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div>
                            <label for="document_type" class="block text-xs font-medium text-text-secondary mb-1">Document Category <span class="text-rose-500">*</span></label>
                            <select name="document_type" id="document_type" class="app-select w-full @error('document_type') border-rose-500 @enderror" required>
                                <option value="faq" {{ old('document_type') == 'faq' ? 'selected' : '' }}>General FAQ</option>
                                <option value="refund_policy" {{ old('document_type') == 'refund_policy' ? 'selected' : '' }}>Refund Policy</option>
                                <option value="return_policy" {{ old('document_type') == 'return_policy' ? 'selected' : '' }}>Return Policy</option>
                                <option value="exchange_policy" {{ old('document_type') == 'exchange_policy' ? 'selected' : '' }}>Exchange Policy</option>
                                <option value="delivery_policy" {{ old('document_type') == 'delivery_policy' ? 'selected' : '' }}>Delivery Policy</option>
                                <option value="payment_policy" {{ old('document_type') == 'payment_policy' ? 'selected' : '' }}>Payment Policy</option>
                                <option value="cancellation_policy" {{ old('document_type') == 'cancellation_policy' ? 'selected' : '' }}>Cancellation Policy</option>
                                <option value="warranty_policy" {{ old('document_type') == 'warranty_policy' ? 'selected' : '' }}>Warranty Policy</option>
                                <option value="terms" {{ old('document_type') == 'terms' ? 'selected' : '' }}>Terms & Conditions</option>
                                <option value="privacy_policy" {{ old('document_type') == 'privacy_policy' ? 'selected' : '' }}>Privacy Policy</option>
                                <option value="about_us" {{ old('document_type') == 'about_us' ? 'selected' : '' }}>About Us</option>
                                <option value="contact" {{ old('document_type') == 'contact' ? 'selected' : '' }}>Contact Information</option>
                                <option value="customer_support" {{ old('document_type') == 'customer_support' ? 'selected' : '' }}>Customer Support Desk</option>
                                <option value="social_media_policy" {{ old('document_type') == 'social_media_policy' ? 'selected' : '' }}>Social Media Policy</option>
                            </select>
                            @error('document_type')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="priority" class="block text-xs font-medium text-text-secondary mb-1">Index Priority</label>
                            <input type="number" class="app-input w-full @error('priority') border-rose-500 @enderror" name="priority" id="priority" value="{{ old('priority', 0) }}" min="0">
                            @error('priority')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-border text-brand-primary focus:ring-brand-primary" checked>
                            <label for="is_active" class="text-xs font-medium text-text-primary select-none cursor-pointer">
                                Active & Searchable Immediately
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Publish Entry</h2>
                    </div>
                    <div class="app-card-body space-y-2">
                        <button type="submit" class="app-btn app-btn-primary w-full justify-center">
                            <i class="ri-check-line"></i> Save & Index FAQ
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
