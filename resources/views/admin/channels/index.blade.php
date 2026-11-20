@extends('admin.app')

@section('title', 'Channel Integrations — Entrepreneurs Automation')

@section('content')
<div class="space-y-5">
    {{-- Top Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-share-line text-brand-500 text-xl"></i>
                <span>Omnichannel Social & Messaging Integrations</span>
                <span class="app-badge app-badge-primary">{{ $workspace->name }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary mt-0.5">
                Connect customer communication channels to your central AI knowledge base and agent inbox.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('widget.manage') }}" class="app-btn app-btn-secondary text-xs">
                <i class="ri-global-line"></i> Web Widget
            </a>
            <a href="{{ route('api-keys.index') }}" class="app-btn app-btn-secondary text-xs">
                <i class="ri-terminal-box-line"></i> REST API
            </a>
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

    {{-- Meta / Webhook Endpoint Reference Card --}}
    <div class="app-card p-4 bg-gradient-to-r from-brand-500/10 via-light-surface dark:via-dark-surface to-light-surface dark:to-dark-surface border border-brand-500/20">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="app-badge app-badge-primary text-3xs font-mono uppercase">Webhook Configuration</span>
                    <span class="text-2xs text-light-muted dark:text-dark-muted">Meta for Developers / Facebook App Setup</span>
                </div>
                <h3 class="text-xs font-bold text-light-text dark:text-dark-text">
                    Incoming Webhook Endpoint URL & Verify Token
                </h3>
                <p class="text-3xs text-light-text-secondary dark:text-dark-text-secondary max-w-2xl">
                    Configure these credentials in your Meta Developer Console (<code class="text-brand-500">developers.facebook.com</code>) under Webhooks &gt; Messenger/WhatsApp subscription.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 flex-shrink-0">
                <div class="flex items-center gap-1.5 p-1.5 px-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-2xs font-mono">
                    <span class="text-light-muted dark:text-dark-muted">Verify Token:</span>
                    <strong class="text-brand-600 dark:text-brand-400 select-all">{{ $verifyToken }}</strong>
                    <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted ml-1" onclick="navigator.clipboard.writeText('{{ $verifyToken }}'); this.innerHTML = '<i class=\'ri-check-line text-emerald-500\'></i>'; setTimeout(() => this.innerHTML = '<i class=\'ri-file-copy-line\'></i>', 1500);" title="Copy Token">
                        <i class="ri-file-copy-line"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3 pt-3 border-t border-light-border dark:border-dark-border text-2xs font-mono">
            <div class="p-2 rounded bg-light-canvas/60 dark:bg-dark-canvas/60 border border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="truncate text-light-muted dark:text-dark-muted">Facebook URL: <code class="text-light-text dark:text-dark-text">{{ $webhookUrls['facebook'] }}</code></span>
                <button type="button" class="text-brand-500 hover:underline text-3xs ml-2 flex-shrink-0" onclick="navigator.clipboard.writeText('{{ $webhookUrls['facebook'] }}'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 1500);">Copy</button>
            </div>
            <div class="p-2 rounded bg-light-canvas/60 dark:bg-dark-canvas/60 border border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="truncate text-light-muted dark:text-dark-muted">WhatsApp URL: <code class="text-light-text dark:text-dark-text">{{ $webhookUrls['whatsapp'] }}</code></span>
                <button type="button" class="text-brand-500 hover:underline text-3xs ml-2 flex-shrink-0" onclick="navigator.clipboard.writeText('{{ $webhookUrls['whatsapp'] }}'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 1500);">Copy</button>
            </div>
        </div>
    </div>

    {{-- Channels Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        {{-- 1. Facebook Messenger Card --}}
        @php
            $fbAccounts = $connectedAccounts->get('facebook', collect());
        @endphp
        <div class="app-card overflow-hidden flex flex-col justify-between border-t-4 border-t-blue-600">
            <div class="p-4">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600/10 text-blue-600 flex items-center justify-center text-xl flex-shrink-0">
                        <i class="ri-facebook-circle-fill"></i>
                    </div>
                    @if($fbAccounts->count() > 0)
                        <span class="app-badge app-badge-success text-3xs uppercase">{{ $fbAccounts->count() }} Connected</span>
                    @else
                        <span class="app-badge app-badge-secondary text-3xs uppercase">Not Connected</span>
                    @endif
                </div>

                <h3 class="text-sm font-bold text-light-text dark:text-dark-text">Facebook Messenger</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted mt-1 leading-relaxed">
                    Automate direct customer inquiries on your official Facebook Business Page with 24/7 AI responses.
                </p>

                {{-- Connected Accounts List --}}
                @if($fbAccounts->count() > 0)
                    <div class="mt-4 space-y-2">
                        @foreach($fbAccounts as $acc)
                            <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center justify-between text-2xs">
                                <div class="truncate pr-2">
                                    <div class="font-semibold text-light-text dark:text-dark-text truncate">{{ $acc->display_name }}</div>
                                    <div class="text-3xs font-mono text-light-muted dark:text-dark-muted truncate">Page ID: {{ $acc->external_id }}</div>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <form method="POST" action="{{ route('channels.toggle', $acc) }}">
                                        @csrf
                                        <button type="submit" class="app-action-btn" title="{{ $acc->is_active ? 'Pause Channel' : 'Activate Channel' }}">
                                            <i class="{{ $acc->is_active ? 'ri-pause-circle-line text-amber-500' : 'ri-play-circle-line text-emerald-500' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('channels.destroy', $acc) }}" onsubmit="return confirm('Disconnect Facebook Page \'{{ $acc->display_name }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="app-action-btn hover:text-rose-500" title="Disconnect">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-3 bg-light-canvas/50 dark:bg-dark-canvas/50 border-t border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="text-3xs text-light-muted dark:text-dark-muted">Omnichannel Live Bot</span>
                <button type="button" class="app-btn app-btn-primary !h-7 !px-2.5 text-2xs flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#connectFacebookModal">
                    <i class="ri-add-line"></i>
                    <span>Connect Page</span>
                </button>
            </div>
        </div>

        {{-- 2. WhatsApp Business Cloud API Card --}}
        @php
            $waAccounts = $connectedAccounts->get('whatsapp', collect());
        @endphp
        <div class="app-card overflow-hidden flex flex-col justify-between border-t-4 border-t-emerald-500">
            <div class="p-4">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl flex-shrink-0">
                        <i class="ri-whatsapp-fill"></i>
                    </div>
                    @if($waAccounts->count() > 0)
                        <span class="app-badge app-badge-success text-3xs uppercase">{{ $waAccounts->count() }} Connected</span>
                    @else
                        <span class="app-badge app-badge-secondary text-3xs uppercase">Not Connected</span>
                    @endif
                </div>

                <h3 class="text-sm font-bold text-light-text dark:text-dark-text">WhatsApp Business Cloud</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted mt-1 leading-relaxed">
                    Connect official WhatsApp Business numbers to provide instant catalogue, ordering, and customer support.
                </p>

                {{-- Connected Accounts List --}}
                @if($waAccounts->count() > 0)
                    <div class="mt-4 space-y-2">
                        @foreach($waAccounts as $acc)
                            <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center justify-between text-2xs">
                                <div class="truncate pr-2">
                                    <div class="font-semibold text-light-text dark:text-dark-text truncate">{{ $acc->display_name }}</div>
                                    <div class="text-3xs font-mono text-light-muted dark:text-dark-muted truncate">Phone ID: {{ $acc->external_id }}</div>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <form method="POST" action="{{ route('channels.toggle', $acc) }}">
                                        @csrf
                                        <button type="submit" class="app-action-btn" title="{{ $acc->is_active ? 'Pause Channel' : 'Activate Channel' }}">
                                            <i class="{{ $acc->is_active ? 'ri-pause-circle-line text-amber-500' : 'ri-play-circle-line text-emerald-500' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('channels.destroy', $acc) }}" onsubmit="return confirm('Disconnect WhatsApp Number \'{{ $acc->display_name }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="app-action-btn hover:text-rose-500" title="Disconnect">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-3 bg-light-canvas/50 dark:bg-dark-canvas/50 border-t border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="text-3xs text-light-muted dark:text-dark-muted">Meta Cloud API</span>
                <button type="button" class="app-btn app-btn-primary !h-7 !px-2.5 text-2xs flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#connectWhatsAppModal">
                    <i class="ri-add-line"></i>
                    <span>Connect WhatsApp</span>
                </button>
            </div>
        </div>

        {{-- 3. Web Chat Widget Card --}}
        @php
            $webAccounts = $connectedAccounts->get('website', collect());
        @endphp
        <div class="app-card overflow-hidden flex flex-col justify-between border-t-4 border-t-brand-500">
            <div class="p-4">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl flex-shrink-0">
                        <i class="ri-global-line"></i>
                    </div>
                    <span class="app-badge app-badge-success text-3xs uppercase">Active</span>
                </div>

                <h3 class="text-sm font-bold text-light-text dark:text-dark-text">Storefront Web Widget</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted mt-1 leading-relaxed">
                    Embed an omnichannel AI floating chat bubble directly into your website, WordPress, or custom storefront.
                </p>

                <div class="mt-4 p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-2xs">
                    <div class="font-semibold text-light-text dark:text-dark-text">{{ $workspace->name }} Web Widget</div>
                    <div class="text-3xs text-emerald-500 mt-0.5 flex items-center gap-1">
                        <i class="ri-checkbox-circle-fill"></i> Ready for one-line script embedding
                    </div>
                </div>
            </div>

            <div class="p-3 bg-light-canvas/50 dark:bg-dark-canvas/50 border-t border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="text-3xs text-light-muted dark:text-dark-muted">Script Tag Ready</span>
                <a href="{{ route('widget.manage') }}" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex items-center gap-1.5">
                    <i class="ri-settings-4-line"></i>
                    <span>Configure & Snippet</span>
                </a>
            </div>
        </div>

        {{-- 4. Developer REST API Card --}}
        <div class="app-card overflow-hidden flex flex-col justify-between border-t-4 border-t-indigo-500">
            <div class="p-4">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-500 flex items-center justify-center text-xl flex-shrink-0">
                        <i class="ri-terminal-box-line"></i>
                    </div>
                    <span class="app-badge app-badge-primary text-3xs uppercase">Developer Ready</span>
                </div>

                <h3 class="text-sm font-bold text-light-text dark:text-dark-text">Programmatic REST API</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted mt-1 leading-relaxed">
                    Integrate your mobile apps, custom ERP, or internal tools using token-authenticated REST endpoints.
                </p>

                <div class="mt-4 p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-2xs font-mono">
                    <span class="text-light-muted dark:text-dark-muted">Endpoint:</span>
                    <code class="text-brand-500 block truncate">POST /api/v1/chat</code>
                </div>
            </div>

            <div class="p-3 bg-light-canvas/50 dark:bg-dark-canvas/50 border-t border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="text-3xs text-light-muted dark:text-dark-muted">API Keys & Docs</span>
                <a href="{{ route('api-keys.index') }}" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs flex items-center gap-1.5">
                    <i class="ri-key-line"></i>
                    <span>Manage Keys</span>
                </a>
            </div>
        </div>

        {{-- 5. Telegram Bot Card --}}
        @php
            $tgAccounts = $connectedAccounts->get('telegram', collect());
        @endphp
        <div class="app-card overflow-hidden flex flex-col justify-between border-t-4 border-t-sky-400">
            <div class="p-4">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-400/10 text-sky-400 flex items-center justify-center text-xl flex-shrink-0">
                        <i class="ri-telegram-fill"></i>
                    </div>
                    @if($tgAccounts->count() > 0)
                        <span class="app-badge app-badge-success text-3xs uppercase">{{ $tgAccounts->count() }} Connected</span>
                    @else
                        <span class="app-badge app-badge-secondary text-3xs uppercase">Not Connected</span>
                    @endif
                </div>

                <h3 class="text-sm font-bold text-light-text dark:text-dark-text">Telegram Bot Integration</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted mt-1 leading-relaxed">
                    Direct automated assistance inside Telegram groups or direct chats through standard BotFather tokens.
                </p>

                @if($tgAccounts->count() > 0)
                    <div class="mt-4 space-y-2">
                        @foreach($tgAccounts as $acc)
                            <div class="p-2.5 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center justify-between text-2xs">
                                <div class="truncate pr-2 font-semibold text-light-text dark:text-dark-text">{{ $acc->display_name }}</div>
                                <form method="POST" action="{{ route('channels.destroy', $acc) }}" onsubmit="return confirm('Disconnect Telegram bot?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="app-action-btn hover:text-rose-500"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="p-3 bg-light-canvas/50 dark:bg-dark-canvas/50 border-t border-light-border dark:border-dark-border flex items-center justify-between">
                <span class="text-3xs text-light-muted dark:text-dark-muted">BotFather API</span>
                <button type="button" class="app-btn app-btn-primary !h-7 !px-2.5 text-2xs flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#connectTelegramModal">
                    <i class="ri-add-line"></i>
                    <span>Connect Telegram</span>
                </button>
            </div>
        </div>

    </div>
</div>

{{-- Modal: Connect Facebook Page --}}
<div class="modal fade" id="connectFacebookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-facebook-circle-fill text-blue-600"></i>
                    <span>Connect Facebook Business Page</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form action="{{ route('channels.store') }}" method="POST">
                @csrf
                <input type="hidden" name="channel_slug" value="facebook">
                <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">

                <div class="space-y-3.5">
                    <div>
                        <label for="fb_display_name" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Page Name / Display Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="fb_display_name" name="display_name" required placeholder="e.g. Apex Electronics Official" class="app-input w-full text-xs">
                    </div>

                    <div>
                        <label for="fb_external_id" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Facebook Page ID <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="fb_external_id" name="external_id" required placeholder="e.g. 104928374928192" class="app-input w-full text-xs font-mono">
                        <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">Found in your Facebook Page About &gt; Page transparency section.</p>
                    </div>

                    <div>
                        <label for="fb_access_token" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Page Access Token <span class="text-rose-500">*</span>
                        </label>
                        <textarea id="fb_access_token" name="access_token" required rows="3" placeholder="EAAB..." class="app-textarea w-full text-xs font-mono"></textarea>
                        <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">Generated from your Meta App Console under Messenger API Settings.</p>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5">
                        <i class="ri-checkbox-circle-line"></i>
                        <span>Save & Connect Page</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Connect WhatsApp Business --}}
<div class="modal fade" id="connectWhatsAppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-whatsapp-fill text-emerald-500"></i>
                    <span>Connect WhatsApp Business Cloud API</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form action="{{ route('channels.store') }}" method="POST">
                @csrf
                <input type="hidden" name="channel_slug" value="whatsapp">
                <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">

                <div class="space-y-3.5">
                    <div>
                        <label for="wa_display_name" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Phone Label / Display Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="wa_display_name" name="display_name" required placeholder="e.g. Sales Desk (+8801700000000)" class="app-input w-full text-xs">
                    </div>

                    <div>
                        <label for="wa_external_id" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            WhatsApp Phone Number ID <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="wa_external_id" name="external_id" required placeholder="e.g. 109283746592817" class="app-input w-full text-xs font-mono">
                        <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">Found in Meta App Dashboard &gt; WhatsApp &gt; API Setup &gt; Phone number ID.</p>
                    </div>

                    <div>
                        <label for="wa_access_token" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            System User Access Token <span class="text-rose-500">*</span>
                        </label>
                        <textarea id="wa_access_token" name="access_token" required rows="3" placeholder="EAAB..." class="app-textarea w-full text-xs font-mono"></textarea>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5">
                        <i class="ri-checkbox-circle-line"></i>
                        <span>Save & Connect WhatsApp</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Connect Telegram --}}
<div class="modal fade" id="connectTelegramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-telegram-fill text-sky-400"></i>
                    <span>Connect Telegram Bot</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form action="{{ route('channels.store') }}" method="POST">
                @csrf
                <input type="hidden" name="channel_slug" value="telegram">
                <input type="hidden" name="workspace_id" value="{{ $workspace->id }}">

                <div class="space-y-3.5">
                    <div>
                        <label for="tg_display_name" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Bot Username / Label <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="tg_display_name" name="display_name" required placeholder="@YourBrandBot" class="app-input w-full text-xs">
                    </div>

                    <div>
                        <label for="tg_external_id" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Bot Username (without @) <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="tg_external_id" name="external_id" required placeholder="YourBrandBot" class="app-input w-full text-xs font-mono">
                    </div>

                    <div>
                        <label for="tg_access_token" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            BotFather API Token <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="tg_access_token" name="access_token" required placeholder="123456789:ABCdefGHIjklMNO..." class="app-input w-full text-xs font-mono">
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5">
                        <i class="ri-checkbox-circle-line"></i>
                        <span>Save & Connect Telegram</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
