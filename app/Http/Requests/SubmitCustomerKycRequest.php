<?php

namespace App\Http\Requests;

use App\Support\KycQuestionnaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitCustomerKycRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $answers = $this->input('answers');
        if (! is_array($answers)) {
            return;
        }

        foreach (KycQuestionnaire::sections(false) as $section) {
            foreach ($section['fields'] as $field) {
                $value = $answers[$field['key']] ?? null;
                if ($field['type'] === 'boolean' && in_array($value, [true, false, 1, 0, '1', '0'], true)) {
                    $answers[$field['key']] = in_array($value, [true, 1, '1'], true);
                }
            }
        }

        $this->merge(['answers' => $answers]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->user()?->customer;

        return $this->user()?->isCustomer() && $customer
            && ! $customer->hasApprovedKyc()
            && $customer->latestKycSubmission?->status() !== 'pending';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $business = $this->user()->customer->account_type === 'business';
        $fields = collect(KycQuestionnaire::sections($business))->pluck('fields')->flatten(1);
        $documents = KycQuestionnaire::documents($business);
        $previousDocuments = $this->user()->customer->latestKycSubmission?->documents ?? [];
        $rules = [
            'answers' => ['required', 'array:'.$fields->pluck('key')->implode(',')],
            'documents' => ['nullable', 'array:'.implode(',', array_column($documents, 'key'))],
            'declaration' => ['required', 'accepted'],
        ];

        foreach ($fields as $field) {
            $type = match ($field['type']) {
                'boolean' => 'boolean',
                'email' => 'email',
                'url' => 'url:http,https',
                default => 'string',
            };
            $rules['answers.'.$field['key']] = [$field['required'] ? 'required' : 'nullable', $type];

            if ($type !== 'boolean') {
                $rules['answers.'.$field['key']][] = 'max:'.($field['type'] === 'textarea' ? 2000 : 255);
            }

            if (isset($field['options'])) {
                $rules['answers.'.$field['key']][] = Rule::in($field['options']);
            }
        }

        foreach ($documents as $document) {
            $required = $document['required'] || ($document['key'] === 'operating_license' && $this->boolean('answers.requires_license'));
            $rules['documents.'.$document['key']] = [
                Rule::requiredIf($required && ! isset($previousDocuments[$document['key']])),
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240',
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return collect(KycQuestionnaire::sections($this->user()->customer->account_type === 'business'))
            ->pluck('fields')->flatten(1)->mapWithKeys(fn (array $field) => ['answers.'.$field['key'] => strtolower($field['label'])])->all();
    }

    public function messages(): array
    {
        return [
            'declaration.accepted' => 'Please confirm the declaration before submitting your KYC.',
            'documents.*.mimes' => 'Upload a PDF, JPG or PNG document.',
            'documents.*.max' => 'Each document must be 10 MB or smaller.',
        ];
    }
}
