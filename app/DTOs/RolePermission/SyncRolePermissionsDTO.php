<?php

namespace App\DTOs\RolePermission;

class SyncRolePermissionsDTO
{
    /**
     * Propiedades inmutables del Data Transfer Object para la Sincronización.
     *
     * @param int $roleId
     * @param array<int> $permissionsIds
     */
    public function __construct(
        public readonly int $roleId,
        public readonly array $permissionsIds
    ) {}

    /**
     * Construye una instancia limpia del DTO a partir del Request validado.
     *
     * @param array $data
     * @return self
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            roleId: (int) $data['role_id'],
            // Aseguramos que todos los elementos internos del arreglo sean enteros puros
            permissionsIds: array_map('intval', $data['permissions_ids'] ?? [])
        );
    }

    /**
     * Convierte el DTO a la estructura requerida por el método sync() de Eloquent.
     *
     * @return array<int>
     */
    public function toArray(): array
    {
        return $this->permissionsIds;
    }
}