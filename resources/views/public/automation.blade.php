@extends('layouts.public')

@section('title', 'Intelligent Workflow Automation — Entrepreneurs Automation')
@section('meta_description', 'Smarter Operations. Automated Workflows. Automate repetitive inquiries, order lookups, and customer handoffs with sub-second execution speed and enterprise reliability.')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

        {{-- Hero --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/30">
                <i class="ri-cpu-line"></i> {{ __('public.automation.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.automation.title') !!}
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.automation.desc') }}
            </p>
        </div>

        {{-- 3 Workflow Use Cases --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-truck-line text-brand-500 text-2xl"></i>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.automation.u1_title') }}
                </h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.automation.u1_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-refresh-line text-sky-500 text-2xl"></i>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.automation.u2_title') }}
                </h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.automation.u2_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-user-star-line text-emerald-500 text-2xl"></i>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.automation.u3_title') }}
                </h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.automation.u3_desc') }}
                </p>
            </div>
        </div>

        {{-- Workflow Node Graphic Mockup --}}
        <div
            class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6">
            <h2 class="text-xs font-mono uppercase tracking-wider font-bold text-light-muted dark:text-dark-muted">
                {{ __('public.automation.pipe_title') }}
            </h2>

            <div
                class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-3 font-mono text-2xs">
                <div class="flex items-center gap-3">
                    <i class="ri-play-circle-line text-brand-500 text-sm"></i>
                    <span class="text-light-text dark:text-dark-text font-bold">{{ __('public.automation.trigger') }}</span>
                </div>
                <div class="pl-5 border-l-2 border-dashed border-light-border dark:border-dark-border space-y-2 py-1">
                    <div class="p-2 rounded bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted">
                        {{ __('public.automation.step1') }}
                    </div>
                    <div class="p-2 rounded bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted">
                        {{ __('public.automation.step2') }}
                    </div>
                    <div class="p-2 rounded bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted">
                        {{ __('public.automation.step3') }}
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <i class="ri-checkbox-circle-fill text-emerald-500 text-sm"></i>
                    <span class="text-emerald-500 font-bold">{{ __('public.automation.action') }}</span>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center pt-8">
            <a href="{{ route('register') }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                <span>{{ __('public.automation.cta_btn') }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>

    </div>
@endsection