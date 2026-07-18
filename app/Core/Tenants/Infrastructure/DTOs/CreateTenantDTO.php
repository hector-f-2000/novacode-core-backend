<?php

namespace App\Core\Tenants\Infrastructure\DTOs;

class CreateTenantDTO
{
    public function __construct(
        public readonly string $rut,
        public readonly string $razon_social,
        public readonly string $giro,
        public readonly string $address,
        public readonly string $slug,
        public readonly int $plan_id,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            rut: $data['rut'],
            razon_social: $data['razon_social'],
            giro: $data['giro'],
            address: $data['address'],
            slug: $data['slug'],
            plan_id: (int) $data['plan_id'],
        );
    }
}
