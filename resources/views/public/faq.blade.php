@extends('layouts.public')

@section('title', 'Frequently Asked Questions — Entrepreneurs Automation')
@section('meta_description', 'Everything you need to know about Entrepreneurs Automation: Multi-source knowledge grounding, unified inbox, integrations, privacy, and early access.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Hero Header --}}
        <div class="text-center max-w-3xl mx-auto mb-16">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20 mb-4">
                <i class="ri-questionnaire-line"></i> Knowledge Hub & Support
            </div>
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text mb-4">
                Frequently Asked <span class="bg-gradient-to-r from-brand-500 to-sky-400 bg-clip-text text-transparent">Questions</span>
            </h1>
            <p class="text-base sm:text-lg text-light-muted dark:text-dark-muted leading-relaxed">
                Clear answers regarding architecture, spreadsheet grounding, data privacy, channel integrations, and enterprise deployment.
            </p>
        </div>

        {{-- Category Pills --}}
        <div class="flex flex-wrap items-center justify-center gap-2 mb-12" id="faqCategoryFilter">
            <button onclick="filterFaq('all', this)" class="faq-category-btn active px-4 py-2 rounded-lg text-xs font-medium bg-brand-600 text-white shadow-sm transition-all duration-150">
                All Topics
            </button>
            <button onclick="filterFaq('knowledge', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                Knowledge Engine
            </button>
            <button onclick="filterFaq('inbox', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                Unified Inbox & Handoff
            </button>
            <button onclick="filterFaq('integrations', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                Integrations & APIs
            </button>
            <button onclick="filterFaq('security', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                Security & Data Privacy
            </button>
            <button onclick="filterFaq('pricing', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                Access & Pricing
            </button>
        </div>

        {{-- FAQ Accordion List --}}
        <div class="space-y-4" id="faqContainer">
            {{-- Knowledge 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="knowledge">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-file-excel-2-line text-emerald-500"></i>
                        How does the platform prevent AI hallucinations when querying product catalogs?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Entrepreneurs Automation uses a strict hybrid retrieval pipeline combining full-text lexical indexing with dense semantic vector search. When a customer inquires about stock, price, or product specs, our engine grounds the LLM context exclusively in the parsed data rows from your uploaded Excel/CSV tables. If an item or condition does not exist within your verified knowledge base, the model is architected to state that the information is unavailable rather than fabricating answers.
                </div>
            </div>

            {{-- Knowledge 2 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="knowledge">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-folder-chart-line text-brand-500"></i>
                        Can I upload multiple Excel sheets with different schemas?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Yes. The platform automatically detects workbooks with multiple worksheets (e.g., "Main Inventory", "Warranty Policies", "Branch Locations") and analyzes header rows dynamically. You can review mapped column types directly from the Knowledge Base dashboard, preview parsed chunks, and selectively toggle or re-index individual files.
                </div>
            </div>

            {{-- Knowledge 3 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="knowledge">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-refresh-line text-sky-500"></i>
                        What happens when we update inventory or pricing?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Simply re-upload your revised spreadsheet in the Knowledge Documents console or sync via the REST API. The background indexing queue re-chunks only the modified rows, flushes outdated embeddings, and makes updated pricing immediately active across all live chat sessions.
                </div>
            </div>

            {{-- Inbox 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="inbox">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-user-shared-line text-amber-500"></i>
                        How does human agent takeover work during live conversations?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Inside the Unified Inbox, any authorized team member can click "Take Over" on an active conversation thread. This immediately mutes AI automated replies for that thread, notifies the customer if configured, and grants the human agent a live interactive console with full conversation history and grounded knowledge search assistance. Agents can release the session back to AI autopilot at any point.
                </div>
            </div>

            {{-- Inbox 2 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="inbox">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-chat-voice-line text-indigo-500"></i>
                        Does the system remember conversation context across multiple messages?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Yes. Each customer conversation maintains sliding-window dialogue memory that preserves context, clarifying queries, identified intent, customer name, and previously discussed SKU references, allowing for coherent multi-turn back-and-forth interactions without requiring repetitive input from the customer.
                </div>
            </div>

            {{-- Integrations 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="integrations">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-messenger-line text-blue-500"></i>
                        Which messaging channels are currently supported in production?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Facebook Messenger, the Embeddable Web Chat Widget, and our REST Webhook API are currently production-ready. WhatsApp Cloud API integration is actively in progress for early access testing, while Telegram, Shopify, and WooCommerce connectors are on the product roadmap.
                </div>
            </div>

            {{-- Integrations 2 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="integrations">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-code-box-line text-purple-500"></i>
                        How long does it take to embed the web widget on an existing site?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Less than 5 minutes. You copy a single asynchronous script tag from your admin widget settings and paste it before the closing &lt;/body&gt; tag of your website. You can customize primary colors, company avatar, greeting message, and widget positioning directly from your control panel without altering code.
                </div>
            </div>

            {{-- Security 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="security">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-shield-keyhole-line text-emerald-500"></i>
                        Is our private business and customer data used to train public AI models?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Never. All proprietary company documents, catalog records, and conversational logs remain strictly isolated within your organization's tenant boundary. Model inference is executed via zero-data-retention enterprise API endpoints with contractual guarantees ensuring your data is never utilized for LLM pre-training or fine-tuning.
                </div>
            </div>

            {{-- Security 2 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="security">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-user-settings-line text-rose-500"></i>
                        What access controls and team permissions are supported?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    Entrepreneurs Automation includes granular Role-Based Access Control (RBAC). You can assign roles such as Superadmin, Admin, Agent, and Viewer. Agents are restricted to managing assigned inbox threads, while Admin access is required to modify knowledge documents, API keys, and system automation rules.
                </div>
            </div>

            {{-- Pricing 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="pricing">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-calendar-check-line text-brand-500"></i>
                        How do we participate in the Early Access Program?
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    You can request access directly via our <a href="{{ route('pricing') }}" class="text-brand-500 underline hover:text-brand-400">Pricing page</a> or contact our technical onboarding team through the <a href="{{ route('contact') }}" class="text-brand-500 underline hover:text-brand-400">Contact form</a>. Qualified merchants and enterprise teams receive priority sandbox provisioning, personalized catalog schema onboarding, and dedicated Slack/Teams collaboration channels.
                </div>
            </div>
        </div>

        {{-- Still have questions CTA --}}
        <div class="mt-16 text-center p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/60 dark:bg-dark-surface/60 backdrop-blur-sm">
            <h3 class="text-xl font-bold text-light-text dark:text-dark-text mb-2">Have a question not listed here?</h3>
            <p class="text-sm text-light-muted dark:text-dark-muted mb-6 max-w-xl mx-auto">
                Our solutions engineering team is available to assist with custom catalog schemas, high-throughput webhook integrations, and dedicated on-premise deployments.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('contact') }}" class="px-5 py-2.5 rounded-lg text-xs font-semibold bg-brand-600 text-white hover:bg-brand-500 transition-colors">
                    Contact Solutions Team
                </a>
                <a href="{{ route('resources') }}" class="px-5 py-2.5 rounded-lg text-xs font-semibold border border-light-border dark:border-dark-border text-light-text dark:text-dark-text hover:bg-light-canvas dark:hover:bg-dark-canvas transition-colors">
                    View Documentation
                </a>
            </div>
        </div>
    </div>
</div>

@push('custom-scripts')
<script>
    function toggleFaq(btn) {
        var content = btn.nextElementSibling;
        var icon = btn.querySelector('.faq-icon');
        var isHidden = content.classList.contains('hidden');

        // Close other open faqs
        document.querySelectorAll('.faq-content').forEach(function(el) {
            el.classList.add('hidden');
        });
        document.querySelectorAll('.faq-icon').forEach(function(i) {
            i.style.transform = 'rotate(0deg)';
        });

        if (isHidden) {
            content.classList.remove('hidden');
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
    }

    function filterFaq(category, btn) {
        var buttons = document.querySelectorAll('.faq-category-btn');
        buttons.forEach(function(b) {
            b.classList.remove('active', 'bg-brand-600', 'text-white');
            b.classList.add('bg-light-surface', 'dark:bg-dark-surface', 'text-light-muted', 'dark:text-dark-muted');
        });
        btn.classList.add('active', 'bg-brand-600', 'text-white');
        btn.classList.remove('bg-light-surface', 'dark:bg-dark-surface', 'text-light-muted', 'dark:text-dark-muted');

        var items = document.querySelectorAll('.faq-item');
        items.forEach(function(item) {
            if (category === 'all' || item.getAttribute('data-category') === category) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>
@endpush
@endsection
