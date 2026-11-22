@extends('layouts.public')

@section('title', 'Simple, Transparent Pricing — Entrepreneurs Automation')
@section('meta_description', 'Pay only ৳0.40 BDT per AI message. Get started with ৳50.00 free starter balance (125 complimentary messages). Omnichannel AI for Facebook, WhatsApp, TikTok, and Web.')

@section('content')
    @php
        $mRate = isset($meteredPlan) ? (float) $meteredPlan->price_monthly : 0.40;
        $sBonus = isset($starterPlan) ? (float) $starterPlan->price_monthly : 50.00;
        $sMsgs = isset($starterPlan) && $starterPlan->max_conversations_monthly > 0
            ? (int) $starterPlan->max_conversations_monthly
            : (int) floor($sBonus / max(0.01, $mRate));
    @endphp
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-20">

        {{-- Hero Section --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
                <i class="ri-price-tag-3-line"></i> {!! __('public.pricing.badge') !!}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.pricing.title', ['rate' => number_format($mRate, 2)]) !!}
                <span class="sr-only">Simple, Fair Pricing</span>
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {!! __('public.pricing.subtitle', ['bonus' => number_format($sBonus, 2), 'msgs' => number_format($sMsgs)]) !!}
            </p>
        </div>

        {{-- Dynamic Tier Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse($plans as $plan)
                <div
                    class="relative p-8 rounded-2xl border {{ $plan->is_featured ? 'border-2 border-brand-500 bg-light-surface dark:bg-dark-surface shadow-md dark:shadow-[0_8px_30px_rgb(0,0,0,0.3)]' : 'border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface' }} space-y-6 flex flex-col justify-between">

                    @if($plan->is_featured)
                        <div
                            class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-brand-500 text-white text-3xs font-mono font-bold uppercase tracking-wider shadow-xs">
                            {{ $plan->badge ?: 'Free Trial' }}
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="space-y-1">
                            @if($plan->badge && !$plan->is_featured)
                                <span
                                    class="text-3xs uppercase tracking-wider font-semibold text-brand-500 font-mono">{{ $plan->badge }}</span>
                            @elseif($plan->is_featured)
                                <span class="text-3xs uppercase tracking-wider font-semibold text-emerald-500 font-mono">Sign-Up
                                    Bonus</span>
                            @else
                                <span
                                    class="text-3xs uppercase tracking-wider font-semibold text-brand-500 font-mono">{{ $plan->slug }}</span>
                            @endif
                            <h3 class="text-xl font-bold text-light-text dark:text-dark-text">{{ $plan->name }}</h3>
                            @if($plan->tagline)
                                <p class="text-2xs text-light-muted dark:text-dark-muted">{{ $plan->tagline }}</p>
                            @endif
                        </div>

                        <div class="py-3 border-y border-light-border dark:border-dark-border">
                            <div class="flex items-baseline gap-1">
                                @if($plan->billing_period_text === 'Volume Discount' || $plan->billing_period_text === 'Custom Quote' || ((float) $plan->price_monthly === 0.0 && empty($plan->billing_period_text)))
                                    <span class="text-2xl font-bold font-mono text-light-text dark:text-dark-text">Volume
                                        Discount</span>
                                @else
                                    <span
                                        class="text-3xl font-black font-mono {{ $plan->is_featured ? 'text-emerald-500' : 'text-light-text dark:text-dark-text' }}">
                                        {{ app()->getLocale() === 'en' ? 'BDT ' : '৳ ' }}{{ number_format((float) $plan->price_monthly, 2) }}
                                    </span>
                                    @if($plan->billing_period_text)
                                        <span
                                            class="text-xs {{ $plan->is_featured ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-light-muted dark:text-dark-muted' }}">
                                            {{ $plan->billing_period_text }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                            @if($plan->price_note)
                                <span
                                    class="text-3xs text-light-muted dark:text-dark-muted block mt-1">{{ $plan->price_note }}</span>
                            @elseif((float) $plan->price_yearly > 0)
                                <span class="text-3xs text-light-muted dark:text-dark-muted block mt-1">{{ app()->getLocale() === 'en' ? 'Yearly: BDT ' : 'Yearly: ৳ ' }}{{ number_format((float) $plan->price_yearly, 2) }}</span>
                            @endif
                        </div>

                        @if(!empty($plan->features) && is_array($plan->features))
                            <ul class="space-y-2.5 text-xs text-light-text-secondary dark:text-dark-text-secondary">
                                @foreach($plan->features as $feature)
                                    <li class="flex items-center gap-2">
                                        <i
                                            class="ri-checkbox-circle-fill {{ $plan->is_featured ? 'text-emerald-500' : 'text-brand-500' }}"></i>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    @php
                        $ctaText = $plan->cta_text ?: ($plan->is_featured ? 'Create Free Account' : 'Get Started');
                        $ctaUrl = $plan->cta_url ?: route('register');
                        if (str_starts_with($ctaUrl, '/')) {
                            $ctaUrl = url($ctaUrl);
                        }
                    @endphp
                    <a href="{{ $ctaUrl }}"
                        class="{{ $plan->is_featured ? 'btn-shimmer bg-brand-600 hover:bg-brand-500 text-white shadow-xs hover:shadow-sm' : 'border border-light-border dark:border-dark-border hover:bg-light-elevated dark:hover:bg-dark-elevated text-light-text dark:text-dark-text' }} w-full text-center py-2.5 rounded-xl text-xs font-semibold transition-all">
                        {{ $ctaText }}
                    </a>
                </div>
            @empty
                <div class="col-span-3 text-center py-12 text-light-muted dark:text-dark-muted">
                    {{ __('public.pricing.empty_plans') }}
                </div>
            @endforelse
        </div>

        {{-- Recharge Estimate Matrix --}}
        <div
            class="chamfer-card p-6 sm:p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/40 dark:bg-dark-surface/40 space-y-5">
            <div class="text-center space-y-1">
                <h3 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text">
                    {{ __('public.pricing.calculator_title') }}</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted">
                    {{ __('public.pricing.calculator_subtitle', ['rate' => number_format($mRate, 2)]) }}</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                <div
                    class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-center space-y-1">
                    <span
                        class="text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-mono block">{{ __('public.pricing.starter_topup') }}</span>
                    <div class="text-xl font-black text-light-text dark:text-dark-text">{{ app()->getLocale() === 'en' ? 'BDT ' : '৳ ' }}500</div>
                    <div class="text-2xs font-semibold text-emerald-500">{{ number_format(floor(500 / max(0.01, $mRate))) }}
                        {{ __('public.pricing.ai_messages') }}</div>
                </div>

                <div
                    class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-center space-y-1">
                    <span
                        class="text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-mono block">{{ __('public.pricing.growth_topup') }}</span>
                    <div class="text-xl font-black text-light-text dark:text-dark-text">{{ app()->getLocale() === 'en' ? 'BDT ' : '৳ ' }}1,000</div>
                    <div class="text-2xs font-semibold text-emerald-500">
                        {{ number_format(floor(1000 / max(0.01, $mRate))) }} {{ __('public.pricing.ai_messages') }}</div>
                </div>

                <div
                    class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-center space-y-1">
                    <span
                        class="text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-mono block">{{ __('public.pricing.pro_topup') }}</span>
                    <div class="text-xl font-black text-light-text dark:text-dark-text">{{ app()->getLocale() === 'en' ? 'BDT ' : '৳ ' }}2,000</div>
                    <div class="text-2xs font-semibold text-emerald-500">
                        {{ number_format(floor(2000 / max(0.01, $mRate))) }} {{ __('public.pricing.ai_messages') }}</div>
                </div>

                <div
                    class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface text-center space-y-1">
                    <span
                        class="text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-mono block">{{ __('public.pricing.mega_topup') }}</span>
                    <div class="text-xl font-black text-light-text dark:text-dark-text">{{ app()->getLocale() === 'en' ? 'BDT ' : '৳ ' }}5,000</div>
                    <div class="text-2xs font-semibold text-emerald-500">
                        {{ number_format(floor(5000 / max(0.01, $mRate))) }} {{ __('public.pricing.ai_messages') }}</div>
                </div>
            </div>
        </div>

        {{-- Pricing FAQ Section --}}
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="text-center space-y-2">
                <span
                    class="text-3xs font-mono font-bold uppercase tracking-wider text-brand-500">{{ __('public.pricing.transparent_answers') }}</span>
                <h2 class="text-2xl font-bold text-light-text dark:text-dark-text">{{ __('public.pricing.faq_heading') }}
                </h2>
            </div>

            <div class="space-y-3">
                <div
                    class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                    <h4 class="text-xs font-semibold text-light-text dark:text-dark-text">
                        {{ __('public.pricing.pricing_faq_q1', ['rate' => number_format($mRate, 2)]) }}</h4>
                    <p class="pt-1.5 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.pricing.pricing_faq_a1') }}
                    </p>
                </div>

                <div
                    class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                    <h4 class="text-xs font-semibold text-light-text dark:text-dark-text">
                        {{ __('public.pricing.pricing_faq_q2', ['bonus' => number_format($sBonus, 2)]) }}</h4>
                    <p class="pt-1.5 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.pricing.pricing_faq_a2', ['bonus' => number_format($sBonus, 2), 'msgs' => number_format($sMsgs)]) }}
                    </p>
                </div>

                <div
                    class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                    <h4 class="text-xs font-semibold text-light-text dark:text-dark-text">
                        {{ __('public.pricing.pricing_faq_q3') }}</h4>
                    <p class="pt-1.5 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.pricing.pricing_faq_a3') }}
                    </p>
                </div>

                <div
                    class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                    <h4 class="text-xs font-semibold text-light-text dark:text-dark-text">
                        {{ __('public.pricing.pricing_faq_q4') }}</h4>
                    <p class="pt-1.5 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.pricing.pricing_faq_a4', ['rate' => number_format($mRate, 2)]) }}
                    </p>
                </div>
            </div>
        </div>

    </div>
@endsection