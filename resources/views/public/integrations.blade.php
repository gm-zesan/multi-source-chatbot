@extends('layouts.public')

@section('title', 'Integrations & Ecosystem — Entrepreneurs Automation')
@section('meta_description', 'Integrated With Your Commerce Stack. Connect your existing channels, storefronts, and messaging portals. See currently available and in-development platform integrations.')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

        {{-- Hero --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
                <i class="ri-links-line"></i> {{ __('public.integrations.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.integrations.title') !!}
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.integrations.desc') }}
            </p>
        </div>

        {{-- Verified Available Integrations --}}
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
                <h2
                    class="text-sm font-bold uppercase tracking-wider text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-checkbox-circle-fill text-emerald-500 text-sm"></i>
                    <span>{{ __('public.integrations.available_heading') }}</span>
                </h2>
                <span
                    class="text-3xs font-mono text-emerald-500 font-semibold">{{ __('public.integrations.prod_verified') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Facebook Messenger --}}
                <div
                    class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                    <div class="flex justify-between items-start">
                        <div
                            class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl">
                            <i class="ri-facebook-circle-fill"></i>
                        </div>
                        <span
                            class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">{{ __('public.integrations.live') }}</span>
                    </div>
                    <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                        {{ __('public.integrations.fb_title') }}
                    </h3>
                    <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.integrations.fb_desc') }}
                    </p>
                </div>

                {{-- Web Chat Widget --}}
                <div
                    class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                    <div class="flex justify-between items-start">
                        <div
                            class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl">
                            <i class="ri-global-line"></i>
                        </div>
                        <span
                            class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">{{ __('public.integrations.live') }}</span>
                    </div>
                    <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                        {{ __('public.integrations.widget_title') }}
                    </h3>
                    <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.integrations.widget_desc') }}
                    </p>
                </div>

                {{-- REST API & Webhooks --}}
                <div
                    class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                    <div class="flex justify-between items-start">
                        <div
                            class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl">
                            <i class="ri-code-s-slash-line"></i>
                        </div>
                        <span
                            class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">{{ __('public.integrations.live') }}</span>
                    </div>
                    <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                        {{ __('public.integrations.api_title') }}
                    </h3>
                    <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.integrations.api_desc') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- In Progress & Roadmap --}}
        <div class="space-y-6">
            <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
                <h2
                    class="text-sm font-bold uppercase tracking-wider text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-time-line text-amber-500 text-sm"></i>
                    <span>{{ __('public.integrations.roadmap_heading') }}</span>
                </h2>
                <span
                    class="text-3xs font-mono text-amber-500 font-semibold">{{ __('public.integrations.in_progress') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div
                    class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/70 dark:bg-dark-surface/70 space-y-2">
                    <div class="flex justify-between items-start">
                        <i class="ri-whatsapp-fill text-emerald-500 text-xl"></i>
                        <span
                            class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-amber-500/15 text-amber-500">{{ __('public.integrations.in_progress') }}</span>
                    </div>
                    <h4 class="text-xs font-bold text-light-text dark:text-dark-text">
                        {{ __('public.integrations.wa_title') }}
                    </h4>
                    <p class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.integrations.wa_desc') }}</p>
                </div>

                <div
                    class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 space-y-2 opacity-80">
                    <div class="flex justify-between items-start">
                        <i class="ri-instagram-fill text-pink-500 text-xl"></i>
                        <span
                            class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">{{ __('public.integrations.planned') }}</span>
                    </div>
                    <h4 class="text-xs font-bold text-light-text dark:text-dark-text">
                        {{ __('public.integrations.ig_title') }}
                    </h4>
                    <p class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.integrations.ig_desc') }}</p>
                </div>

                <div
                    class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 space-y-2 opacity-80">
                    <div class="flex justify-between items-start">
                        <i class="ri-shopping-bag-3-line text-orange-500 text-xl"></i>
                        <span
                            class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">{{ __('public.integrations.planned') }}</span>
                    </div>
                    <h4 class="text-xs font-bold text-light-text dark:text-dark-text">
                        {{ __('public.integrations.shopify_title') }}
                    </h4>
                    <p class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.integrations.shopify_desc') }}
                    </p>
                </div>

                <div
                    class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 space-y-2 opacity-80">
                    <div class="flex justify-between items-start">
                        <i class="ri-wordpress-fill text-sky-500 text-xl"></i>
                        <span
                            class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">{{ __('public.integrations.planned') }}</span>
                    </div>
                    <h4 class="text-xs font-bold text-light-text dark:text-dark-text">
                        {{ __('public.integrations.woo_title') }}
                    </h4>
                    <p class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.integrations.woo_desc') }}</p>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center pt-8">
            <a href="{{ config('auth.registration_enabled') ? route('register') : route('contact') }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                <span>{{ __('public.integrations.cta_btn') }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>

    </div>
@endsection