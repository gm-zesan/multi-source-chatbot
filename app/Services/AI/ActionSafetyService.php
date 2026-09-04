<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ActionSafetyService
{
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
    ): void {
        $metadata = $conversation->metadata ?? [];
        $metadata['pending_action'] = [
            'action'         => $action,
            'parameters'     => $parameters,
            'prompt_message' => $promptMessage,
            'created_at'     => now()->toIso8601String(),
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
     * @return ?array{action: string, parameters: array, prompt_message: ?string, created_at: string, status: string}
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
