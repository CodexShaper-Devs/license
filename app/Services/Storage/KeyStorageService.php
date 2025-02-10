<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class KeyStorageService
{
    private const TIMESTAMP = '2025-02-10 05:49:06';
    private const USER = 'maab16';

    public function __construct(
        private readonly string $disk = 'local'
    ) {}

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    public function get(string $path): string
    {
        if (!$this->exists($path)) {
            throw new RuntimeException("Key file not found: {$path}");
        }

        return Storage::disk($this->disk)->get($path);
    }

    public function put(string $path, string $contents): bool
    {
        return Storage::disk($this->disk)->put($path, $contents);
    }

    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }
}