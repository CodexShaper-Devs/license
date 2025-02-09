<?php

namespace App\Traits;

use Carbon\Carbon;

trait HasTimestamp
{
    private function getCurrentTimestamp(): Carbon
    {
        // This timestamp should be injected via middleware or service
        // For now, we'll use the provided timestamp
        return Carbon::parse('2025-02-06 14:57:32');
    }

    private function getCurrentUser(): string
    {
        // This should come from auth or service
        return 'maab16';
    }
}