<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PolicyIntentMapping;
use Illuminate\Database\Seeder;

/**
 * Seeds policy_intent_mappings from retrieval_engine.py POLICY_INTENT_MAP (L424-457).
 * All entries are GLOBAL (workspace_id=0) and ACTIVE.
 */
class PolicyIntentMappingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Direct translation of POLICY_INTENT_MAP from retrieval_engine.py
        $policies = [
            'return_policy' => [
                'target_doc_type' => 'return_policy',
                'cues' => [
                    'রিটার্ন', 'পণ্য ফেরত', 'ফেরত দিতে', 'ফেরত দেওয়া', 'নন-রিটার্নেবল',
                    'return policy', 'can i return', 'return product',
                ],
            ],
            'delivery_policy' => [
                'target_doc_type' => 'delivery_policy',
                'cues' => [
                    'ডেলিভারি চার্জ', 'ডেলিভারি সময়', 'কুরিয়ার চার্জ', 'শিপিং',
                    'সেইম ডে ডেলিভারি', 'ঢাকায় ডেলিভারি', 'সারাদেশে ডেলিভারি',
                    'পার্সেল ট্র্যাকিং', 'ট্র্যাকিং কোড', 'ডেলিভারি হতে কত দিন',
                    'delivery charge', 'shipping fee',
                ],
            ],
            'payment_policy' => [
                'target_doc_type' => 'payment_policy',
                'cues' => [
                    'পেমেন্ট', 'বিকাশ', 'নগদ', 'ক্যাশ অন ডেলিভারি',
                    'payment method', 'cod',
                ],
            ],
            'warranty_policy' => [
                'target_doc_type' => 'warranty_policy',
                'cues' => [
                    'ওয়ারেন্টি', 'গ্যারান্টি', 'সেলাই ছুটে', 'বোতাম ভাঙা',
                    'নষ্ট কাপড়', 'সার্ভিসিং', 'ইনভয়েস', 'ক্লেইম',
                    'warranty', 'guarantee',
                ],
            ],
            'privacy_policy' => [
                'target_doc_type' => 'privacy_policy',
                'cues' => [
                    'গোপনীয়তা', 'তথ্য সুরক্ষা', 'থার্ড পার্টি', 'ডাটা সুরক্ষা',
                    'privacy policy', 'third party',
                ],
            ],
            'exchange_policy' => [
                'target_doc_type' => 'exchange_policy',
                'cues' => [
                    'সাইজ বদলানো', 'কালার বদলানো', 'এক্সচেঞ্জ', 'সাইজ পরিবর্তন',
                    'exchange policy',
                ],
            ],
            'customer_support' => [
                'target_doc_type' => 'contact',
                'cues' => [
                    'কাস্টমার কেয়ার', 'হেল্পলাইন', 'খোলা থাকে', 'যোগাযোগ',
                    'customer support', 'helpline',
                ],
            ],
            'cancellation_policy' => [
                'target_doc_type' => 'cancellation_policy',
                'cues' => [
                    'অর্ডার বাতিল', 'বাতিল করার নিয়ম', 'cancel order',
                ],
            ],
        ];

        $rows = [];
        foreach ($policies as $policyName => $data) {
            foreach ($data['cues'] as $cue) {
                $rows[] = [
                    'workspace_id'   => 0,
                    'policy_name'    => $policyName,
                    'cue_phrase'     => $cue,
                    'target_doc_type'=> $data['target_doc_type'],
                    'status'         => 'ACTIVE',
                    'version'        => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            PolicyIntentMapping::insertOrIgnore($chunk);
        }

        $total = PolicyIntentMapping::count();
        $this->command->info("[PolicyIntentMappingSeeder] Seeded {$total} policy intent mappings across " . count($policies) . " policies.");
    }
}
