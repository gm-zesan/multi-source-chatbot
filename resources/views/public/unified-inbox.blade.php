@extends('layouts.public')

@section('title', 'Unified Customer Inbox — Entrepreneurs Automation')
@section('meta_description', 'One Unified Inbox for All Customer Dialogues. Centralize multi-channel dialogues from Facebook Messenger, Web Chat, and messaging portals. Seamlessly switch between AI resolution and human agent takeover.')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

        {{-- Hero --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-sky-500/10 text-sky-500 border border-sky-500/30">
                <i class="ri-chat-1-line"></i> {{ __('public.unified_inbox.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-extrabold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.unified_inbox.title') !!}
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.unified_inbox.desc') }}
            </p>
        </div>

        {{-- Feature Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-user-shared-line text-brand-500 text-2xl"></i>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                    {{ __('public.unified_inbox.f1_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.unified_inbox.f1_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-history-line text-sky-500 text-2xl"></i>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                    {{ __('public.unified_inbox.f2_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.unified_inbox.f2_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-file-download-line text-purple-500 text-2xl"></i>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">
                    {{ __('public.unified_inbox.f3_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.unified_inbox.f3_desc') }}
                </p>
            </div>
        </div>

        {{-- Inbox Interface Mockup --}}
        <div
            class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border">
                <div class="flex items-center gap-2">
                    <i class="ri-checkbox-circle-fill text-emerald-500 text-sm"></i>
                    <span
                        class="text-xs font-bold text-light-text dark:text-dark-text">{{ __('public.unified_inbox.hub_title') }}</span>
                </div>
                <span
                    class="text-3xs font-mono text-light-muted dark:text-dark-muted">{{ __('public.unified_inbox.hub_status') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 font-mono text-2xs">
                {{-- Thread list --}}
                <div
                    class="md:col-span-4 p-3 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                    <div
                        class="text-3xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-bold pb-1 border-b border-light-border dark:border-dark-border">
                        {{ __('public.unified_inbox.active_convs') }}
                    </div>
                    <div class="p-2 rounded bg-light-surface dark:bg-dark-surface border-l-2 border-brand-500 space-y-0.5">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-light-text dark:text-dark-text">Sabbir Ahmed</span>
                            <span
                                class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.unified_inbox.time_1m') }}</span>
                        </div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted truncate">
                            {{ __('public.unified_inbox.t1_msg') }}</div>
                    </div>
                    <div class="p-2 rounded bg-light-surface/50 dark:bg-dark-surface/50 space-y-0.5">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-light-text dark:text-dark-text">Nusrat Jahan</span>
                            <span
                                class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.unified_inbox.time_12m') }}</span>
                        </div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted truncate">
                            {{ __('public.unified_inbox.t2_msg') }}</div>
                    </div>
                </div>

                {{-- Thread detail --}}
                <div
                    class="md:col-span-8 p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-3">
                    <div
                        class="flex justify-between items-center pb-2 border-b border-light-border dark:border-dark-border">
                        <div class="flex items-center gap-2">
                            <span
                                class="w-6 h-6 rounded-lg bg-brand-500/20 text-brand-500 flex items-center justify-center text-3xs font-bold">SA</span>
                            <div>
                                <div class="text-xs font-bold text-light-text dark:text-dark-text">Sabbir Ahmed</div>
                                <div class="text-3xs text-light-muted dark:text-dark-muted">
                                    {{ __('public.unified_inbox.channel_label') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="px-2 py-0.5 rounded text-3xs bg-emerald-500/15 text-emerald-500 font-bold">{{ __('public.unified_inbox.ai_active') }}</span>
                            <button type="button"
                                class="px-2 py-0.5 rounded text-3xs bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border hover:text-brand-500 font-bold cursor-pointer">{{ __('public.unified_inbox.take_over') }}</button>
                        </div>
                    </div>

                    <div class="space-y-2 text-2xs">
                        <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface max-w-[80%]">
                            <div class="text-3xs text-light-muted dark:text-dark-muted mb-0.5">
                                {{ __('public.unified_inbox.c_timestamp') }}</div>
                            <p class="text-light-text dark:text-dark-text">{{ __('public.unified_inbox.c_msg') }}</p>
                        </div>
                        <div
                            class="p-2.5 rounded-lg bg-brand-500/15 text-light-text dark:text-dark-text ml-auto max-w-[80%] border border-brand-500/30">
                            <div class="text-3xs text-brand-500 font-bold mb-0.5">
                                {{ __('public.unified_inbox.ai_timestamp') }}</div>
                            <p>{{ __('public.unified_inbox.ai_msg') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center pt-8">
            <a href="{{ route('register') }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                <span>{{ __('public.unified_inbox.cta_btn') }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>

    </div>
@endsection