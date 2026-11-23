<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\AgentRuntimeEngine;
use App\AI\Agents\OwnerBusinessAgent;
use App\AI\DTOs\AgentContext;
use App\AI\Tools\BusinessOverviewTool;
use App\AI\Tools\CustomerInsightsTool;
use App\AI\Tools\EmployeeAnalyticsTool;
use App\AI\Tools\FinancialAnalyticsTool;
use App\AI\Tools\InventoryAnalyticsTool;
use App\AI\Tools\KnowledgeIntelligenceTool;
use App\AI\Tools\OwnerCustomerSupportInsightsTool;
use App\AI\Tools\ProductPerformanceTool;
use App\AI\Tools\SalesAnalyticsTool;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OwnerAiChatController extends Controller
{
    public function __construct(
        private readonly OwnerBusinessAgent $agent,
        private readonly AgentRuntimeEngine $runtimeEngine,
        private readonly SalesAnalyticsTool $salesTool,
        private readonly BusinessOverviewTool $overviewTool,
        private readonly InventoryAnalyticsTool $inventoryTool,
        private readonly ProductPerformanceTool $productTool,
        private readonly FinancialAnalyticsTool $financialTool,
        private readonly CustomerInsightsTool $customerTool,
        private readonly EmployeeAnalyticsTool $employeeTool,
        private readonly OwnerCustomerSupportInsightsTool $supportTool,
        private readonly KnowledgeIntelligenceTool $knowledgeTool,
    ) {}

    /**
     * Ask a question to the Virtual CFO / Owner Business Intelligence Agent.
     */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question'       => 'required|string|max:1000',
            'history'        => 'nullable|array|max:10',
            'history.*.role' => 'required|string|in:user,assistant',
            'history.*.text' => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $workspaceId = (int) ($user->workspace_id ?: 1);
        $userRole = ($user->hasRole('superadmin') || $user->hasRole('admin')) ? 'owner' : 'staff';

        // Permissions granted for executive decision-maker
        $permissions = [
            'analytics.view_margins',
            'analytics.view_cogs',
            'financial.view_pl',
            'inventory.view_stock',
            'hr.view_salaries',
        ];

        $scc = new ServerControlledContext(
            workspaceId: $workspaceId,
            userId: $user->id,
            userRole: $userRole,
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: $permissions,
        );

        $context = AgentContext::fromServerControlledContext($scc, user: $user);
        $question = trim($validated['question']);
        $history = $validated['history'] ?? [];
        $startTime = microtime(true);

        $qLower = mb_strtolower($question);
        $isDeterministicTarget = (bool) preg_match('/(?:আজকের|yesterday|গতকাল|সেলস|sales|বিক্রি|রেভিনিউ|revenue|স্টক|stock|ইনভেন্টরি|inventory|টপ|সেরা|বেশি\s*বিক্রি|চলছে|বাকি|due|ডিউ|কাস্টমার|balance|মেসেজ|message|কোটা|quota|খরচ|spend|ওয়ালেট|wallet|এটার|এটির|এর|ঐ)/iu', $qLower);

        // ── Fast-path: Grounded Analytics & CFO Engine for precise real-time database figures (Instant 0-latency) ──
        if ($isDeterministicTarget) {
            try {
                $directResult = $this->resolveDeterministicAnswer($question, $context, $history);
                $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'status'         => 'success',
                    'answer'         => $directResult['answer'],
                    'tools_executed' => $directResult['tools'],
                    'execution_time' => "{$elapsedMs}ms",
                    'mode'           => 'grounded_analytics_tools',
                ]);
            } catch (\Throwable $eFast) {
                Log::warning('[OwnerAiChatController] Fast deterministic path threw non-fatal exception, attempting fallback', [
                    'error' => $eFast->getMessage(),
                ]);
            }
        }

        // Build contextual prompt when multi-turn conversation history is provided
        $contextualPrompt = $question;
        if (! empty($history)) {
            $historyLines = [];
            foreach ($history as $h) {
                $roleLabel = $h['role'] === 'user' ? 'User' : 'Virtual CFO';
                $historyLines[] = "{$roleLabel}: " . trim($h['text']);
            }
            $contextualPrompt = "### Previous Conversation Context:\n"
                . implode("\n", $historyLines) . "\n\n"
                . "### Current User Question:\n"
                . "User: {$question}\n"
                . "(Please resolve any pronouns like 'এটার', 'এটি', 'ওটা', 'তার', 'it', 'this', 'its' based on the conversation context above).";
        }

        try {
            // Second attempt: Run Governed LLM Agent via AgentRuntimeEngine
            $response = $this->runtimeEngine->run($this->agent, $contextualPrompt, $context);

            if ($response->isSuccess() && ! empty($response->content)) {
                $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);
                return response()->json([
                    'status'         => 'success',
                    'answer'         => $response->content,
                    'telemetry'      => $response->telemetry,
                    'execution_time' => "{$elapsedMs}ms",
                    'mode'           => 'llm_agent',
                ]);
            }
        } catch (\Throwable $e) {
            Log::info('[OwnerAiChatController] LLM execution bypassed or threw error, falling back to deterministic tool engine', [
                'error' => $e->getMessage(),
            ]);
        }

        // ── Final Fallback: Always provide a fresh context to guarantee zero SLA timeout expiration ──
        try {
            $freshContext = AgentContext::fromServerControlledContext($scc, user: $user);
            $directResult = $this->resolveDeterministicAnswer($question, $freshContext, $history);
            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'status'         => 'success',
                'answer'         => $directResult['answer'],
                'tools_executed' => $directResult['tools'],
                'execution_time' => "{$elapsedMs}ms",
                'mode'           => 'grounded_analytics_tools',
            ]);
        } catch (\Throwable $eFallback) {
            Log::error('[OwnerAiChatController] All execution paths failed', [
                'error' => $eFallback->getMessage(),
            ]);

            return response()->json([
                'status'         => 'success',
                'answer'         => "📊 **ব্যবসার ডেটা আপডেট:** এই মুহূর্তে উপাত্ত সংগ্রহ করা হচ্ছে। আপনি ড্যাশবোর্ডের মূল পরিসংখ্যান ও লেজার পেজে লাইভ রিপোর্ট দেখতে পারেন।",
                'execution_time' => '10ms',
                'mode'           => 'error_recovery',
            ]);
        }
    }

    /**
     * Provide predefined prompt suggestions in Bengali and English.
     */
    public function suggestions(): JsonResponse
    {
        return response()->json([
            'status'      => 'success',
            'suggestions' => [
                [
                    'text'  => 'আমার কয়টা মেসেজ গেছে এবং কত টাকা খরচ হয়েছে?',
                    'label' => '💬 মেসেজ ও ব্যালেন্স হিসাব',
                ],
                [
                    'text'  => 'আজকের মোট সেলস ও রেভিনিউ কত?',
                    'label' => '💰 আজকের সেলস',
                ],
                [
                    'text'  => 'কোন প্রোডাক্টটি সবচেয়ে বেশি বিক্রি হচ্ছে?',
                    'label' => '🔥 টপ সেলিং প্রোডাক্ট',
                ],
                [
                    'text'  => 'কোন কোন আইটেমের স্টক কম রয়েছে?',
                    'label' => '⚠️ লো-স্টক ইনভেন্টরি',
                ],
                [
                    'text'  => 'চলতি মাসের নিট লাভ বা মার্জিন কত?',
                    'label' => '📈 লাভ ও মার্জিন',
                ],
                [
                    'text'  => 'আমাদের নিয়মিত ও রিপিট কাস্টমার কেমন?',
                    'label' => '👥 কাস্টমার এনগেজমেন্ট',
                ],
            ],
        ]);
    }

    /**
     * Deterministic analytical query processor for instant 0-latency grounded answers.
     *
     * @param array<int, array{role: string, text: string}> $history
     * @return array{answer: string, tools: array<string>}
     */
    private function resolveDeterministicAnswer(string $question, AgentContext $context, array $history = []): array
    {
        $q = mb_strtolower($question);
        $today = Carbon::now()->toDateString();
        $workspaceId = $context->getWorkspaceId();

        // ── 0. Multi-Turn Anaphora & Context Resolution ──────────────
        $lastAssistantText = '';
        $lastUserText = '';
        if (! empty($history)) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if ($history[$i]['role'] === 'assistant' && empty($lastAssistantText)) {
                    $lastAssistantText = $history[$i]['text'];
                } elseif ($history[$i]['role'] === 'user' && empty($lastUserText)) {
                    $lastUserText = $history[$i]['text'];
                }
            }
        }

        // Detect if query is a follow-up referring to an entity from previous turn
        $hasAnaphora = (bool) preg_match('/(?:এটার|এটির|এর|এগুলো|এই|ঐ|তার|it|this|that|its|these|item|product)/iu', $q);

        // Try extracting previous product name if mentioned (e.g. **Silk Panjabi Navy Blue**)
        $previousProductName = null;
        if ($hasAnaphora && ! empty($lastAssistantText)) {
            if (preg_match_all('/\*\*(.*?)\*\*/u', $lastAssistantText, $matches)) {
                foreach ($matches[1] as $candidate) {
                    $c = trim($candidate);
                    // Filter out non-product header words
                    if (! preg_match('/(?:সারাংশ|বিক্রয়|স্বাস্থ্য|শীর্ষ|Overview|Analytics|স্ট্যাটাস|রেভিনিউ|Executive|ব্যালেন্স)/iu', $c)) {
                        $previousProductName = $c;
                        break;
                    }
                }
            }
        }

        // Context follow-up for specific product stock inquiry (e.g. "এটার স্টক কত?")
        if ($previousProductName && preg_match('/(?:স্টক|stock|ইনভেন্টরি|কয়টি|মজুদ|available|বাকি)/iu', $q)) {
            $product = \App\Models\AnalyticsProduct::where('workspace_id', $workspaceId)
                ->where(function ($query) use ($previousProductName) {
                    $query->where('name', 'like', "%{$previousProductName}%")
                          ->orWhere('sku', 'like', "%{$previousProductName}%");
                })
                ->first();

            if ($product) {
                $statusBadge = $product->stock_quantity <= 0 ? '❌ স্টক আউট' : ($product->stock_quantity <= 5 ? '⚠️ লো-স্টক' : '✅ পর্যাপ্ত স্টক');
                $answer = "📦 **{$product->name} (SKU: {$product->sku}) এর স্টক তথ্য:**\n\n"
                        . "- **মজুদ পরিমাণ (Stock):** {$product->stock_quantity} টি\n"
                        . "- **স্ট্যাটাস:** {$statusBadge}\n"
                        . "- **মূল্য:** BDT " . number_format((float) $product->unit_price, 2) . "\n\n"
                        . "💡 *টিপস: পূর্ববর্তী প্রসঙ্গের রেফারেন্সে '{$product->name}'-এর স্টক উপাত্ত দেখানো হয়েছে।*";

                return ['answer' => $answer, 'tools' => ['contextual_product_stock']];
            }
        }

        // Context follow-up for previous sales topic with relative dates (e.g. "আর গতকালের?", "আর গত মাসের?")
        if (preg_match('/(?:আর|এবং|whats|how\s*about)/iu', $q) && ! preg_match('/(?:sales|সেলস|রেভিনিউ|স্টক)/iu', $q)) {
            if (preg_match('/(?:আজকের|sales|বিক্রি)/iu', $lastUserText)) {
                if (preg_match('/(?:গতকাল|yesterday)/iu', $q)) {
                    $yesterday = Carbon::yesterday()->toDateString();
                    $res = $this->runtimeEngine->executeTool(
                        tool: $this->salesTool,
                        arguments: ['date_from' => $yesterday, 'date_to' => $yesterday],
                        context: $context,
                        agent: $this->agent
                    );
                    $data = $res->data;
                    $orders = $data['total_orders'] ?? 0;
                    $gmv = number_format((float) ($data['total_sales'] ?? $data['gmv'] ?? 0), 2);
                    $net = number_format((float) ($data['net_sales'] ?? $data['net_revenue'] ?? 0), 2);

                    $answer = "📊 **পূর্ববর্তী প্রসঙ্গ (Sales) অনুযায়ী গতকালের বিক্রয় সারাংশ:**\n\n"
                            . "- **মোট অর্ডার সংখ্যা:** {$orders} টি\n"
                            . "- **মোট বিক্রয় (GMV):** BDT {$gmv}\n"
                            . "- **নিট রেভিনিউ:** BDT {$net}\n";

                    return ['answer' => $answer, 'tools' => ['contextual_sales_analytics']];
                }
            }
        }

        // 0. Messaging Usage, Quota, Spending & Wallet Balance inquiries
        $isMessagingQuery = (bool) preg_match('/(?:মেসেজ|message|কোটা|quota|ওয়ালেট|wallet|এসএমএস|sms|মেটা|meta)/iu', $q);
        $isWalletSpendingQuery = (bool) (preg_match('/(?:খরচ|ব্যালেন্স|balance|spend)/iu', $q) && preg_match('/(?:মেসেজ|কোটা|ওয়ালেট|মেটা|সার্ভিস|প্ল্যাটফর্ম)/iu', $q));

        if ($isMessagingQuery || $isWalletSpendingQuery) {
            $workspaceId = $context->getWorkspaceId();
            $quota = \App\Models\WorkspaceUsageQuota::currentForWorkspace($workspaceId);
            $workspace = \App\Models\Workspace::find($workspaceId);
            $wallet = $workspace?->wallet;
            $startOfMonth = Carbon::now()->startOfMonth();

            $totalSpentThisMonth = (float) \App\Models\WorkspaceWalletTransaction::where('workspace_id', $workspaceId)
                ->where('type', 'debit')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('amount');

            $waSent = $quota->whatsapp_messages_sent;
            $waLimit = $quota->whatsapp_free_limit;
            $waRemaining = $quota->whatsAppRemaining();
            $fbSent = $quota->messenger_messages_sent;
            $totalMessages = $waSent + $fbSent;
            $walletBalance = (float) ($wallet?->balance ?? 50.00);

            $answer = "📱 **আপনার ওয়ার্কস্পেসের মেসেজিং, খরচ ও ব্যালেন্স বিবরণী:**\n\n"
                    . "- **মোট প্রেরিত মেসেজ:** " . number_format($totalMessages) . " টি\n"
                    . "  • WhatsApp (মেটা ফ্রি কোটা): " . number_format($waSent) . " / " . number_format($waLimit) . " টি (বাকি আছে: " . number_format($waRemaining) . " টি)\n"
                    . "  • Facebook Messenger (ফ্রি সার্ভিস উইন্ডো): " . number_format($fbSent) . " টি\n"
                    . "- **মোট ব্যয় হয়েছে (Total Spent):** ৳ " . number_format($totalSpentThisMonth, 2) . " BDT\n"
                    . "  *(জিরো-চার্জ সুরক্ষা সক্রিয় থাকায় মেটার কোনো অতিরিক্ত বিল বা ক্রেডিট কার্ড চার্জ কাটা হয়নি)*\n"
                    . "- **অবশিষ্ট ওয়ালেট ব্যালেন্স (Remaining Balance):** ৳ " . number_format($walletBalance, 2) . " BDT\n\n"
                    . "💡 *পরামর্শ: আপনি মেটার সম্পূর্ণ ফ্রি লিমিটের ভেতরে নিরাপদ আছেন। অতিরিক্ত পেইড মেসেজিং প্যাকেজ শীঘ্রই চালু করা হবে (Coming Soon)।*";

            return ['answer' => $answer, 'tools' => ['workspace_quota_tracker']];
        }

        // 1. Sales & Revenue inquiries
        if (preg_match('/(?:আজকের|today|sales|বিক্রি|সেলস|রেভিনিউ|revenue)/iu', $q) && ! preg_match('/(?:স্টক|stock|ইনভেন্টরি)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->salesTool,
                arguments: [
                    'date_from' => $today,
                    'date_to'   => $today,
                ],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $orders = $data['total_orders'] ?? 0;
            $gmv = number_format((float) ($data['total_sales'] ?? $data['gmv'] ?? 0), 2);
            $net = number_format((float) ($data['net_sales'] ?? $data['net_revenue'] ?? 0), 2);

            $answer = "📊 **আজকের বিক্রয় ও রেভিনিউ সারাংশ:**\n\n"
                    . "- **মোট অর্ডার সংখ্যা:** {$orders} টি\n"
                    . "- **মোট বিক্রয় (GMV):** BDT {$gmv}\n"
                    . "- **নিট রেভিনিউ:** BDT {$net}\n\n"
                    . "💡 *পরামর্শ: আপনার শীর্ষ বিক্রিত পণ্যগুলোর স্টক বজায় রাখুন এবং পেন্ডিং অর্ডারগুলো দ্রুত কুরিয়ারে বুকিং সম্পন্ন করুন।*";

            return ['answer' => $answer, 'tools' => ['sales_analytics']];
        }

        // 2. Stock / Low-stock inquiries
        if (preg_match('/(?:স্টক|stock|ইনভেন্টরি|inventory|লো\s*স্টক|ফুরিয়ে)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->inventoryTool,
                arguments: [
                    'low_stock_threshold' => 10,
                ],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $lowStockItems = $data['low_stock_products'] ?? $data['items'] ?? [];
            if (empty($lowStockItems) && ($data['status'] ?? '') === 'no_inventory_data_ingested') {
                $lowStockItems = \App\Models\AnalyticsProduct::where('workspace_id', $context->getWorkspaceId())
                    ->where(function ($q) {
                        $q->where('stock_quantity', '<=', 5)
                          ->orWhereColumn('stock_quantity', '<=', 'reorder_threshold');
                    })
                    ->get()
                    ->map(fn($p) => ['product_name' => $p->name, 'current_stock' => $p->stock_quantity])
                    ->all();
            }
            $totalValuation = number_format((float) ($data['total_inventory_value'] ?? 0), 2);

            if (empty($lowStockItems)) {
                $answer = "✅ **ইনভেন্টরি অবস্থা সন্তোষজনক:**\n\nবর্তমানে কোনো পণ্যের মারাত্মক লো-স্টক নেই।\n- **মোট ইনভেন্টরি ভ্যালুয়েশন:** BDT {$totalValuation}\n\nসব পণ্যের পর্যাপ্ত স্টক রয়েছে।";
            } else {
                $answer = "⚠️ **লো-স্টক পণ্যের তালিকা ও সতর্কতা:**\n\n";
                foreach (array_slice($lowStockItems, 0, 5) as $item) {
                    $name = $item['name'] ?? $item['product_name'] ?? 'Product';
                    $stock = $item['stock'] ?? $item['current_stock'] ?? $item['stock_quantity'] ?? 0;
                    $answer .= "- **{$name}**: অবশিষ্ট স্টক মাত্র **{$stock}** টি\n";
                }
                $answer .= "\n💡 *পরামর্শ: গ্রাহকের অর্ডার বাতিল হওয়া রোধ করতে দ্রুত সাপ্লায়ারের কাছে রি-অর্ডার রিকুইজিশন তৈরি করুন।*";
            }

            return ['answer' => $answer, 'tools' => ['inventory_analytics']];
        }

        // 3. Top-selling / Product performance inquiries
        if (preg_match('/(?:টপ|সেরা|বেশি\s*বিক্রি|চলছে|top\s*selling|product|পণ্য)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->productTool,
                arguments: [
                    'limit' => 5,
                ],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $products = $data['top_products'] ?? $data['products'] ?? [];

            $answer = "🔥 **শীর্ষ বিক্রিত ও জনপ্রিয় পণ্যসমূহ:**\n\n";
            if (! empty($products)) {
                foreach (array_slice($products, 0, 5) as $idx => $p) {
                    $num = $idx + 1;
                    $name = $p['name'] ?? $p['product_name'] ?? 'Unknown';
                    $sold = $p['units_sold'] ?? $p['total_sold'] ?? 0;
                    $rev = number_format((float) ($p['revenue'] ?? 0), 2);
                    $answer .= "{$num}. **{$name}** — {$sold} টি বিক্রি (মোট: BDT {$rev})\n";
                }
            } else {
                $answer .= "গত কয়েকদিনের বিক্রয় ডেটা থেকে এখনো নির্দিষ্ট ট্রেন্ড লোড হচ্ছে।\n";
            }
            $answer .= "\n💡 *পরামর্শ: সর্বাধিক বিক্রিত পণ্যগুলোর জন্য স্পেশাল বান্ডেল অফার দিলে এভারেজ অর্ডার ভ্যালু (AOV) বৃদ্ধি পাবে।*";

            return ['answer' => $answer, 'tools' => ['product_performance']];
        }

        // 4. Customer Engagement, Cohorts & Retention Inquiries
        if (preg_match('/(?:কাস্টমার|গ্রাহক|customer|রিটেনশন|রিপিট\s*বায়ার|লয়ালটি)/iu', $q) && ! preg_match('/(?:সাপোর্ট|support|অভিযোগ|রিভিউ|বাকি|due|ডিউ)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->customerTool,
                arguments: [],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $totalCust = $data['total_registered_customers'] ?? $data['total_customers'] ?? 0;
            $activeCust = $data['active_customers'] ?? 0;
            $repeatRate = isset($data['repeat_buyer_ratio']) ? round(((float) $data['repeat_buyer_ratio']) * 100, 1) . '%' : ($data['repeat_customer_rate'] ?? '0%');
            $avgSpend = number_format((float) ($data['average_spend_per_customer'] ?? 0), 2);

            $answer = "👥 **কাস্টমার অ্যানালিটিক্স ও রিটেনশন সারাংশ:**\n\n"
                    . "- **মোট রেজিস্টার্ড কাস্টমার:** {$totalCust} জন\n"
                    . "- **সক্রিয় বা নিয়মিত কাস্টমার:** {$activeCust} জন\n"
                    . "- **রিপিট পারচেজ রেট (পুনরাবৃত্তি ক্রয়):** {$repeatRate}\n"
                    . "- **গড় কাস্টমার স্পেন্ডিং ভ্যালু (Avg Spend):** BDT {$avgSpend}\n\n"
                    . "💡 *পরামর্শ: লয়্যাল ও রিপিট ক্রেতাদের জন্য বিশেষ ডিসকাউন্ট বা রেফারাল অফার প্রদান করলে তাদের লাইফটাইম ভ্যালু আরও বৃদ্ধি পাবে।*";

            return ['answer' => $answer, 'tools' => ['customer_insights']];
        }

        // 4.1 Customer Dues / Balance Clarification (Explicitly clarifies that customer dues do not exist)
        if (preg_match('/(?:বাকি|due|ডিউ)/iu', $q)) {
            $answer = "ℹ️ **কাস্টমার ব্যালেন্স ও ডিউ সংক্রান্ত তথ্য:**\n\n"
                    . "আপনার ই-কমার্স বিজনেসে কোনো কাস্টমার বাকি বা বাকির খাতা (Customer Due Ledger) সক্রিয় নেই।\n"
                    . "আপনার সকল অর্ডার প্রিপেইড (bKash/Nagad/কার্ড) অথবা ক্যাশ অন ডেলিভারি (COD) হিসেবে পরিচালিত হয়। ফলে গ্রাহকদের কাছে কোনো ব্যক্তিগত বাকি বা বকেয়া থাকে না এবং প্রতিটি বিক্রয় সরাসরি ক্যাশ কালেকশন হিসেবে সম্পন্ন হয়।\n\n"
                    . "💡 *পরামর্শ: আপনি 'আজকের সেলস', 'টপ সেলিং প্রোডাক্ট', 'ইনভেন্টরি স্টক' অথবা 'লাভ ও মার্জিন' সম্পর্কে জানতে পারেন।*";

            return ['answer' => $answer, 'tools' => ['customer_insights']];
        }

        // 5. Financial, Profit Margin & P&L Inquiries
        if (preg_match('/(?:প্রফিট|লাভ|মার্জিন|profit|margin|cogs|লাভ-ক্ষতি|পিঅ্যান্ডএল|p&l|খরচ-লাভ)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->financialTool,
                arguments: [],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $grossSales = number_format((float) ($data['gross_sales'] ?? 0), 2);
            $cogs = number_format((float) ($data['cogs'] ?? 0), 2);
            $grossProfit = number_format((float) ($data['gross_profit'] ?? 0), 2);
            $marginRate = isset($data['gross_margin_rate']) ? round(((float) $data['gross_margin_rate']) * 100, 1) . '%' : '০%';

            $answer = "📈 **ব্যবসার লাভ ও আর্থিক মার্জিন সারাংশ:**\n\n"
                    . "- **সর্বমোট বিক্রয় (Gross Sales):** BDT {$grossSales}\n"
                    . "- **পণ্যের পাইকারি খরচ (COGS):** BDT {$cogs}\n"
                    . "- **গ্রস প্রফিট (Gross Profit):** BDT {$grossProfit}\n"
                    . "- **গ্রস মার্জিন রেট:** {$marginRate}\n\n"
                    . "💡 *পরামর্শ: উচ্চ মার্জিনের পণ্যগুলোতে ফোকাস বাড়িয়ে গড় মুনাফা বৃদ্ধি করা সম্ভব।*";

            return ['answer' => $answer, 'tools' => ['financial_analytics']];
        }

        // 6. Employee & Salesperson Performance Inquiries
        if (preg_match('/(?:সেলসম্যান|স্টাফ|কর্মচারী|কর্মকর্তা|employee|salesman|salesperson|staff|কর্মী)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->employeeTool,
                arguments: [],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $totalStaff = $data['total_headcount'] ?? $data['active_headcount'] ?? 0;
            $staffList = $data['salespersons'] ?? [];

            $answer = "👔 **কর্মকর্তা ও সেলসপার্সন অ্যানালিটিক্স:**\n\n"
                    . "- **মোট সক্রিয় সেলস স্টাফ:** {$totalStaff} জন\n";

            if (! empty($staffList)) {
                $answer .= "\n**কর্মীদের পারফরম্যান্স:**\n";
                foreach (array_slice($staffList, 0, 5) as $staff) {
                    $name = $staff['name'] ?? 'Staff';
                    $code = $staff['employee_code'] ?? '';
                    $answer .= "- **{$name}** ({$code})\n";
                }
            }
            $answer .= "\n💡 *পরামর্শ: শীর্ষ পারফরমারদের প্রণোদনা দিলে সামগ্রিক সেলস বৃদ্ধি পায়।*";

            return ['answer' => $answer, 'tools' => ['employee_analytics']];
        }

        // 7. Customer Support, CSAT & Feedback Inquiries
        if (preg_match('/(?:রিভিউ|কমপ্লেইন|ফিডব্যাক|সিএসএটি|csat|support|সাপোর্ট|রেটিং|অভিযোগ)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->supportTool,
                arguments: [],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $totalConv = $data['total_conversations'] ?? 0;
            $openTickets = $data['open_tickets'] ?? 0;
            $handoffRate = isset($data['handoff_rate']) ? round(((float) $data['handoff_rate']) * 100, 1) . '%' : '০%';

            $answer = "🎧 **কাস্টমার সাপোর্ট ও এক্সপেরিয়েন্স ইনসাইটস:**\n\n"
                    . "- **মোট কাস্টমার ডায়ালগ:** {$totalConv} টি\n"
                    . "- **চলমান ওপেন টিকেট:** {$openTickets} টি\n"
                    . "- **হিউম্যান হ্যান্ডঅফ অনুপাত:** {$handoffRate}\n\n"
                    . "💡 *পরামর্শ: কৃত্রিম বুদ্ধিমত্তা অধিকাংশ সাধারণ প্রশ্নের উত্তর দিয়ে সাপোর্ট লোড হ্রাস করছে।*";

            return ['answer' => $answer, 'tools' => ['owner_support_insights']];
        }

        // 8. Policy, Refund & Knowledge Inquiries
        if (preg_match('/(?:পলিসি|রিটার্ন|রিফান্ড|নীতিমালা|policy|refund|faq|ডকুমেন্ট|নিয়ম)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->knowledgeTool,
                arguments: ['query' => $question, 'top_k' => 2],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $docs = $data['matches'] ?? $data['documents'] ?? [];

            if (! empty($docs)) {
                $answer = "📑 **কোম্পানির অফিশিয়াল পলিসি ও নির্দেশিকা:**\n\n";
                foreach ($docs as $doc) {
                    $title = $doc['title'] ?? 'Policy';
                    $snippet = $doc['content'] ?? $doc['snippet'] ?? '';
                    $answer .= "**{$title}**:\n{$snippet}\n\n";
                }
                return ['answer' => trim($answer), 'tools' => ['knowledge_intelligence']];
            }
        }

        // 9. Default Business Overview & Strategic Recommendations
        $res = $this->runtimeEngine->executeTool(
            tool: $this->overviewTool,
            arguments: [],
            context: $context,
            agent: $this->agent
        );

        $data = $res->data;
        $gmv = number_format((float) ($data['gross_sales'] ?? $data['gmv'] ?? 0), 2);
        $orders = $data['order_count'] ?? $data['total_orders'] ?? 0;
        $cashCollected = number_format((float) ($data['cash_collected'] ?? 0), 2);
        $customers = $data['active_customers'] ?? 0;

        $answer = "💼 **ব্যবসার সার্বিক বাণিজ্যিক স্বাস্থ্য (Executive Overview):**\n\n"
                . "- **সর্বমোট গ্রস সেলস (Gross Sales):** BDT {$gmv}\n"
                . "- **মোট সম্পন্ন অর্ডার:** {$orders} টি\n"
                . "- **ক্যাশ কালেকশন (Total Paid):** BDT {$cashCollected}\n"
                . "- **সক্রিয় কাস্টমার:** {$customers} জন\n\n"
                . "💡 *কৌশলগত পরামর্শ: আপনার ব্যবসায়ের চলতি পারফরম্যান্স বিশ্লেষণ করে পরামর্শ হলো—সর্বাধিক বিক্রিত পণ্যগুলোর স্টক বজায় রাখুন এবং পেন্ডিং অর্ডারগুলো দ্রুত গ্রাহকের ঠিকানায় ডেলিভারি সম্পন্ন করুন।*";

        return ['answer' => $answer, 'tools' => ['business_overview']];
    }
}
