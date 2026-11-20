{{-- Full-Width Sticky Top Header (Linear / Vercel style) --}}
<header class="sticky top-0 z-50 w-full border-b border-light-border dark:border-white/[0.08] bg-light-surface/85 dark:bg-dark-canvas/85 backdrop-blur-md transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        
        {{-- Brand Logo --}}
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 group">
                <img src="{{ asset('images/logo/ea-app-icon.png') }}" alt="Entrepreneurs Automation" class="w-8 h-8 rounded-lg object-contain shadow-xs group-hover:scale-105 transition-transform duration-200">
                <div class="flex flex-col">
                    <span class="font-bold text-sm tracking-tight text-light-text dark:text-dark-text group-hover:text-brand-500 transition-colors">
                        Entrepreneurs Automation
                    </span>
                    <span class="text-3xs font-mono tracking-wider text-light-muted dark:text-dark-muted uppercase font-semibold">
                        Enterprise AI Platform
                    </span>
                </div>
            </a>
        </div>

        {{-- Desktop Navigation Links --}}
        <nav class="hidden lg:flex items-center gap-1 text-xs font-medium text-light-text-secondary dark:text-dark-text-secondary">
            
            {{-- Platform Dropdown --}}
            <div class="relative group">
                <button type="button" class="flex items-center gap-1 px-3 py-2 rounded-lg hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                    <span>Platform</span>
                    <i class="ri-arrow-down-s-line text-xs transition-transform group-hover:rotate-180"></i>
                </button>
                <div class="absolute top-full left-0 pt-2 w-72 hidden group-hover:block transition-all">
                    <div class="p-2 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface shadow-dropdown backdrop-blur-md space-y-1">
                        <a href="{{ route('platform') }}" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                            <i class="ri-dashboard-3-line text-brand-500 text-sm mt-0.5"></i>
                            <div>
                                <div class="font-semibold text-xs text-light-text dark:text-dark-text">Platform Overview</div>
                                <div class="text-3xs text-light-muted dark:text-dark-muted">Complete connected business engine</div>
                            </div>
                        </a>
                        <a href="{{ route('knowledge-engine') }}" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                            <i class="ri-database-2-line text-sky-500 text-sm mt-0.5"></i>
                            <div>
                                <div class="font-semibold text-xs text-light-text dark:text-dark-text">AI Knowledge Engine</div>
                                <div class="text-3xs text-light-muted dark:text-dark-muted">Excel, spreadsheet & doc semantic grounding</div>
                            </div>
                        </a>
                        <a href="{{ route('unified-inbox') }}" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                            <i class="ri-chat-1-line text-emerald-500 text-sm mt-0.5"></i>
                            <div>
                                <div class="font-semibold text-xs text-light-text dark:text-dark-text">Unified Customer Inbox</div>
                                <div class="text-3xs text-light-muted dark:text-dark-muted">Messenger, Web Chat & human handoff</div>
                            </div>
                        </a>
                        <a href="{{ route('business-intelligence') }}" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                            <i class="ri-bar-chart-box-line text-purple-500 text-sm mt-0.5"></i>
                            <div>
                                <div class="font-semibold text-xs text-light-text dark:text-dark-text">Business Intelligence</div>
                                <div class="text-3xs text-light-muted dark:text-dark-muted">Owner-first analytics & query insights</div>
                            </div>
                        </a>
                        <a href="{{ route('automation') }}" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                            <i class="ri-cpu-line text-amber-500 text-sm mt-0.5"></i>
                            <div>
                                <div class="font-semibold text-xs text-light-text dark:text-dark-text">Intelligent Automation</div>
                                <div class="text-3xs text-light-muted dark:text-dark-muted">Order workflows & resolution gates</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <a href="{{ route('solutions') }}" class="px-3 py-2 rounded-lg hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                Solutions
            </a>

            <a href="{{ route('integrations') }}" class="px-3 py-2 rounded-lg hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                Integrations
            </a>

            <a href="{{ route('resources') }}" class="px-3 py-2 rounded-lg hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                Resources
            </a>

            <a href="{{ route('pricing') }}" class="px-3 py-2 rounded-lg hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                Pricing
            </a>

            <a href="{{ route('about') }}" class="px-3 py-2 rounded-lg hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                About
            </a>

            <a href="{{ route('contact') }}" class="px-3 py-2 rounded-lg hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                Contact
            </a>
        </nav>

        {{-- Right Action Buttons --}}
        <div class="flex items-center gap-2.5">
            {{-- Dark / Light Theme Toggle Button --}}
            <button type="button" onclick="togglePublicTheme()" class="w-8 h-8 rounded-lg border border-light-border dark:border-white/[0.08] flex items-center justify-center text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" title="Toggle Theme" aria-label="Toggle Theme">
                <i class="ri-sun-line text-amber-400 public-theme-icon"></i>
            </button>

            @auth
                <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-light-border dark:border-white/[0.1] text-xs font-semibold text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                    <i class="ri-dashboard-line text-brand-500"></i>
                    <span>Console</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                    Sign In
                </a>
                <a href="{{ route('register') }}" class="btn-shimmer hidden sm:inline-flex items-center gap-1 px-3.5 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-semibold shadow-xs transition-all">
                    <span>Get Started</span>
                    <i class="ri-arrow-right-line text-xs"></i>
                </a>
            @endauth

            {{-- Mobile Menu Hamburger --}}
            <button type="button" onclick="toggleMobileMenu()" class="lg:hidden w-8 h-8 rounded-lg border border-light-border dark:border-white/[0.08] flex items-center justify-center text-light-text dark:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" aria-label="Toggle Navigation">
                <i class="ri-menu-3-line text-lg" id="mobileMenuIcon"></i>
            </button>
        </div>
    </div>

    {{-- Mobile Dropdown Menu --}}
    <div id="publicMobileMenu" class="hidden lg:hidden border-t border-light-border dark:border-white/[0.08] bg-light-surface/95 dark:bg-dark-canvas/95 backdrop-blur-xl px-4 py-4 space-y-3">
        <div class="space-y-1">
            <div class="px-2 pb-1 text-3xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted font-mono">Platform Modules</div>
            <a href="{{ route('platform') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Platform Overview</a>
            <a href="{{ route('knowledge-engine') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">AI Knowledge Engine</a>
            <a href="{{ route('unified-inbox') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Unified Customer Inbox</a>
            <a href="{{ route('business-intelligence') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Business Intelligence</a>
            <a href="{{ route('automation') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Intelligent Automation</a>
        </div>
        <div class="border-t border-light-border dark:border-white/[0.08] pt-2 space-y-1">
            <a href="{{ route('solutions') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Solutions</a>
            <a href="{{ route('integrations') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Integrations</a>
            <a href="{{ route('pricing') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Pricing</a>
            <a href="{{ route('resources') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Resources</a>
            <a href="{{ route('about') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">About</a>
            <a href="{{ route('contact') }}" class="block px-3 py-2 rounded-lg text-xs font-medium hover:bg-light-elevated dark:hover:bg-dark-elevated">Contact</a>
        </div>
        <div class="border-t border-light-border dark:border-white/[0.08] pt-3 flex flex-col gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="w-full text-center py-2 rounded-lg bg-brand-600 text-white text-xs font-semibold">Open Console</a>
            @else
                <a href="{{ route('login') }}" class="w-full text-center py-2 rounded-lg border border-light-border dark:border-white/[0.08] text-xs font-medium">Sign In</a>
                <a href="{{ route('register') }}" class="btn-shimmer w-full text-center py-2 rounded-lg bg-brand-600 text-white text-xs font-semibold">Get Started Free</a>
            @endauth
        </div>
    </div>
</header>
