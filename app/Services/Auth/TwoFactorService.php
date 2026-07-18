<?php

namespace App\Services\Auth;

use App\Jobs\SendOtpEmail;
use App\Models\Auth\UserOtp;
use App\Models\Tenant\TenantUser;
use App\Models\User\User;
use Exception;
use Illuminate\Support\Facades\Hash;

class TwoFactorService
{
    // Poblar con roles como 'tenant_admin' cuando existan.
    // Mientras esté vacío, la columna two_factor_enabled_for_intermediate_roles
    // en la tabla tenants no tiene efecto — ningún rol intermedio gatilla 2FA.
    public const INTERMEDIATE_TENANT_ROLES = [];
    private const CODE_TTL_MINUTES = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const CODE_DIGITS = 6;

    public function __construct(
        private readonly UserOtp $userOtp,
    ) {}

    public function isRequiredForStaff(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function isRequiredForTenant(TenantUser $user): bool
    {
        if ($user->role === 'tenant_owner') return true;

        if (in_array($user->role, self::INTERMEDIATE_TENANT_ROLES, true)) {
            return $user->tenant?->two_factor_enabled_for_intermediate_roles ?? false;
        }

        return false;
    }

    public function generateOtp(string $userType, User|TenantUser $user, string $email): string
    {
        $this->userOtp
            ->where('user_type', $userType)
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->where('code_expires_at', '>', now())
            ->update(['code_expires_at' => now()]);

        $code = (string) random_int(10 ** (self::CODE_DIGITS - 1), (10 ** self::CODE_DIGITS) - 1);
        $sessionToken = bin2hex(random_bytes(32));

        $this->userOtp->create([
            'user_type'          => $userType,
            'user_id'            => $user->id,
            'email'              => $email,
            'code_hash'          => Hash::make($code),
            'session_token'      => $sessionToken,
            'code_expires_at'    => now()->addMinutes(self::CODE_TTL_MINUTES),
            'resend_allowed_at'  => now()->addSeconds(self::RESEND_COOLDOWN_SECONDS),
            'attempts_remaining' => 3,
        ]);

        SendOtpEmail::dispatch($email, $code);

        return $sessionToken;
    }

    public function resendOtp(string $sessionToken): string
    {
        $otp = $this->userOtp
            ->where('session_token', $sessionToken)
            ->whereNull('verified_at')
            ->where('code_expires_at', '>', now())
            ->first();

        if (!$otp) {
            throw new Exception('Sesión de verificación expirada o inválida. Inicia sesión nuevamente.');
        }

        if ($otp->resend_allowed_at->isFuture()) {
            $seconds = (int) now()->diffInSeconds($otp->resend_allowed_at);
            throw new Exception("Espera {$seconds} segundos antes de reenviar el código.");
        }

        $code = (string) random_int(10 ** (self::CODE_DIGITS - 1), (10 ** self::CODE_DIGITS) - 1);

        $otp->update([
            'code_hash'          => Hash::make($code),
            'code_expires_at'    => now()->addMinutes(self::CODE_TTL_MINUTES),
            'resend_allowed_at'  => now()->addSeconds(self::RESEND_COOLDOWN_SECONDS),
            'attempts_remaining' => 3,
        ]);

        SendOtpEmail::dispatch($otp->email, $code);

        return $otp->session_token;
    }
}
