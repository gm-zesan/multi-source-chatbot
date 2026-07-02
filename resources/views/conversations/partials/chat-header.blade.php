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

    {{-- <div class="chat-actions">

        <button>

            <i class="bi bi-search"></i>

        </button>

        <button>

            <i class="bi bi-star"></i>

        </button>

        <button>

            <i class="bi bi-three-dots"></i>

        </button>

    </div> --}}

</div>