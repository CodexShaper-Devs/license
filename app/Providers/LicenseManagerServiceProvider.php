<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Interfaces\CurrentUserResolverInterface;
use App\Services\CurrentUserResolver;
use App\Interfaces\LicenseRepositoryInterface;
use App\Repositories\LicenseRepository;
use App\Services\CurrentUserResolverService;

class LicenseManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentUserResolverInterface::class, CurrentUserResolverService::class);
        
        $this->app->singleton(LicenseRepositoryInterface::class, function ($app) {
            return new LicenseRepository(
                new \App\Models\License(),
                $app->make(CurrentUserResolverInterface::class)
            );
        });
    }
}