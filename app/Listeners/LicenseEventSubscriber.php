<?php

namespace App\Listeners;

use App\Events\LicenseCreated;
use App\Events\LicenseActivated;
use App\Events\LicenseDeactivated;
use Illuminate\Events\Dispatcher;

class LicenseEventSubscriber
{
    public function handleLicenseCreated($event)
    {
        // Handle license creation
    }

    public function handleLicenseActivated($event)
    {
        // Handle license activation
    }

    public function handleLicenseDeactivated($event)
    {
        // Handle license deactivation
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen(
            LicenseCreated::class,
            [LicenseEventSubscriber::class, 'handleLicenseCreated']
        );

        $events->listen(
            LicenseActivated::class,
            [LicenseEventSubscriber::class, 'handleLicenseActivated']
        );

        $events->listen(
            LicenseDeactivated::class,
            [LicenseEventSubscriber::class, 'handleLicenseDeactivated']
        );
    }
}