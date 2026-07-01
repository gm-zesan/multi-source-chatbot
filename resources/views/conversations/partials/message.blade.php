@php

$isOutgoing = $message->direction === 'outbound';

@endphp

<div class="message-row {{ $isOutgoing ? 'outgoing' : 'incoming' }}">

    <div class="message-bubble">

        {{ $message->body }}

    </div>

    <div class="message-time">

        {{ $message->created_at->format('h:i A') }}

    </div>

</div>