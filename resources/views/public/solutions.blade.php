@extends('layouts.public')

@section('title', 'Solutions for Commerce & Support — Entrepreneurs Automation')
@section('meta_description', 'Tailored business solutions for e-commerce brands, f-commerce merchants, and multi-location retailers. Reduce manual support and automate inquiries.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-lightbulb-line"></i> Industry Solutions
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Tailored for High-Velocity Modern Commerce.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Whether you operate an omnichannel retail chain, a fast-scaling Shopify storefront, or a high-volume social commerce page, Entrepreneurs Automation adapts to your operational bottlenecks.
        </p>
    </div>

    {{-- 4 Core Solutions --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        {{-- Solution 1: E-Commerce Brands --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-shopping-cart-2-line"></i>
            </div>
            <h3 class="text-lg font-bold text-light-text dark:text-dark-text">High-Volume E-Commerce Brands</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                When thousands of shoppers visit your storefront during sales campaigns, customer support tickets explode. Our autonomous agent answers product specifications, delivery timeframes, and sizing questions directly from your catalog spreadsheets without human delay.
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> Zero-latency answers during flash sales</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> Automatic stock verification per warehouse</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-brand-500"></i> 1-line script storefront installation</li>
            </ul>
        </div>

        {{-- Solution 2: F-Commerce & Social Retailers --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-facebook-box-line"></i>
            </div>
            <h3 class="text-lg font-bold text-light-text dark:text-dark-text">F-Commerce & Social Merchants</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Social commerce merchants face repetitive inbox floods: "Price?", "Delivery charge koto?", "Stock ache?". Entrepreneurs Automation monitors your Facebook Page Messenger 24/7, providing bilingual conversational answers and capturing customer phone numbers and delivery details.
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-blue-500"></i> 24/7 instant replies, including 2:00 AM inquiries</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-blue-500"></i> Fluent English and Bengali script understanding</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-blue-500"></i> Smooth handoff to human moderators for custom orders</li>
            </ul>
        </div>

        {{-- Solution 3: Multi-Location Retail Stores --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-store-2-line"></i>
            </div>
            <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Multi-Branch Retail Chains</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Customers frequently inquire about stock at specific retail outlets: "Is this shoe available in Dhanmondi branch?". By uploading multi-branch inventory spreadsheets, the AI tells customers which exact branch has their size and opening hours.
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> Outlet-specific inventory resolution</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> Branch address & timing dissemination</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-emerald-500"></i> Reduced call center phone burden</li>
            </ul>
        </div>

        {{-- Solution 4: Support & Operations Teams --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-2xl font-bold">
                <i class="ri-customer-service-2-line"></i>
            </div>
            <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Support & Operations Teams</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Free your support agents from answering the same policy questions dozens of times a day. Automate 90%+ of repetitive questions, allowing human staff to focus on high-value escalated sales, VIP clients, and complex troubleshooting.
            </p>
            <ul class="space-y-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary pt-2">
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> 90%+ first-touch automated resolution</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> Response times dropped from 20m to 200ms</li>
                <li class="flex items-center gap-2"><i class="ri-check-line text-purple-500"></i> Comprehensive agent audit logs & exports</li>
            </ul>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>Discuss Your Business Use Case</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
