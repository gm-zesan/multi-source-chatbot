@extends('admin.app')
@section('title')
    Edit FAQ
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('faqs.update', $faq->id) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Edit FAQ</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('faqs.index') }}">FAQs</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Edit FAQ</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('faqs.index') }}" class="add-new">FAQ List<i class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3">
                                <label for="question" class="form-label custom-label">Question <span class="text-danger">*</span></label>
                                <input type="text" class="form-control custom-input @error('question') is-invalid @enderror" name="question" id="question" value="{{ old('question', $faq->question) }}" placeholder="Enter the FAQ question">
                                @error('question')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="answer" class="form-label custom-label">Answer <span class="text-danger">*</span></label>
                                <textarea class="form-control custom-input @error('answer') is-invalid @enderror" name="answer" id="answer" rows="8" placeholder="Enter the FAQ answer">{{ old('answer', $faq->answer) }}</textarea>
                                @error('answer')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- AI Retrieval & Dynamic Commerce Lexicon Panel --}}
                    <div class="card table-card mt-3">
                        <div class="card-header table-header d-flex align-items-center justify-content-between">
                            <div class="table-title d-flex align-items-center">
                                <i class="ri-sparkling-fill text-primary me-2 fs-5"></i>
                                AI Retrieval & Commerce Ontology
                            </div>
                            @if ($faq->lexicon)
                                <span class="badge" style="background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 11px;">
                                    <i class="ri-checkbox-circle-line me-1"></i>Validated & Synced
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 11px;">Pending Ingestion</span>
                            @endif
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <div class="col-md-6 col-12">
                                    <label class="form-label text-muted small mb-1 fw-bold text-uppercase" style="font-size: 11px;">Commerce Domain Category</label>
                                    <div class="p-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <span class="badge" style="background-color: #3b82f6; color: #ffffff; font-size: 12px;">
                                            <i class="ri-store-2-line me-1"></i>{{ $faq->lexicon?->domain ?? 'General Support' }}
                                        </span>
                                        <span class="text-muted small ms-2">Intent: <code>{{ $faq->lexicon?->intent ?? 'support_faq_inquiry' }}</code></span>
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label text-muted small mb-1 fw-bold text-uppercase" style="font-size: 11px;">Canonical Search Concepts</label>
                                    <div class="p-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; min-height: 42px;">
                                        @forelse ($faq->lexicon?->canonical_terms ?? [] as $term)
                                            <span class="badge" style="background-color: #e2e8f0; color: #334155; margin-right: 4px; margin-bottom: 4px; font-size: 11px;">
                                                {{ $term }}
                                            </span>
                                        @empty
                                            <span class="text-muted small">None generated yet</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label text-muted small mb-1 fw-bold text-uppercase" style="font-size: 11px;">Bengali (বাংলা) & Banglish Variations</label>
                                    <div class="p-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; min-height: 42px;">
                                        @forelse ($faq->lexicon?->bangla_terms ?? [] as $term)
                                            <span class="badge" style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; margin-right: 4px; margin-bottom: 4px; font-size: 11px;">
                                                {{ $term }}
                                            </span>
                                        @empty
                                            <span class="text-muted small">None generated yet</span>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label text-muted small mb-1 fw-bold text-uppercase" style="font-size: 11px;">F-Commerce & Social Aliases</label>
                                    <div class="p-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; min-height: 42px;">
                                        @forelse ($faq->lexicon?->commerce_terms ?? [] as $term)
                                            <span class="badge" style="background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; margin-right: 4px; margin-bottom: 4px; font-size: 11px;">
                                                {{ $term }}
                                            </span>
                                        @empty
                                            <span class="text-muted small">None generated yet</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 p-2 d-flex align-items-center" style="background-color: #f0f9ff; border: 1px dashed #bae6fd; border-radius: 6px;">
                                <i class="ri-information-line text-info me-2 fs-5"></i>
                                <span class="small text-muted">
                                    <strong>Automatic Lifecycle:</strong> Updating this FAQ will asynchronously regenerate these terms and re-index them in Typesense with zero runtime latency.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="table-title">Category & Settings</div>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3">
                                <label for="document_type" class="form-label custom-label">Policy / Document Type <span class="text-danger">*</span></label>
                                <select name="document_type" id="document_type" class="form-control custom-input single-select2 @error('document_type') is-invalid @enderror">
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
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="category_id" class="form-label custom-label">Category</label>
                                <select name="category_id" id="category_id" class="form-control custom-input single-select2">
                                    <option value="">No Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $faq->category_id) == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="priority" class="form-label custom-label">Priority</label>
                                <input type="number" class="form-control custom-input @error('priority') is-invalid @enderror" name="priority" id="priority" value="{{ old('priority', $faq->priority) }}" min="0">
                                @error('priority')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label custom-label d-block">Status</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $faq->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>

                            <div class="mb-3 p-3" style="background-color: #f8fafc; border-radius: 6px;">
                                <small class="text-muted d-block">Hit Count: <strong>{{ number_format($faq->hit_count) }}</strong></small>
                                <small class="text-muted d-block">Last Used: <strong>{{ $faq->last_used_at?->diffForHumans() ?? 'Never' }}</strong></small>
                                <small class="text-muted d-block">Created: <strong>{{ $faq->created_at?->format('M d, Y') }}</strong></small>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-sm w-100 py-2 text-primary" style="background-color: #eff6ff; border: 1px solid #bfdbfe; font-weight: 500;" onclick="triggerResync('{{ $faq->id }}')">
                                    <i class="ri-refresh-line me-1"></i> Re-sync to Typesense & Regenerate Lexicon
                                </button>
                            </div>

                            <div class="form-actions">
                                <div class="row">
                                    <div class="col-6">
                                        <button type="submit" class="btn submit-button">Update
                                            <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <a href="{{ route('faqs.index') }}" class="btn cancel-button">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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

    <script>
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
                        swal("Synced!", data.message || "FAQ synced successfully.", "success")
                            .then(() => location.reload());
                    })
                    .catch(err => {
                        swal("Error", "Failed to dispatch sync job.", "error");
                    });
                }
            });
        }
    </script>
@endpush
