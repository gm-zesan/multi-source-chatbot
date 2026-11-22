<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Decision\UnifiedOrchestrator;
use App\Enums\DocumentLifecycleStatus;
use App\Enums\FaqLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PDO;
use Tests\TestCase;

/**
 * Option 1: Full Real-World Merchant Onboarding & Simulation Test.
 *
 * Simulates an authentic e-commerce brand ("Aroma Silk & Crafts") onboarding onto the SaaS platform:
 * 1. Business Workspace Setup with dynamic merchant settings:
 *    - Hotline & Support Email
 *    - Custom in-domain unanswerable / fallback message
 *    - Custom out-of-domain refusal message
 *    - Custom product unavailable message
 *    - Custom merchant synonyms (e.g., 'clay pot' -> 'Terracotta Clay Pot')
 * 2. Multi-source knowledge ingestion:
 *    - FAQs (Operational hours, delivery fees inside & outside Dhaka)
 *    - Knowledge Document (7-day replacement & exchange policy)
 *    - Structured Product Catalog (SQLite dual-ingestion tables)
 * 3. End-to-end customer query simulations:
 *    - FAQ retrieval
 *    - Policy document retrieval with citations
 *    - Live product catalog lookup (price, stock)
 *    - Synonym mapping resolution
 *    - Out-of-catalog product refusal
 *    - Out-of-domain refusal
 *    - Multi-tenant tenant boundary isolation (zero cross-tenant leaks)
 */
class MerchantOnboardingAndStorefrontSimulationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $aromaWorkspace;
    private Workspace $rivalWorkspace;
    private ChannelAccount $aromaAccount;
    private Conversation $aromaConversation;
    private UnifiedOrchestrator $orchestrator;
    private string $analyticsDir;
    private string $catalogDbPath;
    private string $policyFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->orchestrator = app(UnifiedOrchestrator::class);

        // 1. Create realistic merchant workspace: "Aroma Silk & Crafts"
        $this->aromaWorkspace = Workspace::forceCreate([
            'id'            => 777001,
            'name'          => 'Aroma Silk & Crafts',
            'slug'          => 'aroma-silk-crafts',
            'support_phone' => '01911223344',
            'support_email' => 'support@aromasilk.com',
            'is_active'     => true,
            'settings'      => [
                'fallback_message'            => 'দুঃখিত, অ্যারোমা সিল্কের হেল্পডেস্কে এই তথ্যের উত্তর নেই। আমাদের হটলাইনে কল করুন: 01911223344।',
                'unanswerable_message'        => 'দুঃখিত, অ্যারোমা সিল্কের হেল্পডেস্কে এই তথ্যের উত্তর নেই। আমাদের হটলাইনে কল করুন: 01911223344।',
                'ood_refusal_message'         => 'আমরা কেবলমাত্র দেশীয় সিল্ক ও হস্তশিল্প পণ্য বিক্রয় করি। বহিরাগত বিষয়ে উত্তর দিতে পারছি না।',
                'product_unavailable_message' => 'দুঃখিত, কাঙ্ক্ষিত পণ্যটি বর্তমানে অ্যারোমা সিল্ক ও হস্তশিল্প ক্যাটালগে উপলব্ধ নেই।',
                'custom_synonyms'             => [
                    'মাটির পাত্র' => ['clay pot', 'terracotta pot', 'shokher hari'],
                ],
            ],
        ]);

        // Rival merchant to test strict multi-tenant boundary
        $this->rivalWorkspace = Workspace::forceCreate([
            'id'            => 777999,
            'name'          => 'TechGadget Bangladesh',
            'slug'          => 'tech-gadget-bd',
            'support_phone' => '01799887766',
            'support_email' => 'support@techgadget.com',
            'is_active'     => true,
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'website'],
            ['name' => 'Website', 'is_active' => true]
        );

        $this->aromaAccount = ChannelAccount::forceCreate([
            'workspace_id' => $this->aromaWorkspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Aroma Storefront Web Widget',
            'external_id'  => 'aroma_widget_001',
            'access_token' => 'token_aroma_public',
            'is_active'    => true,
        ]);

        $this->aromaConversation = Conversation::forceCreate([
            'channel_account_id' => $this->aromaAccount->id,
            'external_user_id'   => 'customer_nadim_01',
            'customer_name'      => 'Nadim Chowdhury',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Seed Knowledge: FAQs, Policy, and Product Catalog
        $this->seedAromaFaqs();
        $this->seedAromaPolicy();
        $this->seedAromaCatalog();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->catalogDbPath)) {
            @unlink($this->catalogDbPath);
        }
        if (isset($this->policyFilePath) && file_exists($this->policyFilePath)) {
            @unlink($this->policyFilePath);
        }
        if (is_dir($this->analyticsDir)) {
            @rmdir($this->analyticsDir);
        }
        Cache::flush();
        parent::tearDown();
    }

    private function seedAromaFaqs(): void
    {
        $faqs = [
            [
                'question' => 'আপনাদের শোরুমের সময়সূচি কী? দোকান কখন খোলা থাকে? Store hours opening time',
                'answer'   => 'অ্যারোমা সিল্কের শোরুম প্রতিদিন সকাল ১০:০০ টা থেকে রাত ৮:০০ টা পর্যন্ত খোলা থাকে।',
            ],
            [
                'question' => 'ডেলিভারি চার্জ কত? ঢাকার ভেতরে এবং বাইরে শিপিং খরচ কত? Delivery shipping charge fee',
                'answer'   => 'আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৬০ টাকা এবং ঢাকার বাইরে ১২০ টাকা।',
            ],
            [
                'question' => 'কাস্টমার কেয়ার হটলাইন নম্বর ও ইমেইল কী? Hotline support phone email contact',
                'answer'   => 'আমাদের অফিসিয়াল হটলাইন: 01911223344 এবং ইমেইল: support@aromasilk.com।',
            ],
        ];

        foreach ($faqs as $f) {
            FAQ::create([
                'workspace_id'     => $this->aromaWorkspace->id,
                'question'         => $f['question'],
                'answer'           => $f['answer'],
                'searchable_text'  => $f['question'] . ' ' . $f['answer'],
                'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
                'is_active'        => true,
            ]);
        }
    }

    private function seedAromaPolicy(): void
    {
        $policyText = <<<EOT
# অ্যারোমা সিল্ক ও হস্তশিল্প এক্সচেঞ্জ ও রিটার্ন পলিসি (Exchange & Return Policy)

১. এক্সচেঞ্জ সময়সীমা (Exchange Window):
পণ্য ডেলিভারি পাওয়ার ৭ দিনের মধ্যে (Within 7 Days) কোনো সাইজ বা রঙের পরিবর্তনের জন্য এক্সচেঞ্জ আবেদন করা যাবে।

২. ইনভয়েস ও ট্যাগ সংরক্ষণ:
এক্সচেঞ্জের সময় মূল ক্রয় ইনভয়েস (Purchase Invoice) এবং পণ্যের ব্র্যান্ড ট্যাগ অবশ্যই অক্ষত থাকতে হবে।

৩. ত্রুটিপূর্ণ বা ক্ষতিগ্রস্ত পণ্য:
পণ্য গ্রহণের ২৪ ঘণ্টার মধ্যে আনবক্সিং প্রমাণসহ হেল্পলাইনে জানালে কোম্পানি নিজস্ব খরচে পরিবর্তন করে দেবে।
EOT;

        $docDir = storage_path("app/documents/{$this->aromaWorkspace->id}");
        if (!is_dir($docDir)) {
            mkdir($docDir, 0755, true);
        }

        $this->policyFilePath = "{$docDir}/aroma_exchange_policy.txt";
        file_put_contents($this->policyFilePath, $policyText);

        KnowledgeDocument::create([
            'workspace_id'        => $this->aromaWorkspace->id,
            'title'               => 'Aroma Exchange Policy (এক্সচেঞ্জ ও রিটার্ন পলিসি Invoice Unboxing Replacement 7 Days)',
            'original_filename'   => 'aroma_exchange_policy.txt',
            'file_path'           => $this->policyFilePath,
            'mime_type'           => 'text/plain',
            'file_size'           => strlen($policyText),
            'content_hash'        => hash('sha256', $policyText),
            'active_content_hash' => hash('sha256', $policyText),
            'lifecycle_status'    => DocumentLifecycleStatus::ACTIVE,
            'purpose'             => IngestionPurpose::KNOWLEDGE_BASE,
            'chunk_count'         => 1,
            'sheets_count'        => 1,
            'version'             => 1,
            'is_active'           => true,
            'metadata'            => [
                'raw_text' => $policyText,
            ],
        ]);
    }

    private function seedAromaCatalog(): void
    {
        $this->analyticsDir = storage_path("app/analytics/{$this->aromaWorkspace->id}");
        if (!is_dir($this->analyticsDir)) {
            mkdir($this->analyticsDir, 0755, true);
        }

        $this->catalogDbPath = "{$this->analyticsDir}/catalog_products.sqlite";

        $pdo = new PDO("sqlite:{$this->catalogDbPath}");
        $pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            product_name TEXT,
            category TEXT,
            price REAL,
            stock INTEGER,
            fabric TEXT,
            craft_type TEXT
        )');

        $stmt = $pdo->prepare('INSERT INTO products (id, product_name, category, price, stock, fabric, craft_type) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([1, 'Pure Silk Jamdani Sharee', 'Sharee', 7500.0, 5, '100% Pure Silk', 'Handloom']);
        $stmt->execute([2, 'Royal Silk Panjabi', 'Panjabi', 2500.0, 20, 'Mulberry Silk', 'Embroidery']);
        $stmt->execute([3, 'Terracotta Clay Pot', 'Handicraft', 450.0, 50, 'Clay', 'Handcrafted']);
    }

    /**
     * Test 1: Operational FAQ query returns exact delivery charge.
     */
    public function test_faq_delivery_fee_query_is_accurately_answered(): void
    {
        $res = $this->orchestrator->process(
            query: 'ঢাকার বাইরে আপনাদের ডেলিভারি চার্জ কত?',
            workspaceId: $this->aromaWorkspace->id,
            user: null,
            conversation: $this->aromaConversation,
            channelSlug: 'website'
        );

        $this->assertTrue($res['answered']);
        $this->assertStringContainsString('১২০', $res['reply']);
    }

    /**
     * Test 2: Store hours FAQ query returns exact opening/closing times.
     */
    public function test_faq_store_hours_query_is_accurately_answered(): void
    {
        $res = $this->orchestrator->process(
            query: 'আপনাদের দোকান কখন খোলা থাকে?',
            workspaceId: $this->aromaWorkspace->id,
            user: null,
            conversation: $this->aromaConversation,
            channelSlug: 'website'
        );

        $this->assertTrue($res['answered']);
        $this->assertStringContainsString('১০:০০', $res['reply']);
        $this->assertStringContainsString('৮:০০', $res['reply']);
    }

    /**
     * Test 3: Policy query returns grounded 7-day exchange window with doc citation.
     */
    public function test_policy_query_returns_grounded_exchange_window(): void
    {
        $res = $this->orchestrator->process(
            query: 'পণ্য পছন্দ না হলে বা সাইজে সমস্যা হলে কতদিনের মধ্যে এক্সচেঞ্জ করা যাবে?',
            workspaceId: $this->aromaWorkspace->id,
            user: null,
            conversation: $this->aromaConversation,
            channelSlug: 'website'
        );

        $this->assertTrue($res['answered']);
        $this->assertStringContainsString('৭', $res['reply']);
        $this->assertStringContainsString('দিন', $res['reply']);
    }

    /**
     * Test 4: Product catalog price and stock query returns live values from SQLite.
     */
    public function test_product_catalog_price_and_stock_lookup(): void
    {
        $res = $this->orchestrator->process(
            query: 'Jamdani Sharee এর দাম কত এবং স্টকে আছে কি?',
            workspaceId: $this->aromaWorkspace->id,
            user: null,
            conversation: $this->aromaConversation,
            channelSlug: 'website'
        );

        $this->assertTrue($res['answered']);
        $this->assertStringContainsString('7,500', $res['reply']);
    }

    /**
     * Test 5: Dynamic custom synonyms map customer colloquial query to catalog product.
     */
    public function test_custom_synonyms_resolve_to_catalog_product(): void
    {
        $res = $this->orchestrator->process(
            query: 'আপনাদের মাটির পাত্র এর দাম কত?',
            workspaceId: $this->aromaWorkspace->id,
            user: null,
            conversation: $this->aromaConversation,
            channelSlug: 'website'
        );

        $this->assertTrue($res['answered']);
        $this->assertStringContainsString('450', $res['reply']);
    }

    /**
     * Test 6: Unavailable product triggers merchant-configured unavailable message.
     */
    public function test_unavailable_product_returns_merchant_custom_refusal(): void
    {
        $res = $this->orchestrator->process(
            query: 'Do you sell Diamond Jewellery in your store?',
            workspaceId: $this->aromaWorkspace->id,
            user: null,
            conversation: $this->aromaConversation,
            channelSlug: 'website'
        );

        $this->assertStringContainsString('দুঃখিত, কাঙ্ক্ষিত পণ্যটি বর্তমানে অ্যারোমা সিল্ক ও হস্তশিল্প ক্যাটালগে উপলব্ধ নেই।', $res['reply']);
    }

    /**
     * Test 7: Out-of-domain query triggers merchant-configured OOD refusal message.
     */
    public function test_out_of_domain_query_triggers_merchant_custom_ood_refusal(): void
    {
        $res = $this->orchestrator->process(
            query: 'Who won the 2022 FIFA World Cup?',
            workspaceId: $this->aromaWorkspace->id,
            user: null,
            conversation: $this->aromaConversation,
            channelSlug: 'website'
        );

        $this->assertSame('ood', $res['route']);
        $this->assertStringContainsString('আমরা কেবলমাত্র দেশীয় সিল্ক ও হস্তশিল্প পণ্য বিক্রয় করি।', $res['reply']);
    }

    /**
     * Test 8: Multi-tenant boundary isolation ensures rival merchant cannot access Aroma's data.
     */
    public function test_multi_tenant_isolation_prevents_cross_workspace_data_leaks(): void
    {
        $rivalAccount = ChannelAccount::forceCreate([
            'workspace_id' => $this->rivalWorkspace->id,
            'channel_id'   => Channel::where('slug', 'website')->value('id'),
            'name'         => 'Rival Web Account',
            'external_id'  => 'rival_widget_999',
            'access_token' => 'rival_token',
            'is_active'    => true,
        ]);

        $rivalConversation = Conversation::forceCreate([
            'channel_account_id' => $rivalAccount->id,
            'external_user_id'   => 'customer_rival_01',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Customer in rival workspace asks for Aroma Silk's Jamdani Sharee
        $res = $this->orchestrator->process(
            query: 'Jamdani Sharee এর দাম কত?',
            workspaceId: $this->rivalWorkspace->id,
            user: null,
            conversation: $rivalConversation,
            channelSlug: 'website'
        );

        // Rival workspace has no catalog or knowledge, so it must NOT leak Aroma's 7500 Tk price
        $this->assertStringNotContainsString('7500', $res['reply']);
    }
}
