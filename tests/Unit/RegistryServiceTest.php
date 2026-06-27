<?php

namespace Tests\Unit;

use App\Services\RegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new RegistryService();
    }

    /** @test */
    public function it_returns_null_for_unknown_table(): void
    {
        $result = $this->registry->getTable('nonexistent_table_xyz');
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_empty_array_for_unknown_columns(): void
    {
        $columns = $this->registry->getColumns('nonexistent_table_xyz');
        $this->assertIsArray($columns);
        $this->assertEmpty($columns);
    }

    /** @test */
    public function it_checks_table_existence(): void
    {
        $this->assertFalse($this->registry->tableExists('nonexistent_table_xyz'));
    }

    /** @test */
    public function it_returns_grouped_tables_structure(): void
    {
        $grouped = $this->registry->getAllTablesGrouped();
        $this->assertIsArray($grouped);
    }

    /** @test */
    public function it_infers_source_id_returns_null_for_unknown_table(): void
    {
        $sourceId = $this->registry->inferSourceId('nonexistent_table_xyz');
        $this->assertNull($sourceId);
    }
}
