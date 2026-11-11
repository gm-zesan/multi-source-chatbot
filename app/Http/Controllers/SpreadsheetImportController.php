<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\User;
use App\Services\Spreadsheet\SpreadsheetIngestionService;
use App\Services\Spreadsheet\SpreadsheetInspectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SpreadsheetImportController extends Controller
{
    public function __construct(
        private readonly SpreadsheetInspectorService $inspector,
        private readonly SpreadsheetIngestionService $ingestion,
        private readonly \App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService $relationshipService = new \App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService(),
    ) {}

    /**
     * Inspect workbook structure, sheets, candidate headers, and data profiles.
     */
    public function inspect(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::VIEW->value);
        $this->authorizeWorkspace($document);

        $filePath = $document->file_path;
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found on storage disk.',
            ], 404);
        }

        $absolutePath = Storage::disk('local')->path($filePath);
        $sheetName = $request->query('sheet');

        $result = $this->inspector->inspectWorkbook(
            absolutePath: $absolutePath,
            originalFilename: $document->original_filename,
            targetSheet: $sheetName ? (string) $sheetName : null
        );

        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Spreadsheet inspection failed.',
            ], 422);
        }

        return response()->json([
            'success'          => true,
            'document_id'      => $document->id,
            'filename'         => $document->original_filename,
            'active_sheet'     => $result['active_sheet'],
            'sheets'           => $result['sheets'],
            'total_rows'       => $result['total_rows'],
            'inspection'       => $result['inspection'],
            'lifecycle_status' => $document->lifecycle_status->value ?? (string) $document->lifecycle_status,
        ]);
    }

    /**
     * Retrieve suggested column mappings and ambiguity analysis for the document.
     */
    public function mappingSuggestions(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::VIEW->value);
        $this->authorizeWorkspace($document);

        $latestJob = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        $schema = $latestJob?->detected_schema;

        if (empty($schema)) {
            $filePath = $document->file_path;
            if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document file not found.',
                ], 404);
            }

            $absolutePath = Storage::disk('local')->path($filePath);
            $inspection = $this->inspector->inspectWorkbook($absolutePath, $document->original_filename);
            $schema = $inspection['inspection'] ?? null;
        }

        return response()->json([
            'success'               => true,
            'document_id'           => $document->id,
            'job_id'                => $latestJob?->id,
            'status'                => $latestJob?->status ?? 'awaiting_mapping',
            'lifecycle_status'      => $document->lifecycle_status->value ?? (string) $document->lifecycle_status,
            'header_row_index'      => $latestJob?->header_row_index ?? ($schema['header_row_index'] ?? 0),
            'candidate_headers'     => $schema['candidate_headers'] ?? [],
            'columns'               => $schema['columns'] ?? [],
            'source_descriptors'    => $latestJob?->source_descriptors ?? ($schema['source_descriptors'] ?? []),
            'schema_version'        => SpreadsheetInspectorService::CONTRACT_VERSION,
            'is_ambiguous'          => $schema['is_ambiguous'] ?? false,
            'existing_mapping'      => $latestJob?->mapping_config ?? [],
            'available_categories'  => [
                SpreadsheetInspectorService::CATEGORY_ENTITY_NAME      => 'Product / Entity Name',
                SpreadsheetInspectorService::CATEGORY_IDENTIFIER       => 'SKU / Code / Identifier',
                SpreadsheetInspectorService::CATEGORY_DESCRIPTION      => 'Description / Details',
                SpreadsheetInspectorService::CATEGORY_AMOUNT           => 'Amount / Price / Financial',
                SpreadsheetInspectorService::CATEGORY_QUANTITY         => 'Quantity / Stock / Units',
                SpreadsheetInspectorService::CATEGORY_DATE_TIME        => 'Date / Time',
                SpreadsheetInspectorService::CATEGORY_STATUS           => 'Status / State',
                SpreadsheetInspectorService::CATEGORY_CATEGORY         => 'Category / Department',
                SpreadsheetInspectorService::CATEGORY_PERSON           => 'Person / Employee / Agent',
                SpreadsheetInspectorService::CATEGORY_CONTACT          => 'Contact / Phone / Email',
                SpreadsheetInspectorService::CATEGORY_CUSTOM_ATTRIBUTE => 'Custom Attribute (Preserved)',
            ],
            'available_roles'       => [
                'amount' => [
                    'purchase_cost'  => 'Purchase Cost / COGS (Internal)',
                    'selling_price'  => 'Selling Price / Unit Price (Public)',
                    'salary'         => 'Salary / Wage (Restricted)',
                    'commission'     => 'Commission / Incentive (Restricted)',
                    'discount'       => 'Discount / Markdown (Internal/Public)',
                    'tax'            => 'Tax / VAT (Internal)',
                    'service_fee'    => 'Service Fee / Charge (Public)',
                    'booking_charge' => 'Booking / Reservation Charge (Public)',
                    'due_balance'    => 'Due Balance / Outstanding (Internal)',
                    'donation_amount'=> 'Donation Amount / Pledge (Internal)',
                ],
                'person' => [
                    'doctor'       => 'Doctor / Physician (Internal)',
                    'patient'      => 'Patient (Restricted)',
                    'salesperson'  => 'Salesperson / Agent (Internal)',
                    'employee'     => 'Employee / Staff (Internal)',
                    'customer'     => 'Customer / Buyer (Internal)',
                    'guest'        => 'Guest / Resident (Internal)',
                    'donor'        => 'Donor / Contributor (Internal)',
                    'beneficiary'  => 'Beneficiary (Restricted)',
                ],
                'date' => [
                    'order_date'       => 'Order Date',
                    'appointment_date' => 'Appointment Date',
                    'check_in_date'    => 'Check-in Date',
                    'check_out_date'   => 'Check-out Date',
                    'delivery_date'    => 'Delivery Date',
                    'transaction_date' => 'Transaction Date',
                ],
                'identifier' => [
                    'sku'            => 'SKU / Product Code',
                    'order_id'       => 'Order ID',
                    'appointment_id' => 'Appointment ID',
                    'booking_id'     => 'Booking / Reservation ID',
                    'room_number'    => 'Room Number / Code',
                    'patient_id'     => 'Patient ID',
                    'employee_id'    => 'Employee ID',
                ],
            ],
            'available_entities' => [
                'product', 'order', 'employee', 'customer', 'medical_provider',
                'patient', 'appointment', 'hotel_room', 'donation', 'service', 'general',
            ],
            'available_sensitivities' => [
                SpreadsheetInspectorService::SENSITIVITY_PUBLIC,
                SpreadsheetInspectorService::SENSITIVITY_INTERNAL,
                SpreadsheetInspectorService::SENSITIVITY_RESTRICTED,
            ],
        ]);
    }

    /**
     * Confirm column mappings, header selection, conflict mode, and launch ingestion.
     */
    public function confirmMapping(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);
        $this->authorizeWorkspace($document);

        $validated = $request->validate([
            'header_row_index'   => ['nullable', 'integer', 'min:0'],
            'mappings'           => ['nullable', 'array'],
            'source_descriptors' => ['nullable', 'array'],
            'conflict_mode'      => ['nullable', 'string', 'in:skip,overwrite,append'],
            'target_sheet'       => ['nullable', 'string'],
        ]);

        if (empty($validated['mappings']) && empty($validated['source_descriptors'])) {
            throw ValidationException::withMessages([
                'mappings' => 'Either mappings or source_descriptors must be provided.',
            ]);
        }

        // Fail-closed security validation:
        // Restricted fields (salary, wage, patient, cost, commission, diagnosis, bonus, payment, margin, etc.)
        // and unvetted custom fields MUST NOT be marked as public
        foreach ($validated['mappings'] ?? [] as $key => $val) {
            if (is_array($val)) {
                $this->validatePublicSensitivity((string) $key, $val, 'mappings');
            }
        }

        foreach ($validated['source_descriptors'] ?? [] as $colId => $desc) {
            if (is_array($desc)) {
                $this->validatePublicSensitivity((string) $colId, $desc, 'source_descriptors');
            }
        }

        $job = $this->ingestion->confirmMapping(
            document: $document,
            data: $validated,
            userId: Auth::id()
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Mapping confirmed and ingestion queued successfully.',
            'job_id'    => $job->id,
            'status'    => $job->status,
            'total_rows'=> $job->total_rows,
        ]);
    }

    /**
     * Poll ingestion progress and status.
     */
    public function status(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::VIEW->value);
        $this->authorizeWorkspace($document);

        $job = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $job) {
            return response()->json([
                'success'          => true,
                'has_job'          => false,
                'document_status'  => $document->lifecycle_status->value ?? (string) $document->lifecycle_status,
            ]);
        }

        $progressPercentage = $job->total_rows > 0
            ? round(($job->processed_rows / $job->total_rows) * 100, 1)
            : ($job->status === 'completed' ? 100.0 : 0.0);

        return response()->json([
            'success'             => true,
            'has_job'             => true,
            'job_id'              => $job->id,
            'status'              => $job->status,
            'document_status'     => $document->lifecycle_status->value ?? (string) $document->lifecycle_status,
            'total_rows'          => $job->total_rows,
            'processed_rows'      => $job->processed_rows,
            'failed_rows'         => $job->failed_rows,
            'checkpoint_row'      => $job->checkpoint_row,
            'progress_percentage' => $progressPercentage,
            'error_message'       => $job->error_message,
            'started_at'          => $job->started_at?->toIso8601String(),
            'completed_at'        => $job->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Retrieve row-level validation errors for the import job.
     */
    public function errors(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::VIEW->value);
        $this->authorizeWorkspace($document);

        $job = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'No import job found for this document.',
            ], 404);
        }

        $errors = $job->validation_errors ?? [];

        return response()->json([
            'success'      => true,
            'job_id'       => $job->id,
            'total_errors' => count($errors),
            'errors'       => $errors,
        ]);
    }

    /**
     * Retry a failed or cancelled import job.
     */
    public function retry(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);
        $this->authorizeWorkspace($document);

        $job = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'No import job found to retry.',
            ], 404);
        }

        $job = $this->ingestion->retryJob($job);

        return response()->json([
            'success' => true,
            'message' => 'Import job has been reset and queued for retry.',
            'job_id'  => $job->id,
            'status'  => $job->status,
        ]);
    }

    /**
     * Cancel an active or queued import job.
     */
    public function cancel(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);
        $this->authorizeWorkspace($document);

        $job = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'No active import job found to cancel.',
            ], 404);
        }

        $job = $this->ingestion->cancelJob($job);

        return response()->json([
            'success' => true,
            'message' => 'Import job cancelled.',
            'job_id'  => $job->id,
            'status'  => $job->status,
        ]);
    }

    /**
     * Ensure the authenticated user has the required permission.
     * Superadmins bypass permission checks.
     */
    private function authorizeAction(string $permission): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if (! $user || ! $user->can($permission)) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Ensure the document belongs to the authenticated user's workspace.
     * Superadmins have unrestricted access across all workspaces.
     */
    private function authorizeWorkspace(KnowledgeDocument $document): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if (! $user || (int) $user->workspace_id !== (int) $document->workspace_id) {
            abort(403, 'Unauthorized access to document.');
        }
    }

    /**
     * Validate whether a column descriptor or mapping is allowed to have public sensitivity.
     */
    private function validatePublicSensitivity(string $key, array $descriptor, string $fieldBag): void
    {
        $sensitivity = strtolower((string) ($descriptor['sensitivity'] ?? ''));
        if ($sensitivity !== SpreadsheetInspectorService::SENSITIVITY_PUBLIC) {
            return;
        }

        $role = strtolower((string) ($descriptor['semantic_role'] ?? ''));
        $header = strtolower((string) ($descriptor['original_header'] ?? $key));
        $category = strtolower((string) ($descriptor['suggested_category'] ?? ''));

        // 1. Prohibited sensitive substrings (Financial, workforce, clinical, internal)
        $sensitivePattern = '/\b(salary|wage|payroll|remuneration|stipend|bonus|incentive|commission|payment|payout|cost|cogs|procurement|supplier|margin|profit|markup|revenue|turnover|target|performance|patient|diagnosis|symptom|clinical|medical_record|prescription|treatment|confidential|secret|internal|due_balance)\b|বেতন|কমিশন|খরচ|লাভ|মুনাফা|রোগী|পেশেন্ট/ui';

        if (preg_match($sensitivePattern, $role) || preg_match($sensitivePattern, $header)) {
            throw ValidationException::withMessages([
                $fieldBag => "Restricted field '{$key}' (role: '{$role}') contains protected business or clinical keywords and cannot be marked as public.",
            ]);
        }

        // 2. Vetted public domain allowlist
        $allowedPublicRoles = [
            'selling_price', 'unit_price', 'mrp', 'retail_price', 'public_price', 'price',
            'product_name', 'item_name', 'entity_name', 'title',
            'description', 'product_description', 'details', 'specs',
            'sku', 'barcode', 'item_code', 'product_code',
            'category', 'department', 'brand',
            'quantity', 'order_quantity', 'stock_quantity',
            'room_number', 'booking_charge', 'service_fee', 'consultation_fee',
            'order_status', 'booking_status', 'delivery_status',
            'faq_question', 'faq_answer',
        ];

        $allowedPublicCategories = [
            SpreadsheetInspectorService::CATEGORY_ENTITY_NAME,
            SpreadsheetInspectorService::CATEGORY_DESCRIPTION,
            SpreadsheetInspectorService::CATEGORY_CATEGORY,
            SpreadsheetInspectorService::CATEGORY_IDENTIFIER,
            SpreadsheetInspectorService::CATEGORY_STATUS,
            SpreadsheetInspectorService::CATEGORY_QUANTITY,
        ];

        $isRoleAllowed = in_array($role, $allowedPublicRoles, true);
        $isCategoryAllowed = in_array($category, $allowedPublicCategories, true);

        if (! $isRoleAllowed && ! $isCategoryAllowed) {
            throw ValidationException::withMessages([
                $fieldBag => "Custom or unvetted field '{$key}' (role: '{$role}') cannot be exposed as public without explicit security clearance.",
            ]);
        }
    }

    /**
     * Inspect discovered cross-sheet relationships for a document.
     */
    public function relationships(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::VIEW->value);
        $this->authorizeWorkspace($document);

        $latestJob = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $latestJob) {
            return response()->json([
                'success' => false,
                'message' => 'No spreadsheet import job found for this document.',
            ], 404);
        }

        $graph = $this->relationshipService->loadGraph($latestJob);

        return response()->json([
            'success'       => true,
            'document_id'   => $document->id,
            'job_id'        => $latestJob->id,
            'graph'         => $graph->toArray(),
            'active_edges'  => array_values(array_map(fn($e) => $e->toArray(), $graph->getActiveEdges())),
            'pending_edges' => array_values(array_map(fn($e) => $e->toArray(), $graph->getPendingEdges())),
        ]);
    }

    /**
     * Trigger relationship discovery across all sheets in the uploaded workbook.
     */
    public function discoverRelationships(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);
        $this->authorizeWorkspace($document);

        $latestJob = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $latestJob) {
            return response()->json([
                'success' => false,
                'message' => 'No spreadsheet import job found for this document.',
            ], 404);
        }

        $filePath = $document->file_path;
        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found on disk.',
            ], 404);
        }

        $absolutePath = Storage::disk('local')->path($filePath);
        $graph = $this->relationshipService->discoverWorkbookFile($latestJob, $absolutePath);

        return response()->json([
            'success'       => true,
            'message'       => 'Cross-sheet relationships discovered successfully.',
            'document_id'   => $document->id,
            'job_id'        => $latestJob->id,
            'graph'         => $graph->toArray(),
            'active_edges'  => array_values(array_map(fn($e) => $e->toArray(), $graph->getActiveEdges())),
            'pending_edges' => array_values(array_map(fn($e) => $e->toArray(), $graph->getPendingEdges())),
        ]);
    }

    /**
     * Confirm a candidate relationship edge.
     */
    public function confirmRelationship(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);
        $this->authorizeWorkspace($document);

        $validated = $request->validate([
            'edge_id' => ['required', 'string'],
        ]);

        $latestJob = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $latestJob) {
            return response()->json([
                'success' => false,
                'message' => 'No active spreadsheet job found.',
            ], 404);
        }

        /** @var User $user */
        $user = Auth::user();

        $confirmed = $this->relationshipService->confirmRelationship(
            job: $latestJob,
            edgeId: $validated['edge_id'],
            userId: (int) $user->id,
            workspaceId: (int) $document->workspace_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Relationship edge confirmed successfully.',
            'edge'    => $confirmed->toArray(),
        ]);
    }

    /**
     * Reject a candidate relationship edge.
     */
    public function rejectRelationship(Request $request, KnowledgeDocument $document): JsonResponse
    {
        $this->authorizeAction(KnowledgeDocumentPermission::UPDATE->value);
        $this->authorizeWorkspace($document);

        $validated = $request->validate([
            'edge_id' => ['required', 'string'],
            'reason'  => ['nullable', 'string', 'max:500'],
        ]);

        $latestJob = SpreadsheetImportJob::where('document_id', $document->id)
            ->where('workspace_id', $document->workspace_id)
            ->latest()
            ->first();

        if (! $latestJob) {
            return response()->json([
                'success' => false,
                'message' => 'No active spreadsheet job found.',
            ], 404);
        }

        /** @var User $user */
        $user = Auth::user();

        $rejected = $this->relationshipService->rejectRelationship(
            job: $latestJob,
            edgeId: $validated['edge_id'],
            userId: (int) $user->id,
            workspaceId: (int) $document->workspace_id,
            reason: $validated['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Relationship edge rejected.',
            'edge'    => $rejected->toArray(),
        ]);
    }
}
