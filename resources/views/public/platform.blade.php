@extends('layouts.public')

@section('title', 'Platform Overview — Entrepreneurs Automation')
@section('meta_description', 'Explore the unified architecture of Entrepreneurs Automation: AI Knowledge Engine, Unified Inbox, Business Intelligence, and Intelligent Workflow Automation.')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

        {{-- Header --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
                <i class="ri-cpu-line"></i> {{ __('public.platform.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.platform.title') !!}
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.platform.desc') }}
            </p>
        </div>

        {{-- System Flow Diagram (Visual) --}}
        <div
            class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6">
            <h2 class="text-xs font-mono uppercase tracking-wider font-bold text-light-muted dark:text-dark-muted">
                {{ __('public.platform.flow_title') }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs font-mono">
                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                    <div class="w-7 h-7 rounded bg-blue-500/15 text-blue-500 flex items-center justify-center font-bold">1
                    </div>
                    <div class="font-bold text-light-text dark:text-dark-text">{{ __('public.platform.step1_title') }}</div>
                    <div class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.platform.step1_desc') }}</div>
                </div>
                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                    <div class="w-7 h-7 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center font-bold">2
                    </div>
                    <div class="font-bold text-light-text dark:text-dark-text">{{ __('public.platform.step2_title') }}</div>
                    <div class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.platform.step2_desc') }}</div>
                </div>
                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                    <div
                        class="w-7 h-7 rounded bg-purple-500/15 text-purple-500 flex items-center justify-center font-bold">
                        3</div>
                    <div class="font-bold text-light-text dark:text-dark-text">{{ __('public.platform.step3_title') }}</div>
                    <div class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.platform.step3_desc') }}</div>
                </div>
                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                    <div
                        class="w-7 h-7 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center font-bold">
                        4</div>
                    <div class="font-bold text-light-text dark:text-dark-text">{{ __('public.platform.step4_title') }}</div>
                    <div class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.platform.step4_desc') }}</div>
                </div>
            </div>
        </div>

        {{-- The 4 Deep Dives --}}
        <div class="space-y-16">
            {{-- Section 1 --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                <div class="space-y-4">
                    <span
                        class="text-xs font-mono font-bold uppercase tracking-wider text-brand-500">{{ __('public.platform.s1_tag') }}</span>
                    <h3 class="text-2xl font-bold text-light-text dark:text-dark-text">{{ __('public.platform.s1_title') }}
                    </h3>
                    <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                        {{ __('public.platform.s1_desc') }}
                    </p>
                    <a href="{{ route('knowledge-engine') }}"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:text-brand-600">
                        <span>{{ __('public.platform.s1_btn') }}</span> <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
                <div
                    class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-2 text-2xs font-mono">
                    <div
                        class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
                        <span class="text-brand-500 font-bold">{{ __('public.platform.s1_card_title') }}</span>
                        <span class="text-3xs text-emerald-500">{{ __('public.platform.s1_card_active') }}</span>
                    </div>
                    <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas space-y-1">
                        <div class="text-light-text dark:text-dark-text font-semibold">
                            {{ __('public.platform.s1_card_src1') }}
                        </div>
                        <div class="text-light-muted dark:text-dark-muted text-3xs">
                            {{ __('public.platform.s1_card_src1_sub') }}
                        </div>
                    </div>
                    <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas space-y-1">
                        <div class="text-light-text dark:text-dark-text font-semibold">
                            {{ __('public.platform.s1_card_src2') }}
                        </div>
                        <div class="text-light-muted dark:text-dark-muted text-3xs">
                            {{ __('public.platform.s1_card_src2_sub') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section 2 --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                <div
                    class="order-2 lg:order-1 p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-2 text-2xs font-mono">
                    <div
                        class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
                        <span class="text-emerald-500 font-bold">{{ __('public.platform.s2_card_title') }}</span>
                        <span
                            class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.platform.s2_card_live') }}</span>
                    </div>
                    <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-light-text dark:text-dark-text">Ticket #4892 (Facebook Messenger)
                            </div>
                            <div class="text-light-muted dark:text-dark-muted text-3xs">Customer: Fahim Ahmed / 2 min ago
                            </div>
                        </div>
                        <span
                            class="px-2 py-0.5 rounded text-3xs bg-brand-500/15 text-brand-500 font-bold">{{ __('public.platform.s2_card_t1_tag') }}</span>
                    </div>
                    <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-light-text dark:text-dark-text">Ticket #4891 (Storefront Web
                                Chat)</div>
                            <div class="text-light-muted dark:text-dark-muted text-3xs">Customer: Tanvir Hossain / 5 min ago
                            </div>
                        </div>
                        <span
                            class="px-2 py-0.5 rounded text-3xs bg-amber-500/15 text-amber-500 font-bold">{{ __('public.platform.s2_card_t2_tag') }}</span>
                    </div>
                </div>
                <div class="order-1 lg:order-2 space-y-4">
                    <span
                        class="text-xs font-mono font-bold uppercase tracking-wider text-sky-500">{{ __('public.platform.s2_tag') }}</span>
                    <h3 class="text-2xl font-bold text-light-text dark:text-dark-text">{{ __('public.platform.s2_title') }}
                    </h3>
                    <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                        {{ __('public.platform.s2_desc') }}
                    </p>
                    <a href="{{ route('unified-inbox') }}"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-500 hover:text-sky-600">
                        <span>{{ __('public.platform.s2_btn') }}</span> <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            </div>

            {{-- Section 3 --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                <div class="space-y-4">
                    <span
                        class="text-xs font-mono font-bold uppercase tracking-wider text-purple-500">{{ __('public.platform.s3_tag') }}</span>
                    <h3 class="text-2xl font-bold text-light-text dark:text-dark-text">{{ __('public.platform.s3_title') }}
                    </h3>
                    <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                        {{ __('public.platform.s3_desc') }}
                    </p>
                    <a href="{{ route('business-intelligence') }}"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-purple-500 hover:text-purple-600">
                        <span>{{ __('public.platform.s3_btn') }}</span> <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
                <div
                    class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3 font-mono text-2xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas">
                            <div class="text-3xs text-light-muted dark:text-dark-muted">
                                {{ __('public.platform.s3_card_c1') }}
                            </div>
                            <div class="text-base font-bold text-light-text dark:text-dark-text mt-1">94.8%</div>
                        </div>
                        <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas">
                            <div class="text-3xs text-light-muted dark:text-dark-muted">
                                {{ __('public.platform.s3_card_c2') }}
                            </div>
                            <div class="text-base font-bold text-emerald-500 mt-1">210ms</div>
                        </div>
                    </div>
                    <div
                        class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas flex items-center justify-between text-3xs">
                        <span>{{ __('public.platform.s3_card_cluster') }}</span>
                        <span class="text-brand-500 font-bold">{{ __('public.platform.s3_card_cluster_sub') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Call to action --}}
        <div class="text-center pt-8">
            <a href="{{ config('auth.registration_enabled') ? route('register') : route('contact') }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                <span>{{ __('public.platform.cta_btn') }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>

    </div>
@endsection