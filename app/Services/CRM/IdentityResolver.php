<?php

namespace App\Services\CRM;

use App\Models\CRMContact;
use App\Models\CRMContactIdentity;
use App\Models\Conversation;

class IdentityResolver
{
    public function resolve(Conversation $conversation): CRMContact
    {
        $conversation->loadMissing('channelAccount.channel');

        $channel = $conversation->channelAccount->channel;

        // Existing Identity?
        $identity = CRMContactIdentity::where('channel_id', $channel->id)->where('external_user_id', $conversation->external_user_id)->first();

        if ($identity) {
            return $identity->contact;
        }

        // Create Contact
        $contact = CRMContact::create([
            'workspace_id' => $conversation->channelAccount->workspace_id,
            'conversation_id' => $conversation->id,
            'source' => $channel->slug,
            'name' => $conversation->customer_name,
            'avatar' => $conversation->customer_avatar,
        ]);

        // Create Identity
        $contact->identities()->create([
            'channel_id' => $channel->id,
            'external_user_id' => $conversation->external_user_id,
            'is_primary' => true,
        ]);

        return $contact;
    }
}
