<?php

namespace App\Events;

use App\Models\License;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseSuspended
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public License $license,
        public string $reason,
        public ?array $metadata = []
    ) {}
}