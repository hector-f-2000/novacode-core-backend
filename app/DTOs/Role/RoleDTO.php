<?php

namespace App\DTOs\Role;

class RoleDTO
{
    /**
     * Propiedades inmutables del Data Transfer Object.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $display_name,
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
            display_name: trim($data['display_name']),
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
            'display_name' => $this->display_name,
            'guard_name'   => $this->guard_name,
        ];
    }
}