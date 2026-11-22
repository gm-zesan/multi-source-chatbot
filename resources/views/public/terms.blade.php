@extends('layouts.public')

@section('title', 'Terms of Service — Entrepreneurs Automation')
@section('meta_description', 'Master Subscription Agreement. Terms and conditions governing the enterprise deployment, API usage, and operational guidelines of Entrepreneurs Automation.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="border-b border-light-border dark:border-dark-border pb-10 mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20 mb-4">
                <i class="ri-file-text-line"></i> {{ __('public.terms.badge') }}
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text mb-3">
                {!! __('public.terms.title') !!}
            </h1>
            <p class="text-sm text-light-muted dark:text-dark-muted">
                {{ __('public.terms.effective') }}
            </p>
        </div>

        {{-- Content Sections --}}
        <div class="space-y-12 text-sm text-light-muted dark:text-dark-muted leading-relaxed">
            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s1_title') }}</h2>
                <p>
                    {{ __('public.terms.s1_p1') }}
                </p>
                <p>
                    {{ __('public.terms.s1_p2') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s2_title') }}</h2>
                <p>{{ __('public.terms.s2_intro') }}</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>{{ __('public.terms.s2_item1') }}</li>
                    <li>{{ __('public.terms.s2_item2') }}</li>
                    <li>{{ __('public.terms.s2_item3') }}</li>
                    <li>{{ __('public.terms.s2_item4') }}</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s3_title') }}</h2>
                <p>
                    {{ __('public.terms.s3_p1') }}
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface">
                    <p class="font-semibold text-light-text dark:text-dark-text mb-1">{{ __('public.terms.s3_box_title') }}</p>
                    <p class="text-xs">
                        {{ __('public.terms.s3_box_desc') }}
                    </p>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s4_title') }}</h2>
                <p>{{ __('public.terms.s4_intro') }}</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>{{ __('public.terms.s4_item1') }}</li>
                    <li>{{ __('public.terms.s4_item2') }}</li>
                    <li>{{ __('public.terms.s4_item3') }}</li>
                    <li>{{ __('public.terms.s4_item4') }}</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s5_title') }}</h2>
                <p>
                    {{ __('public.terms.s5_p1') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s6_title') }}</h2>
                <p>
                    {{ __('public.terms.s6_p1') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s7_title') }}</h2>
                <p>
                    {{ __('public.terms.s7_p1') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.terms.s8_title') }}</h2>
                <p>
                    {{ __('public.terms.s8_p1') }}
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-1 font-mono text-xs">
                    <p class="font-sans font-bold text-sm text-light-text dark:text-dark-text">{{ __('public.terms.legal_dept') }}</p>
                    <p class="text-light-muted dark:text-dark-muted">{{ __('public.terms.legal_email') }}</p>
                </div>
            </section>
        </div>

        {{-- Footer Links --}}
        <div class="mt-16 pt-8 border-t border-light-border dark:border-dark-border flex items-center justify-between">
            <a href="{{ route('privacy') }}" class="text-xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text inline-flex items-center gap-1">
                <i class="ri-arrow-left-line"></i> {{ __('public.terms.privacy_link') }}
            </a>
            <a href="{{ route('cookies') }}" class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                {{ __('public.terms.cookies_link') }} &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
