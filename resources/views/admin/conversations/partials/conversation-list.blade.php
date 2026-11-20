@foreach ($conversations as $conversation)
    <a href="{{ route('conversations.show', $conversation) }}"
        class="conversation-card flex items-center gap-2.5 px-2.5 py-2 mx-1.5 my-1 rounded-md transition-colors min-w-0 overflow-hidden {{ request()->routeIs('conversations.show') && request()->route('conversation')->id == $conversation->id ? 'active' : '' }}">

        <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center font-bold text-xs bg-brand-500 text-white overflow-hidden">
            @if ($conversation->customer_avatar)
                <img src="{{ $conversation->customer_avatar }}"
                    alt="{{ $conversation->customer_name ?? 'Unknown User' }}"
                    class="w-full h-full object-cover">
            @else
                {{ strtoupper(substr($conversation->customer_name ?? 'U', 0, 1)) }}
            @endif
        </div>

        <div class="conversation-content flex-1 min-w-0 overflow-hidden">
            <div class="conversation-top flex items-center justify-between gap-1 min-w-0">
                <h6 class="truncate text-xs font-semibold text-light-text dark:text-dark-text min-w-0 mb-0">
                    {{ $conversation->customer_name ?? 'Unknown User' }}
                </h6>
                <small class="shrink-0 text-2xs text-light-muted dark:text-dark-muted whitespace-nowrap">
                    {{ optional($conversation->last_message_at)->diffForHumans() }}
                </small>
            </div>

            <p class="truncate text-xs text-light-muted dark:text-dark-muted block w-full my-0.5">
                @php
                    $lastMsg = $conversation->last_message ?? '';
                    if (is_string($lastMsg) && str_starts_with(trim($lastMsg), '{') && str_ends_with(trim($lastMsg), '}')) {
                        $dec = json_decode(trim($lastMsg), true);
                        if (is_array($dec) && isset($dec['answer'])) {
                            $lastMsg = $dec['answer'];
                        }
                    }
                @endphp
                {{ Str::limit($lastMsg, 35) }}
            </p>

            <div class="conversation-bottom flex items-center justify-between mt-0.5">
                @php
                    $slug = $conversation->channelAccount->channel->slug ?? 'default';
                @endphp
                <span class="channel-badge inline-flex items-center gap-1 text-2xs text-light-muted dark:text-dark-muted">
                    <i class="{{ \App\Support\ChannelIcon::icon($slug) }}" style="color:{{ \App\Support\ChannelIcon::color($slug) }}"></i>
                    <span>{{ ucfirst($slug) }}</span>
                </span>

                @if ($conversation->unread_count)
                    <span class="px-1.5 py-0.5 rounded text-3xs font-bold bg-brand-500 text-white leading-none shrink-0">{{ $conversation->unread_count }}</span>
                @endif
            </div>
        </div>
    </a>
@endforeach

@if ($conversations->hasPages())
    <div class="p-2 border-t border-light-border dark:border-dark-border text-center">
        {{ $conversations->links() }}
    </div>
@endif
