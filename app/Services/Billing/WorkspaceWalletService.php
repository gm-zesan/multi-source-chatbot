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
}
