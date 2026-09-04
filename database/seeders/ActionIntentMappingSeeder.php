<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActionIntentMapping;
use Illuminate\Database\Seeder;

/**
 * Seeds action_intent_mappings from retrieval_engine.py ACTION_INTENT_MAP.
 * All entries are GLOBAL (workspace_id=0), ACTIVE, execution_enabled=FALSE.
 */
class ActionIntentMappingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Structure from ACTION_INTENT_MAP in retrieval_engine.py L322-338
        $mappings = [
            // invoice intent
            ['intent_name' => 'invoice', 'action_keyword' => 'view',    'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'download', 'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'receipt',  'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'history',  'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'see',      'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'find',     'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'purono',   'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'kothay',   'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'invoice', 'action_keyword' => 'pabo',     'target_phrase' => 'how do i view my invoices?', 'penalty_phrase' => 'how do i update my payment method?'],

            // payment_method intent
            ['intent_name' => 'payment_method', 'action_keyword' => 'update',         'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],
            ['intent_name' => 'payment_method', 'action_keyword' => 'change payment', 'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],
            ['intent_name' => 'payment_method', 'action_keyword' => 'credit card',    'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],
            ['intent_name' => 'payment_method', 'action_keyword' => 'card info',      'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],
            ['intent_name' => 'payment_method', 'action_keyword' => 'add card',       'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],
            ['intent_name' => 'payment_method', 'action_keyword' => 'পেমেন্ট মেথড',   'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],
            ['intent_name' => 'payment_method', 'action_keyword' => 'কার্ড পরিবর্তন',  'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],
            ['intent_name' => 'payment_method', 'action_keyword' => 'notun card',     'target_phrase' => 'how do i update my payment method?', 'penalty_phrase' => 'how do i view my invoices?'],

            // plan_change intent
            ['intent_name' => 'plan_change', 'action_keyword' => 'switch',             'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'upgrade',            'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'downgrade',          'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'annual',             'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'monthly to annual',  'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'প্ল্যান পরিবর্তন',   'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'আপগ্রেড',            'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'plan change',        'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
            ['intent_name' => 'plan_change', 'action_keyword' => 'change plan',        'target_phrase' => 'can i change my plan?', 'penalty_phrase' => 'how do i update my payment method?'],
        ];

        $rows = [];
        foreach ($mappings as $m) {
            $rows[] = [
                'workspace_id'     => 0,
                'intent_name'      => $m['intent_name'],
                'action_keyword'   => $m['action_keyword'],
                'target_phrase'    => $m['target_phrase'],
                'penalty_phrase'   => $m['penalty_phrase'],
                'execution_enabled' => false,
                'execution_handler' => null,
                'status'           => 'ACTIVE',
                'version'          => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            ActionIntentMapping::insertOrIgnore($chunk);
        }

        $total = ActionIntentMapping::count();
        $this->command->info("[ActionIntentMappingSeeder] Seeded {$total} action intent mappings (all execution_enabled=false).");
    }
}
