<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\ChannelAccount;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChannelAccountResolver
{
    public function resolve(string $channelSlug, string $externalId): ChannelAccount {

        $channel = Channel::where('slug',$channelSlug)->firstOrFail();

        Log::info('Resolver Debug', [
            'channel_id' => $channel->id,
            'external_id' => $externalId,
        ]);

        $account = ChannelAccount::where('channel_id',$channel->id)->where('external_id',$externalId)->first();

        if (!$account) {
            throw new RuntimeException(
                "Channel Account not found."
            );
        }
        return $account;
    }

}