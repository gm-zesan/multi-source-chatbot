<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentLifecycleStatus;
use App\Enums\IngestionPurpose;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeDocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace Alpha',
            'slug' => 'workspace-alpha',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace Beta',
            'slug' => 'workspace-beta',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->userA->assignRole(RoleEnum::ADMIN->value);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->userB->assignRole(RoleEnum::ADMIN->value);

        $this->superAdmin = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->superAdmin->assignRole(RoleEnum::SUPERADMIN->value);
    }

    public function test_authorized_user_can_preview_spreadsheet_csv(): void
    {
        $csvContent = "Product,Price,Quantity\nLaptop,1200,5\nMouse,25,50\nKeyboard,75,20";
        $filePath = "documents/{$this->workspaceA->id}/" . (string) Str::uuid() . '.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Product Inventory',
            'original_filename' => 'inventory.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $response = $this->actingAs($this->userA)
            ->getJson(route('documents.preview', ['document' => $doc->id]));

        $response->assertOk()
            ->assertJson([
                'success'      => true,
                'type'         => 'spreadsheet',
                'filename'     => 'inventory.csv',
                'extension'    => 'csv',
                'sheets'       => ['Default'],
                'active_sheet' => 'Default',
                'headers'      => ['Product', 'Price', 'Quantity'],
            ]);

        $data = $response->json();
        $this->assertCount(3, $data['rows']);
        $this->assertEquals(['Laptop', '1200', '5'], $data['rows'][0]);
    }

    public function test_authorized_user_can_preview_text_document(): void
    {
        $textContent = "This is a sample business knowledge article content.";
        $filePath = "documents/{$this->workspaceA->id}/" . (string) Str::uuid() . '.txt';
        Storage::disk('local')->put($filePath, $textContent);

        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Policy Article',
            'original_filename' => 'policy.txt',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $textContent),
            'mime_type'         => 'text/plain',
            'file_size'         => strlen($textContent),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::KNOWLEDGE_BASE,
        ]);

        $response = $this->actingAs($this->userA)
            ->getJson(route('documents.preview', ['document' => $doc->id]));

        $response->assertOk()
            ->assertJson([
                'success'  => true,
                'type'     => 'text',
                'filename' => 'policy.txt',
                'content'  => $textContent,
            ]);
    }

    public function test_authorized_user_can_stream_raw_file(): void
    {
        $fileContent = "Sample plain text policy document.";
        $filePath = "documents/{$this->workspaceA->id}/" . (string) Str::uuid() . '.txt';
        Storage::disk('local')->put($filePath, $fileContent);

        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Notes',
            'original_filename' => 'notes.txt',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $fileContent),
            'mime_type'         => 'text/plain',
            'file_size'         => strlen($fileContent),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::KNOWLEDGE_BASE,
        ]);

        $response = $this->actingAs($this->userA)
            ->get(route('documents.preview', ['document' => $doc->id, 'stream' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_cross_workspace_user_is_forbidden_from_preview(): void
    {
        $csvContent = "Secret,Value\nAPI_KEY,xyz123";
        $filePath = "documents/{$this->workspaceA->id}/" . (string) Str::uuid() . '.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Workspace A Confidential',
            'original_filename' => 'confidential.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        // User B belongs to Workspace B, document belongs to Workspace A
        $response = $this->actingAs($this->userB)
            ->getJson(route('documents.preview', ['document' => $doc->id]));

        $response->assertForbidden();

        // Also test streaming check
        $streamResponse = $this->actingAs($this->userB)
            ->get(route('documents.preview', ['document' => $doc->id, 'stream' => 1]));

        $streamResponse->assertForbidden();
    }

    public function test_unauthenticated_user_is_denied(): void
    {
        $filePath = "documents/{$this->workspaceA->id}/test.csv";
        Storage::disk('local')->put($filePath, "a,b\n1,2");

        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceA->id,
            'title'             => 'Doc',
            'original_filename' => 'test.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', "a,b\n1,2"),
            'mime_type'         => 'text/csv',
            'file_size'         => 7,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $response = $this->getJson(route('documents.preview', ['document' => $doc->id]));
        $response->assertUnauthorized();
    }

    public function test_superadmin_can_preview_documents_across_workspaces(): void
    {
        $csvContent = "Col1,Col2\nVal1,Val2";
        $filePath = "documents/{$this->workspaceB->id}/" . (string) Str::uuid() . '.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspaceB->id,
            'title'             => 'Workspace B Doc',
            'original_filename' => 'data.csv',
            'file_path'         => $filePath,
            'content_hash'      => hash('sha256', $csvContent),
            'mime_type'         => 'text/csv',
            'file_size'         => strlen($csvContent),
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'purpose'           => IngestionPurpose::DATA_ANALYTICS,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('documents.preview', ['document' => $doc->id]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'type'    => 'spreadsheet',
            ]);
    }
}
