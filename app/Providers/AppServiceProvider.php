<?php

namespace App\Providers;

use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Core\Auth\Domain\Contracts\TenantAuthRepositoryInterface;
use App\Core\Auth\Infrastructure\Eloquent\AuthEloquentRepository;
use App\Core\Auth\Infrastructure\Eloquent\TenantAuthEloquentRepository;
use App\Core\Tenants\Domain\Contracts\TenantRepositoryInterface;
use App\Core\Tenants\Infrastructure\Eloquent\TenantEloquentRepository;
use App\Models\Sanctum\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TenantRepositoryInterface::class, TenantEloquentRepository::class);
        $this->app->bind(AuthRepositoryInterface::class, AuthEloquentRepository::class);
        $this->app->bind(TenantAuthRepositoryInterface::class, TenantAuthEloquentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
