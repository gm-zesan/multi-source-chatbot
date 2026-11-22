@extends('layouts.public')

@section('title', 'Privacy Policy — Entrepreneurs Automation')
@section('meta_description', 'Enterprise Data Governance. Learn how Entrepreneurs Automation safeguards enterprise data, ensures multi-tenant isolation, and protects customer conversations with workspace-level security.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="border-b border-light-border dark:border-dark-border pb-10 mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 mb-4">
                <i class="ri-shield-check-line"></i> {{ __('public.privacy.badge') }}
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text mb-3">
                {!! __('public.privacy.title') !!}
            </h1>
            <p class="text-sm text-light-muted dark:text-dark-muted">
                {{ __('public.privacy.effective') }}
            </p>
        </div>

        {{-- Content Sections --}}
        <div class="space-y-12 text-sm text-light-muted dark:text-dark-muted leading-relaxed">
            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.privacy.s1_title') }}</h2>
                <p>
                    {{ __('public.privacy.s1_p1') }}
                </p>
                <p>
                    {{ __('public.privacy.s1_p2') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.privacy.s2_title') }}</h2>
                <p>{{ __('public.privacy.s2_intro') }}</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>{{ __('public.privacy.s2_item1') }}</li>
                    <li>{{ __('public.privacy.s2_item2') }}</li>
                    <li>{{ __('public.privacy.s2_item3') }}</li>
                    <li>{{ __('public.privacy.s2_item4') }}</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.privacy.s3_title') }}</h2>
                <p>
                    {{ __('public.privacy.s3_p1') }}
                </p>
                <div class="p-5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300">
                    <p class="font-semibold mb-1 flex items-center gap-2">
                        <i class="ri-shield-keyhole-line"></i> {{ __('public.privacy.s3_box_title') }}
                    </p>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400">
                        {{ __('public.privacy.s3_box_desc') }}
                    </p>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.privacy.s4_title') }}</h2>
                <p>{{ __('public.privacy.s4_intro') }}</p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>{{ __('public.privacy.s4_item1') }}</li>
                    <li>{{ __('public.privacy.s4_item2') }}</li>
                    <li>{{ __('public.privacy.s4_item3') }}</li>
                    <li>{{ __('public.privacy.s4_item4') }}</li>
                </ul>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.privacy.s5_title') }}</h2>
                <p>
                    {{ __('public.privacy.s5_p1') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.privacy.s6_title') }}</h2>
                <p>
                    {{ __('public.privacy.s6_p1') }}
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-1 font-mono text-xs">
                    <p class="font-sans font-bold text-sm text-light-text dark:text-dark-text">{{ __('public.privacy.legal_dept') }}</p>
                    <p class="text-light-muted dark:text-dark-muted">{{ __('public.privacy.legal_email') }}</p>
                    <p class="text-light-muted dark:text-dark-muted">{{ __('public.privacy.legal_subject') }}</p>
                </div>
            </section>
        </div>

        {{-- Return to Home CTA --}}
        <div class="mt-16 pt-8 border-t border-light-border dark:border-dark-border flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline inline-flex items-center gap-1">
                <i class="ri-arrow-left-line"></i> {{ __('public.privacy.back_home') }}
            </a>
            <a href="{{ route('terms') }}" class="text-xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text">
                {{ __('public.privacy.terms_link') }} &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
