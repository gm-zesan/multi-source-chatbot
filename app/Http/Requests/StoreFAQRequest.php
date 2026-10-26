<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFAQRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'workspace_id'  => ['nullable', 'integer', 'exists:workspaces,id'],
            'document_type' => 'nullable|string|max:100',
            'question'      => 'required|string|max:500',
            'answer'        => ['required', 'string', 'max:5000', new \App\Rules\MaxWords(250)],
            'priority'      => 'nullable|integer|min:0|max:999999',
            'is_active'     => 'nullable|boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required'    => 'The FAQ question is required.',
            'question.max'         => 'The question must not exceed 500 characters.',
            'answer.required'      => 'The FAQ answer is required.',
            'priority.integer'     => 'Priority must be a valid number.',
            'workspace_id.exists'  => 'The selected workspace does not exist.',
            'workspace_id.integer' => 'The workspace ID must be a valid integer.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\User|null $user */
            $user = $this->user();
            if (! $user) {
                return;
            }

            $isSuperAdmin = $user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value);

            if ($isSuperAdmin) {
                $wsId = $this->input('workspace_id') ?? $user->workspace_id;
                if (! $wsId) {
                    $validator->errors()->add('workspace_id', 'No active workspace selected or found for superadmin context.');
                }
            } else {
                if ($this->filled('workspace_id')) {
                    $requestedWsId = (int) $this->input('workspace_id');
                    if ($requestedWsId !== (int) $user->workspace_id) {
                        $validator->errors()->add('workspace_id', 'Unauthorized workspace selection. Users may only target their assigned workspace.');
                    }
                }
            }
        });
    }
}
