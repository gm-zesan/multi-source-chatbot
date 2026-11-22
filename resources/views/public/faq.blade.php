@extends('layouts.public')

@section('title', 'Frequently Asked Questions — Entrepreneurs Automation')
@section('meta_description', 'Frequently Asked Questions. Everything you need to know about Entrepreneurs Automation: Multi-source knowledge grounding, unified inbox, integrations, privacy, and early access.')

@section('content')
<div class="py-20 lg:py-28">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Hero Header --}}
        <div class="text-center max-w-3xl mx-auto mb-16">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-brand-500/10 text-brand-600 dark:text-brand-400 border border-brand-500/20 mb-4">
                <i class="ri-questionnaire-line"></i> {{ __('public.faq.badge') }}
            </div>
            <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text mb-4">
                <span class="block">{{ __('public.faq.title_prefix') }}</span>
                <span class="block bg-gradient-to-r from-brand-500 to-sky-400 bg-clip-text text-transparent">{{ __('public.faq.title_highlight') }}</span>
            </h1>
            <p class="text-base sm:text-lg text-light-muted dark:text-dark-muted leading-relaxed">
                {{ __('public.faq.desc') }}
            </p>
        </div>

        {{-- Category Pills --}}
        <div class="flex flex-wrap items-center justify-center gap-2 mb-12" id="faqCategoryFilter">
            <button onclick="filterFaq('all', this)" class="faq-category-btn active px-4 py-2 rounded-lg text-xs font-medium bg-brand-600 text-white shadow-sm transition-all duration-150">
                {{ __('public.faq.cat_all') }}
            </button>
            <button onclick="filterFaq('knowledge', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                {{ __('public.faq.cat_knowledge') }}
            </button>
            <button onclick="filterFaq('inbox', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                {{ __('public.faq.cat_inbox') }}
            </button>
            <button onclick="filterFaq('integrations', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                {{ __('public.faq.cat_integrations') }}
            </button>
            <button onclick="filterFaq('security', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                {{ __('public.faq.cat_security') }}
            </button>
            <button onclick="filterFaq('pricing', this)" class="faq-category-btn px-4 py-2 rounded-lg text-xs font-medium bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border border-light-border dark:border-dark-border hover:border-brand-500/50 transition-all duration-150">
                {{ __('public.faq.cat_pricing') }}
            </button>
        </div>

        {{-- FAQ Accordion List --}}
        <div class="space-y-4" id="faqContainer">
            {{-- Knowledge 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="knowledge">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-file-excel-2-line text-emerald-500"></i>
                        {{ __('public.faq.k1_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.k1_a') }}
                </div>
            </div>

            {{-- Knowledge 2 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="knowledge">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-folder-chart-line text-brand-500"></i>
                        {{ __('public.faq.k2_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.k2_a') }}
                </div>
            </div>

            {{-- Knowledge 3 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="knowledge">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-refresh-line text-sky-500"></i>
                        {{ __('public.faq.k3_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.k3_a') }}
                </div>
            </div>

            {{-- Inbox 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="inbox">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-user-shared-line text-amber-500"></i>
                        {{ __('public.faq.i1_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.i1_a') }}
                </div>
            </div>

            {{-- Inbox 2 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="inbox">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-chat-voice-line text-indigo-500"></i>
                        {{ __('public.faq.i2_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.i2_a') }}
                </div>
            </div>

            {{-- Integrations 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="integrations">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-messenger-line text-blue-500"></i>
                        {{ __('public.faq.int1_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.int1_a') }}
                </div>
            </div>

            {{-- Integrations 2 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="integrations">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-code-box-line text-purple-500"></i>
                        {{ __('public.faq.int2_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.int2_a') }}
                </div>
            </div>

            {{-- Security 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="security">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-shield-keyhole-line text-emerald-500"></i>
                        {{ __('public.faq.s1_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.s1_a') }}
                </div>
            </div>

            {{-- Pricing 1 --}}
            <div class="faq-item rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface overflow-hidden transition-all duration-200" data-category="pricing">
                <button type="button" onclick="toggleFaq(this)" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-semibold text-light-text dark:text-dark-text hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                    <span class="flex items-center gap-3">
                        <i class="ri-wallet-3-line text-brand-500"></i>
                        {{ __('public.faq.p1_q') }}
                    </span>
                    <i class="ri-arrow-down-s-line text-lg text-light-muted dark:text-dark-muted transition-transform duration-200 faq-icon"></i>
                </button>
                <div class="faq-content hidden px-6 pb-6 text-sm text-light-muted dark:text-dark-muted leading-relaxed border-t border-light-border/40 dark:border-dark-border/40 pt-4">
                    {{ __('public.faq.p1_a') }}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleFaq(button) {
        const item = button.closest('.faq-item');
        const content = item.querySelector('.faq-content');
        const icon = item.querySelector('.faq-icon');
        const isHidden = content.classList.contains('hidden');

        document.querySelectorAll('.faq-content').forEach(c => c.classList.add('hidden'));
        document.querySelectorAll('.faq-icon').forEach(i => i.style.transform = 'rotate(0deg)');

        if (isHidden) {
            content.classList.remove('hidden');
            icon.style.transform = 'rotate(180deg)';
        }
    }

    function filterFaq(category, button) {
        document.querySelectorAll('.faq-category-btn').forEach(b => {
            b.classList.remove('active', 'bg-brand-600', 'text-white', 'shadow-sm');
            b.classList.add('bg-light-surface', 'dark:bg-dark-surface', 'text-light-muted', 'dark:text-dark-muted', 'border', 'border-light-border', 'dark:border-dark-border');
        });

        button.classList.add('active', 'bg-brand-600', 'text-white', 'shadow-sm');
        button.classList.remove('bg-light-surface', 'dark:bg-dark-surface', 'text-light-muted', 'dark:text-dark-muted', 'border', 'border-light-border', 'dark:border-dark-border');

        const items = document.querySelectorAll('.faq-item');
        items.forEach(item => {
            if (category === 'all' || item.getAttribute('data-category') === category) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>
@endsection
