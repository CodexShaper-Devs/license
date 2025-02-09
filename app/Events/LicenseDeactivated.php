<?php

namespace App\Events;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseDeactivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public License $license,
        public LicenseActivation $activation
    ) {}
}