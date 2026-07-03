<?php

namespace App\Services\CRM;

use App\Models\Conversation;
use App\Models\CRMContact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CRMService
{

    public function sync(Conversation $conversation,array $entities): CRMContact {

        $conversation->loadMissing('channelAccount.channel');

        return DB::transaction(function () use ($conversation, $entities) {

            $contact = CRMContact::firstOrCreate(
                [
                    'workspace_id'    => $conversation->channelAccount->workspace_id,
                    'external_user_id'=> $conversation->external_user_id,
                ],
                [
                    'conversation_id' => $conversation->id,
                    'source'          => $conversation->channelAccount->channel->slug,
                    'name'            => $conversation->customer_name,
                    'avatar'          => $conversation->customer_avatar,
                ]
            );

            // Keep contact information updated
            $contact->update([
                'conversation_id' => $conversation->id,
                'name'            => $conversation->customer_name,
                'avatar'          => $conversation->customer_avatar,
            ]);

            foreach (array_unique($entities['contact']['phones'] ?? []) as $phone) {
                $contact->phones()->firstOrCreate(
                    ['phone' => $phone],
                    ['is_primary' => !$contact->phones()->exists()]
                );
            }

            foreach (array_unique($entities['contact']['emails'] ?? []) as $email) {
                $contact->emails()->firstOrCreate(
                    ['email' => $email],
                    ['is_primary' => !$contact->emails()->exists()]
                );
            }

            foreach (array_unique($entities['contact']['websites'] ?? []) as $website) {
                $contact->websites()->firstOrCreate(
                    ['website' => $website],
                    ['is_primary' => !$contact->websites()->exists()]
                );
            }

            return $contact;
        });
    }
}
