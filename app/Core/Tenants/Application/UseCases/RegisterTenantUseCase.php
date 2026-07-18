<?php

namespace App\Core\Tenants\Application\UseCases;

use App\Core\Tenants\Domain\Contracts\TenantRepositoryInterface;
use App\Core\Tenants\Infrastructure\DTOs\CreateTenantDTO;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class RegisterTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository
    ) {}

    public function execute(CreateTenantDTO $dto): array
    {
        if ($this->tenantRepository->findByRut($dto->rut)) {
            throw new Exception("La empresa con el RUT {$dto->rut} ya se encuentra registrada.");
        }

        $apiKey = 'nc_pub_' . Str::random(32);
        $plainSecret = Str::random(40);
        $hashedSecret = Hash::make($plainSecret);

        $expiresAt = now()->addDays(30)->toDateTimeString();

        /* Se guarda el hash (bcrypt), se devuelve el secreto plano una sola vez */
        return $this->tenantRepository->create($dto, $apiKey, $hashedSecret, $expiresAt, $plainSecret);
    }
}
