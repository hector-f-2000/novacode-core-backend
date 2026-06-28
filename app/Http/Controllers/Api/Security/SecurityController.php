<?php

namespace App\Http\Controllers\Api\Security;

use App\Http\Controllers\Controller;
use App\Models\Sanctum\PersonalAccessToken;
use App\Services\Security\SessionService;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SecurityController extends Controller
{
    private SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * GET /api/security/sessions
     * Obtener sesiones activas del usuario autenticado o de todos (si es admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $roleName = $user->role?->name;
        $isAdmin = $roleName === 'admin' || $roleName === 'super_admin';

        // Si es admin, puede filtrar por user_id; null = todos los usuarios
        $userId = $request->query('user_id');

        if (!$isAdmin && $userId && $userId != $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'No tienes permiso para ver las sesiones de otros usuarios',
            ], 403);
        }

        // Admin sin user_id explícito ve todos; no-admin ve solo sus sesiones
        $targetUserId = $isAdmin ? ($userId ? (int)$userId : null) : $user->id;

        // Obtener token actual del request
        $currentTokenId = $request->bearerToken()
            ? PersonalAccessToken::findToken($request->bearerToken())?->id
            : null;

        $sessions = $this->sessionService->getActiveSessionsAsDTO($targetUserId, $currentTokenId);

        return response()->json([
            'status' => true,
            'data' => array_map(fn($dto) => $dto->toArray(), $sessions),
        ]);
    }

    /**
     * DELETE /api/security/sessions/{tokenId}
     * Revocar una sesión específica.
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = auth()->user();
        $roleName = $user->role?->name;
        $isAdmin = $roleName === 'admin' || $roleName === 'super_admin';

        $token = PersonalAccessToken::find($tokenId);

        if (!$token) {
            return response()->json([
                'status' => false,
                'message' => 'Sesión no encontrada',
            ], 404);
        }

        // Validar que es el dueño o es admin
        if ($token->tokenable_id !== $user->id && !$isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'No tienes permiso para revocar esta sesión',
            ], 403);
        }

        $result = $this->sessionService->revokeToken($tokenId, $token->tokenable_id);

        if (!$result) {
            return response()->json([
                'status' => false,
                'message' => 'No se pudo revocar la sesión',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Sesión revocada correctamente',
        ]);
    }

    /**
     * POST /api/security/sessions/revoke-all-others
     * Revocar todas las demás sesiones excepto la actual.
     */
    public function revokeAllOtherSessions(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Obtener el token actual
        $currentToken = $request->bearerToken()
            ? PersonalAccessToken::findToken($request->bearerToken())
            : null;

        if (!$currentToken) {
            return response()->json([
                'status' => false,
                'message' => 'Token no válido',
            ], 401);
        }

        $revokedCount = $this->sessionService->revokeAllOtherSessions(
            $user->id,
            $currentToken->id
        );

        return response()->json([
            'status' => true,
            'message' => "Se revocaron {$revokedCount} sesiones",
            'data' => [
                'revoked_count' => $revokedCount,
            ],
        ]);
    }

    /**
     * GET /api/security/audit-logs
     * Obtener historial de auditoría.
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        $user = auth()->user();
        $roleName = $user->role?->name;
        $isAdmin = $roleName === 'admin' || $roleName === 'super_admin';

        $limit = (int)$request->query('limit', 10);
        $offset = (int)$request->query('offset', 0);
        $userId = $request->query('user_id');
        $eventType = $request->query('event_type');

        // Validar límite
        $limit = min($limit, 100);
        $limit = max($limit, 1);

        // Si no es admin, solo puede ver su propio historial
        if (!$isAdmin && $userId && $userId != $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'No tienes permiso para ver el historial de otros usuarios',
            ], 403);
        }

        $targetUserId = $isAdmin && $userId ? $userId : ($isAdmin ? null : $user->id);

        $result = $this->sessionService->getAuditLogs($targetUserId, $limit, $offset, $eventType);

        return response()->json([
            'status' => true,
            'data' => array_map(fn($dto) => $dto->toArray(), $result['data']),
            'pagination' => [
                'limit' => $result['limit'],
                'offset' => $result['offset'],
                'total' => $result['total'],
            ],
        ]);
    }
}
