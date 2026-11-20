@extends('admin.app')

@section('title')
    Workspaces Directory & Multi-Tenant Management
@endsection

@section('content')
<div class="container-fluid">
    {{-- Header & Create Button --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="mb-1 text-dark fw-bold">Multi-Tenant Workspaces Directory</h4>
            <p class="text-muted mb-0 small">Superadmin Central Workspace Governance · Current Active Workspace: 
                <span class="badge bg-primary px-2 py-1">ID: {{ $activeWorkspaceId }}</span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#createWorkspaceModal">
                <i class="ri-add-line fs-5"></i> Create New Workspace
            </button>
        </div>
    </div>

    {{-- Success Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="ri-checkbox-circle-line me-2 fs-5"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="icon-box-primary fs-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <i class="ri-building-line"></i>
                    </div>
                    <div>
                        <span class="text-muted text-uppercase small d-block fw-semibold">Total Workspaces</span>
                        <h3 class="fw-bold mb-0 text-dark">{{ $totalWorkspaces }}</h3>
                        <small class="text-muted">Registered tenant organizations</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="icon-box-success fs-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                    <div>
                        <span class="text-muted text-uppercase small d-block fw-semibold">Active Tenants</span>
                        <h3 class="fw-bold mb-0 text-success">{{ $activeWorkspaces }} <span class="fs-6 text-muted fw-normal">/ {{ $totalWorkspaces }}</span></h3>
                        <small class="text-muted">{{ $inactiveWorkspaces }} suspended / inactive</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="icon-box-info fs-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <i class="ri-team-line"></i>
                    </div>
                    <div>
                        <span class="text-muted text-uppercase small d-block fw-semibold">Multi-Tenant Users</span>
                        <h3 class="fw-bold mb-0 text-dark">{{ $totalUsers }}</h3>
                        <small class="text-muted">Admins, agents & staff</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="icon-box-warning fs-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <i class="ri-message-3-line"></i>
                    </div>
                    <div>
                        <span class="text-muted text-uppercase small d-block fw-semibold">Total Conversations</span>
                        <h3 class="fw-bold mb-0 text-dark">{{ $totalConversations }}</h3>
                        <small class="text-muted">Across all channels</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters, Search & View Mode Switcher --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('workspaces.index') }}" class="row g-2 align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text"><i class="ri-search-line"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search workspace by name or slug..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Suspended / Inactive Only</option>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ri-filter-3-line"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 col-12 text-md-end">
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-secondary active w-50" id="btnViewGrid" onclick="setViewMode('grid')">
                            <i class="ri-grid-fill"></i> Grid
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-50" id="btnViewTable" onclick="setViewMode('table')">
                            <i class="ri-table-line"></i> Table
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Workspaces Grid View --}}
    <div id="workspacesGridView" class="row g-3 mb-4">
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
            <div class="col-xl-4 col-md-6 col-12">
                <div class="card border-0 shadow-sm rounded-3 h-100 {{ $isCurrentActive ? 'border-primary border-2' : '' }}">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 50px; height: 50px; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                                {{ strtoupper(substr($ws->name, 0, 2)) }}
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 text-dark text-truncate" style="max-width: 180px;" title="{{ $ws->name }}">
                                    {{ $ws->name }}
                                </h5>
                                <code class="small text-muted">/{{ $ws->slug }}</code>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            @if($ws->is_active)
                                <span class="badge badge-soft-success">Active</span>
                            @else
                                <span class="badge badge-soft-danger">Suspended</span>
                            @endif
                            @if($isCurrentActive)
                                <span class="badge bg-primary text-white px-2 py-1 small rounded-pill shadow-sm">Active Session</span>
                            @endif
                        </div>
                    </div>

                    <div class="card-body px-4 py-2">
                        {{-- Subscription Plan & Quota --}}
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted">Subscription Plan</span>
                                <span class="badge bg-dark">{{ $planName }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span class="text-muted">Quota ({{ $currentPeriod }})</span>
                                <span class="fw-bold text-dark">{{ $convUsed }} / {{ $convMax === -1 ? 'Unlimited' : $convMax }}</span>
                            </div>
                            @if($convMax !== -1)
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $pct > 80 ? 'bg-danger' : 'bg-primary' }}" role="progressbar" style="width: {{ $pct }}%"></div>
                                </div>
                            @endif
                        </div>

                        {{-- Metadata Counters --}}
                        <div class="row g-2 text-center mb-2">
                            <div class="col-3">
                                <div class="p-2 border rounded-2">
                                    <span class="d-block fw-bold text-dark fs-6">{{ $ws->users_count }}</span>
                                    <small class="text-muted" style="font-size: 11px;">Users</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded-2">
                                    <span class="d-block fw-bold text-dark fs-6">{{ $ws->channelAccounts->count() }}</span>
                                    <small class="text-muted" style="font-size: 11px;">Channels</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded-2">
                                    <span class="d-block fw-bold text-dark fs-6">{{ $ws->faqs_count }}</span>
                                    <small class="text-muted" style="font-size: 11px;">FAQs</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded-2">
                                    <span class="d-block fw-bold text-dark fs-6">{{ $ws->knowledge_documents_count }}</span>
                                    <small class="text-muted" style="font-size: 11px;">Docs</small>
                                </div>
                            </div>
                        </div>

                        {{-- Business Profile Details --}}
                        <div class="py-2 border-top border-bottom my-2 small text-muted">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Currency:</span>
                                <strong class="badge badge-soft-primary px-2 py-0.5">{{ $ws->currency }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1 text-truncate">
                                <span>Support:</span>
                                <span class="fw-semibold text-dark">{{ $ws->support_phone ?: ($ws->support_email ?: 'Not configured') }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Hours:</span>
                                <span class="text-dark">{{ $ws->operating_hours['weekdays'] ?? '09:00 AM - 08:00 PM' }}</span>
                            </div>
                        </div>

                        <div class="small text-muted mb-1">
                            <i class="ri-calendar-line me-1"></i> Created: {{ $ws->created_at ? $ws->created_at->format('M d, Y') : 'N/A' }}
                        </div>
                    </div>

                    <div class="card-footer bg-white border-0 pt-0 pb-4 px-4 d-flex gap-2">
                        <form method="POST" action="{{ route('workspaces.switch', $ws) }}" class="flex-grow-1">
                            @csrf
                            <button type="submit" class="btn {{ $isCurrentActive ? 'btn-outline-primary' : 'btn-primary' }} btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                                <i class="ri-refresh-line"></i> {{ $isCurrentActive ? 'Current Context' : 'Switch Context' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('workspaces.toggle_active', $ws) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="{{ $ws->is_active ? 'Suspend Workspace' : 'Activate Workspace' }}">
                                <i class="{{ $ws->is_active ? 'ri-pause-circle-line text-warning' : 'ri-play-circle-line text-success' }} fs-6"></i>
                            </button>
                        </form>

                        <a href="{{ route('workspace.settings.edit', ['workspace_id' => $ws->id]) }}" class="btn btn-outline-secondary btn-sm" title="Edit Business Profile & Settings">
                            <i class="ri-settings-4-line fs-6"></i>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-3 text-center py-5">
                    <div class="card-body">
                        <i class="ri-building-line text-muted display-4 mb-3 d-block"></i>
                        <h5 class="text-dark fw-bold">No Workspaces Found</h5>
                        <p class="text-muted">No workspaces matched your search criteria.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Workspaces Table View (Initially Hidden) --}}
    <div id="workspacesTableView" class="card border-0 shadow-sm rounded-3 mb-4 d-none">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Workspace</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th>Users</th>
                            <th>Channels</th>
                            <th>Knowledge</th>
                            <th>Conversations</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
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
                            <tr class="{{ $isCurrentActive ? 'table-primary' : '' }}">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center icon-box-primary fw-bold" style="width: 36px; height: 36px; font-size: 0.95rem;">
                                            {{ strtoupper(substr($ws->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <strong>{{ $ws->name }}</strong>
                                            <code class="d-block small text-muted">/{{ $ws->slug }}</code>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($ws->is_active)
                                        <span class="badge badge-soft-success">Active</span>
                                    @else
                                        <span class="badge badge-soft-danger">Suspended</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-dark">{{ $planName }}</span></td>
                                <td>{{ $ws->users_count }}</td>
                                <td>{{ $ws->channelAccounts->count() }}</td>
                                <td><small class="text-muted">{{ $ws->faqs_count }} FAQs · {{ $ws->knowledge_documents_count }} Docs</small></td>
                                <td><strong>{{ $convUsed }}</strong></td>
                                <td><small class="text-muted">{{ $ws->created_at ? $ws->created_at->format('Y-m-d') : 'N/A' }}</small></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="{{ route('workspaces.switch', $ws) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-sm" title="Switch Context">
                                                <i class="ri-refresh-line"></i> Switch
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('workspaces.toggle_active', $ws) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Toggle Status">
                                                <i class="{{ $ws->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('workspace.settings.edit', ['workspace_id' => $ws->id]) }}" class="btn btn-outline-secondary btn-sm" title="Edit Business Profile & Settings">
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
    </div>

    {{-- Pagination --}}
    <div class="d-flex justify-content-end">
        {{ $workspaces->links() }}
    </div>
</div>

{{-- Create Workspace Modal --}}
<div class="modal fade" id="createWorkspaceModal" tabindex="-1" aria-labelledby="createWorkspaceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('workspaces.store') }}">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="createWorkspaceModalLabel">Create New Workspace</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Workspace Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Acme Enterprise" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Workspace Slug (URL Identifier)</label>
                        <input type="text" name="slug" class="form-control" placeholder="e.g. acme-enterprise (optional)">
                        <small class="text-muted">Unique tenant identifier. Auto-generated from name if left blank.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subscription Plan</label>
                        <select name="plan_id" class="form-select">
                            <option value="">Default Starter</option>
                            @foreach($availablePlans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->currency }} {{ number_format((float)$plan->price_monthly) }}/mo)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief business overview..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Create Workspace</button>
                </div>
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
        gridView.classList.add('d-none');
        tableView.classList.remove('d-none');
        btnTable.classList.add('active');
        btnGrid.classList.remove('active');
    } else {
        gridView.classList.remove('d-none');
        tableView.classList.add('d-none');
        btnGrid.classList.add('active');
        btnTable.classList.remove('active');
    }
}
</script>
@endsection
