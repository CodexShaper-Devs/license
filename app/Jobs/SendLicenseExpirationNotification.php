<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\License;
use App\Notifications\LicenseExpirationNotification;

class SendLicenseExpirationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly License $license)
    {}

    public function handle()
    {
        $user = $this->license->user;
        $user->notify(new LicenseExpirationNotification($this->license));
    }
}