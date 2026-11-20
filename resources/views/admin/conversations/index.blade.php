@extends('admin.app')

@section('title','Inbox')

@push('custom-style')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/inbox.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="inbox-container w-full h-full overflow-hidden">
    <div class="inbox-sidebar">
        @include('admin.conversations.partials.sidebar')
    </div>

    <div class="inbox-empty flex-1 flex items-center justify-center">
        <div class="empty-state text-center p-6">
            <i class="ri-chat-smile-2-line text-5xl text-light-muted dark:text-dark-muted mb-2 block"></i>
            <h5 class="text-sm font-semibold text-light-text dark:text-dark-text mb-1">Select a conversation</h5>
            <p class="text-xs text-light-muted dark:text-dark-muted">Choose from the list on the left to start viewing customer inquiries</p>
        </div>
    </div>
</div>
@endsection
