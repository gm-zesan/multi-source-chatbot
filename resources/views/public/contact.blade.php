@extends('layouts.public')

@section('title', 'Contact Enterprise Sales & Support — Entrepreneurs Automation')
@section('meta_description', 'Contact our enterprise sales and engineering team. Inquire about custom deployments, multi-channel setup, and volume pricing.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-mail-send-line"></i> Direct Communication
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Let's Discuss Your Business Operations.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Have questions about custom catalog ingestion, multi-workspace deployment, or volume pricing? Fill out the form below to connect directly with our engineering and enterprise team.
        </p>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 max-w-6xl mx-auto items-start">
        
        {{-- Contact Form (7 cols) --}}
        <div class="lg:col-span-7 p-6 sm:p-10 rounded-3xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-sm dark:shadow-[0_8px_30px_rgb(0,0,0,0.3)] space-y-6">
            <div class="space-y-1">
                <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Send Us an Inquiry</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted">Messages are routed directly to our operations team.</p>
            </div>

            {{-- Success Message --}}
            @if(session('success'))
                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
                    <i class="ri-checkbox-circle-fill text-base"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            {{-- Error Summary --}}
            @if(isset($errors) && $errors->any())
                <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <i class="ri-error-warning-fill"></i> Please review the following errors:
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
                        <label for="name" class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                            Full Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required
                               class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                               placeholder="e.g. Tanvir Ahmed">
                    </div>

                    {{-- Email --}}
                    <div class="space-y-1">
                        <label for="email" class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                            Work Email <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                               class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                               placeholder="tanvir@company.com">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Phone --}}
                    <div class="space-y-1">
                        <label for="phone" class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                            Phone / WhatsApp (Optional)
                        </label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                               class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                               placeholder="+880 1700 000000">
                    </div>

                    {{-- Subject --}}
                    <div class="space-y-1">
                        <label for="subject" class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                            Inquiry Topic
                        </label>
                        <input type="text" id="subject" name="subject" value="{{ old('subject') }}"
                               class="w-full h-10 px-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all"
                               placeholder="e.g. Enterprise Deployment">
                    </div>
                </div>

                {{-- Message --}}
                <div class="space-y-1">
                    <label for="message" class="block text-2xs font-semibold uppercase tracking-wider text-light-text dark:text-dark-text">
                        Message Details <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="message" name="message" rows="5" required
                              class="w-full p-3.5 rounded-lg border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-xs text-light-text dark:text-dark-text placeholder:text-light-muted dark:placeholder:text-dark-muted focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all resize-none"
                              placeholder="Describe your conversation volume, current channels, and integration goals...">{{ old('message') }}</textarea>
                </div>

                {{-- Submit Button --}}
                <button type="submit" id="btnSubmitContact"
                        class="w-full inline-flex items-center justify-center gap-2 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all cursor-pointer">
                    <i class="ri-send-plane-fill"></i>
                    <span>Send Message to Team</span>
                </button>
            </form>
        </div>

        {{-- Direct Info Sidebar (5 cols) --}}
        <div class="lg:col-span-5 space-y-6">
            <div class="p-6 rounded-3xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-5">
                <h3 class="text-sm font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                    Enterprise Communication Channels
                </h3>

                <div class="space-y-4 text-xs">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-500 flex items-center justify-center text-sm flex-shrink-0">
                            <i class="ri-mail-line"></i>
                        </div>
                        <div>
                            <div class="font-semibold text-light-text dark:text-dark-text">Enterprise Inquiries</div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted font-mono">support@entrepreneurs.automation</div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-sm flex-shrink-0">
                            <i class="ri-time-line"></i>
                        </div>
                        <div>
                            <div class="font-semibold text-light-text dark:text-dark-text">Operational Hours</div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Monday – Friday: 9:00 AM – 8:00 PM (BST)</div>
                            <div class="text-3xs text-emerald-500 mt-0.5">24/7 Automated AI Monitoring Active</div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center text-sm flex-shrink-0">
                            <i class="ri-shield-check-line"></i>
                        </div>
                        <div>
                            <div class="font-semibold text-light-text dark:text-dark-text">Data Privacy & Workspace Isolation</div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Inquiries and catalog schemas are held under strict non-disclosure and tenant isolation protocols.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 rounded-3xl border border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 space-y-2">
                <div class="text-xs font-bold text-light-text dark:text-dark-text">Already an Enterprise Client?</div>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Access your live telemetry dashboard, manage catalog spreadsheets, and inspect conversation traces directly from your dedicated console.
                </p>
                <div class="pt-2">
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:text-brand-600">
                        <span>Sign in to Enterprise Console</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
