<?php

namespace App\Core\Auth\Infrastructure\DTOs;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: strtolower(trim($data['email'])),
            password: $data['password'],
            ip: $data['ip'] ?? null,
            userAgent: $data['user_agent'] ?? null,
        );
    }
}
