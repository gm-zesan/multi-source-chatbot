<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Mail\SellerNotificationMail;
use App\Models\AnalyticsSalesperson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SellerEmailService
{
    /**
     * Resolve a salesperson entity within the trusted workspace.
     *
     * @return array{
     *     status: 'resolved'|'ambiguous'|'not_found'|'inactive'|'missing_email',
     *     seller?: AnalyticsSalesperson,
     *     matches?: Collection<int, AnalyticsSalesperson>
     * }
     */
    public function resolveSeller(string $reference, int $workspaceId): array
    {
        $cleanRef = trim($reference);
        if ($cleanRef === '') {
            return ['status' => 'not_found'];
        }

        // Case 1: Explicit email provided
        if (filter_var($cleanRef, FILTER_VALIDATE_EMAIL)) {
            $seller = AnalyticsSalesperson::where('workspace_id', $workspaceId)
                ->where('email', strtolower($cleanRef))
                ->first();

            if (!$seller) {
                return ['status' => 'not_found'];
            }

            if (!$seller->is_active) {
                return ['status' => 'inactive', 'seller' => $seller];
            }

            return ['status' => 'resolved', 'seller' => $seller];
        }

        // Case 2: Clean reference from common Banglish honorifics/suffixes
        $normalizedName = preg_replace('/\b(bhai|vai|vaia|bhaia|sir|madam|ভাই|ভাইয়া|ভায়া|স্যার|ম্যাডাম)\b/ui', '', $cleanRef);
        $normalizedName = trim((string) preg_replace('/\s+/', ' ', (string) $normalizedName));
        if ($normalizedName === '') {
            $normalizedName = $cleanRef;
        }

        // First attempt: Exact match on name or employee_code
        $exactMatches = AnalyticsSalesperson::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->where(function ($query) use ($cleanRef, $normalizedName) {
                $query->where('name', $cleanRef)
                    ->orWhere('name', $normalizedName)
                    ->orWhere('employee_code', $cleanRef);
            })
            ->get();

        if ($exactMatches->count() === 1) {
            $seller = $exactMatches->first();
            if (empty($seller->email) || !filter_var($seller->email, FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'missing_email', 'seller' => $seller];
            }
            return ['status' => 'resolved', 'seller' => $seller];
        }

        if ($exactMatches->count() > 1) {
            return ['status' => 'ambiguous', 'matches' => $exactMatches];
        }

        // Second attempt: Substring / like match
        $partialMatches = AnalyticsSalesperson::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->where(function ($query) use ($normalizedName) {
                $query->where('name', 'like', "%{$normalizedName}%")
                    ->orWhere('employee_code', 'like', "%{$normalizedName}%");
            })
            ->get();

        if ($partialMatches->count() === 1) {
            $seller = $partialMatches->first();
            if (empty($seller->email) || !filter_var($seller->email, FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'missing_email', 'seller' => $seller];
            }
            return ['status' => 'resolved', 'seller' => $seller];
        }

        if ($partialMatches->count() > 1) {
            return ['status' => 'ambiguous', 'matches' => $partialMatches];
        }

        // Check if an inactive seller matched
        $inactiveMatch = AnalyticsSalesperson::where('workspace_id', $workspaceId)
            ->where('is_active', false)
            ->where(function ($query) use ($cleanRef, $normalizedName) {
                $query->where('name', 'like', "%{$cleanRef}%")
                    ->orWhere('name', 'like', "%{$normalizedName}%")
                    ->orWhere('employee_code', $cleanRef);
            })
            ->first();

        if ($inactiveMatch) {
            return ['status' => 'inactive', 'seller' => $inactiveMatch];
        }

        return ['status' => 'not_found'];
    }

    /**
     * Dispatch an email notification to a verified seller.
     * Enforces tenant boundary, active status, authoritative email, and deterministic fingerprint.
     *
     * @return array{
     *     success: bool,
     *     error_code?: string,
     *     message?: string,
     *     seller?: AnalyticsSalesperson,
     *     recipient_email?: string
     * }
     */
    public function sendSellerEmail(
        int $sellerId,
        string $subject,
        string $message,
        int $workspaceId,
        ?string $expectedFingerprint = null,
    ): array {
        // Step 1: Re-resolve seller strictly within tenant workspace
        $seller = AnalyticsSalesperson::where('id', $sellerId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if (!$seller) {
            Log::warning('[SellerEmailService] Seller not found in workspace', [
                'seller_id'    => $sellerId,
                'workspace_id' => $workspaceId,
            ]);
            return [
                'success'    => false,
                'error_code' => 'seller_not_found',
                'message'    => 'Seller not found or does not belong to the current workspace.',
            ];
        }

        // Step 2: Validate active status
        if (!$seller->is_active) {
            Log::warning('[SellerEmailService] Attempted to send email to inactive seller', [
                'seller_id'    => $sellerId,
                'workspace_id' => $workspaceId,
            ]);
            return [
                'success'    => false,
                'error_code' => 'seller_inactive',
                'message'    => 'Seller is inactive and cannot receive notifications.',
            ];
        }

        // Step 3: Validate authoritative email
        $authoritativeEmail = trim((string) $seller->email);
        if ($authoritativeEmail === '' || !filter_var($authoritativeEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('[SellerEmailService] Seller does not have a valid authoritative email', [
                'seller_id' => $sellerId,
            ]);
            return [
                'success'    => false,
                'error_code' => 'missing_email',
                'message'    => 'Seller does not have a valid authoritative email address.',
            ];
        }

        // Step 4: Validate subject and message content
        $cleanSubject = trim($subject);
        $cleanMessage = trim($message);
        if ($cleanSubject === '' || $cleanMessage === '') {
            return [
                'success'    => false,
                'error_code' => 'invalid_content',
                'message'    => 'Subject and message body must not be empty.',
            ];
        }

        // Step 5: Verify deterministic fingerprint if supplied
        if ($expectedFingerprint !== null) {
            $computedFingerprint = hash('sha256', implode('|', [
                (string) $workspaceId,
                (string) $sellerId,
                strtolower($authoritativeEmail),
                $cleanSubject,
                $cleanMessage,
            ]));

            if (!hash_equals($expectedFingerprint, $computedFingerprint)) {
                Log::warning('[SellerEmailService] Action fingerprint verification failed', [
                    'seller_id'            => $sellerId,
                    'workspace_id'         => $workspaceId,
                    'expected_fingerprint' => $expectedFingerprint,
                    'computed_fingerprint' => $computedFingerprint,
                ]);
                return [
                    'success'    => false,
                    'error_code' => 'fingerprint_mismatch',
                    'message'    => 'Action authorization fingerprint mismatch.',
                ];
            }
        }

        // Step 6: Dispatch email through Laravel Mailer
        try {
            Mail::to($authoritativeEmail)->send(new SellerNotificationMail(
                emailSubject: $cleanSubject,
                emailMessage: $cleanMessage,
                sellerName: $seller->name,
            ));

            Log::info('[SellerEmailService] Seller notification email dispatched successfully', [
                'seller_id'    => $sellerId,
                'workspace_id' => $workspaceId,
                'recipient'    => $authoritativeEmail,
            ]);

            return [
                'success'         => true,
                'seller'          => $seller,
                'recipient_email' => $authoritativeEmail,
            ];
        } catch (Throwable $e) {
            Log::error('[SellerEmailService] Mail transport dispatch failed: ' . $e->getMessage(), [
                'seller_id'    => $sellerId,
                'workspace_id' => $workspaceId,
            ]);

            return [
                'success'    => false,
                'error_code' => 'mail_transport_failure',
                'message'    => 'Mail transport service encountered an error while dispatching.',
            ];
        }
    }
}
