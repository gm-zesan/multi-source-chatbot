<aside id="adminSidebar" class="w-56 h-full max-h-full min-h-0 border-r border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface flex-shrink-0 flex flex-col justify-between overflow-y-auto scrollbar transition-all duration-200">
    <div class="p-2 space-y-4">
        {{-- Brand / Workspace Identity --}}
        <div class="p-2 flex items-center gap-2 border-b border-light-border dark:border-dark-border pb-2.5">
            <div class="w-7 h-7 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-sm font-bold flex-shrink-0">
                <i class="ri-sparkling-fill"></i>
            </div>
            <div class="overflow-hidden">
                <div class="text-xs font-semibold text-light-text dark:text-dark-text truncate">
                    {{ Auth::user()->workspace->name ?? 'Entrepreneurs AI' }}
                </div>
                <div class="text-2xs text-light-muted dark:text-dark-muted truncate">
                    Enterprise Platform
                </div>
            </div>
        </div>

        {{-- Navigation Menu --}}
        <nav class="space-y-3">
            {{-- Core Section --}}
            <div>
                <div class="px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Core
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('dashboard') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-dashboard-3-line text-sm {{ Route::is('dashboard') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('analytics.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('analytics.index') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-bar-chart-2-line text-sm {{ Route::is('analytics.index') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Analytics & BI</span>
                    </a>
                </div>
            </div>

            {{-- Omni-Channel AI --}}
            <div>
                <div class="px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Omni-Channel AI
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('conversations.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('conversations.index') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-message-3-line text-sm {{ Route::is('conversations.index') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Conversations</span>
                    </a>
                    <a href="{{ route('widget.manage') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('widget.manage') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-code-s-slash-line text-sm {{ Route::is('widget.manage') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Web Chat Widget</span>
                    </a>
                    <a href="{{ route('simulator.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('simulator.index') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-robot-2-line text-sm {{ Route::is('simulator.index') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Chat Simulator</span>
                    </a>
                    <a href="{{ route('observability.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('observability.index') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-pulse-line text-sm {{ Route::is('observability.index') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Telemetry</span>
                    </a>
                    @can(\App\Enums\Permissions\MessagePermission::VIEW->value)
                    <a href="{{ route('message') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('message*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-mail-line text-sm {{ Route::is('message*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Contact Messages</span>
                    </a>
                    @endcan
                </div>
            </div>

            {{-- Platform Governance (Superadmin) or Workspace Settings --}}
            @if(Auth::user() && (Auth::user()->hasRole('superadmin') || Auth::user()->hasRole('admin') || Auth::user()->email === 'superadmin@example.com' || (int) Auth::user()->id === 1))
            <div>
                <div class="px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Governance
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('workspaces.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('workspaces.*') || Route::is('workspace.settings.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-building-line text-sm {{ Route::is('workspaces.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Workspaces & Settings</span>
                    </a>
                    <a href="{{ route('admin.commercial.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('commercial.*') || Route::is('admin.commercial.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-money-dollar-circle-line text-sm {{ Route::is('commercial.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Commercial & Billing</span>
                    </a>
                </div>
            </div>
            @else
            <div>
                <div class="px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Settings
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('workspace.settings.edit') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ Route::is('workspace.settings.*') ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-settings-4-line text-sm {{ Route::is('workspace.settings.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Workspace Settings</span>
                    </a>
                </div>
            </div>
            @endif

            {{-- Knowledge Base --}}
            @canany([\App\Enums\Permissions\FAQPermission::VIEW->value, \App\Enums\Permissions\KnowledgeDocumentPermission::VIEW->value])
            <div>
                <div class="px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Knowledge Base
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('knowledge-base.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ in_array(Route::currentRouteName(), ['knowledge-base.index', 'faqs.index', 'faqs.create', 'faqs.edit', 'documents.index', 'documents.create']) ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-book-open-line text-sm {{ Route::is('knowledge-base.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Knowledge Base</span>
                    </a>
                </div>
            </div>
            @endcanany

            {{-- Users & Roles --}}
            <div>
                <div class="px-2 pb-1 text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                    Users & Access
                </div>
                <div class="space-y-0.5">
                    <a href="{{ route('users.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ in_array(Route::currentRouteName(), ['users.index', 'users.create', 'users.edit']) ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-user-settings-line text-sm {{ Route::is('users.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Users</span>
                    </a>
                    <a href="{{ route('roles.index') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs font-medium transition-colors {{ in_array(Route::currentRouteName(), ['roles.index', 'roles.create', 'roles.edit']) ? 'text-brand-600 dark:text-brand-400 bg-brand-500/10 font-semibold border-l-2 border-brand-500 rounded-l-none' : 'text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated' }}">
                        <i class="ri-shield-keyhole-line text-sm {{ Route::is('roles.*') ? 'text-brand-500' : 'text-light-muted dark:text-dark-muted' }}"></i>
                        <span>Roles</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
</aside>
