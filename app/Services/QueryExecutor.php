<?php

namespace App\Services;

use App\Services\QueryPlanner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class QueryExecutor
{
    protected QueryPlanner $planner;

    public function __construct(QueryPlanner $planner)
    {
        $this->planner = $planner;
    }

    public function execute(array $intent): Collection|array
    {
        if (empty($intent) || ($intent['action'] ?? null) !== 'select' || empty($intent['table'])) {
            return [];
        }

        try {
            $plan = $this->planner->planFromIntent($intent);
            $query = $this->planner->build($plan);

            return $query->get();
        } catch (InvalidArgumentException $e) {
            Log::warning('Query execution blocked by validation: ' . $e->getMessage(), ['intent' => $intent]);
            return collect([]);
        } catch (\Throwable $e) {
            Log::error('Query execution failed: ' . $e->getMessage(), ['intent' => $intent]);
            return collect([]);
        }
    }
}