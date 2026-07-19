# INSTRUCCIONES DE DESARROLLO: INTEGRACIÓN DE AUDITORÍA EN HANDSHAKE MIDDLEWARE

## 🎯 Objetivo

Modificar el middleware `VerifyCorporateHandshake` para inyectar y ejecutar el repositorio `TenantApiLogRepository`. El sistema debe registrar un log histórico de cada intento de conexión (sea exitoso o fallido).

---

## 🛠️ Modificación del Middleware

El agente debe actualizar `app/Http/Middleware/VerifyCorporateHandshake.php` para seguir la siguiente lógica estructurada:

1. **Inyectar el Repositorio:** Utilizar el constructor del middleware o resolverlo mediante el contenedor de servicios (`app(TenantApiLogRepository::class)`).
2. **Estructura "Fail-Safe":** Inicializar las variables por defecto (`tenant_id = null`) para capturar la petición incluso si las credenciales fallan o el Tenant no existe.
3. **Registro de Salidas Tempranas:** Cada bloque `return response()->json(...)` de error debe disparar previamente el método `logExchange()`.

### Código de Referencia Esperado para el Middleware:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Core\Tenants\Infrastructure\Eloquent\TenantApiLogRepository;
use App\Core\Tenants\Infrastructure\DTOs\RegisterApiLogDTO;
use App\Core\Tenants\Domain\Enums\TenantStatus;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus;
use App\Core\Tenants\Infrastructure\Services\TenantCredentialService; // Ajustar a tu servicio real

class VerifyCorporateHandshake
{
    protected TenantCredentialService $credentialService;
    protected TenantApiLogRepository $logRepository;

    public function __construct(
        TenantCredentialService $credentialService,
        TenantApiLogRepository $logRepository
    ) {
        $this->credentialService = $credentialService;
        $this->logRepository = $logRepository;
    }

    public function handle(Request $request, Closure $next)
    {
        // 1. Capturar Headers e Información de Contexto
        $tenantSlug = $request->header('X-Tenant-Slug');
        $tenantToken = $request->header('X-Tenant-Token');
        $appSlug = $request->route('app_slug') ?? 'asist-go'; // O como captures el identificador

        $contextData = [
            'app_slug'   => $appSlug,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint'   => $request->fullUrl(),
            'tenant_id'  => null // Por defecto
        ];

        // 2. Buscar Credenciales
        $tenantData = $this->credentialService->verifyCredentials($tenantSlug, $tenantToken, $appSlug);

        if (!$tenantData) {
            $this->logFailure($contextData, 'INVALID_CREDENTIALS');
            return response()->json(['status' => false, 'message' => 'Credenciales corporativas inválidas.'], 403);
        }

        // Asignar el ID real una vez que sabemos quién es el Tenant
        $contextData['tenant_id'] = $tenantData['tenant_id'];

        // 3. Validar Estado del Tenant Global
        if ($tenantData['tenant_status'] !== TenantStatus::ACTIVE->value) {
            $this->logFailure($contextData, 'TENANT_INACTIVE');
            return response()->json(['status' => false, 'message' => 'La cuenta de la empresa se encuentra inactiva.'], 403);
        }

        // 4. Validar Estado de la Suscripción de la App (Tu Enum)
        $currentAppStatus = $tenantData['app_status'];
        if ($currentAppStatus !== AppSubscriptionStatus::ACTIVE->value && $currentAppStatus !== AppSubscriptionStatus::TRIAL->value) {
            $this->logFailure($contextData, 'SUBSCRIPTION_RESTRICTED');
            return response()->json(['status' => false, 'message' => 'La licencia para este producto no está activa.'], 403);
        }

        // 5. Todo Exitoso -> Registrar Log Positivo y Continuar
        $this->logRepository->logExchange(RegisterApiLogDTO::fromArray(array_merge($contextData, [
            'is_success'  => true,
            'fail_reason' => null
        ])));

        return $next($request);
    }

    private function logFailure(array $context, string $reason): void
    {
        $this->logRepository->logExchange(RegisterApiLogDTO::fromArray(array_merge($context, [
            'is_success'  => false,
            'fail_reason' => $reason
        ])));
    }
}
```
