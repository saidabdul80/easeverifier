<?php

namespace App\Http\Requests\Mcp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterOAuthClientRequest extends FormRequest
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
            'client_name' => ['nullable', 'string', 'max:100', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:100', 'required_without:client_name'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:5'],
            'redirect_uris.*' => [
                'required',
                'url',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! Str::startsWith($value, $this->allowedRedirectDomains())) {
                        $fail("The {$attribute} is not a permitted OAuth callback URL.");
                    }
                },
            ],
            'grant_types' => ['sometimes', 'array', 'min:1'],
            'grant_types.*' => ['string', 'in:authorization_code,refresh_token'],
            'response_types' => ['sometimes', 'array', 'min:1'],
            'response_types.*' => ['string', 'in:code'],
            'token_endpoint_auth_method' => ['sometimes', 'string', 'in:none'],
            'scope' => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $scopes = preg_split('/\s+/', trim((string) $value), flags: PREG_SPLIT_NO_EMPTY);

                    if ($scopes !== ['mcp:use']) {
                        $fail("The {$attribute} may only request the mcp:use scope.");
                    }
                },
            ],
        ];
    }

    /** @return list<string> */
    private function allowedRedirectDomains(): array
    {
        return collect(config('mcp.redirect_domains', []))
            ->filter(fn (mixed $domain): bool => is_string($domain) && $domain !== '')
            ->map(fn (string $domain): string => Str::finish($domain, '/'))
            ->values()
            ->all();
    }
}
