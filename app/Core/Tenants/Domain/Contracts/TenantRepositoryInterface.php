<?php

namespace App\Core\Tenants\Domain\Contracts;

use App\Core\Tenants\Infrastructure\DTOs\CreateTenantDTO;

interface TenantRepositoryInterface
{
    /**
     * Guarda un nuevo Tenant con sus tokens de seguridad ya generados.
     * $secretToken debe ir hasheado (bcrypt) para almacenamiento seguro.
     * $plainSecret se retorna en la respuesta para mostrarlo una sola vez.
     */
    public function create(CreateTenantDTO $dto, string $apiKey, string $secretToken, string $expiresAt, string $plainSecret): array;
    
    public function findByRut(string $rut): ?array;
    
    public function updateStatus(int $id, string $status): bool;

    /**
     * Busca un tenant activo y verifica si tiene acceso a una app específica.
     */
    public function verifyCredentials(string $slug, string $secretToken, string $appSlug): ?array;
}