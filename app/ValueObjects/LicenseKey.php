<?php

namespace App\ValueObjects;

use Illuminate\Support\Str;

class LicenseKey
{
    private string $value;

    public function __construct(string $value = null)
    {
        $this->value = $value ?? $this->generate();
    }

    public function toString(): string
    {
        return $this->value;
    }

    protected function generate(): string
    {
        return implode('-', [
            Str::random(4),
            Str::random(4),
            Str::random(4),
            Str::random(4)
        ]);
    }

    public function validate(): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $this->value);
    }
}