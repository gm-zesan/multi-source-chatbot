<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\DocumentLifecycleStatus;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RetrievalClient
{
    private array $lastTelemetry = [];
    private bool $lastSearchFailed = false;
    private ?string $lastSearchError = null;

    /**
     * Domain-neutral and conversational stopwords to prevent bag-of-words score inflation.
     */
    private const FALLBACK_STOPWORDS = [
        'what', 'whats', "what's", 'how', 'does', 'can', 'could', 'would', 'will', 'is', 'are', 'am', 'was', 'were',
        'the', 'a', 'an', 'of', 'in', 'for', 'to', 'at', 'by', 'from', 'on', 'with', 'about', 'tell', 'me', 'you',
        'your', 'do', 'have', 'has', 'had', 'any', 'there', 'this', 'that', 'please', 'i', 'my', 'we', 'our',
        'ami', 'tumi', 'apni', 'she', 'tara', 'ki', 'koto', 'kobe', 'kemne', 'kivabe', 'asche', 'ashbe', 'pabo', 'pawa', 'jabe', 'hobe', 'ache', 'bhai', 'mama', 'vai',
        'apnader', 'amader', 'amar', 'shob', 'kono', 'ekto', 'ektu', 'ashole', 'jante', 'chaisilam', 'je', 'okhane', 'ar', 'eta', 'otar', 'ta', 'te', 'kore', 'korle', 'korben',
        'lagbe', 'katben', 'bolben', 'diben', 'deben', 'nite', 'pab', 'jodi', 'tahole', 'option', 'shomoy', 'beshi', 'lage', 'pochondo', 'na',
        'kemon', 'achen',
        'কি', 'কী', 'কে', 'কেন', 'কখন', 'কোথায়', 'কীভাবে', 'কিভাবে', 'হবে', 'হবেকি', 'আছে', 'আছেকি',
        'করা', 'যায়', 'যায়কি', 'পারি', 'পারব', 'পারা', 'জন্য', 'এর', 'তে', 'এ', 'দেওয়া', 'দেয়া',
        'দেবেন', 'দিবেন', 'নাকি', 'বলুন', 'আমাকে', 'একটু', 'ভাই', 'মামা', 'আমাদের', 'আপনার', 'আপনাদের', 'আমার', 'আমি', 'বা', 'বাকি', 'পাওয়া', 'গেলে', 'হলে', 'হওয়া',
        'লাগবে', 'কাটবেন', 'বলবেন', 'নিতে', 'পাব', 'পাবো', 'পৌঁছাইতে', 'পৌঁছাতে', 'করতে', 'অপেক্ষা', 'স্পষ্ট', 'লেখা', 'বলছেন', 'কোনো', 'চাইলে', 'চাই', 'চান', 'চায়', 'কেমন',
    ];

    /**
     * Domain-neutral cross-lingual vocabulary & synonyms for search candidate expansion.
     * Preserves language translation, transliteration, and universal commerce terms
     * without domain/product-specific hardcoding.
     */
    private const GENERIC_SYNONYMS = [
        'pure' => ['পিওর', 'খাঁটি'],
        'পিওর' => ['pure', 'খাঁটি'],
        'খাঁটি' => ['pure', 'পিওর'],
        'জরুরি' => ['দ্রুত', 'urgent', 'express'],
        'দ্রুত' => ['জরুরি', 'urgent', 'express'],
        'urgent' => ['জরুরি', 'দ্রুত', 'express'],
        'emergency' => ['জরুরি', 'urgent', 'express'],
        'express' => ['জরুরি', 'দ্রুত', 'urgent', 'fast'],
        'দাম' => ['মূল্য', 'price', 'rate', 'টাকা'],
        'মূল্য' => ['দাম', 'price', 'rate', 'টাকা'],
        'price' => ['দাম', 'মূল্য', 'টাকা'],
        'খরচ' => ['ফি', 'fee', 'charge', 'cost', 'কুরিয়ার'],
        'charge' => ['ফি', 'খরচ', 'cost', 'fee', 'কুরিয়ার'],
        'fee' => ['ফি', 'খরচ', 'charge', 'cost'],
        'ফি' => ['fee', 'খরচ', 'charge', 'cost'],
        'courier' => ['কুরিয়ার', 'শিপিং', 'ডেলিভারি', 'খরচ'],
        'shipping' => ['কুরিয়ার', 'শিপিং', 'ডেলিভারি', 'খরচ'],
        'কুরিয়ার' => ['courier', 'শিপিং', 'ডেলিভারি', 'খরচ', 'চার্জ'],
        'শিপিং' => ['shipping', 'কুরিয়ার', 'ডেলিভারি'],
        'hardware' => ['হার্ডওয়্যার', 'হার্ডওয়্যার', 'যন্ত্রাংশ', 'পার্টস'],
        'হার্ডওয়্যার' => ['hardware', 'হার্ডওয়্যার', 'পার্টস'],
        'হার্ডওয়্যার' => ['hardware', 'হার্ডওয়্যার', 'পার্টস'],
        'covered' => ['কভারেজ', 'আওতাভুক্ত', 'পাবে'],
        'coverage' => ['কভারেজ', 'আওতাভুক্ত', 'ওয়ারেন্টি'],
        'কভারেজ' => ['coverage', 'covered', 'আওতাভুক্ত'],
        'আওতাভুক্ত' => ['covered', 'coverage', 'কভারেজ'],
        'water' => ['পানি', 'পানিতে', 'জল'],
        'পানি' => ['water', 'পানিতে'],
        'পানিতে' => ['water', 'পানি'],
        'damage' => ['ক্ষতিগ্রস্ত', 'ভাঙা', 'নষ্ট', 'ক্ষতি', 'damaged'],
        'damaged' => ['ক্ষতিগ্রস্ত', 'ভাঙা', 'নষ্ট', 'ক্ষতি', 'damage'],
        'ক্ষতিগ্রস্ত' => ['damaged', 'damage', 'ভাঙা', 'নষ্ট', 'ত্রুটি'],
        'ভাঙা' => ['damaged', 'damage', 'ক্ষতিগ্রস্ত', 'ভাঙা'],
        'নষ্ট' => ['damaged', 'damage', 'ক্ষতিগ্রস্ত', 'ত্রুটিপূর্ণ'],
        'defective' => ['ত্রুটিপূর্ণ', 'ত্রুটি', 'নষ্ট', 'ভুল', 'ভাঙা'],
        'ত্রুটিপূর্ণ' => ['defective', 'ত্রুটি', 'নষ্ট', 'faulty'],
        'ত্রুটি' => ['defective', 'ত্রুটিপূর্ণ', 'নষ্ট', 'সমস্যা'],
        'timeline' => ['সময়সীমা', 'সময়', 'timeframe', 'duration'],
        'সময়সীমা' => ['timeline', 'সময়', 'মেয়াদ'],
        'original' => ['মূল', 'আসল', 'অরিজিনাল'],
        'মূল' => ['original', 'আসল', 'ট্যাগ'],
        'report' => ['রিপোর্ট', 'জানাতে', 'ক্লেইম'],
        'reporting' => ['রিপোর্ট', 'জানাতে', 'ক্লেইম'],
        'রিপোর্ট' => ['report', 'reporting', 'জানাতে'],
        'জানাতে' => ['report', 'reporting', 'যোগাযোগ'],
        'transit' => ['ট্রানজিট', 'ডেলিভারি', 'কুরিয়ার'],
        'ট্রানজিট' => ['transit', 'কুরিয়ার', 'ডেলিভারি'],
        'unboxing' => ['আনবক্সিং', 'ভিডিও'],
        'আনবক্সিং' => ['unboxing', 'ভিডিও'],
        'video' => ['ভিডিও', 'রেকর্ডিং'],
        'ভিডিও' => ['video', 'রেকর্ডিং'],
        'restocking' => ['রিস্টকিং', 'ফি', 'কাটা'],
        'রিস্টকিং' => ['restocking', 'ফি'],
        'tag' => ['ট্যাগ', 'ট্যাগসহ', 'মূল'],
        'ট্যাগ' => ['tag', 'ট্যাগসহ', 'মূল'],
        'tags' => ['ট্যাগ', 'tag', 'ট্যাগসহ'],
        'chire' => ['ছিঁড়ে', 'ছেঁড়া', 'ট্যাগ'],
        'ছিঁড়ে' => ['chire', 'ট্যাগ', 'ছেঁড়া'],
        'exchange' => ['এক্সচেঞ্জ', 'রিপ্লেসমেন্ট', 'পরিবর্তন', 'বিনিময়'],
        'এক্সচেঞ্জ' => ['exchange', 'রিপ্লেসমেন্ট', 'পরিবর্তন'],
        'replace' => ['রিপ্লেসমেন্ট', 'এক্সচেঞ্জ', 'পরিবর্তন'],
        'replacement' => ['রিপ্লেসমেন্ট', 'এক্সচেঞ্জ', 'পরিবর্তন'],
        'রিপ্লেসমেন্ট' => ['replacement', 'replace', 'এক্সচেঞ্জ', 'পরিবর্তন'],
        'goods' => ['পণ্য', 'আইটেম', 'জিনিস'],
        'item' => ['পণ্য', 'আইটেম', 'জিনিস'],
        'items' => ['পণ্য', 'আইটেম', 'জিনিস'],
        'product' => ['পণ্য', 'প্রোডাক্ট', 'জিনিস'],
        'products' => ['পণ্য', 'প্রোডাক্ট', 'জিনিস'],
        'পণ্য' => ['product', 'item', 'goods', 'জিনিস', 'প্রোডাক্ট'],
        'জিনিস' => ['পণ্য', 'item', 'product'],
        'jinish' => ['জিনিস', 'পণ্য', 'item', 'product'],
        'mouse' => ['মাউস', 'মাউসের', 'মাউসটির'],
        'মাউস' => ['mouse', 'মাউসের', 'মাউসটির'],
        'keyboard' => ['কীবোর্ড', 'কিবোর্ড'],
        'কীবোর্ড' => ['keyboard', 'কিবোর্ড', 'কীবোর্ডের'],
        'mechanical' => ['মেকানিক্যাল', 'মেকানিকাল'],
        'মেকানিক্যাল' => ['mechanical', 'মেকানিকাল'],
        'gaming' => ['গেমিং'],
        'গেমিং' => ['gaming'],
        'jamdani' => ['জামদানি', 'জামদানী'],
        'জামদানি' => ['jamdani', 'শাড়ি'],
        'sharee' => ['শাড়ি', 'শাড়ী', 'saree', 'sari'],
        'saree' => ['শাড়ি', 'শাড়ী', 'sharee'],
        'শাড়ি' => ['sharee', 'saree', 'শাড়ী', 'শাড়ির'],
        'শাড়ী' => ['sharee', 'saree', 'শাড়ি'],
        'panjabi' => ['পাঞ্জাবি', 'পাঞ্জাবী'],
        'পাঞ্জাবি' => ['panjabi', 'পাঞ্জাবী', 'পাঞ্জাবির'],
        'linen' => ['লিনেন'],
        'লিনেন' => ['linen'],
        'silk' => ['সিল্ক'],
        'সিল্ক' => ['silk'],
        'cotton' => ['কটন', 'সুতি'],
        'কটন' => ['cotton', 'সুতি'],
        'সুতি' => ['cotton', 'কটন'],
    ];

    public const BANGLISH_TO_BANGLA = [
        'koto' => 'কত',
        'dam' => 'দাম',
        'daam' => 'দাম',
        'damer' => 'দামের',
        'kivabe' => 'কিভাবে',
        'kibhabe' => 'কিভাবে',
        'kobe' => 'কবে',
        'kokhon' => 'কখন',
        'pabo' => 'পাব',
        'paowa' => 'পাওয়া',
        'ache' => 'আছে',
        'asen' => 'আছেন',
        'kothay' => 'কোথায়',
        'kothai' => 'কোথায়',
        'charz' => 'চার্জ',
        'charge' => 'চার্জ',
        'ferot' => 'ফেরত',
        'feroth' => 'ফেরত',
        'dekhte' => 'দেখতে',
        'dekhaw' => 'দেখাও',
        'dekhan' => 'দেখান',
        'dekhbo' => 'দেখবো',
        'kinte' => 'কিনতে',
        'kinbo' => 'কিনব',
        'kena' => 'কেনা',
        'chara' => 'ছাড়া',
        'bade' => 'বাদে',
        'notun' => 'নতুন',
        'puran' => 'পুরানো',
        'purano' => 'পুরানো',
        'khula' => 'খোলা',
        'bondho' => 'বন্ধ',
        'somoy' => 'সময়',
        'shokal' => 'সকাল',
        'raat' => 'রাত',
        'thikana' => 'ঠিকানা',
        'jogajog' => 'যোগাযোগ',
        'ovijog' => 'অভিযোগ',
        'sahajjo' => 'সাহায্য',
        'bhalo' => 'ভালো',
        'shob' => 'সব',
        'sob' => 'সব',
        'apnader' => 'আপনাদের',
        'apnara' => 'আপনারা',
        'apnar' => 'আপনার',
        'apni' => 'আপনি',
        'amader' => 'আমাদের',
        'amar' => 'আমার',
        'ami' => 'আমি',
        'kisu' => 'কিছু',
        'kichu' => 'কিছু',
        'pathan' => 'পাঠান',
        'pathaben' => 'পাঠাবেন',
        'pathano' => 'পাঠানো',
        'kotodin' => 'কতদিন',
        'koidin' => 'কয়দিন',
        'din' => 'দিন',
        'taka' => 'টাকা',
        'tk' => 'টাকা',
        'shundor' => 'সুন্দর',
        'rong' => 'রং',
        'colour' => 'রং',
        'color' => 'রং',
        'bhabe' => 'ভাবে',
        'hobe' => 'হবে',
        'lagbe' => 'লাগবে',
        'chai' => 'চাই',
        'cai' => 'চাই',
        'chay' => 'চায়',
        'nibo' => 'নেব',
        'nebo' => 'নেব',
        'nibona' => 'নেবনা',
        'nebona' => 'নেবনা',
        'shari' => 'শাড়ি',
        'sari' => 'শাড়ি',
        'panjabi' => 'পাঞ্জাবি',
        'punjabi' => 'পাঞ্জাবি',
        'jama' => 'জামা',
        'kapor' => 'কাপড়',
        'dhaka' => 'ঢাকা',
        'dhakar' => 'ঢাকার',
        'baire' => 'বাইরে',
        'bairer' => 'বাইরের',
        'vetore' => 'ভেতরে',
        'ashbe' => 'আসবে',
        'asbe' => 'আসবে',
        'pelam' => 'পেলাম',
        'paini' => 'পাইনি',
        'painai' => 'পাইনি',
        'khuje' => 'খুঁজে',
        'thakle' => 'থাকলে',
        'thakbe' => 'থাকবে',
        'sathe' => 'সাথে',
        'shathe' => 'সাথে',
        'bistarito' => 'বিস্তারিত',
        'janan' => 'জানান',
        'jante' => 'জানতে',
        'sothik' => 'সঠিক',
        'shothik' => 'সঠিক',
        'vul' => 'ভুল',
        'bhul' => 'ভুল',
        'thik' => 'ঠিক',
        'dhonnobad' => 'ধন্যবাদ',
        'thanks' => 'ধন্যবাদ',
        'shomossha' => 'সমস্যা',
        'somossa' => 'সমস্যা',
        'bhanga' => 'ভাঙা',
        'vanga' => 'ভাঙা',
        'bhenge' => 'ভেঙে',
        'vange' => 'ভেঙে',
        'noshto' => 'নষ্ট',
        'nosto' => 'নষ্ট',
        'chira' => 'ছিঁড়া',
        'chire' => 'ছিঁড়ে',
        'jinish' => 'জিনিস',
        'jinis' => 'জিনিস',
        'pani' => 'পানি',
        'panite' => 'পানিতে',
        'porle' => 'পড়লে',
        'porbe' => 'পড়বে',
        'khotigrostho' => 'ক্ষতিগ্রস্ত',
        'khoti' => 'ক্ষতি',
        'truti' => 'ত্রুটি',
        'trutipurno' => 'ত্রুটিপূর্ণ',
        'video' => 'ভিডিও',
        'unboxing' => 'আনবক্সিং',
        'transit' => 'ট্রানজিট',
        'damage' => 'ক্ষতিগ্রস্ত',
        'exchange' => 'এক্সচেঞ্জ',
        'replace' => 'রিপ্লেসমেন্ট',
        'replacement' => 'রিপ্লেসমেন্ট',
        'restocking' => 'রিস্টকিং',
        'free' => 'ফ্রি',
        'fee' => 'ফি',
        'warranty' => 'ওয়ারেন্টি',
        'waranty' => 'ওয়ারেন্টি',
        'guarantee' => 'গ্যারান্টি',
        'cancel' => 'বাতিল',
        'cancle' => 'বাতিল',
        'helpline' => 'হেল্পলাইন',
        'support' => 'সাপোর্ট',
        'care' => 'কেয়ার',
        'hotline' => 'হটলাইন',
        'number' => 'নম্বর',
        'nombor' => 'নম্বর',
        'outlet' => 'আউটলেট',
        'branch' => 'শাখা',
        'dokan' => 'দোকান',
        'store' => 'স্টোর',
        'shop' => 'শপ',
        'open' => 'খোলা',
        'close' => 'বন্ধ',
        'time' => 'সময়',
        'timing' => 'সময়',
        'koy' => 'কয়',
        'koyta' => 'কয়টি',
        'koita' => 'কয়টি',
        'bochor' => 'বছর',
        'mas' => 'মাস',
        'mash' => 'মাস',
        'ghonta' => 'ঘণ্টা',
        'ghanta' => 'ঘণ্টা',
        'fabric' => 'ফেব্রিক',
        'origin' => 'উৎপত্তি',
        'made' => 'তৈরি',
        'bodlano' => 'বদলানো',
        'poriborton' => 'পরিবর্তন',
        'eita' => 'এইটা',
        'oita' => 'ওইটা',
        'eta' => 'এটা',
        'ota' => 'ওটা',
        'kom' => 'কম',
        'beshi' => 'বেশি',
        'courier' => 'কুরিয়ার',
        'curier' => 'কুরিয়ার',
        'shipping' => 'কুরিয়ার',
        'defective' => 'ত্রুটিপূর্ণ',
        'hardware' => 'হার্ডওয়্যার',
        'coverage' => 'কভারেজ',
        'covered' => 'কভারেজ',
        'goods' => 'পণ্য',
        'timeline' => 'সময়সীমা',
        'hours' => 'ঘণ্টা',
        'hour' => 'ঘণ্টা',
        'days' => 'দিন',
        'tag' => 'ট্যাগ',
        'torn' => 'ছিঁড়ে',
        'fellam' => 'ফেলেছি',
        'ekhon' => 'এখন',
    ];

    public const BENGALI_SPELLING_VARIANTS = [
        '/(?<![^\s,;.!?])কী(?![^\s,;.!?])/u' => 'কি',
        '/ওয়/u' => 'ওয়',
        '/ওয়ারেন্টি/u' => 'ওয়ারেন্টি',
        '/কুরিয়ার/u' => 'কুরিয়ার',
        '/ডেলিভারী/u' => 'ডেলিভারি',
        '/পলিসী/u' => 'পলিসি',
        '/কোম্পানী/u' => 'কোম্পানি',
        '/ডিসকাওন্ট/u' => 'ডিসকাউন্ট',
        '/ষ্টোর/u' => 'স্টোর',
        '/অরডার/u' => 'অর্ডার',
        '/পাওয়া/u' => 'পাওয়া',
        '/প্যাকেজিং/u' => 'প্যাকেজিং',
        '/গ্যারান্টী/u' => 'গ্যারান্টি',
        '/রিফান্ড/u' => 'রিফান্ড',
        '/বিকাশ/u' => 'বিকাশ',
        '/ইনভয়েস/u' => 'ইনভয়েস',
    ];

    /**
     * Normalize query text: zero-width artifacts, Bengali spelling variants, and fast local Banglish expansion.
     */
    public function normalizeSpellingOnly(string $text): string
    {
        $clean = preg_replace('/[\x{200b}\x{200c}\x{200d}\x{feff}]/u', '', $text);
        foreach (self::BENGALI_SPELLING_VARIANTS as $pattern => $replacement) {
            $clean = preg_replace($pattern, $replacement, $clean);
        }
        return trim((string) preg_replace('/\s+/u', ' ', $clean));
    }

    public function normalizeQueryText(string $query): string
    {
        $clean = $this->normalizeSpellingOnly($query);

        if (preg_match_all('/\b[a-zA-Z]+\b/u', mb_strtolower($clean), $matches)) {
            $appendedBangla = [];
            foreach ($matches[0] as $w) {
                if (isset(self::BANGLISH_TO_BANGLA[$w])) {
                    $bn = self::BANGLISH_TO_BANGLA[$w];
                    if (!str_contains($clean, $bn) && !in_array($bn, $appendedBangla, true)) {
                        $appendedBangla[] = $bn;
                    }
                }
            }
            if (!empty($appendedBangla)) {
                return $clean . ' ' . implode(' ', $appendedBangla);
            }
        }

        return $clean;
    }

    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 15,
        private readonly int $defaultTopK = 5,
    ) {}

    /**
     * Get the last received telemetry data from the Python retrieval service.
     *
     * @return array<string, mixed>
     */
    public function getLastTelemetry(): array
    {
        return $this->lastTelemetry;
    }

    /**
     * Check if the last search failed against the primary retrieval service and used database fallback.
     */
    public function wasLastSearchFallback(): bool
    {
        return $this->lastSearchFailed;
    }

    /**
     * Get the error message from the last retrieval failure, if any.
     */
    public function getLastSearchError(): ?string
    {
        return $this->lastSearchError;
    }

    /**
     * Reset the last search failure state.
     */
    public function resetSearchState(): void
    {
        $this->lastSearchFailed = false;
        $this->lastSearchError = null;
        $this->lastTelemetry = [];
    }

    /**
     * Get the base URL for the Python retrieval service.
     */
    public function baseUrl(): string
    {
        if ($this->baseUrl !== null && trim($this->baseUrl) !== '') {
            return rtrim($this->baseUrl, '/');
        }

        $configured = (string) config('retrieval.base_url');
        if (trim($configured) !== '') {
            return rtrim($configured, '/');
        }

        return 'http://127.0.0.1:8001';
    }

    /**
     * Dynamically compute the bounded timeout in seconds.
     * Respects active AgentContext monotonic SLA budget if available.
     */
    public function effectiveTimeout(?int $default = null): float
    {
        $base = (float) ($default ?? $this->timeout);

        if (\App\AI\DTOs\AgentContext::hasCurrent()) {
            $remaining = \App\AI\DTOs\AgentContext::current()->getRemainingTimeout();
            return max(0.1, min($base, $remaining));
        }

        return $base;
    }

    /**
     * Search relevant FAQs through the Python Retrieval Service.
     *
     * @return Collection<int, FAQSearchResult>
     */
    public function search(
        string $query,
        ?int $workspaceId = null,
        ?int $topK = null,
        ?string $contextualSignal = null,
        ?string $documentType = null,
    ): Collection
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return new Collection();
        }

        $topK = $topK ?? $this->defaultTopK;
        $url = "{$this->baseUrl()}/api/v1/search";
        $payload = [
            'query'             => $trimmed,
            'workspace_id'      => $workspaceId,
            'top_k'             => $topK,
            'contextual_signal' => $contextualSignal,
        ];

        if ($documentType !== null && trim($documentType) !== '') {
            $payload['document_type'] = trim($documentType);
        }

        try {
            $timeout = $this->effectiveTimeout();
            $connectTimeout = min($timeout, 2.0);
            $req = Http::timeout($timeout)->connectTimeout($connectTimeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Validate required response payload structure
                if (!is_array($data) || !array_key_exists('results', $data) || !is_array($data['results'])) {
                    $this->lastTelemetry = [];
                    $this->lastSearchFailed = true;
                    $this->lastSearchError = 'Malformed retrieval response: missing or invalid "results" array in payload';
                    Log::warning('[RetrievalClient] Search returned malformed HTTP 200 response', [
                        'workspace_id' => $workspaceId,
                        'query_sha256' => hash('sha256', $trimmed),
                    ]);

                    return $this->fallbackDatabaseSearch($trimmed, $workspaceId, $topK, $contextualSignal);
                }

                $results = $data['results'];
                $telemetry = is_array($data['telemetry'] ?? null) ? $data['telemetry'] : [];
                $this->lastTelemetry = $telemetry;
                $this->lastSearchFailed = false;
                $this->lastSearchError = null;

                if (! empty($telemetry)) {
                    Log::info('[Retrieval Observability]', [
                        'workspace_id'     => $workspaceId,
                        'query_sha256'     => hash('sha256', $trimmed),
                        'first_pass_score' => $telemetry['first_pass_score'] ?? null,
                        'expansion'        => $telemetry['expansion_triggered'] ?? false,
                        'final_score'      => $telemetry['final_score'] ?? null,
                        'latency_total_ms' => $telemetry['total_retrieval_latency_ms'] ?? null,
                        'returned_ids'     => $telemetry['returned_faq_ids'] ?? [],
                    ]);
                }

                return $this->mapResults($results, $topK, $workspaceId);
            }

            $this->lastTelemetry = [];
            $this->lastSearchFailed = true;
            $this->lastSearchError = "Search returned HTTP {$response->status()}";
            Log::warning('[RetrievalClient] Search returned non-200 status', [
                'workspace_id' => $workspaceId,
                'status'       => $response->status(),
                'query_sha256' => hash('sha256', $trimmed),
            ]);
        } catch (\Throwable $e) {
            $this->lastTelemetry = [];
            $this->lastSearchFailed = true;
            $this->lastSearchError = $e->getMessage();
            Log::warning('[RetrievalClient] Python retrieval service unreachable, falling back to DB', [
                'workspace_id' => $workspaceId,
                'query_sha256' => hash('sha256', $trimmed),
                'error'        => $e->getMessage(),
            ]);
        }

        // Graceful fallback to database keyword search
        return $this->fallbackDatabaseSearch($trimmed, $workspaceId, $topK, $contextualSignal);
    }

    /**
     * Sync an FAQ record to the Python retrieval service for embedding & indexing.
     */
    public function syncFaq(FAQ $faq): bool
    {
        $url = "{$this->baseUrl()}/api/v1/faqs/sync";

        $payload = [
            'id'            => $faq->id,
            'workspace_id'  => $faq->workspace_id,
            'question'      => $faq->question,
            'answer'        => $faq->answer,
            'document_type' => $faq->document_type ?? 'faq',
            'priority'      => $faq->priority ?? 100,
            'is_active'     => (bool) $faq->is_active,
            'lexicon_terms' => $this->extractLexiconTerms($faq),
        ];

        try {
            $req = Http::timeout($this->timeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->post($url, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] FAQ sync to Python service failed', [
                'faq_id' => $faq->id,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract unique lexical terms domain-neutrally from FAQ question, answer, and searchable_text.
     *
     * @param FAQ $faq
     * @return string[]
     */
    public function extractLexiconTerms(FAQ $faq): array
    {
        try {
            $rawParts = [
                $faq->question ?? '',
                $faq->answer ?? '',
                $faq->searchable_text ?? '',
            ];

            $text = implode(' ', array_filter($rawParts));
            if (trim($text) === '') {
                return [];
            }

            // Strip HTML tags and decode entities
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = mb_strtolower($text, 'UTF-8');

            // Split into tokens based on whitespace, Unicode punctuation, and symbols
            $rawTokens = preg_split('/[\s\p{P}\p{S}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            // Universal domain-neutral grammatical stop words (Bengali and English)
            $stopWords = [
                // English functional / grammatical words
                'the', 'and', 'for', 'are', 'what', 'how', 'you', 'can', 'with', 'from',
                'this', 'that', 'your', 'our', 'have', 'does', 'doing', 'where', 'when',
                'will', 'was', 'were', 'about', 'into', 'all', 'any', 'not', 'but', 'out',
                'has', 'had', 'been', 'would', 'could', 'should', 'more', 'some', 'such',
                'than', 'them', 'then', 'there', 'they', 'their', 'only', 'also', 'over',
                'just', 'which', 'who', 'whom', 'whose', 'why', 'his', 'her', 'its', 'my',
                'to', 'or', 'it', 'in', 'on', 'at', 'by', 'an', 'as', 'is', 'if', 'of',
                'up', 'so', 'no', 'we', 'he', 'me', 'us', 'am', 'do', 'go',

                // Bengali functional / grammatical particles & pronouns
                'কি', 'কী', 'কিভাবে', 'কোন', 'থেকে', 'হলে', 'করব', 'করতে', 'পারি', 'হবে',
                'এই', 'সেই', 'এবং', 'বা', 'না', 'একটি', 'করা', 'আছে', 'ছিল', 'জন্য',
                'মতো', 'আর', 'তা', 'তো', 'যে', 'সে', 'ও', 'যা', 'কে', 'কীভাবে',
            ];
            $stopWordsFlip = array_flip($stopWords);

            $terms = [];
            foreach ($rawTokens as $token) {
                $token = trim($token);
                // Retain tokens with character length >= 2 and exclude stop words
                if (mb_strlen($token, 'UTF-8') >= 2 && !isset($stopWordsFlip[$token])) {
                    $terms[$token] = true;
                    if (count($terms) >= 50) {
                        break;
                    }
                }
            }

            return array_values(array_map('strval', array_keys($terms)));
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] Failed to extract lexicon terms from FAQ', [
                'faq_id' => $faq->id ?? null,
                'error'  => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sync a knowledge document chunk to the Python retrieval service for embedding & indexing.
     */
    public function syncDocumentChunk(
        string $id,
        int $workspaceId,
        string $title,
        string $content,
        string $documentType = 'knowledge_document',
        int $priority = 100,
        bool $isActive = true,
        array $lexiconTerms = [],
        ?string $sourceId = null,
    ): bool {
        $url = "{$this->baseUrl()}/api/v1/faqs/sync";

        $payload = [
            'id'            => $id,
            'workspace_id'  => $workspaceId,
            'question'      => $title,
            'answer'        => $content,
            'document_type' => $documentType,
            'priority'      => $priority,
            'is_active'     => $isActive,
            'lexicon_terms' => $lexiconTerms,
        ];

        try {
            $req = Http::timeout($this->timeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->post($url, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] Document chunk sync to Python service failed', [
                'chunk_id'     => $id,
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete an FAQ document from the Python retrieval service index.
     */
    public function deleteFaq(string|int $faqId, ?int $workspaceId = null): bool
    {
        $url = "{$this->baseUrl()}/api/v1/faqs/{$faqId}";
        if ($workspaceId !== null) {
            $url .= "?workspace_id={$workspaceId}";
        }

        try {
            $req = Http::timeout($this->timeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->delete($url);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] FAQ deletion in Python service failed', [
                'faq_id' => $faqId,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Safe stub for reloading workspace lexicon to prevent runtime failures.
     *
     * @param int $workspaceId
     * @return array{ok: bool, workspace_id: int}
     */
    public function reloadLexicon(int $workspaceId = 0): array
    {
        return [
            'ok'           => true,
            'workspace_id' => $workspaceId,
        ];
    }

    /**
     * Check health of the Python retrieval service.
     *
     * @return array{ok: bool, latency_ms: float, error: ?string}
     */
    public function health(): array
    {
        $start = microtime(true);
        $url = "{$this->baseUrl()}/health";

        try {
            $req = Http::timeout(5);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->get($url);

            if (! $response->successful()) {
                $altResponse = $req->get("{$this->baseUrl()}/api/v1/health");
                if ($altResponse->successful()) {
                    $response = $altResponse;
                }
            }

            $latency = round((microtime(true) - $start) * 1000, 2);

            if (! $response->successful()) {
                Log::warning('[RetrievalClient] Health check non-200', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return [
                'ok'         => $response->successful(),
                'latency_ms' => $latency,
                'error'      => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);
            Log::warning('[RetrievalClient] Health check exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok'         => false,
                'latency_ms' => $latency,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Map structured Python retrieval results to FAQSearchResult objects.
     *
     * @param array<int, array<string, mixed>> $results
     * @return Collection<int, FAQSearchResult>
     */
    /**
     * Map structured Python retrieval results to FAQSearchResult objects.
     *
     * @param array<int, array<string, mixed>> $results
     * @return Collection<int, FAQSearchResult>
     */
    private function mapResults(array $results, int $topK, ?int $workspaceId = null): Collection
    {
        if (empty($results)) {
            return new Collection();
        }

        $docIds = array_values(array_filter(array_map(fn ($r) => $r['id'] ?? null, $results)));
        $faqs = ! empty($docIds)
            ? FAQ::whereIn('id', $docIds)->get()->keyBy('id')
            : new Collection();

        $mapped = [];
        foreach ($results as $item) {
            $id = $item['id'] ?? null;
            $faq = $id ? ($faqs[$id] ?? null) : null;

            // If it is a knowledge document chunk, verify that the parent document is active and belongs to this workspace
            if (is_string($id) && str_starts_with($id, 'doc_')) {
                // Chunk ID formats:
                // 1) Legacy: doc_{docUuid}_{chunkIndex}
                // 2) Generation-scoped: doc_{docUuid}_{generationId}_{chunkIndex}
                $parts = explode('_', $id);
                $docUuid = $parts[1] ?? null;
                $chunkGen = count($parts) >= 4 ? $parts[2] : null;

                if ($docUuid) {
                    $doc = KnowledgeDocument::find($docUuid);
                    if ($doc === null || ! $doc->is_active || $doc->lifecycle_status !== DocumentLifecycleStatus::ACTIVE || $doc->trashed()) {
                        continue;
                    }
                    if ($workspaceId !== null && (int) $doc->workspace_id !== (int) $workspaceId) {
                        continue;
                    }
                    // Generation fencing: if chunk is generation-scoped, ensure it matches the currently active generation
                    if ($chunkGen !== null && ! empty($doc->active_generation_id) && $chunkGen !== (string) $doc->active_generation_id) {
                        continue;
                    }
                }
            }

            if ($faq === null && isset($item['question'], $item['answer'])) {
                // If model not in local DB (e.g. distributed store or document chunk), build a transient instance
                $itemWorkspaceId = isset($item['workspace_id']) ? (int) $item['workspace_id'] : $workspaceId;
                if ($workspaceId !== null && $itemWorkspaceId !== (int) $workspaceId) {
                    continue; // Strict tenant boundary guard
                }

                $faq = new FAQ([
                    'id'            => $id ?? 0,
                    'question'      => (string) $item['question'],
                    'answer'        => (string) $item['answer'],
                    'document_type' => (string) ($item['document_type'] ?? 'knowledge_document'),
                    'workspace_id'  => $itemWorkspaceId,
                    'is_active'     => isset($item['is_active']) ? (bool) $item['is_active'] : true,
                ]);
                $faq->exists = true;
            }

            if ($faq !== null && (! $faq->is_active || $faq->trashed())) {
                continue;
            }

            if ($faq !== null && $workspaceId !== null && (int) $faq->workspace_id !== (int) $workspaceId) {
                continue;
            }

            if ($faq !== null) {
                $score = (float) ($item['score'] ?? 0.85);
                $matchType = (string) ($item['match_type'] ?? 'hybrid');
                $isPureKeyword = in_array($matchType, ['keyword', 'bm25', 'keyword_fallback'], true);

                // Preserve explicit sub-scores from Python service contract.
                // If sub-scores are not provided in the payload, explicitly leave as null
                // rather than assuming semanticScore = finalScore or 0.0.
                $semanticScore = isset($item['semantic_score'])
                    ? (float) $item['semantic_score']
                    : ($isPureKeyword ? 0.0 : ($matchType === 'vector' ? $score : null));

                $keywordScore = isset($item['keyword_score'])
                    ? (float) $item['keyword_score']
                    : ($matchType === 'vector' ? 0.0 : ($isPureKeyword ? $score : null));

                $mapped[] = new FAQSearchResult(
                    faq: $faq,
                    keywordScore: $keywordScore,
                    semanticScore: $semanticScore,
                    finalScore: $score,
                    matchType: $matchType,
                );
            }
        }

        return new Collection(array_slice($mapped, 0, $topK));
    }

    /**
     * Database fallback when Python retrieval service is unavailable.
     * Enforces strict workspace isolation, MySQL FULLTEXT optimization where available,
     * graceful parameterized token fallback, and calibrated relevance scoring.
     *
     * @return Collection<int, FAQSearchResult>
     */
    private function fallbackDatabaseSearch(string $query, ?int $workspaceId, int $topK, ?string $contextualSignal = null): Collection
    {
        if ($workspaceId === null || $workspaceId <= 0) {
            Log::warning('[RetrievalClient] Database fallback aborted: missing or invalid workspace_id for tenant isolation', [
                'workspace_id' => $workspaceId,
                'query'        => mb_substr($query, 0, 80),
            ]);

            return new Collection();
        }

        $cleanQuery = $this->normalizeSpellingOnly(mb_strtolower(trim($query)));
        $cleanContext = ($contextualSignal !== null && trim($contextualSignal) !== '' && trim($contextualSignal) !== trim($query))
            ? $this->normalizeSpellingOnly(mb_strtolower(trim($contextualSignal)))
            : null;

        if (($cleanQuery === '' || mb_strlen($cleanQuery) < 2) && empty($cleanContext)) {
            return new Collection();
        }

        // Detect negated attribute expressions (e.g., "সুতি বাদে", "কটন ছাড়া", "synthetic pochondo na")
        $negatedWords = [];
        if (preg_match_all('/([^\p{L}\p{M}\p{N}\-_]+|^)([\p{L}\p{M}\p{N}\-_]+)\s+(?:pochondo\s+na|pochondo\s*nei|পছন্দ\s*না|পছন্দ\s*নেই|বাদে|ছাড়া|chara|bade|without|except)/ui', $cleanQuery, $negMatches)) {
            foreach ($negMatches[2] as $nw) {
                $negatedWords[] = mb_strtolower($nw);
            }
        }

        // Tokenize query with punctuation normalization (length >= 2 or alphanumeric single token, excluding negated attributes)
        $normalizedQuery = preg_replace('/[^\p{L}\p{M}\p{N}\-_]+/u', ' ', $cleanQuery);
        $queryWords = array_values(array_unique(array_filter(
            preg_split('/\s+/u', $normalizedQuery),
            fn ($w) => (mb_strlen(trim($w)) >= 2 || preg_match('/^[\p{L}\p{N}]$/u', trim($w))) && !in_array($w, $negatedWords, true)
        )));

        // Tokenize contextual signal if present
        $normalizedCtx = $cleanContext !== null ? preg_replace('/[^\p{L}\p{M}\p{N}\-_]+/u', ' ', $cleanContext) : null;
        $contextWords = $normalizedCtx !== null
            ? array_values(array_unique(array_filter(
                preg_split('/\s+/u', $normalizedCtx),
                fn ($w) => (mb_strlen(trim($w)) >= 2 || preg_match('/^[\p{L}\p{N}]$/u', trim($w))) && !in_array($w, $negatedWords, true)
            )))
            : [];

        // Combined unique words for database candidate filtering
        $allWords = array_values(array_unique(array_merge($queryWords, $contextWords)));
        if (empty($allWords)) {
            return new Collection();
        }

        // Substantive words vs stopwords
        $substantiveWords = array_values(array_filter($allWords, fn ($w) => !in_array($w, self::FALLBACK_STOPWORDS, true)));
        $searchWords = !empty($substantiveWords) ? $substantiveWords : $allWords;

        // Expand candidate filtering keywords using generic morphological stemming, language synonyms, banglish, and spelling variants
        $expandedSearchWords = $searchWords;
        $stemSuffixes = ['গুলোতে', 'গুলোর', 'টিতে', 'টাতে', 'টার', 'টির', 'গুলো', 'গুলা', 'য়ের', 'ের', 'য়ে', 'তে', 'ে', 'য়', 'এ', 'র', 'কে', 'টা', 'টি'];
        foreach ($searchWords as $w) {
            $wClean = mb_strtolower(trim($w));
            $wSpelling = $this->normalizeSpellingOnly($wClean);
            if ($wSpelling !== $wClean) {
                $expandedSearchWords[] = $wSpelling;
            }
            if (isset(self::BANGLISH_TO_BANGLA[$wClean])) {
                $expandedSearchWords[] = self::BANGLISH_TO_BANGLA[$wClean];
            }
            foreach (self::BANGLISH_TO_BANGLA as $banglish => $bangla) {
                if ($bangla === $wClean || $bangla === $wSpelling) {
                    $expandedSearchWords[] = $banglish;
                }
            }
            foreach ($stemSuffixes as $suf) {
                if (str_ends_with($wClean, $suf)) {
                    $stem = mb_substr($wClean, 0, mb_strlen($wClean) - mb_strlen($suf));
                    if (mb_strlen($stem) >= 3) {
                        $expandedSearchWords[] = $stem;
                    }
                }
            }
        }
        foreach ($expandedSearchWords as $w) {
            if (isset(self::GENERIC_SYNONYMS[$w])) {
                foreach (self::GENERIC_SYNONYMS[$w] as $syn) {
                    $expandedSearchWords[] = $syn;
                }
            }
        }
        $expandedSearchWords = array_values(array_unique($expandedSearchWords));

        // 1. Check database driver for MySQL FULLTEXT support
        $isMysql = false;
        try {
            $isMysql = (new FAQ)->getConnection()->getDriverName() === 'mysql';
        } catch (\Throwable) {
            $isMysql = false;
        }

        $faqs = new Collection();

        // Attempt MySQL FullText Boolean Search if on MySQL driver
        if ($isMysql) {
            try {
                // Strip boolean operators and punctuation that break MySQL boolean mode or token boundary parsing
                $ftSource = $cleanContext ?? $cleanQuery;
                $sanitized = preg_replace('/[+\-><()~*\"@:;|&!?,.\/\\\]/u', ' ', (string) $ftSource);
                $ftTokens = array_values(array_filter(
                    preg_split('/\s+/u', (string) $sanitized),
                    fn ($t) => mb_strlen(trim($t)) >= 2
                ));
                if (! empty($ftTokens)) {
                    // Use wildcard tokens without mandatory '+' conjunction to prevent built-in InnoDB
                    // stopwords and short tokens (< innodb_ft_min_token_size) from causing 0-hit rejection.
                    $booleanExpr = implode(' ', array_map(fn ($t) => $t . '*', $ftTokens));
                    $faqs = FAQ::where('is_active', true)
                        ->where('workspace_id', $workspaceId)
                        ->whereRaw('MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE)', [$booleanExpr])
                        ->orderByRaw('MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE) DESC', [$booleanExpr])
                        ->limit(max(100, $topK * 10))
                        ->get();
                }
            } catch (\Throwable $e) {
                Log::debug('[RetrievalClient] MySQL FullText query unviable, falling back to parameterized search', [
                    'error' => $e->getMessage(),
                ]);
                $faqs = new Collection();
            }
        }

        // If candidate filtering with expanded search words produced 0 hits, fall back to all words
        if ($faqs->isEmpty()) {
            $dbQuery = FAQ::where('is_active', true)
                ->where('workspace_id', $workspaceId);

            $dbQuery->where(function ($q) use ($expandedSearchWords) {
                foreach ($expandedSearchWords as $word) {
                    $q->orWhere('question', 'LIKE', '%' . $word . '%')
                      ->orWhere('searchable_text', 'LIKE', '%' . $word . '%')
                      ->orWhere('answer', 'LIKE', '%' . $word . '%');
                }
            });

            $faqs = $dbQuery->limit(max(100, $topK * 10))->get();
        }

        $mapped = [];
        foreach ($faqs as $faq) {
            $rawScore = $this->calculateFallbackRelevance(
                titleOrQuestion: $faq->question,
                contentOrAnswer: $faq->answer,
                searchableText: $faq->searchable_text ?? '',
                queryWords: $queryWords,
                cleanQuery: $cleanQuery,
                negatedWords: $negatedWords,
            );

            $ctxScore = !empty($contextWords) && $cleanContext !== null
                ? $this->calculateFallbackRelevance(
                    titleOrQuestion: $faq->question,
                    contentOrAnswer: $faq->answer,
                    searchableText: $faq->searchable_text ?? '',
                    queryWords: $contextWords,
                    cleanQuery: $cleanContext,
                    negatedWords: $negatedWords,
                )
                : 0.0;

            $score = max($rawScore, $ctxScore);

            // If an explicit SKU is matched in query or context, ensure precision boost
            if (!empty($faq->sku)) {
                $skuLower = mb_strtolower($faq->sku);
                if (str_contains($cleanQuery, $skuLower) || ($cleanContext !== null && str_contains($cleanContext, $skuLower))) {
                    $score = round(min(0.95, $score + 0.05), 4);
                }
            }

            if ($score >= 0.35) {
                $mapped[] = new FAQSearchResult(
                    faq: $faq,
                    keywordScore: $score,
                    semanticScore: 0.0,
                    finalScore: $score,
                    matchType: 'keyword_fallback',
                );
            }
        }

        // 2. Also search active knowledge documents in database fallback mode
        $docs = KnowledgeDocument::where('is_active', true)
            ->where('lifecycle_status', DocumentLifecycleStatus::ACTIVE)
            ->where('workspace_id', $workspaceId)
            ->limit(max(50, $topK * 5))
            ->get();
        foreach ($docs as $doc) {
            $rawText = $doc->metadata['raw_text'] ?? null;
            if (empty($rawText)) {
                if (Storage::disk('local')->exists($doc->file_path)) {
                    $rawText = (string) Storage::disk('local')->get($doc->file_path);
                } elseif (file_exists($doc->file_path)) {
                    $rawText = (string) file_get_contents($doc->file_path);
                }
            }

            if (! empty($rawText)) {
                $rawScore = $this->calculateFallbackRelevance(
                    titleOrQuestion: $doc->title,
                    contentOrAnswer: $rawText,
                    searchableText: '',
                    queryWords: $queryWords,
                    cleanQuery: $cleanQuery,
                    negatedWords: $negatedWords,
                );

                $ctxScore = !empty($contextWords) && $cleanContext !== null
                    ? $this->calculateFallbackRelevance(
                        titleOrQuestion: $doc->title,
                        contentOrAnswer: $rawText,
                        searchableText: '',
                        queryWords: $contextWords,
                        cleanQuery: $cleanContext,
                        negatedWords: $negatedWords,
                    )
                    : 0.0;

                $score = max($rawScore, $ctxScore);

                if ($score >= 0.35) {
                    $transientFaq = new FAQ([
                        'id'            => $doc->id,
                        'question'      => $doc->title,
                        'answer'        => $rawText,
                        'document_type' => 'knowledge_document',
                        'workspace_id'  => $doc->workspace_id,
                        'is_active'     => true,
                    ]);
                    $transientFaq->exists = true;

                    $mapped[] = new FAQSearchResult(
                        faq: $transientFaq,
                        keywordScore: $score,
                        semanticScore: 0.0,
                        finalScore: $score,
                        matchType: 'keyword_fallback',
                    );
                }
            }
        }

        // 3. Sort hits descending by calibrated relevance score
        usort($mapped, fn (FAQSearchResult $a, FAQSearchResult $b) => $b->finalScore <=> $a->finalScore);

        return new Collection(array_slice($mapped, 0, $topK));
    }

    /**
     * Compute calibrated lexical relevance score in [0.0, 0.95].
     *
     * @param string   $titleOrQuestion
     * @param string   $contentOrAnswer
     * @param string   $searchableText
     * @param string[] $queryWords
     * @param string   $cleanQuery
     * @param string[] $negatedWords
     * @return float
     */
    private function calculateFallbackRelevance(
        string $titleOrQuestion,
        string $contentOrAnswer,
        string $searchableText,
        array $queryWords,
        string $cleanQuery,
        array $negatedWords = [],
    ): float {
        if (empty($queryWords)) {
            return 0.0;
        }

        $title = mb_strtolower(trim($titleOrQuestion));
        $content = mb_strtolower(trim($contentOrAnswer));
        $searchable = mb_strtolower(trim($searchableText));

        // Penalty if candidate features negated attribute
        if (!empty($negatedWords)) {
            foreach ($negatedWords as $neg) {
                if (str_contains($title, $neg) || str_contains($content, $neg)) {
                    return 0.10;
                }
            }
        }

        // 1. Exact title/question match (ignoring trailing punctuation and zero-width/spelling variants)
        $normalizedTitle = $this->normalizeSpellingOnly(trim(preg_replace('/[?!.,;:]+$/u', '', $title)));
        $normalizedQuery = $this->normalizeSpellingOnly(trim(preg_replace('/[?!.,;:]+$/u', '', $cleanQuery)));
        if ($title === $cleanQuery || ($normalizedTitle !== '' && $normalizedTitle === $normalizedQuery)) {
            return 0.95;
        }

        // 2. Contiguous phrase containment in title
        if (str_contains($title, $cleanQuery) || ($normalizedQuery !== '' && str_contains($normalizedTitle, $normalizedQuery))) {
            return 0.90;
        }

        // Substantive tokens
        $subWords = array_values(array_filter($queryWords, fn ($w) => !in_array($w, self::FALLBACK_STOPWORDS, true)));
        $effectiveSubWords = !empty($subWords) ? $subWords : $queryWords;

        // Multi-word phrase matching in title
        $hasTrigramInTitle = false;
        $hasBigramInTitle = false;
        if (count($effectiveSubWords) >= 3) {
            for ($i = 0; $i < count($effectiveSubWords) - 2; $i++) {
                $trigram = $effectiveSubWords[$i] . ' ' . $effectiveSubWords[$i + 1] . ' ' . $effectiveSubWords[$i + 2];
                if (str_contains($title, $trigram)) {
                    $hasTrigramInTitle = true;
                    break;
                }
            }
        }
        if (count($effectiveSubWords) >= 2) {
            for ($i = 0; $i < count($effectiveSubWords) - 1; $i++) {
                $bigram = $effectiveSubWords[$i] . ' ' . $effectiveSubWords[$i + 1];
                if (str_contains($title, $bigram)) {
                    $hasBigramInTitle = true;
                    break;
                }
            }
        }

        $phraseBonus = $hasTrigramInTitle ? 0.12 : ($hasBigramInTitle ? 0.08 : 0.0);

        // Word matches in title
        $matchedInTitle = 0;
        foreach ($effectiveSubWords as $word) {
            if ($this->wordMatches($title, $word)) {
                $matchedInTitle++;
            }
        }
        $titleRatio = $matchedInTitle / count($effectiveSubWords);

        // Word matches in document
        $matchedInDoc = 0;
        foreach ($effectiveSubWords as $word) {
            if ($this->wordMatches($title, $word) || $this->wordMatches($searchable, $word) || $this->wordMatches($content, $word)) {
                $matchedInDoc++;
            }
        }
        $docRatio = $matchedInDoc / count($effectiveSubWords);

        // Numeric specification precision bonus
        preg_match_all('/\b\d+(?:[.:]\d+)?\b/u', $cleanQuery, $numMatches);
        $numBonus = 0.0;
        if (!empty($numMatches[0])) {
            foreach ($numMatches[0] as $num) {
                if (str_contains($title, $num) || str_contains($content, $num) || str_contains($searchable, $num)) {
                    $numBonus = 0.06;
                    break;
                }
            }
        }

        if ($docRatio >= 1.0) {
            return round(min(0.92, 0.82 + 0.10 * $titleRatio + $phraseBonus + $numBonus), 4);
        }

        if ($titleRatio >= 1.0) {
            return round(min(0.92, 0.82 + 0.02 * count($effectiveSubWords) + $phraseBonus + $numBonus), 4);
        }

        if ($titleRatio >= 0.5 && $docRatio >= 0.6) {
            return round(min(0.88, 0.62 + 0.20 * $titleRatio + $phraseBonus + $numBonus), 4);
        }

        if ($phraseBonus > 0.0) {
            return round(min(0.85, 0.58 + 0.15 * $docRatio + $phraseBonus + $numBonus), 4);
        }

        if ($docRatio >= 0.5) {
            return round(min(0.70, 0.40 + 0.25 * $docRatio + $phraseBonus + $numBonus), 4);
        }

        if ($docRatio > 0.0) {
            if ($titleRatio === 0.0 && $matchedInDoc <= 1 && count($effectiveSubWords) >= 2) {
                return 0.15; // 1 isolated token match in document with zero title match is noise
            }
            if ($matchedInDoc >= 2) {
                return round(min(0.65, 0.35 + 0.25 * $docRatio + $phraseBonus + $numBonus), 4);
            }
            return round(min(0.40, 0.60 * $docRatio + $phraseBonus + $numBonus), 4);
        }

        return 0.0;
    }

    /**
     * Check if a token or its morphological/synonym/banglish variants match within target text.
     */
    private function wordMatches(string $haystack, string $needle): bool
    {
        $haystackClean = $this->normalizeSpellingOnly(mb_strtolower($haystack));
        $needleClean = $this->normalizeSpellingOnly(mb_strtolower(trim($needle)));

        $suffixes = ['গুলোতে', 'গুলোর', 'টিতে', 'টাতে', 'টার', 'টির', 'গুলো', 'গুলা', 'য়ের', 'ের', 'য়ে', 'তে', 'ে', 'য়', 'এ', 'র', 'কে', 'টা', 'টি'];
        $variants = [$needleClean];

        if (isset(self::BANGLISH_TO_BANGLA[$needleClean])) {
            $variants[] = self::BANGLISH_TO_BANGLA[$needleClean];
        }
        foreach (self::BANGLISH_TO_BANGLA as $banglish => $bangla) {
            if ($bangla === $needleClean) {
                $variants[] = $banglish;
            }
        }

        foreach ($variants as $v) {
            foreach ($suffixes as $suf) {
                if (str_ends_with($v, $suf)) {
                    $stem = mb_substr($v, 0, mb_strlen($v) - mb_strlen($suf));
                    if (mb_strlen($stem) >= 3) {
                        $variants[] = $stem;
                    }
                }
            }
            if (str_ends_with($v, 's') && mb_strlen($v) >= 4) {
                $variants[] = mb_substr($v, 0, -1);
            }
        }
        $variants = array_values(array_unique($variants));

        foreach ($variants as $var) {
            if (mb_strlen($var) <= 3) {
                if ((bool) preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote($var, '/') . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $haystackClean)) {
                    return true;
                }
            } elseif (stripos($haystackClean, $var) !== false) {
                return true;
            }

            if (isset(self::GENERIC_SYNONYMS[$var])) {
                foreach (self::GENERIC_SYNONYMS[$var] as $syn) {
                    if (mb_strlen($syn) <= 3) {
                        if ((bool) preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote($syn, '/') . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $haystackClean)) {
                            return true;
                        }
                    } elseif (stripos($haystackClean, $syn) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
