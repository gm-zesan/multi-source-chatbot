<header class="h-12 border-b border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface sticky top-0 z-40 transition-colors flex-shrink-0">
    <div class="h-full px-3 sm:px-4 flex items-center justify-between gap-3">
        {{-- Left Section --}}
        <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
            <button id="sidebarToggleBtn" type="button" class="w-7 h-7 flex items-center justify-center rounded border border-light-border dark:border-dark-border text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" title="Toggle Sidebar">
                <i class="ri-menu-2-line text-sm"></i>
            </button>

            <a target="_blank" href="{{ route('home') }}" class="hidden md:inline-flex items-center gap-1 px-2 py-1 rounded text-xs text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" title="Visit Storefront">
                <i class="ri-external-link-line text-xs"></i>
                <span>Storefront</span>
            </a>

            <a href="{{ route('cache-clear') }}" class="hidden lg:inline-flex items-center gap-1 px-2 py-1 rounded text-xs text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" title="Flush Application Cache">
                <i class="ri-sparkling-2-line text-xs"></i>
                <span>Clear Cache</span>
            </a>
        </div>

        {{-- Center Section: Compact Omnisearch Trigger for Command Palette --}}
        <div class="hidden md:flex flex-1 max-w-sm mx-4">
            <button type="button" class="w-full h-7 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas px-2.5 flex items-center justify-between text-xs text-light-muted dark:text-dark-muted hover:border-brand-500 hover:text-light-text dark:hover:text-dark-text transition-all cursor-pointer" onclick="openCommandPalette()" title="Press ⌘K or Ctrl+K to open Command Palette">
                <div class="flex items-center gap-2">
                    <i class="ri-search-2-line text-xs"></i>
                    <span class="text-xs truncate">Search commands, pages...</span>
                </div>
                <div class="flex items-center gap-0.5">
                    <kbd class="px-1 text-2xs font-mono font-medium rounded border border-light-border dark:border-dark-border">⌘</kbd>
                    <kbd class="px-1 text-2xs font-mono font-medium rounded border border-light-border dark:border-dark-border">K</kbd>
                </div>
            </button>
        </div>

        {{-- Right Section: Theme Switcher & Profile --}}
        <div class="flex items-center gap-2 flex-shrink-0">
            {{-- Dark / Light Theme Toggle Button --}}
            <button class="w-7 h-7 flex items-center justify-center rounded border border-light-border dark:border-dark-border text-light-text-secondary dark:text-dark-text-secondary hover:text-amber-400 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" id="themeToggleBtn" type="button" onclick="toggleAdminTheme()" title="Toggle Dark / Light Theme" aria-label="Toggle Theme">
                <i class="ri-sun-line text-xs text-amber-400" id="themeToggleIcon"></i>
            </button>

            {{-- User Profile Dropdown --}}
            <div class="relative">
                <button class="profile-toggle flex items-center gap-1.5 p-1 rounded border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Profile menu">
                    <div class="w-6 h-6 rounded-full bg-brand-500/15 text-brand-400 flex items-center justify-center font-bold text-2xs overflow-hidden flex-shrink-0">
                        @if(Auth::user()->avatar_url)
                            <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" class="w-full h-full object-cover rounded-full" referrerpolicy="no-referrer">
                        @elseif(Auth::user()->image)
                            <img src="{{ asset('storage/' . Auth::user()->image) }}" alt="{{ Auth::user()->name }}" class="w-full h-full object-cover rounded-full" referrerpolicy="no-referrer">
                        @else
                            <i class="ri-user-3-line"></i>
                        @endif
                    </div>
                    <span class="text-xs font-medium text-light-text dark:text-dark-text max-w-[100px] truncate hidden sm:inline">{{ Auth::user()->name }}</span>
                    <i class="ri-arrow-down-s-line text-xs text-light-muted dark:text-dark-muted"></i>
                </button>

                {{-- Dropdown Menu (Hidden by default) --}}
                <div class="dropdown-menu hidden absolute right-0 mt-1.5 w-56 rounded-md border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-dropdown py-1 z-50">
                    <div class="px-3 py-2 border-b border-light-border dark:border-dark-border">
                        <div class="font-semibold text-xs text-light-text dark:text-dark-text truncate">{{ Auth::user()->name }}</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted truncate">{{ Auth::user()->email }}</div>
                        <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-2xs font-semibold bg-brand-500/15 text-brand-400 uppercase">
                            {{ Auth::user()->roles->first()?->name ?? 'User' }}
                        </span>
                    </div>

                    <div class="py-1">
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                            <i class="ri-user-settings-line text-light-muted dark:text-dark-muted"></i>
                            <span>Update Profile</span>
                        </a>

                        <a href="{{ route('password-change.profile') }}" class="flex items-center gap-2 px-3 py-1.5 text-xs text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text hover:bg-light-elevated dark:hover:bg-dark-elevated transition-colors">
                            <i class="ri-lock-password-line text-light-muted dark:text-dark-muted"></i>
                            <span>Change Password</span>
                        </a>
                    </div>

                    <div class="border-t border-light-border dark:border-dark-border my-1"></div>

                    <div class="p-1">
                        <form method="POST" action="{{ route('logout') }}" id="logout-form">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs text-red-500 hover:bg-red-500/10 rounded transition-colors text-left">
                                <i class="ri-logout-box-r-line"></i>
                                <span>Log Out</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
