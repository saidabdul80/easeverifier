<?php

namespace App\Services\Verification;

class VerificationResult
{
    public function __construct(
        public bool $success,
        public ?array $data = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
        public ?int $responseTime = null,
        public ?int $providerUsed = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(array $data, int $responseTime = null, int $providerUsed = null): self
    {
        return new self(
            success: true,
            data: $data,
            responseTime: $responseTime,
            providerUsed: $providerUsed,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $message, string $code = null, int $responseTime = null): self
    {
        return new self(
            success: false,
            errorMessage: $message,
            errorCode: $code,
            responseTime: $responseTime,
        );
    }

    /**
     * Check if the verification was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Get the verification data.
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'response_time' => $this->responseTime,
        ];
    }
}

