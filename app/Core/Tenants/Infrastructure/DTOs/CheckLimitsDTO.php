<?php

namespace App\Core\Tenants\Infrastructure\DTOs;

class CheckLimitsDTO
{
    public function __construct(
        public readonly string $tenantSlug,
        public readonly string $appSlug,
        public readonly int $currentUsersCount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tenantSlug: $data['tenant_slug'],
            appSlug: $data['app_slug'],
            currentUsersCount: (int) $data['current_users_count'],
        );
    }
}
