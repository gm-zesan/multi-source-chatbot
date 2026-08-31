<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FAQ;
use App\Models\FaqLexicon;
use App\Services\FAQ\CommerceOntology;

echo "=================================================================================================\n";
echo "🔬 PRODUCTION FAQ LEXICON QUALITY & DOMAIN DISTRIBUTION AUDIT\n";
echo "=================================================================================================\n\n";

$lexicons = FaqLexicon::with('faq')->get();
$totalFaqs = FAQ::where('is_active', true)->count();
$totalLexicons = $lexicons->count();

echo "• Total Active FAQs in Database:   {$totalFaqs}\n";
echo "• Total Generated Lexicon Records: {$totalLexicons}\n";
echo "• Lexicon Coverage:                " . ($totalFaqs > 0 ? round(($totalLexicons / $totalFaqs) * 100, 1) : 0) . "%\n\n";

$domainDistribution = [];
$allTermsCombined = [];
$shortTermWarnings = [];
$overlyBroadStopwords = ['the', 'how', 'do', 'i', 'is', 'a', 'an', 'what', 'why', 'are', 'my', 'can', 'does'];
$noisyTermsDetected = [];

echo "─────────────────────────────────────────────────────────────────────────────────────────────────\n";
echo sprintf("%-4s | %-32s | %-28s | %-6s | %-30s\n", "No", "FAQ Question", "Domain", "Terms", "Sample Lexicon Terms");
echo "─────────────────────────────────────────────────────────────────────────────────────────────────\n";

foreach ($lexicons as $idx => $lex) {
    $faq = $lex->faq;
    $q = $faq ? $faq->question : 'Orphan Lexicon';
    $domain = $lex->domain;
    $domainDistribution[$domain] = ($domainDistribution[$domain] ?? 0) + 1;

    $terms = $lex->allTerms();
    $termCount = count($terms);

    foreach ($terms as $t) {
        $allTermsCombined[] = mb_strtolower($t);
        if (in_array(mb_strtolower($t), $overlyBroadStopwords, true)) {
            $noisyTermsDetected[$t] = ($noisyTermsDetected[$t] ?? 0) + 1;
        }
        if (mb_strlen($t) < 3) {
            $shortTermWarnings[] = "FAQ #{$idx}: '{$t}'";
        }
    }

    $sample = implode(', ', array_slice($terms, 0, 4));
    echo sprintf(
        "%-4d | %-32s | %-28s | %-6d | %-30s\n",
        $idx + 1,
        mb_substr($q, 0, 32),
        mb_substr($domain, 0, 28),
        $termCount,
        mb_substr($sample, 0, 30)
    );
}

echo "\n=================================================================================================\n";
echo "📊 1. COMMERCE DOMAIN DISTRIBUTION BREAKDOWN\n";
echo "=================================================================================================\n";
foreach (CommerceOntology::ALL_DOMAINS as $d) {
    $count = $domainDistribution[$d] ?? 0;
    $pct = $totalLexicons > 0 ? round(($count / $totalLexicons) * 100, 1) : 0;
    $bar = str_repeat('█', (int) ($count * 2));
    echo sprintf("  • %-32s : %2d (%5.1f%%) %s\n", $d, $count, $pct, $bar);
}

$uniqueTermsCount = count(array_unique($allTermsCombined));
$totalTermsCount = count($allTermsCombined);
$avgTerms = $totalLexicons > 0 ? round($totalTermsCount / $totalLexicons, 1) : 0;

echo "\n=================================================================================================\n";
echo "📊 2. LEXICON QUALITY & NOISE METRICS\n";
echo "=================================================================================================\n";
echo "  • Total Search Terms Indexed:     {$totalTermsCount}\n";
echo "  • Unique Canonical Terms:         {$uniqueTermsCount}\n";
echo "  • Average Terms Per FAQ:          {$avgTerms} terms\n";
echo "  • Noisy / Pure Stopword Terms:    " . count($noisyTermsDetected) . "\n";
if (!empty($noisyTermsDetected)) {
    echo "     ↳ Detected stopwords to clean: " . implode(', ', array_keys($noisyTermsDetected)) . "\n";
}
echo "  • Ultra-Short Terms (< 3 chars):  " . count($shortTermWarnings) . "\n";
echo "=================================================================================================\n";
