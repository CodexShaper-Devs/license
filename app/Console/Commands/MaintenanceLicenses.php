<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessExpiredLicenses;
use App\Jobs\SendLicenseExpirationNotification;
use App\Models\License;

class MaintenanceLicenses extends Command
{
    protected $signature = 'licenses:maintenance';
    protected $description = 'Perform license maintenance tasks';

    public function handle()
    {
        // Process expired licenses
        ProcessExpiredLicenses::dispatch();

        // Send notifications for licenses expiring soon
        License::query()
            ->where('type', 'subscription')
            ->where('status', 'active')
            ->where('valid_until', '>', now())
            ->where('valid_until', '<', now()->addDays(7))
            ->each(function (License $license) {
                SendLicenseExpirationNotification::dispatch($license);
            });
    }
}