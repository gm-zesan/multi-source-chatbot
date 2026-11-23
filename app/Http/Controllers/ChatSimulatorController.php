<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\KnowledgeDocument;
use App\Models\Workspace;
use App\AI\Decision\UnifiedOrchestrator;
use App\AI\Orchestration\CustomerSupportService;
use App\Services\CRM\CRMService;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatSimulatorController extends Controller
{
    public function __construct(
        private readonly CRMService $crmService,
        private readonly CustomerSupportService $customerSupportService,
        private readonly RetrievalClient $retrievalClient,
        private readonly AnalyticsClient $analyticsClient,
        private readonly UnifiedOrchestrator $orchestrator,
    ) {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        if (!$this->isSuperAdmin($user)) {
            abort(403, 'Unauthorized. The AI Simulator is strictly reserved for Superadmin diagnostics.');
        }

        $workspaceId = $this->resolveWorkspaceId($request);
        $isSuperAdmin = true;
        $allWorkspaces = Workspace::orderBy('id', 'asc')->get();
        $currentWorkspace = Workspace::find($workspaceId);

        $conversation = $this->resolveSimulatorConversation($request, $workspaceId);
        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        $rawHistory = $conversation->metadata['llm_usage_history'] ?? [];
        $llmUsageHistory = array_values(array_filter(array_map(function ($item) {
            if (isset($item['model']) && ($item['model'] === 'deepseek-chat' || $item['model'] === 'Deepseek-Chat')) {
                $item['model'] = 'deepseek-chat';
            }
            return $item;
        }, $rawHistory), function ($item) {
            $total = (int) ($item['total_tokens'] ?? (($item['prompt_tokens'] ?? 0) + ($item['completion_tokens'] ?? 0)));
            return $total > 0;
        }));

        $fileId = $request->query('file_id');
        if (!empty($fileId)) {
            $doc = KnowledgeDocument::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($fileId) {
                    $q->where('source_id', $fileId)->orWhere('id', $fileId);
                })
                ->first();

            if ($doc) {
                $effectiveFileId = (string) ($doc->source_id ?? $doc->id);
                $metadata = $conversation->metadata ?? [];
                $metadata['active_file_id'] = $effectiveFileId;
                $metadata['active_filename'] = $doc->original_filename ?: $doc->title;
                $metadata['active_file_sheets'] = $doc->metadata['sheets'] ?? $doc->sheets ?? [];
                $metadata['active_file_rows'] = (int) ($doc->chunk_count ?? $doc->total_rows ?? 0);
                $conversation->metadata = $metadata;
                $conversation->save();
            }
        }

        $activeSpreadsheet = [
            'file_id' => $conversation->metadata['active_file_id'] ?? null,
            'filename' => $conversation->metadata['active_filename'] ?? null,
            'sheets' => $conversation->metadata['active_file_sheets'] ?? [],
            'total_rows' => $conversation->metadata['active_file_rows'] ?? 0,
        ];

        $availableDocuments = KnowledgeDocument::where('workspace_id', $workspaceId)
            ->where('lifecycle_status', \App\Enums\DocumentLifecycleStatus::ACTIVE)
            ->whereNotNull('source_id')
            ->orderBy('created_at', 'desc')
            ->get();

        $storedMessages = $messages->map(function ($msg) {
            $meta = $msg->metadata ?? [];
            return [
                'id' => $msg->id,
                'direction' => $msg->direction,
                'body' => $msg->body,
                'route' => $meta['route'] ?? null,
                'is_handoff' => !empty($meta['handoff_to_human']),
                'suggestions' => $meta['suggestions'] ?? [],
                'sources' => $meta['sources'] ?? [],
                'pipeline_diagnostics' => [
                    'total_time_ms' => $meta['total_time_ms'] ?? 0,
                ],
            ];
        })->values()->all();

        return view('admin.simulator', compact(
            'messages',
            'storedMessages',
            'llmUsageHistory',
            'activeSpreadsheet',
            'availableDocuments',
            'allWorkspaces',
            'workspaceId',
            'currentWorkspace',
            'isSuperAdmin'
        ));
    }

    /**
     * Process an incoming customer message through the full pipeline:
     * 1. Extract CRM entities and persist contact profile.
     * 2. Run CustomerSupportService (Agent + Tool + Retrieval + LLM with multi-turn conversation memory).
     * 3. Return structured response with diagnostics.
     */
    public function send(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$this->isSuperAdmin($currentUser)) {
            abort(403, 'Unauthorized. The AI Simulator is strictly reserved for Superadmin diagnostics.');
        }

        $request->validate([
            'message'    => 'required|string|max:1000',
            'file_id'    => 'nullable|string',
            'agent_mode' => 'nullable|string|in:auto,sales,support,cfo,retention,whatsapp,csat,catalog_sync',
        ]);

        $query = (string) $request->input('message');
        $fileId = $request->input('file_id');
        $agentMode = (string) $request->input('agent_mode', 'auto');
        $workspaceId = $this->resolveWorkspaceId($request);
        $startTime = microtime(true);
        @set_time_limit(120);

        // ── 0. Resolve Multi-Turn Simulator Conversation ───────────────
        $conversation = $this->resolveSimulatorConversation($request, $workspaceId);

        // If file_id is explicitly passed, associate active document with conversation context
        if (!empty($fileId)) {
            $metadata = $conversation->metadata ?? [];
            if (($metadata['active_file_id'] ?? null) !== $fileId) {
                $doc = KnowledgeDocument::where('workspace_id', $workspaceId)
                    ->where(function ($q) use ($fileId) {
                        $q->where('source_id', $fileId)->orWhere('id', $fileId);
                    })
                    ->first();
                if ($doc) {
                    $metadata['active_file_id'] = (string) ($doc->source_id ?? $doc->id);
                    $metadata['active_filename'] = $doc->original_filename;
                    $metadata['active_file_sheets'] = $doc->metadata['sheets'] ?? [];
                    $metadata['active_file_rows'] = $doc->chunk_count ?? 0;
                    $conversation->metadata = $metadata;
                    $conversation->save();
                }
            }
        }

        // Save incoming user message to conversation history
        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => $query,
        ]);

        // ── Direct Agent Mode Routing for Superadmin Diagnostics ───────
        if ($agentMode === 'cfo') {
            $ownerAiController = app(\App\Http\Controllers\OwnerAiChatController::class);
            $cfoResponse = $ownerAiController->ask(new Request(['question' => $query]));
            $cfoData = $cfoResponse->getData(true);

            $reply = $cfoData['answer'] ?? 'No response generated.';
            $toolsExecuted = $cfoData['tools_executed'] ?? ['owner_business_agent'];
            $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);

            $this->customerSupportService->saveOutboundReply(
                conversation: $conversation,
                replyText: $reply,
                deliveryResponse: [
                    'route' => 'analytics',
                    'confidence' => 1.0,
                    'answered' => true,
                    'total_time_ms' => $totalElapsed,
                ],
            );

            return response()->json([
                'success' => true,
                'query' => $query,
                'reply' => $reply,
                'route' => 'analytics',
                'agent_mode' => 'cfo',
                'agent_tested' => 'OwnerBusinessAgent (Virtual CFO)',
                'tools_executed' => $toolsExecuted,
                'suggestions' => [
                    'আজকের মোট সেলস ও রেভিনিউ কত?',
                    'কোন প্রোডাক্টটি সবচেয়ে বেশি বিক্রি হচ্ছে?',
                    'কোন কোন আইটেমের স্টক কম রয়েছে?',
                ],
                'sources' => ['Internal Governed Analytics Tools'],
                'decision_trace' => [
                    'query' => $query,
                    'route' => 'ANALYTICS (CFO)',
                    'agent_tested' => 'OwnerBusinessAgent (Virtual CFO)',
                    'route_confidence' => 100.0,
                    'memory_decision' => 'ACTIVE',
                    'contextual_signal' => 'EXECUTIVE_CFO_QUERY',
                    'latency_breakdown' => [
                        'total_ms' => $totalElapsed,
                        'total_e2e_ms' => $totalElapsed,
                    ],
                ],
                'pipeline_diagnostics' => [
                    'total_time_ms' => $totalElapsed,
                    'agent_mode' => 'cfo',
                    'tools_executed' => $toolsExecuted,
                ],
                'confidence' => 100.0,
                'is_handoff' => false,
                'answered' => true,
            ]);
        }

        if ($agentMode === 'retention') {
            $retentionService = app(\App\Services\Retention\AbandonedLeadRecoveryService::class);
            $preview = $retentionService->craftFollowUpMessage($conversation);
            $metaSafe = $conversation->last_message_at !== null && $conversation->last_message_at->gte(now()->subHours(23));
            $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);

            $reply = "🔄 **[AI Retention Agent — Diagnostic Simulation]**\n\n"
                   . "📋 **তৈরিকৃত রিকভারি মেসেজ:**\n\"{$preview}\"\n\n"
                   . "🛡️ **মেটা ২৪-ঘণ্টা কমপ্লায়েন্স:** " . ($metaSafe ? "✅ নিরাপদ (Within 23h window)" : "⚠️ মেয়াদোত্তীর্ণ (>23h passed)") . "\n"
                   . "🏷️ **কনভারসেশন ট্যাগস:** " . implode(', ', (array) ($conversation->ai_tags ?? ['none'])) . "\n"
                   . "📦 **অর্ডার বিদ্যমান কিনা:** " . ($conversation->orders()->exists() ? 'অর্ডার অলরেডি সম্পন্ন (ফলো-আপ বাতিল)' : 'কোনো অর্ডার নেই (রিকভারি প্রযোজ্য)');

            $this->customerSupportService->saveOutboundReply(
                conversation: $conversation,
                replyText: $reply,
                deliveryResponse: [
                    'route' => 'action',
                    'confidence' => 1.0,
                    'answered' => true,
                    'total_time_ms' => $totalElapsed,
                ],
            );

            return response()->json([
                'success' => true,
                'query' => $query,
                'reply' => $reply,
                'route' => 'action',
                'agent_mode' => 'retention',
                'agent_tested' => 'AbandonedLeadRecoveryService (Retention Agent)',
                'tools_executed' => ['abandoned_lead_recovery_service'],
                'suggestions' => [],
                'sources' => ['Meta 24h Retention Engine'],
                'decision_trace' => [
                    'query' => $query,
                    'route' => 'ACTION (RETENTION)',
                    'agent_tested' => 'AbandonedLeadRecoveryService (Retention Agent)',
                    'route_confidence' => 100.0,
                    'memory_decision' => 'ACTIVE',
                    'contextual_signal' => 'RETENTION_ABANDONED_LEAD',
                    'latency_breakdown' => [
                        'total_ms' => $totalElapsed,
                        'total_e2e_ms' => $totalElapsed,
                    ],
                ],
                'pipeline_diagnostics' => [
                    'total_time_ms' => $totalElapsed,
                    'agent_mode' => 'retention',
                ],
                'confidence' => 100.0,
                'is_handoff' => false,
                'answered' => true,
            ]);
        }

        // ── WhatsApp Cloud API Channel Simulator ──────────────────────
        if ($agentMode === 'whatsapp') {
            $isButton = str_contains($query, '[Interactive Button:') || str_starts_with(strtolower($query), 'button:');
            $isMedia = str_contains($query, '[Attachment:') || str_starts_with(strtolower($query), 'image:');

            $simulatedMessage = [
                'from' => '8801700000000',
                'id'   => 'wamid.SIM_' . uniqid(),
                'type' => $isButton ? 'interactive' : ($isMedia ? 'image' : 'text'),
            ];

            if ($isButton) {
                $cleanTitle = trim(str_replace(['[Interactive Button:', 'button:', ']'], '', $query));
                $simulatedMessage['interactive'] = [
                    'type'         => 'button_reply',
                    'button_reply' => ['id' => 'btn_sim_1', 'title' => $cleanTitle],
                ];
            } elseif ($isMedia) {
                $caption = trim(str_replace(['[Attachment: Image]', '[Attachment:', 'image:'], '', $query));
                $simulatedMessage['image'] = ['caption' => $caption ?: 'Sample product picture'];
            } else {
                $simulatedMessage['text'] = ['body' => $query];
            }

            $driver = new \App\Channels\WhatsApp\WhatsAppDriver();
            $parsed = $driver->parseWebhook([
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'metadata' => ['phone_number_id' => 'wa_sim_phone_001'],
                                    'contacts' => [['profile' => ['name' => 'Simulated WhatsApp User']]],
                                    'messages' => [$simulatedMessage],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $supportResult = $this->orchestrator->process(
                query: $parsed['text'] ?: $query,
                workspaceId: $workspaceId,
                user: $currentUser,
                conversation: $conversation,
                channelSlug: 'whatsapp',
            );

            $reply = $supportResult['reply'] ?? 'WhatsApp message processed.';
            $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);

            $whatsappOutboundPayload = [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => '8801700000000',
                'type'              => 'text',
                'text'              => ['preview_url' => false, 'body' => $reply],
            ];

            $this->customerSupportService->saveOutboundReply(
                conversation: $conversation,
                replyText: $reply,
                deliveryResponse: [
                    'route'         => $supportResult['route'] ?? 'knowledge',
                    'confidence'    => $supportResult['confidence'] ?? 1.0,
                    'answered'      => true,
                    'total_time_ms' => $totalElapsed,
                ],
            );

            return response()->json([
                'success'              => true,
                'query'                => $query,
                'reply'                => $reply,
                'route'                => $supportResult['route'] ?? 'knowledge',
                'agent_mode'           => 'whatsapp',
                'agent_tested'         => 'WhatsAppCloudApiConnector (WhatsAppDriver)',
                'whatsapp_outbound'    => $whatsappOutboundPayload,
                'suggestions'          => $supportResult['suggestions'] ?? [],
                'sources'              => array_merge(['WhatsApp Cloud API v20.0'], (array) ($supportResult['sources'] ?? [])),
                'decision_trace'       => [
                    'query'             => $query,
                    'route'             => 'WHATSAPP_LIVE_CHANNEL',
                    'agent_tested'      => 'WhatsApp Cloud API Connector',
                    'route_confidence'  => 100.0,
                    'memory_decision'   => 'ACTIVE',
                    'contextual_signal' => 'INBOUND_WHATSAPP_WEBHOOK',
                    'latency_breakdown' => [
                        'total_ms'     => $totalElapsed,
                        'total_e2e_ms' => $totalElapsed,
                    ],
                ],
                'pipeline_diagnostics' => [
                    'total_time_ms' => $totalElapsed,
                    'agent_mode'    => 'whatsapp',
                    'inbound_type'  => $simulatedMessage['type'],
                ],
                'confidence'           => 100.0,
                'is_handoff'           => false,
                'answered'             => true,
            ]);
        }

        // ── Post-Delivery CSAT Feedback Simulator ──────────────────────
        if ($agentMode === 'csat') {
            $feedbackService = app(\App\Services\Retention\PostDeliveryFeedbackService::class);
            $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);

            $recordedFeedback = $feedbackService->recordFeedbackFromMessage($conversation, $query);

            if ($recordedFeedback) {
                $csatStats = $feedbackService->getCSATStats($workspaceId);
                $reply = "⭐ **[CSAT Feedback Engine — Rating Recorded!]**\n\n"
                    . "- **গ্রাহক রেটিং:** " . str_repeat('⭐', $recordedFeedback->rating) . " ({$recordedFeedback->rating}/5 Stars)\n"
                    . "- **ফিডব্যাক মন্তব্য:** \"{$recordedFeedback->review_text}\"\n"
                    . "- **অর্ডার রেফারেন্স:** #{$recordedFeedback->order?->order_number}\n\n"
                    . "📊 **ওয়ার্কস্পেস CSAT মেট্রিক্স আপডেট:**\n"
                    . "- **গড় সন্তুষ্টি রেটিং:** {$csatStats['average_rating']} / 5.0 ⭐\n"
                    . "- **পজিটিভ রেটিং শতাংশ:** {$csatStats['csat_percentage']}%\n"
                    . "- **মোট সংগৃহীত ফিডব্যাক:** {$csatStats['total_received']} টি\n\n"
                    . "💬 *কাস্টমারকে তাৎক্ষণিক কৃতজ্ঞতাসূচক ধন্যবাদ বার্তা পাঠানো হয়েছে।*";

                return response()->json([
                    'success'              => true,
                    'query'                => $query,
                    'reply'                => $reply,
                    'route'                => 'action',
                    'agent_mode'           => 'csat',
                    'agent_tested'         => 'PostDeliveryFeedbackService (CSAT Rating)',
                    'csat_recorded'        => [
                        'rating' => $recordedFeedback->rating,
                        'review' => $recordedFeedback->review_text,
                        'stats'  => $csatStats,
                    ],
                    'decision_trace'       => [
                        'query'             => $query,
                        'route'             => 'CSAT_FEEDBACK_RECORDED',
                        'agent_tested'      => 'PostDeliveryFeedbackService',
                        'route_confidence'  => 100.0,
                        'latency_breakdown' => ['total_ms' => $totalElapsed],
                    ],
                    'pipeline_diagnostics' => ['total_time_ms' => $totalElapsed, 'agent_mode' => 'csat'],
                    'confidence'           => 100.0,
                    'is_handoff'           => false,
                    'answered'             => true,
                ]);
            }

            // Otherwise, trigger courier delivered feedback request
            $order = \App\Models\WorkspaceOrder::where('workspace_id', $workspaceId)->latest()->first();
            if (! $order) {
                $order = \App\Models\WorkspaceOrder::create([
                    'workspace_id'    => $workspaceId,
                    'conversation_id' => $conversation->id,
                    'order_number'    => 'ORD-SIM-' . rand(1000, 9999),
                    'channel'         => 'whatsapp',
                    'customer_name'   => 'Superadmin Tester',
                    'customer_phone'  => '01700000000',
                    'status'          => 'delivered',
                    'courier_status'  => 'delivered',
                    'order_amount'    => 2500.00,
                ]);
            } else {
                $order->status = 'delivered';
                $order->courier_status = 'delivered';
                $order->conversation_id = $conversation->id;
                $order->save();
            }

            $feedback = $feedbackService->dispatchFeedbackRequest($order);

            $reply = "📦 **[Post-Delivery CSAT Simulation — Feedback Dispatched]**\n\n"
                . "🚚 **কুরিয়ার স্ট্যাটাস:** Delivered (Steadfast/Pathao)\n"
                . "📋 **অর্ডার নম্বর:** #{$order->order_number}\n\n"
                . "📨 **কাস্টমারকে প্রেরিত বার্তা:**\n"
                . "\"আসসালামু আলাইকুম {$order->customer_name}! আপনার অর্ডারটি সফলভাবে ডেলিভারি সম্পন্ন হয়েছে।\n"
                . "আমাদের পণ্য ও ডেলিভারি সেবা কেমন লেগেছে? ১ থেকে ৫ এর মধ্যে রেটিং দিন (যেমন: 5 = চমৎকার, 1 = অসন্তুষ্ট)।\"\n\n"
                . "💡 *সিমুলেট করতে নিচে '5', '৫ স্টার', অথবা 'অসাধারণ প্রডাক্ট' লিখে সেন্ড করুন!*";

            return response()->json([
                'success'              => true,
                'query'                => $query,
                'reply'                => $reply,
                'route'                => 'action',
                'agent_mode'           => 'csat',
                'agent_tested'         => 'PostDeliveryFeedbackService (CSAT Dispatch)',
                'suggestions'          => ['5 star', 'অসাধারণ প্রোডাক্ট, অনেক সুন্দর হয়েছে!', '১ - সাইজ মিলেনি, বাজে সার্ভিস'],
                'decision_trace'       => [
                    'query'             => $query,
                    'route'             => 'CSAT_FEEDBACK_DISPATCHED',
                    'agent_tested'      => 'PostDeliveryFeedbackService',
                    'route_confidence'  => 100.0,
                    'latency_breakdown' => ['total_ms' => $totalElapsed],
                ],
                'pipeline_diagnostics' => ['total_time_ms' => $totalElapsed, 'agent_mode' => 'csat'],
                'confidence'           => 100.0,
                'is_handoff'           => false,
                'answered'             => true,
            ]);
        }

        // ── Shopify & WooCommerce Catalog Bridge Simulator ────────────
        if ($agentMode === 'catalog_sync') {
            $bridgeService = app(\App\Services\Catalog\ExternalStoreCatalogBridgeService::class);
            $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);

            $isWoo = str_contains(strtolower($query), 'woo') || str_contains(strtolower($query), 'woocommerce');
            $simSku = $isWoo ? ('WOO-TEST-' . rand(100, 999)) : ('SH-TEST-' . rand(100, 999));
            $simName = $isWoo ? 'WooCommerce Ergonomic Office Chair' : 'Shopify Silk Embroidered Panjabi';

            if ($isWoo) {
                $product = $bridgeService->syncWooCommerceProduct($workspaceId, [
                    'id'             => rand(5000, 9999),
                    'name'           => $simName,
                    'sku'            => $simSku,
                    'price'          => '3850.00',
                    'stock_quantity' => 15,
                    'stock_status'   => 'instock',
                    'status'         => 'publish',
                    'categories'     => [['name' => 'Premium Office Furniture']],
                    'images'         => [['src' => 'https://via.placeholder.com/400?text=Chair']],
                ]);
            } else {
                $product = $bridgeService->syncShopifyProduct($workspaceId, [
                    'id'           => rand(80000, 99999),
                    'title'        => $simName,
                    'vendor'       => 'Apex Heritage',
                    'product_type' => 'Traditional Clothing',
                    'status'       => 'active',
                    'variants'     => [
                        ['sku' => $simSku, 'price' => '2950.00', 'inventory_quantity' => 20],
                    ],
                    'images'       => [['src' => 'https://via.placeholder.com/400?text=Panjabi']],
                ]);
            }

            $reply = "🌐 **[External Store Catalog Bridge — Webhook Ingestion OK]**\n\n"
                . "- **প্ল্যাটফর্ম:** " . ($isWoo ? 'WooCommerce REST API' : 'Shopify Webhook (products/create)') . "\n"
                . "- **পণ্যের নাম:** {$product->name}\n"
                . "- **SKU কোড:** `{$product->sku}`\n"
                . "- **মূল্য:** ৳ {$product->unit_price}\n"
                . "- **লাইভ স্টক:** {$product->stock_quantity} টি\n"
                . "- **স্ট্যাটাস:** " . ($product->is_active ? '✅ Active (In Stock)' : '❌ Inactive') . "\n\n"
                . "🚀 **এআই ক্যাটালগ সিঙ্ক:** প্রডাক্টটি তৎক্ষণাৎ সেন্ট্রাল এআই নলেজ বেসে লাইভ অন্তর্ভুক্ত হয়েছে।\n"
                . "💡 *পরীক্ষা করতে জিজ্ঞেস করুন: \"{$product->name} কি স্টকে আছে?\" অথবা \"দাম কত?\"*";

            return response()->json([
                'success'              => true,
                'query'                => $query,
                'reply'                => $reply,
                'route'                => 'catalog_bridge',
                'agent_mode'           => 'catalog_sync',
                'agent_tested'         => 'ExternalStoreCatalogBridgeService',
                'synced_product'       => [
                    'name'  => $product->name,
                    'sku'   => $product->sku,
                    'stock' => $product->stock_quantity,
                    'price' => $product->unit_price,
                ],
                'suggestions'          => [
                    "{$product->name} কি স্টকে আছে?",
                    "{$product->name} এর দাম কত?",
                ],
                'decision_trace'       => [
                    'query'             => $query,
                    'route'             => 'EXTERNAL_CATALOG_SYNC',
                    'agent_tested'      => 'ExternalStoreCatalogBridgeService',
                    'route_confidence'  => 100.0,
                    'latency_breakdown' => ['total_ms' => $totalElapsed],
                ],
                'pipeline_diagnostics' => ['total_time_ms' => $totalElapsed, 'agent_mode' => 'catalog_sync'],
                'confidence'           => 100.0,
                'is_handoff'           => false,
                'answered'             => true,
            ]);
        }

        // ── 1. CRM Entity Extraction & Contact Persistence ─────────────
        $crm = $this->crmService->processForWorkspace(
            workspaceId: $workspaceId,
            text: $query,
            name: null,
        );

        // ── 2. Unified AI Orchestration Pipeline ─────────────────────────────
        $supportResult = $this->orchestrator->process(
            query: $query,
            workspaceId: $workspaceId,
            user: $currentUser,
            conversation: $conversation,
            channelSlug: ($agentMode === 'sales' ? 'sales_agent' : ($agentMode === 'support' ? 'support_agent' : 'simulator')),
        );

        // Save AI outbound response to conversation history for next turn context
        if (!empty($supportResult['reply'])) {
            $totalElapsedSoFar = round((microtime(true) - $startTime) * 1000, 2);
            $rawLlm = $supportResult['raw_llm_response'] ?? [];
            $outboundMsg = $this->customerSupportService->saveOutboundReply(
                conversation: $conversation,
                replyText: $supportResult['reply'],
                deliveryResponse: [
                    'route' => $supportResult['route'] ?? 'knowledge',
                    'confidence' => $supportResult['confidence'] ?? 1.0,
                    'answered' => $supportResult['answered'] ?? false,
                    'total_time_ms' => $totalElapsedSoFar,
                    'answerability_decision' => $supportResult['answerability_decision'] ?? null,
                    'routing_telemetry' => $supportResult['routing_telemetry'] ?? [],
                    'llm_usage' => [
                        'prompt_tokens' => $rawLlm['prompt_tokens'] ?? 0,
                        'completion_tokens' => $rawLlm['completion_tokens'] ?? 0,
                        'total_tokens' => $rawLlm['total_tokens'] ?? 0,
                        'router_tokens' => $rawLlm['router_tokens'] ?? [],
                        'agent_tokens' => $rawLlm['agent_tokens'] ?? [],
                    ],
                ],
            );

            // Dispatch asynchronous Graph Memory Ingestion (Non-blocking, port 8002)
            \App\Jobs\IngestConversationMemoryJob::dispatch($conversation);

            // Observer Pattern: Telemetry Event
            try {
                event(new \App\Events\AITelemetryRecorded(
                    conversation: $conversation,
                    outboundMessage: $outboundMsg,
                    query: $query,
                    reply: $supportResult['reply'],
                    telemetry: [
                        'route' => $supportResult['route'],
                        'confidence' => $supportResult['confidence'],
                        'answered' => $supportResult['answered'],
                        'total_time_ms' => $totalElapsedSoFar,
                        'answerability_decision' => $supportResult['answerability_decision'] ?? null,
                        'routing_telemetry' => $supportResult['routing_telemetry'] ?? [],
                        'provider' => config('ai.default', 'deepseek'),
                        'model' => config('ai.default_model', 'deepseek-chat'),
                    ],
                    workspaceId: $workspaceId,
                ));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[ChatSimulator] Telemetry dispatch absorbed: ' . $e->getMessage());
            }
        }

        // ── 3. Diagnostics & Structured Decision Trace ──────────────────
        $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);
        $topHit = $supportResult['top_hit'];
        $retrievalHits = $supportResult['retrieval_hits'];
        $answerabilityDecision = $supportResult['answerability_decision'] ?? null;
        $rawBreakdown = $supportResult['latency_breakdown'] ?? [];

        $routerLatency = (float) ($rawBreakdown['router_ms'] ?? $supportResult['routing_telemetry']['router_latency_ms'] ?? 0.0);
        $contextLatency = (float) ($rawBreakdown['context_resolution_ms'] ?? 0.0);
        $clarificationLatency = (float) ($rawBreakdown['clarification_ms'] ?? 0.0);
        $memoryLatency = (float) ($rawBreakdown['memory_retrieval_ms'] ?? 0.0);
        $businessLatency = (float) ($rawBreakdown['business_context_ms'] ?? 0.0);
        $knowledgeRetrievalLatency = (float) ($rawBreakdown['knowledge_retrieval_ms'] ?? 0.0);
        $answerabilityLatency = (float) ($rawBreakdown['answerability_ms'] ?? 0.0);
        $llmLatency = (float) ($rawBreakdown['llm_generation_ms'] ?? 0.0);

        $contextualSignal = $supportResult['routing_telemetry']['signals']['contextual_signal']
            ?? $supportResult['routing_telemetry']['intent']
            ?? 'COMMERCE_GENERAL';

        $agentTested = match ($agentMode) {
            'sales'     => 'AI Sales Agent (ConversationalSupportAgent)',
            'support'   => 'AI Customer Support Agent (KnowledgeSupportAgent)',
            'cfo'       => 'AI Virtual CFO Agent (OwnerBusinessAgent)',
            'retention' => 'AI Retention Agent (AbandonedLeadRecoveryService)',
            default     => 'Master Unified Orchestrator (Auto Detect)',
        };

        $decisionTrace = [
            'query' => $query,
            'agent_tested' => $agentTested,
            'agent_mode' => $agentMode,
            'route' => strtoupper((string) $supportResult['route']),
            'route_confidence' => round($supportResult['confidence'] * 100, 1),
            'memory_decision' => !empty($supportResult['memory_context']) ? 'USED' : 'BYPASSED',
            'memory_preview' => !empty($supportResult['memory_context']) ? substr($supportResult['memory_context'], 0, 150) . '...' : null,
            'contextual_signal' => strtoupper((string) $contextualSignal),
            'retrieval_summary' => [
                'hits_count' => $retrievalHits->count(),
                'top_score' => $topHit ? round($topHit->finalScore * 100, 1) : 0.0,
                'top_doc_type' => $topHit?->faq?->documentTypeLabel() ?? null,
                'top_question' => $topHit?->faq?->question ?? null,
            ],
            'answerability_status' => strtoupper($answerabilityDecision['status'] ?? 'BYPASSED'),
            'answerability_score' => round(($answerabilityDecision['confidence_score'] ?? 0.0) * 100, 1),
            'grounded_hit_count' => (int) ($answerabilityDecision['grounded_count'] ?? count($supportResult['sources'] ?? [])),
            'llm_generation' => [
                'provider' => config('ai.default', 'deepseek'),
                'model' => config('ai.default_model', 'deepseek-chat'),
                'status' => !empty($supportResult['reply']) ? 'GENERATED' : 'FALLBACK',
                'prompt_tokens' => $supportResult['raw_llm_response']['prompt_tokens'] ?? 0,
                'completion_tokens' => $supportResult['raw_llm_response']['completion_tokens'] ?? 0,
                'total_tokens' => $supportResult['raw_llm_response']['total_tokens'] ?? 0,
            ],
            'latency_breakdown' => [
                'router_ms' => $routerLatency,
                'context_resolution_ms' => $contextLatency,
                'clarification_ms' => $clarificationLatency,
                'memory_retrieval_ms' => $memoryLatency,
                'business_context_ms' => $businessLatency,
                'retrieval_ms' => $knowledgeRetrievalLatency,
                'knowledge_retrieval_ms' => $knowledgeRetrievalLatency,
                'answerability_ms' => $answerabilityLatency,
                'llm_ms' => $llmLatency,
                'llm_generation_ms' => $llmLatency,
                'total_ms' => $totalElapsed,
                'total_e2e_ms' => (float) ($rawBreakdown['total_e2e_ms'] ?? $totalElapsed),
                'retrieval_sub_stages' => $supportResult['lexicon_telemetry'] ?? [],
            ],
            'lexicon_telemetry' => [
                'canonical_concepts' => $supportResult['lexicon_telemetry']['canonical_concepts'] ?? [],
                'expansion_triggered' => (bool) ($supportResult['lexicon_telemetry']['expansion_triggered'] ?? false),
                'expanded_query' => $supportResult['lexicon_telemetry']['expanded_query'] ?? null,
                'reranker_applied' => (bool) ($supportResult['lexicon_telemetry']['reranker_applied'] ?? false),
                'reranker_reason' => $supportResult['lexicon_telemetry']['reranker_reason'] ?? null,
                'first_pass_score' => $supportResult['lexicon_telemetry']['first_pass_score'] ?? null,
                'tier_executed' => $supportResult['lexicon_telemetry']['tier_executed'] ?? null,
            ],
        ];

        // ── Persistent Usage Tracking (Only for actual paid LLM calls) ──
        $hasPaidTokens = (($decisionTrace['llm_generation']['prompt_tokens'] ?? 0) + ($decisionTrace['llm_generation']['completion_tokens'] ?? 0)) > 0;
        if ($hasPaidTokens) {
            $metadata = $conversation->metadata ?? [];
            if (!isset($metadata['llm_usage_history'])) {
                $metadata['llm_usage_history'] = [];
            }
            $metadata['llm_usage_history'][] = $decisionTrace['llm_generation'];
            $conversation->update(['metadata' => $metadata]);
        }

        $conversation->refresh();

        return response()->json([
            'success' => true,
            'query' => $query,
            'reply' => $supportResult['reply'],
            'route' => $supportResult['route'],
            'agent_mode' => $agentMode,
            'agent_tested' => $agentTested,
            'suggestions' => $supportResult['suggestions'] ?? [],
            'sources' => $supportResult['sources'] ?? [],
            'active_spreadsheet' => [
                'file_id' => $conversation->metadata['active_file_id'] ?? null,
                'filename' => $conversation->metadata['active_filename'] ?? null,
                'sheets' => $conversation->metadata['active_file_sheets'] ?? [],
                'total_rows' => $conversation->metadata['active_file_rows'] ?? 0,
                'auto_resolved' => (bool) ($conversation->metadata['auto_resolved'] ?? false),
            ],
            'is_handoff' => $supportResult['is_handoff'] ?? false,
            'answered' => $supportResult['answered'],
            'decision_trace' => $decisionTrace,
            'answerability_decision' => $supportResult['answerability_decision'] ?? null,
            'raw_llm_response' => $supportResult['raw_llm_response'] ?? null,
            'confidence' => $topHit ? round($topHit->finalScore * 100, 1) : round(($supportResult['confidence'] ?? 1.0) * 100, 1),
            'match_type' => $topHit?->matchType ?? 'none',
            'matched_faq' => $topHit?->faq ? [
                'id' => (string) $topHit->faq->id,
                'question' => $topHit->faq->question,
                'answer' => $topHit->faq->answer,
                'priority' => $topHit->faq->priority,
                'document_type' => $topHit->faq->documentTypeLabel(),
            ] : null,

            'pipeline_diagnostics' => [
                'total_time_ms' => $totalElapsed,
                'answerability_decision' => $supportResult['answerability_decision'] ?? null,
                'memory_context' => $supportResult['memory_context'] ?? null,
                'has_memory_context' => !empty($supportResult['memory_context']),
                'business_context' => $supportResult['business_context'] ?? null,
                'has_business_context' => !empty($supportResult['business_context']),
                'routing_telemetry' => $supportResult['routing_telemetry'] ?? [],
                'crm_extracted' => [
                    'has_data' => $crm['has_data'],
                    'db_saved' => $crm['db_saved'],
                    'contact_id' => $crm['contact_id'],
                    'name' => $crm['name'] ?? null,
                    'address' => $crm['address'] ?? null,
                    'emails' => $crm['emails'],
                    'phones' => $crm['phones'],
                    'websites' => $crm['websites'],
                    'nid' => $crm['nid'],
                ],
                'python_service' => $this->retrievalDiagnostics(),
                'typesense' => [
                    'status' => $retrievalHits->isNotEmpty() ? 'ok' : 'degraded',
                    'latency_ms' => $knowledgeRetrievalLatency,
                    'hits_found' => $retrievalHits->count(),
                    'error' => null,
                ],
                'scores' => [
                    'keyword_score' => $topHit ? round($topHit->keywordScore * 100, 1) : 0.0,
                    'semantic_score' => $topHit ? round($topHit->semanticScore * 100, 1) : 0.0,
                    'final_confidence' => $topHit ? round($topHit->finalScore * 100, 1) : 0.0,
                    'threshold' => 40.0,
                ],
            ],
        ]);
    }

    /**
     * Determine whether the authenticated user has superadmin privileges.
     */
    private function isSuperAdmin(?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }

        try {
            if (class_exists(\App\Enums\RoleEnum::class) && $user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value)) {
                return true;
            }

            $superRoles = [
                'superadmin',
                'super-admin',
                'super_admin',
                'Super Admin',
                'SuperAdmin',
            ];
            if ($user->hasAnyRole($superRoles) || $user->can('access all workspaces')) {
                return true;
            }
        } catch (\Throwable) {
            // Ignore if role system is not configured
        }

        return false;
    }

    /**
     * Resolve effective workspace ID.
     */
    private function resolveWorkspaceId(?Request $request = null): int
    {
        $request = $request ?? request();
        $user = Auth::user();
        $isSuperAdmin = $this->isSuperAdmin($user);

        // 1. Explicitly requested workspace via query string or body (allowed for superadmin)
        if ($isSuperAdmin && $request && $request->filled('workspace_id')) {
            $requestedWsId = (int) $request->input('workspace_id');
            if (Workspace::where('id', $requestedWsId)->exists()) {
                session(['active_simulator_workspace_id' => $requestedWsId]);
                return $requestedWsId;
            }
        }

        // 2. Previously selected workspace in session for superadmin
        if ($isSuperAdmin && session()->has('active_simulator_workspace_id')) {
            $sessionWsId = (int) session('active_simulator_workspace_id');
            if (Workspace::where('id', $sessionWsId)->exists()) {
                return $sessionWsId;
            }
        }

        // 3. User's explicitly assigned workspace_id
        if ($user && $user->workspace_id) {
            return (int) $user->workspace_id;
        }

        // 4. Superadmin fallback to the first active workspace
        if ($isSuperAdmin) {
            $firstWs = Workspace::first();
            if ($firstWs) {
                session(['active_simulator_workspace_id' => (int) $firstWs->id]);
                return (int) $firstWs->id;
            }
        }

        abort(403, 'Unauthorized: User is not associated with an active workspace context.');
    }

    /**
     * Gather Python service health diagnostics.
     *
     * @return array<string, mixed>
     */
    private function retrievalDiagnostics(): array
    {
        $pyHealth = $this->retrievalClient->health();

        return [
            'status' => $pyHealth['ok'] ? 'ok' : 'degraded',
            'latency_ms' => $pyHealth['latency_ms'],
            'dimensions' => 768,
            'model' => 'paraphrase-multilingual-mpnet-base-v2',
            'vector_sample' => [],
            'error' => $pyHealth['error'],
        ];
    }

    private function resolveSimulatorConversation(Request $request, int $workspaceId): Conversation
    {
        $currentUser = Auth::user();
        $userIdentifier = $currentUser?->id ? 'user_' . $currentUser->id : ($request->hasSession() ? 'sess_' . $request->session()->getId() : 'default');
        $sessionKey = 'simulator_' . $userIdentifier . '_ws_' . $workspaceId;

        $channel = Channel::where('slug', 'simulator')->first() ?? Channel::create([
            'name' => 'Simulator Channel',
            'slug' => 'simulator',
            'driver' => 'web',
        ]);

        $channelAccount = ChannelAccount::firstOrCreate(
            ['workspace_id' => $workspaceId, 'external_id' => 'sim_acc_' . $workspaceId],
            [
                'name' => 'Simulator Account',
                'channel_id' => $channel->id,
                'access_token' => 'simulator_token',
                'is_active' => true,
            ]
        );

        if ($channelAccount->channel_id !== $channel->id) {
            $channelAccount->channel_id = $channel->id;
            $channelAccount->save();
        }

        $isOwnerOrAdmin = $currentUser?->hasRole('owner')
            || $currentUser?->hasRole('admin')
            || $currentUser?->hasRole('superadmin');
        $effectiveRole = $isOwnerOrAdmin
            ? 'owner'
            : ($currentUser?->hasRole('manager') ? 'manager' : ($currentUser?->roles->first()?->name ?? 'staff'));

        $conversation = Conversation::firstOrCreate(
            [
                'channel_account_id' => $channelAccount->id,
                'external_user_id' => $sessionKey,
            ],
            [
                'status' => 'active',
                'customer_name' => $currentUser?->name ?? 'Simulator User',
                'last_direction' => 'inbound',
                'metadata' => [
                    'is_simulator' => true,
                    'is_customer' => false,
                    'role' => $effectiveRole,
                ],
            ]
        );

        // Ensure metadata is populated and up-to-date for simulator conversations
        $meta = $conversation->metadata ?? [];
        $meta['is_simulator'] = true;
        $meta['is_customer'] = false;
        $meta['role'] = $effectiveRole;
        $conversation->metadata = $meta;
        $conversation->save();

        return $conversation;
    }

    /**
     * Clear simulator conversation history.
     */
    public function clear(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        $conversation = $this->resolveSimulatorConversation($request, $workspaceId);
        $conversation->messages()->delete();
        $conversation->update(['metadata' => []]);

        // Clean up any temporary CRM contacts created strictly from simulator testing
        \App\Models\CRMContact::where('workspace_id', $workspaceId)
            ->where('source', 'simulator')
            ->delete();

        return response()->json(['success' => true, 'message' => 'Simulator conversation history cleared.']);
    }


    /**
     * Clear active uploaded data source context from simulator session.
     */
    public function clearExcel(Request $request): JsonResponse
    {
        $workspaceId = $this->resolveWorkspaceId($request);
        $conversation = $this->resolveSimulatorConversation($request, $workspaceId);

        $metadata = $conversation->metadata ?? [];
        unset($metadata['active_file_id'], $metadata['active_filename'], $metadata['active_file_sheets'], $metadata['active_file_rows'], $metadata['uploaded_sources']);
        $conversation->metadata = $metadata;
        $conversation->save();

        return response()->json([
            'success' => true,
            'message' => 'Active data sources removed from simulator session.',
        ]);
    }

    /**
     * Select or clear active data source context in simulator session.
     */
    public function selectSource(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'nullable|string',
        ]);

        $workspaceId = $this->resolveWorkspaceId($request);
        $conversation = $this->resolveSimulatorConversation($request, $workspaceId);
        $fileId = $request->input('file_id');

        $metadata = $conversation->metadata ?? [];

        if (empty($fileId)) {
            unset($metadata['active_file_id'], $metadata['active_filename'], $metadata['active_file_sheets'], $metadata['active_file_rows']);
            $conversation->metadata = $metadata;
            $conversation->save();

            return response()->json([
                'success' => true,
                'message' => 'Active document context cleared. Queries will auto-resolve across all workspace sources.',
                'active_spreadsheet' => null,
            ]);
        }

        // Verify document belongs to workspace
        $doc = KnowledgeDocument::where('workspace_id', $workspaceId)
            ->where(function ($q) use ($fileId) {
                $q->where('source_id', $fileId)->orWhere('id', $fileId);
            })
            ->first();

        if ($doc) {
            $effectiveFileId = (string) ($doc->source_id ?? $doc->id);
            $effectiveSheets = $doc->metadata['sheets'] ?? $doc->sheets ?? [];
            $effectiveRows = (int) ($doc->chunk_count ?? $doc->total_rows ?? 0);

            $metadata['active_file_id'] = $effectiveFileId;
            $metadata['active_filename'] = $doc->original_filename ?: $doc->title;
            $metadata['active_file_sheets'] = $effectiveSheets;
            $metadata['active_file_rows'] = $effectiveRows;

            $uploadedSources = $metadata['uploaded_sources'] ?? [];
            $uploadedSources[$effectiveFileId] = [
                'file_id' => $effectiveFileId,
                'filename' => $doc->original_filename ?: $doc->title,
                'sheets' => $effectiveSheets,
                'total_rows' => $effectiveRows,
            ];
            $metadata['uploaded_sources'] = $uploadedSources;
            $conversation->metadata = $metadata;
            $conversation->save();

            return response()->json([
                'success' => true,
                'message' => 'Active document set to ' . ($doc->original_filename ?: $doc->title),
                'active_spreadsheet' => [
                    'file_id' => $effectiveFileId,
                    'filename' => $doc->original_filename ?: $doc->title,
                    'sheets' => $effectiveSheets,
                    'total_rows' => $effectiveRows,
                ],
            ]);
        }

        // If not found in KnowledgeDocument, check uploaded_sources in conversation metadata
        $uploadedSources = $metadata['uploaded_sources'] ?? [];
        if (isset($uploadedSources[$fileId])) {
            $src = $uploadedSources[$fileId];
            $metadata['active_file_id'] = $fileId;
            $metadata['active_filename'] = $src['filename'] ?? $fileId;
            $metadata['active_file_sheets'] = $src['sheets'] ?? [];
            $metadata['active_file_rows'] = $src['total_rows'] ?? 0;
            $conversation->metadata = $metadata;
            $conversation->save();

            return response()->json([
                'success' => true,
                'message' => 'Active document set to ' . ($src['filename'] ?? $fileId),
                'active_spreadsheet' => [
                    'file_id' => $fileId,
                    'filename' => $src['filename'] ?? $fileId,
                    'sheets' => $src['sheets'] ?? [],
                    'total_rows' => $src['total_rows'] ?? 0,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Document not found in workspace.',
        ], 404);
    }

    /**
     * Switch active simulator workspace (superadmin only).
     */
    public function switchWorkspace(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ]);

        $user = Auth::user();
        if (!$this->isSuperAdmin($user)) {
            abort(403, 'Unauthorized: Only superadmins can switch workspace context.');
        }

        $workspaceId = (int) $request->input('workspace_id');
        session(['active_simulator_workspace_id' => $workspaceId]);

        return response()->json([
            'success' => true,
            'message' => 'Active simulator workspace switched successfully.',
            'workspace_id' => $workspaceId,
        ]);
    }

    /**
     * 1-Click WhatsApp Cloud API Scenario Simulator.
     */
    public function simulateWhatsAppScenario(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$this->isSuperAdmin($user)) {
            abort(403, 'Unauthorized.');
        }

        $type = (string) $request->input('type', 'text');
        $query = match ($type) {
            'button' => '[Interactive Button: অর্ডার কনফার্ম করুন]',
            'media'  => '[Attachment: Image] এই শার্টটির প্রাইস কত?',
            default  => 'আপনাদের ক্যাশ অন ডেলিভারি চার্জ কত?',
        };

        $request->merge([
            'message'    => $query,
            'agent_mode' => 'whatsapp',
        ]);

        return $this->send($request);
    }

    /**
     * 1-Click Post-Delivery CSAT Feedback Scenario Simulator.
     */
    public function simulateDeliveryCsatScenario(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$this->isSuperAdmin($user)) {
            abort(403, 'Unauthorized.');
        }

        $action = (string) $request->input('action', 'trigger_delivery');
        $query = match ($action) {
            'rating_5' => '৫ স্টার, অনেক ভালো সার্ভিস!',
            'rating_1' => '১ স্টার - সাইজ মিলেনি, বাজে ডেলিভারি',
            default    => 'Steadfast Courier Delivered #ORD-SIM',
        };

        $request->merge([
            'message'    => $query,
            'agent_mode' => 'csat',
        ]);

        return $this->send($request);
    }

    /**
     * 1-Click Shopify / WooCommerce Catalog Sync Scenario Simulator.
     */
    public function simulateCatalogSyncScenario(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$this->isSuperAdmin($user)) {
            abort(403, 'Unauthorized.');
        }

        $platform = (string) $request->input('platform', 'shopify');
        $query = $platform === 'woocommerce'
            ? 'Sync WooCommerce Store Catalog Product'
            : 'Sync Shopify Store Catalog Product';

        $request->merge([
            'message'    => $query,
            'agent_mode' => 'catalog_sync',
        ]);

        return $this->send($request);
    }
}



