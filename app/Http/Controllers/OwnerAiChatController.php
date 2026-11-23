<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\AgentRuntimeEngine;
use App\AI\Agents\OwnerBusinessAgent;
use App\AI\DTOs\AgentContext;
use App\AI\Tools\BusinessOverviewTool;
use App\AI\Tools\CustomerInsightsTool;
use App\AI\Tools\FinancialAnalyticsTool;
use App\AI\Tools\InventoryAnalyticsTool;
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
    ) {}

    /**
     * Ask a question to the Virtual CFO / Owner Business Intelligence Agent.
     */
    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:1000',
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
        $startTime = microtime(true);

        try {
            // First attempt: Run Governed LLM Agent via AgentRuntimeEngine
            $response = $this->runtimeEngine->run($this->agent, $question, $context);

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

        // Fallback: Deterministic Analytics & CFO Engine using concrete governed tools
        $directResult = $this->resolveDeterministicAnswer($question, $context);
        $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'status'         => 'success',
            'answer'         => $directResult['answer'],
            'tools_executed' => $directResult['tools'],
            'execution_time' => "{$elapsedMs}ms",
            'mode'           => 'grounded_analytics_tools',
        ]);
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
                    'text'  => 'গ্রাহকদের মোট বাকি বা ডিউ কত টাকা?',
                    'label' => '📑 কাস্টমার ব্যালেন্স ও ডিউ',
                ],
                [
                    'text'  => 'গত ৩০ দিনের গ্রস প্রফিট ও মার্জিন কত?',
                    'label' => '📊 নিট প্রফিট ও মার্জিন',
                ],
            ],
        ]);
    }

    /**
     * Deterministic analytical query processor for instant 0-latency grounded answers.
     *
     * @return array{answer: string, tools: array<string>}
     */
    private function resolveDeterministicAnswer(string $question, AgentContext $context): array
    {
        $q = mb_strtolower($question);
        $today = Carbon::now()->toDateString();

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

        // 4. Customer balance, dues, and cohorts
        if (preg_match('/(?:বাকি|due|ডিউ|কাস্টমার|গ্রাহক|balance|customer)/iu', $q)) {
            $res = $this->runtimeEngine->executeTool(
                tool: $this->customerTool,
                arguments: [],
                context: $context,
                agent: $this->agent
            );

            $data = $res->data;
            $totalCust = $data['total_customers'] ?? 0;
            $dueAmount = number_format((float) ($data['total_outstanding_due'] ?? $data['total_due'] ?? 0), 2);
            $repeatRate = $data['repeat_customer_rate'] ?? '0%';

            $answer = "👥 **কাস্টমার অ্যানালিটিক্স ও বাকি সারাংশ:**\n\n"
                    . "- **মোট রেজিস্টার্ড কাস্টমার:** {$totalCust} জন\n"
                    . "- **মোট অপরিশোধিত বাকি (Due):** BDT {$dueAmount}\n"
                    . "- **রিপিট পারচেজ রেট:** {$repeatRate}\n\n"
                    . "💡 *পরামর্শ: যেসকল গ্রাহকের ডিউ রয়েছে তাদের সিস্টেমে রিমাইন্ডার এসএমএস পাঠানো যেতে পারে।*";

            return ['answer' => $answer, 'tools' => ['customer_insights']];
        }

        // 5. Default Business Overview & Commercial Health
        $res = $this->runtimeEngine->executeTool(
            tool: $this->overviewTool,
            arguments: [],
            context: $context,
            agent: $this->agent
        );

        $data = $res->data;
        $gmv = number_format((float) ($data['gmv'] ?? 0), 2);
        $orders = $data['total_orders'] ?? 0;
        $returns = $data['returns_count'] ?? 0;
        $fulfillment = $data['fulfillment_rate'] ?? '100%';

        $answer = "💼 **ব্যবসার সার্বিক বাণিজ্যিক স্বাস্থ্য (Executive Overview):**\n\n"
                . "- **সর্বমোট জিএমভি (GMV):** BDT {$gmv}\n"
                . "- **মোট সম্পন্ন অর্ডার:** {$orders} টি\n"
                . "- **ডেলিভারি রিটার্ন সংখ্যা:** {$returns} টি\n"
                . "- **ফুলফিলমেন্ট রেট:** {$fulfillment}\n\n"
                . "💡 *পরামর্শ: আপনার ব্যবসা সন্তোষজনক গতিতে পরিচালিত হচ্ছে। আরও বিস্তারিত বিশ্লেষণের জন্য সেলস, ইনভেন্টরি বা কাস্টমার রিপোর্ট নিয়ে প্রশ্ন করতে পারেন।*";

        return ['answer' => $answer, 'tools' => ['business_overview']];
    }
}
