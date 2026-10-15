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
        'text/csv',
        'application/csv',
        'text/x-csv',
    ];

    /**
     * Allowed file extensions.
     */
    public const ALLOWED_EXTENSIONS = ['txt', 'pdf', 'docx', 'csv'];

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
            'document.mimes'     => 'Only .txt, .pdf, .docx, and .csv files are supported.',
            'document.mimetypes' => 'The detected file type is not supported or the file content is spoofed.',
        ];
    }
}
