@extends('admin.app')
@section('title')
    Edit User
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('users.update', ['user' => $user->id]) }}" method="POST" enctype="multipart/form-data" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-8 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Edit User</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('dashboard') }}">Dashboard</a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="{{ route('users.index') }}">Users</a>
                                        </li>
                                        <li class="breadcrumb-item active" aria-current="page">Edit User</li>
                                    </ol>
                                </nav>
                            </div>
                            <a href="{{ route('users.index') }}" class="add-new">User List<i class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            <div class="mb-3">
                                <label for="name" class="form-label custom-label">Name</label>
                                <input type="text" class="form-control custom-input @error('name') is-invalid @enderror" name="name" id="name" value="{{ old('name', $user->name) }}">
                                @error('name')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label custom-label">Email</label>
                                <input type="email" class="form-control custom-input @error('email') is-invalid @enderror" name="email" id="email" value="{{ old('email', $user->email) }}">
                                @error('email')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="phone_no" class="form-label custom-label">Phone No</label>
                                <input type="number" class="form-control custom-input @error('phone_no') is-invalid @enderror" name="phone_no" id="phone_no" value="{{ old('phone_no', $user->phone_no) }}">
                                @error('phone_no')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label custom-label">Address</label>
                                <textarea name="address" class="form-control custom-input" id="address" cols="30" rows="3">{{ old('address', $user->address) }}</textarea>
                                @error('address')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-12">
                    <div class="card table-card">
                        <div class="card-header table-header">
                            <div class="table-title">Profile Picture</div>
                        </div>
                        <div class="custom-form card-body text-center">
                            <div style="position: relative; display: inline-block;">
                                @if($user->image)
                                    <img id="cover_image_preview" src="{{ asset('storage/' . $user->image) }}" alt="Preview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6; display: inline-block;">
                                    <i id="cover_image_icon" class="ri-user-3-line" style="font-size: 48px; color: #3b82f6; display: none;"></i>
                                @else
                                    <img id="cover_image_preview" src="" alt="Preview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #3b82f6; display: none;">
                                    <i id="cover_image_icon" class="ri-user-3-line" style="font-size: 48px; color: #3b82f6;"></i>
                                @endif
                            </div>
                            <div style="margin-top: 12px;">
                                <input type="file" id="cover_image" name="image" class="d-none" accept="image/*" onchange="handleImageUpload(this)">
                                <label for="cover_image" style="cursor: pointer; display: inline-block; background: #3b82f6; color: white; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; transition: background 0.3s; margin-bottom: 10px;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">Upload Picture</label>
                                @if($user->image)
                                    <button type="button" id="cover_image_remove" class="btn" style="background: #ef4444; color: white; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; margin-bottom: 10px;" onclick="handleImageRemove('cover_image')">Remove Picture</button>
                                @else
                                    <button type="button" id="cover_image_remove" class="btn" style="display: none; background: #ef4444; color: white; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; margin-bottom: 10px;" onclick="handleImageRemove('cover_image')">Remove Picture</button>
                                @endif
                            </div>
                            @error('image')
                                <div class="error_msg" style="font-size: 12px; margin-top: 8px;">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="card table-card mt-3">
                        <div class="card-header table-header">
                            <div class="table-title">Action</div>
                        </div>
                        <div class="custom-form card-body">
                            <div class="row">
                                <div class="col-6">
                                    <button type="submit" class="btn submit-button">Update
                                        <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <a href="{{ route('users.index') }}" class="btn cancel-button">Cancel</a>
                                </div>
                            </div>
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
            const validExtensions = ['gif', 'png', 'jpg', 'jpeg'];

            const previewImg = document.getElementById('cover_image_preview');
            const previewIcon = document.getElementById('cover_image_icon');
            const removeBtn = document.getElementById('cover_image_remove');

            if (validExtensions.includes(ext)) {
                const reader = new FileReader();
                reader.readAsDataURL(fileInput.files[0]);
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'inline-block';
                    previewIcon.style.display = 'none';
                    removeBtn.style.display = 'inline-block';
                };
            } else {
                alert('Select a jpg, jpeg, png or gif type image file.');
                fileInput.value = '';
                previewImg.style.display = 'none';
                previewIcon.style.display = 'inline-block';
                removeBtn.style.display = 'none';
            }
        }

        function handleImageRemove(inputId) {
            const fileInput = document.getElementById(inputId);
            const previewImg = document.getElementById(inputId + '_preview');
            const previewIcon = document.getElementById(inputId + '_icon');
            const removeBtn = document.getElementById(inputId + '_remove');

            fileInput.value = '';
            previewImg.src = '';
            previewImg.style.display = 'none';
            previewIcon.style.display = 'inline-block';
            removeBtn.style.display = 'none';
        }
    </script>
@endpush
