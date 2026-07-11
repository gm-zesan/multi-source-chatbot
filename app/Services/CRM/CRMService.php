<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\Conversation;
use App\Models\CRMContact;
use Illuminate\Support\Facades\DB;

class CRMService
{

    public function __construct(protected IdentityResolver $identityResolver) {}

    public function sync(Conversation $conversation,array $entities): CRMContact {

        $conversation->loadMissing('channelAccount.channel');

        return DB::transaction(function () use ($conversation, $entities) {

            $contact = $this->identityResolver->resolve($conversation);

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
