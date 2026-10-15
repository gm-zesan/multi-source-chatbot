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
        'text/plain',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'text/csv',
        'application/csv',
        'text/x-csv',
    ];

    /**
     * Allowed file extensions for MIME detection matching.
     */
    public const ALLOWED_EXTENSIONS = ['txt', 'pdf', 'docx', 'csv', 'xlsx', 'zip'];

    /**
     * Supported client extensions for user uploads.
     */
    public const SUPPORTED_CLIENT_EXTENSIONS = ['txt', 'pdf', 'docx', 'csv', 'xlsx'];

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

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'document' => [
                'required',
                'file',
                'max:10240', // 10MB in KB
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
            'document.required'  => 'Please select a document file to upload.',
            'document.file'      => 'The uploaded file is invalid.',
            'document.max'       => 'The document must not exceed 10 MB.',
            'document.mimes'     => 'Only .txt, .pdf, .docx, .csv, and .xlsx files are supported.',
            'document.mimetypes' => 'The detected file type is not supported or the file content is spoofed.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('document');
            if (! $file || ! $file->isValid()) {
                return;
            }

            $clientExt = strtolower($file->getClientOriginalExtension());
            if (! in_array($clientExt, self::SUPPORTED_CLIENT_EXTENSIONS, true)) {
                $validator->errors()->add('document', 'Only .txt, .pdf, .docx, .csv, and .xlsx files are supported.');
                return;
            }

            // Reject raw zip files masquerading as plain text, PDF, or CSV
            $detectedMime = $file->getMimeType();
            if (in_array($clientExt, ['txt', 'pdf', 'csv'], true) && $detectedMime === 'application/zip') {
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
