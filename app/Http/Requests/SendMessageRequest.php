<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'min:1',
                'max:2000',
            ],

        ];
    }

    /**
     * Friendly field names
     */
    public function attributes(): array
    {
        return [
            'message' => 'message',
        ];
    }

    /**
     * Custom messages
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message.',
            'message.max' => 'Maximum message length is 2000 characters.',
        ];
    }

    /**
     * Sanitize input
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => trim(
                (string) $this->message
            ),
        ]);
    }
}
