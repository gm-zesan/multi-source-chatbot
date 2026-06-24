<?php

namespace Tests\Unit;

use App\Services\QueryExecutor;
use App\Services\QueryPlanner;
use PHPUnit\Framework\TestCase;

class QueryExecutorTest extends TestCase
{
    public function test_it_executes_a_planned_query(): void
    {
        $planner = $this->createMock(QueryPlanner::class);
        $planner->expects($this->once())
            ->method('planFromIntent')
            ->willReturn([
                'connection' => 'db_03',
                'table' => 'customers',
                'columns' => ['*'],
                'filters' => [],
                'sort' => null,
                'limit' => null,
            ]);
        $planner->expects($this->once())
            ->method('build')
            ->willReturn(new class {
                public function get(): array
                {
                    return [['id' => 1, 'name' => 'Test']];
                }
            });

        $executor = new QueryExecutor($planner);

        $result = $executor->execute([
            'action' => 'select',
            'table' => 'customers',
        ]);

        $this->assertIsArray($result);
    }
}
