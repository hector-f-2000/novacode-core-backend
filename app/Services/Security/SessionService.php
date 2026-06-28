<?php

namespace App\Services\Security;

use App\DTOs\Security\SessionDTO;
use App\DTOs\Security\AuditLogDTO;
use App\Models\User\User;
use App\Models\Security\SecurityAuditLog;
use App\Utilities\DeviceParser;
use App\Utilities\GeoIPParser;
use App\Models\Sanctum\PersonalAccessToken;
use Illuminate\Database\Eloquent\Collection;

class SessionService
{
    /**
     * Registra un login exitoso creando un token y evento de auditoría.
     */
    public function recordLoginSuccess(User $user, string $ip, string $userAgent): string
    {
        $device = DeviceParser::parse($userAgent);
        $location = GeoIPParser::getLocation($ip);
        $locationString = "{$location['city']}, {$location['country_code']}";

        // Crear token en personal_access_tokens
        $newToken = $user->createToken(
            name: $device['device_name'],
            abilities: ['*']
        );

        // Actualizar el modelo directamente con metadatos
        // NOTA: No re-hashear el plainTextToken; Sanctum almacena hash del random interno,
        // no del string completo "id|random". Usamos el modelo devuelto por createToken.
        $newToken->accessToken->update([
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_name' => $device['device_name'],
            'location' => $locationString,
            'is_revoked' => false,
        ]);

        // Registrar evento de auditoría
        SecurityAuditLog::create([
            'user_id' => $user->id,
            'event_type' => 'login_success',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_name' => $device['device_name'],
            'location' => $locationString,
            'description' => "Login exitoso desde {$device['device_name']}",
        ]);

        return $newToken->plainTextToken;
    }

    /**
     * Registra un intento de login fallido en auditoría.
     */
    public function recordLoginFailed(string $email, string $ip, string $userAgent, int $attemptCount = 1): void
    {
        $device = DeviceParser::parse($userAgent);
        $location = GeoIPParser::getLocation($ip);
        $locationString = "{$location['city']}, {$location['country_code']}";

        SecurityAuditLog::create([
            'user_id' => null, // No sabemos quién es
            'event_type' => 'login_failed',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_name' => $device['device_name'],
            'location' => $locationString,
            'attempt_count' => $attemptCount,
            'description' => "Intento fallido de login para {$email} (intento #{$attemptCount})",
        ]);
    }

    /**
     * Obtiene sesiones activas. Si userId es null, obtiene todas (solo admin).
     */
    public function getUserActiveSessions(?int $userId = null): Collection
    {
        $query = PersonalAccessToken::with('tokenable.profile')
            ->where('tokenable_type', User::class)
            ->where('is_revoked', false);

        if ($userId) {
            $query->where('tokenable_id', $userId);
        }

        return $query->orderByDesc('last_used_at')->get();
    }

    /**
     * Convierte sesiones activas a DTOs. userId null = todas las sesiones.
     */
    public function getActiveSessionsAsDTO(?int $userId = null, ?int $currentTokenId = null): array
    {
        $sessions = $this->getUserActiveSessions($userId);
        $dtos = [];

        foreach ($sessions as $session) {
            /** @var User|null $user */
            $user = $session->tokenable;

            $dtos[] = new SessionDTO(
                token_id: $session->id,
                device_name: $session->device_name ?? 'Unknown',
                ip_address: $session->ip_address ?? 'Unknown',
                location: $session->location,
                last_used_at: $session->last_used_at?->format('d/m/Y H:i'),
                is_current: $currentTokenId && $session->id === $currentTokenId,
                user_id: $user?->id,
                username: $user?->username,
                full_name: $user?->full_name,
                firstname: $user?->profile?->firstname,
                lastname: $user?->profile?->lastname,
            );
        }

        return $dtos;
    }

    /**
     * Revoca un token específico marcándolo como revocado.
     */
    public function revokeToken(int $tokenId, int $userId): bool
    {
        $token = PersonalAccessToken::find($tokenId);

        if (!$token || $token->tokenable_id !== $userId) {
            return false;
        }

        $token->update(['is_revoked' => true]);

        // Registrar evento de auditoría
        SecurityAuditLog::create([
            'user_id' => $userId,
            'event_type' => 'session_revoked',
            'ip_address' => $token->ip_address,
            'user_agent' => $token->user_agent,
            'device_name' => $token->device_name,
            'location' => $token->location,
            'description' => "Sesión revocada: {$token->device_name}",
        ]);

        return true;
    }

    /**
     * Revoca todos los tokens de un usuario excepto uno.
     */
    public function revokeAllOtherSessions(int $userId, int $currentTokenId): int
    {
        $revokedCount = PersonalAccessToken::where('tokenable_id', $userId)
            ->where('tokenable_type', User::class)
            ->where('id', '!=', $currentTokenId)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);

        // Registrar evento de auditoría
        SecurityAuditLog::create([
            'user_id' => $userId,
            'event_type' => 'sessions_revoked_all',
            'description' => "Se revocaron {$revokedCount} sesiones",
        ]);

        return $revokedCount;
    }

    /**
     * Obtiene el historial de auditoría con filtros opcionales.
     */
    public function getAuditLogs(?int $userId = null, int $limit = 10, int $offset = 0, ?string $eventType = null): array
    {
        $query = SecurityAuditLog::with('user.profile');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $total = (clone $query)->count();

        $logs = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $dtos = [];
        foreach ($logs as $log) {
            $user = $log->user;

            $dtos[] = new AuditLogDTO(
                user_id: $log->user_id ?? 0,
                event_type: $log->event_type,
                ip_address: $log->ip_address,
                user_agent: $log->user_agent,
                device_name: $log->device_name,
                location: $log->location,
                attempt_count: $log->attempt_count,
                description: $log->description,
                created_at: $log->created_at->format('d/m/Y H:i'),
                username: $user?->username,
                firstname: $user?->profile?->firstname,
                lastname: $user?->profile?->lastname,
            );
        }

        return [
            'data' => $dtos,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
}
