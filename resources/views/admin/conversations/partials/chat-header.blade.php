<div class="chat-header">

    <div class="chat-user">

        <div class="chat-avatar">

            @if($conversation->customer_avatar)

                <img
                    src="{{ $conversation->customer_avatar }}"
                    alt="{{ $conversation->customer_name }}">

            @else

                {{ strtoupper(substr($conversation->customer_name ?? 'U',0,1)) }}

            @endif

        </div>

        <div>

            <h5>

                {{ $conversation->customer_name }}

            </h5>

            <div class="chat-meta">

                <span class="channel">

                    {{ ucfirst($conversation->channelAccount->channel->name) }}

                </span>

                <span>•</span>

                <span class="status">

                    {{ ucfirst($conversation->status) }}

                </span>

                <span>•</span>

                <small>

                    {{ $conversation->external_user_id }}

                </small>

            </div>

        </div>

    </div>

    <div class="chat-actions">
        <form action="{{ url('/dashboard/conversations/'.$conversation->id.'/ai-reply') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary" style="display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-weight: 500; font-size: 13px;" title="Generate AI Assistant Response">
                <i class="bi bi-robot"></i>
                <span>Generate AI Reply</span>
            </button>
        </form>
    </div>
</div>