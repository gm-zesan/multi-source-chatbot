<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdversarialLexiconChallengeTest extends TestCase
{
    use RefreshDatabase;

    private RetrievalClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new RetrievalClient('http://127.0.0.1:8002');
    }

    /**
     * CHALLENGE 1 (REMEDIATED): Token type fidelity with numeric tokens
     *
     * In PHP, array_keys() naturally coerces numeric string keys to integers.
     * extractLexiconTerms() must explicitly cast array keys back to string primitives
     * (e.g. "19", "101", "24", "2500") to satisfy JSON List[str] schema contracts.
     */
    public function test_extract_lexicon_terms_preserves_string_types_for_numeric_tokens(): void
    {
        $faq = new FAQ([
            'question' => 'What is the COVID-19 policy for Room 101?',
            'answer'   => 'Hotline is open 24/7. Admission fee is 2500.',
        ]);

        $terms = $this->client->extractLexiconTerms($faq);

        // Must not contain any integer types
        $nonStringTerms = array_filter($terms, fn($t) => !is_string($t));
        $this->assertEmpty($nonStringTerms, 'All extracted lexicon terms must be strictly strings');

        // Assert string representations of numeric tokens are present
        $this->assertContains('19', $terms);
        $this->assertContains('101', $terms);
        $this->assertContains('24', $terms);
        $this->assertContains('2500', $terms);

        // Assert integer primitives are NOT present
        $this->assertNotContains(19, $terms, '', true);
        $this->assertNotContains(101, $terms, '', true);
        $this->assertNotContains(24, $terms, '', true);
        $this->assertNotContains(2500, $terms, '', true);
    }

    /**
     * CHALLENGE 1: Extreme and Boundary Inputs
     */
    public function test_extract_lexicon_terms_handles_extreme_inputs_gracefully(): void
    {
        // 1. Empty strings & whitespace
        $faqEmpty = new FAQ(['question' => '', 'answer' => "  \n\t  "]);
        $this->assertSame([], $this->client->extractLexiconTerms($faqEmpty));

        // 2. Pure punctuation & symbols
        $faqPunct = new FAQ(['question' => '!@#$%^&*()_+=-{}[]:;"\'<>,.?/~`|\\']);
        $this->assertSame([], $this->client->extractLexiconTerms($faqPunct));

        // 3. Emojis
        $faqEmoji = new FAQ(['question' => '😀🚀🔥🎉🏨🏥🩺🎓📚🛒📦', 'answer' => '❤️👍👋']);
        $this->assertSame([], $this->client->extractLexiconTerms($faqEmoji));

        // 4. Massive 10,000-character text
        $longText = str_repeat('admission registration fee appointment doctor clinic hotel reservation delivery refund tracking ', 100);
        $faqMassive = new FAQ(['question' => 'Massive text stress test', 'answer' => $longText]);
        $start = microtime(true);
        $termsMassive = $this->client->extractLexiconTerms($faqMassive);
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(50, $elapsedMs, 'Processing 10,000 characters should take < 50ms');
        $this->assertLessThanOrEqual(50, count($termsMassive), 'Must never exceed 50 terms cap');

        // 5. Bengali complex conjuncts, matras, and nuktas
        $bengaliText = 'যুক্তবর্ণ: ক্ষ জ্ঞ হ্ণ স্ট্র ড় ঢ় য় ড় ঢ় য় বাংলা ইউনিকোড পরীক্ষা';
        $faqBengali = new FAQ(['question' => $bengaliText]);
        $termsBengali = $this->client->extractLexiconTerms($faqBengali);

        $this->assertContains('যুক্তবর্ণ', $termsBengali);
        $this->assertContains('ক্ষ', $termsBengali);
        $this->assertContains('জ্ঞ', $termsBengali);
        $this->assertContains('হ্ণ', $termsBengali);
        $this->assertContains('স্ট্র', $termsBengali);
        $this->assertContains('বাংলা', $termsBengali);

        // 6. HTML tags & entity decoding
        $faqHtml = new FAQ([
            'question' => '<script>alert("xss")</script> <b>Admission</b> &amp; <i>Fees</i>',
            'answer'   => '<p style="color:red">Tuition details</p>',
        ]);
        $termsHtml = $this->client->extractLexiconTerms($faqHtml);
        $this->assertNotContains('script', $termsHtml);
        $this->assertNotContains('p', $termsHtml);
        $this->assertNotContains('b', $termsHtml);
        $this->assertContains('admission', $termsHtml);
        $this->assertContains('fees', $termsHtml);
        $this->assertContains('tuition', $termsHtml);
    }

    /**
     * CHALLENGE 1: Domain-Neutrality (No hardcoded industry stop words)
     */
    public function test_extract_lexicon_terms_does_not_filter_domain_words(): void
    {
        $domainWords = [
            'hotel', 'room', 'stay', 'booking', 'reservation',
            'clinic', 'doctor', 'appointment', 'patient', 'hospital',
            'university', 'admission', 'student', 'tuition', 'fee',
            'retail', 'order', 'delivery', 'refund', 'exchange', 'price',
        ];

        $faq = new FAQ([
            'question' => implode(' ', $domainWords),
        ]);

        $terms = $this->client->extractLexiconTerms($faq);

        // Every domain word must be retained as a lexicon term
        foreach ($domainWords as $word) {
            $this->assertContains($word, $terms, "Domain word '{$word}' should not be filtered as a stop word.");
        }
    }
}
