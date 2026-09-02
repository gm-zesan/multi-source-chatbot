<?php

declare(strict_types=1);

namespace App\Enums;

enum FaqLifecycleStatus: string
{
    case DRAFT = 'draft';
    case VALIDATING = 'validating';
    case SYNCING = 'syncing';
    case ACTIVE = 'active';
    case VALIDATION_FAILED = 'validation_failed';
    case SYNC_FAILED = 'sync_failed';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT             => 'Draft',
            self::VALIDATING        => 'Validating Lexicon',
            self::SYNCING           => 'Syncing to Typesense',
            self::ACTIVE            => 'Active & Searchable',
            self::VALIDATION_FAILED => 'Validation Failed',
            self::SYNC_FAILED       => 'Sync Failed',
        };
    }

    /**
     * Bootstrap / custom badge CSS class.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT             => 'bg-secondary',
            self::VALIDATING        => 'bg-info text-white',
            self::SYNCING           => 'text-white', // custom background in blade: #8b5cf6
            self::ACTIVE            => 'bg-success',
            self::VALIDATION_FAILED => 'bg-danger',
            self::SYNC_FAILED       => 'bg-danger',
        };
    }

    /**
     * Remixicon class for UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT             => 'ri-draft-line',
            self::VALIDATING        => 'ri-loader-4-line ri-spin',
            self::SYNCING           => 'ri-refresh-line ri-spin',
            self::ACTIVE            => 'ri-checkbox-circle-line',
            self::VALIDATION_FAILED => 'ri-error-warning-line',
            self::SYNC_FAILED       => 'ri-close-circle-line',
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
     * Whether the FAQ failed during processing.
     */
    public function hasFailed(): bool
    {
        return in_array($this, [self::VALIDATION_FAILED, self::SYNC_FAILED], true);
    }

    /**
     * Whether the FAQ is currently being processed.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::VALIDATING, self::SYNCING], true);
    }
}
