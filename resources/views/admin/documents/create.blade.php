@extends('admin.app')
@section('title', 'Upload Analytics Data Source')

@section('content')
<div class="space-y-4">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
        <div>
            <h1 class="text-base font-semibold text-text-primary tracking-tight">Upload Analytics Data Source</h1>
            <div class="flex items-center gap-1.5 text-xs text-text-muted mt-0.5">
                <a href="{{ route('dashboard') }}" class="hover:text-text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <a href="{{ route('knowledge-base.index') }}" class="hover:text-text-primary transition-colors">Knowledge Base</a>
                <span>/</span>
                <span class="text-text-secondary font-medium">Upload Data Source</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('knowledge-base.index', ['tab' => 'data_sources']) }}" class="app-btn app-btn-secondary">
                <i class="ri-arrow-left-line"></i> Back to Data Sources
            </a>
        </div>
    </div>

    <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" autocomplete="off" id="uploadDocumentForm">
        @csrf
        <input type="hidden" name="purpose" value="data_analytics">
        <input type="hidden" name="redirect_to" value="hub">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Main Form Column --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <div>
                            <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Spreadsheet File & Metadata</h2>
                            <p class="text-2xs text-text-muted">Upload multi-sheet Excel (.xlsx) or CSV files for automated AI analytics</p>
                        </div>
                    </div>
                    <div class="app-card-body space-y-4">
                        @if ($errors->any())
                            <div class="p-3 bg-rose-500/10 border border-rose-500/20 rounded-md text-xs text-rose-500 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <div>• {{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        {{-- SuperAdmin Workspace Select --}}
                        @if (!empty($isSuperAdmin) && isset($workspaces) && count($workspaces) > 0)
                            <div>
                                <label for="workspace_id" class="block text-xs font-medium text-text-secondary mb-1">
                                    Target Workspace <span class="text-rose-500">*</span>
                                </label>
                                <select name="workspace_id" id="workspace_id" class="app-select w-full @error('workspace_id') border-rose-500 @enderror" required>
                                    <option value="">-- Select Target Workspace --</option>
                                    @foreach ($workspaces as $ws)
                                        <option value="{{ $ws->id }}" {{ old('workspace_id') == $ws->id ? 'selected' : '' }}>
                                            {{ $ws->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('workspace_id')
                                    <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        {{-- Title --}}
                        <div>
                            <label for="title" class="block text-xs font-medium text-text-secondary mb-1">
                                Data Source Title <span class="text-2xs text-text-muted font-normal">(Optional, defaults to file name)</span>
                            </label>
                            <input type="text" class="app-input w-full @error('title') border-rose-500 @enderror" name="title" id="title" value="{{ old('title') }}" placeholder="e.g. Q4 Order History & Inventory Ledger">
                            @error('title')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Dropzone Area --}}
                        <div>
                            <label class="block text-xs font-medium text-text-secondary mb-1.5">
                                Select Spreadsheet File <span class="text-rose-500">*</span>
                            </label>

                            <input type="file" class="hidden @error('document') border-rose-500 @enderror" name="document" id="document" accept=".xlsx,.csv" required>

                            {{-- Dropzone visual --}}
                            <div id="dropzoneArea" class="border-2 border-dashed border-border rounded-lg p-6 text-center cursor-pointer hover:border-brand-primary bg-surface-elevated/40 hover:bg-surface-elevated transition-all flex flex-col items-center justify-center gap-2" onclick="document.getElementById('document').click()">
                                <div class="w-10 h-10 rounded-full bg-brand-primary/10 text-brand-primary flex items-center justify-center text-xl">
                                    <i class="ri-upload-cloud-2-line"></i>
                                </div>
                                <div class="text-xs font-medium text-text-primary">
                                    Click to browse or drag & drop spreadsheet here
                                </div>
                                <p class="text-2xs text-text-muted">
                                    Supports Microsoft Excel (.xlsx) and CSV files up to 20 MB
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="app-badge bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 text-2xs">
                                        <i class="ri-file-excel-2-line"></i> .XLSX
                                    </span>
                                    <span class="app-badge bg-sky-500/10 text-sky-500 border border-sky-500/20 text-2xs">
                                        <i class="ri-file-text-line"></i> .CSV
                                    </span>
                                </div>
                            </div>

                            {{-- File Preview Card --}}
                            <div id="filePreviewCard" class="hidden app-card border-emerald-500/30 bg-emerald-500/5 mt-2 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-md bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-lg flex-shrink-0">
                                            <i class="ri-file-excel-2-fill" id="previewIcon"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-text-primary truncate" id="previewFileName">spreadsheet.xlsx</p>
                                            <div class="flex items-center gap-2 text-2xs text-text-muted mt-0.5">
                                                <span id="previewFileSize">0 KB</span>
                                                <span>•</span>
                                                <span class="font-mono text-emerald-500 font-semibold" id="previewFileExt">XLSX</span>
                                                <span>•</span>
                                                <span class="text-emerald-500 flex items-center gap-1 font-medium"><i class="ri-checkbox-circle-fill"></i> Ready</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <button type="button" class="app-btn app-btn-secondary text-xs h-7 px-2" onclick="document.getElementById('document').click()" title="Change file">
                                            <i class="ri-refresh-line"></i> Change
                                        </button>
                                        <button type="button" class="app-btn text-rose-500 border border-rose-500/20 hover:bg-rose-500/10 text-xs h-7 px-2" onclick="removeSelectedFile()" title="Remove file">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            @error('document')
                                <p class="text-rose-500 text-2xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar Column --}}
            <div class="space-y-4">
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Storage & Isolation</h2>
                    </div>
                    <div class="app-card-body space-y-3">
                        <div class="p-2.5 rounded-md bg-surface-elevated border border-border space-y-1">
                            <span class="text-2xs font-semibold text-brand-primary uppercase tracking-wider flex items-center gap-1">
                                <i class="ri-shield-keyhole-line"></i> Tenant Isolation
                            </span>
                            <p class="text-2xs text-text-muted leading-relaxed">
                                Uploaded data sources are strictly sandboxed inside your isolated workspace schema with cryptographic SHA-256 deduplication.
                            </p>
                        </div>

                        <div class="p-2.5 rounded-md bg-surface-elevated border border-border space-y-1">
                            <span class="text-2xs font-semibold text-emerald-500 uppercase tracking-wider flex items-center gap-1">
                                <i class="ri-database-2-line"></i> In-Memory Math Engine
                            </span>
                            <p class="text-2xs text-text-muted leading-relaxed">
                                High-speed SQL queries execute safe read-only mathematical aggregations (AOV, margins, customer orders) without production DB queries.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Action Card --}}
                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="text-xs font-semibold text-text-primary uppercase tracking-wider">Registration</h2>
                    </div>
                    <div class="app-card-body space-y-2">
                        <button type="submit" class="app-btn app-btn-primary w-full justify-center" id="btnUploadSubmit">
                            <i class="ri-upload-2-line"></i>
                            <span id="btnSubmitText">Upload & Register</span>
                        </button>
                        <a href="{{ route('knowledge-base.index', ['tab' => 'data_sources']) }}" class="app-btn app-btn-secondary w-full justify-center">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('custom-scripts')
<script>
    function formatBytes(bytes, decimals = 1) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function handleFileSelection(file) {
        if (!file) return;

        const ext = file.name.split('.').pop().toLowerCase();
        if (!['xlsx', 'csv'].includes(ext)) {
            if (typeof swal === 'function') {
                swal("Invalid Format", "Only .xlsx and .csv spreadsheets are supported.", "error");
            } else {
                alert('Only .xlsx and .csv spreadsheets are supported.');
            }
            removeSelectedFile();
            return;
        }

        if (file.size > 20 * 1024 * 1024) {
            if (typeof swal === 'function') {
                swal("File Too Large", "Maximum upload size is 20 MB.", "warning");
            } else {
                alert('Maximum upload size is 20 MB.');
            }
            removeSelectedFile();
            return;
        }

        document.getElementById('previewFileName').textContent = file.name;
        document.getElementById('previewFileSize').textContent = formatBytes(file.size);
        document.getElementById('previewFileExt').textContent = ext.toUpperCase();

        var previewIcon = document.getElementById('previewIcon');
        if (ext === 'csv') {
            previewIcon.className = 'ri-file-text-fill';
        } else {
            previewIcon.className = 'ri-file-excel-2-fill';
        }

        var titleInput = document.getElementById('title');
        if (titleInput && !titleInput.value.trim()) {
            var nameWithoutExt = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
            titleInput.value = nameWithoutExt;
        }

        document.getElementById('dropzoneArea').classList.add('hidden');
        document.getElementById('filePreviewCard').classList.remove('hidden');
    }

    function removeSelectedFile() {
        document.getElementById('document').value = '';
        document.getElementById('dropzoneArea').classList.remove('hidden');
        document.getElementById('filePreviewCard').classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const dropzone = document.getElementById('dropzoneArea');
        const fileInput = document.getElementById('document');

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    handleFileSelection(this.files[0]);
                }
            });
        }

        if (dropzone) {
            ['dragover', 'dragenter'].forEach(eventName => {
                dropzone.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('border-brand-primary', 'bg-surface-elevated');
                });
            });

            ['dragleave', 'dragend', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('border-brand-primary', 'bg-surface-elevated');
                });
            });

            dropzone.addEventListener('drop', function (e) {
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    fileInput.files = dt.files;
                    handleFileSelection(dt.files[0]);
                }
            });
        }
    });
</script>
@endpush
