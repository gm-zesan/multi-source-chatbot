@extends('admin.app')

@section('title','Inbox')

@push('custom-style')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/inbox.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="inbox-container">
    <div class="inbox-sidebar">
        @include('admin.conversations.partials.sidebar')
    </div>

    <div class="inbox-empty">
        <div class="empty-state">
            <i class="bi bi-chat-square-text"></i>
            <h5>Select a conversation</h5>
            <p>Choose from the list to start chatting</p>
        </div>
    </div>
</div>
@endsection