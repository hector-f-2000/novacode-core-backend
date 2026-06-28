<?php

namespace App\DTOs\Permission;

class PermissionDTO
{
    /**
     * Propiedades inmutables del Data Transfer Object para Permisos.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $guard_name = 'web'
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
            name: trim($data['name']),
            guard_name: trim($data['guard_name'] ?? 'web')
        );
    }

    /**
     * Convierte el DTO a un array plano listo para persistir en Eloquent.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name'         => $this->name,
            'guard_name'   => $this->guard_name,
        ];
    }
}