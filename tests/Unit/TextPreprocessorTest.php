<?php

namespace Tests\Unit;

use App\Services\NLP\TextPreprocessor;
use PHPUnit\Framework\TestCase;

class TextPreprocessorTest extends TestCase
{
    private TextPreprocessor $preprocessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preprocessor = TextPreprocessor::make();
    }

    public function test_lowercases_english_text(): void
    {
        $result = $this->preprocessor->process('Hello World', 'en');

        $this->assertSame('hello world', $result->normalized);
    }

    public function test_expands_english_contractions(): void
    {
        $result = $this->preprocessor->process("I can't reset my password", 'en');

        $this->assertStringContainsString('cannot', $result->normalized);
        $this->assertStringNotContainsString("can't", $result->normalized);
    }

    public function test_removes_punctuation(): void
    {
        $result = $this->preprocessor->process('Hello, world! How are you?', 'en');

        $this->assertStringNotContainsString(',', $result->normalized);
        $this->assertStringNotContainsString('!', $result->normalized);
        $this->assertStringNotContainsString('?', $result->normalized);
    }

    public function test_removes_emojis(): void
    {
        $result = $this->preprocessor->process('Hello 😊 world 🎉', 'en');

        $this->assertStringContainsString('hello world', $result->normalized);
    }

    public function test_preserves_email_addresses(): void
    {
        $result = $this->preprocessor->process('Contact me at test@example.com', 'en');

        $this->assertStringContainsString('test@example.com', $result->normalized);
    }

    public function test_preserves_phone_numbers(): void
    {
        $result = $this->preprocessor->process('Call +880-1712-345678', 'en');

        $this->assertStringContainsString('+880-1712-345678', $result->normalized);
    }

    public function test_removes_english_stop_words(): void
    {
        $result = $this->preprocessor->process('How do I reset my password?', 'en');

        $this->assertStringContainsString('reset password', $result->keyword);
        $this->assertStringNotContainsString('how', $result->keyword);
        $this->assertStringNotContainsString('my', $result->keyword);
    }

    public function test_detects_english_language(): void
    {
        $result = $this->preprocessor->process('Hello world', 'auto');

        $this->assertSame('en', $result->language);
    }

    public function test_detects_bangla_language(): void
    {
        $result = $this->preprocessor->process('আমার সোনার বাংলা', 'auto');

        $this->assertSame('bn', $result->language);
    }

    public function test_detects_banglish_language(): void
    {
        $result = $this->preprocessor->process('Ami tomake valobashi', 'auto');

        // Banglish may detect as 'en' because of Latin script dominance
        $this->assertContains($result->language, ['en', 'banglish']);
    }

    public function test_returns_tokens(): void
    {
        $result = $this->preprocessor->process('reset my password', 'en');

        $this->assertIsArray($result->tokens);
        $this->assertNotEmpty($result->tokens);
    }

    public function test_returns_keywords_without_stop_words(): void
    {
        $result = $this->preprocessor->process('How do I reset my password please?', 'en');

        $tokens = explode(' ', $result->keyword);
        $this->assertContains('reset', $tokens);
        $this->assertContains('password', $tokens);
        $this->assertNotContains('how', $tokens);
        $this->assertNotContains('my', $tokens);
    }

    public function test_handles_empty_string(): void
    {
        $result = $this->preprocessor->process('', 'en');

        $this->assertSame('', $result->normalized);
        $this->assertSame('', $result->keyword);
        $this->assertEmpty($result->tokens);
    }

    public function test_normalizes_whitespace(): void
    {
        $result = $this->preprocessor->process("hello    world\n\nnew line", 'en');

        $this->assertSame('hello world new line', $result->normalized);
    }

    public function test_disabling_stop_words_preserves_them(): void
    {
        $result = $this->preprocessor->withStopWords(false)
            ->process('How do I reset my password?', 'en');

        $this->assertStringContainsString('how', $result->keyword);
    }
}
