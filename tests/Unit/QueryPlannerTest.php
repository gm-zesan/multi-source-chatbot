<?php

namespace Tests\Unit;

use App\Services\QueryPlanner;
use App\Services\RegistryResolver;
use PHPUnit\Framework\TestCase;

class QueryPlannerTest extends TestCase
{
    public function test_it_resolves_connection_from_registry(): void
    {
        $resolver = $this->createMock(RegistryResolver::class);
        $resolver->expects($this->once())
            ->method('resolveTable')
            ->with('customers')
            ->willReturn([
                'source_id' => 'db_05',
                'table_name' => 'customers',
            ]);

        $planner = new QueryPlanner($resolver);

        $plan = $planner->planFromIntent([
            'table' => 'customers',
        ]);

        $this->assertSame('db_05', $plan['connection']);
        $this->assertSame('customers', $plan['table']);
        $this->assertSame(['*'], $plan['columns']);
    }

    public function test_it_resolves_aliases_to_canonical_table_names(): void
    {
        $resolver = $this->createMock(RegistryResolver::class);
        $resolver->expects($this->once())
            ->method('resolveTable')
            ->with('customer')
            ->willReturn([
                'source_id' => 'db_01',
                'table_name' => 'customers',
            ]);

        $planner = new QueryPlanner($resolver);

        $plan = $planner->planFromIntent([
            'table' => 'customer',
        ]);

        $this->assertSame('db_01', $plan['connection']);
        $this->assertSame('customers', $plan['table']);
    }
}
