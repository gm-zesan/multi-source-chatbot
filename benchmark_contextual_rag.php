<?php

declare(strict_types=1);

/**
 * =============================================================================
 * MULTI-TURN CONTEXTUAL RAG BENCHMARK
 * =============================================================================
 * Compares:
 *   - Pipeline A (Baseline): Raw Follow-up Query -> Retrieval
 *   - Pipeline B (Experiment): Contextualized Query (Query + Context) -> Retrieval
 * Across 5 Conversational Follow-Up Categories:
 *   1. Category A: Direct Follow-up
 *   2. Category B: Pronoun Follow-up
 *   3. Category C: Elliptical Follow-up
 *   4. Category D: Topic Continuation
 *   5. Category E: Context Switch / Interleaved Conversational Turns
 * =============================================================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;

echo "=================================================================================================\n";
echo "📊 MULTI-TURN CONTEXTUAL RAG COMPARATIVE BENCHMARK\n";
echo "=================================================================================================\n";

$retrievalClient = app(RetrievalClient::class);
$ws = Workspace::find(1);

// ── 1. Benchmark Test Dataset (25 Multi-Turn Conversational Pairs) ───────────
$testSet = [
    // ── Category A: Direct Follow-Up ──
    [
        'category'      => 'Category A: Direct Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How long is your free trial?'],
            ['role' => 'assistant', 'text' => 'We offer a 14-day free trial on our Pro plan with full access to all features.'],
        ],
        'raw_query'     => 'Can I extend it?',
        'contextual'    => 'Can I extend the 14-day free trial?',
        'target_faq'    => 'Is there a free trial?',
    ],
    [
        'category'      => 'Category A: Direct Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'What plans do you have?'],
            ['role' => 'assistant', 'text' => 'We have Free, Pro, and Enterprise subscription plans.'],
        ],
        'raw_query'     => 'How much is the pro tier?',
        'contextual'    => 'What are the subscription plans and pricing for the Pro tier?',
        'target_faq'    => 'What plans are available?',
    ],
    [
        'category'      => 'Category A: Direct Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'Do you offer non profit discounts?'],
            ['role' => 'assistant', 'text' => 'Yes, we offer a 50% discount for registered non-profit organizations.'],
        ],
        'raw_query'     => 'How do I apply for this?',
        'contextual'    => 'How do I apply for the non-profit organization 50% discount?',
        'target_faq'    => 'Do you offer discounts for non-profits?',
    ],
    [
        'category'      => 'Category A: Direct Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'Why is my chatbot not replying to messages?'],
            ['role' => 'assistant', 'text' => 'This usually happens if the channel account token has expired or if webhook events are disabled.'],
        ],
        'raw_query'     => 'How do I fix the token issue?',
        'contextual'    => 'How to resolve chatbot not responding and fix expired channel token?',
        'target_faq'    => 'Why is my chatbot not responding?',
    ],
    [
        'category'      => 'Category A: Direct Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How can I connect WhatsApp?'],
            ['role' => 'assistant', 'text' => 'You can connect WhatsApp via the Meta Cloud API from Settings > Channels.'],
        ],
        'raw_query'     => 'Does it support official business numbers?',
        'contextual'    => 'Does connecting WhatsApp support official business numbers?',
        'target_faq'    => 'How do I connect WhatsApp?',
    ],

    // ── Category B: Pronoun Follow-Up ──
    [
        'category'      => 'Category B: Pronoun Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How is customer data encrypted on the platform?'],
            ['role' => 'assistant', 'text' => 'Data is encrypted at rest using AES-256 and in transit using TLS 1.3.'],
        ],
        'raw_query'     => 'Is it compliant with European privacy laws?',
        'contextual'    => 'Is the platform and data security compliant with GDPR European privacy laws?',
        'target_faq'    => 'Does the platform comply with GDPR?',
    ],
    [
        'category'      => 'Category B: Pronoun Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How do I enable two-factor authentication?'],
            ['role' => 'assistant', 'text' => 'Navigate to Profile > Security and enable 2FA using Google Authenticator.'],
        ],
        'raw_query'     => 'Can I turn it off later?',
        'contextual'    => 'Can I disable two-factor authentication 2FA later from security settings?',
        'target_faq'    => 'How do I enable two-factor authentication?',
    ],
    [
        'category'      => 'Category B: Pronoun Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'Where can I get my API key?'],
            ['role' => 'assistant', 'text' => 'Go to Settings > Developer > API Keys to generate a new key.'],
        ],
        'raw_query'     => 'How do I use it to authenticate requests?',
        'contextual'    => 'How do I use the API key to authenticate API requests with Bearer token?',
        'target_faq'    => 'How do I authenticate API requests?',
    ],
    [
        'category'      => 'Category B: Pronoun Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'Can I use multiple channels at the same time?'],
            ['role' => 'assistant', 'text' => 'Yes, you can connect WhatsApp, Facebook, Telegram, and Web Chat concurrently.'],
        ],
        'raw_query'     => 'Are they synced in one single inbox?',
        'contextual'    => 'Are multiple connected communication channels synced together in one inbox?',
        'target_faq'    => 'Can I use multiple channels simultaneously?',
    ],
    [
        'category'      => 'Category B: Pronoun Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'Can I change my subscription plan?'],
            ['role' => 'assistant', 'text' => 'You can upgrade or downgrade at any time from Settings > Billing.'],
        ],
        'raw_query'     => 'Does it take effect immediately?',
        'contextual'    => 'Does changing or upgrading subscription plan take effect immediately?',
        'target_faq'    => 'Can I change my plan?',
    ],

    // ── Category C: Elliptical Follow-Up ──
    [
        'category'      => 'Category C: Elliptical Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How do I connect WhatsApp?'],
            ['role' => 'assistant', 'text' => 'Go to Settings > Channels > WhatsApp and enter your Meta phone number ID and access token.'],
        ],
        'raw_query'     => 'And Telegram?',
        'contextual'    => 'How do I connect Telegram bot to the platform?',
        'target_faq'    => 'How do I connect Telegram?',
    ],
    [
        'category'      => 'Category C: Elliptical Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How do I get my API key?'],
            ['role' => 'assistant', 'text' => 'Generate an API key in Settings > API Keys.'],
        ],
        'raw_query'     => 'What about rate limits?',
        'contextual'    => 'What are the API rate limits per minute and hour?',
        'target_faq'    => 'What are the API rate limits?',
    ],
    [
        'category'      => 'Category C: Elliptical Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How do I update my payment method?'],
            ['role' => 'assistant', 'text' => 'Go to Settings > Billing > Payment Methods and add your credit card.'],
        ],
        'raw_query'     => 'And invoices?',
        'contextual'    => 'How do I view and download my billing invoices in PDF?',
        'target_faq'    => 'How do I view my invoices?',
    ],
    [
        'category'      => 'Category C: Elliptical Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How do I create a new user account?'],
            ['role' => 'assistant', 'text' => 'Click Register on the homepage and verify your email.'],
        ],
        'raw_query'     => 'What about after logging in for the first time?',
        'contextual'    => 'What do I do after logging in for the first time to setup workspace?',
        'target_faq'    => 'What do I do after logging in for the first time?',
    ],
    [
        'category'      => 'Category C: Elliptical Follow-up',
        'history'       => [
            ['role' => 'user', 'text' => 'How often should I update FAQs?'],
            ['role' => 'assistant', 'text' => 'We recommend reviewing FAQs monthly or whenever policies change.'],
        ],
        'raw_query'     => 'And how to improve accuracy?',
        'contextual'    => 'How can I improve chatbot response accuracy and FAQ quality?',
        'target_faq'    => 'How can I improve chatbot response accuracy?',
    ],

    // ── Category D: Topic Continuation ──
    [
        'category'      => 'Category D: Topic Continuation',
        'history'       => [
            ['role' => 'user', 'text' => 'Tell me about setting up a workspace.'],
            ['role' => 'assistant', 'text' => 'You can configure workspace name, logo, and time zone from Workspace Settings.'],
        ],
        'raw_query'     => 'Where do I configure team members and permissions?',
        'contextual'    => 'How do I set up my workspace team members and general settings?',
        'target_faq'    => 'How do I set up my workspace?',
    ],
    [
        'category'      => 'Category D: Topic Continuation',
        'history'       => [
            ['role' => 'user', 'text' => 'What makes a good FAQ answer?'],
            ['role' => 'assistant', 'text' => 'A clear, direct answer with bullet points and actionable steps.'],
        ],
        'raw_query'     => 'How frequently should these answers be updated?',
        'contextual'    => 'How often should I update my FAQs and knowledge base answers?',
        'target_faq'    => 'How often should I update my FAQs?',
    ],
    [
        'category'      => 'Category D: Topic Continuation',
        'history'       => [
            ['role' => 'user', 'text' => 'I am getting an error when syncing messages.'],
            ['role' => 'assistant', 'text' => 'Please check your channel webhook logs and API rate limits.'],
        ],
        'raw_query'     => 'Why are messages not being delivered to customers?',
        'contextual'    => 'Why are my messages not being delivered to recipients?',
        'target_faq'    => 'Why are my messages not being delivered?',
    ],
    [
        'category'      => 'Category D: Topic Continuation',
        'history'       => [
            ['role' => 'user', 'text' => 'I am having an issue with the system.'],
            ['role' => 'assistant', 'text' => 'Please provide the error code or screenshot.'],
        ],
        'raw_query'     => 'What should I do if I encounter an error?',
        'contextual'    => 'What should I do if I encounter an error in the platform?',
        'target_faq'    => 'What should I do if I encounter an error?',
    ],
    [
        'category'      => 'Category D: Topic Continuation',
        'history'       => [
            ['role' => 'user', 'text' => 'How do I view my billing history?'],
            ['role' => 'assistant', 'text' => 'Go to Settings > Billing > Invoices.'],
        ],
        'raw_query'     => 'Can I update the credit card on file for payments?',
        'contextual'    => 'How do I update my payment method and credit card?',
        'target_faq'    => 'How do I update my payment method?',
    ],

    // ── Category E: Context Switch / Interleaved Turns ──
    [
        'category'      => 'Category E: Context Switch',
        'history'       => [
            ['role' => 'user', 'text' => 'Where can I get an API key?'],
            ['role' => 'assistant', 'text' => 'Go to Settings > API Keys to generate a key.'],
            ['role' => 'user', 'text' => 'Great, thank you!'],
            ['role' => 'assistant', 'text' => 'You are very welcome! Is there anything else?'],
        ],
        'raw_query'     => 'What are the limits on it?',
        'contextual'    => 'What are the API rate limits on API keys?',
        'target_faq'    => 'What are the API rate limits?',
    ],
    [
        'category'      => 'Category E: Context Switch',
        'history'       => [
            ['role' => 'user', 'text' => 'How is data encrypted?'],
            ['role' => 'assistant', 'text' => 'With AES-256 and TLS 1.3.'],
            ['role' => 'user', 'text' => 'Awesome, thanks.'],
            ['role' => 'assistant', 'text' => 'Glad to help!'],
        ],
        'raw_query'     => 'Does this comply with GDPR?',
        'contextual'    => 'Does the platform comply with GDPR privacy regulation?',
        'target_faq'    => 'Does the platform comply with GDPR?',
    ],
    [
        'category'      => 'Category E: Context Switch',
        'history'       => [
            ['role' => 'user', 'text' => 'Do you have a free trial?'],
            ['role' => 'assistant', 'text' => 'Yes, a 14-day free trial on Pro.'],
            ['role' => 'user', 'text' => 'Hello again!'],
            ['role' => 'assistant', 'text' => 'Hello! How can I help?'],
        ],
        'raw_query'     => 'Do you also have discounts for non profits?',
        'contextual'    => 'Do you offer discounts for non-profits and charity organizations?',
        'target_faq'    => 'Do you offer discounts for non-profits?',
    ],
    [
        'category'      => 'Category E: Context Switch',
        'history'       => [
            ['role' => 'user', 'text' => 'How do I log in for the first time?'],
            ['role' => 'assistant', 'text' => 'Use your registration credentials and confirm email.'],
            ['role' => 'user', 'text' => 'Okay got it.'],
            ['role' => 'assistant', 'text' => 'Let me know if you need anything else!'],
        ],
        'raw_query'     => 'How to set up the workspace after that?',
        'contextual'    => 'How do I set up my workspace after logging in?',
        'target_faq'    => 'How do I set up my workspace?',
    ],
    [
        'category'      => 'Category E: Context Switch',
        'history'       => [
            ['role' => 'user', 'text' => 'What plans are available?'],
            ['role' => 'assistant', 'text' => 'Free, Pro, and Enterprise.'],
            ['role' => 'user', 'text' => 'Cool.'],
            ['role' => 'assistant', 'text' => 'Happy to help!'],
        ],
        'raw_query'     => 'Can I change my plan later?',
        'contextual'    => 'Can I change my plan or upgrade subscription later?',
        'target_faq'    => 'Can I change my plan?',
    ],
];

// ── 2. Run Comparative Evaluation ────────────────────────────────────────────

$metrics = [
    'baseline' => [
        'top1' => 0,
        'top3' => 0,
        'mrr'  => 0.0,
        'lat'  => 0.0,
    ],
    'experiment' => [
        'top1' => 0,
        'top3' => 0,
        'mrr'  => 0.0,
        'lat'  => 0.0,
    ],
];

$categoryStats = [];

$total = count($testSet);
$contextualQueryBuilder = app(\App\Services\AI\ContextualQueryBuilder::class);

$channel = \App\Models\Channel::first() ?? \App\Models\Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
$acc = \App\Models\ChannelAccount::firstOrCreate(
    ['workspace_id' => $ws->id, 'external_id' => 'bench_acc'],
    ['name' => 'Bench Acc', 'channel_id' => $channel->id, 'access_token' => 'tok', 'is_active' => true]
);

foreach ($testSet as $idx => $item) {
    $cat = $item['category'];
    if (!isset($categoryStats[$cat])) {
        $categoryStats[$cat] = [
            'count'       => 0,
            'base_top1'   => 0,
            'exp_top1'    => 0,
            'base_top3'   => 0,
            'exp_top3'    => 0,
        ];
    }
    $categoryStats[$cat]['count']++;

    // Create session conversation to feed ContextualQueryBuilder
    $conv = \App\Models\Conversation::create([
        'channel_account_id' => $acc->id,
        'external_user_id'   => 'bench_user_' . uniqid(),
        'status'             => 'active',
        'last_direction'     => 'inbound',
    ]);
    foreach ($item['history'] as $hMsg) {
        \App\Models\Message::create([
            'conversation_id' => $conv->id,
            'direction'       => $hMsg['role'] === 'user' ? 'inbound' : 'outbound',
            'type'            => 'text',
            'body'            => $hMsg['text'],
        ]);
    }

    // 1. BASELINE: Raw Query
    $t_start = microtime(true);
    $baseHits = $retrievalClient->search($item['raw_query'], $ws->id, 5);
    $baseLat = round((microtime(true) - $t_start) * 1000, 2);
    $metrics['baseline']['lat'] += $baseLat;

    $baseRank = 0;
    foreach ($baseHits as $rIdx => $hit) {
        if ($hit->faq && stripos($hit->faq->question, $item['target_faq']) !== false) {
            $baseRank = $rIdx + 1;
            break;
        }
    }
    if ($baseRank === 1) {
        $metrics['baseline']['top1']++;
        $categoryStats[$cat]['base_top1']++;
    }
    if ($baseRank >= 1 && $baseRank <= 3) {
        $metrics['baseline']['top3']++;
        $categoryStats[$cat]['base_top3']++;
    }
    if ($baseRank > 0) {
        $metrics['baseline']['mrr'] += (1.0 / $baseRank);
    }

    // 2. EXPERIMENT: Dynamic Contextualized Query via Live ContextualQueryBuilder
    $dynamicContextualQuery = $contextualQueryBuilder->buildContextualQuery($item['raw_query'], $conv);

    $t_start2 = microtime(true);
    $expHits = $retrievalClient->search($dynamicContextualQuery, $ws->id, 5);
    $expLat = round((microtime(true) - $t_start2) * 1000, 2);
    $metrics['experiment']['lat'] += $expLat;

    $expRank = 0;
    foreach ($expHits as $rIdx => $hit) {
        if ($hit->faq && stripos($hit->faq->question, $item['target_faq']) !== false) {
            $expRank = $rIdx + 1;
            break;
        }
    }
    if ($expRank === 1) {
        $metrics['experiment']['top1']++;
        $categoryStats[$cat]['exp_top1']++;
    }
    if ($expRank >= 1 && $expRank <= 3) {
        $metrics['experiment']['top3']++;
        $categoryStats[$cat]['exp_top3']++;
    }
    if ($expRank > 0) {
        $metrics['experiment']['mrr'] += (1.0 / $expRank);
    }

    $bIcon = ($baseRank === 1) ? '✅' : ($baseRank > 0 ? '🟡' : '❌');
    $eIcon = ($expRank === 1) ? '✅' : ($expRank > 0 ? '🟡' : '❌');
    $n = $idx + 1;
    echo "  #{$n} [{$cat}]\n";
    echo "     Raw Query:          \"{$item['raw_query']}\" ──> {$bIcon} Rank {$baseRank} ({$baseLat} ms)\n";
    echo "     Contextualized:     \"{$dynamicContextualQuery}\" ──> {$eIcon} Rank {$expRank} ({$expLat} ms)\n";
    echo "     Target FAQ:         \"{$item['target_faq']}\"\n\n";
}

// ── 3. Summary Scorecard & Category Breakdown ────────────────────────────────

$baseTop1Pct = round(($metrics['baseline']['top1'] / $total) * 100, 2);
$baseTop3Pct = round(($metrics['baseline']['top3'] / $total) * 100, 2);
$baseMrr     = round($metrics['baseline']['mrr'] / $total, 4);
$baseAvgLat  = round($metrics['baseline']['lat'] / $total, 2);

$expTop1Pct  = round(($metrics['experiment']['top1'] / $total) * 100, 2);
$expTop3Pct  = round(($metrics['experiment']['top3'] / $total) * 100, 2);
$expMrr      = round($metrics['experiment']['mrr'] / $total, 4);
$expAvgLat   = round($metrics['experiment']['lat'] / $total, 2);

echo "=================================================================================================\n";
echo "🏆 MULTI-TURN CONTEXTUAL RAG COMPARATIVE SCORECARD\n";
echo "=================================================================================================\n";
printf("%-24s | %-16s | %-16s | %-16s\n", "Metric", "Baseline (Raw)", "Experiment (Context)", "Delta / Gain");
echo "-------------------------------------------------------------------------------------------------\n";
printf("%-24s | %-15.1f%% | %-15.1f%% | %s\n", "Top-1 Retrieval Accuracy", $baseTop1Pct, $expTop1Pct, sprintf("+%.1f%%", $expTop1Pct - $baseTop1Pct));
printf("%-24s | %-15.1f%% | %-15.1f%% | %s\n", "Top-3 Retrieval Accuracy", $baseTop3Pct, $expTop3Pct, sprintf("+%.1f%%", $expTop3Pct - $baseTop3Pct));
printf("%-24s | %-16.4f | %-16.4f | %s\n", "MRR (Mean Recip. Rank)", $baseMrr, $expMrr, sprintf("+%.4f", $expMrr - $baseMrr));
printf("%-24s | %-13.2f ms | %-13.2f ms | %s\n", "Avg Retrieval Latency", $baseAvgLat, $expAvgLat, sprintf("%.2f ms", $expAvgLat - $baseAvgLat));
echo "-------------------------------------------------------------------------------------------------\n";

echo "\n📊 CATEGORY BREAKDOWN (Top-1 Accuracy):\n";
printf("%-38s | %-14s | %-14s | %-12s\n", "Category", "Baseline Top-1", "Experiment Top-1", "Improvement");
echo "---------------------------------------------------------------------------------------\n";
foreach ($categoryStats as $cName => $cData) {
    $bRate = round(($cData['base_top1'] / $cData['count']) * 100, 1);
    $eRate = round(($cData['exp_top1'] / $cData['count']) * 100, 1);
    $diff  = $eRate - $bRate;
    printf("%-38s | %5.1f%% (%d/%d)  | %5.1f%% (%d/%d)  | %+5.1f%%\n",
        $cName,
        $bRate, $cData['base_top1'], $cData['count'],
        $eRate, $cData['exp_top1'], $cData['count'],
        $diff
    );
}
echo "=================================================================================================\n";
