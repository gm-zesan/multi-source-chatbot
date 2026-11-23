<div class="chat-header flex items-center justify-between px-3 sm:px-4 border-b border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface h-14 min-h-[56px] max-h-[56px] gap-2 shrink-0">
    <div class="flex items-center gap-2.5 min-w-0 flex-1">
        <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center font-bold text-xs bg-brand-500/15 text-brand-500 overflow-hidden">
            @if($conversation->customer_avatar)
                <img src="{{ $conversation->customer_avatar }}" alt="{{ $conversation->customer_name }}" class="w-full h-full object-cover rounded-full">
            @else
                {{ strtoupper(substr($conversation->customer_name ?? 'U',0,1)) }}
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5 flex-wrap">
                <h5 class="text-xs font-semibold text-light-text dark:text-dark-text truncate mb-0 max-w-[150px]">{{ $conversation->customer_name ?? 'Anonymous Visitor' }}</h5>
                @php
                    $currentStatus = $conversation->status ?? 'open';
                    $currentPriority = $conversation->priority ?? 'normal';
                    $channelSlug = $conversation->channelAccount?->channel?->slug ?? '';
                    $wsId = (int) ($conversation->channelAccount?->workspace_id ?: 1);
                    $quota = \App\Models\WorkspaceUsageQuota::currentForWorkspace($wsId);
                    $isWaExhausted = ($channelSlug === 'whatsapp') && $quota->isWhatsAppQuotaExhausted();
                    $isFbExpired = ($channelSlug === 'facebook') && optional($conversation->last_message_at)->lt(\Carbon\Carbon::now()->subHours(24));
                @endphp
                <span class="app-badge {{ $currentStatus === 'resolved' ? 'app-badge-success' : ($currentStatus === 'closed' ? 'app-badge-secondary' : 'app-badge-primary') }} !text-2xs shrink-0">
                    {{ ucfirst(str_replace('_', ' ', $currentStatus)) }}
                </span>
                <span class="app-badge {{ $currentPriority === 'urgent' ? 'app-badge-danger' : ($currentPriority === 'high' ? 'app-badge-warning' : 'app-badge-secondary') }} !text-2xs shrink-0">
                    {{ ucfirst($currentPriority) }}
                </span>
                @if($isWaExhausted)
                    <span class="app-badge app-badge-danger !text-2xs shrink-0 flex items-center gap-1 font-mono" title="WhatsApp 1,000 free quota exhausted">
                        <i class="ri-shield-alert-fill"></i> Free Quota Exhausted
                    </span>
                @elseif($isFbExpired)
                    <span class="app-badge app-badge-warning !text-2xs shrink-0 flex items-center gap-1 font-mono" title="Messenger 24-hour free service window expired">
                        <i class="ri-time-line"></i> 24h Window Expired
                    </span>
                @endif
            </div>

            <div class="text-2xs text-light-muted dark:text-dark-muted flex items-center gap-1 mt-0.5">
                <span class="app-badge app-badge-secondary !text-2xs shrink-0">
                    {{ ucfirst($conversation->channelAccount?->channel?->name ?? 'Channel') }}
                </span>
                @if($conversation->assignedTo)
                    <span>•</span>
                    <span class="text-brand-500 flex items-center gap-1 truncate"><i class="ri-user-follow-line"></i>{{ $conversation->assignedTo->name }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="flex items-center gap-1.5 shrink-0">
        {{-- Agent Assignment Selector --}}
        <form action="{{ route('conversations.assign', $conversation->id) }}" method="POST" class="inline-flex items-center">
            @csrf
            <select name="assigned_to_user_id" class="app-select !h-7 !py-0 !px-1.5 !text-2xs !bg-light-canvas dark:!bg-dark-canvas !border-light-border dark:!border-dark-border !text-light-text dark:!text-dark-text max-w-[110px]" onchange="this.form.submit()" title="Assign conversation to agent">
                <option value="">Unassigned</option>
                @if(isset($teamMembers))
                    @foreach($teamMembers as $member)
                        <option value="{{ $member->id }}" {{ $conversation->assigned_to_user_id == $member->id ? 'selected' : '' }}>
                            {{ $member->name }}
                        </option>
                    @endforeach
                @endif
            </select>
        </form>

        {{-- Status & Priority Selector --}}
        <form action="{{ route('conversations.update-status', $conversation->id) }}" method="POST" class="inline-flex items-center gap-1">
            @csrf
            <select name="status" class="app-select !h-7 !py-0 !px-1.5 !text-2xs !bg-light-canvas dark:!bg-dark-canvas !border-light-border dark:!border-dark-border !text-light-text dark:!text-dark-text max-w-[95px]" onchange="this.form.submit()" title="Change Ticket Status">
                <option value="open" {{ $conversation->status === 'open' ? 'selected' : '' }}>Open</option>
                <option value="in_progress" {{ $conversation->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ $conversation->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="closed" {{ $conversation->status === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
            <select name="priority" class="app-select !h-7 !py-0 !px-1.5 !text-2xs !bg-light-canvas dark:!bg-dark-canvas !border-light-border dark:!border-dark-border !text-light-text dark:!text-dark-text max-w-[85px]" onchange="this.form.submit()" title="Change Ticket Priority">
                <option value="low" {{ ($conversation->priority ?? 'normal') === 'low' ? 'selected' : '' }}>Low</option>
                <option value="normal" {{ ($conversation->priority ?? 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="high" {{ ($conversation->priority ?? 'normal') === 'high' ? 'selected' : '' }}>High</option>
                <option value="urgent" {{ ($conversation->priority ?? 'normal') === 'urgent' ? 'selected' : '' }}>Urgent</option>
            </select>
        </form>

        {{-- AI Takeover / Pause Toggle Button --}}
        <form action="{{ route('conversations.toggle-ai', $conversation->id) }}" method="POST" class="inline">
            @csrf
            @if($conversation->isAiPaused())
                <button type="submit" class="app-btn !h-7 !px-2.5 !text-2xs whitespace-nowrap !bg-amber-500 hover:!bg-amber-600 !text-white flex items-center gap-1 shadow-xs" title="Resume automated AI responses for this conversation">
                    <i class="ri-play-circle-line text-xs"></i>
                    <span class="hidden md:inline">AI Paused (Resume)</span>
                    <span class="md:hidden">Resume AI</span>
                </button>
            @else
                <button type="submit" class="app-btn app-btn-secondary !h-7 !px-2.5 !text-2xs whitespace-nowrap flex items-center gap-1 hover:!border-amber-500 hover:!text-amber-500" title="Takeover chat and pause AI auto-replies">
                    <i class="ri-pause-circle-line text-xs text-amber-500"></i>
                    <span class="hidden md:inline">Pause AI</span>
                    <span class="md:hidden">Pause</span>
                </button>
            @endif
        </form>

        {{-- AI Reply Button --}}
        <form action="{{ route('conversations.ai-reply', $conversation->id) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="app-btn app-btn-primary !h-7 !px-2.5 !text-2xs whitespace-nowrap" title="Generate AI Assistant Response">
                <i class="ri-robot-2-line text-xs"></i>
                <span class="hidden md:inline">Generate AI Reply</span>
                <span class="md:hidden">AI Reply</span>
            </button>
        </form>
    </div>
</div>
