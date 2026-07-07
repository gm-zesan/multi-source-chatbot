@extends('admin.app')
@section('title')
    My Profile | Password Change
@endsection

@push('custom-style')
    <style>
        .update_info_title{
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 0;
        }
        .update_info_subtitle{
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 0;
        }
        .content-body .table-card .custom-form .custom-label{
            font-size: 13px;
            margin-bottom: 2px;
        }
        .content-body .table-card .custom-form .custom-input{
            height: 36px;
            font-size: 13px;
        }
        .error-messages{
            color: red;
            font-size: 14px;
            list-style: none;
            padding-left: 0;
        }
        .content-body .table-card .custom-form .submit-button{
            background-color: #000;
        }
        .content-body .table-card .custom-form .submit-button:hover{
            background-color: #6c757d;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid mt-5">
        <div class="row">
            <div class="col-md-6 col-12 mx-auto">
                <div class="p-4 bg-white rounded-3">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
@endsection