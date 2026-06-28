<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Sanctum\PersonalAccessToken;

class ValidateNotRevokedToken
{
    /**
     * Valida que el token no haya sido revocado.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken()) {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($request->bearerToken());

        if ($token && $token->is_revoked) {
            return response()->json([
                'status' => false,
                'message' => 'Tu sesión ha sido revocada. Por favor inicia sesión nuevamente.',
            ], 401);
        }

        return $next($request);
    }
}
