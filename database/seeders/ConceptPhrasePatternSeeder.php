<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ConceptPhrasePattern;
use Illuminate\Database\Seeder;

/**
 * Seeds concept_phrase_patterns from:
 *   - canonical_mapper.py CONCEPT_PATTERNS (14 concepts)
 *   - retrieval_engine.py MULTI_ENTITY_CUES (folded as MULTI_ENTITY_DETECTION concept)
 *
 * Each concept gets:
 *   - 1 CONCEPT_META row (target_doc_type, phrase=NULL)
 *   - N POSITIVE rows (phrases)
 *   - M NEGATIVE_GUARD rows (blocking phrases)
 *
 * All entries are GLOBAL (workspace_id=0) and ACTIVE.
 */
class ConceptPhrasePatternSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Structure: concept_key → [target_doc_type, positive_phrases[], negative_guards[]]
        $concepts = [

            'DELIVERY_TIMELINE' => [
                'target_doc_type' => 'delivery_policy',
                'positives' => [
                    'koto din lagbe', 'delivery time', 'delivery r somoy', 'kobe pabo', 'kobe aasbe',
                    'কত দিন লাগবে', 'ডেলিভারি সময়', 'কবে পাবো', 'কবে আসবে', 'ডেলিভারি হতে কতদিন',
                    'কতদিনে পাবো', 'delivery hobe kobe',
                ],
                'negative_guards' => [],
            ],

            'DELIVERY_TRACKING' => [
                'target_doc_type' => 'delivery_policy',
                'positives' => [
                    'parcel track', 'courier tracking', 'track my order', 'tracking code', 'tracking number',
                    'পার্সেল ট্র্যাক', 'কুরিয়ার ট্র্যাকিং', 'ট্র্যাকিং কোড', 'ট্র্যাক করব', 'অর্ডার ট্র্যাক',
                    'sms tracking', 'delivery status',
                ],
                'negative_guards' => [],
            ],

            'DELIVERY_CHARGES' => [
                'target_doc_type' => 'delivery_policy',
                'positives' => [
                    'delivery charge', 'shipping fee', 'shipping rate', 'courier charge', 'delivry cost',
                    'ডেলিভারি চার্জ', 'ডেলিভারি খরচ', 'ডেলিভারি ফি', 'ডেলিভারির ফি', 'শিপিং চার্জ',
                    'কুরিয়ার চার্জ', 'same day delivery', 'সেইম ডে ডেলিভারি',
                ],
                'negative_guards' => [],
            ],

            'RETURN_POLICY' => [
                'target_doc_type' => 'return_policy',
                'positives' => [
                    'return policy', 'return korbo', 'ferot debo', 'ferot pathabo', 'jama ferot',
                    'product ferot', 'return korar niyom', 'return timeframe', 'can i return',
                    'রিটার্ন পলিসি', 'পণ্য ফেরত', 'জামা ফেরত', 'ফেরত দেওয়া', 'ফেরত দিতে',
                    'নন-রিটার্নেবল', 'রিটার্ন নীতি', 'রিটার্ন', 'return product', 'retrn',
                ],
                'negative_guards' => [],
            ],

            'REFUND_POLICY' => [
                'target_doc_type' => 'return_policy',
                'positives' => [
                    'refund policy', 'taka ferot', 'taka refund', 'refund pete koto din', 'money back',
                    'payment reversal', 'rfnd', 'reversal', 'refund korbe kobe',
                    'রিফান্ড', 'টাকা ফেরত', 'রিফান্ড পলিসি', 'টাকা কবে ফেরত',
                ],
                'negative_guards' => [],
            ],

            'EXCHANGE_POLICY' => [
                'target_doc_type' => 'exchange_policy',
                'positives' => [
                    'size na mille', 'size change', 'exchange kora jabe', 'exchange policy',
                    'size swap', 'choto hoise', 'boro hoise', 'color change', 'item exchange',
                    'change kora jabe', 'size boro hole', 'size choto hole', 'swap',
                    'সাইজ বদলানো', 'সাইজ না মিললে', 'এক্সচেঞ্জ পলিসি', 'এক্সচেঞ্জ করা যাবে',
                    'বদলানো যাবে', 'অন্য কালার নিতে চাই', 'সাইজ ছোট হয়েছে', 'সাইজ বড় হয়েছে',
                    'এক্সচেঞ্জ', 'l size er poriborte',
                ],
                'negative_guards' => [],
            ],

            'PAYMENT_METHOD' => [
                'target_doc_type' => 'payment_policy',
                'positives' => [
                    'payment kora jabe', 'bkash payment', 'nagad payment', 'card payment',
                    'cod available', 'cash on delivery ache', 'bKash diye payment',
                    'nagad diye payment', 'card diye payment', 'kivabe payment korbo',
                    'payment methods', 'payment er niyom', 'bikash', 'bks',
                    'পেমেন্ট মাধ্যম', 'বিকাশে পেমেন্ট', 'নগদে পেমেন্ট', 'ক্যাশ অন ডেলিভারি',
                    'কার্ডে পেমেন্ট', 'পেমেন্ট নেওয়া হয়', 'পেমেন্ট করার নিয়ম', 'পেমেনট নেওয়া',
                    'বিকাশ পেমেন্ট', 'পেমেন্ট গ্রহণ',
                ],
                'negative_guards' => ['problem', 'issue', 'failed', 'atkese', 'somossha', 'ভুল'],
            ],

            'WARRANTY_POLICY' => [
                'target_doc_type' => 'warranty_policy',
                'positives' => [
                    'warranty ache kina', 'guarantee koto din', 'warranty koto din',
                    'selai khule gele', 'selai chute', 'defect claim', 'warranty policy',
                    'service warranty', 'product warranty', 'button vanga', 'botam vanga',
                    'button venge gese', 'claim korte ki invoice lagbe', 'venge gele',
                    'ওয়ারেন্টি কত দিন', 'গ্যারান্টি কত দিন', 'সেলাই ছুটে যাওয়া', 'বোতাম ভাঙা',
                    'ওয়ারেন্টি পলিসি', 'সার্ভিসিং ফ্রিতে পাব', 'ডিসপ্লে নষ্ট',
                    'সার্ভিস চার্জ দিতে হবে', 'সার্ভিস চার্জ', 'সার্ভিসিং ফ্রিতে',
                    'ওয়ারেন্টি', 'গ্যারান্টি', 'ক্লেইম', 'claim',
                ],
                'negative_guards' => [],
            ],

            'CANCELLATION_POLICY' => [
                'target_doc_type' => 'cancellation_policy',
                'positives' => [
                    'order cancel korbo', 'cancel kora jabe', 'cancel kora jay', 'cancel policy', 'order batil',
                    'cancel korbo kivabe', 'order cancellation', 'cancel before shipping', 'dispatch hole ki cancel',
                    'অর্ডার বাতিল', 'বাতিল করার নিয়ম', 'ক্যানসেল পলিসি', 'ক্যানসেল করা যাবে',
                    'অর্ডার ক্যান্সেল', 'order cancel', 'ক্যানসেল', 'বাতিল',
                ],
                'negative_guards' => [],
            ],

            'PRIVACY_POLICY' => [
                'target_doc_type' => 'privacy_policy',
                'positives' => [
                    'data secured', 'data security', 'third party k deya', 'third party',
                    'secured thakbe apnader kache', 'phone number ki third party',
                    'gdpr', 'privacy regulation', 'data encrypted',
                    'তথ্য সুরক্ষা', 'থার্ড পার্টি', 'ডাটা সুরক্ষা', 'ডেটা এনক্রিপশন',
                    'এনক্রিপ্ট', 'এনক্রিপশন',
                ],
                'negative_guards' => [],
            ],

            'SOCIAL_MEDIA_POLICY' => [
                'target_doc_type' => 'social_media_policy',
                'positives' => [
                    'messenger e ki bkash pin', 'bkash pin ba otp', 'otp chaowa hoy',
                    'মেসেঞ্জারে কি পিন', 'পিন বা পাসওয়ার্ড',
                ],
                'negative_guards' => [],
            ],

            'CONTACT_SUPPORT' => [
                'target_doc_type' => 'contact',
                'positives' => [
                    'official whatsapp', 'whatsapp number', 'official helpline',
                    'customer care number', 'customer support',
                    'কাস্টমার কেয়ার হেল্পলাইন', 'হোয়াটসঅ্যাপ নম্বর',
                    'কার সাথে যোগাযোগ করব', 'কাস্টমার কেয়ার', 'helpline',
                ],
                'negative_guards' => [],
            ],

            'TERMS_POLICY' => [
                'target_doc_type' => 'terms',
                'positives' => [
                    'price ki notice chara', 'price change hote pare', 'dam change',
                    'terms and conditions',
                    'শর্তাবলী', 'পরিবর্তিত হতে পারে', 'মূল্য কি যেকোনো সময়',
                ],
                'negative_guards' => [],
            ],

            // MULTI_ENTITY_DETECTION — no target_doc_type; drives B2 boost only
            'MULTI_ENTITY_DETECTION' => [
                'target_doc_type' => null,
                'positives' => [
                    'both', 'together', 'simultaneously', 'multiple channels',
                    'একই সাথে', 'একাধিক', 'একসাথে', 'ekshathe', 'ekoi shathe', 'duto eksathe',
                ],
                'negative_guards' => [],
            ],
        ];

        $rows = [];

        foreach ($concepts as $conceptKey => $data) {
            // CONCEPT_META row
            $rows[] = [
                'workspace_id'    => 0,
                'concept_key'     => $conceptKey,
                'pattern_type'    => 'CONCEPT_META',
                'phrase'          => null,
                'target_doc_type' => $data['target_doc_type'],
                'status'          => 'ACTIVE',
                'version'         => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            // POSITIVE rows
            foreach ($data['positives'] as $phrase) {
                $rows[] = [
                    'workspace_id'    => 0,
                    'concept_key'     => $conceptKey,
                    'pattern_type'    => 'POSITIVE',
                    'phrase'          => $phrase,
                    'target_doc_type' => null,
                    'status'          => 'ACTIVE',
                    'version'         => 1,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            // NEGATIVE_GUARD rows
            foreach ($data['negative_guards'] as $guard) {
                $rows[] = [
                    'workspace_id'    => 0,
                    'concept_key'     => $conceptKey,
                    'pattern_type'    => 'NEGATIVE_GUARD',
                    'phrase'          => $guard,
                    'target_doc_type' => null,
                    'status'          => 'ACTIVE',
                    'version'         => 1,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            ConceptPhrasePattern::insertOrIgnore($chunk);
        }

        $total = ConceptPhrasePattern::count();
        $meta  = ConceptPhrasePattern::where('pattern_type', 'CONCEPT_META')->count();
        $pos   = ConceptPhrasePattern::where('pattern_type', 'POSITIVE')->count();
        $neg   = ConceptPhrasePattern::where('pattern_type', 'NEGATIVE_GUARD')->count();
        $this->command->info("[ConceptPhrasePatternSeeder] Seeded {$total} rows: {$meta} META, {$pos} POSITIVE, {$neg} NEGATIVE_GUARD.");
    }
}
