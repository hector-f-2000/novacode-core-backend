<?php

namespace App\Services\Security;

use App\Events\AccountLocked;
use Illuminate\Support\Facades\Cache;

class LockoutService
{
    private const CACHE_PREFIX_USER = 'lockout:user:';
    private const CACHE_PREFIX_IP   = 'lockout:ip:';
    private const CACHE_TTL_SECONDS = 86400;

    private const USER_BACKOFF = [0, 0, 0, 0, 3, 5, 15, 30];
    private const IP_BACKOFF   = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 5, 15, 30];

    public function check(string $email, ?string $ip): array
    {
        $result = $this->checkKey(self::CACHE_PREFIX_USER, $this->hashEmail($email));
        if ($result['locked']) return $result;

        if ($ip !== null) {
            $result = $this->checkKey(self::CACHE_PREFIX_IP, $ip);
            if ($result['locked']) return $result;
        }

        return ['locked' => false, 'remaining_seconds' => 0];
    }

    public function increment(string $email, ?string $ip): int
    {
        $userKey = $this->hashEmail($email);
        $userCount = $this->incrementKey(self::CACHE_PREFIX_USER, $userKey);

        if ($ip !== null) {
            $this->incrementKey(self::CACHE_PREFIX_IP, $ip);
        }

        $backoff = $this->getBackoffMinutes(self::CACHE_PREFIX_USER, $userCount);
        if ($backoff > 0) {
            AccountLocked::dispatch($email, $ip ?? 'unknown', $backoff);
        }

        return $userCount;
    }

    public function reset(string $email, ?string $ip): void
    {
        Cache::forget(self::CACHE_PREFIX_USER . $this->hashEmail($email));
        if ($ip !== null) {
            Cache::forget(self::CACHE_PREFIX_IP . $ip);
        }
    }

    public function getRemainingMessage(string $email, ?string $ip): ?string
    {
        $result = $this->check($email, $ip);
        if (!$result['locked']) return null;

        $seconds = $result['remaining_seconds'];
        if ($seconds < 60) {
            return 'Demasiados intentos fallidos. Intenta de nuevo en menos de 1 minuto.';
        }
        $minutes = (int) ceil($seconds / 60);
        $label = $minutes === 1 ? 'minuto' : 'minutos';
        return "Demasiados intentos fallidos. Intenta de nuevo en {$minutes} {$label}.";
    }

    private function checkKey(string $prefix, string $key): array
    {
        $entry = Cache::get($prefix . $key);
        if (!$entry) return ['locked' => false, 'remaining_seconds' => 0];

        $backoff = $this->getBackoffMinutes($prefix, $entry['count']);
        if ($backoff === 0) return ['locked' => false, 'remaining_seconds' => 0];

        $elapsedSeconds = time() - $entry['created_at'];
        $remainingSeconds = ($backoff * 60) - $elapsedSeconds;

        if ($remainingSeconds <= 0) return ['locked' => false, 'remaining_seconds' => 0];

        return ['locked' => true, 'remaining_seconds' => $remainingSeconds];
    }

    private function incrementKey(string $prefix, string $key): int
    {
        $entry = Cache::get($prefix . $key);
        $count = $entry ? $entry['count'] + 1 : 1;

        Cache::put($prefix . $key, [
            'count'      => $count,
            'created_at' => time(),
        ], self::CACHE_TTL_SECONDS);

        return $count;
    }

    private function getBackoffMinutes(string $prefix, int $count): int
    {
        $map = $prefix === self::CACHE_PREFIX_USER ? self::USER_BACKOFF : self::IP_BACKOFF;
        return $map[$count] ?? end($map);
    }

    private function hashEmail(string $email): string
    {
        return md5(strtolower(trim($email)));
    }
}
