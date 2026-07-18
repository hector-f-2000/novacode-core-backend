<?php

namespace App\Core\Auth\Domain\Contracts;

use App\Models\Tenant\TenantUser;
use DateTimeInterface;

interface TenantAuthRepositoryInterface
{
    public function findByEmail(string $email): ?TenantUser;

    public function createToken(TenantUser $tenantUser, string $tokenName, ?DateTimeInterface $expiresAt = null): string;

    public function revokeCurrentToken(TenantUser $tenantUser): bool;

    public function findTenantStatusByUserId(int $tenantUserId): ?string;
}
