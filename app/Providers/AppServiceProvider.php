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

        $privateKey = config('sso.private_key');
        if ($privateKey !== '' && $privateKey !== null) {
            $decoded = base64_decode($privateKey, true);
            if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                throw new \RuntimeException(
                    'SSO_PRIVATE_KEY inválida: se esperaban ' . SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
                    . ' bytes decodificados, se encontraron '
                    . ($decoded === false ? 0 : strlen($decoded))
                    . '. Verifica que la variable en .env no tenga espacios, saltos de línea, ni esté truncada.'
                );
            }
        }
    }
}
