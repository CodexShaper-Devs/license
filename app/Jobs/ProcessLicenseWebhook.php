<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

class ProcessLicenseWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TIMESTAMP = '2025-02-09 05:08:22';
    private const USER = 'maab16';

    public $tries = 3;
    public $backoff = [60, 300, 600];

    public function __construct(
        private readonly string $event,
        private readonly array $payload
    ) {}

    public function handle(): void
    {
        $webhooks = config('license.webhooks.endpoints', []);
        
        foreach ($webhooks as $webhook) {
            try {
                $deliveryId = (string) Str::uuid();
                $signature = $this->generateSignature($webhook['secret'], $this->payload);

                $response = Http::withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Delivery' => $deliveryId,
                    'X-Webhook-Event' => $this->event,
                    'User-Agent' => 'License-Manager/1.0'
                ])->post($webhook['url'], array_merge($this->payload, [
                    'event' => $this->event,
                    'delivery_id' => $deliveryId,
                    'timestamp' => self::TIMESTAMP
                ]));

                $this->logWebhookDelivery($webhook, $deliveryId, $response);
            } catch (\Exception $e) {
                report($e);
                $this->logWebhookFailure($webhook, $deliveryId ?? null, $e);
            }
        }
    }

    private function generateSignature(string $secret, array $payload): string
    {
        $payload = json_encode($payload);
        return hash_hmac('sha256', $payload, $secret);
    }

    private function logWebhookDelivery(array $webhook, string $deliveryId, $response): void
    {
        // WebhookDelivery::create([
        //     'uuid' => $deliveryId,
        //     'event' => $this->event,
        //     'url' => $webhook['url'],
        //     'payload' => $this->payload,
        //     'response_code' => $response->status(),
        //     'response_body' => $response->body(),
        //     'success' => $response->successful(),
        //     'created_at' => self::TIMESTAMP,
        //     'created_by' => self::USER
        // ]);
    }

    private function logWebhookFailure(array $webhook, ?string $deliveryId, \Exception $exception): void
    {
        // WebhookDelivery::create([
        //     'uuid' => $deliveryId ?? (string) Str::uuid(),
        //     'event' => $this->event,
        //     'url' => $webhook['url'],
        //     'payload' => $this->payload,
        //     'response_code' => 0,
        //     'response_body' => $exception->getMessage(),
        //     'success' => false,
        //     'error' => [
        //         'message' => $exception->getMessage(),
        //         'code' => $exception->getCode(),
        //         'trace' => $exception->getTraceAsString()
        //     ],
        //     'created_at' => self::TIMESTAMP,
        //     'created_by' => self::USER
        // ]);
    }
}