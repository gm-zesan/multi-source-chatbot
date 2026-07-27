<div class="sidebar sidebar-navigation active">
    <!-- Logo Section -->
    {{-- <div class="logo_content">
        <a href="{{ route('dashboard') }}" class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="logo" class="logo_img" />
            <div class="logo_name">
                <span class="logo-text">Admin</span>
            </div>
        </a>
    </div> --}}

    <!-- Navigation Menu -->
    <nav class="sidebar-menu">
        <ul class="nav_list ps-0 scrollbar">
            <!-- Main Section -->
            <li class="nav-section">
                <span class="nav-section-title">Main</span>
            </li>
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ Route::is('dashboard') ? 'active' : '' }}">
                    <i class="ri-home-4-line nav-icon"></i>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>

            <!-- Conversations Section -->
            <li class="nav-item">
                <a href="{{ route('conversations.index') }}" class="nav-link {{ Route::is('conversations.index') ? 'active' : '' }}">
                    <i class="ri-message-3-line nav-icon"></i>
                    <span class="nav-label">Conversations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('simulator.index') }}" class="nav-link {{ Route::is('simulator.index') ? 'active' : '' }}">
                    <i class="ri-robot-2-line nav-icon"></i>
                    <span class="nav-label">Chat Simulator</span>
                </a>
            </li>

            <!-- Knowledge Base Section -->
            <li class="nav-section">
                <span class="nav-section-title">Knowledge Base</span>
            </li>
            <li class="nav-item">
                <a href="{{ route('faq-categories.index') }}" class="nav-link {{ in_array(Route::currentRouteName(), ['faq-categories.index', 'faq-categories.create', 'faq-categories.edit']) ? 'active' : '' }}">
                    <i class="ri-folders-line nav-icon"></i>
                    <span class="nav-label">FAQ Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('faqs.index') }}" class="nav-link {{ in_array(Route::currentRouteName(), ['faqs.index', 'faqs.create', 'faqs.edit']) ? 'active' : '' }}">
                    <i class="ri-question-answer-line nav-icon"></i>
                    <span class="nav-label">FAQs</span>
                </a>
            </li>

            <!-- Users & Roles Section -->
            <li class="nav-section">
                <span class="nav-section-title">Users & Access</span>
            </li>
            <li class="nav-item">
                <a href="{{ route('users.index') }}" class="nav-link {{ in_array(Route::currentRouteName(), ['users.index', 'users.create', 'users.edit']) ? 'active' : '' }}">
                    <i class="ri-user-3-line nav-icon"></i>
                    <span class="nav-label">Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('roles.index') }}" class="nav-link {{ in_array(Route::currentRouteName(), ['roles.index', 'roles.create', 'roles.edit']) ? 'active' : '' }}">
                    <i class="ri-shield-user-line nav-icon"></i>
                    <span class="nav-label">Roles</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('assign-roles.index') }}" class="nav-link {{ Route::is('assign-roles.index') ? 'active' : '' }}">
                    <i class="ri-user-settings-line nav-icon"></i>
                    <span class="nav-label">Assign Roles</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Profile Section -->
    {{-- <div class="sidebar-profile">
        <div class="profile-card">
            <div class="profile-avatar">
                @if(Auth::user()->image)
                    <img id="sidebarImageDB" src="{{ asset('storage/' . Auth::user()->image) }}" alt="{{ Auth::user()->name }}" class="avatar-img">
                @else
                    <i class="ri-user-3-line"></i>
                @endif
            </div>
            <div class="profile-info">
                <p class="profile-name">{{ Auth::user()->name }}</p>
                <p class="profile-role">{{ Auth::user()->designation }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                @csrf
                <button type="submit" class="logout-btn" title="Logout">
                    <i class="ri-logout-box-r-line"></i>
                </button>
            </form>
        </div>
    </div> --}}
</div>
