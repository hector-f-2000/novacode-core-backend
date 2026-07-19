# INSTRUCCIONES DE DESARROLLO: CONTROL DE LÍMITES Y CONTROLADOR DE LICENCIAS (CORE)

## 🎯 Objetivo

Implementar los endpoints de control de cuotas corporativas (`max_users`) y actualización de estados de suscripción para las aplicaciones inquilinas (Tenants), manteniendo el patrón de Clean Architecture y aislamiento de lógica en el Core.

---

## 🛠️ 1. Especificación de Archivos a Crear

### Paso A: DTO de Validación de Límites

- **Ruta:** `app/Core/Tenants/Infrastructure/DTOs/CheckLimitsDTO.php`

```php
<?php

namespace App\Core\Tenants\Infrastructure\DTOs;

class CheckLimitsDTO
{
    public string $tenantSlug;
    public string $appSlug;
    public int $currentUsersCount;

    public function __construct(array $data)
    {
        $this->tenantSlug = $data['tenant_slug'];
        $this->appSlug = $data['app_slug'];
        $this->currentUsersCount = (int)$data['current_users_count'];
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
Paso B: Caso de Uso para Validación de Límites (Control de Cuotas)
Ruta: app/Core/Tenants/Application/UseCases/ValidateTenantLimitsUseCase.php

PHP
<?php

namespace App\Core\Tenants\Application\UseCases;

use App\Core\Tenants\Infrastructure\DTOs\CheckLimitsDTO;
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
            ->join('plans', 'tenant_apps.plan_id', '=', 'plans.id')
            ->where('tenants.slug', $dto->tenantSlug)
            ->where('apps.slug', $dto->appSlug)
            ->select('plans.max_users', 'tenant_apps.status')
            ->first();

        if (!$licenseData) {
            throw new Exception("No se encontró una licencia activa para el producto y cliente especificado.");
        }

        if ($licenseData->status !== 'active' && $licenseData->status !== 'trial') {
            return [
                'allowed' => false,
                'reason' => 'SUBSCRIPTION_INACTIVE',
                'max_allowed' => 0,
                'current' => $dto->currentUsersCount
            ];
        }

        // 2. Validar cuota de usuarios activos enviados por el Satélite
        $allowed = $dto->currentUsersCount < $licenseData->max_users;

        return [
            'allowed' => $allowed,
            'reason' => $allowed ? 'LIMITS_OK' : 'MAX_USERS_EXCEEDED',
            'max_allowed' => (int)$licenseData->max_users,
            'current' => $dto->currentUsersCount,
            'remaining' => max(0, (int)$licenseData->max_users - $dto->currentUsersCount)
        ];
    }
}
Paso C: Controlador de Gestión de Licencias de la API Core
Ruta: app/Http/Controllers/Api/V1/Core/LicenseController.php

Nota: Este controlador va protegido por el middleware corporativo de Handshake que creamos ayer.

PHP
<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Core\Tenants\Application\UseCases\ValidateTenantLimitsUseCase;
use App\Core\Tenants\Infrastructure\DTOs\CheckLimitsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class LicenseController extends Controller
{
    private ValidateTenantLimitsUseCase $validateLimitsUseCase;

    public function __construct(ValidateTenantLimitsUseCase $validateLimitsUseCase)
    {
        $this->validateLimitsUseCase = $validateLimitsUseCase;
    }

    /**
     * Verifica si el tenant satélite puede crear más recursos según su plan.
     */
    public function checkLimits(Request $request): JsonResponse
    {
        try {
            // Capturar datos cruzados desde los encabezados del Handshake y el body
            $dto = CheckLimitsDTO::fromArray([
                'tenant_slug' => $request->header('X-Tenant-Slug'),
                'app_slug' => $request->header('X-App-Identifier'),
                'current_users_count' => $request->input('current_users_count', 0)
            ]);

            $result = $this->validateLimitsUseCase->execute($dto);

            return response()->json([
                'status' => true,
                'message' => 'Validación de cuotas procesada con éxito.',
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }
}
🔐 2. Inclusión de Rutas en routes/api.php
El agente debe registrar el nuevo endpoint de control de cuotas dentro del grupo versionado y protegido por el middleware de validación corporativa de la siguiente manera:

PHP
Route::prefix('v1/core')->group(function () {
    // Grupo protegido por Handshake Inter-App corporativo
    Route::middleware([\App\Http\Middleware\VerifyCorporateHandshake::class])->group(function () {
        Route::get('/asist-go/check-connection', function() {
            return response()->json(['status' => true, 'message' => 'Handshake exitoso.']);
        });

        // 🔹 NUEVO: Validación de límites de usuarios para apps satélites
        Route::post('/license/check-limits', [\App\Http\Controllers\Api\V1\Core\LicenseController::class, 'checkLimits']);
    });
});
```
