<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCustomerKycRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['rejection_reason.required_if' => 'Give the customer a reason so they can correct their submission.'];
    }
}
