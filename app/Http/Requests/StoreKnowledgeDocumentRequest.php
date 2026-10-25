<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Permissions\KnowledgeDocumentPermission;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeDocumentRequest extends FormRequest
{
    /**
     * Allowed MIME types for uploaded knowledge documents.
     */
    public const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'text/csv',
        'text/plain',
        'application/csv',
        'text/x-csv',
    ];

    /**
     * Allowed file extensions for MIME detection matching.
     */
    public const ALLOWED_EXTENSIONS = ['xlsx', 'csv', 'zip', 'txt'];

    /**
     * Supported client extensions for user uploads.
     */
    public const SUPPORTED_CLIENT_EXTENSIONS = ['xlsx', 'csv'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
            return true;
        }

        return $user->can(KnowledgeDocumentPermission::CREATE->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedMimes = implode(',', self::ALLOWED_MIME_TYPES);
        $allowedExtensions = implode(',', self::ALLOWED_EXTENSIONS);

        $purposeVal = $this->input('purpose') ?? $this->input('ingestion_purpose');
        $resolvedPurpose = $purposeVal ? \App\Enums\IngestionPurpose::resolve($purposeVal) : null;
        $isKnowledge = $resolvedPurpose === \App\Enums\IngestionPurpose::KNOWLEDGE_BASE;

        $validPurposes = [
            \App\Enums\IngestionPurpose::DATA_ANALYTICS->value,
            \App\Enums\IngestionPurpose::KNOWLEDGE_BASE->value,
            'generic_analytics',
            'semantic_knowledge',
        ];

        return [
            'workspace_id'      => ['nullable', 'integer', 'exists:workspaces,id'],
            'purpose'           => ['nullable', 'string', \Illuminate\Validation\Rule::in($validPurposes)],
            'ingestion_purpose' => ['nullable', 'string', \Illuminate\Validation\Rule::in($validPurposes)],
            'title'             => [$isKnowledge ? 'required' : 'nullable', 'string', 'max:255'],
            'content'           => [$isKnowledge ? 'required' : 'nullable', 'string', 'min:5', 'max:5000', new \App\Rules\MaxWords(250)],
            'document'          => [
                $isKnowledge ? 'nullable' : 'required',
                'file',
                'max:20480', // 20MB in KB
                "mimes:{$allowedExtensions}",
                "mimetypes:{$allowedMimes}",
            ],
        ];
    }

    /**
     * Custom error messages for validation failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required'     => 'Please provide a title for the knowledge article.',
            'content.required'   => 'Please enter the text content for the FAQ / Knowledge article.',
            'document.required'  => 'Please select a spreadsheet file to upload for Data & Analytics.',
            'document.file'      => 'The uploaded file is invalid.',
            'document.max'       => 'The document must not exceed 20 MB.',
            'document.mimes'     => 'Only .xlsx and .csv files are supported.',
            'document.mimetypes' => 'The detected file type is not supported or the file content is spoofed.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var User|null $user */
            $user = $this->user();
            if ($user && ! $user->hasRole(RoleEnum::SUPERADMIN->value) && $this->filled('workspace_id')) {
                $requestedWsId = (int) $this->input('workspace_id');
                if ($requestedWsId !== (int) $user->workspace_id) {
                    $validator->errors()->add('workspace', 'Unauthorized workspace selection. Users may only target their assigned workspace.');
                    return;
                }
            }

            $file = $this->file('document');
            if (! $file || ! $file->isValid()) {
                return;
            }

            $clientExt = strtolower($file->getClientOriginalExtension());
            if (! in_array($clientExt, self::SUPPORTED_CLIENT_EXTENSIONS, true)) {
                $validator->errors()->add('document', 'Only .xlsx and .csv files are supported.');
                return;
            }

            // Reject zero-byte / empty files
            if ($file->getSize() === 0) {
                $validator->errors()->add('document', 'The uploaded file is empty.');
                return;
            }

            // Reject raw zip files masquerading as CSV
            $detectedMime = $file->getMimeType();
            if ($clientExt === 'csv' && $detectedMime === 'application/zip') {
                $validator->errors()->add('document', 'The detected file type is not supported or the file content is spoofed.');
                return;
            }

            if ($clientExt === 'xlsx') {
                $this->validateXlsxStructure($file, $validator);
            }
        });
    }

    /**
     * Validate the structural integrity of an uploaded XLSX file.
     */
    protected function validateXlsxStructure(\Illuminate\Http\UploadedFile $file, \Illuminate\Contracts\Validation\Validator $validator): void
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            $validator->errors()->add('document', 'The uploaded XLSX file could not be read.');
            return;
        }

        if (! class_exists(\ZipArchive::class)) {
            $validator->errors()->add('document', 'The server cannot process XLSX files because the PHP ZipArchive extension is not available.');
            return;
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($realPath, \ZipArchive::CHECKCONS);
        if ($openResult !== true) {
            $validator->errors()->add('document', 'The uploaded XLSX file is not a valid ZIP package.');
            return;
        }

        try {
            $hasContentTypes = false;
            $hasWorkbook = false;
            $totalDecompressedSize = 0;
            $maxDecompressedBytes = 50 * 1024 * 1024; // 50MB
            $maxRatio = 100.0;
            $maxFiles = 1000;

            if ($zip->numFiles > $maxFiles) {
                $validator->errors()->add('document', 'The XLSX package contains too many internal files.');
                return;
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! $stat) {
                    continue;
                }

                $name = $stat['name'];
                if ($name === '[Content_Types].xml') {
                    $hasContentTypes = true;
                }
                if ($name === 'xl/workbook.xml') {
                    $hasWorkbook = true;
                }

                $size = (int) ($stat['size'] ?? 0);
                $compSize = (int) ($stat['comp_size'] ?? 0);

                $totalDecompressedSize += $size;
                if ($totalDecompressedSize > $maxDecompressedBytes) {
                    $validator->errors()->add('document', 'The XLSX decompressed size exceeds the 50 MB safety limit.');
                    return;
                }

                if ($compSize > 0 && ($size / $compSize) > $maxRatio && $size > 102400) {
                    $validator->errors()->add('document', 'The XLSX file exceeds safe compression ratio limits.');
                    return;
                }
            }

            if (! $hasContentTypes || ! $hasWorkbook) {
                $validator->errors()->add('document', 'The uploaded XLSX file lacks required spreadsheet structure (missing xl/workbook.xml or [Content_Types].xml).');
                return;
            }
        } finally {
            $zip->close();
        }
    }
}
