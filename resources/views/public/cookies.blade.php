@extends('layouts.public')

@section('title', 'Cookie Policy — Entrepreneurs Automation')
@section('meta_description', 'Browser Storage Disclosure. Understand how Entrepreneurs Automation uses cookies and local browser storage to provide secure authentication, theme preferences, and widget sessions.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="border-b border-light-border dark:border-dark-border pb-10 mb-12">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 mb-4">
                <i class="ri-cookie-line"></i> {{ __('public.cookies.badge') }}
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text mb-3">
                {!! __('public.cookies.title') !!}
            </h1>
            <p class="text-sm text-light-muted dark:text-dark-muted">
                {{ __('public.cookies.effective') }}
            </p>
        </div>

        {{-- Content Sections --}}
        <div class="space-y-12 text-sm text-light-muted dark:text-dark-muted leading-relaxed">
            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.cookies.s1_title') }}</h2>
                <p>
                    {{ __('public.cookies.s1_p1') }}
                </p>
                <p>
                    {{ __('public.cookies.s1_p2') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.cookies.s2_title') }}</h2>

                <div class="space-y-4">
                    {{-- Essential --}}
                    <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface">
                        <div class="flex items-center gap-2 mb-2 font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-shield-keyhole-line text-emerald-500 text-sm"></i>
                            <span>{{ __('public.cookies.s2_box1_title') }}</span>
                        </div>
                        <p class="text-xs mb-3">
                            {{ __('public.cookies.s2_box1_desc') }}
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs font-mono">
                                <thead>
                                    <tr class="border-b border-light-border dark:border-dark-border text-light-muted dark:text-dark-muted">
                                        <th class="py-2">Identifier</th>
                                        <th class="py-2">Type</th>
                                        <th class="py-2">Purpose</th>
                                        <th class="py-2">Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-light-border/40 dark:divide-dark-border/40 text-light-text dark:text-dark-text">
                                    <tr>
                                        <td class="py-2 text-brand-600 dark:text-brand-400 font-bold">XSRF-TOKEN</td>
                                        <td class="py-2">Cookie</td>
                                        <td class="py-2 font-sans">CSRF Security Verification</td>
                                        <td class="py-2">Session</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-brand-600 dark:text-brand-400 font-bold">ea_session</td>
                                        <td class="py-2">Cookie</td>
                                        <td class="py-2 font-sans">Encrypted Session Identifier</td>
                                        <td class="py-2">120 Minutes</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-brand-600 dark:text-brand-400 font-bold">agy_admin_theme</td>
                                        <td class="py-2">LocalStorage</td>
                                        <td class="py-2 font-sans">Theme Selection (Dark / Light)</td>
                                        <td class="py-2">Persistent</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Web Chat Widget --}}
                    <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface">
                        <div class="flex items-center gap-2 mb-2 font-semibold text-light-text dark:text-dark-text">
                            <i class="ri-chat-3-line text-sky-500 text-sm"></i>
                            <span>{{ __('public.cookies.s2_box2_title') }}</span>
                        </div>
                        <p class="text-xs mb-2">
                            {{ __('public.cookies.s2_box2_desc') }}
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs font-mono">
                                <tbody class="divide-y divide-light-border/40 dark:divide-dark-border/40 text-light-text dark:text-dark-text">
                                    <tr>
                                        <td class="py-2 text-sky-600 dark:text-sky-400 font-bold w-1/4">ea_chat_session</td>
                                        <td class="py-2 font-sans">Retains active chat conversation UUID</td>
                                        <td class="py-2 w-1/4">24 Hours</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.cookies.s3_title') }}</h2>
                <p>
                    {{ __('public.cookies.s3_p1') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.cookies.s4_title') }}</h2>
                <p>
                    {{ __('public.cookies.s4_p1') }}
                </p>
                <ul class="list-disc pl-6 space-y-1 text-xs">
                    <li><strong class="text-light-text dark:text-dark-text">{{ __('public.cookies.s4_chrome') }}</strong></li>
                    <li><strong class="text-light-text dark:text-dark-text">{{ __('public.cookies.s4_firefox') }}</strong></li>
                    <li><strong class="text-light-text dark:text-dark-text">{{ __('public.cookies.s4_safari') }}</strong></li>
                </ul>
                <p class="text-xs text-light-muted dark:text-dark-muted">
                    {{ __('public.cookies.s4_note') }}
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-light-text dark:text-dark-text">{{ __('public.cookies.s5_title') }}</h2>
                <p>
                    {{ __('public.cookies.s5_p1') }}
                </p>
                <div class="p-5 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-1 font-mono text-xs">
                    <p class="font-sans font-bold text-sm text-light-text dark:text-dark-text">{{ __('public.cookies.infra_dept') }}</p>
                    <p class="text-light-muted dark:text-dark-muted">{{ __('public.cookies.infra_email') }}</p>
                </div>
            </section>
        </div>

        {{-- Footer Link --}}
        <div class="mt-16 pt-8 border-t border-light-border dark:border-dark-border flex items-center justify-between">
            <a href="{{ route('terms') }}" class="text-xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text inline-flex items-center gap-1">
                <i class="ri-arrow-left-line"></i> {{ __('public.cookies.terms_link') }}
            </a>
            <a href="{{ route('home') }}" class="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                {{ __('public.cookies.home_link') }} &rarr;
            </a>
        </div>
    </div>
</div>
@endsection
