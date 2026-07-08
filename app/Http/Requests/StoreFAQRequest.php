<?php

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
            'category_id' => 'nullable|exists:faq_categories,id',
            'question'    => 'required|string|max:500',
            'answer'      => 'required|string',
            'priority'    => 'nullable|integer|min:0|max:999999',
            'is_active'   => 'nullable|boolean',
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
            'question.required' => 'The FAQ question is required.',
            'question.max'      => 'The question must not exceed 500 characters.',
            'answer.required'   => 'The FAQ answer is required.',
            'category_id.exists'=> 'The selected category does not exist.',
            'priority.integer'  => 'Priority must be a valid number.',
        ];
    }
}
