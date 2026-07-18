<?php

namespace App\Core\Auth\Domain\Contracts;

use App\Models\User\User;
use DateTimeInterface;

interface AuthRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function createToken(User $user, string $tokenName, ?DateTimeInterface $expiresAt = null): string;

    public function revokeCurrentToken(User $user): bool;
}
