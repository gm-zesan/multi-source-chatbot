<div class="chat-header">

    <div class="d-flex align-items-center">

        <div class="avatar">

            {{ strtoupper(substr($conversation->customer_name,0,1)) }}

        </div>

        <div class="ms-3">

            <h5 class="mb-0">

                {{ $conversation->customer_name }}

            </h5>

            <small class="text-muted">

                {{ ucfirst($conversation->channelAccount->channel->name) }}

            </small>

        </div>

    </div>

</div>