<?php

namespace App\Http\Controllers\Api\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\RegisterTenantRequest;
use App\Http\Requests\Tenants\StoreTenantOwnerRequest;
use App\Core\Tenants\Application\UseCases\RegisterTenantUseCase;
use App\Core\Tenants\Application\UseCases\CreateTenantOwnerUseCase;
use App\Core\Tenants\Infrastructure\Eloquent\Tenant;
use App\Core\Tenants\Infrastructure\DTOs\CreateTenantDTO;
use App\Models\Tenant\TenantUser;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class TenantController extends Controller
{
    public function __construct(
        private readonly RegisterTenantUseCase $registerTenantUseCase,
        private readonly CreateTenantOwnerUseCase $createTenantOwnerUseCase,
    ) {}

    public function register(RegisterTenantRequest $request): JsonResponse
    {
        try {
            $dto = CreateTenantDTO::fromArray($request->validated());
            $tenant = $this->registerTenantUseCase->execute($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Empresa registrada exitosamente.',
                'data'    => $tenant
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
                'error'   => $e->getMessage()
            ], Response::HTTP_CONFLICT);
        }
    }

    public function storeOwner(int $tenant, StoreTenantOwnerRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'No autenticado.',
                'data'    => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->can('tenant_owner_create')) {
            return response()->json([
                'status'  => false,
                'message' => 'No autorizado.',
                'data'    => null,
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $result = $this->createTenantOwnerUseCase->execute(
                $tenant,
                $request->input('name'),
                $request->input('email'),
            );

            return response()->json([
                'status'  => true,
                'message' => 'Propietario del tenant creado exitosamente.',
                'data'    => $result,
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            $httpCode = $e->getMessage() === 'El tenant especificado no existe.'
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_CONFLICT;

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $httpCode);
        }
    }

    public function update2faFlag(int $tenantId, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'No autenticado.',
                'data'    => null,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->can('role_manage')) {
            return response()->json([
                'status'  => false,
                'message' => 'No autorizado.',
                'data'    => null,
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        try {
            $tenant = Tenant::findOrFail($tenantId);
            $tenant->two_factor_enabled_for_intermediate_roles = $validated['enabled'];
            $tenant->save();

            $intermediateRoles = TwoFactorService::INTERMEDIATE_TENANT_ROLES;
            if (!empty($intermediateRoles)) {
                TenantUser::where('tenant_id', $tenantId)
                    ->whereIn('role', $intermediateRoles)
                    ->each(fn ($tu) => $tu->tokens()->delete());
            }

            return response()->json([
                'status'  => true,
                'message' => 'Configuración de 2FA actualizada correctamente.',
                'data'    => [
                    'two_factor_enabled_for_intermediate_roles' => $validated['enabled'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], Response::HTTP_CONFLICT);
        }
    }
}
