<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\Conversation;
use App\Models\CRMContact;
use Illuminate\Support\Facades\DB;

class CRMService
{
    public function __construct(
        protected IdentityResolver $identityResolver,
        protected EntityExtractor $extractor,
        protected EntityNormalizer $normalizer,
    ) {}

    /**
     * Extract and normalize CRM entities from raw message text.
     *
     * @return array<string, mixed>
     */
    public function extractEntities(?string $text): array
    {
        $raw = $this->extractor->extract($text);

        return $this->normalizer->normalize($raw);
    }

    /**
     * Process message for a workspace: extracts entities, normalizes, and saves if present.
     * Returns structured diagnostic payload.
     *
     * @return array{
     *     has_data: bool,
     *     db_saved: bool,
     *     contact: ?CRMContact,
     *     contact_id: ?int,
     *     emails: array<string>,
     *     phones: array<string>,
     *     websites: array<string>,
     *     nid: ?string,
     * }
     */
    public function processForWorkspace(int $workspaceId, string $text, ?string $name = null): array
    {
        $entities = $this->extractEntities($text);

        $emails   = $entities['contact']['emails'] ?? [];
        $phones   = $entities['contact']['phones'] ?? [];
        $websites = $entities['contact']['websites'] ?? [];
        $nid      = $entities['document']['nid'] ?? null;

        $hasData = ! empty($emails) || ! empty($phones) || ! empty($websites) || ! empty($nid);

        $savedContact = null;
        if ($hasData) {
            $savedContact = $this->syncForWorkspace($workspaceId, $entities, $name);
        }

        return [
            'has_data'   => $hasData,
            'db_saved'   => $savedContact !== null,
            'contact'    => $savedContact,
            'contact_id' => $savedContact?->id,
            'emails'     => $emails,
            'phones'     => $phones,
            'websites'   => $websites,
            'nid'        => $nid,
        ];
    }

    /**
     * Sync extracted contact entities for a specific conversation.
     */
    public function sync(Conversation $conversation, ?array $entities = null): CRMContact
    {
        $conversation->loadMissing('channelAccount.channel');

        if ($entities === null) {
            $lastMessage = $conversation->messages()->where('direction', 'inbound')->latest('id')->first();
            $entities = $this->extractEntities($lastMessage?->body ?? '');
        }

        return DB::transaction(function () use ($conversation, $entities) {
            $contact = $this->identityResolver->resolve($conversation);

            $this->persistEntities($contact, $entities);

            return $contact;
        });
    }

    /**
     * Sync extracted contact entities directly for a workspace.
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
            $emails = array_unique($entities['contact']['emails'] ?? []);
            $phones = array_unique($entities['contact']['phones'] ?? []);

            $contact = null;

            if (! empty($emails)) {
                $contact = CRMContact::where('workspace_id', $workspaceId)
                    ->whereHas('emails', fn ($q) => $q->whereIn('email', $emails))
                    ->first();
            }

            if (! $contact && ! empty($phones)) {
                $contact = CRMContact::where('workspace_id', $workspaceId)
                    ->whereHas('phones', fn ($q) => $q->whereIn('phone', $phones))
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

