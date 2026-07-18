<?php

namespace App\Core\Auth\Infrastructure\Eloquent;

use App\Core\Auth\Domain\Contracts\AuthRepositoryInterface;
use App\Models\User\User;
use DateTimeInterface;

class AuthEloquentRepository implements AuthRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::with('profile')->where('email', $email)->first();
    }

    public function createToken(User $user, string $tokenName, ?DateTimeInterface $expiresAt = null): string
    {
        return $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;
    }

    public function revokeCurrentToken(User $user): bool
    {
        return $user->currentAccessToken()?->delete() ?? false;
    }
}
