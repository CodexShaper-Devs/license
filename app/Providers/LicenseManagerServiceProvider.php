<?php

namespace App\Providers;

use App\Console\Commands\InitializeLicenseKeys;
use App\Console\Commands\VerifySystemRequirements;
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
use App\Services\EnvatoService;
use App\Services\KeyManagementService;
use App\Services\LicenseActivationService;
use App\Services\LicenseDeactivationService;
use App\Services\LicenseDomainService;
use App\Services\LicenseSecurityService;
use App\Services\LicenseService;
use App\Services\Marketplace\Verifiers\CustomVerifier;
use App\Services\Marketplace\Verifiers\EnvatoVerifier;
use App\Services\Marketplace\Verifiers\OtherMarketplaceVerifier;
use App\Services\Storage\KeyStorageService;
use App\Services\Validators\DomainValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseManagerServiceProvider extends ServiceProvider
{
    private const TIMESTAMP = '2025-02-09 05:07:14';
    private const USER = 'maab16';

    public function register(): void
    {
        $this->app->singleton(KeyStorageService::class, function ($app) {
            return new KeyStorageService('local');
        });

        $this->app->singleton(DomainValidator::class, function ($app) {
            return new DomainValidator();
        });

        $this->app->singleton(LicenseDomainService::class, function ($app) {
            return new LicenseDomainService(
                $app->make(DomainValidator::class),
                $app->make(LicenseSecurityService::class)
            );
        });

        $this->app->singleton(LicenseActivationService::class, function ($app) {
            return new LicenseActivationService(
                $app->make(LicenseDomainService::class),
                $app->make(LicenseSecurityService::class)
            );
        });

        $this->app->singleton(LicenseDeactivationService::class, function ($app) {
            return new LicenseDeactivationService();
        });

        $this->app->singleton(KeyManagementService::class, function ($app) {
            return new KeyManagementService(
                $app->make(KeyStorageService::class)
            );
        });

        $this->app->singleton(EncryptionService::class, function ($app) {
            return new EncryptionService(
                $app->make(KeyManagementService::class)
            );
        });

        $this->app->singleton(EnvatoService::class, function ($app) {
            return new EnvatoService(
                'envato', 
                $app->make(Http::class)
            );
        });

        $this->app->singleton(LicenseRepository::class, function ($app) {
            return new LicenseRepository();
        });

        $this->app->singleton(LicenseService::class, function ($app) {
            return new LicenseService(
                $app->make(LicenseRepository::class),
                $app->make(LicenseSecurityService::class),
                $app->make(EnvatoService::class),
                $app->make(EncryptionService::class),
                $app->make(LicenseActivationService::class),
                $app->make(LicenseDeactivationService::class),
            );
        });

        // Register commands
        $this->commands([
            InitializeLicenseKeys::class,
            VerifySystemRequirements::class,
            // \App\Console\Commands\VerifyLicenseSetup::class,
        ]);
    }

    public function boot(): void
    {
       
    }
}