<?php

namespace App\Interfaces;

use App\Models\License;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface LicenseRepositoryInterface
{
    public function findByKey(string $key): ?License;
    public function create(array $data): License;
    public function activate(License $license, array $activationData): bool;
    public function deactivate(License $license, string $deviceIdentifier): bool;
    public function logLicenseEvent(License $license, string $event, array $data = []): void;
    public function getEventsByType(License $license, string $eventType, ?Carbon $since = null): Collection;
    public function getLatestEvents(License $license, int $limit = 10): Collection;
}