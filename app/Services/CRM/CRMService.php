<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\Conversation;
use App\Models\CRMContact;
use Illuminate\Support\Facades\DB;

class CRMService
{
    public function __construct(protected IdentityResolver $identityResolver) {}

    /**
     * Sync extracted contact entities for a specific conversation.
     */
    public function sync(Conversation $conversation, array $entities): CRMContact
    {
        $conversation->loadMissing('channelAccount.channel');

        return DB::transaction(function () use ($conversation, $entities) {
            $contact = $this->identityResolver->resolve($conversation);

            $this->persistEntities($contact, $entities);

            return $contact;
        });
    }

    /**
     * Sync extracted contact entities directly for a workspace (e.g. via Chat Simulator or direct API).
     */
    public function syncForWorkspace(int $workspaceId, array $entities, ?string $name = null): ?CRMContact
    {
        $hasData = ! empty($entities['contact']['emails'] ?? [])
            || ! empty($entities['contact']['phones'] ?? [])
            || ! empty($entities['contact']['websites'] ?? []);

        if (! $hasData) {
            return null;
        }

        return DB::transaction(function () use ($workspaceId, $entities, $name) {
            $emails   = array_unique($entities['contact']['emails'] ?? []);
            $phones   = array_unique($entities['contact']['phones'] ?? []);

            // Lookup existing contact by email or phone in workspace
            $contact = null;

            if (! empty($emails)) {
                $contact = CRMContact::where('workspace_id', $workspaceId)
                    ->whereHas('emails', fn($q) => $q->whereIn('email', $emails))
                    ->first();
            }

            if (! $contact && ! empty($phones)) {
                $contact = CRMContact::where('workspace_id', $workspaceId)
                    ->whereHas('phones', fn($q) => $q->whereIn('phone', $phones))
                    ->first();
            }

            if (! $contact) {
                $contact = CRMContact::create([
                    'workspace_id' => $workspaceId,
                    'name'         => $name ?? ($emails[0] ?? $phones[0] ?? 'Extracted Contact'),
                    'source'       => 'simulator',
                ]);
            }

            $this->persistEntities($contact, $entities);

            return $contact;
        });
    }

    /**
     * Persist extracted phone, email, and website records onto a CRMContact.
     */
    protected function persistEntities(CRMContact $contact, array $entities): void
    {
        foreach (array_unique($entities['contact']['phones'] ?? []) as $phone) {
            $contact->phones()->firstOrCreate(
                ['phone' => $phone],
                ['is_primary' => ! $contact->phones()->exists()]
            );
        }

        foreach (array_unique($entities['contact']['emails'] ?? []) as $email) {
            $contact->emails()->firstOrCreate(
                ['email' => $email],
                ['is_primary' => ! $contact->emails()->exists()]
            );
        }

        foreach (array_unique($entities['contact']['websites'] ?? []) as $website) {
            $contact->websites()->firstOrCreate(
                ['website' => $website],
                ['is_primary' => ! $contact->websites()->exists()]
            );
        }
    }
}
