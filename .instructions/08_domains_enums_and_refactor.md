# INSTRUCCIONES DE DESARROLLO: CENTRALIZACIÓN DE ESTADOS MEDIANTE ENUMS DE DOMINIO

## 🎯 Objetivo

Eliminar las cadenas de texto sueltas (strings mágicos) en las validaciones y centralizar todos los estados posibles para Tenants y Suscripciones utilizando Backed Enums de PHP 8.3 dentro de la capa de Dominio. Además, se creará un endpoint público del Core para listar estos estados al frontend.

---

## 📁 1. Estructura de Carpetas a Asegurar

El agente debe ubicar los Enums dentro del Dominio de Tenants:

```text
app/
└── Core/
    └── Tenants/
        └── Domain/
            └── Enums/          # 🔹 NUEVA CARPETA
                ├── TenantStatus.php
                └── AppSubscriptionStatus.php
🛠️ 2. Creación de Enums de PHP 8.3
Paso A: Enum para Estados de Empresa (Tenant)
Ruta: app/Core/Tenants/Domain/Enums/TenantStatus.php

PHP
<?php

namespace App\Core\Tenants\Domain\Enums;

enum TenantStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    /**
     * Retorna un mapa clave-valor amigable para el frontend (React PrimeReact).
     */
    public static function getFormOptions(): array
    {
        return array_map(fn($status) => [
            'label' => match($status) {
                self::ACTIVE => 'Activo',
                self::SUSPENDED => 'Suspendido Global',
                self::CANCELLED => 'Cancelado / Inactivo',
            },
            'value' => $status->value
        ], self::cases());
    }
}
Paso B: Enum para Estados de Licencia/Suscripción (App)
Ruta: app/Core/Tenants/Domain/Enums/AppSubscriptionStatus.php

PHP
<?php

namespace App\Core\Tenants\Domain\Enums;

enum AppSubscriptionStatus: string
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case EXPIRED = 'expired';

    /**
     * Retorna un mapa clave-valor amigable para el frontend (React PrimeReact).
     */
    public static function getFormOptions(): array
    {
        return array_map(fn($status) => [
            'label' => match($status) {
                self::TRIAL => 'Período de Prueba',
                self::ACTIVE => 'Suscripción Activa',
                self::SUSPENDED => 'Suscripción Suspendida',
                self::EXPIRED => 'Suscripción Vencida',
            },
            'value' => $status->value
        ], self::cases());
    }
}
🎮 3. Endpoint de Utilidad para el Frontend (React)
Para que tu panel en React pueda pintar selectores de estados dinámicos sin hardcodear nada en el front, crearemos un controlador de utilidades.

Ruta: app/Http/Controllers/Api/V1/Core/CatalogController.php

PHP
<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Core\Tenants\Domain\Enums\TenantStatus;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    /**
     * Retorna todos los catálogos de estados disponibles en el sistema Core.
     */
    public function getStatuses(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Catálogo de estados del sistema recuperado con éxito.',
            'data' => [
                'tenant_statuses' => TenantStatus::getFormOptions(),
                'app_subscription_statuses' => AppSubscriptionStatus::getFormOptions()
            ]
        ], 200);
    }
}
🔐 4. Registro de Ruta en routes/api.php
Agrega la ruta de consulta de catálogos dentro del grupo v1/core:

PHP
Route::prefix('v1/core')->group(function () {
    // Endpoint para que el Frontend de React consulte los estados disponibles en formularios
    Route::get('/catalogs/statuses', [\App\Http\Controllers\Api\V1\Core\CatalogController::class, 'getStatuses']);

    // Grupo protegido por Handshake (Rutas existentes...)
    Route::middleware([\App\Http\Middleware\VerifyCorporateHandshake::class])->group(function () {
        Route::post('/license/check-limits', [\App\Http\Controllers\Api\V1\Core\LicenseController::class, 'checkLimits']);
    });
});
```
