<?php

namespace App\Services\NLP;

use App\Services\NLP\Contracts\ProcessorInterface;
use App\Services\NLP\Processors\LowercaseProcessor;
use App\Services\NLP\Processors\NormalizeBanglaUnicodeProcessor;
use App\Services\NLP\Processors\NormalizeContractionsProcessor;
use App\Services\NLP\Processors\NormalizeWhitespaceProcessor;
use App\Services\NLP\Processors\PreservePatternsProcessor;
use App\Services\NLP\Processors\RemoveEmojisProcessor;
use App\Services\NLP\Processors\RemovePunctuationProcessor;
use App\Services\NLP\Processors\RemoveStopWordsProcessor;
use App\Services\NLP\Processors\TokenizerProcessor;
use App\Services\NLP\Processors\TrimProcessor;

class TextPreprocessor
{
    /**
     * Ordered list of processor instances.
     *
     * @var ProcessorInterface[]
     */
    private array $processors = [];

    /**
     * Whether to remove stop words during normalization.
     */
    private bool $removeStopWords = true;

    /**
     * Minimum token length to keep.
     */
    private int $minTokenLength = 1;

    /**
     * The preserve-patterns processor (kept as a reference for restoration).
     */
    private ?PreservePatternsProcessor $preserveProcessor = null;

    /**
     * Create a preprocessor with the default pipeline.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Build the default processing pipeline.
     *
     * Order matters — each step feeds into the next.
     */
    public function __construct()
    {
        $this->preserveProcessor = new PreservePatternsProcessor();

        $this->processors = [
            new TrimProcessor(),
            new LowercaseProcessor(),
            new NormalizeContractionsProcessor(),
            new NormalizeBanglaUnicodeProcessor(),
            $this->preserveProcessor,
            new RemoveEmojisProcessor(),
            new RemovePunctuationProcessor(),
            new NormalizeWhitespaceProcessor(),
        ];
    }

    /**
     * Add a custom processor to the end of the pipeline.
     */
    public function addProcessor(ProcessorInterface $processor): self
    {
        $this->processors[] = $processor;

        return $this;
    }

    /**
     * Prepend a processor to the beginning of the pipeline.
     */
    public function prependProcessor(ProcessorInterface $processor): self
    {
        array_unshift($this->processors, $processor);

        return $this;
    }

    /**
     * Remove a processor by name from the pipeline.
     */
    public function removeProcessor(string $name): self
    {
        $this->processors = array_values(array_filter(
            $this->processors,
            fn (ProcessorInterface $p) => $p->name() !== $name,
        ));

        return $this;
    }

    /**
     * Enable or disable stop word removal.
     */
    public function withStopWords(bool $enabled = true): self
    {
        $this->removeStopWords = $enabled;

        return $this;
    }

    /**
     * Set the minimum token length.
     */
    public function withMinTokenLength(int $length): self
    {
        $this->minTokenLength = $length;

        return $this;
    }

    /**
     * Run the full preprocessing pipeline on the given text.
     */
    public function process(string $text, string $language = 'auto'): ProcessedText
    {
        $original = $text;
        $language = $this->detectLanguage($text, $language);
        $normalized = $this->normalize($text, $language);

        // Tokenize
        $tokenizer = new TokenizerProcessor($this->minTokenLength);
        $tokens = $tokenizer->tokenize($normalized, $language);

        // Build keyword string — optionally remove stop words
        if ($this->removeStopWords) {
            $stopWordRemover = new RemoveStopWordsProcessor();
            $keywordTokens = $stopWordRemover->filterTokens($tokens, $language);
        } else {
            $keywordTokens = $tokens;
        }
        $keyword = $tokenizer->buildKeywordString($keywordTokens, $language);

        return new ProcessedText(
            original: $original,
            normalized: $normalized,
            tokens: $tokens,
            keyword: $keyword,
            language: $language,
        );
    }

    /**
     * Normalize the text by running the processor pipeline.
     */
    private function normalize(string $text, string $language): string
    {
        foreach ($this->processors as $processor) {
            $text = $processor->process($text, $language);
        }

        // Restore preserved patterns
        if ($this->preserveProcessor) {
            $text = $this->preserveProcessor->restore($text);
        }

        // Collapse duplicate spaces one final time
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        // Optionally remove stop words from normalized text
        if ($this->removeStopWords) {
            $stopWordRemover = new RemoveStopWordsProcessor(removeTokens: true);
            $text = $stopWordRemover->process($text, $language);
            $text = preg_replace('/\s+/u', ' ', $text);
            $text = trim($text);
        }

        return $text;
    }

    /**
     * Detect the language of the input text.
     *
     * Returns 'en', 'bn', or 'banglish'.
     */
    public function detectLanguage(string $text, string $preferred = 'auto'): string
    {
        if ($preferred !== 'auto') {
            return $preferred;
        }

        // Strip common ASCII punctuation/whitespace to check only content
        $content = preg_replace('/[[:punct:]\s]+/u', '', $text);

        if ($content === '' || $content === false) {
            return 'en';
        }

        // Count Bengali Unicode characters
        $banglaCount = preg_match_all('/[\x{0980}-\x{09FF}]/u', $content, $matches);

        // Count Latin characters
        $latinCount = preg_match_all('/[a-zA-Z]/', $content);

        if ($banglaCount > 0 && $latinCount > 0) {
            return 'banglish';
        }

        if ($banglaCount > 0) {
            return 'bn';
        }

        return 'en';
    }
}
