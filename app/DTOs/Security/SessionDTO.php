<?php

namespace App\DTOs\Security;

class SessionDTO
{
    public function __construct(
        public int $token_id,
        public string $device_name,
        public string $ip_address,
        public ?string $location,
        public ?string $last_used_at,
        public bool $is_current = false,
        public ?int $user_id = null,
        public ?string $username = null,
        public ?string $full_name = null,
        public ?string $firstname = null,
        public ?string $lastname = null,
    ) {}

    /**
     * Convertir DTO a array para respuesta JSON.
     */
    public function toArray(): array
    {
        return [
            'token_id' => $this->token_id,
            'device_name' => $this->device_name,
            'ip_address' => $this->ip_address,
            'location' => $this->location,
            'last_used_at' => $this->last_used_at,
            'is_current' => $this->is_current,
            'user_id' => $this->user_id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
        ];
    }
}
