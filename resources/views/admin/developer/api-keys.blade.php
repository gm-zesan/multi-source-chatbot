@extends('admin.app')

@section('title', 'Developer API Keys — Entrepreneurs Automation')

@section('content')
<div class="space-y-5">
    {{-- Top Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-terminal-box-line text-brand-500 text-xl"></i>
                <span>Developer REST API Keys</span>
                <span class="app-badge app-badge-primary">{{ $workspace->name }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-0.5">
                Authenticate programmatic requests from custom storefronts, mobile apps, and backend services.
            </p>
        </div>
        <div>
            <button type="button" class="app-btn app-btn-primary flex items-center gap-2" data-bs-toggle="modal" data-bs-target="#createApiKeyModal">
                <i class="ri-key-2-line text-sm"></i>
                <span>Generate New API Key</span>
            </button>
        </div>
    </div>

    {{-- Success Alert --}}
    @if(session('success'))
        <div class="alert p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-500 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="ri-checkbox-circle-line text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-500 hover:text-emerald-400" data-bs-dismiss="alert">
                <i class="ri-close-line text-base"></i>
            </button>
        </div>
    @endif

    {{-- One-Time Token Reveal Alert --}}
    @if(!empty($newPlainToken))
        <div class="p-4 rounded-xl border-2 border-brand-500 bg-brand-500/10 shadow-lg relative">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-brand-500 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="ri-shield-keyhole-line text-base"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-xs font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                        <span>Save your new API Key: <span class="text-brand-500">"{{ $newApiKeyName }}"</span></span>
                    </h3>
                    <p class="text-2xs text-light-text-secondary dark:text-dark-text-secondary mt-1">
                        Please copy this key immediately and store it securely. For security reasons, you will <strong>never</strong> be able to view this full secret again.
                    </p>

                    <div class="mt-3 flex items-center gap-2 max-w-2xl">
                        <input 
                            type="text" 
                            id="revealedApiKeyInput" 
                            readonly 
                            value="{{ $newPlainToken }}" 
                            class="app-input flex-1 font-mono text-xs bg-light-canvas dark:bg-dark-canvas border-brand-500/50 text-brand-600 dark:text-brand-400 font-semibold select-all"
                        >
                        <button 
                            type="button" 
                            id="copyRevealedKeyBtn" 
                            onclick="navigator.clipboard.writeText('{{ $newPlainToken }}'); this.innerHTML = '<i class=\'ri-check-line\'></i> Copied!'; setTimeout(() => this.innerHTML = '<i class=\'ri-file-copy-line\'></i> Copy Key', 2000);"
                            class="app-btn app-btn-primary flex items-center gap-1.5 whitespace-nowrap"
                        >
                            <i class="ri-file-copy-line"></i>
                            <span>Copy Key</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Active Keys Table Card --}}
    <div class="app-card overflow-hidden">
        <div class="app-card-header flex items-center justify-between p-3.5 border-b border-light-border dark:border-dark-border">
            <div class="flex items-center gap-2">
                <i class="ri-lock-line text-brand-500"></i>
                <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Active API Credentials</h2>
                <span class="app-badge app-badge-secondary font-mono">{{ count($apiKeys) }} active</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-light-border dark:border-dark-border bg-light-canvas/50 dark:bg-dark-canvas/50 text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        <th class="py-2.5 px-4 font-semibold">Key Name</th>
                        <th class="py-2.5 px-4 font-semibold">Key Identifier</th>
                        <th class="py-2.5 px-4 font-semibold">Permissions</th>
                        <th class="py-2.5 px-4 font-semibold">Created Date</th>
                        <th class="py-2.5 px-4 font-semibold">Last Used</th>
                        <th class="py-2.5 px-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-border dark:divide-dark-border">
                    @forelse($apiKeys as $key)
                        <tr class="hover:bg-light-canvas/40 dark:hover:bg-dark-canvas/40 transition-colors">
                            <td class="py-3 px-4 font-medium text-light-text dark:text-dark-text">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-brand-500/10 text-brand-500 flex items-center justify-center text-xs">
                                        <i class="ri-key-line"></i>
                                    </div>
                                    <span>{{ $key->name }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 font-mono text-2xs text-light-muted dark:text-dark-muted">
                                <code class="px-2 py-0.5 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border text-brand-600 dark:text-brand-400">
                                    {{ $key->key_prefix }}
                                </code>
                            </td>
                            <td class="py-3 px-4">
                                <span class="app-badge app-badge-success text-3xs uppercase">Full Access (*)</span>
                            </td>
                            <td class="py-3 px-4 text-2xs text-light-muted dark:text-dark-muted">
                                {{ $key->created_at->format('M d, Y') }}
                            </td>
                            <td class="py-3 px-4 text-2xs text-light-muted dark:text-dark-muted">
                                @if($key->last_used_at)
                                    <span class="text-emerald-500">{{ $key->last_used_at->diffForHumans() }}</span>
                                    @if($key->last_used_ip)
                                        <span class="text-3xs block opacity-75 font-mono">IP: {{ $key->last_used_ip }}</span>
                                    @endif
                                @else
                                    <span class="opacity-60">Never used</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <form method="POST" action="{{ route('api-keys.destroy', $key) }}" class="inline" onsubmit="return confirm('Are you sure you want to permanently revoke this API Key? Any application using this key will immediately fail.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="app-action-btn hover:text-rose-500 hover:bg-rose-500/10" title="Revoke Key">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-light-muted dark:text-dark-muted">
                                <i class="ri-key-2-line text-2xl mb-1 block opacity-50"></i>
                                <span class="text-xs font-medium">No API keys created yet.</span>
                                <p class="text-2xs mt-0.5">Click "Generate New API Key" above to integrate your custom apps.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick Integration Code Snippets Carousel/Tabs --}}
    <div class="app-card p-4">
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-light-border dark:border-dark-border">
            <div class="flex items-center gap-2">
                <i class="ri-code-s-slash-line text-brand-500"></i>
                <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">API Integration Examples</h2>
            </div>
            <span class="text-3xs font-mono text-light-muted dark:text-dark-muted">Endpoint: POST /api/v1/chat</span>
        </div>

        <div class="space-y-3">
            {{-- cURL Snippet --}}
            <div>
                <div class="text-2xs font-semibold text-light-muted dark:text-dark-muted mb-1 flex items-center justify-between">
                    <span>cURL / Shell</span>
                    <button type="button" class="text-brand-500 hover:underline text-3xs" onclick="navigator.clipboard.writeText(document.getElementById('curlSnippet').innerText); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 1500);">Copy</button>
                </div>
                <pre id="curlSnippet" class="p-3 rounded-lg bg-dark-canvas text-emerald-400 font-mono text-2xs overflow-x-auto leading-relaxed border border-dark-border">curl -X POST "{{ $chatEndpoint }}" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Do you offer free delivery inside Dhaka?",
    "customer_name": "Rahim Khan"
  }'</pre>
            </div>

            {{-- Python Snippet --}}
            <div>
                <div class="text-2xs font-semibold text-light-muted dark:text-dark-muted mb-1 flex items-center justify-between">
                    <span>Python (requests)</span>
                    <button type="button" class="text-brand-500 hover:underline text-3xs" onclick="navigator.clipboard.writeText(document.getElementById('pythonSnippet').innerText); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 1500);">Copy</button>
                </div>
                <pre id="pythonSnippet" class="p-3 rounded-lg bg-dark-canvas text-sky-300 font-mono text-2xs overflow-x-auto leading-relaxed border border-dark-border">import requests

url = "{{ $chatEndpoint }}"
headers = {
    "Authorization": "Bearer YOUR_API_KEY",
    "Content-Type": "application/json"
}
payload = {
    "message": "What are your business operating hours?",
    "customer_name": "Zisan Ahmed"
}

response = requests.post(url, json=payload, headers=headers)
data = response.json()
print("AI Reply:", data["data"]["reply"])</pre>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Generate API Key --}}
<div class="modal fade" id="createApiKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-key-2-line text-brand-500"></i>
                    <span>Generate Developer API Key</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form action="{{ route('api-keys.store') }}" method="POST">
                @csrf
                <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">

                <div class="space-y-4">
                    <div>
                        <label for="key_name" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Key Description / Client Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="key_name" 
                            name="name" 
                            required 
                            placeholder="e.g. Production Shopify Store, Mobile App Backend" 
                            class="app-input w-full text-xs"
                        >
                        <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">
                            A descriptive identifier to help you remember where this API key is deployed.
                        </p>
                    </div>

                    <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 text-3xs flex items-start gap-2">
                        <i class="ri-alert-line text-sm flex-shrink-0 mt-0.5"></i>
                        <span>This key will have full programmatic access to query your AI knowledge base and customer transcripts within <strong>{{ $workspace->name }}</strong>.</span>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5">
                        <i class="ri-checkbox-circle-line"></i>
                        <span>Generate Key</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
