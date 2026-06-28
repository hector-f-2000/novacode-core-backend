<?php

namespace App\DTOs\UserPermission;

class SyncUserPermissionsDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly array $permissionIds
    ) {}

    public static function fromRequest(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            permissionIds: array_map('intval', $data['permission_ids'] ?? [])
        );
    }

    public function toArray(): array
    {
        return $this->permissionIds;
    }
}
