<?php

namespace App\Services\ResultPins;

use RuntimeException;

class ResultPinProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        protected ?string $providerCode = null,
        protected ?array $providerResponse = null,
    ) {
        parent::__construct($message);
    }

    public function providerCode(): ?string
    {
        return $this->providerCode;
    }

    public function providerResponse(): ?array
    {
        return $this->providerResponse;
    }
}
