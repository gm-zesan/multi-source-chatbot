<div class="chat-body">

    @foreach($conversation->messages as $message)

        @include(
            'conversations.partials.message',
            compact('message')
        )

    @endforeach

</div>