@extends('admin.app')
@section('title', 'Edit User')

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Edit User: {{ $user->name }}</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <a href="{{ route('users.index') }}" class="hover:text-text-primary transition-colors">Users</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Edit User</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('users.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-arrow-left-line"></i> Back to Users
            </a>
        </div>
    </div>

    <form action="{{ route('users.update', ['user' => $user->id]) }}" method="POST" enctype="multipart/form-data" autocomplete="off">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Primary User Details --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <div>
                            <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Account Credentials & Profile</h2>
                            <p class="text-2xs text-text-muted">Update contact details, identity and address</p>
                        </div>
                    </div>
                    <div class="app-card-body space-y-3.5">
                        <div>
                            <label for="name" class="block text-xs font-medium text-text-secondary mb-1">Full Name <span class="text-rose-500">*</span></label>
                            <input type="text" class="app-input w-full @error('name') border-rose-500 @enderror" name="name" id="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="email" class="block text-xs font-medium text-text-secondary mb-1">Email Address <span class="text-rose-500">*</span></label>
                                <input type="email" class="app-input w-full @error('email') border-rose-500 @enderror" name="email" id="email" value="{{ old('email', $user->email) }}" required>
                                @error('email')
                                    <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="phone_no" class="block text-xs font-medium text-text-secondary mb-1">Phone Number</label>
                                <input type="text" class="app-input w-full @error('phone_no') border-rose-500 @enderror" name="phone_no" id="phone_no" value="{{ old('phone_no', $user->phone_no) }}">
                                @error('phone_no')
                                    <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="address" class="block text-xs font-medium text-text-secondary mb-1">Physical Address</label>
                            <textarea name="address" class="app-textarea w-full text-xs rounded-md border border-border bg-surface text-text-primary p-2.5 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-all" id="address" rows="3">{{ old('address', $user->address) }}</textarea>
                            @error('address')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Sidebar: Profile Picture & Actions --}}
            <div class="space-y-4">
                {{-- Profile Picture Card --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Avatar & Photo</h2>
                    </div>
                    <div class="app-card-body text-center space-y-3">
                        <div class="flex justify-center">
                            <div class="relative w-24 h-24 rounded-full border-2 border-dashed border-border flex items-center justify-center bg-surface-elevated overflow-hidden group">
                                @if($user->image)
                                    <img id="cover_image_preview" src="{{ asset('storage/' . $user->image) }}" alt="Preview" class="w-full h-full object-cover">
                                    <i id="cover_image_icon" class="ri-user-3-line text-3xl text-text-muted hidden"></i>
                                @else
                                    <img id="cover_image_preview" src="" alt="Preview" class="w-full h-full object-cover hidden">
                                    <i id="cover_image_icon" class="ri-user-3-line text-3xl text-text-muted"></i>
                                @endif
                            </div>
                        </div>
                        <div>
                            <input type="file" id="cover_image" name="image" class="hidden" accept="image/*" onchange="handleImageUpload(this)">
                            <div class="flex items-center justify-center gap-2">
                                <label for="cover_image" class="app-btn app-btn-secondary cursor-pointer">
                                    <i class="ri-upload-2-line"></i> Change Photo
                                </label>
                                <button type="button" id="cover_image_remove" class="app-btn text-rose-500 border border-rose-500/30 hover:bg-rose-500/10 {{ $user->image ? '' : 'hidden' }}" onclick="handleImageRemove('cover_image')">
                                    <i class="ri-delete-bin-line"></i> Remove
                                </button>
                            </div>
                        </div>
                        @error('image')
                            <p class="text-rose-500 text-2xs">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Action Card --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Update Account</h2>
                    </div>
                    <div class="app-card-body space-y-2">
                        <button type="submit" class="app-btn app-btn-primary w-full justify-center">
                            <i class="ri-check-line"></i> Update Changes
                        </button>
                        <a href="{{ route('users.index') }}" class="app-btn app-btn-secondary w-full justify-center">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('custom-scripts')
<script>
    function handleImageUpload(element) {
        const fileInput = element;
        const filePath = fileInput.value;
        const ext = filePath.substring(filePath.lastIndexOf('.') + 1).toLowerCase();
        const validExtensions = ['gif', 'png', 'jpg', 'jpeg', 'webp'];

        const previewImg = document.getElementById('cover_image_preview');
        const previewIcon = document.getElementById('cover_image_icon');
        const removeBtn = document.getElementById('cover_image_remove');

        if (validExtensions.includes(ext) && fileInput.files && fileInput.files[0]) {
            const reader = new FileReader();
            reader.readAsDataURL(fileInput.files[0]);
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewImg.classList.remove('hidden');
                previewIcon.classList.add('hidden');
                removeBtn.classList.remove('hidden');
            };
        }
    }

    function handleImageRemove(inputId) {
        const input = document.getElementById(inputId);
        if (input) input.value = '';
        const previewImg = document.getElementById('cover_image_preview');
        const previewIcon = document.getElementById('cover_image_icon');
        const removeBtn = document.getElementById('cover_image_remove');
        if (previewImg) previewImg.classList.add('hidden');
        if (previewIcon) previewIcon.classList.remove('hidden');
        if (removeBtn) removeBtn.classList.add('hidden');
    }
</script>
@endpush
