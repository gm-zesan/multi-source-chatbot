@extends('layouts.public')

@section('title', 'Solutions for Commerce & Support — Entrepreneurs Automation')
@section('meta_description', 'Tailored business solutions for e-commerce brands, f-commerce merchants, and multi-location retailers. Reduce manual support and automate inquiries.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-5xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-lightbulb-line"></i> {{ __('public.solutions.badge') }}
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
            {!! __('public.solutions.title') !!}
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            {{ __('public.solutions.desc') }}
        </p>
    </div>

    {{-- 4 Core Solutions --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        {{-- Solution 1: Customer Sales & Support Department --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-customer-service-2-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-brand-500">{{ __('public.solutions.s1_tag') }}</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">{{ __('public.solutions.s1_title') }}</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                {{ __('public.solutions.s1_desc') }}
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> {{ __('public.solutions.s1_point1') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> {{ __('public.solutions.s1_point2') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> {{ __('public.solutions.s1_point3') }}</li>
            </ul>
        </div>

        {{-- Solution 2: Order Dispatch & Courier Logistics --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-truck-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-amber-500">{{ __('public.solutions.s2_tag') }}</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">{{ __('public.solutions.s2_title') }}</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                {{ __('public.solutions.s2_desc') }}
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-amber-500"></i> {{ __('public.solutions.s2_point1') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-amber-500"></i> {{ __('public.solutions.s2_point2') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-amber-500"></i> {{ __('public.solutions.s2_point3') }}</li>
            </ul>
        </div>

        {{-- Solution 3: Financial Accounting & Inventory Forecasting --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-funds-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-purple-500">{{ __('public.solutions.s3_tag') }}</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">{{ __('public.solutions.s3_title') }}</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                {{ __('public.solutions.s3_desc') }}
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> {{ __('public.solutions.s3_point1') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> {{ __('public.solutions.s3_point2') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> {{ __('public.solutions.s3_point3') }}</li>
            </ul>
        </div>

        {{-- Solution 4: F-Commerce & Omnichannel Social Retailers --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-store-2-line"></i>
            </div>
            <div>
                <span class="text-3xs font-mono uppercase tracking-wider font-bold text-emerald-500">{{ __('public.solutions.s4_tag') }}</span>
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">{{ __('public.solutions.s4_title') }}</h3>
            </div>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                {{ __('public.solutions.s4_desc') }}
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> {{ __('public.solutions.s4_point1') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> {{ __('public.solutions.s4_point2') }}</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> {{ __('public.solutions.s4_point3') }}</li>
            </ul>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>{{ __('public.solutions.discuss_btn') }}</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
