<?php

namespace App\Core\Tenants\Infrastructure\DTOs;

class RegisterApiLogDTO
{
    public function __construct(
        public readonly ?int $tenantId,
        public readonly ?string $appSlug,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly string $endpoint,
        public readonly bool $is_success,
        public readonly ?string $failReason,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: isset($data['tenant_id']) ? (int)$data['tenant_id'] : null,
            appSlug: $data['app_slug'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            endpoint: $data['endpoint'],
            is_success: (bool)$data['is_success'],
            failReason: $data['fail_reason'] ?? null,
        );
    }
}
