<?php

namespace App\Core\Tenants\Infrastructure\Eloquent;

use App\Core\Tenants\Domain\Contracts\TenantRepositoryInterface;
use App\Core\Tenants\Infrastructure\DTOs\CreateTenantDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantEloquentRepository implements TenantRepositoryInterface
{
    public function create(CreateTenantDTO $dto, string $apiKey, string $secretToken, string $expiresAt, string $plainSecret): array
    {
        $id = DB::table('tenants')->insertGetId([
            'rut' => $dto->rut,
            'razon_social' => $dto->razon_social,
            'giro' => $dto->giro,
            'address' => $dto->address,
            'slug' => $dto->slug,
            'plan_id' => $dto->plan_id,
            'api_key_client' => $apiKey,
            'api_secret_token' => $secretToken,
            'status' => 'trial',
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /* api_secret_token en la respuesta es el secreto en texto plano (se muestra una sola vez) */
        return [
            'id' => $id,
            'rut' => $dto->rut,
            'api_key_client' => $apiKey,
            'api_secret_token' => $plainSecret ?? $secretToken,
            'expires_at' => $expiresAt,
            'status' => 'trial'
        ];
    }

    public function findByRut(string $rut): ?array
    {
        $tenant = DB::table('tenants')->where('rut', $rut)->first();
        return $tenant ? (array)$tenant : null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        return DB::table('tenants')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now()
        ]) > 0;
    }

    public function verifyCredentials(string $slug, string $secretToken, string $appSlug): ?array
    {
        $tenant = DB::table('tenants')
            ->join('tenant_apps', 'tenants.id', '=', 'tenant_apps.tenant_id')
            ->join('apps', 'apps.id', '=', 'tenant_apps.app_id')
            ->where('tenants.slug', $slug)
            ->where('apps.slug', $appSlug)
            ->select(
                'tenants.id as tenant_id',
                'tenants.razon_social',
                'tenants.status as tenant_status',
                'tenants.expires_at',
                'tenants.api_secret_token',
                'apps.status as app_status'
            )
            ->first();

        if (!$tenant) {
            return null;
        }

        if (!Hash::check($secretToken, $tenant->api_secret_token)) {
            return null;
        }

        return [
            'tenant_id'    => $tenant->tenant_id,
            'razon_social' => $tenant->razon_social,
            'tenant_status' => $tenant->tenant_status,
            'expires_at'   => $tenant->expires_at,
            'app_status'     => $tenant->app_status,
            'sso_public_key' => config('sso.public_key_pem'),
        ];
    }
}