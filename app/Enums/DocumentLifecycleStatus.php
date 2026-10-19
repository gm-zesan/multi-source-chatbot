<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentLifecycleStatus: string
{
    case PENDING_INGESTION = 'pending_ingestion';
    case INDEXING = 'indexing';
    case REQUIRES_MAPPING = 'requires_mapping';
    case ACTIVE = 'active';
    case FAILED = 'failed';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_INGESTION => 'Pending Ingestion',
            self::INDEXING          => 'Indexing Chunks',
            self::REQUIRES_MAPPING  => 'Requires Column Mapping',
            self::ACTIVE            => 'Active & Searchable',
            self::FAILED            => 'Ingestion Failed',
        };
    }

    /**
     * Bootstrap badge CSS class.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING_INGESTION => 'bg-warning text-dark',
            self::INDEXING          => 'bg-info text-white',
            self::REQUIRES_MAPPING  => 'bg-warning text-dark',
            self::ACTIVE            => 'bg-success',
            self::FAILED            => 'bg-danger',
        };
    }

    /**
     * Remixicon class for UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING_INGESTION => 'ri-time-line',
            self::INDEXING          => 'ri-refresh-line ri-spin',
            self::REQUIRES_MAPPING  => 'ri-layout-column-line',
            self::ACTIVE            => 'ri-checkbox-circle-line',
            self::FAILED            => 'ri-close-circle-line',
        };
    }

    /**
     * Whether this status authorizes the document for customer-facing retrieval.
     */
    public function isReadyForRetrieval(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Whether the document failed during processing.
     */
    public function hasFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Whether the document is awaiting ingestion.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING_INGESTION;
    }

    /**
     * Whether the document requires column mapping confirmation before ingestion can proceed.
     */
    public function requiresMapping(): bool
    {
        return $this === self::REQUIRES_MAPPING;
    }
}
