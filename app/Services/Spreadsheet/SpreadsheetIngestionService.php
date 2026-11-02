<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet;

use App\Enums\DocumentLifecycleStatus;
use App\Jobs\ProcessSpreadsheetImportJob;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetIngestionService
{
    public function __construct(
        private readonly SpreadsheetInspectorService $inspector,
    ) {}

    /**
     * Create an import job for a knowledge document and its designated worksheet.
     *
     * @param array<string, mixed>|null $mappingConfig
     */
    public function createImportJob(
        KnowledgeDocument $document,
        ?string $sheetName = null,
        int $headerRowIndex = 0,
        ?array $mappingConfig = null,
        string $conflictMode = 'skip',
        ?int $userId = null
    ): SpreadsheetImportJob {
        $realPath = Storage::disk('local')->path($document->file_path);
        $inspection = $this->inspector->inspectWorkbook($realPath, $document->original_filename, $sheetName);

        $detectedSchema = $inspection['inspection'] ?? [];
        $totalRows = (int) ($inspection['total_rows'] ?? 0);
        $sheet = $inspection['active_sheet'] ?? ($sheetName ?: 'Default');

        // Check if mapping is complete or requires owner confirmation
        $status = 'queued';
        if (empty($mappingConfig)) {
            // Check if automatic mapping suggestions are high confidence or ambiguous
            $hasAmbiguous = $detectedSchema['is_ambiguous'] ?? false;
            foreach ($detectedSchema['columns'] ?? [] as $col) {
                if (! empty($col['is_ambiguous']) || (float) ($col['confidence'] ?? 0.0) < 0.65) {
                    $hasAmbiguous = true;
                    break;
                }
            }
            if ($hasAmbiguous) {
                $status = 'awaiting_mapping';
            }
        }

        $generationId = (string) Str::uuid();

        return SpreadsheetImportJob::create([
            'id'                 => (string) Str::uuid(),
            'workspace_id'       => $document->workspace_id,
            'document_id'        => $document->id,
            'sheet_name'         => $sheet,
            'status'             => $status,
            'conflict_mode'      => in_array($conflictMode, ['skip', 'overwrite', 'append'], true) ? $conflictMode : 'skip',
            'generation_id'      => $generationId,
            'schema_version'     => SpreadsheetInspectorService::CONTRACT_VERSION,
            'header_row_index'   => $headerRowIndex,
            'total_rows'         => $totalRows,
            'processed_rows'     => 0,
            'staged_rows'        => 0,
            'failed_rows'        => 0,
            'checkpoint_row'     => 0,
            'detected_schema'    => $detectedSchema,
            'source_descriptors' => $detectedSchema['source_descriptors'] ?? [],
            'mapping_config'     => $mappingConfig,
            'validation_errors'  => [],
            'created_by'         => $userId,
        ]);
    }

    /**
     * Confirm column mapping for an import job and queue processing.
     *
     * @param array{
     *     header_row_index?: int,
     *     mappings?: array<string, string>,
     *     conflict_mode?: string,
     *     target_sheet?: string
     * } $data
     */
    public function confirmMapping(KnowledgeDocument $document, array $data, ?int $userId = null): SpreadsheetImportJob
    {
        $job = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        $generationId = (string) Str::uuid();
        $conflictMode = $data['conflict_mode'] ?? $job?->conflict_mode ?? 'skip';
        if (! in_array($conflictMode, ['skip', 'overwrite', 'append'], true)) {
            $conflictMode = 'skip';
        }

        $headerRowIndex = isset($data['header_row_index']) ? (int) $data['header_row_index'] : ($job?->header_row_index ?? 0);
        $mappings = $data['mappings'] ?? [];
        $sheetName = $data['target_sheet'] ?? $job?->sheet_name;

        // Resolve and merge source descriptors
        $existingDescriptors = $job?->source_descriptors ?? ($job?->detected_schema['source_descriptors'] ?? []);
        $updatedDescriptors = $existingDescriptors;

        // Process rich source_descriptors if provided
        if (! empty($data['source_descriptors']) && is_array($data['source_descriptors'])) {
            foreach ($data['source_descriptors'] as $colId => $descData) {
                if (! is_array($descData)) {
                    continue;
                }
                $existing = $updatedDescriptors[$colId] ?? [];
                $updatedDescriptors[$colId] = array_merge($existing, $descData, [
                    'source_column_id' => $colId,
                    'owner_confirmed'  => true,
                    'mapping_source'   => 'owner_confirmed',
                ]);
            }
        }

        // Process mappings if provided
        if (! empty($mappings)) {
            foreach ($mappings as $key => $val) {
                $matchedColId = null;
                if (isset($updatedDescriptors[$key])) {
                    $matchedColId = $key;
                } else {
                    foreach ($updatedDescriptors as $cId => $d) {
                        if (($d['original_header'] ?? '') === $key || ($d['normalized_header'] ?? '') === $key) {
                            $matchedColId = $cId;
                            break;
                        }
                    }
                }

                if ($matchedColId !== null) {
                    if (is_array($val)) {
                        $updatedDescriptors[$matchedColId] = array_merge($updatedDescriptors[$matchedColId], $val, [
                            'owner_confirmed' => true,
                            'mapping_source'  => 'owner_confirmed',
                        ]);
                    } elseif (is_string($val)) {
                        $isCategory = in_array($val, [
                            SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
                            SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
                            SpreadsheetInspectorService::CATEGORY_DESCRIPTION,
                            SpreadsheetInspectorService::CATEGORY_AMOUNT,
                            SpreadsheetInspectorService::CATEGORY_QUANTITY,
                            SpreadsheetInspectorService::CATEGORY_DATE_TIME,
                            SpreadsheetInspectorService::CATEGORY_STATUS,
                            SpreadsheetInspectorService::CATEGORY_CATEGORY,
                            SpreadsheetInspectorService::CATEGORY_PERSON,
                            SpreadsheetInspectorService::CATEGORY_PERSON_REFERENCE,
                            SpreadsheetInspectorService::CATEGORY_CONTACT,
                            SpreadsheetInspectorService::CATEGORY_CONTACT_INFO,
                            SpreadsheetInspectorService::CATEGORY_CUSTOM_ATTRIBUTE,
                        ], true);

                        if ($isCategory) {
                            $updatedDescriptors[$matchedColId]['suggested_category'] = $val;
                        } else {
                            $updatedDescriptors[$matchedColId]['semantic_role'] = $val;
                        }
                        $updatedDescriptors[$matchedColId]['owner_confirmed'] = true;
                        $updatedDescriptors[$matchedColId]['mapping_source'] = 'owner_confirmed';
                    }
                }
            }
        }

        if (! $job) {
            $job = $this->createImportJob(
                document: $document,
                sheetName: $sheetName,
                headerRowIndex: $headerRowIndex,
                mappingConfig: $mappings,
                conflictMode: $conflictMode,
                userId: $userId
            );
            if (! empty($updatedDescriptors)) {
                $job->update(['source_descriptors' => $updatedDescriptors]);
            }
        } else {
            // Delete previously created records for this job to prevent duplicates on remap
            StructuredRecord::where('import_job_id', $job->id)->delete();

            $job->update([
                'status'             => 'queued',
                'generation_id'      => $generationId,
                'schema_version'     => SpreadsheetInspectorService::CONTRACT_VERSION,
                'header_row_index'   => $headerRowIndex,
                'source_descriptors' => $updatedDescriptors,
                'mapping_config'     => $mappings,
                'conflict_mode'      => $conflictMode,
                'sheet_name'         => $sheetName ?? $job->sheet_name,
                'checkpoint_row'     => 0,
                'processed_rows'     => 0,
                'failed_rows'        => 0,
                'staged_rows'        => 0,
                'validation_errors'  => [],
                'error_message'      => null,
                'started_at'         => null,
                'completed_at'       => null,
            ]);
            $job->refresh();
        }

        $document->update([
            'lifecycle_status' => DocumentLifecycleStatus::INDEXING,
            'is_active'        => false,
            'error_message'    => null,
        ]);

        ProcessSpreadsheetImportJob::dispatch($job, $job->generation_id);

        return $job;
    }

    /**
     * Retry an import job from scratch with a fresh generation token.
     */
    public function retryJob(SpreadsheetImportJob $job): SpreadsheetImportJob
    {
        $newGenerationId = (string) Str::uuid();

        // Clean up previous records for this job
        StructuredRecord::where('import_job_id', $job->id)->delete();

        $job->update([
            'status'            => 'queued',
            'generation_id'     => $newGenerationId,
            'checkpoint_row'    => 0,
            'processed_rows'    => 0,
            'failed_rows'       => 0,
            'staged_rows'       => 0,
            'validation_errors' => [],
            'error_message'     => null,
            'started_at'        => null,
            'completed_at'      => null,
        ]);
        $job->refresh();

        $document = $job->document;
        if ($document) {
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::INDEXING,
                'error_message'    => null,
            ]);
        }

        ProcessSpreadsheetImportJob::dispatch($job, $job->generation_id);

        return $job;
    }

    /**
     * Cancel an active or queued import job with generation invalidation.
     */
    public function cancelJob(SpreadsheetImportJob $job): SpreadsheetImportJob
    {
        $job->update([
            'status'        => 'cancelled',
            'generation_id' => (string) Str::uuid(), // Invalidate any running workers
            'completed_at'  => now(),
        ]);
        $job->refresh();

        $document = $job->document;
        if ($document && $document->lifecycle_status === DocumentLifecycleStatus::INDEXING) {
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::FAILED,
                'error_message'    => 'Import job was cancelled by user.',
            ]);
        }

        return $job;
    }

    /**
     * Process a chunk of rows for an import job with generation fencing and checkpointing.
     *
     * @return array{processed_in_chunk: int, remaining_rows: int, is_finished: bool, status: string}
     */
    public function processChunk(SpreadsheetImportJob $job, int $chunkSize = 250): array
    {
        $job->refresh();

        // 1. Generation Fencing Guard
        if (in_array($job->status, ['cancelled', 'completed', 'failed'], true)) {
            Log::info("[SpreadsheetIngestionService] Aborting chunk processing for job {$job->id} with terminal status: {$job->status}");
            return [
                'processed_in_chunk' => 0,
                'remaining_rows'     => 0,
                'is_finished'        => true,
                'status'             => $job->status,
            ];
        }

        $document = $job->document;
        if (! $document || ! Storage::disk('local')->exists($document->file_path)) {
            $job->update([
                'status'        => 'failed',
                'error_message' => 'Document file not found on storage disk.',
            ]);
            return [
                'processed_in_chunk' => 0,
                'remaining_rows'     => 0,
                'is_finished'        => true,
                'status'             => 'failed',
            ];
        }

        if ($job->status === 'queued' || $job->status === 'awaiting_mapping') {
            $job->update([
                'status'     => 'processing',
                'started_at' => $job->started_at ?? now(),
            ]);
        }

        $absolutePath = Storage::disk('local')->path($document->file_path);
        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));

        // 2. Read Chunk based on file type
        $startRow = $job->checkpoint_row + 1; // 1-based data row offset
        $headerOffset = $job->header_row_index; // Header index in raw file

        $chunkData = $extension === 'csv'
            ? $this->readCsvChunk($absolutePath, $headerOffset, $startRow, $chunkSize)
            : $this->readExcelChunk($absolutePath, $job->sheet_name, $headerOffset, $startRow, $chunkSize);

        $headers = $chunkData['headers'];
        $rows = $chunkData['rows'];
        $isEof = $chunkData['is_eof'];

        if (empty($rows)) {
            // Finished
            $finalStatus = $job->failed_rows > 0 ? 'completed_with_errors' : 'completed';
            $job->update([
                'status'       => $finalStatus,
                'completed_at' => now(),
            ]);

            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
                'is_active'        => true,
                'chunk_count'      => $job->processed_rows,
            ]);

            return [
                'processed_in_chunk' => 0,
                'remaining_rows'     => 0,
                'is_finished'        => true,
                'status'             => $finalStatus,
            ];
        }

        // 3. Process and persist records in a transaction
        $mappingConfig = $job->mapping_config ?? [];
        $sourceDescriptors = $job->source_descriptors ?? ($job->detected_schema['source_descriptors'] ?? []);
        $validationErrors = $job->validation_errors ?? [];
        $recordsToInsert = [];
        $recordsToUpdate = [];
        $stagedIdentifiers = [];
        $stagedFingerprints = [];
        $processedCount = 0;
        $failedCount = 0;

        // Public exposure is strictly opt-in by authorized owner
        $isPublicExposureAllowed = ! empty($mappingConfig['_allow_public_exposure'])
            || (! empty($document->metadata['is_public_catalog']) && $document->getIngestionPurpose() === IngestionPurpose::KNOWLEDGE_BASE);

        foreach ($rows as $rowIndex => $rowCells) {
            $absoluteFileRow = $startRow + $rowIndex + $headerOffset;

            try {
                $parsed = $this->parseRow($headers, $rowCells, $mappingConfig, $absoluteFileRow, $isPublicExposureAllowed, $sourceDescriptors);

                if (! empty($parsed['errors'])) {
                    foreach ($parsed['errors'] as $err) {
                        $validationErrors[] = $err;
                    }
                    $failedCount++;
                }

                $recordData = [
                    'workspace_id'           => $job->workspace_id,
                    'document_id'            => $job->document_id,
                    'import_job_id'          => $job->id,
                    'sheet_name'             => $job->sheet_name ?: 'Sheet1',
                    'row_index'              => $absoluteFileRow,
                    'record_identifier'      => $parsed['identifier'],
                    'canonical_entity_name'  => $parsed['entity_name'],
                    'canonical_category'     => $parsed['category'],
                    'canonical_amount'       => $parsed['amount'],
                    'canonical_quantity'     => $parsed['quantity'],
                    'canonical_date'         => $parsed['date'],
                    'canonical_status'       => $parsed['status'],
                    'canonical_person'       => $parsed['person'],
                    'canonical_contact'      => $parsed['contact'],
                    'raw_values'             => json_encode($parsed['raw_values'], JSON_UNESCAPED_UNICODE),
                    'source_values'          => json_encode($parsed['source_values'] ?? [], JSON_UNESCAPED_UNICODE),
                    'mapped_attributes'      => json_encode($parsed['mapped_attributes'], JSON_UNESCAPED_UNICODE),
                    'semantic_projections'   => json_encode($parsed['semantic_projections'] ?? [], JSON_UNESCAPED_UNICODE),
                    'field_sensitivities'    => json_encode($parsed['field_sensitivities'] ?? [], JSON_UNESCAPED_UNICODE),
                    'custom_attributes'      => json_encode($parsed['custom_attributes'], JSON_UNESCAPED_UNICODE),
                    'content_fingerprint'    => $parsed['fingerprint'],
                    'is_public'              => $parsed['is_public'],
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ];

                // Conflict Resolution & Deduplication
                if (! empty($parsed['identifier']) && $job->conflict_mode !== 'append') {
                    // Check intra-chunk duplicates first
                    if (isset($stagedIdentifiers[$parsed['identifier']])) {
                        if ($job->conflict_mode === 'skip') {
                            continue;
                        } elseif ($job->conflict_mode === 'overwrite') {
                            $targetIndex = $stagedIdentifiers[$parsed['identifier']];
                            $recordsToInsert[$targetIndex] = $recordData;
                            continue;
                        }
                    }

                    // Check database records within workspace & document scope
                    $existing = StructuredRecord::where('workspace_id', $job->workspace_id)
                        ->where('document_id', $job->document_id)
                        ->where('record_identifier', $parsed['identifier'])
                        ->first();

                    if ($existing) {
                        if ($job->conflict_mode === 'overwrite') {
                            $recordsToUpdate[] = ['id' => $existing->id, 'data' => $recordData];
                            $processedCount++;
                            continue;
                        } elseif ($job->conflict_mode === 'skip') {
                            continue;
                        }
                    }
                } elseif (empty($parsed['identifier']) && $job->conflict_mode === 'skip') {
                    // Unidentified rows: deduplicate by content fingerprint
                    if (isset($stagedFingerprints[$parsed['fingerprint']])) {
                        continue;
                    }
                    $existingFingerprint = StructuredRecord::where('workspace_id', $job->workspace_id)
                        ->where('document_id', $job->document_id)
                        ->where('content_fingerprint', $parsed['fingerprint'])
                        ->first();
                    if ($existingFingerprint) {
                        continue;
                    }
                }

                $insertIdx = count($recordsToInsert);
                $recordsToInsert[] = $recordData;
                if (! empty($parsed['identifier'])) {
                    $stagedIdentifiers[$parsed['identifier']] = $insertIdx;
                }
                $stagedFingerprints[$parsed['fingerprint']] = $insertIdx;
                $processedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                $validationErrors[] = [
                    'row'     => $absoluteFileRow,
                    'column'  => 'general',
                    'reason'  => $e->getMessage(),
                    'value'   => null,
                ];
            }
        }

        // 4. Batch DB Commit
        DB::transaction(function () use ($recordsToInsert, $recordsToUpdate) {
            if (! empty($recordsToInsert)) {
                StructuredRecord::insert($recordsToInsert);
            }
            foreach ($recordsToUpdate as $up) {
                unset($up['data']['created_at']);
                StructuredRecord::where('id', $up['id'])->update($up['data']);
            }
        });

        // 5. Update Checkpoint
        $newCheckpoint = $job->checkpoint_row + count($rows);
        $newProcessed = $job->processed_rows + $processedCount;
        $newFailed = $job->failed_rows + $failedCount;

        $isFinished = $isEof || count($rows) < $chunkSize;
        $finalStatus = $isFinished
            ? ($newFailed > 0 ? 'completed_with_errors' : 'completed')
            : 'processing';

        $job->update([
            'checkpoint_row'    => $newCheckpoint,
            'processed_rows'    => $newProcessed,
            'failed_rows'       => $newFailed,
            'staged_rows'       => $newProcessed,
            'validation_errors' => array_slice($validationErrors, 0, 100), // Cap logged errors
            'status'            => $finalStatus,
            'completed_at'      => $isFinished ? now() : null,
        ]);

        if ($isFinished) {
            $document->update([
                'lifecycle_status' => DocumentLifecycleStatus::ACTIVE,
                'is_active'        => true,
                'chunk_count'      => $newProcessed,
            ]);
        }

        return [
            'processed_in_chunk' => count($rows),
            'remaining_rows'     => max(0, $job->total_rows - $newCheckpoint),
            'is_finished'        => $isFinished,
            'status'             => $finalStatus,
        ];
    }

    /**
     * Parse and map a single row into canonical fields and custom attributes.
     *
     * @param array<int, string> $headers
     * @param array<int, string> $rowCells
     * @param array<string, mixed> $mappingConfig
     * @param array<string, array<string, mixed>> $sourceDescriptors
     * @return array<string, mixed>
     */
    public function parseRow(
        array $headers,
        array $rowCells,
        array $mappingConfig,
        int $rowNum,
        bool $isPublicAllowed = false,
        array $sourceDescriptors = []
    ): array {
        $rawValues = [];
        $sourceValues = [];
        $mappedAttributes = [];
        $semanticProjections = [];
        $fieldSensitivities = [];
        $customAttributes = [];
        $errors = [];

        $identifier = null;
        $entityName = null;
        $category = null;
        $amount = null;
        $quantity = null;
        $date = null;
        $status = null;
        $person = null;
        $contact = null;
        $isPublic = false;

        foreach ($headers as $colIdx => $colName) {
            $sourceColId = 'col_' . str_pad((string) $colIdx, 3, '0', STR_PAD_LEFT);
            $rawCell = trim((string) ($rowCells[$colIdx] ?? ''));
            $rawValues[$colName] = $rawCell;
            $sourceValues[$sourceColId] = $rawCell;

            // 1. Locate column descriptor if available
            $desc = $sourceDescriptors[$sourceColId] ?? null;
            if (! $desc && ! empty($sourceDescriptors)) {
                foreach ($sourceDescriptors as $d) {
                    if (($d['original_header'] ?? '') === $colName || ($d['normalized_header'] ?? '') === $colName) {
                        $desc = $d;
                        break;
                    }
                }
            }

            // Fallback or explicit mapping configuration
            $mappingVal = $mappingConfig[$colName] ?? ($mappingConfig[$sourceColId] ?? null);

            $assignedCategory = SpreadsheetInspectorService::CATEGORY_CUSTOM_ATTRIBUTE;
            $semanticRole = 'custom_attribute';
            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_STRING;
            $entityType = 'unknown';
            $unit = null;
            $sensitivity = SpreadsheetInspectorService::SENSITIVITY_RESTRICTED;

            if ($desc) {
                $assignedCategory = $desc['suggested_category'] ?? $assignedCategory;
                $semanticRole = $desc['semantic_role'] ?? $semanticRole;
                $physicalType = $desc['physical_type'] ?? $physicalType;
                $entityType = $desc['entity_type'] ?? $entityType;
                $unit = $desc['unit'] ?? $unit;
                $sensitivity = $desc['sensitivity'] ?? $sensitivity;
            }

            // If mappingVal provides overrides
            if (is_string($mappingVal) && $mappingVal !== '') {
                $isCategory = in_array($mappingVal, [
                    SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
                    SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
                    SpreadsheetInspectorService::CATEGORY_DESCRIPTION,
                    SpreadsheetInspectorService::CATEGORY_AMOUNT,
                    SpreadsheetInspectorService::CATEGORY_QUANTITY,
                    SpreadsheetInspectorService::CATEGORY_DATE_TIME,
                    SpreadsheetInspectorService::CATEGORY_STATUS,
                    SpreadsheetInspectorService::CATEGORY_CATEGORY,
                    SpreadsheetInspectorService::CATEGORY_PERSON,
                    SpreadsheetInspectorService::CATEGORY_PERSON_REFERENCE,
                    SpreadsheetInspectorService::CATEGORY_CONTACT,
                    SpreadsheetInspectorService::CATEGORY_CONTACT_INFO,
                    SpreadsheetInspectorService::CATEGORY_CUSTOM_ATTRIBUTE,
                ], true);

                if ($isCategory) {
                    $assignedCategory = $mappingVal;
                    if (! $desc) {
                        if ($assignedCategory === SpreadsheetInspectorService::CATEGORY_ENTITY_NAME) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_STRING;
                            $semanticRole = 'entity_name';
                            $sensitivity = SpreadsheetInspectorService::SENSITIVITY_PUBLIC;
                        } elseif ($assignedCategory === SpreadsheetInspectorService::CATEGORY_AMOUNT) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_DECIMAL;
                            $semanticRole = 'amount';
                            if (preg_match('/\b(cost|salary|wage|cogs|commission)\b/i', $colName)) {
                                $sensitivity = SpreadsheetInspectorService::SENSITIVITY_RESTRICTED;
                            } else {
                                $sensitivity = SpreadsheetInspectorService::SENSITIVITY_PUBLIC;
                            }
                        } elseif ($assignedCategory === SpreadsheetInspectorService::CATEGORY_QUANTITY) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_DECIMAL;
                            $semanticRole = 'quantity';
                            $sensitivity = SpreadsheetInspectorService::SENSITIVITY_PUBLIC;
                        } elseif ($assignedCategory === SpreadsheetInspectorService::CATEGORY_DATE_TIME) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_DATE;
                            $semanticRole = 'date';
                            $sensitivity = SpreadsheetInspectorService::SENSITIVITY_INTERNAL;
                        } elseif ($assignedCategory === SpreadsheetInspectorService::CATEGORY_IDENTIFIER) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_IDENTIFIER;
                            $semanticRole = 'identifier';
                            $sensitivity = SpreadsheetInspectorService::SENSITIVITY_PUBLIC;
                        } elseif ($assignedCategory === SpreadsheetInspectorService::CATEGORY_DESCRIPTION) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_STRING;
                            $semanticRole = 'description';
                            $sensitivity = SpreadsheetInspectorService::SENSITIVITY_PUBLIC;
                        } elseif ($assignedCategory === SpreadsheetInspectorService::CATEGORY_CATEGORY) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_STRING;
                            $semanticRole = 'category';
                            $sensitivity = SpreadsheetInspectorService::SENSITIVITY_PUBLIC;
                        } elseif (in_array($assignedCategory, [SpreadsheetInspectorService::CATEGORY_PERSON, SpreadsheetInspectorService::CATEGORY_PERSON_REFERENCE], true)) {
                            $physicalType = SpreadsheetInspectorService::PHYSICAL_TYPE_STRING;
                            $semanticRole = 'person';
                            $sensitivity = preg_match('/\b(patient)\b/i', $colName)
                                ? SpreadsheetInspectorService::SENSITIVITY_RESTRICTED
                                : SpreadsheetInspectorService::SENSITIVITY_INTERNAL;
                        }
                    }
                } else {
                    $semanticRole = $mappingVal;
                }
            } elseif (is_array($mappingVal)) {
                $assignedCategory = $mappingVal['suggested_category'] ?? $assignedCategory;
                $semanticRole = $mappingVal['semantic_role'] ?? $semanticRole;
                $physicalType = $mappingVal['physical_type'] ?? $physicalType;
                $entityType = $mappingVal['entity_type'] ?? $entityType;
                $unit = $mappingVal['unit'] ?? $unit;
                $sensitivity = $mappingVal['sensitivity'] ?? $sensitivity;
            }

            // 2. Typed Parsing according to physical type & category
            $typedValue = null;
            if ($rawCell !== '') {
                if (in_array($physicalType, [
                    SpreadsheetInspectorService::PHYSICAL_TYPE_DECIMAL,
                    SpreadsheetInspectorService::PHYSICAL_TYPE_CURRENCY,
                    SpreadsheetInspectorService::PHYSICAL_TYPE_PERCENTAGE,
                ], true) || in_array($assignedCategory, [
                    SpreadsheetInspectorService::CATEGORY_AMOUNT,
                    SpreadsheetInspectorService::CATEGORY_QUANTITY,
                ], true)) {
                    $parsedAmount = $this->parseDecimal($rawCell);
                    if ($parsedAmount !== null) {
                        $typedValue = $parsedAmount;
                    } else {
                        $errors[] = [
                            'row'    => $rowNum,
                            'column' => $colName,
                            'reason' => "Invalid numerical amount format: '{$rawCell}'",
                            'value'  => $rawCell,
                        ];
                    }
                } elseif ($physicalType === SpreadsheetInspectorService::PHYSICAL_TYPE_INTEGER) {
                    $parsedAmount = $this->parseDecimal($rawCell);
                    if ($parsedAmount !== null) {
                        $typedValue = (int) round($parsedAmount);
                    } else {
                        $errors[] = [
                            'row'    => $rowNum,
                            'column' => $colName,
                            'reason' => "Invalid integer format: '{$rawCell}'",
                            'value'  => $rawCell,
                        ];
                    }
                } elseif (in_array($physicalType, [
                    SpreadsheetInspectorService::PHYSICAL_TYPE_DATE,
                    SpreadsheetInspectorService::PHYSICAL_TYPE_DATETIME,
                ], true) || $assignedCategory === SpreadsheetInspectorService::CATEGORY_DATE_TIME) {
                    $parsedDate = $this->parseDateTime($rawCell);
                    if ($parsedDate !== null) {
                        $typedValue = $parsedDate;
                    } else {
                        $errors[] = [
                            'row'    => $rowNum,
                            'column' => $colName,
                            'reason' => "Unrecognized date/time format: '{$rawCell}'",
                            'value'  => $rawCell,
                        ];
                    }
                } elseif ($physicalType === SpreadsheetInspectorService::PHYSICAL_TYPE_BOOLEAN) {
                    $l = strtolower($rawCell);
                    $typedValue = in_array($l, ['true', '1', 'yes', 'y', 'active'], true);
                } else {
                    $typedValue = $rawCell;
                }
            }

            // 3. Source-Column-Centric mapped_attributes entry
            $mappedAttributes[$sourceColId] = [
                'source_column_id'   => $sourceColId,
                'header'             => $colName,
                'physical_type'      => $physicalType,
                'suggested_category' => $assignedCategory,
                'semantic_role'      => $semanticRole,
                'entity_type'        => $entityType,
                'value'              => $typedValue,
                'raw_value'          => $rawCell,
                'unit'               => $unit,
                'sensitivity'        => $sensitivity,
            ];

            // 4. Record sensitivity
            $fieldSensitivities[$sourceColId] = $sensitivity;

            // 5. Semantic Projections (Indexed by semantic_role without collision)
            if ($typedValue !== null && $semanticRole !== 'custom_attribute') {
                if (! array_key_exists($semanticRole, $semanticProjections)) {
                    $semanticProjections[$semanticRole] = $typedValue;
                } else {
                    // Duplicate semantic role collision avoidance!
                    $prevVal = $semanticProjections[$semanticRole];
                    if (! is_array($prevVal) || ! isset($prevVal['_is_multi_column'])) {
                        $firstColId = null;
                        foreach ($mappedAttributes as $cId => $attr) {
                            if (($attr['semantic_role'] ?? '') === $semanticRole && $cId !== $sourceColId) {
                                $firstColId = $cId;
                                break;
                            }
                        }
                        $firstColId = $firstColId ?: 'col_first';
                        $semanticProjections[$semanticRole] = [
                            '_is_multi_column' => true,
                            $firstColId        => $prevVal,
                            $sourceColId       => $typedValue,
                        ];
                        $semanticProjections["{$semanticRole}_{$firstColId}"] = $prevVal;
                    } else {
                        $semanticProjections[$semanticRole][$sourceColId] = $typedValue;
                    }
                    $semanticProjections["{$semanticRole}_{$sourceColId}"] = $typedValue;
                }
            }

            // 6. Legacy Canonical Fields (First representative wins)
            switch ($assignedCategory) {
                case SpreadsheetInspectorService::CATEGORY_IDENTIFIER:
                    if ($identifier === null && $rawCell !== '') {
                        $identifier = $rawCell;
                        $mappedAttributes['identifier'] = $rawCell;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_ENTITY_NAME:
                    if ($entityName === null && $rawCell !== '') {
                        $entityName = $rawCell;
                        $mappedAttributes['entity_name'] = $rawCell;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_CATEGORY:
                    if ($category === null && $rawCell !== '') {
                        $category = $rawCell;
                        $mappedAttributes['category'] = $rawCell;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_AMOUNT:
                    if ($amount === null && $typedValue !== null) {
                        $amount = $typedValue;
                        $mappedAttributes['amount'] = $typedValue;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_QUANTITY:
                    if ($quantity === null && $typedValue !== null) {
                        $quantity = $typedValue;
                        $mappedAttributes['quantity'] = $typedValue;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_DATE_TIME:
                    if ($date === null && $typedValue !== null) {
                        $date = $typedValue;
                        $mappedAttributes['date'] = $typedValue;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_STATUS:
                    if ($status === null && $rawCell !== '') {
                        $status = mb_substr($rawCell, 0, 64);
                        $mappedAttributes['status'] = $status;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_PERSON:
                case SpreadsheetInspectorService::CATEGORY_PERSON_REFERENCE:
                    if ($person === null && $rawCell !== '') {
                        $person = mb_substr($rawCell, 0, 128);
                        $mappedAttributes['person'] = $person;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_CONTACT:
                case SpreadsheetInspectorService::CATEGORY_CONTACT_INFO:
                    if ($contact === null && $rawCell !== '') {
                        $contact = mb_substr($rawCell, 0, 128);
                        $mappedAttributes['contact'] = $contact;
                    }
                    break;

                case SpreadsheetInspectorService::CATEGORY_DESCRIPTION:
                    if (! isset($mappedAttributes['description']) && $rawCell !== '') {
                        $mappedAttributes['description'] = $rawCell;
                    }
                    break;

                default:
                    if ($rawCell !== '') {
                        $customAttributes[$colName] = $rawCell;
                    }
                    break;
            }
        }

        // Enforce Public vs Private Invariants (Section 4):
        // 1. New records default to private (is_public = false).
        // 2. Public exposure requires explicit authorized approval ($isPublicAllowed = true).
        // 3. Employee, salary, commission, internal sales, targets, purchase costs, and patient data MUST strictly remain private.
        $hasRestrictedField = false;
        $restrictedRoles = [
            'salary', 'remuneration', 'stipend', 'wage', 'payroll',
            'purchase_cost', 'unit_cost', 'cogs', 'supplier_price',
            'commission', 'bonus', 'internal_target',
            'patient', 'clinical_notes', 'diagnosis', 'symptom',
        ];

        foreach ($mappedAttributes as $k => $attr) {
            if (! is_array($attr)) {
                continue;
            }
            if (($attr['sensitivity'] ?? '') === SpreadsheetInspectorService::SENSITIVITY_RESTRICTED) {
                $hasRestrictedField = true;
                break;
            }
            if (in_array($attr['semantic_role'] ?? '', $restrictedRoles, true)) {
                $hasRestrictedField = true;
                break;
            }
        }

        $hasWorkforcePrivacy = ! empty($person);
        if ($isPublicAllowed && ! empty($entityName) && ! $hasWorkforcePrivacy && ! $hasRestrictedField) {
            $isPublic = true;
        } else {
            $isPublic = false;
        }

        // Generate normalized fingerprint of raw values for deduplication
        $fingerprint = hash('sha256', json_encode($rawValues, JSON_UNESCAPED_UNICODE));

        return [
            'identifier'           => $identifier,
            'entity_name'          => $entityName,
            'category'             => $category,
            'amount'               => $amount,
            'quantity'             => $quantity,
            'date'                 => $date,
            'status'               => $status,
            'person'               => $person,
            'contact'              => $contact,
            'raw_values'           => $rawValues,
            'source_values'        => $sourceValues,
            'mapped_attributes'    => $mappedAttributes,
            'semantic_projections' => $semanticProjections,
            'field_sensitivities'  => $fieldSensitivities,
            'custom_attributes'    => $customAttributes,
            'fingerprint'          => $fingerprint,
            'is_public'            => $isPublic,
            'errors'               => $errors,
        ];
    }

    /**
     * Read a chunk of CSV rows starting from $startRow (1-based data index) up to $chunkSize.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, is_eof: bool}
     */
    private function readCsvChunk(string $path, int $headerOffset, int $startRow, int $chunkSize): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['headers' => [], 'rows' => [], 'is_eof' => true];
        }

        $headers = [];
        $rows = [];
        $currentLine = 0;
        $dataRowCounter = 0;

        // Skip to header row
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($currentLine === $headerOffset) {
                foreach ($row as $idx => $cell) {
                    $c = trim((string) $cell);
                    $headers[] = $c !== '' ? $c : "Column_" . ($idx + 1);
                }
                break;
            }
            $currentLine++;
        }

        // Seek to $startRow
        while ($dataRowCounter < $startRow - 1 && ($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $dataRowCounter++;
        }

        // Read up to $chunkSize rows
        $readCount = 0;
        $isEof = false;

        while ($readCount < $chunkSize && ($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $cleaned = array_map(fn ($c) => trim((string) $c), $row);
            // Skip entirely empty row
            if (! empty(array_filter($cleaned, fn ($v) => $v !== ''))) {
                $rows[] = $cleaned;
                $readCount++;
            }
        }

        if (feof($handle)) {
            $isEof = true;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows'    => $rows,
            'is_eof'  => $isEof,
        ];
    }

    /**
     * Read a chunk of Excel rows using PhpSpreadsheet.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, is_eof: bool}
     */
    private function readExcelChunk(string $path, ?string $sheetName, int $headerOffset, int $startRow, int $chunkSize): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        if ($sheetName) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $sheetName ? ($spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet()) : $spreadsheet->getActiveSheet();

        $highestRow = (int) $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        // 1. Read Headers (row is 1-based in PhpSpreadsheet: headerOffset + 1)
        $excelHeaderRow = $headerOffset + 1;
        $headerData = $worksheet->rangeToArray("A{$excelHeaderRow}:{$highestColumn}{$excelHeaderRow}", null, true, true, false);
        $rawHeaders = ! empty($headerData) ? $headerData[0] : [];
        $headers = [];
        foreach ($rawHeaders as $idx => $cell) {
            $c = trim((string) $cell);
            $headers[] = $c !== '' ? $c : "Column_" . ($idx + 1);
        }

        // 2. Read Data Chunk
        $excelStartRow = $excelHeaderRow + $startRow;
        $excelEndRow = min($highestRow, $excelStartRow + $chunkSize - 1);

        $rows = [];
        $isEof = $excelEndRow >= $highestRow;

        if ($excelStartRow <= $highestRow) {
            $chunkData = $worksheet->rangeToArray("A{$excelStartRow}:{$highestColumn}{$excelEndRow}", null, true, true, false);
            foreach ($chunkData as $r) {
                $cleaned = array_map(fn ($c) => trim(is_scalar($c) ? (string) $c : ''), $r);
                if (! empty(array_filter($cleaned, fn ($v) => $v !== ''))) {
                    $rows[] = $cleaned;
                }
            }
        }

        return [
            'headers' => $headers,
            'rows'    => $rows,
            'is_eof'  => $isEof,
        ];
    }

    /**
     * Parse decimal values from formatted currency strings, commas, Bengali digits, or accounting formats.
     */
    public function parseDecimal(string $val): ?float
    {
        $v = trim($val);
        if ($v === '') {
            return null;
        }

        // Convert Bengali digits (০-৯) to English digits
        $bengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $v = str_replace($bengaliDigits, $englishDigits, $v);

        // Check for negative accounting format in parentheses e.g. (500) or ($500.25)
        $isNegative = false;
        if (preg_match('/^\((.+)\)$/', $v, $matches)) {
            $isNegative = true;
            $v = $matches[1];
        } elseif (str_contains($v, '-')) {
            $isNegative = true;
            $v = str_replace('-', '', $v);
        }

        // Strip currency prefixes and suffixes (e.g. Tk., BDT, $, ৳, টাকা)
        $v = preg_replace('/^[+-]?\s*(?:tk\.?|bdt\.?|usd\.?|\$|৳|₹|€|£)\s*/iu', '', $v);
        $v = preg_replace('/\s*(?:tk\.?|bdt\.?|টাকা|৳)$/iu', '', $v);

        // Strip commas and any remaining non-numeric non-period characters
        $cleaned = preg_replace('/[^\d\.]/', '', str_replace(',', '', $v));

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        $num = (float) $cleaned;
        return $isNegative ? -$num : $num;
    }

    /**
     * Parse date/time strings into standard ISO format.
     */
    public function parseDateTime(string $val): ?string
    {
        $v = trim($val);
        if ($v === '' || is_numeric($v)) {
            return null;
        }

        // Convert Bengali digits
        $bengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $v = str_replace($bengaliDigits, $englishDigits, $v);

        $ts = strtotime($v);
        if ($ts === false || $ts <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }
}
