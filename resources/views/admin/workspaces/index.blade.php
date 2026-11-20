@extends('admin.app')

@section('title')
    Workspaces Directory & Governance
@endsection

@section('content')
<div class="space-y-4">
    {{-- Header & Create Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <span>Multi-Tenant Workspaces Directory</span>
                <span class="app-badge app-badge-primary">Active ID: {{ $activeWorkspaceId }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Centralized tenant organizations, subscription quota limits, and channel integrations.
            </p>
        </div>
        <button type="button" class="app-btn app-btn-primary" data-bs-toggle="modal" data-bs-target="#createWorkspaceModal">
            <i class="ri-add-line text-sm"></i>
            <span>Create Workspace</span>
        </button>
    </div>

    {{-- Success Alert --}}
    @if(session('success'))
        <div class="alert p-3 rounded border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-checkbox-circle-line text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-500 hover:text-emerald-400" data-bs-dismiss="alert">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
    @endif

    {{-- Top 4 KPI Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="app-card p-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-lg flex-shrink-0">
                <i class="ri-building-line"></i>
            </div>
            <div>
                <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Total Workspaces</div>
                <div class="text-lg font-bold text-light-text dark:text-dark-text">{{ $totalWorkspaces }}</div>
                <div class="text-2xs text-light-muted dark:text-dark-muted">Registered tenants</div>
            </div>
        </div>

        <div class="app-card p-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-lg flex-shrink-0">
                <i class="ri-checkbox-circle-line"></i>
            </div>
            <div>
                <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Active Tenants</div>
                <div class="text-lg font-bold text-emerald-500">{{ $activeWorkspaces }} <span class="text-xs font-normal text-light-muted dark:text-dark-muted">/ {{ $totalWorkspaces }}</span></div>
                <div class="text-2xs text-light-muted dark:text-dark-muted">{{ $inactiveWorkspaces }} suspended</div>
            </div>
        </div>

        <div class="app-card p-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center text-lg flex-shrink-0">
                <i class="ri-team-line"></i>
            </div>
            <div>
                <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Tenant Users</div>
                <div class="text-lg font-bold text-light-text dark:text-dark-text">{{ $totalUsers }}</div>
                <div class="text-2xs text-light-muted dark:text-dark-muted">Admins & staff seats</div>
            </div>
        </div>

        <div class="app-card p-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-amber-500/15 text-amber-500 flex items-center justify-center text-lg flex-shrink-0">
                <i class="ri-message-3-line"></i>
            </div>
            <div>
                <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Conversations</div>
                <div class="text-lg font-bold text-light-text dark:text-dark-text">{{ $totalConversations }}</div>
                <div class="text-2xs text-light-muted dark:text-dark-muted">Across all channels</div>
            </div>
        </div>
    </div>

    {{-- Filters, Search & View Switcher --}}
    <div class="app-card p-2.5">
        <form method="GET" action="{{ route('workspaces.index') }}" class="flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2 w-full sm:w-auto flex-1">
                <div class="relative flex-1 max-w-sm">
                    <i class="ri-search-line absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-light-muted dark:text-dark-muted"></i>
                    <input type="text" name="search" class="app-input w-full pl-7" placeholder="Search by name or slug..." value="{{ request('search') }}">
                </div>
                <select name="status" class="app-select w-36" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Suspended Only</option>
                </select>
                <button type="submit" class="app-btn app-btn-secondary">
                    <i class="ri-filter-3-line"></i> Filter
                </button>
            </div>

            <div class="flex items-center gap-1 w-full sm:w-auto justify-end">
                <button type="button" class="app-btn app-btn-secondary active" id="btnViewGrid" onclick="setViewMode('grid')" title="Grid View">
                    <i class="ri-grid-fill"></i> Grid
                </button>
                <button type="button" class="app-btn app-btn-secondary" id="btnViewTable" onclick="setViewMode('table')" title="Table View">
                    <i class="ri-table-line"></i> Table
                </button>
            </div>
        </form>
    </div>

    {{-- Grid View --}}
    <div id="workspacesGridView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse($workspaces as $ws)
            @php
                $activeSub = $ws->subscriptions->where('status', 'active')->first();
                $planName = $activeSub?->plan?->name ?? 'Default Starter';
                $quota = $quotas->get($ws->id);
                $convUsed = $quota?->conversations_count ?? 0;
                $convMax = $activeSub?->plan?->max_conversations_monthly ?? 500;
                $pct = $convMax > 0 ? min(100, round(($convUsed / $convMax) * 100)) : 0;
                $isCurrentActive = (int) $ws->id === (int) $activeWorkspaceId;
            @endphp
            <div class="app-card flex flex-col justify-between {{ $isCurrentActive ? 'ring-1 ring-brand-500 border-brand-500' : '' }}">
                <div class="app-card-header">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <div class="w-8 h-8 rounded bg-brand-500/15 text-brand-500 font-bold text-xs flex items-center justify-center flex-shrink-0">
                            {{ strtoupper(substr($ws->name, 0, 2)) }}
                        </div>
                        <div class="overflow-hidden">
                            <h2 class="text-xs font-bold text-light-text dark:text-dark-text truncate" title="{{ $ws->name }}">
                                {{ $ws->name }}
                            </h2>
                            <code class="text-2xs text-light-muted dark:text-dark-muted font-mono">/{{ $ws->slug }}</code>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        @if($ws->is_active)
                            <span class="app-badge app-badge-success">Active</span>
                        @else
                            <span class="app-badge app-badge-danger">Suspended</span>
                        @endif
                        @if($isCurrentActive)
                            <span class="px-1.5 py-0.5 rounded text-2xs font-bold bg-brand-500 text-white">Active</span>
                        @endif
                    </div>
                </div>

                <div class="app-card-body space-y-2.5">
                    {{-- Quota Meter --}}
                    <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas space-y-1">
                        <div class="flex items-center justify-between text-2xs">
                            <span class="text-light-muted dark:text-dark-muted">Plan: <strong class="text-light-text dark:text-dark-text">{{ $planName }}</strong></span>
                            <span class="font-mono text-light-text dark:text-dark-text">{{ $convUsed }} / {{ $convMax === -1 ? '∞' : $convMax }}</span>
                        </div>
                        @if($convMax !== -1)
                            <div class="w-full h-1 bg-light-border dark:bg-dark-border rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $pct > 80 ? 'bg-red-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div>
                            </div>
                        @endif
                    </div>

                    {{-- Metadata Counters --}}
                    <div class="grid grid-cols-4 gap-1.5 text-center">
                        <div class="p-1.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                            <div class="text-xs font-bold text-light-text dark:text-dark-text">{{ $ws->users_count }}</div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Users</div>
                        </div>
                        <div class="p-1.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                            <div class="text-xs font-bold text-light-text dark:text-dark-text">{{ $ws->channelAccounts->count() }}</div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Channels</div>
                        </div>
                        <div class="p-1.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                            <div class="text-xs font-bold text-light-text dark:text-dark-text">{{ $ws->faqs_count }}</div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">FAQs</div>
                        </div>
                        <div class="p-1.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                            <div class="text-xs font-bold text-light-text dark:text-dark-text">{{ $ws->knowledge_documents_count }}</div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Docs</div>
                        </div>
                    </div>

                    {{-- Business Details --}}
                    <div class="text-2xs text-light-muted dark:text-dark-muted pt-1 space-y-0.5">
                        <div class="flex justify-between">
                            <span>Currency:</span>
                            <span class="font-semibold text-light-text dark:text-dark-text">{{ $ws->currency }}</span>
                        </div>
                        <div class="flex justify-between truncate">
                            <span>Support:</span>
                            <span class="text-light-text dark:text-dark-text truncate">{{ $ws->support_phone ?: ($ws->support_email ?: 'Not configured') }}</span>
                        </div>
                    </div>
                </div>

                <div class="app-card-footer flex items-center gap-1.5">
                    <form method="POST" action="{{ route('workspaces.switch', $ws) }}" class="flex-1">
                        @csrf
                        <button type="submit" class="app-btn {{ $isCurrentActive ? 'app-btn-outline' : 'app-btn-primary' }} w-full text-2xs">
                            <i class="ri-refresh-line"></i> {{ $isCurrentActive ? 'Current' : 'Switch Context' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('workspaces.toggle_active', $ws) }}">
                        @csrf
                        <button type="submit" class="app-action-btn app-btn-secondary" title="{{ $ws->is_active ? 'Suspend' : 'Activate' }}">
                            <i class="{{ $ws->is_active ? 'ri-pause-circle-line text-amber-500' : 'ri-play-circle-line text-emerald-500' }}"></i>
                        </button>
                    </form>

                    <a href="{{ route('workspace.settings.edit', ['workspace_id' => $ws->id]) }}" class="app-action-btn app-btn-secondary" title="Settings">
                        <i class="ri-settings-4-line text-light-text dark:text-dark-text"></i>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full app-card p-8 text-center text-light-muted dark:text-dark-muted">
                <i class="ri-building-line text-3xl mb-2 block"></i>
                <div class="text-xs font-semibold text-light-text dark:text-dark-text">No Workspaces Found</div>
                <p class="text-2xs">No workspaces matched your search filter.</p>
            </div>
        @endforelse
    </div>

    {{-- Table View (Initially Hidden) --}}
    <div id="workspacesTableView" class="app-card overflow-hidden hidden">
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Workspace</th>
                        <th>Status</th>
                        <th>Plan</th>
                        <th>Users</th>
                        <th>Channels</th>
                        <th>Knowledge</th>
                        <th>Conversations</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workspaces as $ws)
                        @php
                            $activeSub = $ws->subscriptions->where('status', 'active')->first();
                            $planName = $activeSub?->plan?->name ?? 'Default Starter';
                            $quota = $quotas->get($ws->id);
                            $convUsed = $quota?->conversations_count ?? 0;
                            $isCurrentActive = (int) $ws->id === (int) $activeWorkspaceId;
                        @endphp
                        <tr class="{{ $isCurrentActive ? 'bg-brand-500/5' : '' }}">
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-brand-500/15 text-brand-500 font-bold text-2xs flex items-center justify-center">
                                        {{ strtoupper(substr($ws->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-light-text dark:text-dark-text">{{ $ws->name }}</div>
                                        <code class="text-2xs text-light-muted dark:text-dark-muted font-mono">/{{ $ws->slug }}</code>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($ws->is_active)
                                    <span class="app-badge app-badge-success">Active</span>
                                @else
                                    <span class="app-badge app-badge-danger">Suspended</span>
                                @endif
                            </td>
                            <td><span class="app-badge app-badge-primary">{{ $planName }}</span></td>
                            <td>{{ $ws->users_count }}</td>
                            <td>{{ $ws->channelAccounts->count() }}</td>
                            <td><span class="text-2xs text-light-muted dark:text-dark-muted">{{ $ws->faqs_count }} FAQs · {{ $ws->knowledge_documents_count }} Docs</span></td>
                            <td><strong>{{ $convUsed }}</strong></td>
                            <td><span class="text-2xs text-light-muted dark:text-dark-muted">{{ $ws->created_at ? $ws->created_at->format('Y-m-d') : 'N/A' }}</span></td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('workspaces.switch', $ws) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="app-btn app-btn-secondary !h-7 !px-2 text-2xs" title="Switch">
                                            <i class="ri-refresh-line"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('workspaces.toggle_active', $ws) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="app-action-btn app-btn-secondary !h-7 !w-7" title="Status">
                                            <i class="{{ $ws->is_active ? 'ri-pause-circle-line text-amber-500' : 'ri-play-circle-line text-emerald-500' }}"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('workspace.settings.edit', ['workspace_id' => $ws->id]) }}" class="app-action-btn app-btn-secondary !h-7 !w-7" title="Settings">
                                        <i class="ri-settings-4-line"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="flex justify-end pt-2">
        {{ $workspaces->links() }}
    </div>
</div>

{{-- Create Workspace Modal (Pure Tailwind) --}}
<div class="modal hidden fixed inset-0 z-50 flex items-center justify-center p-4" id="createWorkspaceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog w-full max-w-md app-card shadow-modal overflow-hidden transition-all duration-200">
        <form method="POST" action="{{ route('workspaces.store') }}">
            @csrf
            <div class="app-card-header">
                <h3 class="text-xs font-bold text-light-text dark:text-dark-text">Create New Workspace</h3>
                <button type="button" class="text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-base"></i>
                </button>
            </div>
            <div class="app-card-body space-y-3">
                <div class="space-y-1">
                    <label class="text-2xs font-semibold text-light-text dark:text-dark-text">Workspace Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" class="app-input w-full" placeholder="e.g. Apex Commerce" required>
                </div>
                <div class="space-y-1">
                    <label class="text-2xs font-semibold text-light-text dark:text-dark-text">Workspace Slug (URL)</label>
                    <input type="text" name="slug" class="app-input w-full" placeholder="e.g. apex-commerce (auto-generated if empty)">
                </div>
                <div class="space-y-1">
                    <label class="text-2xs font-semibold text-light-text dark:text-dark-text">Subscription Plan</label>
                    <select name="plan_id" class="app-select w-full">
                        <option value="">Default Starter</option>
                        @foreach($availablePlans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->currency }} {{ number_format((float)$plan->price_monthly) }}/mo)</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-2xs font-semibold text-light-text dark:text-dark-text">Description</label>
                    <textarea name="description" class="app-textarea w-full !h-16 py-1.5" placeholder="Brief business overview..."></textarea>
                </div>
            </div>
            <div class="app-card-footer flex items-center justify-end gap-2">
                <button type="button" class="app-btn app-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="app-btn app-btn-primary">Create Workspace</button>
            </div>
        </form>
    </div>
</div>

<script>
function setViewMode(mode) {
    const gridView = document.getElementById('workspacesGridView');
    const tableView = document.getElementById('workspacesTableView');
    const btnGrid = document.getElementById('btnViewGrid');
    const btnTable = document.getElementById('btnViewTable');

    if (mode === 'table') {
        gridView.classList.add('hidden');
        tableView.classList.remove('hidden');
        btnTable.classList.add('app-btn-primary');
        btnTable.classList.remove('app-btn-secondary');
        btnGrid.classList.remove('app-btn-primary');
        btnGrid.classList.add('app-btn-secondary');
    } else {
        gridView.classList.remove('hidden');
        tableView.classList.add('hidden');
        btnGrid.classList.add('app-btn-primary');
        btnGrid.classList.remove('app-btn-secondary');
        btnTable.classList.remove('app-btn-primary');
        btnTable.classList.add('app-btn-secondary');
    }
}
</script>
@endsection
