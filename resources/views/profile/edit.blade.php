@extends('admin.app')

@section('title')
    Edit Profile
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <!-- Left Column: Profile Edit Form -->
            <div class="col-lg-8 col-12">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">Profile Information</div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Edit Profile</li>
                                </ol>
                            </nav>
                        </div>
                        <a href="{{ route('password-change.profile') }}" class="add-new">
                            Change Password <i class="ri-lock-password-line ms-1"></i>
                        </a>
                    </div>

                    <div class="card-body custom-form">
                        @if (session('status') === 'profile-updated' || session('success'))
                            <div class="alert alert-success d-flex align-items-center mb-4 py-2 px-3 border-0" role="alert" style="background-color: #ecfdf5; color: #065f46; border-radius: 6px; font-size: 13px;">
                                <i class="ri-checkbox-circle-fill me-2 text-success"></i>
                                <div>Profile information has been updated successfully.</div>
                            </div>
                        @endif

                        <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" id="profileForm">
                            @csrf
                            @method('patch')

                            <input type="hidden" name="remove_avatar" id="remove_avatar" value="0">

                            <!-- Avatar Upload Section -->
                            <div class="mb-4 pb-3 border-bottom">
                                <label class="form-label custom-label mb-2">Profile Avatar</label>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="position-relative flex-shrink-0">
                                        <img 
                                            id="avatar_preview" 
                                            src="{{ $user->avatar_url ?: '' }}" 
                                            alt="{{ $user->name }}" 
                                            referrerpolicy="no-referrer"
                                            onerror="this.style.display='none'; document.getElementById('avatar_fallback').style.display='inline-flex';"
                                            style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6; display: {{ $user->avatar_url ? 'block' : 'none' }};"
                                        >
                                        <div 
                                            id="avatar_fallback" 
                                            style="width: 80px; height: 80px; border-radius: 50%; background: #eff6ff; color: #3b82f6; display: {{ $user->avatar_url ? 'none' : 'inline-flex' }}; align-items: center; justify-content: center; font-size: 32px; border: 2px solid #3b82f6;"
                                        >
                                            <i class="ri-user-3-line"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap gap-2 mb-1">
                                            <input 
                                                type="file" 
                                                id="avatar_input" 
                                                name="avatar" 
                                                class="d-none" 
                                                accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                                                onchange="handleAvatarSelect(this)"
                                            >
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-primary"
                                                onclick="document.getElementById('avatar_input').click()"
                                            >
                                                <i class="ri-upload-2-line me-1"></i> Upload Picture
                                            </button>
                                            <button 
                                                type="button" 
                                                id="avatar_remove_btn" 
                                                class="btn btn-sm btn-outline-danger"
                                                style="display: {{ $user->avatar_url ? 'inline-flex' : 'none' }};"
                                                onclick="handleAvatarRemove()"
                                            >
                                                <i class="ri-delete-bin-line me-1"></i> Remove
                                            </button>
                                        </div>
                                        <span class="text-muted" style="font-size: 12px;">JPG, PNG, GIF or WebP. Max file size: 2MB.</span>
                                        @error('avatar')
                                            <div class="error_msg mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Name & Email Row -->
                            <div class="row">
                                <div class="col-md-6 col-12 mb-3">
                                    <label for="name" class="form-label custom-label">Full Name <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        class="form-control custom-input @error('name') is-invalid @enderror" 
                                        name="name" 
                                        id="name" 
                                        value="{{ old('name', $user->name) }}" 
                                        required 
                                        autocomplete="name"
                                        placeholder="Enter your full name"
                                    >
                                    @error('name')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 col-12 mb-3">
                                    <label for="email" class="form-label custom-label">Email Address <span class="text-danger">*</span></label>
                                    <input 
                                        type="email" 
                                        class="form-control custom-input @error('email') is-invalid @enderror" 
                                        name="email" 
                                        id="email" 
                                        value="{{ old('email', $user->email) }}" 
                                        required 
                                        autocomplete="username"
                                        placeholder="name@company.com"
                                    >
                                    @error('email')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div class="mb-3">
                                <label for="phone" class="form-label custom-label">Phone Number</label>
                                <input 
                                    type="tel" 
                                    class="form-control custom-input @error('phone') is-invalid @enderror" 
                                    name="phone" 
                                    id="phone" 
                                    value="{{ old('phone', $user->phone) }}" 
                                    autocomplete="tel"
                                    placeholder="+1 (555) 000-0000"
                                >
                                @error('phone')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                <div class="mt-2 mb-3 p-3 bg-light rounded text-muted small">
                                    <div class="d-flex align-items-center">
                                        <i class="ri-alert-line text-warning me-2" style="font-size: 16px;"></i>
                                        <div>
                                            Your email address is unverified.
                                            <button form="send-verification" type="submit" class="btn btn-link p-0 text-primary small text-decoration-none ms-1">
                                                Click here to re-send the verification email.
                                            </button>
                                        </div>
                                    </div>

                                    @if (session('status') === 'verification-link-sent')
                                        <div class="mt-2 text-success">
                                            A new verification link has been sent to your email address.
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- Action Buttons -->
                            <div class="form-actions mt-4">
                                <div class="row">
                                    <div class="col-sm-4 col-6">
                                        <button type="submit" class="btn submit-button">
                                            <span>Save Changes</span>
                                            <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                    </div>
                                    <div class="col-sm-4 col-6">
                                        <a href="{{ route('dashboard') }}" class="btn cancel-button">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <form id="send-verification" method="post" action="{{ route('verification.send') }}" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Rich Account & Activity Overview -->
            <div class="col-lg-4 col-12 mt-4 mt-lg-0">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="table-title">Account & Activity</div>
                    </div>
                    <div class="card-body custom-form text-center">
                        <!-- User Head Badge -->
                        <div class="mb-3">
                            @if($user->avatar_url)
                                <img 
                                    src="{{ $user->avatar_url }}" 
                                    alt="{{ $user->name }}" 
                                    referrerpolicy="no-referrer"
                                    onerror="this.onerror=null; this.src=''; this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                                    style="width: 84px; height: 84px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6;"
                                >
                                <div style="width: 84px; height: 84px; border-radius: 50%; background: #eff6ff; color: #3b82f6; display: none; align-items: center; justify-content: center; font-size: 34px; border: 2px solid #3b82f6; margin: 0 auto;">
                                    <i class="ri-user-3-line"></i>
                                </div>
                            @else
                                <div style="width: 84px; height: 84px; border-radius: 50%; background: #eff6ff; color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 34px; border: 2px solid #3b82f6; margin: 0 auto;">
                                    <i class="ri-user-3-line"></i>
                                </div>
                            @endif
                        </div>
                        <h5 class="mb-1 fw-bold text-dark" style="font-size: 15px;">{{ $user->name }}</h5>
                        <p class="text-muted mb-2" style="font-size: 13px;">{{ $user->email }}</p>
                        <div class="mb-3">
                            <span class="badge bg-primary text-white" style="font-size: 11px; padding: 4px 8px;">
                                {{ ucfirst($user->roles->first()?->name ?? 'User') }}
                            </span>
                        </div>

                        <!-- Detailed Information List -->
                        <div class="text-start border-top pt-3">
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Account Status</span>
                                <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Email Status</span>
                                @if($user->email_verified_at)
                                    <span class="badge bg-success"><i class="ri-checkbox-circle-line me-1"></i>Verified</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="ri-time-line me-1"></i>Unverified</span>
                                @endif
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Phone</span>
                                <span class="text-dark fw-medium">{{ $user->phone ?: 'Not Set' }}</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Auth Provider</span>
                                <div>
                                    @if($user->google_id)
                                        <span class="badge bg-light text-dark border"><i class="ri-google-fill text-danger me-1"></i>Google</span>
                                    @endif
                                    @if($user->facebook_id)
                                        <span class="badge bg-light text-dark border ms-1"><i class="ri-facebook-fill text-primary me-1"></i>Facebook</span>
                                    @endif
                                    @if(!$user->google_id && !$user->facebook_id)
                                        <span class="badge bg-light text-secondary border">Email / Password</span>
                                    @endif
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Password Status</span>
                                <span class="badge {{ ($user->password_set ?? true) ? 'bg-primary' : 'bg-warning text-dark' }}">
                                    {{ ($user->password_set ?? true) ? 'Password Set' : 'Not Set (Social)' }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Member Since</span>
                                <span class="text-dark">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Last Login</span>
                                <span class="text-dark" title="{{ $user->last_login_at ? $user->last_login_at->format('M d, Y h:i A') : '' }}">
                                    {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Just now' }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="font-size: 13px;">
                                <span class="text-muted">Last Login IP</span>
                                <span class="text-dark font-monospace" style="font-size: 12px;">
                                    {{ $user->last_login_ip ?: request()->ip() }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2" style="font-size: 13px;">
                                <span class="text-muted">Last Profile Update</span>
                                <span class="text-dark">
                                    {{ $user->updated_at ? $user->updated_at->diffForHumans() : 'N/A' }}
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
                    preview.style.display = 'block';
                    fallback.style.display = 'none';
                    removeBtn.style.display = 'inline-flex';
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
            preview.style.display = 'none';
            fallback.style.display = 'inline-flex';
            removeBtn.style.display = 'none';
            removeInput.value = '1';
        }
    </script>
@endpush