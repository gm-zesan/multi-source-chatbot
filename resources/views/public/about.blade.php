@extends('layouts.public')

@section('title', 'About Entrepreneurs Automation — Company Vision & Platform Philosophy')
@section('meta_description', 'Learn about Entrepreneurs Automation. We build intelligent, grounded business systems that unify customer conversations, business knowledge, and enterprise automation.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-compass-3-line"></i> Company Mission
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Building the Operating System for Intelligent Commerce.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            We believe enterprise AI shouldn't guess, invent facts, or create customer friction. It must be deeply connected to your business truth: spreadsheets, policies, and operational workflows.
        </p>
    </div>

    {{-- Core Values / Principles --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl font-bold">
                <i class="ri-shield-check-line"></i>
            </div>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Grounded Truth Over Guesswork</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                We reject generic AI bots that fabricate inventory, pricing, or return promises. If an answer cannot be verified from your uploaded business data, our quality gates enforce human escalation.
            </p>
        </div>

        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-xl font-bold">
                <i class="ri-speed-line"></i>
            </div>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Sub-Second Execution</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                In high-velocity commerce, response speed dictates conversion. Our pipeline is architected for sub-second round-trip retrieval and answer dispatch across web and messaging channels.
            </p>
        </div>

        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl font-bold">
                <i class="ri-pie-chart-2-line"></i>
            </div>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Owner-First Clarity</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Business leaders need clear data to make strategic investments. We turn every conversation into structured demand insights, token cost analytics, and resolution telemetry.
            </p>
        </div>
    </div>

    {{-- Story / Perspective Section --}}
    <div class="p-8 sm:p-12 rounded-3xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6 max-w-4xl mx-auto">
        <h2 class="text-xl font-bold text-light-text dark:text-dark-text">
            Why We Built Entrepreneurs Automation
        </h2>
        <div class="space-y-4 text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            <p>
                Retailers and commerce brands in Bangladesh and emerging markets spend dozens of hours every day answering the exact same customer questions on Facebook Messenger and web storefronts: <em>"Stock ache?", "Delivery charge koto?", "Warranty koto din?"</em>
            </p>
            <p>
                Generic chatbot builders required manual flowchart diagrams that broke whenever a product went out of stock. Modern LLMs, on the other hand, frequently invented prices or promised unapproved discounts.
            </p>
            <p>
                Entrepreneurs Automation bridges this divide: an enterprise multi-source retrieval engine that reads your live inventory spreadsheets and business documents directly, giving customers accurate answers in 200 milliseconds while giving store owners complete control.
            </p>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>Get in Touch With Our Team</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
