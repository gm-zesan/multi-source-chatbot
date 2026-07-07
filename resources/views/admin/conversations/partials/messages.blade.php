@php
    $currentDate = null;
    $dividerShown = false;
@endphp

<div class="messages-wrapper">

    @foreach($conversation->messages as $message)

        @php
            $messageDate = $message->created_at->format('Y-m-d');
        @endphp

        {{-- Date Divider --}}
        @if($currentDate !== $messageDate)

            @php
                $currentDate = $messageDate;
            @endphp

            <div class="date-divider">

                <span>

                    @if($message->created_at->isToday())

                        Today

                    @elseif($message->created_at->isYesterday())

                        Yesterday

                    @else

                        {{ $message->created_at->format('d M Y') }}

                    @endif

                </span>

            </div>

        @endif


        {{-- Unread Divider --}}
        @if(
            !$dividerShown &&
            $conversation->unread_count > 0 &&
            $loop->remaining == $conversation->unread_count
        )

            <div class="unread-divider">

                <span>New Messages</span>

            </div>

            @php
                $dividerShown = true;
            @endphp

        @endif


        <div class="message-row {{ $message->direction }}">

            <div class="message-bubble">

                {{ $message->body }}

                <div class="message-meta">

                    <span>

                        {{ $message->created_at->format('h:i A') }}

                    </span>

                    @if($message->direction == 'outbound')

                        @switch($message->status)

                            @case('sending')
                                <i class="bi bi-clock"></i>
                                @break

                            @case('sent')
                                <i class="bi bi-check"></i>
                                @break

                            @case('delivered')
                                <i class="bi bi-check2-all"></i>
                                @break

                            @case('seen')
                                <i class="bi bi-check2-all text-primary"></i>
                                @break

                            @default
                                <i class="bi bi-x-circle text-danger"></i>

                        @endswitch

                    @endif

                </div>

            </div>

        </div>

    @endforeach

</div>