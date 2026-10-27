<?php

declare(strict_types=1);

namespace Tests\Scratch;

use App\AI\Tools\KnowledgeRetrievalTool;
use App\Enums\IngestionPurpose;
use App\Models\KnowledgeDocument;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Services\Knowledge\KnowledgeDocumentService;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class test_ing_disconnect extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_knowledge_document_is_never_retrieved_by_knowledge_tool(): void
    {
        Storage::fake('local');

        $workspace = Workspace::create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);

        $user = \App\Models\User::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $this->actingAs($user);

        $service = app(KnowledgeDocumentService::class);

        // Upload a knowledge text document
        $uniqueDoc = $service->storeText(
            title: 'Policy on UniqueWidgetXYZ',
            textContent: 'The UniqueWidgetXYZ comes with an unconditional 5-year replacement guarantee.',
            purpose: IngestionPurpose::KNOWLEDGE_BASE,
            explicitWorkspaceId: $workspace->id
        );

        $this->assertTrue($uniqueDoc->is_active);
        $this->assertEquals('active', $uniqueDoc->lifecycle_status->value);

        // Attempt retrieval via KnowledgeRetrievalTool
        $tool = app(KnowledgeRetrievalTool::class);
        $results = $tool->execute('UniqueWidgetXYZ warranty', workspaceId: $workspace->id);

        // Assert that results are completely empty because KnowledgeRetrievalTool only queries FAQs!
        $this->assertCount(0, $results);
    }
}
