<a href="{{ route('conversations.show',$conversation) }}"
   class="conversation-card">

    <div class="d-flex">

        <div class="avatar">

            {{ strtoupper(substr($conversation->customer_name ?? 'U',0,1)) }}

        </div>

        <div class="flex-grow-1 ms-3">

            <div class="d-flex justify-content-between">

                <strong>

                    {{ $conversation->customer_name ?? 'Unknown User' }}

                </strong>

                <small>

                    {{ optional($conversation->last_message_at)->diffForHumans() }}

                </small>

            </div>

            <div class="text-muted small">

                {{ \Illuminate\Support\Str::limit($conversation->last_message,45) }}

            </div>

            <div class="mt-2">

                <span class="badge bg-primary">

                    {{ ucfirst($conversation->channelAccount->channel->name) }}

                </span>

            </div>

        </div>

    </div>

</a>