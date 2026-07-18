<?php

namespace App\Core\Auth\Application\UseCases;

use App\Core\Auth\Domain\Contracts\TenantAuthRepositoryInterface;
use App\Core\Auth\Infrastructure\DTOs\LoginDTO;
use App\Models\Security\SecurityAuditLog;
use App\Services\Auth\TwoFactorService;
use App\Services\Security\LockoutService;
use Illuminate\Support\Facades\Hash;
use Exception;

class TenantLoginUseCase
{
    public function __construct(
        private readonly TenantAuthRepositoryInterface $tenantAuthRepository,
        private readonly LockoutService $lockoutService,
        private readonly TwoFactorService $twoFactorService,
    ) {}

    public function execute(LoginDTO $dto): array
    {
        $message = $this->lockoutService->getRemainingMessage($dto->email, $dto->ip);
        if ($message) {
            throw new Exception($message);
        }

        $tenantUser = $this->tenantAuthRepository->findByEmail($dto->email);

        if (!$tenantUser || !Hash::check($dto->password, $tenantUser->password)) {
            $attemptCount = $this->lockoutService->increment($dto->email, $dto->ip);
            $this->logFailure($dto->email, null, 'Credenciales inválidas.', $dto, $attemptCount);
            $message = $this->lockoutService->getRemainingMessage($dto->email, $dto->ip);
            if ($message) {
                throw new Exception($message);
            }
            throw new Exception('Credenciales inválidas.');
        }

        if (!$tenantUser->status) {
            $this->logFailure($tenantUser->email, $tenantUser->id, 'Usuario desactivado.', $dto, 0);
            throw new Exception('Credenciales inválidas.');
        }

        $tenantStatus = $this->tenantAuthRepository->findTenantStatusByUserId($tenantUser->id);

        if (!in_array($tenantStatus, ['trial', 'active'], true)) {
            $this->logFailure($tenantUser->email, $tenantUser->id, "Tenant suspendido o cancelado: {$tenantStatus}", $dto, 0);
            throw new Exception('Credenciales inválidas.');
        }

        $this->lockoutService->reset($dto->email, $dto->ip);

        if ($this->twoFactorService->isRequiredForTenant($tenantUser)) {
            $sessionToken = $this->twoFactorService->generateOtp('tenant', $tenantUser, $tenantUser->email);
            return [
                'requires_2fa'  => true,
                'session_token' => $sessionToken,
                'user'  => [
                    'id'        => $tenantUser->id,
                    'name'      => $tenantUser->name,
                    'email'     => $tenantUser->email,
                    'role'      => $tenantUser->role,
                    'tenant_id' => $tenantUser->tenant_id,
                ],
            ];
        }

        $token = $this->tenantAuthRepository->createToken(
            $tenantUser,
            'tenant_session',
            now()->addHours(24)
        );

        return [
            'token' => $token,
            'user'  => [
                'id'        => $tenantUser->id,
                'name'      => $tenantUser->name,
                'email'     => $tenantUser->email,
                'role'      => $tenantUser->role,
                'tenant_id' => $tenantUser->tenant_id,
            ],
        ];
    }

    private function logFailure(string $email, ?int $tenantUserId, string $reason, LoginDTO $dto, int $attemptCount): void
    {
        SecurityAuditLog::create([
            'user_id'      => $tenantUserId,
            'event_type'   => 'tenant_login_failed',
            'ip_address'   => $dto->ip,
            'user_agent'   => $dto->userAgent,
            'description'  => "Login fallido para {$email}: {$reason}",
            'attempt_count'=> $attemptCount,
        ]);
    }
}
