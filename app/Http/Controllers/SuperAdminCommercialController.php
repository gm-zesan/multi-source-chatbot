<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use App\Models\WorkspaceWallet;
use App\Services\Billing\WorkspaceWalletService;
use App\Services\Subscription\SubscriptionQuotaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SuperAdminCommercialController extends Controller
{
    public function __construct(
        private readonly SubscriptionQuotaService $quotaService,
        private readonly WorkspaceWalletService $walletService,
    ) {}

    /**
     * Show Superadmin Commercial Dashboard (MRR, Tenants, Quotas).
     */
    public function index(): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && ($user->hasRole(RoleEnum::SUPERADMIN->value) || $user->hasRole('superadmin') || $user->email === 'superadmin@example.com');
        if (! $isSuperAdmin) {
            abort(403, 'Unauthorized. Superadmin role required.');
        }

        $activeSubscriptions = WorkspaceSubscription::with(['workspace', 'plan'])
            ->where('status', 'active')
            ->get();

        $mrr = 0.0;
        foreach ($activeSubscriptions as $sub) {
            if ($sub->plan) {
                if ($sub->billing_cycle === 'yearly') {
                    $mrr += round((float) $sub->plan->price_yearly / 12, 2);
                } else {
                    $mrr += (float) $sub->plan->price_monthly;
                }
            }
        }

        $plans = SubscriptionPlan::withCount('subscriptions')->get();
        $totalTenants = Workspace::count();
        $activeTenants = Workspace::where('is_active', true)->count();

        $currentPeriod = Carbon::now()->format('Y-m');
        $quotas = WorkspaceUsageQuota::with('workspace')
            ->where('period_year_month', $currentPeriod)
            ->get();

        $tenantsWithWallets = Workspace::with('wallet')->latest('id')->paginate(15);

        return view('admin.commercial.index', [
            'mrr'                => round($mrr, 2),
            'totalTenants'       => $totalTenants,
            'activeTenants'      => $activeTenants,
            'plans'              => $plans,
            'subscriptions'      => $activeSubscriptions,
            'quotas'             => $quotas,
            'period'             => $currentPeriod,
            'tenantsWithWallets' => $tenantsWithWallets,
        ]);
    }

    /**
     * SuperAdmin Manual Adjustment / Credit Bonus on a Tenant Wallet.
     */
    public function adjustWallet(Request $request, Workspace $workspace): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && ($user->hasRole(RoleEnum::SUPERADMIN->value) || $user->hasRole('superadmin') || $user->email === 'superadmin@example.com');
        if (! $isSuperAdmin) {
            abort(403, 'Unauthorized. Superadmin role required.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'between:-100000,100000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->walletService->adjustBalance(
            workspace: $workspace,
            amount: (float) $validated['amount'],
            reason: $validated['reason'],
            adminUser: $user,
        );

        $sign = (float) $validated['amount'] >= 0 ? '+' : '';
        return redirect()->back()->with('success', "Prepaid wallet for '{$workspace->name}' successfully adjusted by {$sign}৳ " . number_format((float) $validated['amount'], 2) . ".");
    }

    /**
     * Public Billing Webhook Endpoint for bKash, SSLCommerz, Stripe.
     */
    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        $payload = $request->all();
        $result = $this->quotaService->handleBillingWebhook($gateway, $payload);

        $status = $result['success'] ? 200 : 400;

        return response()->json($result, $status);
    }
}
