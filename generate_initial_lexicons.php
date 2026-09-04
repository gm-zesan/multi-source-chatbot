<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FAQ;
use App\Services\FAQ\FaqLexiconGeneratorService;
use App\Services\Retrieval\RetrievalClient;

$generator = app(FaqLexiconGeneratorService::class);
$retrievalClient = app(RetrievalClient::class);

$faqs = FAQ::where('is_active', true)->get();
echo "=================================================================================================\n";
echo "📦 GENERATING & VALIDATING COMMERCE LEXICONS FOR ALL EXISTING FAQS (" . $faqs->count() . " FAQs)\n";
echo "=================================================================================================\n\n";

$syncedCount = 0;
foreach ($faqs as $idx => $faq) {
    echo "Processing [" . ($idx + 1) . "/{$faqs->count()}]: \"{$faq->question}\" ...\n";
    $lexicon = $generator->generateAndStore($faq);

    if ($lexicon) {
        $faq->load('lexicon');
        $synced = $retrievalClient->syncFaq($faq);
        $termCount = count($lexicon->allTerms());
        echo "   ↳ Domain: {$lexicon->domain} | Intent: {$lexicon->intent} | Terms: {$termCount} | Synced: " . ($synced ? '✅' : '❌') . "\n";
        echo "   ↳ Sample Terms: " . implode(', ', array_slice($lexicon->allTerms(), 0, 5)) . " ...\n";
        $syncedCount++;
    } else {
        echo "   ⚠️ Failed to generate lexicon, syncing raw FAQ...\n";
        $retrievalClient->syncFaq($faq);
    }
}

echo "\n=================================================================================================\n";
echo "✅ Batch Lexicon Generation Complete! Total Synced: {$syncedCount} / {$faqs->count()}\n";
echo "=================================================================================================\n";
