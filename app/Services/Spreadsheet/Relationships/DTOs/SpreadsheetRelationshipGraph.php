<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet\Relationships\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Spreadsheet Relationship Graph DTO.
 *
 * Implements the shared versioned graph representation (Contract 2.3.0) capturing
 * multi-sheet nodes, candidate primary keys, and directed relationship edges.
 */
class SpreadsheetRelationshipGraph implements Arrayable, JsonSerializable
{
    public const GRAPH_VERSION = '2.3.0';

    /**
     * @param int $workspaceId
     * @param string $documentId
     * @param string|null $jobId
     * @param string $generationId
     * @param array<string, array{sheet_name: string, row_count: int, primary_key: ?array<int, string>, candidate_keys: array, entity_type: string}> $nodes
     * @param array<string, DiscoveredRelationshipEdge> $edges
     * @param array<int, string> $ambiguities
     * @param string $graphVersion
     * @param string $discoveredAt
     */
    public function __construct(
        public readonly int $workspaceId,
        public readonly string $documentId,
        public readonly ?string $jobId,
        public readonly string $generationId,
        public readonly array $nodes = [],
        public readonly array $edges = [],
        public readonly array $ambiguities = [],
        public readonly string $graphVersion = self::GRAPH_VERSION,
        public readonly string $discoveredAt = '',
    ) {}

    /**
     * Get active (confirmed or high-confidence auto-eligible) edges.
     *
     * @return array<string, DiscoveredRelationshipEdge>
     */
    public function getActiveEdges(): array
    {
        return array_filter($this->edges, function (DiscoveredRelationshipEdge $edge) {
            return $edge->state === DiscoveredRelationshipEdge::STATE_CONFIRMED
                || ($edge->state === DiscoveredRelationshipEdge::STATE_DISCOVERED && $edge->confidence >= 0.85 && $edge->inclusionRatio >= 0.85);
        });
    }

    /**
     * Get edges requiring owner review.
     *
     * @return array<string, DiscoveredRelationshipEdge>
     */
    public function getPendingEdges(): array
    {
        return array_filter($this->edges, function (DiscoveredRelationshipEdge $edge) {
            return $edge->state === DiscoveredRelationshipEdge::STATE_NEEDS_CONFIRMATION;
        });
    }

    /**
     * Instantiate from array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawEdges = (array) ($data['edges'] ?? []);
        $edges = [];
        foreach ($rawEdges as $k => $edgeData) {
            if ($edgeData instanceof DiscoveredRelationshipEdge) {
                $edges[$edgeData->id] = $edgeData;
            } elseif (is_array($edgeData)) {
                $edge = DiscoveredRelationshipEdge::fromArray($edgeData);
                $edges[$edge->id] = $edge;
            }
        }

        return new self(
            workspaceId: (int) ($data['workspace_id'] ?? 0),
            documentId: (string) ($data['document_id'] ?? ''),
            jobId: isset($data['job_id']) ? (string) $data['job_id'] : null,
            generationId: (string) ($data['generation_id'] ?? ''),
            nodes: (array) ($data['nodes'] ?? []),
            edges: $edges,
            ambiguities: (array) ($data['ambiguities'] ?? []),
            graphVersion: (string) ($data['graph_version'] ?? self::GRAPH_VERSION),
            discoveredAt: (string) ($data['discovered_at'] ?? now()->toISOString()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $edgeArrays = [];
        foreach ($this->edges as $id => $edge) {
            $edgeArrays[$id] = $edge instanceof DiscoveredRelationshipEdge ? $edge->toArray() : (array) $edge;
        }

        return [
            'graph_version' => $this->graphVersion,
            'workspace_id'  => $this->workspaceId,
            'document_id'   => $this->documentId,
            'job_id'        => $this->jobId,
            'generation_id' => $this->generationId,
            'nodes'         => empty($this->nodes) ? (object) [] : $this->nodes,
            'edges'         => empty($edgeArrays) ? (object) [] : $edgeArrays,
            'ambiguities'   => $this->ambiguities,
            'discovered_at' => $this->discoveredAt ?: now()->toISOString(),
        ];
    }

    /**
     * Convert to Python TableSemanticProfile compatible candidate_relationships array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toPythonCandidateRelationships(): array
    {
        $list = [];
        foreach ($this->edges as $edge) {
            $list[] = [
                'source_sheet'     => $edge->sourceSheet,
                'source_column'    => implode(',', $edge->sourceColumnIds),
                'target_sheet'     => $edge->targetSheet,
                'target_column'    => implode(',', $edge->targetColumnIds),
                'cardinality'      => $edge->cardinality,
                'confidence'       => $edge->confidence,
                'status'           => strtolower($edge->state),
                'contract_version' => $this->graphVersion,
            ];
        }
        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

