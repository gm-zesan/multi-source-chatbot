@extends('layouts.public')

@section('title', 'About Entrepreneurs Automation — Company Vision & Platform Philosophy')
@section('meta_description', 'Building the Operating System for Intelligent Commerce. Learn about Entrepreneurs Automation. We build intelligent, grounded business systems that unify customer conversations, business knowledge, and enterprise automation.')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

        {{-- Hero --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
                <i class="ri-compass-3-line"></i> {{ __('public.about.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.about.title') !!}
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.about.desc') }}
            </p>
        </div>

        {{-- Core Values / Principles --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl font-bold">
                    <i class="ri-shield-check-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.about.v1_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.about.v1_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-xl font-bold">
                    <i class="ri-speed-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.about.v2_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.about.v2_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl font-bold">
                    <i class="ri-pie-chart-2-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.about.v3_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.about.v3_desc') }}
                </p>
            </div>
        </div>

        {{-- Story / Perspective Section --}}
        <div
            class="p-8 sm:p-12 rounded-3xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6 max-w-4xl mx-auto">
            <h2 class="text-xl font-bold text-light-text dark:text-dark-text">
                {{ __('public.about.story_title') }}
            </h2>
            <div class="space-y-4 text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                <p>
                    {{ __('public.about.story_p1') }}
                </p>
                <p>
                    {{ __('public.about.story_p2') }}
                </p>
                <p>
                    {{ __('public.about.story_p3') }}
                </p>
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center pt-8">
            <a href="{{ route('contact') }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                <span>{{ __('public.about.cta_btn') }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>

    </div>
@endsection