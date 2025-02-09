<?php

namespace App\Exceptions;

use Exception;

class EnvatoValidationException extends Exception
{
    protected $purchaseCode;
    protected $validationErrors;

    public function __construct(
        string $message, 
        string $purchaseCode = '', 
        array $validationErrors = [], 
        int $code = 0
    ) {
        parent::__construct($message, $code);
        $this->purchaseCode = $purchaseCode;
        $this->validationErrors = $validationErrors;
    }

    public function getPurchaseCode(): string
    {
        return $this->purchaseCode;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function context(): array
    {
        return [
            'purchase_code' => $this->purchaseCode,
            'errors' => $this->validationErrors,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}