<header>
    <div id="top-navbar" class="container-fluid">
        <div class="left-header-content">
            <div>
                <i class="ri-menu-2-line" id="btn" style="font-size: 22px;"></i>
            </div>
            <a target="_blank" href="{{ route('home') }}" class="website-visit">
                <i class="ri-global-line"></i>
            </a>
            <a href="{{ route('cache-clear') }}" class="clear-cache"><i class="ri-hard-drive-3-line"></i> Clear Cache</a>
        </div>
        
        <div class="header-profile-wrapper">
            <button class="profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Profile menu">
                <div class="profile-avatar">
                    @if(Auth::user()->avatar_url)
                        <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" class="avatar-img" referrerpolicy="no-referrer" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                    @elseif(Auth::user()->image)
                        <img src="{{ asset('storage/' . Auth::user()->image) }}" alt="{{ Auth::user()->name }}" class="avatar-img" referrerpolicy="no-referrer" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                    @else
                        <i class="ri-user-3-line"></i>
                    @endif
                </div>
                <div class="profile-info">
                    <span class="profile-name">{{ Auth::user()->name }}</span>
                    <span class="profile-role">{{ Auth::user()->roles->first()?->name ?? 'User' }}</span>
                </div>
            </button>

            <div class="dropdown-menu profile-dropdown">
                <div class="dropdown-header-content">
                    <div class="dropdown-user-card">
                        <div class="dropdown-user-avatar">
                            @if(Auth::user()->avatar_url)
                                <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}" referrerpolicy="no-referrer" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                            @elseif(Auth::user()->image)
                                <img src="{{ asset('storage/' . Auth::user()->image) }}" alt="{{ Auth::user()->name }}" referrerpolicy="no-referrer" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                            @else
                                <i class="ri-user-3-line"></i>
                            @endif
                        </div>
                        <div class="dropdown-user-info">
                            <p class="dropdown-user-name">{{ Auth::user()->name }}</p>
                            <p class="dropdown-user-email">{{ Auth::user()->email }}</p>
                        </div>
                    </div>
                </div>

                <div class="dropdown-divider"></div>

                <div class="dropdown-items-group">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="ri-user-settings-line"></i>
                        <div class="dropdown-item-content">
                            <span class="dropdown-item-text">Update Profile</span>
                            <span class="dropdown-item-description">Edit your account information</span>
                        </div>
                    </a>

                    <a href="{{ route('password-change.profile') }}" class="dropdown-item">
                        <i class="ri-lock-password-line"></i>
                        <div class="dropdown-item-content">
                            <span class="dropdown-item-text">Change Password</span>
                            <span class="dropdown-item-description">Update your password</span>
                        </div>
                    </a>
                </div>

                <div class="dropdown-divider"></div>

                <div class="dropdown-items-group">
                    <form method="POST" action="{{ route('logout') }}" id="logout-form">
                        @csrf
                        <a href="#" class="dropdown-item dropdown-item-logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="ri-logout-box-r-line"></i>
                            <span class="dropdown-item-text">Log Out</span>
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>