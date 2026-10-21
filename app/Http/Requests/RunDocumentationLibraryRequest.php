<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunDocumentationLibraryRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'portal' => ['required', 'string', 'json', 'max:20000'],
            'result' => ['required', 'string', 'json', 'max:20000'],
            'adapter' => ['required', 'string', 'json', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Provide the example input before running it.',
            '*.json' => 'Use valid JSON in this input.',
            '*.max' => 'Keep each example input below 20,000 characters.',
        ];
    }
}
