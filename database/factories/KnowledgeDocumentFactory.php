<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentLifecycleStatus;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    public function definition(): array
    {
        $filename = $this->faker->slug(2) . '.pdf';
        $content = $this->faker->paragraphs(3, true);

        return [
            'id'                => (string) Str::uuid(),
            'workspace_id'      => Workspace::factory(),
            'title'             => $this->faker->sentence(3),
            'original_filename' => $filename,
            'file_path'         => 'documents/1/' . Str::uuid() . '.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => strlen($content),
            'content_hash'        => hash('sha256', $content),
            'active_content_hash' => hash('sha256', $content),
            'lifecycle_status'    => DocumentLifecycleStatus::PENDING_INGESTION,
            'chunk_count'       => 0,
            'version'           => 1,
            'is_active'         => false,
            'error_message'     => null,
            'created_by'        => User::factory(),
            'updated_by'        => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn () => [
            'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
            'is_active'        => true,
            'chunk_count'      => 5,
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'lifecycle_status' => DocumentLifecycleStatus::FAILED,
            'error_message'    => 'Failed to extract text from document',
        ]);
    }
}
