<?php

namespace App\Http\Controllers\Api\Auth;

use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Core\Auth\Domain\Contracts\TenantAuthRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Auth\UserOtp;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use App\Services\Auth\TwoFactorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OtpController extends Controller
{
    public function __construct(
        private readonly AuthRepositoryInterface $authRepository,
        private readonly TenantAuthRepositoryInterface $tenantAuthRepository,
        private readonly TwoFactorService $twoFactorService,
    ) {}

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'string', 'size:64'],
            'code'          => ['required', 'string', 'digits:6'],
        ]);

        $otp = UserOtp::where('session_token', $request->session_token)
            ->whereNull('verified_at')
            ->where('attempts_remaining', '>', 0)
            ->where('code_expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json([
                'status'  => false,
                'message' => 'Sesión de verificación expirada o inválida. Inicia sesión nuevamente.',
            ], 401);
        }

        if (!Hash::check($request->code, $otp->code_hash)) {
            $otp->decrement('attempts_remaining');
            if ($otp->attempts_remaining === 0) {
                $otp->update(['code_expires_at' => now()]);
                return response()->json([
                    'status'  => false,
                    'message' => 'Código agotado. Debes iniciar sesión nuevamente.',
                ], 401);
            }
            return response()->json([
                'status'  => false,
                'message' => 'Código incorrecto.',
            ], 401);
        }

        $otp->update(['verified_at' => now()]);

        $token = match ($otp->user_type) {
            'staff'  => $this->authRepository->createToken(
                User::findOrFail($otp->user_id),
                'core_master_session',
                now()->addHours(8)
            ),
            'tenant' => $this->tenantAuthRepository->createToken(
                TenantUser::findOrFail($otp->user_id),
                'tenant_session',
                now()->addHours(24)
            ),
            default => throw new Exception('Tipo de usuario OTP no reconocido: ' . $otp->user_type),
        };

        $user = match ($otp->user_type) {
            'staff'  => User::find($otp->user_id),
            'tenant' => TenantUser::find($otp->user_id),
            default => throw new Exception('Tipo de usuario OTP no reconocido: ' . $otp->user_type),
        };

        return response()->json([
            'status'  => true,
            'message' => 'Autenticación exitosa.',
            'data'    => [
                'token' => $token,
                'user'  => $user,
            ],
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'string', 'size:64'],
        ]);

        try {
            $sessionToken = $this->twoFactorService->resendOtp($request->session_token);

            return response()->json([
                'status'  => true,
                'message' => 'Código reenviado al correo registrado.',
                'data'    => [
                    'session_token' => $sessionToken,
                ],
            ]);
        } catch (Exception $e) {
            $status = str_contains($e->getMessage(), 'Espera') ? 429 : 401;
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
