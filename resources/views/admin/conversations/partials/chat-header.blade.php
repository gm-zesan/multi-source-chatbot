<div class="chat-header d-flex flex-wrap align-items-center justify-content-between p-3 border-bottom bg-white">
    <div class="chat-user d-flex align-items-center gap-3">
        <div class="chat-avatar">
            @if($conversation->customer_avatar)
                <img src="{{ $conversation->customer_avatar }}" alt="{{ $conversation->customer_name }}" class="rounded-circle" width="42" height="42">
            @else
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px; font-size: 16px;">
                    {{ strtoupper(substr($conversation->customer_name ?? 'U',0,1)) }}
                </div>
            @endif
        </div>

        <div>
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold">{{ $conversation->customer_name ?? 'Anonymous Visitor' }}</h5>
                @php
                    $statusColors = [
                        'open'        => 'bg-primary',
                        'pending'     => 'bg-warning text-dark',
                        'in_progress' => 'bg-info text-dark',
                        'resolved'    => 'bg-success',
                        'closed'      => 'bg-secondary',
                    ];
                    $priorityColors = [
                        'low'    => 'border border-secondary text-secondary',
                        'normal' => 'bg-secondary',
                        'high'   => 'bg-warning text-dark',
                        'urgent' => 'bg-danger',
                    ];
                    $currentStatus = $conversation->status ?? 'open';
                    $currentPriority = $conversation->priority ?? 'normal';
                @endphp
                <span class="badge {{ $statusColors[$currentStatus] ?? 'bg-primary' }}">
                    {{ ucfirst(str_replace('_', ' ', $currentStatus)) }}
                </span>
                <span class="badge {{ $priorityColors[$currentPriority] ?? 'bg-secondary' }}">
                    {{ ucfirst($currentPriority) }}
                </span>
            </div>

            <div class="chat-meta text-muted small mt-1 d-flex align-items-center gap-2">
                <span class="channel badge bg-light text-dark border">
                    {{ ucfirst($conversation->channelAccount?->channel?->name ?? 'Channel') }}
                </span>
                <span>•</span>
                <span>ID: {{ $conversation->external_user_id }}</span>
                @if($conversation->assignedTo)
                    <span>•</span>
                    <span class="text-primary"><i class="bi bi-person-check-fill me-1"></i>{{ $conversation->assignedTo->name }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="chat-actions d-flex flex-wrap align-items-center gap-2 mt-2 mt-md-0">
        {{-- Agent Assignment Selector --}}
        <form action="{{ route('conversations.assign', $conversation->id) }}" method="POST" class="d-inline-flex align-items-center">
            @csrf
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                <select name="assigned_to_user_id" class="form-select form-select-sm" onchange="this.form.submit()" title="Assign conversation to agent" style="max-width: 150px;">
                    <option value="">Unassigned</option>
                    @if(isset($teamMembers))
                        @foreach($teamMembers as $member)
                            <option value="{{ $member->id }}" {{ $conversation->assigned_to_user_id == $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
        </form>

        {{-- Status & Priority Selector --}}
        <form action="{{ route('conversations.update-status', $conversation->id) }}" method="POST" class="d-inline-flex align-items-center gap-1">
            @csrf
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" title="Change Ticket Status" style="max-width: 120px;">
                <option value="open" {{ $conversation->status === 'open' ? 'selected' : '' }}>Open</option>
                <option value="in_progress" {{ $conversation->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ $conversation->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="closed" {{ $conversation->status === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
            <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()" title="Change Ticket Priority" style="max-width: 100px;">
                <option value="low" {{ ($conversation->priority ?? 'normal') === 'low' ? 'selected' : '' }}>Low</option>
                <option value="normal" {{ ($conversation->priority ?? 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="high" {{ ($conversation->priority ?? 'normal') === 'high' ? 'selected' : '' }}>High</option>
                <option value="urgent" {{ ($conversation->priority ?? 'normal') === 'urgent' ? 'selected' : '' }}>Urgent</option>
            </select>
        </form>

        {{-- AI Reply Button --}}
        <form action="{{ route('conversations.ai-reply', $conversation->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary" style="display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 13px;" title="Generate AI Assistant Response">
                <i class="bi bi-robot"></i>
                <span>Generate AI Reply</span>
            </button>
        </form>
    </div>
</div>
