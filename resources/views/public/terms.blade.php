@extends('layouts.public')

@section('title', 'Terms of Service — Entrepreneurs Automation')
@section('meta_description', 'Terms and conditions governing the enterprise deployment, API usage, and operational guidelines of Entrepreneurs Automation.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="border-b border-light-border dark:border-dark-border pb-10 mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20 mb-4">
                <i class="ri-file-text-line"></i> Legal Agreement
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-light-text dark:text-dark-text mb-3">
                Terms of Service
            </h1>
            <p class="text-sm text-light-muted dark:text-dark-muted">
                Effective Date: November 2026 / Master Subscription Agreement
            </p>
        </div>

        {{-- Content Sections --}}
        <div class="space-y-12 text-sm text-light-muted dark:text-dark-muted leading-relaxed">
            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">1. Acceptance & Engagement</h2>
                <p>
                    These Terms of Service ("Agreement") govern your organization's access to and use of the Entrepreneurs Automation platform, software suites, REST APIs, embeddable web chat widgets, and associated documentation provided by Entrepreneurs Automation ("Company", "we", "us").
                </p>
                <p>
                    By provisioning an account, integrating our web widget, or accessing the platform via API credentials, you represent and warrant that you possess the lawful authority to bind your business organization to these terms.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">2. Platform Access & Permitted Usage</h2>
                <p>
                    Subject to compliance with this Agreement and timely payment of applicable subscription or contract fees, Entrepreneurs Automation grants you a non-exclusive, non-transferable, revocable license to:
                </p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Upload, ingest, and index proprietary organizational documents and structured spreadsheets for retrieval grounding.</li>
                    <li>Deploy web chat widgets and omni-channel messaging connectors for customer engagement.</li>
                    <li>Utilize our Unified Inbox and administrative dashboard for team collaboration and human takeover operations.</li>
                    <li>Access platform REST APIs in accordance with documented rate limits and programmatic safety thresholds.</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">3. Grounding Integrity & AI Disclaimers</h2>
                <p>
                    Entrepreneurs Automation engineers deterministic grounding guardrails to eliminate inaccuracies. However, because generative language models synthesize natural language responses, the accuracy of customer-facing responses is directly contingent upon the completeness and currency of your ingested knowledge documents.
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface">
                    <p class="font-semibold text-light-text dark:text-dark-text mb-1">Customer Verification Responsibility</p>
                    <p class="text-xs">
                        Organizations remain responsible for maintaining accurate spreadsheet catalogs, reviewing critical pricing tables, and configuring human agent takeover escalation rules for high-value transactional conversations.
                    </p>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">4. Acceptable Conduct & Restrictions</h2>
                <p>You agree that you will not, directly or indirectly:</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Reverse engineer, decompile, or attempt to extract source code or underlying proprietary algorithms from the platform.</li>
                    <li>Transmit malicious payloads, exploit vulnerabilities, or circumvent API throttling and rate limits.</li>
                    <li>Utilize the messaging infrastructure to distribute unsolicited spam, fraudulent solicitations, or deceptive content in violation of Meta / WhatsApp / Telegram platform policies.</li>
                    <li>Infringe upon intellectual property rights, trademarks, or proprietary trade secrets of any third party.</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">5. Service Availability & High-Availability SLOs</h2>
                <p>
                    We target a monthly uptime service level objective of 99.9% for core routing, webhook ingestion, and knowledge retrieval pipelines, excluding scheduled maintenance windows announced with advance notice. In the event of critical infrastructure disruptions, status notifications are published via our administrative channels.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">6. Intellectual Property Ownership</h2>
                <p>
                    You retain all right, title, and ownership in all customer conversations, catalog spreadsheets, and proprietary assets submitted to the platform. Entrepreneurs Automation retains all rights, title, and interest in and to the platform codebase, vector pipeline architecture, interface designs, trademarks, and associated documentation.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">7. Termination</h2>
                <p>
                    Either party may terminate a subscription agreement upon written notice if the other party materially breaches these terms. Upon termination, access to the platform and API tokens will be revoked, and data will be permanently wiped according to our Privacy Policy retention schedule.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">8. Contact Legal</h2>
                <p>
                    If you have questions regarding this Agreement or enterprise licensing terms, please reach out:
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-1 font-mono text-xs">
                    <p class="font-sans font-bold text-sm text-light-text dark:text-dark-text">Entrepreneurs Automation Legal Department</p>
                    <p class="text-light-muted dark:text-dark-muted">Email: legal@entrepreneursautomation.com</p>
                </div>
            </section>
        </div>

        {{-- Footer Links --}}
        <div class="mt-16 pt-8 border-t border-light-border dark:border-dark-border flex items-center justify-between">
            <a href="{{ route('privacy') }}" class="text-xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text inline-flex items-center gap-1">
                <i class="ri-arrow-left-line"></i> Privacy Policy
            </a>
            <a href="{{ route('cookies') }}" class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                Cookie Policy &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
