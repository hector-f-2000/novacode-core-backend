<?php

namespace App\Http\Controllers\Core\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Core\Auth\Application\UseCases\TenantLoginUseCase;
use App\Core\Auth\Domain\Contracts\TenantAuthRepositoryInterface;
use App\Core\Auth\Infrastructure\DTOs\LoginDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TenantAuthController extends Controller
{
    public function __construct(
        private readonly TenantLoginUseCase $tenantLoginUseCase,
        private readonly TenantAuthRepositoryInterface $tenantAuthRepository
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = array_merge($request->validated(), [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent() ?? '',
            ]);
            $dto = LoginDTO::fromArray($validated);
            $result = $this->tenantLoginUseCase->execute($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Autenticación exitosa. Sesión iniciada.',
                'data'    => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], 401);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('tenant');

        return response()->json([
            'status'  => true,
            'message' => 'Datos del usuario autenticado obtenidos.',
            'data'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tenantAuthRepository->revokeCurrentToken($request->user('tenant'));

        return response()->json([
            'status'  => true,
            'message' => 'Sesión cerrada exitosamente.',
            'data'    => null,
        ]);
    }
}
