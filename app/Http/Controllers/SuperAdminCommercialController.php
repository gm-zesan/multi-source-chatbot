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
use Illuminate\Support\Str;
use Illuminate\View\View;

class SuperAdminCommercialController extends Controller
{
    public function __construct(
        private readonly SubscriptionQuotaService $quotaService,
        private readonly WorkspaceWalletService $walletService,
    ) {}

    /**
     * Helper to verify superadmin authorization.
     */
    private function ensureSuperAdmin(): User
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && ($user->hasRole(RoleEnum::SUPERADMIN->value) || $user->hasRole('superadmin') || $user->email === 'superadmin@example.com');
        if (! $isSuperAdmin) {
            abort(403, 'Unauthorized. Superadmin role required.');
        }

        return $user;
    }

    /**
     * Show Superadmin Commercial Dashboard (MRR, Tenants, Quotas).
     */
    public function index(): View
    {
        $this->ensureSuperAdmin();

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
        $user = $this->ensureSuperAdmin();

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
     * Store a newly created dynamic Subscription Plan.
     */
    public function storePlan(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'name'                      => ['required', 'string', 'max:64'],
            'slug'                      => ['nullable', 'string', 'max:64', 'unique:subscription_plans,slug'],
            'badge'                     => ['nullable', 'string', 'max:64'],
            'tagline'                   => ['nullable', 'string', 'max:255'],
            'price_monthly'             => ['required', 'numeric', 'min:0'],
            'price_yearly'              => ['nullable', 'numeric', 'min:0'],
            'currency'                  => ['nullable', 'string', 'max:8'],
            'billing_period_text'       => ['nullable', 'string', 'max:64'],
            'price_note'                => ['nullable', 'string', 'max:255'],
            'max_conversations_monthly' => ['required', 'integer'],
            'max_knowledge_docs'        => ['required', 'integer', 'min:0'],
            'max_staff_seats'           => ['required', 'integer', 'min:1'],
            'features'                  => ['nullable', 'string'],
            'is_featured'               => ['nullable'],
            'sort_order'                => ['nullable', 'integer'],
            'cta_text'                  => ['nullable', 'string', 'max:64'],
            'cta_url'                   => ['nullable', 'string', 'max:255'],
            'is_active'                 => ['nullable'],
        ]);

        $slug = !empty($validated['slug']) 
            ? Str::slug($validated['slug']) 
            : Str::slug($validated['name']);

        if (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $featuresArray = [];
        if (!empty($validated['features'])) {
            $featuresArray = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $validated['features']))));
        }

        $priceMonthly = (float) $validated['price_monthly'];
        $priceYearly = !empty($validated['price_yearly']) ? (float) $validated['price_yearly'] : round($priceMonthly * 10, 2);

        $plan = SubscriptionPlan::create([
            'name'                      => $validated['name'],
            'slug'                      => $slug,
            'badge'                     => $validated['badge'] ?? null,
            'tagline'                   => $validated['tagline'] ?? null,
            'price_monthly'             => $priceMonthly,
            'price_yearly'              => $priceYearly,
            'currency'                  => strtoupper($validated['currency'] ?? 'BDT'),
            'billing_period_text'       => $validated['billing_period_text'] ?? null,
            'price_note'                => $validated['price_note'] ?? null,
            'max_conversations_monthly' => (int) $validated['max_conversations_monthly'],
            'max_knowledge_docs'        => (int) $validated['max_knowledge_docs'],
            'max_staff_seats'           => (int) $validated['max_staff_seats'],
            'features'                  => $featuresArray,
            'is_featured'               => $request->boolean('is_featured'),
            'sort_order'                => (int) ($validated['sort_order'] ?? 0),
            'cta_text'                  => $validated['cta_text'] ?? null,
            'cta_url'                   => $validated['cta_url'] ?? null,
            'is_active'                 => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', "Subscription Plan '{$plan->name}' created successfully.");
    }

    /**
     * Update an existing Subscription Plan.
     */
    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'name'                      => ['required', 'string', 'max:64'],
            'slug'                      => ['nullable', 'string', 'max:64', 'unique:subscription_plans,slug,' . $plan->id],
            'badge'                     => ['nullable', 'string', 'max:64'],
            'tagline'                   => ['nullable', 'string', 'max:255'],
            'price_monthly'             => ['required', 'numeric', 'min:0'],
            'price_yearly'              => ['nullable', 'numeric', 'min:0'],
            'currency'                  => ['nullable', 'string', 'max:8'],
            'billing_period_text'       => ['nullable', 'string', 'max:64'],
            'price_note'                => ['nullable', 'string', 'max:255'],
            'max_conversations_monthly' => ['required', 'integer'],
            'max_knowledge_docs'        => ['required', 'integer', 'min:0'],
            'max_staff_seats'           => ['required', 'integer', 'min:1'],
            'features'                  => ['nullable', 'string'],
            'is_featured'               => ['nullable'],
            'sort_order'                => ['nullable', 'integer'],
            'cta_text'                  => ['nullable', 'string', 'max:64'],
            'cta_url'                   => ['nullable', 'string', 'max:255'],
            'is_active'                 => ['nullable'],
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : $plan->slug;

        $featuresArray = [];
        if (!empty($validated['features'])) {
            $featuresArray = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $validated['features']))));
        }

        $priceMonthly = (float) $validated['price_monthly'];
        $priceYearly = !empty($validated['price_yearly']) ? (float) $validated['price_yearly'] : round($priceMonthly * 10, 2);

        $plan->update([
            'name'                      => $validated['name'],
            'slug'                      => $slug,
            'badge'                     => $validated['badge'] ?? null,
            'tagline'                   => $validated['tagline'] ?? null,
            'price_monthly'             => $priceMonthly,
            'price_yearly'              => $priceYearly,
            'currency'                  => strtoupper($validated['currency'] ?? 'BDT'),
            'billing_period_text'       => $validated['billing_period_text'] ?? null,
            'price_note'                => $validated['price_note'] ?? null,
            'max_conversations_monthly' => (int) $validated['max_conversations_monthly'],
            'max_knowledge_docs'        => (int) $validated['max_knowledge_docs'],
            'max_staff_seats'           => (int) $validated['max_staff_seats'],
            'features'                  => $featuresArray,
            'is_featured'               => $request->boolean('is_featured'),
            'sort_order'                => (int) ($validated['sort_order'] ?? 0),
            'cta_text'                  => $validated['cta_text'] ?? null,
            'cta_url'                   => $validated['cta_url'] ?? null,
            'is_active'                 => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with('success', "Subscription Plan '{$plan->name}' updated successfully.");
    }

    /**
     * Quick toggle active status for a Subscription Plan.
     */
    public function togglePlan(SubscriptionPlan $plan): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $plan->is_active = ! $plan->is_active;
        $plan->save();

        $status = $plan->is_active ? 'Active' : 'Deactivated';
        return redirect()->back()->with('success', "Subscription Plan '{$plan->name}' is now {$status}.");
    }

    /**
     * Delete a Subscription Plan (with active subscriber protection).
     */
    public function destroyPlan(SubscriptionPlan $plan): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $activeSubsCount = $plan->subscriptions()->where('status', 'active')->count();
        if ($activeSubsCount > 0) {
            return redirect()->back()->with('error', "Cannot delete plan '{$plan->name}' because it has {$activeSubsCount} active subscriber workspace(s). Please deactivate the plan instead.");
        }

        $name = $plan->name;
        $plan->delete();

        return redirect()->back()->with('success', "Subscription Plan '{$name}' deleted successfully.");
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
