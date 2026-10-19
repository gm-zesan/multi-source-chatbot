<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\KnowledgeDocument;

enum IngestionPurpose: string
{
    case DATA_ANALYTICS = 'data_analytics';
    case KNOWLEDGE_BASE = 'knowledge_base';

    /**
     * Resolve purpose from an explicit value, document state/metadata, or file extension.
     */
    public static function resolve(?string $value, ?KnowledgeDocument $document = null, ?string $filename = null): self
    {
        if ($value !== null && trim($value) !== '') {
            $normalized = trim($value);
            if ($normalized === 'generic_analytics' || $normalized === 'data_analytics') {
                return self::DATA_ANALYTICS;
            }
            if ($normalized === 'semantic_knowledge' || $normalized === 'knowledge_base' || $normalized === 'product_catalog') {
                return self::KNOWLEDGE_BASE;
            }
            $parsed = self::tryFrom($normalized);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if ($document !== null) {
            $metaPurpose = $document->metadata['ingestion_purpose'] ?? null;
            if (is_string($metaPurpose) && trim($metaPurpose) !== '') {
                $normalized = trim($metaPurpose);
                if ($normalized === 'generic_analytics' || $normalized === 'data_analytics') {
                    return self::DATA_ANALYTICS;
                }
                if ($normalized === 'semantic_knowledge' || $normalized === 'knowledge_base' || $normalized === 'product_catalog') {
                    return self::KNOWLEDGE_BASE;
                }
                $parsed = self::tryFrom($normalized);
                if ($parsed !== null) {
                    return $parsed;
                }
            }

            if ($document->purpose instanceof self) {
                return $document->purpose;
            }
            if (is_string($document->purpose) && trim($document->purpose) !== '') {
                $normalized = trim($document->purpose);
                if ($normalized === 'generic_analytics' || $normalized === 'data_analytics') {
                    return self::DATA_ANALYTICS;
                }
                if ($normalized === 'semantic_knowledge' || $normalized === 'knowledge_base' || $normalized === 'product_catalog') {
                    return self::KNOWLEDGE_BASE;
                }
                $parsed = self::tryFrom($normalized);
                if ($parsed !== null) {
                    return $parsed;
                }
            }

            $filename = $filename ?? $document->original_filename;
        }

        if ($filename !== null && trim($filename) !== '') {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, ['csv', 'xlsx'], true)) {
                return self::DATA_ANALYTICS;
            }
        }

        return self::KNOWLEDGE_BASE;
    }

    public function label(): string
    {
        return match ($this) {
            self::DATA_ANALYTICS => 'Data & Analytics',
            self::KNOWLEDGE_BASE => 'Knowledge Base',
        };
    }
}
