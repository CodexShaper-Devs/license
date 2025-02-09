<?php

namespace App\Providers;

use App\Events\LicenseActivated;
use App\Events\LicenseCreated;
use App\Events\LicenseDeactivated;
use App\Factories\LicenseFactory;
use Illuminate\Support\ServiceProvider;
use App\Interfaces\CurrentUserResolverInterface;
use App\Services\CurrentUserResolver;
use App\Interfaces\LicenseRepositoryInterface;
use App\Jobs\ProcessLicenseWebhook;
use App\Models\License;
use App\Repositories\LicenseRepository;
use App\Services\CurrentUserResolverService;
use App\Services\EncryptionService;
use App\Services\KeyManagementService;
use App\Services\LicenseService;
use App\Services\Marketplace\Verifiers\CustomVerifier;
use App\Services\Marketplace\Verifiers\EnvatoVerifier;
use App\Services\Marketplace\Verifiers\OtherMarketplaceVerifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LicenseManagerServiceProvider extends ServiceProvider
{
    private const TIMESTAMP = '2025-02-09 05:07:14';
    private const USER = 'maab16';

    public function register(): void
    {
        $this->app->singleton(KeyManagementService::class, function ($app) {
            return new KeyManagementService();
        });

        $this->app->singleton(EncryptionService::class, function ($app) {
            return new EncryptionService(
                $app->make(KeyManagementService::class)
            );
        });

        $this->app->singleton(LicenseService::class, function ($app) {
            return new LicenseService(
                $app->make(LicenseRepository::class),
                $app->make(EncryptionService::class),
                $app->make(KeyManagementService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->app['events']->listen(LicenseCreated::class, function ($event) {
            // Log to external service
            Log::channel('license')->info('License created', [
                'license_key' => $event->license->key,
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]);
        
            // Trigger webhooks if configured
            if (config('license.webhooks.enabled')) {
                dispatch(new ProcessLicenseWebhook('created', $event->broadcastWith()));
            }
        });
        
        $this->app['events']->listen(LicenseActivated::class, function ($event) {
            Log::channel('license')->info('License activated', [
                'license_key' => $event->license->key,
                'device' => $event->activation->device_identifier,
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]);
        
            if (config('license.webhooks.enabled')) {
                dispatch(new ProcessLicenseWebhook('activated', $event->broadcastWith()));
            }
        });
        
        $this->app['events']->listen(LicenseDeactivated::class, function ($event) {
            Log::channel('license')->info('License deactivated', [
                'license_key' => $event->license->key,
                'device' => $event->activation->device_identifier,
                'reason' => $event->reason,
                'timestamp' => self::TIMESTAMP,
                'user' => self::USER
            ]);
        
            if (config('license.webhooks.enabled')) {
                dispatch(new ProcessLicenseWebhook('deactivated', $event->broadcastWith()));
            }
        });
    }
}