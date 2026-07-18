<?php

namespace App\Core\Tenants\Application\UseCases;

use App\Core\Tenants\Infrastructure\DTOs\CheckLimitsDTO;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus;
use Illuminate\Support\Facades\DB;
use Exception;

class ValidateTenantLimitsUseCase
{
    public function execute(CheckLimitsDTO $dto): array{
        // 1. Obtener el límite permitido en el plan del Tenant (Corregido tenants.plan_id)
        $licenseData = DB::table('tenant_apps')
            ->join('tenants', 'tenant_apps.tenant_id', '=', 'tenants.id')
            ->join('apps', 'tenant_apps.app_id', '=', 'apps.id')
            ->join('plans', 'tenants.plan_id', '=', 'plans.id') // ◄ CRUCIAL: Aquí cambiamos tenant_apps por tenants
            ->where('tenants.slug', $dto->tenantSlug)
            ->where('apps.slug', $dto->appSlug)
            ->select('plans.max_users', 'tenants.status')
            ->first();

        if (!$licenseData) {
            throw new Exception("No se encontró una licencia activa para el producto y cliente especificado.");
        }

        if ($licenseData->status !== AppSubscriptionStatus::ACTIVE->value && $licenseData->status !== AppSubscriptionStatus::TRIAL->value) {
            return [
                'allowed' => false,
                'reason' => 'SUBSCRIPTION_INACTIVE',
                'max_allowed' => 0,
                'current' => $dto->currentUsersCount
            ];
        }

        // 2. Validar cuota de usuarios activos enviados por el Satélite
        $allowed = $dto->currentUsersCount < (int)$licenseData->max_users;

        return [
            'allowed' => $allowed,
            'reason' => $allowed ? 'LIMITS_OK' : 'MAX_USERS_EXCEEDED',
            'max_allowed' => (int)$licenseData->max_users,
            'current' => $dto->currentUsersCount,
            'remaining' => max(0, (int)$licenseData->max_users - $dto->currentUsersCount)
        ];
    }
}
