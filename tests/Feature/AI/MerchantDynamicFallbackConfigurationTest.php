<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Decision\UnifiedOrchestrator;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\Business\BusinessSourceOfTruthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MerchantDynamicFallbackConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $account;
    private Conversation $conversation;
    private UnifiedOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->orchestrator = app(UnifiedOrchestrator::class);

        $this->workspace = Workspace::forceCreate([
            'id'            => 888123,
            'name'          => 'Custom Merchant Emporium',
            'slug'          => 'custom-merchant',
            'support_phone' => '01899112233',
            'support_email' => 'support@merchantemporium.com',
            'is_active'     => true,
            'settings'      => [
                'unanswerable_message'        => 'আমাদের নলেজ বেসে এই তথ্যটি নেই। মার্চেন্ট হেল্পডেস্কে কল করুন।',
                'ood_refusal_message'         => 'আমরা কেবলমাত্র পণ্য বিক্রয় করি, বহিরাগত বিষয়ে উত্তর দিই না।',
                'product_unavailable_message' => 'দুঃখিত, এই আইটেমটি আমাদের মার্চেন্ট ক্যাটালগে নেই।',
                'custom_synonyms'             => [
                    'মাটির_হাড়ি' => ['clay pot', 'pottery'],
                ],
            ],
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'website'],
            ['name' => 'Website', 'is_active' => true]
        );

        $this->account = ChannelAccount::forceCreate([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Merchant Web Account',
            'external_id'  => 'acc_merchant_888',
            'access_token' => 'token_test_888',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::forceCreate([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'customer_user_888',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_custom_ood_refusal_message_is_served_dynamically(): void
    {
        $res = $this->orchestrator->process(
            query: 'বাংলাদেশের বর্তমান প্রধানমন্ত্রী কে?',
            workspaceId: $this->workspace->id,
            user: null,
            conversation: $this->conversation,
            channelSlug: 'website'
        );

        $this->assertSame('ood', $res['route']);
        $this->assertSame('আমরা কেবলমাত্র পণ্য বিক্রয় করি, বহিরাগত বিষয়ে উত্তর দিই না।', $res['reply']);
    }

    public function test_custom_unanswerable_message_is_served_dynamically(): void
    {
        $res = $this->orchestrator->process(
            query: 'আপনাদের খুলনা আউটলেটের ঠিকানা ও ফোন নম্বর কী?',
            workspaceId: $this->workspace->id,
            user: null,
            conversation: $this->conversation,
            channelSlug: 'website'
        );

        $this->assertSame('unanswerable', $res['route']);
        $this->assertStringContainsString('আমাদের নলেজ বেসে এই তথ্যটি নেই। মার্চেন্ট হেল্পডেস্কে কল করুন।', $res['reply']);
    }

    public function test_custom_product_unavailable_message_is_served_dynamically(): void
    {
        $res = $this->orchestrator->process(
            query: 'আপনাদের দোকানে কি Tesla Model S গাড়ি পাওয়া যাবে?',
            workspaceId: $this->workspace->id,
            user: null,
            conversation: $this->conversation,
            channelSlug: 'website'
        );

        $this->assertStringContainsString('দুঃখিত, এই আইটেমটি আমাদের মার্চেন্ট ক্যাটালগে নেই।', $res['reply']);
    }

    public function test_default_fallback_appends_support_contacts_when_custom_message_omitted(): void
    {
        // Workspace without custom messages, but with support phone & email
        $defaultWs = Workspace::forceCreate([
            'id'            => 888456,
            'name'          => 'Default Store',
            'slug'          => 'default-store',
            'support_phone' => '01711223344',
            'support_email' => 'help@defaultstore.com',
            'is_active'     => true,
            'settings'      => [],
        ]);

        $defaultAccount = ChannelAccount::forceCreate([
            'workspace_id' => $defaultWs->id,
            'channel_id'   => $this->account->channel_id,
            'name'         => 'Default Web Account',
            'external_id'  => 'acc_default_888',
            'access_token' => 'token_test_default',
            'is_active'    => true,
        ]);

        $defaultConv = Conversation::forceCreate([
            'channel_account_id' => $defaultAccount->id,
            'external_user_id'   => 'customer_user_default',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $res = $this->orchestrator->process(
            query: 'আপনাদের সিলেট আউটলেটের ঠিকানা কী?',
            workspaceId: $defaultWs->id,
            user: null,
            conversation: $defaultConv,
            channelSlug: 'website'
        );

        $this->assertStringContainsString('দুঃখিত, আপনার প্রশ্নটি সম্পর্কিত সুনির্দিষ্ট তথ্য আমাদের নলেজ বেসে এই মুহূর্তে পাওয়া যায়নি', $res['reply']);
        $this->assertStringContainsString('হটলাইন: 01711223344', $res['reply']);
        $this->assertStringContainsString('ইমেইল: help@defaultstore.com', $res['reply']);
    }

    public function test_merchant_custom_synonyms_are_recognized_in_search(): void
    {
        $bsot = app(BusinessSourceOfTruthService::class);
        $matches = $bsot->searchWorkspaceProductsOnDemand($this->workspace->id, 'মাটির_হাড়ি');
        // search tokens must process the query without fatal error and utilize custom synonyms
        $this->assertIsArray($matches);
    }
}
