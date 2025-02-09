<?php

namespace App\Events;

use App\Models\License;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public License $license,
        public string $oldUserId,
        public string $newUserId,
        public array $metadata = []
    ) {}
}