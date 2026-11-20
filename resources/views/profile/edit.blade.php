@extends('admin.app')

@section('title', 'Profile Settings')

@section('content')
<div class="space-y-4">
    <!-- Header / Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-light-border dark:border-dark-border">
        <div>
            <h1 class="text-base font-semibold text-light-text dark:text-dark-text tracking-tight">Account Profile</h1>
            <p class="text-xs text-light-muted dark:text-dark-muted">Manage your personal credentials, contact information, and security settings</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('password-change.profile') }}" class="app-btn app-btn-secondary">
                <i class="ri-lock-password-line text-sm"></i>
                <span>Change Password</span>
            </a>
            <a href="{{ route('dashboard') }}" class="app-btn app-btn-outline">
                <i class="ri-arrow-left-line text-sm"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if (session('status') === 'profile-updated' || session('success'))
        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-md text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
            <i class="ri-checkbox-circle-line text-sm"></i>
            <span>Profile information has been updated successfully.</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Left Column: Profile Form -->
        <div class="lg:col-span-2 space-y-4">
            <div class="app-card">
                <div class="app-card-header">
                    <div class="flex items-center gap-2">
                        <i class="ri-user-settings-line text-brand-500 text-sm"></i>
                        <span class="text-xs font-semibold text-light-text dark:text-dark-text">Personal Details</span>
                    </div>
                </div>
                <div class="app-card-body">
                    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" id="profileForm" class="space-y-4">
                        @csrf
                        @method('patch')

                        <input type="hidden" name="remove_avatar" id="remove_avatar" value="0">

                        <!-- Avatar Upload Section -->
                        <div class="flex items-center gap-4 pb-4 border-b border-light-border dark:border-dark-border">
                            <div class="relative shrink-0">
                                <img 
                                    id="avatar_preview" 
                                    src="{{ $user->avatar_url ?: '' }}" 
                                    alt="{{ $user->name }}" 
                                    referrerpolicy="no-referrer"
                                    onerror="this.style.display='none'; document.getElementById('avatar_fallback').style.display='inline-flex';"
                                    class="w-16 h-16 rounded-full object-cover border-2 border-brand-500 {{ $user->avatar_url ? 'block' : 'hidden' }}"
                                >
                                <div 
                                    id="avatar_fallback" 
                                    class="w-16 h-16 rounded-full bg-brand-500/10 text-brand-600 dark:text-brand-400 border-2 border-brand-500/30 {{ $user->avatar_url ? 'hidden' : 'inline-flex' }} items-center justify-center text-2xl font-bold"
                                >
                                    <i class="ri-user-3-line"></i>
                                </div>
                            </div>
                            <div class="space-y-1.5 flex-1">
                                <label class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted block">Profile Picture</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input 
                                        type="file" 
                                        id="avatar_input" 
                                        name="avatar" 
                                        class="hidden" 
                                        accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                                        onchange="handleAvatarSelect(this)"
                                    >
                                    <button 
                                        type="button" 
                                        class="app-btn app-btn-secondary !h-7 !text-xs"
                                        onclick="document.getElementById('avatar_input').click()"
                                    >
                                        <i class="ri-upload-2-line text-xs"></i> Upload New
                                    </button>
                                    <button 
                                        type="button" 
                                        id="avatar_remove_btn" 
                                        class="app-btn app-btn-outline !h-7 !text-xs !border-red-500/30 text-red-600 dark:text-red-400 hover:bg-red-500/10 {{ $user->avatar_url ? 'inline-flex' : 'hidden' }}"
                                        onclick="handleAvatarRemove()"
                                    >
                                        <i class="ri-delete-bin-line text-xs"></i> Remove
                                    </button>
                                </div>
                                <span class="text-2xs text-light-muted dark:text-dark-muted block">JPG, PNG, GIF or WebP. Max file size: 2MB.</span>
                                @error('avatar')
                                    <div class="text-2xs text-red-500 mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Form Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label for="name" class="text-2xs font-semibold text-light-text dark:text-dark-text">Full Name <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    class="app-input w-full @error('name') border-red-500 @enderror" 
                                    name="name" 
                                    id="name" 
                                    value="{{ old('name', $user->name) }}" 
                                    required 
                                    autocomplete="name"
                                    placeholder="Enter your full name"
                                >
                                @error('name')
                                    <div class="text-2xs text-red-500">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="space-y-1">
                                <label for="email" class="text-2xs font-semibold text-light-text dark:text-dark-text">Email Address <span class="text-red-500">*</span></label>
                                <input 
                                    type="email" 
                                    class="app-input w-full @error('email') border-red-500 @enderror" 
                                    name="email" 
                                    id="email" 
                                    value="{{ old('email', $user->email) }}" 
                                    required 
                                    autocomplete="username"
                                    placeholder="name@company.com"
                                >
                                @error('email')
                                    <div class="text-2xs text-red-500">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Phone Number -->
                        <div class="space-y-1">
                            <label for="phone" class="text-2xs font-semibold text-light-text dark:text-dark-text">Phone Number</label>
                            <input 
                                type="tel" 
                                class="app-input w-full @error('phone') border-red-500 @enderror" 
                                name="phone" 
                                id="phone" 
                                value="{{ old('phone', $user->phone) }}" 
                                autocomplete="tel"
                                placeholder="+1 (555) 000-0000"
                            >
                            @error('phone')
                                <div class="text-2xs text-red-500">{{ $message }}</div>
                            @enderror
                        </div>

                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                            <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-md text-xs flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                                    <i class="ri-alert-line text-sm"></i>
                                    <span>Your email address is unverified.</span>
                                </div>
                                <button form="send-verification" type="submit" class="text-brand-600 dark:text-brand-400 underline font-medium hover:text-brand-700">
                                    Resend Verification Email
                                </button>
                            </div>

                            @if (session('status') === 'verification-link-sent')
                                <div class="text-2xs text-emerald-600 dark:text-emerald-400">
                                    A new verification link has been sent to your email address.
                                </div>
                            @endif
                        @endif

                        <!-- Submit Buttons -->
                        <div class="pt-3 border-t border-light-border dark:border-dark-border flex items-center justify-end gap-2">
                            <a href="{{ route('dashboard') }}" class="app-btn app-btn-secondary">Cancel</a>
                            <button type="submit" class="app-btn app-btn-primary">
                                <i class="ri-save-line text-xs"></i>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </form>

                    <form id="send-verification" method="post" action="{{ route('verification.send') }}" class="hidden">
                        @csrf
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Account Overview & Metadata -->
        <div class="space-y-4">
            <div class="app-card">
                <div class="app-card-header">
                    <div class="flex items-center gap-2">
                        <i class="ri-shield-user-line text-brand-500 text-sm"></i>
                        <span class="text-xs font-semibold text-light-text dark:text-dark-text">Account & Activity</span>
                    </div>
                </div>
                <div class="app-card-body text-center space-y-3">
                    <div class="flex justify-center">
                        @if($user->avatar_url)
                            <img 
                                src="{{ $user->avatar_url }}" 
                                alt="{{ $user->name }}" 
                                referrerpolicy="no-referrer"
                                onerror="this.onerror=null; this.src=''; this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                                class="w-16 h-16 rounded-full object-cover border-2 border-brand-500"
                            >
                            <div class="w-16 h-16 rounded-full bg-brand-500/10 text-brand-600 dark:text-brand-400 border-2 border-brand-500/30 hidden items-center justify-center text-xl font-bold">
                                <i class="ri-user-3-line"></i>
                            </div>
                        @else
                            <div class="w-16 h-16 rounded-full bg-brand-500/10 text-brand-600 dark:text-brand-400 border-2 border-brand-500/30 inline-flex items-center justify-center text-xl font-bold">
                                <i class="ri-user-3-line"></i>
                            </div>
                        @endif
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-light-text dark:text-dark-text">{{ $user->name }}</h4>
                        <p class="text-2xs text-light-muted dark:text-dark-muted">{{ $user->email }}</p>
                    </div>
                    <div>
                        <span class="app-badge app-badge-primary">
                            {{ ucfirst($user->roles->first()?->name ?? 'User') }}
                        </span>
                    </div>

                    <!-- Key Metrics & Audit Info -->
                    <div class="pt-3 border-t border-light-border dark:border-dark-border text-left space-y-2 text-xs">
                        <div class="flex items-center justify-between py-1 border-b border-light-border dark:border-dark-border">
                            <span class="text-light-muted dark:text-dark-muted text-2xs">Account Status</span>
                            <span class="app-badge {{ $user->is_active ? 'app-badge-success' : 'app-badge-danger' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between py-1 border-b border-light-border dark:border-dark-border">
                            <span class="text-light-muted dark:text-dark-muted text-2xs">Email Status</span>
                            @if($user->email_verified_at)
                                <span class="app-badge app-badge-success"><i class="ri-checkbox-circle-line"></i> Verified</span>
                            @else
                                <span class="app-badge app-badge-warning"><i class="ri-time-line"></i> Unverified</span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between py-1 border-b border-light-border dark:border-dark-border">
                            <span class="text-light-muted dark:text-dark-muted text-2xs">Auth Provider</span>
                            <div class="flex items-center gap-1">
                                @if($user->google_id)
                                    <span class="app-badge app-badge-secondary"><i class="ri-google-fill text-red-500"></i> Google</span>
                                @endif
                                @if($user->facebook_id)
                                    <span class="app-badge app-badge-secondary"><i class="ri-facebook-fill text-blue-500"></i> Facebook</span>
                                @endif
                                @if(!$user->google_id && !$user->facebook_id)
                                    <span class="app-badge app-badge-secondary">Standard</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between py-1 border-b border-light-border dark:border-dark-border">
                            <span class="text-light-muted dark:text-dark-muted text-2xs">Member Since</span>
                            <span class="text-2xs text-light-text dark:text-dark-text font-medium">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</span>
                        </div>

                        <div class="flex items-center justify-between py-1 border-b border-light-border dark:border-dark-border">
                            <span class="text-light-muted dark:text-dark-muted text-2xs">Last Login</span>
                            <span class="text-2xs text-light-text dark:text-dark-text font-medium" title="{{ $user->last_login_at ? $user->last_login_at->format('M d, Y h:i A') : '' }}">
                                {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Just now' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between py-1">
                            <span class="text-light-muted dark:text-dark-muted text-2xs">Last Login IP</span>
                            <span class="text-2xs text-light-text dark:text-dark-text font-mono">
                                {{ $user->last_login_ip ?: request()->ip() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
<script>
    function handleAvatarSelect(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];

            if (!validTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, GIF, or WebP).');
                input.value = '';
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                alert('Image file size must not exceed 2MB.');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatar_preview');
                const fallback = document.getElementById('avatar_fallback');
                const removeBtn = document.getElementById('avatar_remove_btn');
                const removeInput = document.getElementById('remove_avatar');

                preview.src = e.target.result;
                preview.classList.remove('hidden');
                preview.classList.add('block');
                fallback.classList.add('hidden');
                fallback.classList.remove('inline-flex');
                removeBtn.classList.remove('hidden');
                removeBtn.classList.add('inline-flex');
                removeInput.value = '0';
            };
            reader.readAsDataURL(file);
        }
    }

    function handleAvatarRemove() {
        const input = document.getElementById('avatar_input');
        const preview = document.getElementById('avatar_preview');
        const fallback = document.getElementById('avatar_fallback');
        const removeBtn = document.getElementById('avatar_remove_btn');
        const removeInput = document.getElementById('remove_avatar');

        input.value = '';
        preview.src = '';
        preview.classList.remove('block');
        preview.classList.add('hidden');
        fallback.classList.remove('hidden');
        fallback.classList.add('inline-flex');
        removeBtn.classList.remove('inline-flex');
        removeBtn.classList.add('hidden');
        removeInput.value = '1';
    }
</script>
@endpush
