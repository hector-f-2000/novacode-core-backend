<?php

namespace App\Core\Auth\Application\UseCases;

use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Core\Auth\Infrastructure\DTOs\LoginDTO;
use App\Models\Security\SecurityAuditLog;
use App\Services\Auth\TwoFactorService;
use App\Services\Security\LockoutService;
use Illuminate\Support\Facades\Hash;
use Exception;

class LoginUseCase
{
    public function __construct(
        private readonly AuthRepositoryInterface $authRepository,
        private readonly LockoutService $lockoutService,
        private readonly TwoFactorService $twoFactorService,
    ) {}

    public function execute(LoginDTO $dto): array
    {
        $message = $this->lockoutService->getRemainingMessage($dto->email, $dto->ip);
        if ($message) {
            throw new Exception($message);
        }

        $user = $this->authRepository->findByEmail($dto->email);

        if (!$user || !Hash::check($dto->password, $user->password)) {
            $attemptCount = $this->lockoutService->increment($dto->email, $dto->ip);
            $this->logFailure($dto->email, null, 'Credenciales inválidas.', $dto, $attemptCount);
            $message = $this->lockoutService->getRemainingMessage($dto->email, $dto->ip);
            if ($message) {
                throw new Exception($message);
            }
            throw new Exception('Las credenciales proporcionadas son incorrectas.');
        }

        if (!$user->status) {
            $this->logFailure($user->email, $user->id, 'Usuario desactivado.', $dto, 0);
            throw new Exception('El acceso a este administrador se encuentra desactivado.');
        }

        $this->lockoutService->reset($dto->email, $dto->ip);

        if ($this->twoFactorService->isRequiredForStaff($user)) {
            $sessionToken = $this->twoFactorService->generateOtp('staff', $user, $user->email);
            return [
                'requires_2fa' => true,
                'session_token' => $sessionToken,
                'user'  => [
                    'id'        => $user->id,
                    'email'     => $user->email,
                    'full_name' => $user->full_name,
                ],
            ];
        }

        $token = $this->authRepository->createToken(
            $user,
            'core_master_session',
            now()->addHours(8)
        );

        return [
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'email'     => $user->email,
                'full_name' => $user->full_name,
                'profile'   => $user->profile
            ]
        ];
    }

    private function logFailure(string $email, ?int $userId, string $reason, LoginDTO $dto, int $attemptCount): void
    {
        SecurityAuditLog::create([
            'user_id'      => $userId,
            'event_type'   => 'login_failed',
            'ip_address'   => $dto->ip,
            'user_agent'   => $dto->userAgent,
            'description'  => "Login fallido para {$email}: {$reason}",
            'attempt_count'=> $attemptCount,
        ]);
    }
}
