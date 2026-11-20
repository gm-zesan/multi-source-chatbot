<footer class="border-t border-light-border dark:border-dark-border bg-light-surface/60 dark:bg-dark-surface/60 backdrop-blur-md pt-16 pb-12 transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        
        {{-- Top Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-8 lg:gap-12">
            
            {{-- Column 1: Brand Info (Spans 2 on mobile, 1 on desktop) --}}
            <div class="col-span-2 space-y-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-brand-500/15 border border-brand-500/30 text-brand-500 flex items-center justify-center text-xs font-bold shadow-xs">
                        <i class="ri-sparkling-fill"></i>
                    </div>
                    <span class="font-bold text-sm tracking-tight text-light-text dark:text-dark-text">
                        Entrepreneurs Automation
                    </span>
                </a>
                <p class="text-xs text-light-muted dark:text-dark-muted max-w-sm leading-relaxed">
                    An intelligent business platform connecting customer engagement, business knowledge, and enterprise automation into one unified ecosystem.
                </p>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-3xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <i class="ri-checkbox-circle-fill text-emerald-500 text-xs"></i>
                        <span>All Core Systems Operational</span>
                    </span>
                </div>
            </div>

            {{-- Column 2: Platform --}}
            <div class="space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                    Platform
                </div>
                <ul class="space-y-2 text-xs text-light-muted dark:text-dark-muted">
                    <li><a href="{{ route('platform') }}" class="hover:text-brand-500 transition-colors">Overview</a></li>
                    <li><a href="{{ route('knowledge-engine') }}" class="hover:text-brand-500 transition-colors">AI Knowledge Engine</a></li>
                    <li><a href="{{ route('unified-inbox') }}" class="hover:text-brand-500 transition-colors">Unified Inbox</a></li>
                    <li><a href="{{ route('business-intelligence') }}" class="hover:text-brand-500 transition-colors">Business Intelligence</a></li>
                    <li><a href="{{ route('automation') }}" class="hover:text-brand-500 transition-colors">Intelligent Automation</a></li>
                </ul>
            </div>

            {{-- Column 3: Ecosystem & Integrations --}}
            <div class="space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                    Ecosystem
                </div>
                <ul class="space-y-2 text-xs text-light-muted dark:text-dark-muted">
                    <li><a href="{{ route('integrations') }}" class="hover:text-brand-500 transition-colors">Facebook Messenger</a></li>
                    <li><a href="{{ route('integrations') }}" class="hover:text-brand-500 transition-colors">Web Chat Widget</a></li>
                    <li><a href="{{ route('integrations') }}" class="hover:text-brand-500 transition-colors">WhatsApp Business</a></li>
                    <li><a href="{{ route('integrations') }}" class="hover:text-brand-500 transition-colors">REST API & Webhooks</a></li>
                    <li><a href="{{ route('solutions') }}" class="hover:text-brand-500 transition-colors">Storefront Solutions</a></li>
                </ul>
            </div>

            {{-- Column 4: Company & Legal --}}
            <div class="space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                    Company
                </div>
                <ul class="space-y-2 text-xs text-light-muted dark:text-dark-muted">
                    <li><a href="{{ route('about') }}" class="hover:text-brand-500 transition-colors">About Us</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-brand-500 transition-colors">Contact Enterprise</a></li>
                    <li><a href="{{ route('resources') }}" class="hover:text-brand-500 transition-colors">Resources & Guides</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-brand-500 transition-colors">FAQ</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-brand-500 transition-colors">Privacy Policy</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-brand-500 transition-colors">Terms of Service</a></li>
                </ul>
            </div>
        </div>

        {{-- Bottom Copyright Strip --}}
        <div class="pt-8 border-t border-light-border dark:border-dark-border flex flex-col sm:flex-row items-center justify-between gap-4 text-3xs text-light-muted dark:text-dark-muted">
            <div class="flex items-center gap-3">
                <span>&copy; {{ date('Y') }} Entrepreneurs Automation. All rights reserved.</span>
                <span class="text-light-border dark:text-dark-border">/</span>
                <span class="font-mono">v2.0 Enterprise</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('privacy') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">Privacy</a>
                <a href="{{ route('terms') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">Terms</a>
                <a href="{{ route('cookies') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">Cookies</a>
            </div>
        </div>
    </div>
</footer>
