<?php

namespace App\Events;

use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class LicenseDeactivated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private const TIMESTAMP = '2025-02-09 05:07:14';
    private const USER = 'maab16';

    public function __construct(
        public readonly License $license,
        public readonly LicenseActivation $activation,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        return ['licenses'];
    }

    public function broadcastAs(): string
    {
        return 'license.deactivated';
    }

    public function broadcastWith(): array
    {
        return [
            'license' => [
                'key' => $this->license->key,
                'status' => $this->license->status
            ],
            'activation' => [
                'device_identifier' => $this->activation->device_identifier,
                'device_name' => $this->activation->device_name,
                'domain' => $this->activation->domain,
                'deactivated_at' => $this->activation->deactivated_at->toIso8601String(),
                'reason' => $this->reason
            ],
            'metadata' => array_merge($this->metadata, [
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER,
                'environment' => config('app.env')
            ])
        ];
    }
}