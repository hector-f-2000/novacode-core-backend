<?php

namespace App\DTOs\Security;

class AuditLogDTO
{
    public function __construct(
        public int $user_id,
        public string $event_type,
        public ?string $ip_address,
        public ?string $user_agent,
        public ?string $device_name,
        public ?string $location,
        public int $attempt_count = 0,
        public ?string $description = null,
        public ?string $created_at = null,
        public ?string $username = null,
        public ?string $firstname = null,
        public ?string $lastname = null,
    ) {}

    /**
     * Convertir DTO a array para respuesta JSON.
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'event_type' => $this->event_type,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device_name' => $this->device_name,
            'location' => $this->location,
            'attempt_count' => $this->attempt_count,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'username' => $this->username,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
        ];
    }
}
