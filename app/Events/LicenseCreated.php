<?php

namespace App\Events;

use App\Models\License;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class LicenseCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private const TIMESTAMP = '2025-02-09 05:07:14';
    private const USER = 'maab16';

    public function __construct(
        public readonly License $license,
        public readonly array $metadata = []
    ) {}

    public function broadcastOn(): array
    {
        return ['licenses'];
    }

    public function broadcastAs(): string
    {
        return 'license.created';
    }

    public function broadcastWith(): array
    {
        return [
            'license' => [
                'key' => $this->license->key,
                'source' => $this->license->source,
                'type' => $this->license->type,
                'status' => $this->license->status,
                'seats' => $this->license->seats,
                'features' => $this->license->features,
                'valid_until' => $this->license->valid_until?->toIso8601String()
            ],
            'metadata' => array_merge($this->metadata, [
                'created_at' => self::TIMESTAMP,
                'created_by' => self::USER,
                'environment' => config('app.env')
            ])
        ];
    }
}