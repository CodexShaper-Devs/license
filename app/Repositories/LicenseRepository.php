<?php

namespace App\Repositories;

use App\Events\LicenseActivated;
use App\Events\LicenseDeactivated;
use App\Exceptions\LicenseActivationException;
use App\Models\License;
use App\Models\LicenseLog;
use App\Interfaces\LicenseRepositoryInterface;
use App\Interfaces\CurrentUserResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class LicenseRepository implements LicenseRepositoryInterface
{
    public function __construct(
        private License $model,
        private CurrentUserResolverInterface $userResolver
    ) {}

    public function findByKey(string $key): ?License
    {
        return $this->model->where('key', $key)->first();
    }

    public function create(array $data): License
    {
        return DB::transaction(function () use ($data) {
            $license = $this->model->create($data);
            $this->logLicenseEvent($license, 'created', $data);
            return $license;
        });
    }

    public function activate(License $license, array $activationData): bool
    {
        return DB::transaction(function () use ($license, $activationData) {
            try {
                $existingActivation = $license->activations()
                    ->where('device_identifier', $activationData['device_identifier'])
                    ->first();

                if ($existingActivation && $existingActivation->is_active) {
                    throw new LicenseActivationException('Device is already activated');
                }

                $activation = $license->activations()->create([
                    'device_identifier' => $activationData['device_identifier'],
                    'device_name' => $activationData['device_name'],
                    'hardware_hash' => $activationData['hardware_hash'],
                    'ip_address' => $activationData['ip_address'] ?? $this->userResolver->getIp(),
                    'domain' => $activationData['domain'] ?? null,
                    'last_check_in' => now(),
                    'metadata' => $activationData['metadata'] ?? null,
                    'is_active' => true
                ]);

                $this->logLicenseEvent($license, 'activated', [
                    'activation_id' => $activation->id,
                    'device_identifier' => $activation->device_identifier,
                    'hardware_hash' => $activation->hardware_hash,
                    'ip_address' => $activation->ip_address,
                ]);

                event(new LicenseActivated($license, $activation));

                return true;

            } catch (\Exception $e) {
                throw new LicenseActivationException(
                    $e->getMessage(),
                    previous: $e
                );
            }
        });
    }

    public function deactivate(License $license, string $deviceIdentifier): bool
    {
        return DB::transaction(function () use ($license, $deviceIdentifier) {
            try {
                $activation = $license->activations()
                    ->where('device_identifier', $deviceIdentifier)
                    ->where('is_active', true)
                    ->first();

                if (!$activation) {
                    throw new LicenseActivationException('No active device found with this identifier');
                }

                $activation->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                    'deactivation_reason' => 'user_requested'
                ]);

                $this->logLicenseEvent($license, 'deactivated', [
                    'activation_id' => $activation->id,
                    'device_identifier' => $deviceIdentifier,
                    'hardware_hash' => $activation->hardware_hash,
                ]);

                event(new LicenseDeactivated($license, $activation));

                return true;

            } catch (\Exception $e) {
                throw new LicenseActivationException(
                    $e->getMessage(),
                    previous: $e
                );
            }
        });
    }

    public function logLicenseEvent(License $license, string $event, array $data = []): void
    {
        DB::transaction(function () use ($license, $event, $data) {
            LicenseLog::create([
                'license_id' => $license->id,
                'event_type' => $event,
                'event_data' => array_merge($data, [
                    'timestamp' => now()->toIso8601String(),
                    'ip_address' => $this->userResolver->getIp(),
                    'user_agent' => $this->userResolver->getUserAgent() ?? 'unknown',
                    'performed_by' => $this->userResolver->getId() ?? 'system'
                ]),
                'ip_address' => $this->userResolver->getIp(),
                'user_agent' => $this->userResolver->getUserAgent() ?? 'unknown',
            ]);

            $license->update([
                'last_activity_at' => now(),
                'last_activity_type' => $event
            ]);

            $this->cacheLatestEvents($license);
        });
    }

    public function getEventsByType(License $license, string $eventType, ?Carbon $since = null): Collection
    {
        $query = $license->logs()->where('event_type', $eventType);

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getLatestEvents(License $license, int $limit = 10): Collection
    {
        $cacheKey = "license_events:{$license->id}";

        if (cache()->has($cacheKey)) {
            return collect(cache()->get($cacheKey));
        }

        $events = $license->logs()
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        cache()->put($cacheKey, $events->toArray(), now()->addHours(24));

        return $events;
    }

    protected function cacheLatestEvents(License $license): void
    {
        $cacheKey = "license_events:{$license->id}";
        $latestEvents = $license->logs()
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->toArray();

        Cache::put($cacheKey, $latestEvents, now()->addHours(24));
    }
}