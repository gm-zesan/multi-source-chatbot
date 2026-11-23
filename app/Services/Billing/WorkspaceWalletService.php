<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceWallet;
use App\Models\WorkspaceWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceWalletService
{
    /**
     * Predefined messaging packages available for instant merchant purchase.
     *
     * @var array<string, array{messages: int, price: float, title: string, description: string}>
     */
    public const MESSAGING_PACKAGES = [
        'starter' => [
            'messages'    => 500,
            'price'       => 200.0,
            'title'       => 'স্টার্টার প্যাক (Starter Pack)',
            'description' => '৫০০টি অতিরিক্ত WhatsApp মেসেজ কোটা',
        ],
        'growth' => [
            'messages'    => 1000,
            'price'       => 400.0,
            'title'       => 'গ্রোথ প্যাক (Growth Pack)',
            'description' => '১,০০০টি অতিরিক্ত WhatsApp মেসেজ কোটা',
        ],
        'scale' => [
            'messages'    => 2500,
            'price'       => 900.0,
            'title'       => 'স্কেল প্যাক (Scale Pack)',
            'description' => '২,৫০০টি অতিরিক্ত WhatsApp মেসেজ কোটা',
        ],
    ];
    /**
     * Get or initialize a workspace prepaid wallet.
     */
    public function getOrCreateWallet(Workspace $workspace): WorkspaceWallet
    {
        $wallet = WorkspaceWallet::where('workspace_id', $workspace->id)->first();

        if (! $wallet) {
            $wallet = DB::transaction(function () use ($workspace) {
                $meteredPlan = SubscriptionPlan::where('slug', 'metered-billing')->first();
                $starterPlan = SubscriptionPlan::where('slug', 'starter-bonus')->first();

                $defaultRate = $meteredPlan ? (float) $meteredPlan->price_monthly : 0.40;
                $starterBonus = $starterPlan ? (float) $starterPlan->price_monthly : 50.00;
                $starterMsgs = $starterPlan && $starterPlan->max_conversations_monthly > 0
                    ? (int) $starterPlan->max_conversations_monthly
                    : (int) floor($starterBonus / max(0.01, $defaultRate));

                $newWallet = WorkspaceWallet::create([
                    'workspace_id'          => $workspace->id,
                    'balance'               => $starterBonus,
                    'currency'              => 'BDT',
                    'rate_per_message'      => $defaultRate,
                    'is_frozen'             => false,
                    'low_balance_threshold' => 20.00,
                ]);

                WorkspaceWalletTransaction::create([
                    'wallet_id'     => $newWallet->id,
                    'workspace_id'  => $workspace->id,
                    'type'          => 'credit',
                    'category'      => 'bonus',
                    'amount'        => $starterBonus,
                    'balance_after' => $starterBonus,
                    'reference_id'  => 'starter_bonus_' . Str::random(8),
                    'description'   => "Welcome Starter Bonus ({$starterMsgs} Free AI Messages)",
                ]);

                return $newWallet;
            });
        }

        return $wallet;
    }

    /**
     * Check if a workspace has enough balance to send an AI message.
     */
    public function canAffordMessage(int $workspaceId): bool
    {
        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return false;
        }

        $wallet = $this->getOrCreateWallet($workspace);

        return $wallet->canAffordMessage();
    }

    /**
     * Deduct ৳0.40 BDT for an AI message response.
     *
     * @return array{success: bool, deducted: float, balance: float, error?: string}
     */
    public function deductMessageUsage(
        int $workspaceId,
        ?string $messageRef = null,
        string $channel = 'Web Widget'
    ): array {
        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return ['success' => false, 'deducted' => 0.0, 'balance' => 0.0, 'error' => 'Workspace not found'];
        }

        return DB::transaction(function () use ($workspace, $messageRef, $channel) {
            /** @var WorkspaceWallet $wallet */
            $wallet = WorkspaceWallet::where('workspace_id', $workspace->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = $this->getOrCreateWallet($workspace);
                $wallet = WorkspaceWallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            $rate = (float) $wallet->rate_per_message;

            // Check balance
            if ((float) $wallet->balance < $rate) {
                $wallet->is_frozen = true;
                $wallet->save();

                return [
                    'success'  => false,
                    'deducted' => 0.0,
                    'balance'  => (float) $wallet->balance,
                    'error'    => 'Insufficient wallet balance. Please recharge your account.',
                ];
            }

            // Deduct
            $newBalance = round((float) $wallet->balance - $rate, 2);
            $wallet->balance = $newBalance;
            if ($newBalance < $rate) {
                $wallet->is_frozen = true;
            }
            $wallet->save();

            // Record transaction
            WorkspaceWalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'workspace_id'  => $workspace->id,
                'type'          => 'debit',
                'category'      => 'message_usage',
                'amount'        => $rate,
                'balance_after' => $newBalance,
                'reference_id'  => $messageRef,
                'description'   => "AI Response Generation ({$channel})",
                'meta'          => [
                    'channel' => $channel,
                    'rate'    => $rate,
                ],
            ]);

            return [
                'success'  => true,
                'deducted' => $rate,
                'balance'  => $newBalance,
            ];
        });
    }

    /**
     * Process a wallet recharge (e.g. bKash, Nagad, Card).
     */
    public function recharge(
        Workspace $workspace,
        float $amount,
        string $gateway = 'bKash',
        ?string $trxId = null
    ): WorkspaceWalletTransaction {
        return DB::transaction(function () use ($workspace, $amount, $gateway, $trxId) {
            /** @var WorkspaceWallet $wallet */
            $wallet = WorkspaceWallet::where('workspace_id', $workspace->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = $this->getOrCreateWallet($workspace);
                $wallet = WorkspaceWallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            $amount = round($amount, 2);
            $newBalance = round((float) $wallet->balance + $amount, 2);

            $wallet->balance = $newBalance;
            $wallet->is_frozen = false; // Unfreeze
            $wallet->save();

            $txId = $trxId ?: 'trx_' . Str::upper(Str::random(10));

            return WorkspaceWalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'workspace_id'  => $workspace->id,
                'type'          => 'credit',
                'category'      => 'recharge',
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'reference_id'  => $txId,
                'description'   => "Prepaid Wallet Recharge via " . strtoupper($gateway),
                'meta'          => [
                    'gateway'        => $gateway,
                    'transaction_id' => $txId,
                ],
            ]);
        });
    }

    /**
     * SuperAdmin Manual Adjustment / Credit Bonus.
     */
    public function adjustBalance(
        Workspace $workspace,
        float $amount,
        string $reason,
        ?User $adminUser = null
    ): WorkspaceWalletTransaction {
        return DB::transaction(function () use ($workspace, $amount, $reason, $adminUser) {
            $wallet = $this->getOrCreateWallet($workspace);
            $wallet = WorkspaceWallet::where('id', $wallet->id)->lockForUpdate()->first();

            $amount = round($amount, 2);
            $newBalance = round((float) $wallet->balance + $amount, 2);

            $wallet->balance = $newBalance;
            if ($newBalance > (float) $wallet->rate_per_message) {
                $wallet->is_frozen = false;
            }
            $wallet->save();

            return WorkspaceWalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'workspace_id'  => $workspace->id,
                'type'          => $amount >= 0 ? 'credit' : 'debit',
                'category'      => 'adjustment',
                'amount'        => abs($amount),
                'balance_after' => $newBalance,
                'reference_id'  => 'adj_' . Str::random(8),
                'description'   => "Admin Adjustment: {$reason}" . ($adminUser ? " by {$adminUser->name}" : ''),
            ]);
        });
    }

    /**
     * Purchase a messaging package using workspace wallet balance.
     *
     * @return array{success: bool, package?: array, wallet?: WorkspaceWallet, quota?: \App\Models\WorkspaceUsageQuota, error?: string}
     */
    public function purchaseMessagingPackage(Workspace $workspace, string $packageKey): array
    {
        if (! isset(self::MESSAGING_PACKAGES[$packageKey])) {
            return ['success' => false, 'error' => 'Invalid messaging package selected.'];
        }

        $pack = self::MESSAGING_PACKAGES[$packageKey];
        $price = (float) $pack['price'];
        $messages = (int) $pack['messages'];

        return DB::transaction(function () use ($workspace, $packageKey, $pack, $price, $messages) {
            $wallet = $this->getOrCreateWallet($workspace);
            $wallet = WorkspaceWallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ((float) $wallet->balance < $price) {
                $deficit = round($price - (float) $wallet->balance, 2);
                return [
                    'success' => false,
                    'error'   => "ওয়ালেটে পর্যাপ্ত ব্যালেন্স নেই। প্যাকেজটির মূল্য ৳" . number_format($price, 2) . " BDT, আপনার ব্যালেন্স ৳" . number_format((float) $wallet->balance, 2) . " BDT (ঘাটতি: ৳" . number_format($deficit, 2) . " BDT)। অনুগ্রহ করে ওয়ালেট রিচার্জ করুন।",
                ];
            }

            $newBalance = round((float) $wallet->balance - $price, 2);
            $wallet->balance = $newBalance;
            $wallet->save();

            // Record transaction
            WorkspaceWalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'workspace_id'  => $workspace->id,
                'type'          => 'debit',
                'category'      => 'package_purchase',
                'amount'        => $price,
                'balance_after' => $newBalance,
                'reference_id'  => 'pkg_' . Str::upper(Str::random(10)),
                'description'   => "Purchased {$pack['title']} ({$messages} Extra WhatsApp Messages)",
                'meta'          => [
                    'package_key' => $packageKey,
                    'messages'    => $messages,
                    'price'       => $price,
                ],
            ]);

            // Update Workspace Usage Quota: increment WhatsApp limit & clear exhausted guard
            $quota = \App\Models\WorkspaceUsageQuota::currentForWorkspace($workspace->id);
            $quota->increment('whatsapp_free_limit', $messages);
            $quota->quota_exhausted_sent_at = null;
            $quota->quota_warning_sent_at = null;
            $quota->save();

            return [
                'success' => true,
                'package' => $pack,
                'wallet'  => $wallet,
                'quota'   => $quota,
            ];
        });
    }
}
