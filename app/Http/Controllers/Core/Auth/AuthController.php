<?php

namespace App\Http\Controllers\Core\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Core\Auth\Application\UseCases\LoginUseCase;
use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Core\Auth\Infrastructure\DTOs\LoginDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly AuthRepositoryInterface $authRepository
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $validated = array_merge($request->validated(), [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent() ?? '',
            ]);
            $dto = LoginDTO::fromArray($validated);
            $result = $this->loginUseCase->execute($dto);

            return response()->json([
                'status'  => true,
                'message' => 'Autenticación exitosa. Sesión iniciada.',
                'data'    => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
                'data'    => null
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authRepository->revokeCurrentToken($request->user());

        return response()->json([
            'status'  => true,
            'message' => 'Sesión cerrada exitosamente.',
            'data'    => null
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'status'  => true,
            'message' => 'Datos del administrador autenticado obtenidos.',
            'data'    => [
                'id'        => $user->id,
                'email'     => $user->email,
                'full_name' => $user->full_name,
                'profile'   => $user->profile
            ]
        ]);
    }
}
