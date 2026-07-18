<?php

namespace App\Http\Middleware;

use App\Core\Tenants\Domain\Contracts\TenantRepositoryInterface;
use App\Core\Tenants\Domain\Enums\TenantStatus;
use App\Core\Tenants\Domain\Enums\AppSubscriptionStatus;
use App\Core\Tenants\Infrastructure\Eloquent\TenantApiLogRepository;
use App\Core\Tenants\Infrastructure\DTOs\RegisterApiLogDTO;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\DB;

class TenantHandshakeMiddleware
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantApiLogRepository $logRepository
    ) {}

    public function handle(Request $request, Closure $next, string $appSlug): Response
    {
        $tenantSlug  = $request->header('X-Tenant-Slug');
        $secretToken = $request->header('X-Tenant-Token');

        $contextData = [
            'app_slug'   => $appSlug,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint'   => $request->fullUrl(),
            'tenant_id'  => null,
        ];

        $tenantId = DB::table('tenants')->where('slug', $tenantSlug)->value('id');
        if ($tenantId) {
            $contextData['tenant_id'] = $tenantId; // ¡Ahora sí lo guardamos aunque el token esté mal!
        }

        if (!$tenantSlug || !$secretToken) {
            $this->logFailure($contextData, 'INVALID_CREDENTIALS');
            return response()->json([
                'status'  => false,
                'message' => 'Cabeceras de integración ausentes o incompletas. Se requieren: X-Tenant-Slug y X-Tenant-Token.',
                'data'    => null
            ], 401);
        }

        $tenantData = $this->tenantRepository->verifyCredentials($tenantSlug, $secretToken, $appSlug);

        if (!$tenantData) {
            $this->logFailure($contextData, 'INVALID_CREDENTIALS');
            return response()->json([
                'status'  => false,
                'message' => 'Credenciales de integración corporativas inválidas para esta aplicación.',
                'data'    => null
            ], 401);
        }

        $contextData['tenant_id'] = $tenantData['tenant_id'];

        if (in_array($tenantData['tenant_status'], [TenantStatus::SUSPENDED->value, TenantStatus::CANCELLED->value])) {
            $this->logFailure($contextData, 'TENANT_INACTIVE');
            return response()->json([
                'status'  => false,
                'message' => "La cuenta de la empresa se encuentra temporalmente: {$tenantData['tenant_status']}.",
                'data'    => null
            ], 403);
        }

        $currentAppStatus = $tenantData['app_status'];

        if ($currentAppStatus !== AppSubscriptionStatus::ACTIVE->value && $currentAppStatus !== AppSubscriptionStatus::TRIAL->value) {
            $this->logFailure($contextData, 'SUBSCRIPTION_RESTRICTED');
            return response()->json([
                'status'  => false,
                'message' => 'El acceso a esta aplicación específica se encuentra desactivado.',
                'data'    => null
            ], 403);
        }

        if ($tenantData['expires_at'] && now()->greaterThan(\Illuminate\Support\Carbon::parse($tenantData['expires_at']))) {
            $this->logFailure($contextData, 'SUBSCRIPTION_RESTRICTED');
            return response()->json([
                'status'  => false,
                'message' => 'La licencia de uso para esta aplicación ha expirado de forma definitiva.',
                'data'    => null
            ], 403);
        }

        $this->logRepository->logExchange(RegisterApiLogDTO::fromArray(array_merge($contextData, [
            'is_success'  => true,
            'fail_reason' => null,
        ])));

        $request->attributes->set('current_tenant', $tenantData);

        return $next($request);
    }

    private function logFailure(array $context, string $reason): void
    {
        $this->logRepository->logExchange(RegisterApiLogDTO::fromArray(array_merge($context, [
            'is_success'  => false,
            'fail_reason' => $reason,
        ])));
    }
}
