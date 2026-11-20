<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceWalletTransaction;
use App\Services\Billing\WorkspaceWalletService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        protected WorkspaceWalletService $walletService,
    ) {}

    /**
     * Show Workspace Billing, Prepaid Wallet Balance, and Usage Ledger.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);
        $wallet = $this->walletService->getOrCreateWallet($workspace);

        $startOfMonth = Carbon::now()->startOfMonth();

        $totalSpentThisMonth = (float) WorkspaceWalletTransaction::where('workspace_id', $workspace->id)
            ->where('type', 'debit')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        $messagesThisMonth = WorkspaceWalletTransaction::where('workspace_id', $workspace->id)
            ->where('category', 'message_usage')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $transactions = WorkspaceWalletTransaction::where('workspace_id', $workspace->id)
            ->latest('id')
            ->paginate(15);

        return view('admin.billing.index', [
            'workspace'               => $workspace,
            'wallet'                  => $wallet,
            'remainingMessages'       => $wallet->estimatedRemainingMessages(),
            'totalSpentThisMonth'     => $totalSpentThisMonth,
            'messagesThisMonth'       => $messagesThisMonth,
            'transactions'            => $transactions,
            'rechargePresets'         => [500, 1000, 2000, 5000],
        ]);
    }

    /**
     * Process a wallet recharge submission (bKash / Nagad / Card).
     */
    public function recharge(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'amount'       => ['required', 'numeric', 'min:50', 'max:100000'],
            'gateway'      => ['required', 'string', 'in:bkash,nagad,card,manual'],
            'trx_id'       => ['nullable', 'string', 'max:100'],
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
        ]);

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && !empty($validated['workspace_id']))
            ? (int) $validated['workspace_id']
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        $tx = $this->walletService->recharge(
            workspace: $workspace,
            amount: (float) $validated['amount'],
            gateway: $validated['gateway'],
            trxId: $validated['trx_id'] ?? null,
        );

        return redirect()->route('billing.index', $user->hasRole(RoleEnum::SUPERADMIN->value) ? ['workspace_id' => $workspace->id] : [])
            ->with('success', "৳ " . number_format((float) $validated['amount'], 2) . " successfully added to your wallet via " . strtoupper($validated['gateway']) . "!");
    }
}
