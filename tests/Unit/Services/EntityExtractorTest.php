<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CRM\EntityExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityExtractorTest extends TestCase
{
    use RefreshDatabase;
    public function test_extract_bangla_lead_info(): void
    {
        $extractor = new EntityExtractor();
        $input = "নাম: GM Zesan, ফোন: 01711000000, ঠিকানা: ধানমন্ডি, ঢাকা";
        $result = $extractor->extract($input);

        $this->assertSame('GM Zesan', $result['person']['name']);
        $this->assertContains('01711000000', $result['contact']['phones']);
        $this->assertSame('ধানমন্ডি, ঢাকা', $result['location']['address']);
    }

    public function test_crm_service_processes_and_saves_contact(): void
    {
        $workspace = \App\Models\Workspace::create(['name' => 'Lead Org', 'slug' => 'lead-org']);
        $crmService = app(\App\Services\CRM\CRMService::class);
        $input = "নাম: GM Zesan, ফোন: 01711000000, ঠিকানা: ধানমন্ডি, ঢাকা";

        $res = $crmService->processForWorkspace($workspace->id, $input);

        $this->assertTrue($res['has_data']);
        $this->assertTrue($res['db_saved']);
        $this->assertSame('GM Zesan', $res['name']);
        $this->assertSame('ধানমন্ডি, ঢাকা', $res['address']);
        $this->assertContains('+8801711000000', $res['phones']);

        $contact = \App\Models\CRMContact::find($res['contact_id']);
        $this->assertNotNull($contact);
        $this->assertSame('GM Zesan', $contact->name);
    }
}
