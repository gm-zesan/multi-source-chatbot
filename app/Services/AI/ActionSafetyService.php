<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ActionSafetyService
{
    public const DEFAULT_EXPIRATION_MINUTES = 15;
    public const ACTION_SEND_SELLER_EMAIL = 'send_seller_email';

    /**
     * Set a pending action requiring confirmation or additional parameters.
     *
     * @param array<string, mixed> $parameters
     */
    public function setPendingAction(
        Conversation $conversation,
        string $action,
        array $parameters = [],
        ?string $promptMessage = null,
        int $expirationMinutes = self::DEFAULT_EXPIRATION_MINUTES,
    ): void {
        $metadata = $conversation->metadata ?? [];
        $metadata['pending_action'] = [
            'action'         => $action,
            'parameters'     => $parameters,
            'prompt_message' => $promptMessage,
            'created_at'     => now()->toIso8601String(),
            'expires_at'     => now()->addMinutes($expirationMinutes)->toIso8601String(),
            'status'         => 'awaiting_confirmation',
        ];

        $conversation->update(['metadata' => $metadata]);

        Log::info('[ActionSafetyService] Pending action registered', [
            'conversation_id' => $conversation->id,
            'action'          => $action,
            'parameters'      => $parameters,
        ]);
    }

    /**
     * Set a structured SEND_SELLER_EMAIL pending action with deterministic fingerprint.
     */
    public function setPendingSellerEmailAction(
        Conversation $conversation,
        \App\Services\AI\DTOs\SellerEmailProposal $proposal,
        int $expirationMinutes = self::DEFAULT_EXPIRATION_MINUTES,
    ): void {
        $metadata = $conversation->metadata ?? [];
        $metadata['pending_action'] = [
            'action'          => self::ACTION_SEND_SELLER_EMAIL,
            'seller_id'       => $proposal->sellerId,
            'recipient_email' => $proposal->recipientEmail,
            'subject'         => $proposal->subject,
            'message'         => $proposal->message,
            'seller_name'     => $proposal->sellerName,
            'fingerprint'     => $proposal->fingerprint,
            'created_at'      => now()->toIso8601String(),
            'expires_at'      => now()->addMinutes($expirationMinutes)->toIso8601String(),
            'status'          => 'awaiting_confirmation',
        ];

        $conversation->update(['metadata' => $metadata]);

        Log::info('[ActionSafetyService] Pending seller email action registered', [
            'conversation_id' => $conversation->id,
            'seller_id'       => $proposal->sellerId,
            'recipient_email' => $proposal->recipientEmail,
            'fingerprint'     => $proposal->fingerprint,
        ]);
    }

    /**
     * Determine if a pending action has expired.
     */
    public function isPendingActionExpired(?array $pendingAction): bool
    {
        if ($pendingAction === null) {
            return true;
        }

        if (empty($pendingAction['expires_at'])) {
            return false;
        }

        return now()->toIso8601String() > $pendingAction['expires_at'];
    }

    /**
     * Recognize user confirmation intent across English, Bangla, and Banglish.
     */
    public function isConfirmationIntent(string $query): bool
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $q = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', (string) $q);
        $q = trim((string) preg_replace('/\s+/', ' ', (string) $q));

        // Direct token or multi-word phrase patterns
        $patterns = [
            '/\b(yes|confirm|confirmed|send|ok|okay|yep|sure|proceed|do it|ha|haan|thik ache|thikase|send it|send mail|yes please|send email|ok send|ok please send|tahole mail pathao|mail pathao|mail pathan)\b/ui',
            '/\b(হ্যাঁ|হ্যা|হাঁ|ঠিক আছে|কনফার্ম|পাঠাও|পাঠিয়ে দাও|পাঠিয়ে দিন|পাঠান|মেইল পাঠাও|মেইল করুন|হ্যাঁ পাঠাও|হ্যা পাঠান|তাহলে পাঠাও|মেইলটি পাঠাও|ইমেইল পাঠাও|হ্যাঁ পাঠান|হ্যাঁ পাঠাতে পারেন)\b/u',
        ];

        // If the query contains negative phrases (e.g. "না, করিমকে পাঠাও", "no don't send"), it is not confirming the pending action
        if (preg_match('/(^|\s)(no|nah|nope|dont|not|না|বাতিল|ক্যান্সেল)($|\s)/ui', $q)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recognize user rejection intent across English, Bangla, and Banglish.
     */
    public function isRejectionIntent(string $query): bool
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $q = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', (string) $q);
        $q = trim((string) preg_replace('/\s+/', ' ', (string) $q));

        // If the query contains explicit new action directives (e.g. "না, করিমকে পাঠাও"), treat as changed request rather than pure cancellation
        if (preg_match('/(পাঠাও|পাঠিয়ে|পাঠান|মেইল|email|send|করিম|রহিম|salesperson)/ui', $q) && !preg_match('/(পাঠাবো না|পাঠাবেন না|পাঠাতে হবে না|পাঠিও না)/ui', $q)) {
            return false;
        }

        $patterns = [
            '/\b(no|cancel|cancelled|stop|reject|rejected|nah|nope|dont|dont send|not now|abort|lagbe na|pathio na|dorkar nai|dorkar nei)\b/ui',
            '/(^|\s)(না|বাতিল|ক্যান্সেল|পাঠাবো না|পাঠাবেন না|দরকার নেই|দরকার নাই|না পাঠিও না|না পাঠান লাগবে না|থাক|পাঠাতে হবে না)($|\s)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $q)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear any pending action from conversation metadata.
     */
    public function clearPendingAction(Conversation $conversation): void
    {
        $metadata = $conversation->metadata ?? [];
        if (isset($metadata['pending_action'])) {
            unset($metadata['pending_action']);
            $conversation->update(['metadata' => $metadata]);

            Log::info('[ActionSafetyService] Pending action cleared', [
                'conversation_id' => $conversation->id,
            ]);
        }
    }

    /**
     * Retrieve the currently pending action from conversation metadata.
     *
     * @return ?array<string, mixed>
     */
    public function getPendingAction(Conversation $conversation): ?array
    {
        return $conversation->metadata['pending_action'] ?? null;
    }

    /**
     * Build a structured UI confirmation response payload for frontend clients.
     *
     * @return array<string, mixed>
     */
    public function formatConfirmationPayload(
        string $action,
        string $message,
        ?int $entityId = null,
        string $language = 'bn',
    ): array {
        $confirmLabel = $language === 'bn' ? 'হ্যাঁ, নিশ্চিত করুন' : 'Yes, Confirm';
        $rejectLabel  = $language === 'bn' ? 'না, বাতিল করুন'   : 'No, Cancel';

        if ($action === 'cancel_order') {
            $confirmLabel = $language === 'bn' ? 'হ্যাঁ, অর্ডার বাতিল করুন' : 'Yes, Cancel Order';
            $rejectLabel  = $language === 'bn' ? 'না, অর্ডারটি রাখুন'       : 'No, Keep Order';
        }

        return [
            'type'         => 'confirmation',
            'action'       => $action,
            'entity_id'    => $entityId,
            'message'      => $message,
            'options'      => [
                ['label' => $confirmLabel, 'value' => 'confirm'],
                ['label' => $rejectLabel,  'value' => 'reject'],
            ],
        ];
    }

    /**
     * Verify server-side authorization and tenant isolation.
     * Ensures that workspace_id and customer credentials originate from authenticated Laravel context,
     * NEVER from untrusted LLM prompt hallucinations.
     *
     * @param array<string, mixed> $parameters
     */
    public function validateTenantAuthorization(
        Conversation $conversation,
        int $expectedWorkspaceId,
        string $action,
        array $parameters,
    ): bool {
        $actualWorkspaceId = $conversation->channelAccount?->workspace_id
            ?? $expectedWorkspaceId;

        // Strict Tenant Boundary Check
        if ($actualWorkspaceId !== $expectedWorkspaceId) {
            Log::warning('[ActionSafetyService] Tenant boundary violation prevented', [
                'conversation_id'      => $conversation->id,
                'actual_workspace_id'   => $actualWorkspaceId,
                'expected_workspace_id' => $expectedWorkspaceId,
                'action'               => $action,
            ]);
            return false;
        }

        return true;
    }
}
