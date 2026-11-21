<aside id="adminSidebar" class="w-56 h-full max-h-full min-h-0 border-r border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface flex-shrink-0 flex flex-col justify-between overflow-y-auto scrollbar transition-all duration-200">
    <div class="p-2 space-y-3.5">
        {{-- Brand / Workspace Identity --}}
        <div class="sidebar-brand p-2 flex items-center gap-2 border-b border-light-border dark:border-dark-border pb-2.5 transition-all">
            <img src="{{ asset('images/logo/ea-app-icon.png') }}" alt="Entrepreneurs Automation" class="w-7 h-7 rounded object-contain flex-shrink-0 shadow-xs">
            <div class="sidebar-text-label overflow-hidden">
                <div class="text-xs font-semibold text-light-text dark:text-dark-text truncate">
                    {{ Auth::user()->workspace->name ?? 'Entrepreneurs Automation' }}
                </div>
                <div class="text-2xs text-light-muted dark:text-dark-muted truncate">
                    @if(Auth::user()->isSuperAdmin())
                        Platform Owner
                    @elseif(Auth::user()->isPlatformSupport())
                        Platform Support
                    @elseif(Auth::user()->isBusinessAdmin())
                        Store Owner
                    @elseif(Auth::user()->isWorkspaceManager())
                        Store Manager
                    @elseif(Auth::user()->isWorkspaceAgent())
                        Support Agent
                    @else
                        Enterprise Workspace
                    @endif
                </div>
            </div>
        </div>

        {{-- Navigation Menu --}}
        <nav class="space-y-3">
            {{-- 1. INBOX & FULFILLMENT (All Workspace Roles) --}}
            <div>
                <div class="sidebar-section-title px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Inbox & Orders
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('dashboard') }}" title="Executive Dashboard" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('dashboard') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-dashboard-3-line text-sm {{ Route::is('dashboard') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Dashboard</span>
                    </a>
                    <a href="{{ route('conversations.index') }}" title="Customer Live Chat & Inbox" class="sidebar-nav-item flex items-center justify-between px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('conversations.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="ri-message-3-line text-sm {{ Route::is('conversations.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                            <span class="sidebar-text-label truncate">Conversations</span>
                        </div>
                    </a>
                    <a href="{{ route('orders.index') }}" title="Captured Orders & Leads" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('orders.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-shopping-bag-3-line text-sm {{ Route::is('orders.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Orders & Leads</span>
                    </a>
                </div>
            </div>

            {{-- 2. AI KNOWLEDGE & TOOLS (Owner, Manager & Superadmin) --}}
            @if(!Auth::user()->isWorkspaceAgent())
            <div>
                <div class="sidebar-section-title px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    AI Knowledge & Tools
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('knowledge-base.index') }}" title="Knowledge Base & FAQs" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ in_array(Route::currentRouteName(), ['knowledge-base.index', 'faqs.index', 'faqs.create', 'faqs.edit', 'documents.index', 'documents.create']) ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-book-open-line text-sm {{ Route::is('knowledge-base.*') || Route::is('faqs.*') || Route::is('documents.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Knowledge Base</span>
                    </a>
                    <a href="{{ route('simulator.index') }}" title="Internal AI Chat Simulator" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('simulator.index') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-robot-2-line text-sm {{ Route::is('simulator.index') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">AI Simulator (Internal)</span>
                    </a>
                    <a href="{{ route('observability.index') }}" title="Telemetry & Observability" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('observability.index') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-pulse-line text-sm {{ Route::is('observability.index') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">AI Telemetry & Logs</span>
                    </a>
                </div>
            </div>
            @endif

            {{-- 3. BUSINESS INTELLIGENCE (Owner, Manager & Superadmin) --}}
            @if(!Auth::user()->isWorkspaceAgent())
            <div>
                <div class="sidebar-section-title px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Business Insights
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('analytics.index') }}" title="Business Intelligence Analytics" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('analytics.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-bar-chart-2-line text-sm {{ Route::is('analytics.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Analytics & BI</span>
                    </a>
                    @if(Auth::user()->isSuperAdmin())
                    <a href="{{ route('message') }}" title="Platform Contact Inquiries" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('message*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-mail-line text-sm {{ Route::is('message*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Platform Inquiries</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- 4. CHANNELS & INTEGRATIONS (Business Owner & Superadmin Only) --}}
            @if(Auth::user()->isSuperAdmin() || Auth::user()->isBusinessAdmin())
            <div>
                <div class="sidebar-section-title px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Channels & APIs
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('channels.index') }}" title="Social & Messaging Integrations" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('channels.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-share-line text-sm {{ Route::is('channels.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Social & Messaging</span>
                    </a>
                    <a href="{{ route('widget.manage') }}" title="Storefront Web Chat Widget" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('widget.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-code-s-slash-line text-sm {{ Route::is('widget.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Web Chat Widget</span>
                    </a>
                    <a href="{{ route('api-keys.index') }}" title="Developer REST API Keys" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('api-keys.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-terminal-box-line text-sm {{ Route::is('api-keys.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Developer API Keys</span>
                    </a>
                </div>
            </div>
            @endif

            {{-- 5. TEAM & EMPLOYEES (Business Owner & Superadmin Only) --}}
            @if(Auth::user()->isSuperAdmin() || Auth::user()->isBusinessAdmin())
            <div>
                <div class="sidebar-section-title px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Team & Access
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('users.index') }}" title="{{ Auth::user()->isSuperAdmin() ? 'All Platform Users' : 'Team & Staff' }}" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ in_array(Route::currentRouteName(), ['users.index', 'users.create', 'users.edit']) ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-user-settings-line text-sm {{ Route::is('users.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">{{ Auth::user()->isSuperAdmin() ? 'All Users' : 'Team & Employees' }}</span>
                    </a>
                    @if(Auth::user()->isSuperAdmin())
                    <a href="{{ route('roles.index') }}" title="Roles & Permissions" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ in_array(Route::currentRouteName(), ['roles.index', 'roles.create', 'roles.edit']) ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-shield-keyhole-line text-sm {{ Route::is('roles.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Roles & Permissions</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- 6. WORKSPACE SETTINGS & LOGISTICS (Business Owner & Superadmin Only) --}}
            @if(Auth::user()->isSuperAdmin() || Auth::user()->isBusinessAdmin())
            <div>
                <div class="sidebar-section-title px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Workspace & Billing
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('billing.index') }}" title="Billing & Prepaid Wallet" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('billing.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-wallet-3-line text-sm {{ Route::is('billing.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Billing & Wallet</span>
                    </a>
                    <a href="{{ route('workspace.courier.settings') }}" title="Courier Logistics (Steadfast, Pathao, RedX)" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('workspace.courier.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-truck-line text-sm {{ Route::is('workspace.courier.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Courier & Delivery</span>
                    </a>
                    <a href="{{ route('workspace.settings.edit') }}" title="Workspace Business Profile" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('workspace.settings.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-settings-4-line text-sm {{ Route::is('workspace.settings.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Workspace Settings</span>
                    </a>
                </div>
            </div>
            @endif

            {{-- 7. PLATFORM GOVERNANCE (Superadmin & Platform Support Only) --}}
            @if(Auth::user()->isSuperAdmin() || Auth::user()->isPlatformSupport())
            <div>
                <div class="sidebar-section-title px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Platform Governance
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('workspaces.index') }}" title="Workspaces Multi-Tenant Directory" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('workspaces.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-building-line text-sm {{ Route::is('workspaces.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">All Workspaces</span>
                    </a>
                    @if(Auth::user()->isSuperAdmin())
                    <a href="{{ route('admin.commercial.index') }}" title="Commercial Plans & Wallets" class="sidebar-nav-item flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('commercial.*') || Route::is('admin.commercial.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-money-dollar-circle-line text-sm {{ Route::is('commercial.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span class="sidebar-text-label">Commercial & Billing</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif
        </nav>
    </div>
</aside>
