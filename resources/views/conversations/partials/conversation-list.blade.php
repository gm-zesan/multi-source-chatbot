@foreach ($conversations as $conversation)
    <a href="{{ route('conversations.show', $conversation) }}"
        class="conversation-card {{ request()->routeIs('conversations.show') && request()->route('conversation')->id == $conversation->id ? 'active' : '' }}">

        <div class="conversation-avatar">

            @if ($conversation->customer_avatar)
                <img src="{{ $conversation->customer_avatar }}"
                    alt="{{ $conversation->customer_name ?? 'Unknown User' }}">
            @else
                {{ strtoupper(substr($conversation->customer_name ?? 'U', 0, 1)) }}
            @endif

        </div>

        <div class="conversation-content">

            <div class="conversation-top">

                <h6>

                    {{ $conversation->customer_name ?? 'Unknown User' }}

                </h6>

                <small>

                    {{ optional($conversation->last_message_at)->diffForHumans() }}

                </small>

            </div>

            <p>

                {{ Str::limit($conversation->last_message, 45) }}

            </p>

            <div class="conversation-bottom">
                @php

                $slug = $conversation->channelAccount->channel->slug;

                @endphp

                <span
                    class="channel-badge">

                <i
                class="{{ \App\Support\ChannelIcon::icon($slug) }}"
                style="color:{{ \App\Support\ChannelIcon::color($slug) }}"></i>

                {{ ucfirst($slug) }}

                </span>

                @if ($conversation->unread_count)
                    <span class="unread-dot"></span>
                @endif

            </div>

        </div>

    </a>
@endforeach

<div class="p-3">

    {{ $conversations->links() }}

</div>
