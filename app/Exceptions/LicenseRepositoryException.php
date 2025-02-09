<?php

namespace App\Exceptions;

use Exception;

class LicenseRepositoryException extends Exception
{
    protected $data;

    public function __construct(string $message, int $code = 0, \Throwable $previous = null, array $data = [])
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function context(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'data' => $this->getData(),
            'timestamp' => '2025-02-09 05:01:07',
            'user' => 'maab16'
        ];
    }
}