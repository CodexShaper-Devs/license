<?php

namespace App\Exceptions;

use Exception;
use App\Models\License;

class LicenseValidationException extends Exception
{
    protected ?License $license;
    protected array $context;

    public function __construct(
        string $message,
        ?License $license = null,
        array $context = [],
        int $code = 0
    ) {
        parent::__construct($message, $code);
        $this->license = $license;
        $this->context = $context;
    }

    public function getLicense(): ?License
    {
        return $this->license;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function context(): array
    {
        return [
            'license_key' => $this->license?->key,
            'validation_context' => $this->context,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}