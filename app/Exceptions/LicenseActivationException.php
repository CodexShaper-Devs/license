<?php

namespace App\Exceptions;

use Exception;
use App\Models\License;
use App\Models\LicenseActivation;

class LicenseActivationException extends Exception
{
    protected ?License $license = null;
    protected ?LicenseActivation $activation = null;
    protected array $context = [];

    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?License $license = null,
        ?LicenseActivation $activation = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->license = $license;
        $this->activation = $activation;
        $this->context = $context;
    }

    public function getLicense(): ?License
    {
        return $this->license;
    }

    public function getActivation(): ?LicenseActivation
    {
        return $this->activation;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function context(): array
    {
        return [
            'license_key' => $this->license?->key,
            'device_identifier' => $this->activation?->device_identifier,
            'context' => $this->context,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}