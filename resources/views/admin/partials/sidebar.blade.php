<div class="sidebar sidebar-navigation active">
    <div class="logo_content">
        <a href="{{ route('dashboard') }}" class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="logo" class="logo_img" style="width: 50px"/>
            <div class="logo_name">
                {{-- <img src="{{ asset('images/logo_name_white.png') }}" alt="logo" class="logo_name_img"> --}}
            </div>
        </a>
    </div>
    <ul class="nav_list ps-0 scrollbar">
        <li class="category-li">
            <span class="link_names">Main</span>
        </li>
        <li>
            <a href="{{ route('dashboard') }}" class="{{ Route::is('dashboard') ? ' active-focus' : '' }}">
                <i class="ri-home-4-line"></i>
                <span class="link_names">Dashboard</span>
            </a>
        </li>

        {{-- link neeed for conversations/inbox --}}
        <li>
            <a href="{{ route('conversations.index') }}"
                class="{{ Route::is('conversations.index') ? 'active-focus' : '' }}">
                <i class="ri-message-3-line"></i>
                <span class="link_names">Conversations</span>
            </a>
        </li>


        <li class="category-li">
            <span class="link_names">Users</span>
        </li>
        <li class="drop-item">
            <a href="{{ route('users.index') }}"
                class="{{ in_array(Route::currentRouteName(), ['users.index', 'users.create', 'users.edit']) ? 'active-focus' : '' }}">
                <i class="ri-user-3-line"></i>
                <span class="link_names">User List</span>
            </a>
        </li>
        <li class="drop-item">
            <a href="{{ route('roles.index') }}"
                class="{{ in_array(Route::currentRouteName(), ['roles.index', 'roles.create', 'roles.edit']) ? 'active-focus' : '' }}">
                <i class="ri-shield-user-line"></i>
                <span class="link_names">Role</span>
            </a>
        </li>


        <li class="drop-item">
            <a href="{{ route('assign-roles.index') }}"
                class="{{ Route::is('assign-roles.index') ? 'active-focus' : '' }}">
                <i class="ri-user-settings-line"></i>
                <span class="link_names">Assign Role</span>
            </a>
            <span class="tooltip">Assign Role</span>
        </li>

    </ul>

    <div class="profile_content">
        <div class="profile">
            <div class="profile_details">

                @if (Auth::user()->image)
                    <img id="sidebarImageDB" src="{{ asset(Auth::user()->image) }}" alt="img" width="30"
                        height="30" class="rounded-circle">
                @else
                    <i class="ri-user-3-line profile-icon"></i>
                @endif

                <div class="name_job">
                    <div class="name">{{ Auth::user()->name }}</div>
                    <div class="job">{{ Auth::user()->designation }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <a href="{{ route('logout') }}" class="d-flex"
                    onclick="event.preventDefault(); this.closest('form').submit();">
                    <i class="ri-logout-box-r-line" id="log_out"></i>
                </a>
            </form>
        </div>
    </div>
</div>
