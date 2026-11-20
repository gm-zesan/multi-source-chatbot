<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChannelIntegrationController extends Controller
{
    /**
     * Show Omnichannel Integrations Hub (Facebook, WhatsApp, Web Widget, Telegram).
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        // Ensure default channels exist
        $this->ensureChannelsExist();

        $channels = Channel::where('is_active', true)->get();
        $connectedAccounts = ChannelAccount::where('workspace_id', $workspace->id)
            ->with('channel')
            ->get()
            ->groupBy(fn($item) => $item->channel?->slug);

        $verifyToken = config('services.facebook.verify_token') ?: 'ea_webhook_verify_token_2026';

        return view('admin.channels.index', [
            'workspace'         => $workspace,
            'channels'          => $channels,
            'connectedAccounts' => $connectedAccounts,
            'webhookUrls'       => [
                'facebook' => url('/webhook/facebook'),
                'whatsapp' => url('/webhook/whatsapp'),
                'telegram' => url('/webhook/telegram'),
            ],
            'verifyToken'       => $verifyToken,
        ]);
    }

    /**
     * Connect or update a social channel account (Facebook Page, WhatsApp Business, etc.).
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'channel_slug' => ['required', 'string', 'exists:channels,slug'],
            'display_name' => ['required', 'string', 'max:150'],
            'external_id'  => ['required', 'string', 'max:150'],
            'access_token' => ['required', 'string', 'max:1000'],
            'settings'     => ['nullable', 'array'],
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
        ]);

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && !empty($validated['workspace_id']))
            ? (int) $validated['workspace_id']
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);
        $channel = Channel::where('slug', $validated['channel_slug'])->firstOrFail();

        $account = ChannelAccount::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'channel_id'   => $channel->id,
                'external_id'  => trim($validated['external_id']),
            ],
            [
                'name'         => $validated['display_name'],
                'display_name' => $validated['display_name'],
                'access_token' => trim($validated['access_token']),
                'settings'     => $validated['settings'] ?? [],
                'is_active'    => true,
            ]
        );

        $channelTitle = ucfirst($channel->slug);

        return redirect()->route('channels.index', $user->hasRole(RoleEnum::SUPERADMIN->value) ? ['workspace_id' => $workspace->id] : [])
            ->with('success', "{$channelTitle} channel '{$account->display_name}' successfully connected!");
    }

    /**
     * Toggle channel account active/inactive status.
     */
    public function toggle(ChannelAccount $channelAccount): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole(RoleEnum::SUPERADMIN->value) && $channelAccount->workspace_id !== $user->workspace_id) {
            abort(403, 'Unauthorized access to channel account.');
        }

        $channelAccount->is_active = ! $channelAccount->is_active;
        $channelAccount->save();

        $statusText = $channelAccount->is_active ? 'activated' : 'paused';

        return redirect()->back()->with('success', "Channel '{$channelAccount->display_name}' has been {$statusText}.");
    }

    /**
     * Delete / Disconnect a channel account.
     */
    public function destroy(ChannelAccount $channelAccount): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole(RoleEnum::SUPERADMIN->value) && $channelAccount->workspace_id !== $user->workspace_id) {
            abort(403, 'Unauthorized access to channel account.');
        }

        $name = $channelAccount->display_name ?: $channelAccount->name;
        $channelAccount->delete();

        return redirect()->back()->with('success', "Channel '{$name}' has been disconnected.");
    }

    private function ensureChannelsExist(): void
    {
        $defaultChannels = [
            ['slug' => 'facebook', 'name' => 'Facebook Messenger', 'icon' => 'ri-facebook-circle-fill'],
            ['slug' => 'whatsapp', 'name' => 'WhatsApp Business',  'icon' => 'ri-whatsapp-fill'],
            ['slug' => 'website',  'name' => 'Storefront Widget',   'icon' => 'ri-global-line'],
            ['slug' => 'api',      'name' => 'Developer REST API',  'icon' => 'ri-terminal-box-line'],
            ['slug' => 'telegram', 'name' => 'Telegram Bot',        'icon' => 'ri-telegram-fill'],
        ];

        foreach ($defaultChannels as $ch) {
            Channel::firstOrCreate(
                ['slug' => $ch['slug']],
                ['name' => $ch['name'], 'icon' => $ch['icon'], 'is_active' => true]
            );
        }
    }
}
