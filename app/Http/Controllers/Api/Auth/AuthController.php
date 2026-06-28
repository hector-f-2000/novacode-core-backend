<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\SessionService;
use Illuminate\Http\Request;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    private SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Maneja el inicio de sesión y la generación de tokens.
     */
    public function login(Request $request): JsonResponse {
        // 1. Validar datos de entrada
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. Eager Loading: Traemos perfil y ROL
        $user = User::with(['profile', 'role'])->where('username', $request->username)->first();

        // 3. Verificar credenciales
        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->sessionService->recordLoginFailed(
                $request->username,
                $request->ip(),
                $request->header('User-Agent') ?? ''
            );

            throw ValidationException::withMessages([
                'username' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // 4. Validar si el usuario está activo
        if (!$user->status) {
            return response()->json([
                'status'  => 'error',
                'mensaje' => 'Tu cuenta se encuentra inactiva. Contacta al administrador.'
            ], 403);
        }

        // 5. Generar Token con SessionService (incluye metadatos y auditoría)
        $token = $this->sessionService->recordLoginSuccess(
            $user,
            $request->ip(),
            $request->header('User-Agent') ?? ''
        );

        // 6. Respuesta con objeto de usuario completo
        return response()->json([
            'status'  => 'success',
            'mensaje' => '¡Inicio de sesión exitoso!',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'        => $user->id,
                    'username'  => $user->username,
                    'full_name' => $user->full_name,
                    'email'     => $user->email,
                    'role'      => $user->role
                ]
            ]
        ], 200);
    }

    /**
     * Maneja el cierre de sesión.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revocamos solo el token que se está usando en este momento
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'mensaje' => 'Sesión cerrada correctamente'
        ], 200);
    }

    /**
     * Obtiene los datos del usuario autenticado actual.
     */
    public function me(Request $request): JsonResponse{
        // Cargamos las relaciones necesarias para que el front reconstruya el estado
        $user = $request->user()->load(['profile', 'role']);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'        => $user->id,
                'username'  => $user->username,
                'full_name' => $user->full_name,
                'email'     => $user->email,
                'role'      => $user->role,
                'status'    => $user->status
            ]
        ], 200);
    }
}