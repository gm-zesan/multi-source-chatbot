@extends('admin.app')
@section('title')
    Role
@endsection
@section('content')
    <div class="container-fluid my-3">
        <form  action="{{ route('roles.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-8 col-12">
                    <div class="card table-card mb-4">
                        <div class="card-header table-header">
                            <div class="title-with-breadcrumb">
                                <div class="table-title">Page</div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item">
                                            <a href="{{route('dashboard')}}">Dashboard</a>
                                        </li> 
                                        <li class="breadcrumb-item">
                                            <a href="{{route('roles.index')}}">Role</a>
                                        </li> 
                                        <li class="breadcrumb-item active" aria-current="page"> Create Role</li> 
                                    </ol> 
                                </nav>
                            </div>
                            <a href="{{route('roles.index')}}" class="add-new">Role<i class="ms-1 ri-list-ordered-2"></i></a>
                        </div>
                        <div class="card-body custom-form">
                            
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-md-12">
                                        <label class="form-label custom-label">Role Name</label>
                                        <input type="text" class="form-control custom-input" name="name" placeholder="Role name">
                                        @if($errors->has('name'))
                                            <div class="error_msg">
                                                {{ $errors->first('name') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="" class="form-label custom-label">Role Description</label>
                                <textarea class="form-control custom-input" name="description" rows="5"  placeholder="Role Description"  style="resize: none; height: auto"></textarea>
                                @if($errors->has('description'))
                                    <div class="error_msg">
                                        {{ $errors->first('description') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="card table-card">
                        <div class="table-header">
                            <div class="table-title">Permission</div>
                        </div>
                        <div class="card-body custom-form">
                            <div class="form-group">
                                @foreach ($modules as $module)
                                    <div class="form-check-wrapper mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="module" id="{{ $module->module }}" class="form-check-input custom-checkbox" onclick="checkRoleModule(this)">
                                            <label for="{{ $module->module }}" class="form-check-label custom-checkbox-label">
                                                {{ $module->module }}
                                            </label>
                                        </div>
                                    @foreach ($permission as $value)
                                        @if($module->module == $value->module)
                                        <div class="form-check d-inline-block mb-2 inner-form-check">
                                            <input type="checkbox" name="permission[]" class="form-check-input custom-checkbox inner-checkbox" id="check-{{ $value->id }}" value="{{ $value->id }}"  data-module="{{ $module->module }}"onclick="innerCheckboxUncheck(this)">
                                            <label class="form-check-label custom-checkbox-label" for="check-{{ $value->id }}">{{ $value->display_name }}</label>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                                @endforeach
                                
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 col-12">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="card table-card">
                                <div class="table-header">
                                    <div class="table-title">Action</div>
                                </div>
                                <div class="custom-form card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <button type="submit" class="btn submit-button">Save
                                                <span class="ms-1 spinner-border spinner-border-sm d-none" role="status">
                                                </span>
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <a href="{{route('roles.index')}}" class="btn leave-button">Leave</a>
                                        </div>
                                    </div>
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
        $('.submit-button').click(function(){
            $(this).css('opacity', '1');
            $(this).find('.spinner-border').removeClass('d-none');
            $(this).attr('disabled', true);
            $(this).closest('form').submit();
        });
    </script>

    <script>
        function checkRoleModule(moduleCheckbox) {
            var moduleId = moduleCheckbox.id;
            var isChecked = moduleCheckbox.checked;

            var innerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
            innerCheckboxes.forEach(function(innerCheckbox) {
                innerCheckbox.checked = isChecked;
            });
        }

        function innerCheckboxUncheck(innerCheckbox) {
            var moduleId = innerCheckbox.getAttribute('data-module');
            var moduleCheckbox = document.getElementById(moduleId);

            var allInnerCheckboxes = document.querySelectorAll('[data-module="' + moduleId + '"]');
            var allChecked = true;
            allInnerCheckboxes.forEach(function(checkbox) {
                if (!checkbox.checked) {
                    allChecked = false;
                }
            });

            moduleCheckbox.checked = allChecked;
        }
    </script>

@endpush
