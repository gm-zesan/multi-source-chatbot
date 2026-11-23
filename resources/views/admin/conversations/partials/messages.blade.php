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

            <div class="message-bubble {{ $message->isBlocked() ? '!border-2 !border-rose-500/70 !bg-rose-50/95 dark:!bg-rose-950/40 text-slate-900 dark:text-slate-100 shadow-sm' : '' }}">

                @php
                    $displayBody = $message->body;
                    $proposedFollowUp = null;
                    if (is_string($displayBody) && str_starts_with(trim($displayBody), '{') && str_ends_with(trim($displayBody), '}')) {
                        $decoded = json_decode(trim($displayBody), true);
                        if (is_array($decoded) && isset($decoded['answer'])) {
                            $displayBody = $decoded['answer'];
                            if (!empty($decoded['proposed_follow_up'])) {
                                $proposedFollowUp = $decoded['proposed_follow_up'];
                            }
                        }
                    }
                @endphp

                <div class="message-text">
                    {!! nl2br(e($displayBody)) !!}
                </div>

                @if($message->isBlocked())
                    <div class="mt-2.5 p-2 rounded-lg bg-rose-500/15 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-3xs space-y-1">
                        <div class="flex items-center gap-1.5 font-bold text-rose-800 dark:text-rose-200">
                            <i class="bi bi-shield-slash-fill text-rose-600 dark:text-rose-400 text-xs"></i>
                            <span>মেটা জিরো-চার্জ প্রটেকশন: বার্তাটি ডেলিভারি হয়নি</span>
                        </div>
                        <p class="text-3xs leading-relaxed opacity-95 mb-0 font-medium">
                            {{ $message->getBlockedReason() }}
                        </p>
                    </div>
                @endif

                @if(!empty($proposedFollowUp))
                    <div class="mt-2 pt-1.5 border-t border-white/20 text-xs opacity-95">
                        <i class="ri-lightbulb-line text-amber-300 mr-1"></i> {{ $proposedFollowUp }}
                    </div>
                @endif

                <div class="message-meta">

                    <span>
                        {{ $message->created_at->format('h:i A') }}
                    </span>

                    @if(($message->metadata['source'] ?? '') === 'customer_support_agent' || ($message->metadata['source'] ?? '') === 'faq_engine')
                        <span class="badge bg-primary text-white" style="font-size: 10px; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px;">
                            <i class="bi bi-robot"></i> AI
                        </span>
                        @if(isset($message->metadata['route']))
                            <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 9px; padding: 2px 5px; border-radius: 4px; text-transform: uppercase;">
                                {{ $message->metadata['route'] }}
                            </span>
                        @endif
                        @if(isset($message->metadata['answerability_decision']['status']))
                            @php $gateStatus = strtolower($message->metadata['answerability_decision']['status']); @endphp
                            <span class="badge" style="background: {{ $gateStatus === 'confident' ? '#dcfce7' : ($gateStatus === 'ambiguous' ? '#fef9c3' : '#fee2e2') }}; color: {{ $gateStatus === 'confident' ? '#166534' : ($gateStatus === 'ambiguous' ? '#854d0e' : '#991b1b') }}; font-size: 9px; padding: 2px 5px; border-radius: 4px;">
                                {{ ucfirst($gateStatus) }}
                            </span>
                        @endif
                    @endif

                    @if($message->direction == 'outbound')

                        @if($message->isBlocked())
                            <span class="badge bg-danger text-white" style="font-size: 9px; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px;" title="{{ $message->getBlockedReason() }}">
                                <i class="bi bi-shield-x"></i> Blocked (Zero-Charge)
                            </span>
                        @else
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

                    @endif

                </div>

            </div>

        </div>

    @endforeach

</div>