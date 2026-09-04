@extends('admin.app')

@section('title', $conversation->customer_name)

@push('custom-style')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/inbox.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="inbox-container">
    {{-- Left Sidebar --}}
    <div class="inbox-sidebar">
        @include('admin.conversations.partials.sidebar')
    </div>

    {{-- Right Chat Panel --}}
    <div class="inbox-chat-panel">
        @include('admin.conversations.partials.chat-header')
        @include('admin.conversations.partials.messages')
        <emoji-picker id="emoji-picker"></emoji-picker>
        @include('admin.conversations.partials.composer')
    </div>
</div>
@endsection

@push('scripts')
    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>

    <script>
        const emojiBtn = document.getElementById('emoji-btn');
        const emojiPicker = document.getElementById('emoji-picker');
        const messageInput = document.getElementById('message');

        emojiBtn.addEventListener('click', () => {
            emojiPicker.style.display = emojiPicker.style.display === 'block' ? 'none' : 'block';
        });

        emojiPicker.addEventListener('emoji-click', event => {
            messageInput.value += event.detail.unicode;
            messageInput.dispatchEvent(new Event('input'));
        });

        document.addEventListener('click', (event) => {
            if (
                !emojiPicker.contains(event.target) &&
                !emojiBtn.contains(event.target)
            ) {
                emojiPicker.style.display = 'none';
            }
        });
    </script>

    <script>
        document.querySelectorAll('textarea').forEach(function(el){
            el.addEventListener('input',function(){
                this.style.height='auto';
                this.style.height=this.scrollHeight+'px';
            });
        });
    </script>
@endpush