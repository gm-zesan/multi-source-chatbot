@extends('layouts.public')

@section('title', 'Privacy Policy — Entrepreneurs Automation')
@section('meta_description', 'Learn how Entrepreneurs Automation safeguards enterprise data, ensures multi-tenant isolation, and protects customer conversations with workspace-level security.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="border-b border-light-border dark:border-dark-border pb-10 mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 mb-4">
                <i class="ri-shield-check-line"></i> Enterprise Data Governance
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-light-text dark:text-dark-text mb-3">
                Privacy Policy
            </h1>
            <p class="text-sm text-light-muted dark:text-dark-muted">
                Effective Date: November 2026 / Last Revised: Version 2.1
            </p>
        </div>

        {{-- Content Sections --}}
        <div class="space-y-12 text-sm text-light-muted dark:text-dark-muted leading-relaxed">
            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">1. Overview and Core Philosophy</h2>
                <p>
                    Entrepreneurs Automation ("we", "us", or "our") builds enterprise-grade business automation, omni-channel customer communication, and knowledge grounding infrastructure. We believe that proprietary business data is your most critical corporate asset.
                </p>
                <p>
                    Our core privacy commitment is straightforward: <strong class="text-light-text dark:text-dark-text">Your proprietary documents, conversation transcripts, customer identities, and business records remain strictly yours. We never sell your data, and we never use your proprietary data to train or fine-tune public foundation models.</strong>
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">2. Information We Process</h2>
                <p>Depending on your deployment tier and configured integrations, Entrepreneurs Automation processes the following categories of information:</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li><strong class="text-light-text dark:text-dark-text">Account & Organization Data:</strong> Administrative contact details, organizational identifiers, team member email addresses, and cryptographic credentials used for authentication.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Knowledge Base Documents:</strong> Uploaded spreadsheets (.xlsx, .csv), PDF manuals, FAQ archives, and structured product inventory data used specifically for contextual retrieval and grounding.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Omni-Channel Communication Logs:</strong> Customer message content, channel platform IDs (e.g., Facebook Page Scoped User IDs, web session tokens), timestamps, and human agent takeover transcripts.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Telemetry & Performance Logs:</strong> Query latency, vector similarity scores, model token consumption tallies, error stack traces, and system health status logs.</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">3. Multi-Tenant Isolation & Workspace Governance</h2>
                <p>
                    Every organization on Entrepreneurs Automation operates within an isolated tenant partition. Vector embeddings, document chunks, and conversation histories are indexed with immutable organizational identifiers, preventing cross-tenant information leakage at both the database and cache layers.
                </p>
                <div class="p-5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
                    <p class="font-semibold mb-1 flex items-center gap-2">
                        <i class="ri-shield-keyhole-line"></i> Tenant Boundary & Inference Privacy
                    </p>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        Model inference and semantic grounding are executed solely to fulfill customer service dialogues. Your prompts, retrieved document chunks, and completions are scoped strictly to your workspace and never shared across tenant accounts or used to train models across organizations.
                    </p>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">4. Information Security & Access Controls</h2>
                <p>We deploy defense-in-depth security controls across the platform lifecycle:</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li><strong class="text-light-text dark:text-dark-text">Encryption in Transit:</strong> All HTTP communications and widget endpoints are enforced with modern HTTPS encryption.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Encryption at Rest:</strong> Sensitive channel credentials, webhook secrets, and access tokens are encrypted using application-level cryptographic keys.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Role-Based Access Control (RBAC):</strong> Granular capability gates regulate which team members can view customer messages, modify indexing sources, or manage webhook endpoints.</li>
                    <li><strong class="text-light-text dark:text-dark-text">Audit Logging:</strong> Administrative actions, document uploads, and human agent interventions are recorded in tamper-resistant observability logs.</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">5. Data Retention & Deletion</h2>
                <p>
                    Organizations retain full governance over their data lifecycle. Administrators can permanently delete uploaded spreadsheets, vector indices, or historical customer conversation threads through the Admin Console at any time. When an organization terminates its subscription, all associated databases and document vectors are completely purged from active storage within 30 calendar days.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">6. Inquiries and Compliance Contact</h2>
                <p>
                    For inquiries regarding security whitepapers, custom Data Processing Agreements (DPAs), or regulatory compliance audits, contact our designated privacy officer:
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-1 font-mono text-xs">
                    <p class="font-sans font-bold text-sm text-light-text dark:text-dark-text">Entrepreneurs Automation Security & Legal</p>
                    <p class="text-light-muted dark:text-dark-muted">Email: legal@entrepreneursautomation.com</p>
                    <p class="text-light-muted dark:text-dark-muted">Subject: Privacy Governance & Data Processing Inquiry</p>
                </div>
            </section>
        </div>

        {{-- Return to Home CTA --}}
        <div class="mt-16 pt-8 border-t border-light-border dark:border-dark-border flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline inline-flex items-center gap-1">
                <i class="ri-arrow-left-line"></i> Back to Homepage
            </a>
            <a href="{{ route('terms') }}" class="text-xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text">
                Terms of Service &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
