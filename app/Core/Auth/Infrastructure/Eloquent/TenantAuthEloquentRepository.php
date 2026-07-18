<?php

namespace App\Core\Auth\Infrastructure\Eloquent;

use App\Core\Auth\Domain\Contracts\TenantAuthRepositoryInterface;
use App\Models\Tenant\TenantUser;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class TenantAuthEloquentRepository implements TenantAuthRepositoryInterface
{
    public function findByEmail(string $email): ?TenantUser
    {
        return TenantUser::where('email', $email)->first();
    }

    public function createToken(TenantUser $tenantUser, string $tokenName, ?DateTimeInterface $expiresAt = null): string
    {
        return $tenantUser->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;
    }

    public function revokeCurrentToken(TenantUser $tenantUser): bool
    {
        return $tenantUser->currentAccessToken()?->delete() ?? false;
    }

    public function findTenantStatusByUserId(int $tenantUserId): ?string
    {
        return DB::table('tenant_users')
            ->join('tenants', 'tenants.id', '=', 'tenant_users.tenant_id')
            ->where('tenant_users.id', $tenantUserId)
            ->value('tenants.status');
    }
}
