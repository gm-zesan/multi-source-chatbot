<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\MaxWords;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MaxWordsRuleTest extends TestCase
{
    public function test_content_within_word_limit_passes(): void
    {
        $rule = new MaxWords(50);
        $text = str_repeat('word ', 45);

        $validator = Validator::make(['content' => $text], [
            'content' => [$rule],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_content_exceeding_word_limit_fails(): void
    {
        $rule = new MaxWords(50);
        $text = str_repeat('word ', 60);

        $validator = Validator::make(['content' => $text], [
            'content' => [$rule],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must not exceed 50 words', $validator->errors()->first('content'));
    }

    public function test_multilingual_unicode_content_is_counted_accurately(): void
    {
        $rule = new MaxWords(10);
        // Exactly 5 Bengali words
        $bengaliText = 'আমাদের পণ্য ফেরত নীতি সংক্রান্ত তথ্য';

        $validator = Validator::make(['answer' => $bengaliText], [
            'answer' => [$rule],
        ]);

        $this->assertTrue($validator->passes());

        // 15 Bengali words (exceeds limit 10)
        $longBengaliText = 'আমাদের পণ্য ফেরত নীতি সংক্রান্ত তথ্য যা গ্রাহকদের জন্য প্রযোজ্য হবে ডেলিভারি পাওয়ার সাত দিনের মধ্যে নিশ্চিত করুন';
        $failingValidator = Validator::make(['answer' => $longBengaliText], [
            'answer' => [$rule],
        ]);

        $this->assertTrue($failingValidator->fails());
        $this->assertStringContainsString('must not exceed 10 words', $failingValidator->errors()->first('answer'));
    }

    public function test_default_limit_is_250_words_and_enforces_clean_error(): void
    {
        $rule = new MaxWords();
        $text = str_repeat('word ', 260);

        $validator = Validator::make(['answer' => $text], [
            'answer' => [$rule],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must not exceed 250 words', $validator->errors()->first('answer'));
        $this->assertStringContainsString('paraphrase-multilingual-mpnet', $validator->errors()->first('answer'));
    }
}

