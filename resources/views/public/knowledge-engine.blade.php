@extends('layouts.public')

@section('title', 'AI Knowledge Engine — Entrepreneurs Automation')
@section('meta_description', 'Enterprise Knowledge Grounding for Complex Commerce. Ground customer conversations in verified business truth. Ingest multi-sheet Excel catalogs, policies, and FAQs with high-precision semantic grounding.')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

        {{-- Hero --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
                <i class="ri-database-2-line"></i> {{ __('public.knowledge_engine.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.knowledge_engine.title') !!}
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.knowledge_engine.desc') }}
            </p>
        </div>

        {{-- How It Works --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg font-bold">
                    <i class="ri-file-excel-2-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                    {{ __('public.knowledge_engine.f1_title') }}
                </h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.knowledge_engine.f1_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-lg font-bold">
                    <i class="ri-file-text-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                    {{ __('public.knowledge_engine.f2_title') }}
                </h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.knowledge_engine.f2_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-lg font-bold">
                    <i class="ri-questionnaire-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                    {{ __('public.knowledge_engine.f3_title') }}
                </h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.knowledge_engine.f3_desc') }}
                </p>
            </div>
        </div>

        {{-- Interactive Grounding Demonstration UI --}}
        <div
            class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-light-border dark:border-dark-border">
                <div>
                    <h2 class="text-sm font-bold text-light-text dark:text-dark-text">
                        {{ __('public.knowledge_engine.sim_title') }}
                    </h2>
                    <p class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.knowledge_engine.sim_sub') }}
                    </p>
                </div>
                <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-brand-500/15 text-brand-500">
                    {{ __('public.knowledge_engine.sim_badge') }}
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs font-mono">
                {{-- Ingested Source --}}
                <div
                    class="space-y-3 p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                    <div
                        class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-bold flex items-center gap-2">
                        <i class="ri-file-excel-line text-emerald-500"></i> {{ __('public.knowledge_engine.sheet_title') }}
                    </div>
                    <div class="space-y-1.5 text-2xs">
                        <div class="flex justify-between border-b border-light-border dark:border-dark-border pb-1">
                            <span
                                class="text-light-muted dark:text-dark-muted">{{ __('public.knowledge_engine.sheet_sku_label') }}</span>
                            <span class="font-bold text-light-text dark:text-dark-text">APX-MACBOOK-M3-16GB</span>
                        </div>
                        <div class="flex justify-between border-b border-light-border dark:border-dark-border pb-1">
                            <span
                                class="text-light-muted dark:text-dark-muted">{{ __('public.knowledge_engine.sheet_product_label') }}</span>
                            <span
                                class="font-bold text-light-text dark:text-dark-text">{{ __('public.knowledge_engine.sheet_product_val') }}</span>
                        </div>
                        <div class="flex justify-between border-b border-light-border dark:border-dark-border pb-1">
                            <span
                                class="text-light-muted dark:text-dark-muted">{{ __('public.knowledge_engine.sheet_price_label') }}</span>
                            <span
                                class="font-bold text-emerald-500">{{ __('public.knowledge_engine.sheet_price_val') }}</span>
                        </div>
                        <div class="flex justify-between border-b border-light-border dark:border-dark-border pb-1">
                            <span
                                class="text-light-muted dark:text-dark-muted">{{ __('public.knowledge_engine.sheet_stock_label') }}</span>
                            <span
                                class="font-bold text-brand-500">{{ __('public.knowledge_engine.sheet_stock_val') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span
                                class="text-light-muted dark:text-dark-muted">{{ __('public.knowledge_engine.sheet_delivery_label') }}</span>
                            <span>{{ __('public.knowledge_engine.sheet_delivery_val') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Grounded Output --}}
                <div class="space-y-3 p-4 rounded-xl bg-brand-500/5 border border-brand-500/20">
                    <div class="text-3xs uppercase tracking-wider text-brand-500 font-bold flex items-center gap-2">
                        <i class="ri-sparkling-fill"></i> {{ __('public.knowledge_engine.grounded_out_title') }}
                    </div>
                    <div class="space-y-2 text-2xs leading-relaxed">
                        <div
                            class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border">
                            <span
                                class="text-light-muted dark:text-dark-muted block mb-1">{{ __('public.knowledge_engine.inquiry_label') }}</span>
                            <strong
                                class="text-light-text dark:text-dark-text">{{ __('public.knowledge_engine.inquiry_text') }}</strong>
                        </div>
                        <div class="p-2.5 rounded-lg bg-brand-500/10 border border-brand-500/20">
                            <span
                                class="text-brand-500 font-semibold block mb-1">{{ __('public.knowledge_engine.reply_label') }}</span>
                            <p class="text-light-text dark:text-dark-text">
                                {{ __('public.knowledge_engine.reply_text') }}
                            </p>
                        </div>
                        <div class="text-3xs text-emerald-500 font-semibold flex items-center gap-1.5">
                            <i class="ri-checkbox-circle-fill"></i> {{ __('public.knowledge_engine.verified_meta') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center pt-8">
            <a href="{{ config('auth.registration_enabled') ? route('register') : route('contact') }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                <span>{{ __('public.knowledge_engine.cta_btn') }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>

    </div>
@endsection