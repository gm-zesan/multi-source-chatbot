@extends('layouts.public')

@section('title', 'Contact Enterprise Sales & Support — Entrepreneurs Automation')
@section('meta_description', "Let's Discuss Your Business Operations. Contact our enterprise sales and engineering team. Inquire about custom deployments, multi-channel setup, and volume pricing.")

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">

        {{-- Hero --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
                <i class="ri-mail-send-line"></i> {{ __('public.contact.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.contact.title') !!}
                <span class="sr-only">Let's Discuss Your Business Operations</span>
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.contact.desc') }}
            </p>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 max-w-6xl mx-auto items-start">

            {{-- Contact Form (7 cols) --}}
            <div
                class="lg:col-span-7 p-6 sm:p-10 rounded-3xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-sm dark:shadow-[0_8px_30px_rgb(0,0,0,0.3)] space-y-6">
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-light-text dark:text-dark-text">{{ __('public.contact.form_title') }}
                    </h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted">{{ __('public.contact.form_sub') }}</p>
                </div>

                {{-- Success Message --}}
                @if(session('success'))
                    <div
                        class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
                        <i class="ri-checkbox-circle-fill text-base"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                {{-- Error Summary --}}
                @if(isset($errors) && $errors->any())
                    <div
                        class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <i class="ri-error-warning-fill"></i> {{ __('public.contact.errors_title') }}
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 text-3xs">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Real Contact Form with CSRF --}}
                <form action="{{ route('contact.store') }}" method="POST" id="publicContactForm" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Name --}}
                        <div class="space-y-1">
                            <label for="name"
                                class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                                {{ __('public.contact.name_label') }} <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                                placeholder="{{ __('public.contact.name_placeholder') }}">
                        </div>

                        {{-- Email --}}
                        <div class="space-y-1">
                            <label for="email"
                                class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                                {{ __('public.contact.email_label') }} <span class="text-rose-500">*</span>
                            </label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                                placeholder="{{ __('public.contact.email_placeholder') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Phone --}}
                        <div class="space-y-1">
                            <label for="phone"
                                class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                                {{ __('public.contact.phone_label') }}
                            </label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                                class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                                placeholder="+880 1700 000000">
                        </div>

                        {{-- Subject --}}
                        <div class="space-y-1">
                            <label for="subject"
                                class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                                {{ __('public.contact.subject_label') }}
                            </label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject') }}"
                                class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                                placeholder="{{ __('public.contact.subject_placeholder') }}">
                        </div>
                    </div>

                    {{-- Message --}}
                    <div class="space-y-1">
                        <label for="message"
                            class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                            {{ __('public.contact.msg_label') }} <span class="text-rose-500">*</span>
                        </label>
                        <textarea id="message" name="message" rows="5" required
                            class="w-full p-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all resize-none"
                            placeholder="{{ __('public.contact.msg_placeholder') }}">{{ old('message') }}</textarea>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit" id="btnSubmitContact"
                        class="w-full inline-flex items-center justify-center gap-2 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all cursor-pointer">
                        <i class="ri-send-plane-fill"></i>
                        <span>{{ __('public.contact.submit_btn') }}</span>
                    </button>
                </form>
            </div>

            {{-- Direct Info Sidebar (5 cols) --}}
            <div class="lg:col-span-5 space-y-6">
                <div
                    class="p-6 rounded-3xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-5">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                        {{ __('public.contact.channels_title') }}
                    </h3>

                    <div class="space-y-4 text-xs">
                        <div class="flex items-start gap-3">
                            <div
                                class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-500 flex items-center justify-center text-sm flex-shrink-0">
                                <i class="ri-mail-line"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-light-text dark:text-dark-text">
                                    {{ __('public.contact.ent_inquiries') }}</div>
                                <div class="text-2xs text-light-muted dark:text-dark-muted font-mono">
                                    support@entrepreneurs.automation</div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div
                                class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-sm flex-shrink-0">
                                <i class="ri-time-line"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-light-text dark:text-dark-text">
                                    {{ __('public.contact.hours_title') }}</div>
                                <div class="text-2xs text-light-muted dark:text-dark-muted">
                                    {{ __('public.contact.hours_desc') }}</div>
                                <div class="text-3xs text-emerald-500 mt-0.5">{{ __('public.contact.hours_sub') }}</div>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div
                                class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center text-sm flex-shrink-0">
                                <i class="ri-map-pin-line"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-light-text dark:text-dark-text">
                                    {{ __('public.contact.office_title') }}</div>
                                <div class="text-2xs text-light-muted dark:text-dark-muted">
                                    {{ __('public.contact.office_desc') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection

@if (session('success'))
    @push('custom-scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof gtag === 'function') {
                    gtag('event', 'generate_lead');
                }
                if (typeof fbq === 'function') {
                    fbq('track', 'Lead');
                }
            });
        </script>
    @endpush
@endif