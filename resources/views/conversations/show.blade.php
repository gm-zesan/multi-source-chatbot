@extends('layouts.inbox')

@section('title', $conversation->customer_name)

@section('content')

<div class="row g-0 vh-100">

    {{-- Left Sidebar --}}
    <div class="col-lg-4">

        @include('conversations.partials.sidebar')

    </div>

    {{-- Right Chat --}}
    <div class="col-lg-8 d-flex flex-column chat-panel">

        @include('conversations.partials.chat-header')

        @include('conversations.partials.messages')

        <emoji-picker id="emoji-picker"></emoji-picker>

        @include('conversations.partials.composer')

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
            messageInput.dispatchEvent(new Event('input')); // Trigger input event to adjust height
        });

        // Hide emoji picker when clicking outside
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