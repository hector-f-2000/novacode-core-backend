# INSTRUCCIONES DE DESARROLLO: REFACTORIZACIÓN DE VALIDACIONES CON ENUMS DE DOMINIO

## 🎯 Objetivo

Modificar el Middleware de Handshake Corporativo y el Caso de Uso de Validación de Límites para sustituir las comparaciones con cadenas de texto fijas por los nuevos Enums nativos de PHP 8.3 (`TenantStatus` y `AppSubscriptionStatus`), blindando la seguridad del Core.

---

## 🛠️ 1. Modificaciones de Archivos Existentes

### Paso A: Refactorización del Caso de Uso de Límites

- **Ruta:** `app/Core/Tenants/Application/UseCases/ValidateTenantLimitsUseCase.php`
- **Cambio Crítico:** Importar el Enum `AppSubscriptionStatus` y validar el estado de la licencia utilizando su valor nativo (`value`).

El agente debe actualizar el método `execute` de la siguiente manera:

```php
<?php

namespace App\Core\Tenants\Application\UseCases;

use App\Core\Tenants\Infrastructure\DTOs\CheckLimitsDTO;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus; // 🔹 Importación del Enum
use Illuminate\Support\Facades\DB;
use Exception;

class ValidateTenantLimitsUseCase
{
    public function execute(CheckLimitsDTO $dto): array
    {
        // 1. Obtener el límite permitido en el plan del Tenant para esa App específica
        $licenseData = DB::table('tenant_apps')
            ->join('tenants', 'tenant_apps.tenant_id', '=', 'tenants.id')
            ->join('apps', 'tenant_apps.app_id', '=', 'apps.id')
            ->join('plans', 'tenants.plan_id', '=', 'plans.id')
            ->where('tenants.slug', $dto->tenantSlug)
            ->where('apps.slug', $dto->appSlug)
            ->select('plans.max_users', 'tenant_apps.status')
            ->first();

        if (!$licenseData) {
            throw new Exception("No se encontró una licencia activa para el producto y cliente especificado.");
        }

        // 🔹 ALINEACIÓN CON ENUM: Validar usando las opciones estrictas del dominio
        $statusTrial = AppSubscriptionStatus::TRIAL->value;
        $statusActive = AppSubscriptionStatus::ACTIVE->value;

        if ($licenseData->status !== $statusActive && $licenseData->status !== $statusTrial) {
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
Paso B: Refactorización del Middleware de Handshake
Ruta: app/Http/Middleware/VerifyCorporateHandshake.php

Cambio Crítico: Asegurar que cuando el middleware verifique el estado del Tenant global y de la aplicación en la base de datos, use las llaves de los Enums (TenantStatus::ACTIVE->value y AppSubscriptionStatus::ACTIVE->value / TRIAL->value).

El agente debe localizar la sección donde se valida la query de credenciales en el Middleware y estructurarla de la siguiente manera:

PHP
// ... Lógica inicial de captura de cabeceras en el Middleware ...

use App\Core\Tenants\Domain\Enums\TenantStatus;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus;

// Dentro del método handle(), cuando se verifica la existencia y estados en la BD:
$corporateData = DB::table('tenant_apps')
    ->join('tenants', 'tenant_apps.tenant_id', '=', 'tenants.id')
    ->join('apps', 'tenant_apps.app_id', '=', 'apps.id')
    ->where('tenants.slug', $tenantSlug)
    ->where('apps.slug', $appIdentifier)
    ->where('tenant_apps.api_secret_token', $tenantToken)
    ->select(
        'tenants.status as tenant_status',
        'tenant_apps.status as app_status',
        'tenant_apps.expires_at'
    )
    ->first();

if (!$corporateData) {
    return response()->json([
        'status' => false,
        'message' => 'Credenciales de integración corporativas inválidas para esta aplicación.'
    ], 403);
}

// 🔹 VALIDACIÓN ESTRICTA CON ENUMS DE DOMINIO
if ($corporateData->tenant_status !== TenantStatus::ACTIVE->value) {
    return response()->json([
        'status' => false,
        'message' => 'La cuenta de la empresa cliente se encuentra suspendida o inactiva a nivel global.'
    ], 403);
}

$allowedAppStatuses = [AppSubscriptionStatus::ACTIVE->value, AppSubscriptionStatus::TRIAL->value];
if (!in_array($corporateData->app_status, $allowedAppStatuses)) {
    return response()->json([
        'status' => false,
        'message' => 'La licencia de acceso para este producto específico no está activa.'
    ], 403);
}

// ... Continuar con la validación de la fecha de expiración y el flujo normal ...
```
