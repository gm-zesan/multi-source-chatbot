@foreach($conversations as $conversation)

    @include(
        'conversations.partials.conversation-card',
        compact('conversation')
    )

@endforeach

<div class="p-3">

    {{ $conversations->links() }}

</div>